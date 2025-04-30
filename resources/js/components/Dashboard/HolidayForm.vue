<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// State for form visibility
const showForm = ref(false);

// Form for creating a holiday period
const holidayForm = useForm({
    start_date: '',
    end_date: '',
    daily_rate: 0,
});

// State for form submission status
const isProcessing = ref(false);

// Submit the holiday form
function submitHoliday() {
    if (!holidayForm.start_date || !holidayForm.end_date || !holidayForm.daily_rate) {
        alert('Please fill in all fields.');
        return;
    }

    // Validate dates
    const start = new Date(holidayForm.start_date);
    const end = new Date(holidayForm.end_date);
    if (start > end) {
        alert('End date must be after start date.');
        return;
    }

    isProcessing.value = true;
    holidayForm.post(route('holidays.store'), {
        onSuccess: () => {
            console.log('Holiday created successfully');
            holidayForm.reset();
            isProcessing.value = false;
            showForm.value = false; // Hide form after submission
        },
        onError: (errors) => {
            console.error('Error creating holiday:', errors);
            alert('Failed to create holiday: ' + JSON.stringify(errors));
            isProcessing.value = false;
        },
    });
}

// Toggle form visibility
function toggleForm() {
    showForm.value = !showForm.value;
    if (!showForm.value) {
        holidayForm.reset();
    }
}
</script>

<template>
    <div class="mb-6 p-4 bg-gray-700 rounded-lg">
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4">
            <h2 class="text-xl font-semibold mb-2 sm:mb-0">Holiday Payments</h2>
            <button @click="toggleForm"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-md transition duration-200 w-full sm:w-auto">
                {{ showForm ? 'Cancel' : 'Add Holiday' }}
            </button>
        </div>

        <div v-if="showForm" class="bg-gray-800 p-4 sm:p-6 rounded-lg shadow-lg">
            <form @submit.prevent="submitHoliday">
                <!-- Processing Indicator -->
                <div v-if="isProcessing" class="mb-4 p-3 sm:p-4 bg-yellow-600 text-white rounded-lg text-sm sm:text-base">
                    Processing holiday creation...
                </div>

                <!-- Form Fields -->
                <div class="space-y-4 sm:space-y-6">
                    <!-- Start Date -->
                    <div>
                        <label class="block text-sm font-medium mb-1 sm:mb-2">Start Date</label>
                        <input v-model="holidayForm.start_date" type="date"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 sm:p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" />
                        <div v-if="holidayForm.errors.start_date" class="text-red-500 text-xs sm:text-sm mt-1">
                            {{ holidayForm.errors.start_date }}
                        </div>
                    </div>

                    <!-- End Date -->
                    <div>
                        <label class="block text-sm font-medium mb-1 sm:mb-2">End Date</label>
                        <input v-model="holidayForm.end_date" type="date"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 sm:p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" />
                        <div v-if="holidayForm.errors.end_date" class="text-red-500 text-xs sm:text-sm mt-1">
                            {{ holidayForm.errors.end_date }}
                        </div>
                    </div>

                    <!-- Daily Rate -->
                    <div>
                        <label class="block text-sm font-medium mb-1 sm:mb-2">Daily Rate (£)</label>
                        <input v-model.number="holidayForm.daily_rate" type="number" min="0" step="0.01"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 sm:p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" />
                        <div v-if="holidayForm.errors.daily_rate" class="text-red-500 text-xs sm:text-sm mt-1">
                            {{ holidayForm.errors.daily_rate }}
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 sm:px-6 sm:py-3 rounded-md transition duration-200 w-full">
                        Add Holiday Period
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>
</template>

<style scoped>
/* Additional custom styles for fine-tuning responsiveness */
@media (max-width: 640px) {
    input {
        font-size: 0.875rem; /* Smaller font size on mobile */
        padding: 0.5rem; /* Reduced padding */
    }

    button {
        font-size: 0.875rem; /* Smaller font size for buttons */
    }

    label {
        font-size: 0.875rem; /* Smaller label font size */
    }
}
</style>
