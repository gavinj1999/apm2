<template>
    <div class="p-6 bg-gray-900 text-gray-100 min-h-screen">
        <h1 class="text-2xl font-semibold mb-6 tracking-tight">Delivery Reports</h1>

        <div v-if="$page.props.flash.success" class="bg-green-600 text-white p-4 mb-6 rounded-lg shadow-md">
            {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash.error" class="bg-red-600 text-white p-4 mb-6 rounded-lg shadow-md">
            {{ $page.props.flash.error }}
        </div>

        <div class="mb-6 max-w-lg">
            <label for="periods" class="block mb-1 font-medium text-gray-300">Select Periods:</label>
            <select id="periods" v-model="selectedPeriods" multiple
                class="w-full p-2 bg-gray-700 border-gray-600 text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option v-for="(label, periodId) in availablePeriods" :key="periodId" :value="periodId">
                    {{ label }}
                </option>
            </select>
        </div>

        <div class="bg-gray-800 p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-medium mb-4 tracking-tight">Report Summary by Round</h2>
            <div v-if="Object.keys(reportData).length === 0" class="text-gray-400 italic">
                No data available for the selected periods.
            </div>
            <div v-else class="space-y-6">
                <div v-for="(data, roundId) in reportData" :key="roundId">
                    <div class="overflow-x-auto mb-4">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-700 text-gray-300 text-sm uppercase tracking-wider">
                                    <th class="py-3 px-4 text-left">Round</th>
                                    <th class="py-3 px-4 text-left">Total Parcels</th>
                                    <th class="py-3 px-4 text-left">Total Income (£)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-t border-gray-700 hover:bg-gray-700 transition-colors">
                                    <td class="py-3 px-4 text-gray-100">{{ data.name }}</td>
                                    <td class="py-3 px-4 text-gray-100">{{ data.total_parcels }}</td>
                                    <td class="py-3 px-4 text-gray-100">{{ data.total_income.toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-x-auto">
                        <h3 class="text-lg font-medium mb-2 text-gray-300">Packet Type Breakdown</h3>
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-700 text-gray-300 text-sm uppercase tracking-wider">
                                    <th class="py-3 px-4 text-left">Packet Type</th>
                                    <th class="py-3 px-4 text-left">Total Parcels</th>
                                    <th class="py-3 px-4 text-left">Income (£)</th>
                                    <th class="py-3 px-4 text-left">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(packet, index) in data.packet_types" :key="index"
                                    class="border-t border-gray-700 hover:bg-gray-700 transition-colors">
                                    <td class="py-3 px-4 text-gray-100">{{ packet.name }}</td>
                                    <td class="py-3 px-4 text-gray-100">{{ packet.total }}</td>
                                    <td class="py-3 px-4 text-gray-100">{{ packet.income.toFixed(2) }}</td>
                                    <td class="py-3 px-4 text-gray-100">{{ packet.percentage.toFixed(2) }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    availablePeriods: Object,
    selectedPeriods: Array,
    reportData: Object,
    flash: Object,
});

const selectedPeriods = ref(props.selectedPeriods);

watch(selectedPeriods, (newPeriods) => {
    router.get('/reports', { periods: newPeriods }, { preserveState: true });
});
</script>

<style scoped>
select[multiple] {
    height: 150px;
}
</style>
