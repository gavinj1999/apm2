<template>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">Create Manifest</h1>
      <form @submit.prevent="submit" class="max-w-lg">
        <div class="mb-4">
          <label class="block">Manifest Number</label>
          <input v-model="form.manifest_number" type="text" class="w-full border p-2" />
          <div v-if="form.errors.manifest_number" class="text-red-500">{{ form.errors.manifest_number }}</div>
        </div>
        <div class="mb-4">
          <label class="block">Delivery Date</label>
          <input v-model="form.delivery_date" type="date" class="w-full border p-2" />
          <div v-if="form.errors.delivery_date" class="text-red-500">{{ form.errors.delivery_date }}</div>
        </div>
        <div class="mb-4">
          <label class="block">Status</label>
          <select v-model="form.status" class="w-full border p-2">
            <option value="pending">Pending</option>
            <option value="in-progress">In-Progress</option>
            <option value="completed">Completed</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block">Round</label>
          <select v-model="form.round_id" class="w-full border p-2">
            <option v-for="round in rounds" :key="round.id" :value="round.id">{{ round.round_id }}</option>
          </select>
          <div v-if="form.errors.round_id" class="text-red-500">{{ form.errors.round_id }}</div>
        </div>
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Save</button>
      </form>
    </div>
  </template>
  <script setup lang="ts">
  import { useForm } from '@inertiajs/vue3';
  defineProps(['rounds']);
  const form = useForm({
    manifest_number: '',
    delivery_date: '',
    status: 'pending',
    round_id: ''
  });
  function submit() {
    form.post(route('manifests.store'));
  }
  </script>
