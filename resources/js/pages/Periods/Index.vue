<template>
    <AppLayout>
        <div class="min-h-screen bg-gray-900 text-gray-100 py-8 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-100">Manage Periods</h1>
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 flex items-center" @click="openCreateModal">
                        <PlusIcon class="w-5 h-5 mr-2" />
                        Add Period
                    </button>
                </div>

                <!-- Flash Messages -->
                <div v-if="$page.props.flash?.success" class="bg-green-800 text-green-100 p-4 rounded-lg mb-6 flex justify-between items-center">
                    {{ $page.props.flash.success }}
                    <button class="text-green-100 hover:text-green-200" @click="$page.props.flash.success = null">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
                <div v-if="$page.props.flash?.error" class="bg-red-800 text-red-100 p-4 rounded-lg mb-6 flex justify-between items-center">
                    {{ $page.props.flash.error }}
                    <button class="text-red-100 hover:text-red-200" @click="$page.props.flash.error = null">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <!-- Periods Table -->
                <div class="bg-gray-800 shadow-md rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-700">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Start Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">End Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <tr v-for="period in periods" :key="period.id" class="hover:bg-gray-700 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ period.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(period.start_date) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(period.end_date) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button class="text-blue-400 hover:text-blue-300 mr-4" @click="openEditModal(period)">
                                            <PencilIcon class="w-5 h-5 inline" />
                                            Edit
                                        </button>
                                        <button class="text-red-400 hover:text-red-300" @click="deletePeriod(period.id)">
                                            <TrashIcon class="w-5 h-5 inline" />
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!periods.length">
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-400">No periods found. Click "Add Period" to create one.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Custom Modal -->
                <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 fade" v-bind:class="{ 'fade-enter-active': showModal, 'fade-leave-active': !showModal }">
                    <div class="bg-[#1a2e3a] text-gray-100 rounded-lg shadow-xl w-full max-w-md p-6 relative">
                        <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-300" @click="closeModal">
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                        <h2 class="text-xl font-bold mb-4">{{ isEditing ? 'Edit Period' : 'Create Period' }}</h2>
                        <form @submit.prevent="submit">
                            <div class="mb-4">
                                <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
                                <input type="text" v-model="form.name" id="name" class="mt-1 block w-full bg-gray-700 border-gray-600 rounded-md shadow-sm text-gray-100 focus:ring-blue-500 focus:border-blue-500" :class="{ 'border-red-500': form.errors.name }" required placeholder="e.g., Period 1">
                                <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
                            </div>
                            <div class="mb-4">
                                <label for="start_date" class="block text-sm font-medium text-gray-300">Start Date</label>
                                <DatePicker v-model="form.start_date" :error="form.errors.start_date" />
                                <!-- Debug display -->
                                <span class="text-gray-500 text-sm">Start Date Debug: {{ form.start_date }}</span>
                            </div>
                            <div class="mb-4">
                                <label for="end_date" class="block text-sm font-medium text-gray-300">End Date</label>
                                <DatePicker v-model="form.end_date" :error="form.errors.end_date" />
                                <!-- Debug display -->
                                <span class="text-gray-500 text-sm">End Date Debug: {{ form.end_date }}</span>
                            </div>
                            <div class="flex justify-end space-x-4">
                                <button type="button" class="bg-gray-600 text-gray-200 px-4 py-2 rounded-md hover:bg-gray-500" @click="closeModal">Cancel</button>
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700" :disabled="form.processing">
                                    {{ form.processing ? 'Saving...' : isEditing ? 'Update' : 'Create' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Toast Notification -->
                <div v-if="showToast" class="fixed top-4 right-4 z-50 fade" v-bind:class="{ 'fade-enter-active': showToast, 'fade-leave-active': !showToast }">
                    <div class="bg-yellow-900 border-l-4 border-yellow-500 text-yellow-200 p-4 rounded-md shadow-md">
                        <div class="flex items-center">
                            <ExclamationCircleIcon class="w-6 h-6 mr-2" />
                            <p>{{ toastMessage }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PlusIcon, XMarkIcon, PencilIcon, TrashIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';

defineProps({
    periods: Array,
    period: Object, // Optional, for edit mode
});

// Modal state
const isEditing = ref(false);
const showModal = ref(false);

// Toast state
const showToast = ref(false);
const toastMessage = ref('');

// Format date to DD/MM/YYYY
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
};

// Open create modal
const openCreateModal = () => {
    isEditing.value = false;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

// Open edit modal
const openEditModal = (period) => {
    isEditing.value = true;
    form.reset();
    form.clearErrors();
    form.name = period.name;
    form.start_date = period.start_date;
    form.end_date = period.end_date;
    form.id = period.id;
    console.log('openEditModal - Period:', period);
    console.log('openEditModal - Form values:', form);
    showModal.value = true;
};

// Close modal
const closeModal = () => {
    showModal.value = false;
    form.reset();
    form.clearErrors();
};

// Form state
const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
    id: null,
});

// Submit form (create or update)
const submit = () => {
    if (isEditing.value) {
        form.put(route('periods.update', form.id), {
            onSuccess: () => {
                closeModal();
            },
        });
    } else {
        form.post(route('periods.store'), {
            onSuccess: () => {
                closeModal();
            },
        });
    }
};

// Delete period
const deletePeriod = (id) => {
    if (confirm('Are you sure you want to delete this period?')) {
        router.delete(route('periods.destroy', id));
    }
};

// Show toast (for future error handling)
const showToastMessage = (message) => {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 3000);
};
</script>
