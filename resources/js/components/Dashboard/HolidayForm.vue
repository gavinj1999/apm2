<!-- resources/js/components/Dashboard/HolidayForm.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue3-toastify';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    holidays?: Array<{
        id: number;
        start_date: string;
        end_date: string;
        daily_rate: number | string; // Allow string due to potential backend issue
    }>;
}>();

// Default to empty array if holidays is undefined
const safeHolidays = props.holidays ?? [];

// State for form visibility
const showForm = ref(false);

// State for edit mode
const isEditMode = ref(false);
const editingHolidayId = ref<number | null>(null);

// Form for creating/updating a holiday period
const holidayForm = useForm({
    start_date: '',
    end_date: '',
    daily_rate: 0,
});

// State for form submission status
const isProcessing = ref(false);

// Format date for display
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

// Format daily rate to fixed 2 decimals
function formatDailyRate(rate: number | string): string {
    const numericRate = typeof rate === 'string' ? parseFloat(rate) : rate;
    return isNaN(numericRate) ? '0.00' : numericRate.toFixed(2);
}

// Check for overlapping holidays
function hasOverlap(startDate: string, endDate: string, excludeId: number | null = null): boolean {
    const start = new Date(startDate);
    const end = new Date(endDate);
    return safeHolidays.some(holiday => {
        if (excludeId && holiday.id === excludeId) return false;
        const hStart = new Date(holiday.start_date);
        const hEnd = new Date(holiday.end_date);
        return start <= hEnd && end >= hStart;
    });
}

// Submit the holiday form
function submitHoliday() {
    if (!holidayForm.start_date || !holidayForm.end_date || !holidayForm.daily_rate) {
        toast.error('Please fill in all fields.');
        return;
    }

    const start = new Date(holidayForm.start_date);
    const end = new Date(holidayForm.end_date);
    if (isNaN(start.getTime()) || isNaN(end.getTime())) {
        toast.error('Invalid date format.');
        return;
    }
    if (start > end) {
        toast.error('End date must be after start date.');
        return;
    }

    if (hasOverlap(holidayForm.start_date, holidayForm.end_date, editingHolidayId.value)) {
        toast.error('Holiday period overlaps with an existing holiday.');
        return;
    }

    isProcessing.value = true;
    const action = isEditMode.value && editingHolidayId.value
        ? holidayForm.put(route('holidays.update', editingHolidayId.value))
        : holidayForm.post(route('holidays.store'));

    action.then(() => {
        toast.success(isEditMode.value ? 'Holiday updated successfully!' : 'Holiday created successfully!');
        holidayForm.reset();
        isProcessing.value = false;
        showForm.value = false;
        isEditMode.value = false;
        editingHolidayId.value = null;
    }).catch(errors => {
        toast.error('Failed to process holiday: ' + Object.values(errors).join(', '));
        isProcessing.value = false;
    });
}

// Edit a holiday
function editHoliday(id: number) {
    const holiday = safeHolidays.find(h => h.id === id);
    if (!holiday) {
        toast.error('Holiday not found.');
        return;
    }
    holidayForm.start_date = holiday.start_date;
    holidayForm.end_date = holiday.end_date;
    holidayForm.daily_rate = typeof holiday.daily_rate === 'string' ? parseFloat(holiday.daily_rate) : holiday.daily_rate;
    isEditMode.value = true;
    editingHolidayId.value = id;
    showForm.value = true;
}

// Delete a holiday
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

// Toggle form visibility
function toggleForm() {
    showForm.value = !showForm.value;
    if (!showForm.value) {
        holidayForm.reset();
        isEditMode.value = false;
        editingHolidayId.value = null;
    }
}
</script>

