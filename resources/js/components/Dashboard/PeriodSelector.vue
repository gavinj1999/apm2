<script setup lang="ts">
import { ref, computed, watch } from 'vue';

const props = defineProps<{
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
    currentPeriod: string;
}>();

// State for period selection
const selectedPeriod = ref(props.currentPeriod);

// Filter out "Unassigned" periods and get available periods
const availablePeriods = computed(() => {
    if (!Array.isArray(props.groupedManifests)) {
        return [];
    }
    return props.groupedManifests
        .filter(group => group.period_name !== 'Unassigned')
        .map(group => group.period_name);
});

// Automatically select the current period if available, otherwise the first available period
if (availablePeriods.value.includes(props.currentPeriod)) {
    selectedPeriod.value = props.currentPeriod;
} else if (availablePeriods.value.length > 0) {
    selectedPeriod.value = availablePeriods.value[0];
}

// Emit the selected period to the parent
const emit = defineEmits(['update:selected-period']);
watch(selectedPeriod, (newValue) => {
    emit('update:selected-period', newValue);
});
</script>

<template>
    <div class="mb-6 p-4 bg-gray-700 rounded-lg">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
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
    </div>
</template>
