<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Props with default values
const { groupedManifests, currentPeriodEarnings = 0, averageDailyIncome = 0, remainingDays = 0, currentPeriod = 'Unknown Period', rounds, parcelTypes, flash, debug = {} } = defineProps<{
    groupedManifests: Array<{
        period_name: string;
        dates: Array<{
            date: string;
            manifests: Array<{
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
            }>;
        }>;
    }>;
    currentPeriodEarnings?: number;
    averageDailyIncome?: number;
    remainingDays?: number;
    currentPeriod?: string;
    rounds: Array<{ id: number; round_id: string; name: string }>;
    parcelTypes: Array<{ id: number; name: string }>;
    flash?: { success?: string; error?: string };
    debug?: {
        user_id?: number;
        roundIds?: number[];
        parcelTypesCount?: number;
        manifestsCount?: number;
        periods?: Array<{ id: number; name: string; start_date: string; end_date: string }>;
        currentDate?: string;
        currentPeriodName?: string;
        currentPeriodStart?: string;
        currentPeriodEnd?: string;
        manifestCount?: number;
        manifestDates?: string[];
        currentPeriodEarnings?: number;
        daysWithManifests?: number;
        averageDailyIncome?: number;
        remainingDays?: number;
        earningsResult?: { days_with_manifests?: number; total_earnings?: number };
        earningsQueryError?: string;
        remainingDaysQueryError?: string;
        error?: string;
        errorTrace?: string;
    };
}>();

// Log debug values to console
//console.log('Debug Values:', debug);

// Default delivery date (today)
const now = new Date();
const day = String(now.getDate()).padStart(2, '0');
const month = String(now.getMonth() + 1).padStart(2, '0');
const year = now.getFullYear();
const defaultDeliveryDate = `${year}-${month}-${day}`;

// State for edit mode and period selection
const isEditMode = ref(false);
const editingManifestId = ref<number | null>(null);
const selectedPeriod = ref(currentPeriod);

// Filter out "Unassigned" periods and get available periods
const availablePeriods = computed(() => {
    if (!Array.isArray(groupedManifests)) {
        return [];
    }
    return groupedManifests
        .filter(group => group.period_name !== 'Unassigned')
        .map(group => group.period_name);
});

// Automatically select the current period if available, otherwise the first available period
if (availablePeriods.value.includes(currentPeriod)) {
    selectedPeriod.value = currentPeriod;
} else if (availablePeriods.value.length > 0) {
    selectedPeriod.value = availablePeriods.value[0];
}

// Filter manifests by selected period
const filteredManifests = computed(() => {
    if (!Array.isArray(groupedManifests)) {
        return [];
    }
    const selected = groupedManifests.find(group => group.period_name === selectedPeriod.value);
    return selected ? selected.dates : [];
});

// Flatten filteredManifests into a list of rows for rendering
const flattenedRows = computed(() => {
    const rows = [];
    if (Array.isArray(filteredManifests.value)) {
        filteredManifests.value.forEach((dateGroup, dIndex) => {
            if (Array.isArray(dateGroup?.manifests)) {
                dateGroup.manifests.forEach((manifest, mIndex) => {
                    rows.push({
                        date: dateGroup.date,
                        manifest: manifest,
                        isFirstInDate: mIndex === 0,
                        dateRowspan: dateGroup.manifests.length,
                    });
                });
            }
        });
    }
    return rows;
});

// Form for creating/updating a manifest
const manifestForm = useForm({
    delivery_date: defaultDeliveryDate,
    status: 'pending',
    round_id: '',
    quantities: parcelTypes.map(type => ({
        parcel_type_id: type.id,
        manifested: 0,
        re_manifested: 0,
        carried_forward: 0
    })),
});

// Submit function (handles both create and update)
function submitManifest() {
    isProcessing.value = true; // Indicate form is processing

    if (isEditMode.value && editingManifestId.value) {
        manifestForm.put(route('manifests.update-by-id', editingManifestId.value), {
            onSuccess: () => {
                isEditMode.value = false;
                editingManifestId.value = null;
                manifestForm.reset();
                isProcessing.value = false; // Form processing complete
            },
            onError: () => {
                isProcessing.value = false; // Form processing complete, even on error
            },
        });
    } else {
        manifestForm.post(route('manifests.store'), {
            onSuccess: () => {
                manifestForm.reset();
                isProcessing.value = false; // Form processing complete
            },
            onError: () => {
                isProcessing.value = false; // Form processing complete, even on error
            },
        });
    }
}

