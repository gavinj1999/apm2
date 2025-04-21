<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Props
const { manifests, totalEarnings, rounds, parcelTypes, flash } = defineProps<{
    manifests: Array<{
        id: number;
        delivery_date: string;
        round_id: string;
        quantities: Array<{
            parcel_type_id: number;
            name: string;
            manifested: number;
            re_manifested: number;
            carried_forward: number;
            total: number;
        }>;
        total_value: number;
    }>;
    totalEarnings: number;
    rounds: Array<{ id: number; round_id: string; name: string }>;
    parcelTypes: Array<{ id: number; name: string }>;
    flash?: { success?: string; error?: string };
}>();

// Generate default manifest number in HHMMDDMMYYYY format
const now = new Date();
const hours = String(now.getHours()).padStart(2, '0');
const minutes = String(now.getMinutes()).padStart(2, '0');
const day = String(now.getDate()).padStart(2, '0');
const month = String(now.getMonth() + 1).padStart(2, '0');
const year = now.getFullYear();
const defaultManifestNumber = `${hours}${minutes}${day}${month}${year}`;

// Default delivery date (today)
const defaultDeliveryDate = `${year}-${month}-${day}`;

// State for edit mode
const isEditMode = ref(false);
const editingManifestId = ref<number | null>(null);

// Form for creating/updating a manifest
const manifestForm = useForm({
    manifest_number: defaultManifestNumber,
    delivery_date: defaultDeliveryDate,
    status: 'pending',
    round_id: '',
    quantities: parcelTypes.map(type => ({
        parcel_type_id: type.id,
        manifested: 0,
        re_manifested: 0,
        carried_forward: 0
    })),
});

// Submit function (handles both create and update)
function submitManifest() {
    if (isEditMode.value && editingManifestId.value) {
        manifestForm.put(route('manifests.update-by-id', editingManifestId.value));
    } else {
        manifestForm.post(route('manifests.store'));
    }
}

// Function to enter edit mode
function editManifest(id: number) {
    fetch(`/manifests/${id}`, {
        headers: {
            Accept: 'application/json',
        },
    })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            manifestForm.delivery_date = data.delivery_date;
            manifestForm.status = data.status;
            manifestForm.round_id = data.round_id;
            manifestForm.quantities = parcelTypes.map(type => {
                const quantity = data.quantities.find(q => q.parcel_type_id === type.id) || {
                    parcel_type_id: type.id,
                    manifested: 0,
                    re_manifested: 0,
                    carried_forward: 0
                };
                return {
                    parcel_type_id: type.id,
                    manifested: quantity.manifested,
                    re_manifested: quantity.re_manifested,
                    carried_forward: quantity.carried_forward
                };
            });
            isEditMode.value = true;
            editingManifestId.value = id;
            window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll to top
        })
        .catch(error => {
            console.error('Error fetching manifest:', error);
            alert('Failed to load manifest data.');
        });
}

// Function to cancel edit mode
function cancelEdit() {
    isEditMode.value = false;
    editingManifestId.value = null;
    manifestForm.reset();
    manifestForm.manifest_number = defaultManifestNumber; // Reset manifest_number to default
}

// Function to delete a manifest
function deleteManifest(id: number, date: string) {
    if (confirm(`Are you sure you want to delete the manifest for ${formatDate(date)}?`)) {
        router.delete(route('manifests.delete-by-id', id));
    }
}

// Computed totals for the create form
function getTotals(form: any) {
    const summaries = form.quantities || [];
    return {
        manifested: Array.isArray(summaries) ? summaries.reduce((sum, s) => sum + Number(s.manifested || 0), 0) : 0,
        re_manifested: Array.isArray(summaries) ? summaries.reduce((sum, s) => sum + Number(s.re_manifested || 0), 0) : 0,
        carried_forward: Array.isArray(summaries) ? summaries.reduce((sum, s) => sum + Number(s.carried_forward || 0), 0) : 0
    };
}

// Split parcel types into two columns (4 per column)
const leftColumnTypes = parcelTypes.slice(0, 4); // First 4: Postable, Small Packet, Packet, Parcels
const rightColumnTypes = parcelTypes.slice(4, 8); // Last 4: Hanging Garment, Heavy, Next Day, POD-Signature

