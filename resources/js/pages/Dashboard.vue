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
const { groupedManifests, totalEarnings, rounds, parcelTypes, flash } = defineProps<{
    groupedManifests: Array<{
        period_name: string;
        dates: Array<{
            date: string;
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
                    value: number;
                }>;
                total_value: number;
            }>;
        }>;
    }>;
    totalEarnings: number;
    rounds: Array<{ id: number; round_id: string; name: string }>;
    parcelTypes: Array<{ id: number; name: string }>;
    flash?: { success?: string; error?: string };
}>();

// Debug: Log the groupedManifests data
console.log('Grouped Manifests:', JSON.stringify(groupedManifests, null, 2));

// Default delivery date (today)
const now = new Date();
const day = String(now.getDate()).padStart(2, '0');
const month = String(now.getMonth() + 1).padStart(2, '0');
const year = now.getFullYear();
const defaultDeliveryDate = `${year}-${month}-${day}`;

// State for edit mode
const isEditMode = ref(false);
const editingManifestId = ref<number | null>(null);

// Form for creating/updating a manifest
const manifestForm = useForm({
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

// Flatten groupedManifests into a list of rows for rendering
const flattenedRows = computed(() => {
    const rows = [];
    groupedManifests.forEach((period, pIndex) => {
        period.dates.forEach((date, dIndex) => {
            date.manifests.forEach((manifest, mIndex) => {
                rows.push({
                    period: period.period_name,
                    date: date.date,
                    manifest: manifest,
                    isFirstInPeriod: dIndex === 0 && mIndex === 0,
                    isFirstInDate: mIndex === 0,
                    periodRowspan: period.dates.reduce((sum, d) => sum + d.manifests.length, 0),
                    dateRowspan: date.manifests.length,
                });
            });
        });
    });
    return rows;
});

// Submit function (handles both create and update)
function submitManifest() {
    if (isEditMode.value && editingManifestId.value) {
        manifestForm.put(route('manifests.update-by-id', editingManifestId.value), {
            onSuccess: () => {
                isEditMode.value = false;
                editingManifestId.value = null;
                manifestForm.reset();
            },
        });
    } else {
        manifestForm.post(route('manifests.store'), {
            onSuccess: () => {
                manifestForm.reset();
            },
        });
    }
}

// Function to enter edit mode
function editManifest(id: number) {
    fetch(`/manifests/${id}`, {
        headers: {
            Accept: 'application/json',
        },
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            if (!data.delivery_date || !data.status || !data.round_id) {
                alert('Invalid manifest data received.');
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
            window.scrollTo({ top: 0, behavior: 'smooth' });
        })
        .catch(error => {
            console.error('Error fetching manifest:', error);
            alert('Failed to load manifest data: ' + error.message);
        });
}

// Function to cancel edit mode
function cancelEdit() {
    isEditMode.value = false;
    editingManifestId.value = null;
    manifestForm.reset();
}

// Function to delete a manifest
function deleteManifest(id: number, date: string) {
    if (confirm(`Are you sure you want to delete the manifest for ${formatDate(date)}?`)) {
        router.delete(route('manifests.destroy', id), {
            onSuccess: () => {
                console.log('Manifest deleted successfully');
            },
            onError: (error) => {
                console.error('Error deleting manifest:', error);
                alert('Failed to delete manifest: ' + (error.message || 'Unknown error'));
            }
        });
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
                                            <label class="block text-xs text-gray-400 mb-1">M</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).manifested"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">R</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).re_manifested"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">CF</label>
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
                                            <label class="block text-xs text-gray-400 mb-1">M</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).manifested"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">R</label>
                                            <input
                                                v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).re_manifested"
                                                type="number"
                                                min="0"
                                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-400 mb-1">CF</label>
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
                                    <label class="block text-sm font-bold">Man: {{ getTotals(manifestForm).manifested }}</label>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold">Re-man: {{ getTotals(manifestForm).re_manifested }}</label>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold">CF: {{ getTotals(manifestForm).carried_forward }}</label>
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
            <div v-if="groupedManifests.length === 0" class="text-gray-400">
                No manifests available.
            </div>
            <div v-else class="bg-gray-800 rounded-lg shadow-lg overflow-x-auto">
                <table class="w-full border border-gray-600">
                    <thead>
                        <tr class="bg-gray-700">
                            <th class="p-3 text-left text-sm font-medium">Period</th>
                            <th class="p-3 text-left text-sm font-medium">Date</th>
                            <th class="p-3 text-left text-sm font-medium">Round</th>
                            <th v-for="type in parcelTypes" :key="type.id" class="p-3 text-left text-sm font-medium">{{ type.name }}</th>
                            <th class="p-3 text-left text-sm font-medium">Total Value</th>
                            <th class="p-3 text-left text-sm font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in flattenedRows" :key="row.manifest.id" class="border-b border-gray-600">
                            <!-- Period (only shown on the first row of the period) -->
                            <td v-if="row.isFirstInPeriod" :rowspan="row.periodRowspan" class="p-3 font-semibold">
                                {{ row.period }}
                            </td>
                            <!-- Date (only shown on the first manifest of the date) -->
                            <td v-if="row.isFirstInDate" :rowspan="row.dateRowspan" class="p-3">
                                {{ formatDate(row.date) }}
                            </td>
                            <!-- Round -->
                            <td class="p-3">
                                {{ row.manifest.round_id }}
                            </td>
                            <!-- Parcel Types -->
                            <td v-for="type in parcelTypes" :key="type.id" class="p-3 relative group">
                                <span class="cursor-pointer">
                                    {{ row.manifest.quantities.find(q => q.parcel_type_id === type.id).total }}
                                    <span class="absolute hidden group-hover:block bg-gray-600 text-white text-xs rounded py-1 px-2 -mt-8 left-1/2 transform -translate-x-1/2">
                                        Value: £{{ row.manifest.quantities.find(q => q.parcel_type_id === type.id).value.toFixed(2) }}
                                    </span>
                                </span>
                            </td>
                            <!-- Total Value -->
                            <td class="p-3">
                                £{{ row.manifest.total_value.toFixed(2) }}
                            </td>
                            <!-- Actions -->
                            <td class="p-3 flex gap-1">
                                <button
                                    @click="editManifest(row.manifest.id)"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold px-2 py-0.5 rounded-md text-xs"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteManifest(row.manifest.id, row.manifest.delivery_date)"
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

<style scoped>
/* Optional: Adjust tooltip positioning if needed */
.group:hover .group-hover\:block {
    z-index: 10;
}

/* Ensure table cells are aligned */
td {
    vertical-align: top;
}
</style>
