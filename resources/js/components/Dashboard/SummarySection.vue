<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    currentPeriod: string;
    currentPeriodEarnings: number;
    averageDailyIncome: number;
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
    holidays: Array<{
        start_date: string;
        end_date: string;
        daily_rate: number;
    }>;
}>();

// Helper function to format date as DD/MM/YYYY
function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

// Period ranges mapping
const periodRanges: { [key: string]: { startDate: string; endDate: string } } = {
    'P 5': { startDate: '2025-06-28', endDate: '2025-08-01' },
    // Add other periods as needed, e.g.:
    // 'P 1': { startDate: '2025-01-01', endDate: '2025-01-15' },
    // 'P 2': { startDate: '2025-01-16', endDate: '2025-01-31' },
};

// Helper function to get period start/end dates
function getPeriodDates(period: string): { startDate: Date; endDate: Date } {
    const range = periodRanges[period];
    if (range) {
        return {
            startDate: new Date(range.startDate),
            endDate: new Date(range.endDate),
        };
    }
    // Fallback: Return current month if period is unknown
    console.warn(`Unknown period: ${period}, falling back to current month`);
    const now = new Date();
    return {
        startDate: new Date(now.getFullYear(), now.getMonth(), 1),
        endDate: new Date(now.getFullYear(), now.getMonth() + 1, 0),
    };
}

// Compute remaining days in the current period
const remainingDays = computed(() => {
    const { endDate } = getPeriodDates(props.currentPeriod);
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Normalize to start of day
    endDate.setHours(0, 0, 0, 0); // Normalize to start of day

    // If today is after the period's end, return 0
    if (today > endDate) return 0;

    // Calculate days remaining (including today)
    const timeDiff = endDate.getTime() - today.getTime();
    const daysRemaining = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
    return daysRemaining;
});

// Compute total holiday payments
const totalHolidayPayments = computed(() => {
    return props.holidays.reduce((sum, holiday) => {
        const start = new Date(holiday.start_date);
        const end = new Date(holiday.end_date);
        const days = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;
        const totalPayment = days * holiday.daily_rate;
        return sum + totalPayment;
    }, 0);
});

// Compute total earnings including holiday payments
const totalEarnings = computed(() => {
    return (props.currentPeriodEarnings + totalHolidayPayments.value).toFixed(2);
});

// Compute highest and lowest days by total_value
const highestAndLowestDays = computed(() => {
    const totalsByDate: { [date: string]: { totalValue: number; date: string } } = {};

    props.flattenedRows.forEach(row => {
        const date = row.date;
        if (!totalsByDate[date]) {
            totalsByDate[date] = { totalValue: 0, date };
        }
        totalsByDate[date].totalValue += row.manifest.total_value || 0;
    });

    const dateEntries = Object.values(totalsByDate);
    if (dateEntries.length === 0) {
        return { highest: null, lowest: null };
    }

    dateEntries.sort((a, b) => b.totalValue - a.totalValue);

    const highest = dateEntries[0];
    const lowest = dateEntries[dateEntries.length - 1];

    return {
        highest: highest ? { date: formatDate(highest.date), value: highest.totalValue.toFixed(2) } : null,
        lowest: lowest ? { date: formatDate(lowest.date), value: lowest.totalValue.toFixed(2) } : null,
    };
});

// Compute total parcels for the selected period
const totalParcels = computed(() => {
    return props.flattenedRows.reduce((sum, row) => {
        const parcels = row.manifest.quantities.reduce((sum, q) => sum + (q.total || 0), 0);
        return sum + parcels;
    }, 0);
});

// Emit download event to parent
const emit = defineEmits(['downloadCsv']);
function handleDownloadCsv() {
    emit('downloadCsv');
}
</script>

<template>
    <div class="mb-8">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold mb-6 text-white">Summary for {{ currentPeriod }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <!-- Total Earnings Card -->
                <div class="bg-gray-700 rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-600 px-4 py-2">
                        <h3 class="text-lg font-medium text-gray-300 text-center">Total Earnings</h3>
                    </div>
                    <div class="p-4 flex justify-center items-center">
                        <p class="text-md font-semibold text-white">£{{ totalEarnings }}</p>
                    </div>
                </div>
                <!-- Average Daily Income Card -->
                <div class="bg-gray-700 rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-600 px-4 py-2">
                        <h3 class="text-lg font-medium text-gray-300 text-center">Day Average</h3>
                    </div>
                    <div class="p-4 flex justify-center items-center">
                        <p class="text-md font-semibold text-white">£{{ averageDailyIncome.toFixed(2) }}</p>
                    </div>
                </div>
                <!-- Highest/Lowest Day Card -->
                <div class="bg-gray-700 rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-600 px-4 py-2">
                        <h3 class="text-lg font-medium text-gray-300 text-center">Hi - Low</h3>
                    </div>
                    <div class="p-4 flex justify-center items-center">
                        <div v-if="highestAndLowestDays.highest && highestAndLowestDays.lowest" class="text-center">
                            <p class="text-md text-white">
                                {{ highestAndLowestDays.highest.date }} £{{ highestAndLowestDays.highest.value }}
                            </p>
                            <p class="text-md font-semibold text-white">
                                {{ highestAndLowestDays.lowest.date }} £{{ highestAndLowestDays.lowest.value }}
                            </p>
                        </div>
                        <p v-else class="text-md font-semibold text-white">No data available</p>
                    </div>
                </div>
                <!-- Total Parcels Card -->
                <div class="bg-gray-700 rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-600 px-4 py-2">
                        <h3 class="text-lg font-medium text-gray-300 text-center">Total Parcels</h3>
                    </div>
                    <div class="p-4 flex justify-center items-center">
                        <p class="text-md font-semibold text-white">{{ totalParcels }}</p>
                    </div>
                </div>
                <!-- Days Remaining Card -->
                <div class="bg-gray-700 rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-600 px-4 py-2">
                        <h3 class="text-lg font-medium text-gray-300 text-center">Days Remaining</h3>
                    </div>
                    <div class="p-4 flex justify-center items-center">
                        <p class="text-md font-semibold text-white">{{ remainingDays }}</p>
                    </div>
                </div>
                <!-- Download CSV Card -->
                <div class="bg-gray-700 rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 flex justify-center items-center">
                        <button @click="handleDownloadCsv"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-md font-semibold px-4 py-2 rounded-md transition duration-200 w-full">
                            Download
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Ensure consistent line heights and spacing */
h3,
p,
button {
    line-height: 1.5;
}

/* Responsive font size adjustments */
@media (max-width: 640px) {
    h2 {
        font-size: 1.125rem;
        /* 18px */
    }

    h3 {
        font-size: 0.875rem;
        /* 14px */
    }

    p,
    button {
        font-size: 1.125rem;
        /* 18px */
    }
}

@media (min-width: 641px) and (max-width: 767px) {
    h2 {
        font-size: 1.25rem;
        /* 20px */
    }

    h3 {
        font-size: 0.875rem;
        /* 14px */
    }

    p,
    button {
        font-size: 1.25rem;
        /* 20px */
    }
}
</style>