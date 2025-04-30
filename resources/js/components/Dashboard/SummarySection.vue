<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    currentPeriod: string;
    currentPeriodEarnings: number;
    averageDailyIncome: number;
    remainingDays: number;
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

// Compute total holiday payments
const totalHolidayPayments = computed(() => {
    return props.holidays.reduce((sum, holiday) => {
        const start = new Date(holiday.start_date);
        const end = new Date(holiday.end_date);
        const days = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1; // Include both start and end dates
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
    // Aggregate total_value by date
    const totalsByDate: { [date: string]: { totalValue: number; date: string } } = {};

    props.flattenedRows.forEach(row => {
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

// Emit download event to parent
const emit = defineEmits(['downloadCsv']);
function handleDownloadCsv() {
    emit('downloadCsv');
}
</script>

<template>
    <div>
        <h2 class="text-xl font-semibold mb-4">{{ currentPeriod }} Summary</h2>
        <div class="mb-6 p-4 bg-gray-700 rounded-lg">
            <div class="grid grid-cols-2 gap-[4px]">
                <!-- Total Earnings -->
                <div class="p-4 bg-gray-800 rounded">
                    <p class="text-gray-300 text-sm">Total Earnings</p>
                    <p class="text-white font-bold text-lg">£{{ totalEarnings }}</p>
                </div>

                <!-- Average Daily Income -->
                <div class="p-4 bg-gray-800 rounded">
                    <p class="text-gray-300 text-sm">Average Daily Income</p>
                    <p class="text-white font-bold text-lg">£{{ averageDailyIncome.toFixed(2) }}</p>
                </div>

                <!-- Days Remaining in Period -->
                <div class="p-4 bg-gray-800 rounded">
                    <p class="text-gray-300 text-sm">Days Remaining in Period</p>
                    <p class="text-white font-bold text-lg">{{ remainingDays }}</p>
                </div>

                <!-- Download as CSV Button -->
                <div class="p-4 bg-gray-800 rounded flex items-center justify-center">
                    <button @click="handleDownloadCsv"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-md transition duration-200">
                        Download as CSV
                    </button>
                </div>

                <!-- Highest Day -->
                <div class="p-4 bg-gray-800 rounded">
                    <p class="text-gray-300 text-sm">Highest Day</p>
                    <p v-if="highestAndLowestDays.highest" class="text-white font-bold text-lg">
                        {{ highestAndLowestDays.highest.date }} (£{{ highestAndLowestDays.highest.value }})
                    </p>
                    <p v-else class="text-gray-400 text-sm">No data available</p>
                </div>

                <!-- Lowest Day -->
                <div class="p-4 bg-gray-800 rounded">
                    <p class="text-gray-300 text-sm">Lowest Day</p>
                    <p v-if="highestAndLowestDays.lowest" class="text-white font-bold text-lg">
                        {{ highestAndLowestDays.lowest.date }} (£{{ highestAndLowestDays.lowest.value }})
                    </p>
                    <p v-else class="text-gray-400 text-sm">No data available</p>
                </div>
            </div>
        </div>
    </div>
</template>
