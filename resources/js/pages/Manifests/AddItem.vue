<template>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">Add Item to Manifest: {{ manifest.manifest_number }}</h1>
      <form @submit.prevent="submit" class="max-w-lg">
        <div class="mb-4">
          <label class="block">Tracking Number</label>
          <input v-model="form.tracking_number" type="text" class="w-full border p-2" />
          <div v-if="form.errors.tracking_number" class="text-red-500">{{ form.errors.tracking_number }}</div>
        </div>
        <div class="mb-4">
          <label class="block">Recipient Name</label>
          <input v-model="form.recipient_name" type="text" class="w-full border p-2" />
          <div v-if="form.errors.recipient_name" class="text-red-500">{{ form.errors.recipient_name }}</div>
        </div>
        <div class="mb-4">
          <label class="block">Delivery Address</label>
          <input v-model="form.delivery_address" type="text" class="w-full border p-2" />
          <div v-if="form.errors.delivery_address" class="text-red-500">{{ form.errors.delivery_address }}</div>
        </div>
        <div class="mb-4">
          <label class="block">Parcel Type</label>
          <select v-model="form.parcel_type_id" class="w-full border p-2">
            <option v-for="type in parcelTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
          </select>
          <div v-if="form.errors.parcel_type_id" class="text-red-500">{{ form.errors.parcel_type_id }}</div>
        </div>
        <div class="mb-4">
          <label class="block">Status</label>
          <select v-model="form.status" class="w-full border p-2">
            <option value="pending">Pending</option>
            <option value="delivered">Delivered</option>
            <option value="failed">Failed</option>
          </select>
        </div>
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Save</button>
      </form>
    </div>
  </template>
  <script setup>
  import { useForm } from '@inertiajs/vue3';
  defineProps(['manifest', 'parcelTypes']);
  const form = useForm({
    tracking_number: '',
    recipient_name: '',
    delivery_address: '',
    parcel_type_id: '',
    status: 'pending'
  });
  function submit() {
    form.post(route('manifests.items.store', manifest.id));
  }
  </script>
