<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Manage Prices', href: '/prices' },
];

// Props
const { rounds, parcelTypes, flash } = defineProps<{
    rounds: Array<{
        id: number;
        round_id: string;
        name: string;
        roundPricings?: Array<{
            id: number;
            round_id: number;
            parcel_type_id: number;
            price: number | string;
            parcel_type?: { id: number; name: string };
        }>;
    }>;
    parcelTypes: { [key: string]: Array<{ id: number; name: string }> };
    flash?: { success?: string; error?: string };
}>();

// Debug: Log the rounds and parcelTypes data
console.log('Rounds data:', JSON.stringify(rounds, null, 2));
console.log('ParcelTypes data:', JSON.stringify(parcelTypes, null, 2));

// State for create/edit form
const isEditMode = ref(false);
const editingRoundPricingId = ref<number | null>(null);

// Form for creating/updating a price
const priceForm = useForm({
    round_id: '',
    parcel_type_id: '',
    price: 0,
});

// Computed property to filter parcel types based on selected round
const filteredParcelTypes = computed(() => {
    const roundId = priceForm.round_id;
    console.log('Selected round_id:', roundId);
    console.log('ParcelTypes for round:', JSON.stringify(parcelTypes[roundId] || [], null, 2));
    return roundId && parcelTypes[roundId] ? parcelTypes[roundId] : [];
});

// Function to reset the form
function resetForm() {
    priceForm.reset();
    priceForm.round_id = rounds.length > 0 ? String(rounds[0].id) : '';
    priceForm.parcel_type_id = filteredParcelTypes.value.length > 0 ? String(filteredParcelTypes.value[0].id) : '';
    priceForm.price = 0;
}

// Function to submit the form (create or update)
function submitPrice() {
    if (isEditMode.value && editingRoundPricingId.value) {
        priceForm.put(route('prices.update', editingRoundPricingId.value), {
            onSuccess: () => {
                isEditMode.value = false;
                editingRoundPricingId.value = null;
                resetForm();
            },
        });
    } else {
        priceForm.post(route('prices.store'), {
            onSuccess: () => {
                resetForm();
            },
        });
    }
}