<template>
    <div class="mb-6 p-4 bg-gray-700 rounded-lg">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4">
                <h2 class="text-xl font-semibold mb-2 sm:mb-0">Holiday Payments</h2>
                <button
                    @click="toggleForm"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-md transition duration-200 w-full sm:w-auto"
                    :aria-expanded="showForm"
                    aria-label="Toggle holiday form"
                >
                    {{ showForm ? 'Cancel' : isEditMode ? 'Edit Holiday' : 'Add Holiday' }}
                </button>
            </div>

            <!-- List of Holidays -->
            <div v-if="safeHolidays.length" class="mb-4">
                <h3 class="text-lg font-medium mb-2">Existing Holidays</h3>
                <ul class="space-y-2">
                    <li v-for="holiday in safeHolidays" :key="holiday.id" class="flex justify-between items-center">
                        <span>
                            {{ formatDate(holiday.start_date) }} - {{ formatDate(holiday.end_date) }} (£{{ formatDailyRate(holiday.daily_rate) }}/day)
                        </span>
                        <div class="flex gap-2">
                            <button
                                @click="editHoliday(holiday.id)"
                                class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-2 py-1 rounded-md text-sm"
                                aria-label="Edit holiday"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteHoliday(holiday.id)"
                                class="bg-red-600 hover:bg-red-700 text-white font-semibold px-2 py-1 rounded-md text-sm"
                                aria-label="Delete holiday"
                            >
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Holiday Form -->
            <div v-if="showForm" class="bg-gray-800 p-4 sm:p-6 rounded-lg shadow-lg">
                <form @submit.prevent="submitHoliday">
                    <!-- Processing Indicator -->
                    <div v-if="isProcessing" class="mb-4 p-3 sm:p-4 bg-yellow-600 text-white rounded-lg text-sm sm:text-base">
                        Processing {{ isEditMode ? 'update' : 'creation' }} of holiday...
                    </div>

                    <!-- Form Fields -->
                    <div class="space-y-4 sm:space-y-6">
                        <!-- Start Date -->
                        <div>
                            <label for="start_date" class="block text-sm font-medium mb-1 sm:mb-2">Start Date</label>
                            <input
                                id="start_date"
                                v-model="holidayForm.start_date"
                                type="date"
                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 sm:p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base"
                                aria-describedby="start_date_error"
                            />
                            <div
                                v-if="holidayForm.errors.start_date"
                                id="start_date_error"
                                class="text-red-500 text-xs sm:text-sm mt-1"
                            >
                                {{ holidayForm.errors.start_date }}
                            </div>
                        </div>

                        <!-- End Date -->
                        <div>
                            <label for="end_date" class="block text-sm font-medium mb-1 sm:mb-2">End Date</label>
                            <input
                                id="end_date"
                                v-model="holidayForm.end_date"
                                type="date"
                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 sm:p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base"
                                aria-describedby="end_date_error"
                            />
                            <div
                                v-if="holidayForm.errors.end_date"
                                id="end_date_error"
                                class="text-red-500 text-xs sm:text-sm mt-1"
                            >
                                {{ holidayForm.errors.end_date }}
                            </div>
                        </div>

                        <!-- Daily Rate -->
                        <div>
                            <label for="daily_rate" class="block text-sm font-medium mb-1 sm:mb-2">Daily Rate (£)</label>
                            <input
                                id="daily_rate"
                                v-model.number="holidayForm.daily_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 sm:p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base"
                                aria-describedby="daily_rate_error"
                            />
                            <div
                                v-if="holidayForm.errors.daily_rate"
                                id="daily_rate_error"
                                class="text-red-500 text-xs sm:text-sm mt-1"
                            >
                                {{ holidayForm.errors.daily_rate }}
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 sm:px-6 sm:py-3 rounded-md transition duration-200 w-full"
                            :disabled="isProcessing"
                        >
                            {{ isEditMode ? 'Update Holiday' : 'Add Holiday Period' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    input {
        font-size: 0.875rem;
        padding: 0.5rem;
    }

    button {
        font-size: 0.875rem;
    }

    label {
        font-size: 0.875rem;
    }
}
</style>