// Function to enter edit mode
function editManifest(id: number) {
    fetch(`/manifests/${id}`, {
        headers: {
            Accept: 'application/json',
        },
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            manifestForm.delivery_date = data.delivery_date;
            manifestForm.status = data.status;
            manifestForm.round_id = data.round_id;
            manifestForm.quantities = parcelTypes.map(type => {
                const quantity = data.quantities.find(q => q.parcel_type_id === type.id) || {
                    parcel_type_id: type.id,
                    manifested: 0,
                    re_manifested: 0,
                    carried_forward: 0
                };
                return {
                    parcel_type_id: type.id,
                    manifested: quantity.manifested,
                    re_manifested: quantity.re_manifested,
                    carried_forward: quantity.carried_forward
                };
            });
            isEditMode.value = true;
            editingManifestId.value = id;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        })
        .catch(error => {
            console.error('Error fetching manifest:', error);
            alert('Failed to load manifest data: ' + error.message);
        });
}

// Function to cancel edit mode
function cancelEdit() {
    isEditMode.value = false;
    editingManifestId.value = null;
    manifestForm.reset();
}

// Function to delete a manifest
function deleteManifest(id: number, date: string) {
    if (confirm(`Are you sure you want to delete the manifest for ${formatDate(date)}?`)) {
        router.delete(route('manifests.destroy', id));
    }
}

// Computed totals for the create form
function getTotals(form: any) {
    const summaries = form.quantities || [];
    return {
        manifested: Array.isArray(summaries) ? summaries.reduce((sum, s) => sum + Number(s.manifested || 0), 0) : 0,
        re_manifested: Array.isArray(summaries) ? summaries.reduce((sum, s) => sum + Number(s.re_manifested || 0), 0) : 0,
        carried_forward: Array.isArray(summaries) ? summaries.reduce((sum, s) => sum + Number(s.carried_forward || 0), 0) : 0
    };
}

// Split parcel types into two columns for desktop (4 per column)
const leftColumnTypes = parcelTypes.slice(0, 4);
const rightColumnTypes = parcelTypes.slice(4, 9);

const manifestLabel = "M";
const remanifestedLabel = "R";
const carriedForwardLabel = "CF";

// Helper function to format date as DD/MM/YYYY
function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

