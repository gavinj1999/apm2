<!-- resources/js/components/Dashboard/ManifestTable.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';

const props = defineProps<{
    flattenedRows: Array<{
        date: string;
        manifest: {
            id: number;
            manifest_number: string;
            delivery_date: string;
            round_id: string;
            quantities: Array<{
                parcel_type_id: number;
                name: string;
                manifested: number;
                re_manifested: number;
                carried_forward: number;
                total: number;
                value: number;
            }>;
            total_value: number;
        };
        isFirstInDate: boolean;
        dateRowspan: number;
    }>;
    parcelTypes: Array<{ id: number; name: string }>;
    rounds: Array<{ id: number; round_id: string; name: string }>;
    holidays?: Array<{
        id: number;
        start_date: string;
        end_date: string;
        daily_rate: number | string;
    }>;
}>();

const emit = defineEmits(['editManifest', 'editHoliday']);

// Default to empty array if holidays is undefined
const safeHolidays = props.holidays ?? [];

// State for sorting
const sortColumn = ref<'date' | 'totalValue' | 'totalParcels' | 'averageRate' | null>(null);
const sortDirection = ref<'asc' | 'desc'>('asc');

// State for sliding panel
const isPanelOpen = ref(false);
const selectedRow = ref<{
    type: 'manifest' | 'holiday';
    data: any;
} | null>(null);

// List of parcel type names to center and hide on mobile
const centeredParcelTypes = [
    'Postable',
    'Small Packet',
    'Packet',
    'Parcels',
    'Heavy',
    'Hanging Garment',
    'Heavy / Large',
    'Collections',
    'Sunday Rate',
];

// Calculate total number of columns (fixed + dynamic parcelTypes)
const totalColumns = computed(() => 6 + props.parcelTypes.length); // Date, Round, Total Parcels, parcelTypes, Avg. Parcel Rate, Total Value, Actions

// Toggle sorting for a column
function toggleSort(column: 'date' | 'totalValue' | 'totalParcels' | 'averageRate') {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
}

// Helper function to format date as DD/MM/YYYY
function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) {
        return 'Invalid Date';
    }
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

function getRoundName(roundId: string): string {
    const round = props.rounds.find(r => r.id === Number(roundId));
    return round ? round.name : 'Unknown Round';
}

// Compute total parcels for a manifest
function getTotalParcels(quantities: Array<{ total: number }>): number {
    return quantities.reduce((sum, quantity) => sum + (quantity.total || 0), 0);
}

// Compute average parcel rate for a manifest
function getAverageParcelRate(totalValue: number, quantities: Array<{ total: number }>): string {
    const totalParcels = getTotalParcels(quantities);
    return totalParcels > 0 ? (totalValue / totalParcels).toFixed(2) : '0.00';
}

// Format daily rate to fixed 2 decimals
function formatDailyRate(rate: number | string): string {
    const numericRate = typeof rate === 'string' ? parseFloat(rate) : rate;
    return isNaN(numericRate) ? '0.00' : numericRate.toFixed(2);
}

// Open sliding panel
function openDetails(row: { type: 'manifest' | 'holiday'; data: any }) {
    selectedRow.value = row;
    isPanelOpen.value = true;
}

// Close sliding panel
function closePanel() {
    isPanelOpen.value = false;
    selectedRow.value = null;
}

// Compute footer totals (only for manifests, excluding holidays)
const footerTotals = computed(() => {
    const totals: { [key: number]: number } = {};
    let totalValueSum = 0;
    let totalParcelsSum = 0;

    props.parcelTypes.forEach(type => {
        totals[type.id] = 0;
    });

    props.flattenedRows.forEach(row => {
        row.manifest.quantities.forEach(quantity => {
            totals[quantity.parcel_type_id] += quantity.total || 0;
        });
        totalValueSum += row.manifest.total_value || 0;
        totalParcelsSum += getTotalParcels(row.manifest.quantities);
    });

    return {
        parcelTypeTotals: totals,
        totalValueSum,
        totalParcelsSum,
    };
});

