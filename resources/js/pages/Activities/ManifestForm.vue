<template>
  <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
    <h3 class="text-lg font-medium text-white mb-4">Create Manifest</h3>
    <form @submit.prevent="submitForm">
      <div class="mb-4">
        <label for="parcelType" class="block text-sm font-medium text-gray-300">Parcel Type</label>
        <select
          id="parcelType"
          v-model="form.parcel_type_id"
          class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
          required
        >
          <option value="">Select Parcel Type</option>
          <option v-for="type in parcelTypes" :key="type.id" :value="type.id">
            {{ type.name }}
          </option>
        </select>
        <div v-if="$page.props.errors.parcel_type_id" class="text-red-400 text-sm mt-1">{{ $page.props.errors.parcel_type_id }}</div>
      </div>
      <div class="flex justify-end">
        <button
          type="button"
          @click="$emit('close')"
          class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="form.processing"
          class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500 flex items-center text-sm"
          :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
        >
          <span v-if="form.processing" class="inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          {{ form.processing ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

defineProps({
  parcelTypes: {
    type: Array,
    default: () => [], // Default to empty array to prevent iteration errors
  },
});

const form = useForm({
  parcel_type_id: '',
});

const submitForm = () => {
  form.post('/manifests', {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      // Emit close event or handle success
    },
    onError: (errors) => {
      console.error('Form submission errors:', errors);
    },
  });
};
</script>