// Compute footer totals
const footerTotals = computed(() => {
    const totals: { [key: number]: number } = {};
    let totalValueSum = 0;

    // Initialize totals for each parcel type
    parcelTypes.forEach(type => {
        totals[type.id] = 0;
    });

    // Sum up totals for each manifest
    flattenedRows.value.forEach(row => {
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

function getRoundName(roundId: string): string {
    const round = rounds.find(r => r.id === Number(roundId));
    return round ? round.name : 'Unknown Round';
}

// Function to download the table as a CSV
function downloadTableAsCSV() {
    // Define CSV headers
    const headers = ['Date', 'Round', ...parcelTypes.map(type => type.name), 'Total Value', 'Actions'];

    // Map table rows to CSV rows
    const csvRows = flattenedRows.value.map(row => {
        const date = formatDate(row.date); // Show date on every row
        const roundName = getRoundName(row.manifest.round_id);
        const parcelQuantities = parcelTypes.map(type =>
            row.manifest.quantities.find(q => q.parcel_type_id === type.id)?.total ?? 0
        );
        const totalValue = (row.manifest.total_value || 0).toFixed(2); // Remove £ symbol
        const actions = ''; // Actions column will be empty in CSV
        return [date, roundName, ...parcelQuantities, totalValue, actions];
    });

    // Combine headers and rows into CSV content (no footer)
    const csvContent = [
        headers,
        ...csvRows,
    ]
        .map(row => row.map(cell => `"${cell}"`).join(',')) // Wrap each cell in quotes to handle commas
        .join('\n');

    // Create a Blob and trigger download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `manifests_${selectedPeriod.value}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
// Compute highest and lowest days by total_value
const highestAndLowestDays = computed(() => {
    // Aggregate total_value by date
    const totalsByDate: { [date: string]: { totalValue: number; date: string } } = {};

    flattenedRows.value.forEach(row => {
        const date = row.date;
        if (!totalsByDate[date]) {
            totalsByDate[date] = { totalValue: 0, date };
        }
        totalsByDate[date].totalValue += row.manifest.total_value || 0;
    });

    // Convert to array and sort by total_value
    const dateEntries = Object.values(totalsByDate);
    if (dateEntries.length === 0) {
        return { highest: null, lowest: null };
    }

    dateEntries.sort((a, b) => b.totalValue - a.totalValue);

    // Highest and lowest days
    const highest = dateEntries[0]; // Highest total_value
    const lowest = dateEntries[dateEntries.length - 1]; // Lowest total_value

    return {
        highest: highest ? { date: formatDate(highest.date), value: highest.totalValue.toFixed(2) } : null,
        lowest: lowest ? { date: formatDate(lowest.date), value: lowest.totalValue.toFixed(2) } : null,
    };
});

// State to track if the form is processing
const isProcessing = ref(false);
</script>

<template>

    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 bg-gray-900 min-h-screen text-white">
            <!-- Flash Messages -->
            <div v-if="isProcessing" class="mb-4 p-4 bg-yellow-600 text-white rounded-lg">
            Processing {{ isEditMode ? 'update' : 'creation' }} of manifest...
        </div>
            <div v-if="flash?.success" class="mb-4 p-4 bg-green-600 text-white rounded-lg">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="mb-4 p-4 bg-red-600 text-white rounded-lg">
                {{ flash.error }}
            </div>

            <!-- Debug Information -->
            <div class="mb-6 p-4 bg-gray-700 rounded-lg hidden">
                <h3 class="text-lg font-semibold mb-2">Debug Information</h3>
                <p>User ID: {{ debug.user_id ?? 'N/A' }}</p>
                <p>Round IDs: {{ debug.roundIds?.join(', ') ?? 'N/A' }}</p>
                <p>Parcel Types Count: {{ debug.parcelTypesCount ?? 'N/A' }}</p>
                <p>Manifests Count: {{ debug.manifestsCount ?? 'N/A' }}</p>
                <p>Periods: {{ debug.periods ? debug.periods.map(p => `${p.name}: ${p.start_date} to
                    ${p.end_date}`).join('; ') : 'N/A' }}</p>
                <p>Current Date: {{ debug.currentDate ?? 'N/A' }}</p>
                <p>Current Period Name: {{ debug.currentPeriodName ?? 'N/A' }}</p>
                <p>Current Period Start: {{ debug.currentPeriodStart ?? 'N/A' }}</p>
                <p>Current Period End: {{ debug.currentPeriodEnd ?? 'N/A' }}</p>
                <p>Manifest Count in Period: {{ debug.manifestCount ?? 'N/A' }}</p>
                <p>Manifest Dates in Period: {{ debug.manifestDates?.join(', ') ?? 'N/A' }}</p>
                <p>Earnings Result: {{ debug.earningsResult ? `Days: ${debug.earningsResult.days_with_manifests},
                    Earnings: £${debug.earningsResult.total_earnings}` : 'N/A' }}</p>
                <p v-if="debug.earningsQueryError">Earnings Query Error: {{ debug.earningsQueryError }}</p>
                <p>Current Period Earnings: £{{ debug.currentPeriodEarnings?.toFixed(2) ?? 'N/A' }}</p>
                <p>Days with Manifests: {{ debug.daysWithManifests ?? 'N/A' }}</p>
                <p>Average Daily Income: £{{ debug.averageDailyIncome?.toFixed(2) ?? 'N/A' }}</p>
                <p>Remaining Days: {{ debug.remainingDays ?? 'N/A' }}</p>
                <p v-if="debug.remainingDaysQueryError">Remaining Days Query Error: {{ debug.remainingDaysQueryError }}
                </p>
                <p v-if="debug.error">Error: {{ debug.error }}</p>
                <p v-if="debug.errorTrace">Error Trace: {{ debug.errorTrace }}</p>
            </div>

            <!-- Create/Update Manifest Form -->
            <h1 class="text-3xl font-bold mb-6">Dashboard</h1>
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">{{ isEditMode ? 'Edit Manifest' : 'Create New Manifest' }}</h2>
                <form @submit.prevent="submitManifest" class="bg-gray-800 p-6 rounded-lg shadow-lg">
                    <!-- Delivery Date -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-2">Delivery Date</label>
                        <input v-model="manifestForm.delivery_date" type="date"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base" />
                        <div v-if="manifestForm.errors.delivery_date" class="text-red-500 text-sm mt-1">{{
                            manifestForm.errors.delivery_date }}</div>
                    </div>

                    <!-- Status -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-2">Status</label>
                        <select v-model="manifestForm.status"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base">
                            <option value="pending">Pending</option>
                            <option value="in-progress">In-Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                        <div v-if="manifestForm.errors.status" class="text-red-500 text-sm mt-1">{{
                            manifestForm.errors.status }}</div>
                    </div>

                    <!-- Round -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-2">Round</label>
                        <select v-model="manifestForm.round_id"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base">
                            <option value="" disabled>Select a round</option>
                            <option v-for="round in rounds" :key="round.id" :value="round.id">{{ round.round_id }}
                            </option>
                        </select>
                        <div v-if="manifestForm.errors.round_id" class="text-red-500 text-sm mt-1">{{
                            manifestForm.errors.round_id }}</div>
                    </div>

                    <!-- Parcel Quantities Section -->
                    <div class="mb-6">
                        <h3 class="text-md font-semibold mb-4">Parcel Quantities</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <div v-for="type in leftColumnTypes" :key="type.id" class="mb-6">
                                    <label class="block text-sm font-medium mb-2">{{ type.name }}</label>
                                    <div class="grid grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">{{ manifestLabel }}</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).manifested"
                                                type="number" min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">{{ remanifestedLabel
                                                }}</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).re_manifested"
                                                type="number" min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">{{ carriedForwardLabel
                                                }}</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).carried_forward"
                                                type="number" min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div v-for="type in rightColumnTypes" :key="type.id" class="mb-6">
                                    <label class="block text-sm font-medium mb-2">{{ type.name }}</label>
                                    <div class="grid grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">{{ manifestLabel }}</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).manifested"
                                                type="number" min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">{{ remanifestedLabel
                                                }}</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).re_manifested"
                                                type="number" min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">{{ carriedForwardLabel
                                                }}</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).carried_forward"
                                                type="number" min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Totals Section -->
                        <div class="mt-6 p-4 bg-gray-700 rounded-lg">
                            <div class="grid grid-cols-4 gap-4 text-sm font-bold">
                                <div>
                                    <label>Total</label>
                                </div>
                                <div>
                                    <label>Man: {{ getTotals(manifestForm).manifested }}</label>
                                </div>
                                <div>
                                    <label>ReMan: {{ getTotals(manifestForm).re_manifested }}</label>
                                </div>
                                <div>
                                    <label>CFWD: {{ getTotals(manifestForm).carried_forward }}</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-md transition duration-200 w-full sm:w-auto">
                            {{ isEditMode ? 'Update Manifest' : 'Create Manifest' }}
                        </button>
                        <button v-if="isEditMode" type="button" @click="cancelEdit"
                            class="bg-gray-600 hover:bg-gray-700 text-white font-semibold px-6 py-3 rounded-md transition duration-200 w-full sm:w-auto">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Period Selection -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Select Period</label>
                <select v-model="selectedPeriod"
                    class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base"
                    :disabled="availablePeriods.length === 0">
                    <option v-if="availablePeriods.length === 0" disabled>No periods available</option>
                    <option v-for="period in availablePeriods" :key="period" :value="period">
                        {{ period }}
                    </option>
                </select>
            </div>

