<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ManifestForm from '@/components/Dashboard/ManifestForm.vue';
import PeriodSelector from '@/components/Dashboard/PeriodSelector.vue';
import SummarySection from '@/components/Dashboard/SummarySection.vue';
import ManifestTable from '@/components/Dashboard/ManifestTable.vue';
import HolidayForm from '@/components/Dashboard/HolidayForm.vue';

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Props with default values
const { groupedManifests, currentPeriodEarnings = 0, averageDailyIncome = 0, remainingDays = 0, currentPeriod = 'Unknown Period', rounds, parcelTypes, flash, holidays = [] } = defineProps<{
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
    holidays?: Array<{
        start_date: string;
        end_date: string;
        daily_rate: number;
    }>;
}>();

// State for selected period
const selectedPeriod = ref(currentPeriod);

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

// Reference to ManifestForm component
const manifestForm = ref<InstanceType<typeof ManifestForm> | null>(null);

// Handle editManifest event from ManifestTable
function handleEditManifest(id: number) {
    if (manifestForm.value) {
        manifestForm.value.editManifest(id);
    }
}

// Helper function to format date as DD/MM/YYYY
function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = dateExamples.getFullYear();
    return `${day}/${month}/${year}`;
}

function getRoundName(roundId: string): string {
    const round = rounds.find(r => r.id === Number(roundId));
    return round ? round.name : 'Unknown Round';
}

// Handle CSV download
function handleDownloadCsv() {
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
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 bg-gray-900 min-h-screen text-white">
            <!-- Flash Messages -->
            <div v-if="flash?.success" class="mb-4 p-4 bg-green-600 text-white rounded-lg">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="mb-4 p-4 bg-red-600 text-white rounded-lg">
                {{ flash.error }}
            </div>

            <!-- Dashboard Content -->
            <h1 class="text-3xl font-bold mb-6">Dashboard</h1>
            <ManifestForm ref="manifestForm" :rounds="rounds" :parcel-types="parcelTypes" />
            <HolidayForm />
            <PeriodSelector
                :grouped-manifests="groupedManifests"
                :current-period="currentPeriod"
                @update:selected-period="selectedPeriod = $event"
            />
            <SummarySection
                :current-period="selectedPeriod"
                :current-period-earnings="currentPeriodEarnings"
                :average-daily-income="averageDailyIncome"
                :remaining-days="remainingDays"
                :flattened-rows="flattenedRows"
                :holidays="holidays"
                @download-csv="handleDownloadCsv"
            />
            <ManifestTable
                :flattened-rows="flattenedRows"
                :parcel-types="parcelTypes"
                :rounds="rounds"
                :holidays="holidays"
                @edit-manifest="handleEditManifest"
            />
        </div>
    </AppLayout>
</template>
