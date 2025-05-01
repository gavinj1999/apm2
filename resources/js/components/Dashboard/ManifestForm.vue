<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    rounds: Array<{ id: number; round_id: string; name: string }>;
    parcelTypes: Array<{ id: number; name: string; sort_order: number }>;
}>();

// Default delivery date (today)
const now = new Date();
const day = String(now.getDate()).padStart(2, '0');
const month = String(now.getMonth() + 1).padStart(2, '0');
const year = now.getFullYear();
const defaultDeliveryDate = `${year}-${month}-${day}`;

// State for edit mode
const isEditMode = ref(false);
const editingManifestId = ref<number | null>(null);
const isProcessing = ref(false);

// Sortable parcel types (copy of props.parcelTypes to allow reordering)
const sortableParcelTypes = ref([...props.parcelTypes].sort((a, b) => a.sort_order - b.sort_order));

// Form for creating/updating a manifest
const manifestForm = useForm({
    delivery_date: defaultDeliveryDate,
    status: 'pending',
    round_id: '',
    quantities: sortableParcelTypes.value.map(type => ({
        parcel_type_id: type.id,
        manifested: 0,
        re_manifested: 0,
        carried_forward: 0,
    })),
});

// Update submitManifest to display errors
function submitManifest() {
    if (!manifestForm.round_id) {
        alert('Please select a round.');
        isProcessing.value = false;
        return;
    }
    console.log('Submitting form', manifestForm.data());
    isProcessing.value = true;

    if (isEditMode.value && editingManifestId.value) {
        manifestForm.put(route('manifests.update', editingManifestId.value), {
            onSuccess: () => {
                console.log('Update success');
                isEditMode.value = false;
                editingManifestId.value = null;
                manifestForm.reset();
                isProcessing.value = false;
            },
            onError: (errors) => {
                console.error('Update error', errors);
                isProcessing.value = false;
                alert('Failed to update manifest: ' + JSON.stringify(errors));
            },
        });
    } else {
        manifestForm.post(route('manifests.store'), {
            onSuccess: () => {
                console.log('Create success');
                manifestForm.reset();
                isProcessing.value = false;
            },
            onError: (errors) => {
                console.error('Create error', errors);
                isProcessing.value = false;
                alert('Failed to create manifest: ' + JSON.stringify(errors));
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
            manifestForm.delivery_date = data.delivery_date;
            manifestForm.status = data.status;
            manifestForm.round_id = data.round_id;
            manifestForm.quantities = sortableParcelTypes.value.map(type => {
                const quantity = data.quantities.find(q => q.parcel_type_id === type.id) || {
                    parcel_type_id: type.id,
                    manifested: 0,
                    re_manifested: 0,
                    carried_forward: 0,
                };
                return {
                    parcel_type_id: type.id,
                    manifested: quantity.manifested,
                    re_manifested: quantity.re_manifested,
                    carried_forward: quantity.carried_forward,
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

// Computed totals for the create form
function getTotals(form: any) {
    const summaries = form.quantities || [];
    return {
        manifested: Array.isArray(summaries) ? summaries.reduce((sum, s) => sum + Number(s.manifested || 0), 0) : 0,
        re_manifested: Array.isArray(summaries) ? summaries.reduce((sum, s) => sum + Number(s.re_manifested || 0), 0) : 0,
        carried_forward: Array.isArray(summaries) ? summaries.reduce((sum, s) => sum + Number(s.carried_forward || 0), 0) : 0,
    };
}

// Drag-and-drop state
const draggedIndex = ref<number | null>(null);
const dropTargetIndex = ref<number | null>(null);

// Split parcel types into two columns for desktop (4 per column)
const leftColumnTypes = computed(() => sortableParcelTypes.value.slice(0, 4));
const rightColumnTypes = computed(() => sortableParcelTypes.value.slice(4, 9));

// Drag-and-drop handlers
function onDragStart(event: DragEvent, index: number) {
    draggedIndex.value = index;
    event.dataTransfer?.setData('text/plain', index.toString());
    // Add a class to the dragged element
    const target = event.target as HTMLElement;
    setTimeout(() => {
        target.classList.add('dragging');
    }, 0);
}

function onDragOver(event: DragEvent, index: number) {
    event.preventDefault(); // Allow dropping
    dropTargetIndex.value = index; // Highlight the drop target
}

function onDragLeave() {
    dropTargetIndex.value = null; // Remove highlight when leaving
}

function onDragEnd() {
    draggedIndex.value = null;
    dropTargetIndex.value = null;
}

async function onDrop(event: DragEvent, dropIndex: number) {
    event.preventDefault();
    if (draggedIndex.value === null) return;

    const draggedItem = sortableParcelTypes.value[draggedIndex.value];
    sortableParcelTypes.value.splice(draggedIndex.value, 1); // Remove from original position
    sortableParcelTypes.value.splice(dropIndex, 0, draggedItem); // Insert at new position

    // Update sort_order for all parcel types
    const updatedOrder = sortableParcelTypes.value.map((type, idx) => ({
        id: type.id,
        sort_order: idx + 1,
    }));

    // Save the original order in case we need to revert
    const originalOrder = [...sortableParcelTypes.value];

    try {
        // Get the CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            throw new Error('CSRF token not found');
        }

        console.log('CSRF Token:', csrfToken); // Debug: Log the token

        // Send the updated order to the server using fetch
        const response = await fetch('/parcel-types/sort', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ order: updatedOrder }),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error('Server did not confirm success');
        }

        console.log('Parcel types order updated successfully');
    } catch (error) {
        console.error('Failed to update parcel types order:', error);
        alert('Failed to update parcel types order: ' + error.message);
        // Revert the order if the server update fails
        sortableParcelTypes.value = originalOrder;
    }

    draggedIndex.value = null;
    dropTargetIndex.value = null;
}

const manifestLabel = "M";
const remanifestedLabel = "R";
const carriedForwardLabel = "CF";

// Expose editManifest to parent
defineExpose({ editManifest });
</script>

<template>
    <div class="mb-8">
        <!-- Processing Indicator -->
        <div v-if="isProcessing" class="mb-4 p-4 bg-yellow-600 text-white rounded-lg">
            Processing {{ isEditMode ? 'update' : 'creation' }} of manifest...
        </div>

        <h2 class="text-xl font-semibold mb-4">{{ isEditMode ? 'Edit Manifest' : 'Create New Manifest' }}</h2>
        <form @submit.prevent="submitManifest" class="bg-gray-800 p-6 rounded-lg shadow-lg">
            <!-- Delivery Date -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Delivery Date</label>
                <input v-model="manifestForm.delivery_date" type="date"
                    class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base" />
                <div v-if="manifestForm.errors.delivery_date" class="text-red-500 text-sm mt-1">
                    {{ manifestForm.errors.delivery_date }}
                </div>
                <div v-if="manifestForm.errors.round_id" class="text-red-500 text-sm mt-1">
                    {{ manifestForm.errors.round_id }}
                </div>
                <div v-for="(error, key) in manifestForm.errors" :key="key" class="text-red-500 text-sm mt-1">
                    {{ error }}
                </div>
            </div>

            <!-- Status -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Status</label>
                <select v-model="manifestForm.status"
                    class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base">
                    <option value="pending">Pending</option>
                    <option value="in-progress">In-Progress</option>
                    <option value="completed">Completed</option>
                </select>
                <div v-if="manifestForm.errors.status" class="text-red-500 text-sm mt-1">{{
                    manifestForm.errors.status }}</div>
            </div>

            <!-- Round -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Round</label>
                <select v-model="manifestForm.round_id"
                    class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base">
                    <option value="" disabled>Select a round</option>
                    <option v-for="round in rounds" :key="round.id" :value="round.id">{{ round.round_id }}
                    </option>
                </select>
                <div v-if="manifestForm.errors.round_id" class="text-red-500 text-sm mt-1">{{
                    manifestForm.errors.round_id }}</div>
            </div>

            <!-- Parcel Quantities Section -->
            <div class="mb-6">
                <h3 class="text-md font-semibold mb-4">Parcel Quantities</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div>
                        <div v-for="(type, index) in leftColumnTypes" :key="type.id" class="mb-6 relative"
                            draggable="true" @dragstart="onDragStart($event, index)" @dragover="onDragOver($event, index)"
                            @dragleave="onDragLeave" @dragend="onDragEnd" @drop="onDrop($event, index)">
                            <!-- Drop Indicator -->
                            <div v-if="dropTargetIndex === index && draggedIndex !== index"
                                class="absolute top-0 left-0 w-full h-1 bg-blue-500 z-10"></div>
                            <div class="flex items-center mb-2">
                                <svg class="drag-handle w-5 h-5 text-gray-400 mr-2 cursor-move" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 5h2m4 0h2m-8 4h2m4 0h2m-8 4h2m4 0h2m-8 4h2m4 0h2"></path>
                                </svg>
                                <label class="block text-sm font-medium cursor-move">{{ type.name }}</label>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">{{ manifestLabel }}</label>
                                    <input
                                        v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).manifested"
                                        type="number" min="0"
                                        class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">{{ remanifestedLabel }}</label>
                                    <input
                                        v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).re_manifested"
                                        type="number" min="0"
                                        class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">{{ carriedForwardLabel }}</label>
                                    <input
                                        v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).carried_forward"
                                        type="number" min="0"
                                        class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Right Column -->
                    <div>
                        <div v-for="(type, index) in rightColumnTypes" :key="type.id" class="mb-6 relative"
                            draggable="true" @dragstart="onDragStart($event, index + 4)" @dragover="onDragOver($event, index + 4)"
                            @dragleave="onDragLeave" @dragend="onDragEnd" @drop="onDrop($event, index + 4)">
                            <!-- Drop Indicator -->
                            <div v-if="dropTargetIndex === index + 4 && draggedIndex !== index + 4"
                                class="absolute top-0 left-0 w-full h-1 bg-blue-500 z-10"></div>
                            <div class="flex items-center mb-2">
                                <svg class="drag-handle w-5 h-5 text-gray-400 mr-2 cursor-move" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 5h2m4 0h2m-8 4h2m4 0h2m-8 4h2m4 0h2m-8 4h2m4 0h2"></path>
                                </svg>
                                <label class="block text-sm font-medium cursor-move">{{ type.name }}</label>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">{{ manifestLabel }}</label>
                                    <input
                                        v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).manifested"
                                        type="number" min="0"
                                        class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">{{ remanifestedLabel }}</label>
                                    <input
                                        v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).re_manifested"
                                        type="number" min="0"
                                        class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">{{ carriedForwardLabel }}</label>
                                    <input
                                        v-model.number="manifestForm.quantities.find(q => q.parcel_type_id === type.id).carried_forward"
                                        type="number" min="0"
                                        class="w-full bg-gray-700 text-white border border-gray-600 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-base h-12" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Totals Section -->
                <div class="mt-6 p-4 bg-gray-700 rounded-lg">
                    <div class="grid grid-cols-4 gap-4 text-sm font-bold">
                        <div>
                            <label>Total</label>
                        </div>
                        <div>
                            <label>Man: {{ getTotals(manifestForm).manifested }}</label>
                        </div>
                        <div>
                            <label>ReMan: {{ getTotals(manifestForm).re_manifested }}</label>
                        </div>
                        <div>
                            <label>CFWD: {{ getTotals(manifestForm).carried_forward }}</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-md transition duration-200 w-full sm:w-auto">
                    {{ isEditMode ? 'Update Manifest' : 'Create Manifest' }}
                </button>
                <button v-if="isEditMode" type="button" @click="cancelEdit"
                    class="bg-gray-600 hover:bg-gray-700 text-white font-semibold px-6 py-3 rounded-md transition duration-200 w-full sm:w-auto">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
@media (max-width: 640px) {
    label {
        font-size: 1rem;
    }

    input,
    select {
        font-size: 1rem;
        padding: 0.75rem;
        height: 3rem;
    }

    .grid-cols-4 > div {
        font-size: 0.875rem;
        padding: 0.5rem 0;
    }
}

[draggable="true"]:hover {
    background-color: #4a5568; /* Tailwind's gray-600 */
    transition: background-color 0.2s ease;
}

.cursor-move {
    cursor: move;
}

.drag-handle:hover {
    color: #a0aec0; /* Tailwind's gray-400 -> gray-300 */
    transition: color 0.2s ease;
}

.dragging {
    opacity: 0.3;
    border: 2px dashed #a0aec0; /* Tailwind's gray-300 */
}
</style>