<!-- Current Period Summary -->
<h2 class="text-xl font-semibold mb-4">{{ currentPeriod }} Summary</h2>
<div class="mb-6 p-4 bg-gray-700 rounded-lg flex flex-col md:flex-row gap-4">
    <!-- Summary Content -->
    <div class="flex-1">
        <div class="flex flex-col gap-4 text-lg">
            <p>
                Total Earnings:
                <span class="font-bold">£{{ currentPeriodEarnings.toFixed(2) }}</span>
            </p>
            <p>
                Average Daily Income:
                <span class="font-bold">£{{ averageDailyIncome.toFixed(2) }}</span>
            </p>
            <p>
                Days Remaining in Period:
                <span class="font-bold">{{ remainingDays }}</span>
            </p>
        </div>
    </div>
    <!-- Download Button -->
    <div class="flex items-center justify-center">
        <button
            @click="downloadTableAsCSV"
            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-md transition duration-200"
        >
            Download as CSV
        </button>
    </div>
    <!-- Highest and Lowest Days -->
    <div class="flex-1">
        <div v-if="highestAndLowestDays.highest && highestAndLowestDays.lowest" class="flex flex-col gap-4 text-lg">
            <p>
                Highest Day:
                <span class="font-bold">{{ highestAndLowestDays.highest.date }} (£{{ highestAndLowestDays.highest.value }})</span>
            </p>
            <p>
                Lowest Day:
                <span class="font-bold">{{ highestAndLowestDays.lowest.date }} (£{{ highestAndLowestDays.lowest.value }})</span>
            </p>
        </div>
        <div v-else class="text-lg text-gray-400">
            No data available for highest/lowest days.
        </div>
    </div>