// Helper function to format date as DD/MM/YYYY
function formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-based
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
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

            <!-- Create/Update Manifest Form -->
            <h1 class="text-3xl font-bold mb-6">Dashboard</h1>
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">{{ isEditMode ? 'Edit Manifest' : 'Create New Manifest' }}</h2>
                <form @submit.prevent="submitManifest" class="bg-gray-800 p-6 rounded-lg shadow-lg">
                    <div v-if="!isEditMode" class="mb-5">
                        <label class="block text-sm font-medium mb-2">Manifest Number</label>
                        <input
                            v-model="manifestForm.manifest_number"
                            type="text"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter manifest number"
                        />
                        <div v-if="manifestForm.errors.manifest_number" class="text-red-500 text-sm mt-1">{{ manifestForm.errors.manifest_number }}</div>
                    </div>
                    <div class="mb-5">
                        <label class="block text-sm font-medium mb-2">Delivery Date</label>
                        <input
                            v-model="manifestForm.delivery_date"
                            type="date"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <div v-if="manifestForm.errors.delivery_date" class="text-red-500 text-sm mt-1">{{ manifestForm.errors.delivery_date }}</div>
                    </div>
                    <div class="mb-5">
                        <label class="block text-sm font-medium mb-2">Status</label>
                        <select
                            v-model="manifestForm.status"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="pending">Pending</option>
                            <option value="in-progress">In-Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                        <div v-if="manifestForm.errors.status" class="text-red-500 text-sm mt-1">{{ manifestForm.errors.status }}</div>
                    </div>
                    <div class="mb-5">
                        <label class="block text-sm font-medium mb-2">Round</label>
                        <select
                            v-model="manifestForm.round_id"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="" disabled>Select a round</option>
                            <option v-for="round in rounds" :key="round.id" :value="round.id">{{ round.round_id }}</option>
                        </select>
                        <div v-if="manifestForm.errors.round_id" class="text-red-500 text-sm mt-1">{{ manifestForm.errors.round_id }}</div>
                    </div>
                    <!-- Parcel Quantities Section -->
                    <div class="mb-5">
                        <h3 class="text-md font-semibold mb-3">Parcel Quantities</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Left Column -->
                            <div>
                                <div v-for="type in leftColumnTypes" :key="type.id" class="mb-4">
                                    <label class="block text-sm font-medium mb-2">{{ type.name }}</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Manifested</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).manifested"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Re-manifested</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).re_manifested"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Carried Forward</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).carried_forward"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Right Column -->
                            <div>
                                <div v-for="type in rightColumnTypes" :key="type.id" class="mb-4">
                                    <label class="block text-sm font-medium mb-2">{{ type.name }}</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Manifested</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).manifested"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Re-manifested</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).re_manifested"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">Carried Forward</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).carried_forward"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Totals Section -->
                        <div class="mt-4 p-3 bg-gray-700 rounded-lg">
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-sm font-bold">Total</label>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold">Manifested: {{ getTotals(manifestForm).manifested }}</label>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold">Re-manifested: {{ getTotals(manifestForm).re_manifested }}</label>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold">Carried Forward: {{ getTotals(manifestForm).carried_forward }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button
                            type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-md transition duration-200"
                        >
                            {{ isEditMode ? 'Update Manifest' : 'Create Manifest' }}
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

            <!-- Manifest Summary -->
            <h2 class="text-xl font-semibold mb-4">Manifest Summary</h2>
            <p class="mb-6 text-lg">Total Earnings: <span class="font-bold">£{{ totalEarnings.toFixed(2) }}</span></p>
            <div v-if="manifests.length === 0" class="text-gray-400">
                No manifests available.
            </div>
            <div v-else class="bg-gray-800 rounded-lg shadow-lg overflow-x-auto">
                <table class="w-full border border-gray-600">
                    <thead>
                        <tr class="bg-gray-700">
                            <th class="p-3 text-left text-sm font-medium">Date</th>
                            <th class="p-3 text-left text-sm font-medium">Round</th>
                            <th v-for="type in parcelTypes" :key="type.id" class="p-3 text-left text-sm font-medium">{{ type.name }}</th>
                            <th class="p-3 text-left text-sm font-medium">Total Value</th>
                            <th class="p-3 text-left text-sm font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="manifest in manifests" :key="manifest.id" class="border-b border-gray-600">
                            <td class="p-3">{{ formatDate(manifest.delivery_date) }}</td>
                            <td class="p-3">{{ manifest.round_id }}</td>
                            <td v-for="quantity in manifest.quantities" :key="quantity.parcel_type_id" class="p-3">{{ quantity.total }}</td>
                            <td class="p-3">£{{ manifest.total_value.toFixed(2) }}</td>
                            <td class="p-3">
                                <button
                                    @click="editManifest(manifest.id)"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-2 py-0.5 rounded-md mr-1 text-xs"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteManifest(manifest.id, manifest.delivery_date)"
                                    class="bg-red-600 hover:bg-red-700 text-white font-semibold px-2 py-0.5 rounded-md text-xs"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