// Function to enter edit mode
function editPrice(roundPricing: any) {
    if (!roundPricing.parcel_type) {
        alert('Cannot edit this price: Parcel type is missing.');
        return;
    }
    console.log('Editing roundPricing:', JSON.stringify(roundPricing, null, 2));
    priceForm.round_id = String(roundPricing.round_id);
    priceForm.parcel_type_id = String(roundPricing.parcel_type_id);
    priceForm.price = Number(roundPricing.price);
    isEditMode.value = true;
    editingRoundPricingId.value = roundPricing.id;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Function to cancel edit mode
function cancelEdit() {
    isEditMode.value = false;
    editingRoundPricingId.value = null;
    resetForm();
}

// Function to delete a price
function deletePrice(id: number) {
    if (confirm('Are you sure you want to delete this price?')) {
        router.delete(route('prices.destroy', id));
    }
}

// Initialize form with default round
if (rounds.length > 0 && !isEditMode.value) {
    priceForm.round_id = String(rounds[0].id);
    if (filteredParcelTypes.value.length > 0) {
        priceForm.parcel_type_id = String(filteredParcelTypes.value[0].id);
    }
}
</script>

<template>
    <Head title="Manage Prices" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 bg-gray-900 min-h-screen text-white">
            <!-- Flash Messages -->
            <div v-if="flash?.success" class="mb-4 p-4 bg-green-600 text-white rounded-lg">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="mb-4 p-4 bg-red-600 text-white rounded-lg">
                {{ flash.error }}
            </div>

            <!-- Create/Update Price Form -->
            <h1 class="text-3xl font-bold mb-6">Manage Prices</h1>
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">{{ isEditMode ? 'Edit Price' : 'Add New Price' }}</h2>
                <form @submit.prevent="submitPrice" class="bg-gray-800 p-6 rounded-lg shadow-lg">
                    <div class="mb-5">
                        <label class="block text-sm font-medium mb-2">Round</label>
                        <select
                            v-model="priceForm.round_id"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :disabled="isEditMode"
                        >
                            <option value="" disabled>Select a round</option>
                            <option v-for="round in rounds" :key="round.id" :value="round.id">{{ round.round_id }}</option>
                        </select>
                        <div v-if="priceForm.errors.round_id" class="text-red-500 text-sm mt-1">{{ priceForm.errors.round_id }}</div>
                    </div>
                    <div class="mb-5">
                        <label class="block text-sm font-medium mb-2">Parcel Type</label>
                        <select
                            v-model="priceForm.parcel_type_id"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :disabled="isEditMode || filteredParcelTypes.length === 0"
                        >
                            <option value="" disabled>Select a parcel type</option>
                            <option v-for="type in filteredParcelTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                        <div v-if="priceForm.errors.parcel_type_id" class="text-red-500 text-sm mt-1">{{ priceForm.errors.parcel_type_id }}</div>
                        <div v-if="filteredParcelTypes.length === 0" class="text-gray-400 text-sm mt-1">
                            No parcel types available for this round.
                        </div>
                    </div>
                    <div class="mb-5">
                        <label class="block text-sm font-medium mb-2">Price (£)</label>
                        <input
                            v-model.number="priceForm.price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter price"
                        />
                        <div v-if="priceForm.errors.price" class="text-red-500 text-sm mt-1">{{ priceForm.errors.price }}</div>
                    </div>
                    <div class="flex gap-3">
                        <button
                            type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-md transition duration-200"
                            :disabled="filteredParcelTypes.length === 0"
                        >
                            {{ isEditMode ? 'Update Price' : 'Add Price' }}
                        </button>
                        <button
                            v-if="isEditMode"
                            type="button"
                            @click="cancelEdit"
                            class="bg-gray-600 hover:bg-gray-700 text-white font-semibold px-6 py-3 rounded-md transition duration-200"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Round Pricing Table (Grouped by Round) -->
            <h2 class="text-xl font-semibold mb-4">Current Prices</h2>
            <div v-if="rounds.length === 0" class="text-gray-400">
                You have no rounds. Please create a round to manage prices.
            </div>
            <div v-else-if="rounds.every(round => !round.roundPricings || round.roundPricings.length === 0)" class="text-gray-400">
                No prices available.
            </div>
            <div v-else class="bg-gray-800 rounded-lg shadow-lg overflow-x-auto">
                <table class="w-full border border-gray-600">
                    <thead>
                        <tr class="bg-gray-700">
                            <th class="p-3 text-left text-sm font-medium">Round</th>
                            <th class="p-3 text-left text-sm font-medium">Parcel Type</th>
                            <th class="p-3 text-left text-sm font-medium">Price (£)</th>
                            <th class="p-3 text-left text-sm font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="round in rounds" :key="round.id">
                            <!-- Row for the Round (Header Row for the Group) -->
                            <tr class="bg-gray-600">
                                <td class="p-3 font-semibold" colspan="4">
                                    {{ round.round_id }}
                                </td>
                            </tr>
                            <!-- Rows for each Parcel Type under the Round -->
                            <tr
                                v-for="roundPricing in round.roundPricings || []"
                                :key="roundPricing.id"
                                class="border-b border-gray-600"
                            >
                                <td class="p-3"></td> <!-- Empty cell under Round column -->
                                <td class="p-3">{{ roundPricing.parcel_type.name }}</td>
                                <td class="p-3">{{ Number(roundPricing.price).toFixed(2) }}</td>
                                <td class="p-3">
                                    <button
                                        @click="editPrice(roundPricing)"
                                        class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-2 py-0.5 rounded-md mr-1 text-xs"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="deletePrice(roundPricing.id)"
                                        class="bg-red-600 hover:bg-red-700 text-white font-semibold px-2 py-0.5 rounded-md text-xs"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
