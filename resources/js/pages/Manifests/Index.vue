<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Manifests</h1>
    <Link :href="route('manifests.create')" class="bg-blue-500 text-white px-4 py-2 rounded">Create Manifest</Link>
    <table v-if="manifests && manifests.length" class="w-full mt-4 border">
      <thead>
        <tr class="bg-gray-100">
          <th class="p-2">Manifest Number</th>
          <th class="p-2">Delivery Date</th>
          <th class="p-2">Status</th>
          <th class="p-2">Round</th>
          <th class="p-2">Items</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="manifest in manifests" :key="manifest.id" class="border-b">
          <td class="p-2">{{ manifest.manifest_number }}</td>
          <td class="p-2">{{ manifest.delivery_date }}</td>
          <td class="p-2">{{ manifest.status }}</td>
          <td class="p-2">{{ manifest.round?.round_id ?? 'N/A' }}</td>
          <td class="p-2">{{ getItemCount(manifest) }}</td>
        </tr>
      </tbody>
    </table>
    <div v-else class="mt-4 text-gray-500">
      No manifests found.
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
  manifests?: any[];
}>();

// Log the manifests for debugging
console.log('Manifests:', props.manifests);

function getItemCount(manifest: any): number {
  return (manifest?.quantities && Array.isArray(manifest.quantities)) ? manifest.quantities.length : 0;
}
</script>