// Compute holiday summaries with validation
const holidaySummaries = computed(() => {
    return safeHolidays
        .map(holiday => {
            const start = new Date(holiday.start_date);
            const end = new Date(holiday.end_date);

            if (isNaN(start.getTime()) || isNaN(end.getTime())) {
                console.error('Invalid date for holiday:', holiday);
                return null;
            }

            const days = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;
            const dailyRate = typeof holiday.daily_rate === 'string' ? parseFloat(holiday.daily_rate) : holiday.daily_rate;
            const totalPayment = isNaN(dailyRate) ? 0 : days * dailyRate;

            return {
                id: holiday.id,
                dateRange: `${formatDate(holiday.start_date)} - ${formatDate(holiday.end_date)}`,
                days,
                dailyRate,
                totalPayment,
            };
        })
        .filter(holiday => holiday !== null);
});

// Combine manifest rows and holiday rows, sorted by the selected column
const combinedRows = computed(() => {
    const manifestRows = props.flattenedRows.map(row => ({
        type: 'manifest' as const,
        data: row,
        sortDate: new Date(row.date).getTime(),
        sortValue: row.manifest.total_value || 0,
        sortTotalParcels: getTotalParcels(row.manifest.quantities),
        sortAverageRate: parseFloat(getAverageParcelRate(row.manifest.total_value, row.manifest.quantities)),
    }));

    const holidayRows = holidaySummaries.value.map(holiday => ({
        type: 'holiday' as const,
        data: holiday,
        sortDate: new Date(holiday.dateRange.split(' - ')[0]).getTime(),
        sortValue: holiday.totalPayment,
        sortTotalParcels: 0,
        sortAverageRate: 0,
    }));

    const rows = [...manifestRows, ...holidayRows];

    // Apply sorting
    if (sortColumn.value) {
        rows.sort((a, b) => {
            let compareValue = 0;
            if (sortColumn.value === 'date') {
                compareValue = a.sortDate - b.sortDate;
            } else if (sortColumn.value === 'totalValue') {
                compareValue = a.sortValue - b.sortValue;
            } else if (sortColumn.value === 'totalParcels') {
                compareValue = a.sortTotalParcels - b.sortTotalParcels;
            } else if (sortColumn.value === 'averageRate') {
                compareValue = a.sortAverageRate - b.sortAverageRate;
            }
            return sortDirection.value === 'asc' ? compareValue : -compareValue;
        });
    }

    return rows;
});

// Function to delete a manifest
function deleteManifest(id: number, date: string) {
    if (confirm(`Are you sure you want to delete the manifest for ${formatDate(date)}?`)) {
        router.delete(route('manifests.destroy', id), {
            onSuccess: () => {
                toast.success('Manifest deleted successfully!');
            },
            onError: (errors) => {
                toast.error('Failed to delete manifest: ' + Object.values(errors).join(', '));
            },
        });
    }
}

// Function to edit a manifest
function editManifest(id: number) {
    emit('editManifest', id);
}

// Function to edit a holiday
function editHoliday(id: number) {
    emit('editHoliday', id);
}

// Function to delete a holiday
function deleteHoliday(id: number) {
    if (!confirm('Are you sure you want to delete this holiday?')) return;
    router.delete(route('holidays.destroy', id), {
        onSuccess: () => {
            toast.success('Holiday deleted successfully!');
        },
        onError: (errors) => {
            toast.error('Failed to delete holiday: ' + Object.values(errors).join(', '));
        },
    });
}
</script>

