<template>
    <div class="relative">
        <!-- Debug to confirm rendering -->
        <span class="text-gray-500 absolute -top-4 left-0">DatePicker Rendered</span>
        <div class="relative">
            <input
                type="text"
                :value="formattedDate"
                @click="showPicker = !showPicker"
                class="mt-1 block w-full bg-gray-700 border-gray-600 rounded-md shadow-sm text-gray-100 focus:ring-blue-500 focus:border-blue-500 pr-10"
                :class="{ 'border-red-500': error }"
                readonly
            >
            <CalendarIcon class="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
        </div>
        <p v-if="error" class="text-red-400 text-sm mt-1">{{ error }}</p>
        <div v-if="showPicker" class="absolute z-10 bg-white text-gray-900 rounded-lg shadow-lg p-4 mt-2 w-72">
            <div class="flex justify-between items-center mb-2">
                <button @click="prevMonth" class="text-gray-600 hover:text-gray-800">
                    <ChevronLeftIcon class="w-5 h-5" />
                </button>
                <span class="font-medium">{{ months[currentMonth] }} {{ currentYear }}</span>
                <button @click="nextMonth" class="text-gray-600 hover:text-gray-800">
                    <ChevronRightIcon class="w-5 h-5" />
                </button>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-sm font-medium text-gray-600 mb-2">
                <div v-for="day in daysOfWeek" :key="day">{{ day }}</div>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-sm">
                <button
                    v-for="day in daysInMonth"
                    :key="day"
                    @click="selectDate(day)"
                    class="p-2 rounded-full"
                    :class="{
                        'text-gray-400': !day,
                        'hover:bg-blue-100': day,
                        'bg-blue-500 text-white': day && selectedDate.getDate() === day && selectedDate.getMonth() === currentMonth && selectedDate.getFullYear() === currentYear,
                    }"
                    :disabled="!day"
                >
                    {{ day }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { CalendarIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: String,
    error: String,
});
const emit = defineEmits(['update:modelValue']);

const showPicker = ref(false);
const selectedDate = ref(props.modelValue ? new Date(props.modelValue) : new Date());
const currentMonth = ref(selectedDate.value.getMonth());
const currentYear = ref(selectedDate.value.getFullYear());

// Debug logs
console.log('DatePicker mounted with modelValue:', props.modelValue);

const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const formattedDate = computed(() => {
    if (!props.modelValue) return '';
    const date = new Date(props.modelValue);
    if (isNaN(date.getTime())) {
        console.error('Invalid date in DatePicker:', props.modelValue);
        return '';
    }
    return `${daysOfWeek[date.getDay()]} ${months[date.getMonth()].slice(0, 3)} ${String(date.getDate()).padStart(2, '0')} ${date.getFullYear()}`;
});

const daysInMonth = computed(() => {
    const days = [];
    const firstDay = new Date(currentYear.value, currentMonth.value, 1);
    const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0);
    const startDay = firstDay.getDay();
    const totalDays = lastDay.getDate();

    for (let i = 0; i < startDay; i++) {
        days.push(null);
    }
    for (let i = 1; i <= totalDays; i++) {
        days.push(i);
    }
    return days;
});

const selectDate = (day) => {
    if (day) {
        selectedDate.value = new Date(currentYear.value, currentMonth.value, day);
        emit('update:modelValue', selectedDate.value.toISOString().split('T')[0]);
        showPicker.value = false;
    }
};

const prevMonth = () => {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
};

const nextMonth = () => {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
};
</script>
