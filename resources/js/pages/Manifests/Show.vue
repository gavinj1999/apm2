<template>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">Manifest: {{ manifest.manifest_number }}</h1>
      <p>Delivery Date: {{ manifest.delivery_date }}</p>
      <p>Status: {{ manifest.status }}</p>
      <p>Round: {{ manifest.round.round_id }}</p>
      <Link :href="route('manifests.items.create', manifest.id)" class="bg-blue-500 text-white px-4 py-2 rounded mt-4 inline-block">Add Item</Link>
      <h2 class="text-xl font-bold mt-6">Items</h2>
      <table class="w-full mt-4 border">
        <thead>
          <tr class="bg-gray-100">
            <th class="p-2">Tracking Number</th>
            <th class="p-2">Recipient</th>
            <th class="p-2">Address</th>
            <th class="p-2">Type</th>
            <th class="p-2">Status</th>
            <th class="p-2">Fee</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in manifest.items" :key="item.id" class="border-b">
            <td class="p-2">{{ item.tracking_number }}</td>
            <td class="p-2">{{ item.recipient_name }}</td>
            <td class="p-2">{{ item.delivery_address }}</td>
            <td class="p-2">{{ item.parcel_type.name }}</td>
            <td class="p-2">{{ item.status }}</td>
            <td class="p-2">£{{ getFee(item.parcel_type_id) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </template>
  <script setup lang="ts">
  import { Link } from '@inertiajs/vue3';
  defineProps(['manifest']);
  function getFee(parcelTypeId) {
    const pricing = manifest.round.pricings.find(p => p.parcel_type_id === parcelTypeId);
    return pricing ? pricing.price : 'N/A';
  }
  </script>
