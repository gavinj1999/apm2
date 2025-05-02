<template>
    <div class="p-6 bg-gray-900 text-gray-100 min-h-screen">
      <!-- Page Title -->
      <h1 class="text-2xl font-semibold mb-6">Parcel Types</h1>

      <!-- Flash Messages -->
      <div v-if="$page.props.flash.success" class="bg-green-600 text-white p-4 mb-6 rounded-lg shadow">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash.error" class="bg-red-600 text-white p-4 mb-6 rounded-lg shadow">
        {{ $page.props.flash.error }}
      </div>

      <!-- Validation Errors -->
      <div v-if="form.errors.name" class="text-red-400 text-sm mb-2">{{ form.errors.name }}</div>

      <!-- Add/Edit Form -->
      <div class="mb-8">
        <h2 class="text-xl font-medium mb-4">{{ editing ? 'Edit' : 'Add' }} Parcel Type</h2>
        <form @submit.prevent="saveParcelType" class="space-y-4">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
            <input
              v-model="form.name"
              type="text"
              id="name"
              class="mt-1 block w-full bg-gray-800 border-gray-700 text-gray-100 rounded-md p-2 focus:ring focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
          <div class="flex space-x-3">
            <button
              type="submit"
              class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
              :disabled="form.processing"
            >
              {{ editing ? 'Update' : 'Create' }}
            </button>
            <button
              v-if="editing"
              type="button"
              @click="cancelEdit"
              class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition-colors"
              :disabled="form.processing"
            >
              Cancel
            </button>
          </div>
        </form>
      </div>

      <!-- Parcel Types List -->
      <div>
        <h2 class="text-xl font-medium mb-4">Parcel Types List</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full bg-gray-800 rounded-lg shadow">
            <thead>
              <tr class="bg-gray-700 text-gray-300 text-sm uppercase tracking-wider">
                <th class="py-3 px-4 text-left">Name</th>
                <th class="py-3 px-4 text-left">Sort Order</th>
                <th class="py-3 px-4 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="parcelType in sortedParcelTypes"
                :key="parcelType.id"
                class="border-t border-gray-700 hover:bg-gray-700 transition-colors"
              >
                <td class="py-3 px-4 text-gray-100">{{ parcelType.name }}</td>
                <td class="py-3 px-4 text-gray-100">{{ parcelType.sort_order }}</td>
                <td class="py-3 px-4">
                  <button
                    @click="editParcelType(parcelType)"
                    class="text-blue-400 hover:text-blue-300 mr-3 transition-colors"
                  >
                    Edit
                  </button>
                  <button
                    @click="deleteParcelType(parcelType)"
                    class="text-red-400 hover:text-red-300 transition-colors"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </template>

  <script setup>
  import { computed } from 'vue';
  import { useForm, router } from '@inertiajs/vue3';

  // Define props explicitly
  const props = defineProps({
    parcelTypes: Array,
    flash: Object,
  });

  const form = useForm({
    id: null,
    name: '',
  });

  const editing = computed(() => !!form.id);

  const sortedParcelTypes = computed(() => {
    return [...props.parcelTypes].sort((a, b) => a.sort_order - b.sort_order);
  });

  const saveParcelType = () => {
    if (editing.value) {
      form.put(`/parcel-types/${form.id}`, {
        onSuccess: () => form.reset(),
      });
    } else {
      form.post('/parcel-types', {
        onSuccess: () => form.reset(),
      });
    }
  };

  const editParcelType = (parcelType) => {
    form.id = parcelType.id;
    form.name = parcelType.name;
  };

  const cancelEdit = () => {
    form.reset();
  };

  const deleteParcelType = (parcelType) => {
    if (confirm('Are you sure you want to delete this parcel type?')) {
      router.delete(`/parcel-types/${parcelType.id}`);
    }
  };
  </script>