</div>
            <div v-if="flattenedRows.length === 0" class="text-gray-400">
                No manifests available for this period.
            </div>
            <div v-else class="bg-gray-800 rounded-lg shadow-lg overflow-x-auto">
                <table class="w-full border border-gray-600">
                    <thead>
                        <tr class="bg-gray-700">
                            <th class="p-3 text-left text-sm font-medium">Date</th>
                            <th class="p-3 text-left text-sm font-medium">Round</th>
                            <th v-for="type in parcelTypes" :key="type.id" class="p-3 text-left text-sm font-medium">{{
                                type.name }}</th>
                            <th class="p-3 text-left text-sm font-medium">Total Value</th>
                            <th class="p-3 text-left text-sm font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in flattenedRows" :key="row.manifest.id"
                            class="border-b border-gray-600">
                            <td v-if="row.isFirstInDate" :rowspan="row.dateRowspan" class="p-3">
                                {{ formatDate(row.date) }}
                            </td>
                            <td class="p-3">
                                {{ getRoundName(row.manifest.round_id) }}
                            </td>
                            <td v-for="type in parcelTypes" :key="type.id" class="p-3 relative group">
                                <span class="cursor-pointer">
                                    {{ row.manifest.quantities.find(q => q.parcel_type_id === type.id)?.total ?? 0 }}
                                    <span
                                        class="absolute hidden group-hover:block bg-gray-600 text-white text-xs rounded py-1 px-2 -mt-8 left-1/2 transform -translate-x-1/2">
                                        Value: £{{ (row.manifest.quantities.find(q => q.parcel_type_id ===
                                            type.id)?.value ?? 0).toFixed(2) }}
                                    </span>
                                </span>
                            </td>
                            <td class="p-3">
                                £{{ (row.manifest.total_value || 0).toFixed(2) }}
                            </td>
                            <td class="p-3 flex gap-1">
                                <button @click="editManifest(row.manifest.id)"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-2 py-0.5 rounded-md text-xs">
                                    Edit
                                </button>
                                <button @click="deleteManifest(row.manifest.id, row.manifest.delivery_date)"
                                    class="bg-red-600 hover:bg-red-700 text-white font-semibold px-2 py-0.5 rounded-md text-xs">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-700 font-bold">
                            <td class="p-3">Total</td>
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
    </AppLayout>
</template>

<style scoped>
td {
    vertical-align: top;
}

@media (max-width: 640px) {
    label {
        font-size: 1rem;
    }

    input,
    select {
        font-size: 1rem;
        padding: 0.75rem;
        height: 3rem;
    }

    .grid-cols-4>div {
        font-size: 0.875rem;
        padding: 0.5rem 0;
    }
}
</style>