<template>
    <div class="relative">
        <div v-if="combinedRows.length === 0" class="text-gray-400">
            No manifests or holidays available for this period.
        </div>
        <div v-else class="bg-gray-800 rounded-lg shadow-lg overflow-x-auto">
            <table class="w-full border border-gray-600 table-fixed">
                <thead>
                    <tr class="bg-gray-700">
                        <th
                            class="p-2 sm:p-1 text-left text-sm font-medium cursor-pointer"
                            @click="toggleSort('date')"
                            :aria-sort="sortColumn === 'date' ? sortDirection : 'none'"
                            aria-label="Sort by date"
                        >
                            Date
                            <span v-if="sortColumn === 'date'" class="ml-1">
                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th class="p-2 sm:p-1 text-left text-sm font-medium">Round</th>
                        <th
                            class="p-2 sm:p-1 text-left text-sm font-medium cursor-pointer"
                            @click="toggleSort('totalParcels')"
                            :aria-sort="sortColumn === 'totalParcels' ? sortDirection : 'none'"
                            aria-label="Sort by total parcels"
                        >
                            Total Parcels
                            <span v-if="sortColumn === 'totalParcels'" class="ml-1">
                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th
                            v-for="type in parcelTypes"
                            :key="type.id"
                            class="p-1 text-sm font-medium text-center hidden sm:table-cell rotated-header"
                        >
                            {{ type.name }}
                        </th>
                        <th
                            class="p-2 sm:p-1 text-left text-sm font-medium cursor-pointer"
                            @click="toggleSort('averageRate')"
                            :aria-sort="sortColumn === 'averageRate' ? sortDirection : 'none'"
                            aria-label="Sort by average parcel rate"
                        >
                            Avg. Parcel Rate
                            <span v-if="sortColumn === 'averageRate'" class="ml-1">
                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th
                            class="p-2 sm:p-1 text-left text-sm font-medium cursor-pointer"
                            @click="toggleSort('totalValue')"
                            :aria-sort="sortColumn === 'totalValue' ? sortDirection : 'none'"
                            aria-label="Sort by total value"
                        >
                            Total Value
                            <span v-if="sortColumn === 'totalValue'" class="ml-1">
                                {{ sortDirection === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th class="p-2 sm:p-1 text-left text-sm font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in combinedRows"
                        :key="row.type === 'manifest' ? row.data.manifest.id : `holiday-${row.data.id}`"
                        :class="row.type === 'holiday' ? 'bg-gray-600' : 'border-b border-gray-600'"
                    >
                        <td class="p-2 sm:p-0.5">
                            <span v-if="row.type === 'manifest' && row.data.isFirstInDate" :rowspan="row.data.dateRowspan">
                                {{ formatDate(row.data.date) }}
                            </span>
                            <span v-else-if="row.type === 'holiday'">
                                {{ row.data.dateRange }}
                            </span>
                        </td>
                        <td class="p-2 sm:p-0.5">
                            <span v-if="row.type === 'manifest'">
                                {{ getRoundName(row.data.manifest.round_id) }}
                            </span>
                            <span v-else>
                                Holiday
                            </span>
                        </td>
                        <td class="p-2 sm:p-0.5">
                            <span v-if="row.type === 'manifest'">
                                {{ getTotalParcels(row.data.manifest.quantities) }}
                            </span>
                            <span v-else>
                                —
                            </span>
                        </td>
                        <td
                            v-for="(type, idx) in parcelTypes"
                            :key="type.id"
                            class="p-1 text-center hidden sm:table-cell relative group"
                        >
                            <span v-if="row.type === 'manifest'" class="cursor-pointer">
                                {{ row.data.manifest.quantities.find(q => q.parcel_type_id === type.id)?.total ?? 0 }}
                                <span
                                    class="absolute hidden group-hover:block bg-gray-600 text-white text-xs rounded py-1 px-2 -mt-8 left-1/2 transform -translate-x-1/2"
                                >
                                    Value: £{{ (row.data.manifest.quantities.find(q => q.parcel_type_id === type.id)?.value || 0).toFixed(2) }}
                                </span>
                            </span>
                            <span v-else-if="idx === 0">
                                {{ row.data.days }} days @ £{{ formatDailyRate(row.data.dailyRate) }}/day
                            </span>
                            <span v-else>
                                —
                            </span>
                        </td>
                        <td class="p-2 sm:p-0.5">
                            <span v-if="row.type === 'manifest'">
                                £{{ getAverageParcelRate(row.data.manifest.total_value, row.data.manifest.quantities) }}
                            </span>
                            <span v-else>
                                —
                            </span>
                        </td>
                        <td class="p-2 sm:p-0.5">
                            <span v-if="row.type === 'manifest'">
                                £{{ (row.data.manifest.total_value || 0).toFixed(2) }}
                            </span>
                            <span v-else>
                                £{{ row.data.totalPayment.toFixed(2) }}
                            </span>
                        </td>
                        <td class="p-2 sm:p-0.5 flex gap-1 flex-nowrap">
                            <button
                                @click="openDetails(row)"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-1 py-0.5 rounded-md text-xs"
                                aria-label="View details"
                            >
                                Details
                            </button>
                            <template v-if="row.type === 'manifest'">
                                <button
                                    @click="editManifest(row.data.manifest.id)"
                                    class="hidden sm:inline-block bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-1 py-0.5 rounded-md text-xs"
                                    aria-label="Edit manifest"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteManifest(row.data.manifest.id, row.data.manifest.delivery_date)"
                                    class="hidden sm:inline-block bg-red-600 hover:bg-red-700 text-white font-semibold px-1 py-0.5 rounded-md text-xs"
                                    aria-label="Delete manifest"
                                >
                                    Delete
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    @click="editHoliday(row.data.id)"
                                    class="hidden sm:inline-block bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-1 py-0.5 rounded-md text-xs"
                                    aria-label="Edit holiday"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteHoliday(row.data.id)"
                                    class="hidden sm:inline-block bg-red-600 hover:bg-red-700 text-white font-semibold px-1 py-0.5 rounded-md text-xs"
                                    aria-label="Delete holiday"
                                >
                                    Delete
                                </button>
                            </template>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-700 font-bold">
                        <td class="p-2 sm:p-0.5">Total (Manifests Only)</td>
                        <td class="p-2 sm:p-0.5"></td>
                        <td class="p-2 sm:p-0.5">{{ footerTotals.totalParcelsSum }}</td>
                        <td
                            v-for="type in parcelTypes"
                            :key="type.id"
                            class="p-1 text-center hidden sm:table-cell"
                        >
                            {{ footerTotals.parcelTypeTotals[type.id] || 0 }}
                        </td>
                        <td class="p-2 sm:p-0.5"></td> <!-- Empty for Avg. Parcel Rate -->
                        <td class="p-2 sm:p-0.5">£{{ footerTotals.totalValueSum.toFixed(2) }}</td>
                        <td class="p-2 sm:p-0.5"></td> <!-- Empty for Actions -->
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Sliding Panel -->
        <div
            v-if="isPanelOpen"
            class="fixed inset-y-0 right-0 w-full sm:w-80 bg-gray-800 text-white shadow-lg z-50 transform transition-transform duration-300"
            :class="{ 'translate-x-0': isPanelOpen, 'translate-x-full': !isPanelOpen }"
        >
            <div class="p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">Details</h2>
                    <button
                        @click="closePanel"
                        class="text-gray-400 hover:text-white"
                        aria-label="Close panel"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div v-if="selectedRow">
                    <div v-if="selectedRow.type === 'manifest'" class="space-y-2">
                        <p><strong>Date:</strong> {{ formatDate(selectedRow.data.date) }}</p>
                        <p><strong>Round:</strong> {{ getRoundName(selectedRow.data.manifest.round_id) }}</p>
                        <p><strong>Total Parcels:</strong> {{ getTotalParcels(selectedRow.data.manifest.quantities) }}</p>
                        <h3 class="text-md font-medium mt-4">Parcel Types</h3>
                        <ul class="space-y-1">
                            <li v-for="type in parcelTypes" :key="type.id" class="flex justify-between">
                                <span>{{ type.name }}</span>
                                <span>
                                    {{ selectedRow.data.manifest.quantities.find(q => q.parcel_type_id === type.id)?.total ?? 0 }}
                                    (£{{ (selectedRow.data.manifest.quantities.find(q => q.parcel_type_id === type.id)?.value || 0).toFixed(2) }})
                                </span>
                            </li>
                        </ul>
                        <p><strong>Avg. Parcel Rate:</strong> £{{ getAverageParcelRate(selectedRow.data.manifest.total_value, selectedRow.data.manifest.quantities) }}</p>
                        <p><strong>Total Value:</strong> £{{ (selectedRow.data.manifest.total_value || 0).toFixed(2) }}</p>
                        <!-- Edit/Delete in Panel for Mobile -->
                        <div class="flex gap-2 mt-4">
                            <button
                                @click="editManifest(selectedRow.data.manifest.id)"
                                class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-2 py-1 rounded-md text-sm"
                                aria-label="Edit manifest"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteManifest(selectedRow.data.manifest.id, selectedRow.data.manifest.delivery_date)"
                                class="bg-red-600 hover:bg-red-700 text-white font-semibold px-2 py-1 rounded-md text-sm"
                                aria-label="Delete manifest"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                    <div v-else class="space-y-2">
                        <p><strong>Date Range:</strong> {{ selectedRow.data.dateRange }}</p>
                        <p><strong>Days:</strong> {{ selectedRow.data.days }}</p>
                        <p><strong>Daily Rate:</strong> £{{ formatDailyRate(selectedRow.data.dailyRate) }}</p>
                        <p><strong>Total Payment:</strong> £{{ selectedRow.data.totalPayment.toFixed(2) }}</p>
                        <!-- Edit/Delete in Panel for Mobile -->
                        <div class="flex gap-2 mt-4">
                            <button
                                @click="editHoliday(selectedRow.data.id)"
                                class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-2 py-1 rounded-md text-sm"
                                aria-label="Edit holiday"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteHoliday(selectedRow.data.id)"
                                class="bg-red-600 hover:bg-red-700 text-white font-semibold px-2 py-1 rounded-md text-sm"
                                aria-label="Delete holiday"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Overlay for panel -->
        <div
            v-if="isPanelOpen"
            class="fixed inset-0 bg-black bg-opacity-50 z-40"
            @click="closePanel"
        ></div>
    </div>
</template>

<style scoped>
table {
    width: 100%;
    table-layout: fixed; /* Enforce equal column widths */
}

th:not(:last-child),
td:not(:last-child) {
    width: calc((100% - 120px) / (v-bind(totalColumns) - 1)); /* Distribute width excluding Actions */
    min-width: 50px; /* Reduced for rotated headers */
}

th:last-child,
td:last-child {
    width: 120px; /* Wider for Actions */
    min-width: 120px; /* Prevent shrinking */
}

/* Rotate parcel type headers */
.rotated-header {
    height: 100px; /* Increase header height for rotated text */
    vertical-align: bottom; /* Align text at bottom */
    padding-bottom: 10px; /* Space for rotation */
}

.rotated-header > * {
    display: inline-block;
    transform: rotate(-90deg);
    transform-origin: center bottom;
    white-space: nowrap; /* Prevent wrapping within rotated text */
}

th.cursor-pointer:hover {
    background-color: #4a5568;
    transition: background-color 0.2s ease;
}

@media (max-width: 640px) {
    th:not(:last-child),
    td:not(:last-child) {
        width: calc((100% - 60px) / 5); /* 6 columns, smaller Actions width */
        min-width: 50px; /* Slightly smaller for mobile */
    }

    th:last-child,
    td:last-child {
        width: 60px; /* Smaller Actions column for Details only */
        min-width: 60px;
    }

    th,
    td {
        font-size: 0.65rem; /* Smaller text on mobile */
        padding: 0.5rem; /* Reduced padding */
    }

    /* Allow wrapping for Round column */
    td:nth-child(2) {
        white-space: normal;
        word-break: break-word;
    }
}
</style>