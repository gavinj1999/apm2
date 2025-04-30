<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

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
    holidays: Array<{
        start_date: string;
        end_date: string;
        daily_rate: number;
    }>;
}>();

const emit = defineEmits(['editManifest']);

// Helper function to format date as DD/MM/YYYY
function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

function getRoundName(roundId: string): string {
    const round = props.rounds.find(r => r.id === Number(roundId));
    return round ? round.name : 'Unknown Round';
}

// Compute footer totals (only for manifests, excluding holidays)
const footerTotals = computed(() => {
    const totals: { [key: number]: number } = {};
    let totalValueSum = 0;

    // Initialize totals for each parcel type
    props.parcelTypes.forEach(type => {
        totals[type.id] = 0;
    });

    // Sum up totals for each manifest
    props.flattenedRows.forEach(row => {
        row.manifest.quantities.forEach(quantity => {
            totals[quantity.parcel_type_id] += quantity.total || 0;
        });
        totalValueSum += row.manifest.total_value || 0;
    });

    return {
        parcelTypeTotals: totals,
        totalValueSum,
    };
});

// Compute holiday summaries
const holidaySummaries = computed(() => {
    return props.holidays.map(holiday => {
        const start = new Date(holiday.start_date);
        const end = new Date(holiday.end_dateFloat);
        const days = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1; // Include both start and end dates
        const totalPayment = days * holiday.daily_rate;
        return {
            dateRange: `${formatDate(holiday.start_date)} - ${formatDate(holiday.end_date)}`,
            days,
            dailyRate: holiday.daily_rate,
            totalPayment,
        };
    });
});

// Combine manifest rows and holiday rows, sorted by date
const combinedRows = computed(() => {
    const manifestRows = props.flattenedRows.map(row => ({
        type: 'manifest' as const,
        data: row,
        sortDate: new Date(row.date).getTime(),
    }));

    const holidayRows = holidaySummaries.value.map(holiday => ({
        type: 'holiday' as const,
        data: holiday,
        sortDate: new Date(holiday.dateRange.split(' - ')[0]).getTime(),
    }));

    return [...manifestRows, ...holidayRows].sort((a, b) => a.sortDate - b.sortDate);
});

// Function to delete a manifest
function deleteManifest(id: number, date: string) {
    if (confirm(`Are you sure you want to delete the manifest for ${formatDate(date)}?`)) {
        router.delete(route('manifests.destroy', id));
    }
}

// Function to edit a manifest
function editManifest(id: number) {
    emit('editManifest', id);
}
</script>

<template>
    <div>
        <div v-if="combinedRows.length === 0" class="text-gray-400">
            No manifests or holidays available for this period.
        </div>
        <div v-else class="bg-gray-800 rounded-lg shadow-lg overflow-x-auto">
            <table class="w-full border border-gray-600">
                <thead>
                    <tr class="bg-gray-700">
                        <th class="p-3 text-left text-sm font-medium">Date</th>
                        <th class="p-3 text-left text-sm font-medium">Round</th>
                        <th v-for="type in parcelTypes" :key="type.id" class="p-3 text-left text-sm font-medium">
                            {{ type.name }}
                        </th>
                        <th class="p-3 text-left text-sm font-medium">Total Value</th>
                        <th class="p-3 text-left text-sm font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in combinedRows" :key="row.type === 'manifest' ? row.data.manifest.id : `holiday-${index}`"
                        :class="row.type === 'holiday' ? 'bg-gray-600' : 'border-b border-gray-600'">
                        <td class="p-3">
                            <span v-if="row.type === 'manifest' && row.data.isFirstInDate" :rowspan="row.data.dateRowspan">
                                {{ formatDate(row.data.date) }}
                            </span>
                            <span v-else-if="row.type === 'holiday'">
                                {{ row.data.dateRange }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span v-if="row.type === 'manifest'">
                                {{ getRoundName(row.data.manifest.round_id) }}
                            </span>
                            <span v-else>
                                Holiday
                            </span>
                        </td>
                        <td v-for="(type, idx) in parcelTypes" :key="type.id" class="p-3 relative group">
                            <span v-if="row.type === 'manifest'" class="cursor-pointer">
                                {{ row.data.manifest.quantities.find(q => q.parcel_type_id === type.id)?.total ?? 0 }}
                                <span
                                    class="absolute hidden group-hover:block bg-gray-600 text-white text-xs rounded py-1 px-2 -mt-8 left-1/2 transform -translate-x-1/2">
                                    Value: £{{ (row.data.manifest.quantities.find(q => q.parcel_type_id === type.id)?.value ?? 0).toFixed(2) }}
                                </span>
                            </span>
                            <span v-else-if="idx === 0">
                                {{ row.data.days }} days @ £{{ row.data.dailyRate }}/day
                            </span>
                            <span v-else>
                                <!-- Empty for other parcel columns in holiday rows -->
                            </span>
                        </td>
                        <td class="p-3">
                            <span v-if="row.type === 'manifest'">
                                £{{ (row.data.manifest.total_value || 0).toFixed(2) }}
                            </span>
                            <span v-else>
                                £{{ row.data.totalPayment.toFixed(2) }}
                            </span>
                        </td>
                        <td class="p-3 flex gap-1">
                            <template v-if="row.type === 'manifest'">
                                <button @click="editManifest(row.data.manifest.id)"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-2 py-0.5 rounded-md text-xs">
                                    Edit
                                </button>
                                <button @click="deleteManifest(row.data.manifest.id, row.data.manifest.delivery_date)"
                                    class="bg-red-600 hover:bg-red-700 text-white font-semibold px-2 py-0.5 rounded-md text-xs">
                                    Delete
                                </button>
                            </template>
                            <span v-else>
                                <!-- No actions for holiday rows for now -->
                            </span>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-700 font-bold">
                        <td class="p-3">Total (Manifests Only)</td>
                        <td class="p-3"></td> <!-- Empty cell for Round column -->
                        <td v-for="type in parcelTypes" :key="type.id" class="p-3">
                            {{ footerTotals.parcelTypeTotals[type.id] || 0 }}
                        </td>
                        <td class="p-3">£{{ footerTotals.totalValueSum.toFixed(2) }}</td>
                        <td class="p-3"></td> <!-- Empty cell for Actions column -->
                    </tr>
                </tfoot>
            </table>
        </div>
        </div>

</template>

<style scoped>
td {
    vertical-align: top;
}
</style>
