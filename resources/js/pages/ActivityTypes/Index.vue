<template>
  <Head title="Activities" />
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-white leading-tight">
        Recorded Activities
      </h2>
    </template>
    <div class="flex-1 flex flex-col">
      <div class="sm:px-2 lg:px-2 flex-1 flex flex-col">
        <div class="bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg flex-1 flex flex-col">
          <div class="p-6 bg-gray-800 border-b border-gray-700 flex-1 flex flex-col">
            <!-- Flash Message -->
            <div v-if="$page.props.flash.success" class="text-green-400 mb-4 text-sm">{{ $page.props.flash.success }}</div>
            <!-- Error Display -->
            <div v-if="$page.props.errors.global" class="text-red-400 mb-4">{{ $page.props.errors.global }}</div>
            <!-- Location and Activity Buttons -->
            <div class="mb-6 flex space-x-4">
              <button
                @click="openLocationModal"
                class="font-bold rounded-lg text-xs w-32 h-8 bg-indigo-600 text-[#ffffff] justify-center"
              >
                Manage Locations
              </button>
              <button
                @click="openCreateModal"
                class="font-bold rounded-lg text-xs w-24 h-8 bg-indigo-600 text-[#ffffff] justify-center"
              >
                Add Activity
              </button>
            </div>
            <!-- Map and Style Selector -->
            <div v-if="mapboxToken" class="mb-6 h-[768px]">
              <div class="flex items-center mb-2">
                <label for="mapStyle" class="block text-sm font-medium text-gray-300 mr-2">Map Style</label>
                <select
                  id="mapStyle"
                  v-model="selectedMapStyle"
                  class="rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-1"
                >
                  <option v-for="style in mapStyles" :key="style.id" :value="style.id">{{ style.name }}</option>
                </select>
              </div>
              <div id="map" class="w-full h-full rounded-md"></div>
            </div>
            <div v-else class="text-yellow-400 mb-4">Mapbox token is missing</div>
            <!-- Calendar -->
            <div class="mb-6 flex-1">
              <label class="block text-sm font-medium text-gray-300 mb-2">Activities Calendar</label>
              <vue-cal
                :events="calendarEvents"
                :active-date="dateFilter"
                @event-drop="handleEventDrop"
                @event-click="handleEventClick"
                :time-from="7 * 60"
                :time-to="21 * 60 + 30"
                :time-step="60"
                :style="{ height: '100%' }"
                class="bg-gray-700 rounded-md"
                default-view="week"
                :week-starts-on="1"
                :disable-views="['years', 'year', 'day']"
                editable-events
                :scroll-to="{ hour: 7 }"
              />
            </div>
            <!-- Table -->
            <table class="min-w-full divide-y divide-gray-700">
              <thead class="bg-gray-900">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Activity</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Time</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Latitude</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Longitude</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-gray-800 divide-y divide-gray-700">
                <template v-for="(group, date) in groupedActivities" :key="date">
                  <tr class="bg-gray-900">
                    <td colspan="5" class="px-6 py-2 text-sm font-sm text-gray-300">{{ formatDate(date, 'date') }}</td>
                  </tr>
                  <tr v-for="activity in group" :key="activity.id || activity.activity">
                    <td class="px-6 py-4 whitespace-nowrap text-white text-sm" :class="{ 'italic': activity.is_manual }">
                      {{ getAlias(activity.activity) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-white text-sm" :class="{ 'italic': activity.is_manual }">
                      {{ activity.datetime ? formatDate(activity.datetime, 'time') : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-white text-sm" :class="{ 'italic': activity.is_manual }">
                      {{ activity.latitude || '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-white text-sm" :class="{ 'italic': activity.is_manual }">
                      {{ activity.longitude || '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap flex items-center space-x-2">
                      <button
                        v-if="activity.id"
                        @click="openEditModal(activity)"
                        class="text-indigo-400 hover:text-indigo-300 text-sm"
                      >
                        Edit
                      </button>
                      <button
                        v-if="activity.id"
                        @click="deleteActivity(activity.id)"
                        class="text-red-400 hover:text-red-300 text-sm"
                      >
                        Delete
                      </button>
                      <button
                        v-if="activity.id && hasDuplicates(activity)"
                        @click="markAsCorrect(activity)"
                        class="text-blue-400 hover:text-blue-300 text-sm"
                      >
                        Mark as Correct
                      </button>
                      <button
                        v-if="!activity.id"
                        @click="openManualModal(activity.activity, date)"
                        class="text-green-400 hover:text-green-300 text-sm"
                      >
                        Add
                      </button>
                      <div v-if="activity.id" class="relative inline-flex items-center">
                        <select
                          v-model="selectedLocation[activity.id]"
                          @change="updateActivityLocation(activity.id, $event.target.value)"
                          :disabled="isUpdatingActivityLocation[activity.id]"
                          class="rounded-md bg-gray-700 border-gray-600 text-white text-sm focus:border-indigo-500 focus:ring-indigo-500 h-6 pr-6"
                          :class="{ 'opacity-50 cursor-not-allowed': isUpdatingActivityLocation[activity.id] }"
                        >
                          <option value="">Select Location</option>
                          <option v-for="location in locations" :key="location.id" :value="location.id">
                            {{ location.name }}
                          </option>
                        </select>
                        <span
                          v-if="isUpdatingActivityLocation[activity.id]"
                          class="absolute right-2 w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"
                        ></span>
                      </div>
                    </td>
                  </tr>
                </template>
                <tr v-if="!Object.keys(groupedActivities).length">
                  <td colspan="5" class="px-6 py-4 text-center text-gray-300 text-sm">No activities found</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <!-- Location Management Modal -->
    <div v-if="showLocationModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-4xl">
        <h3 class="text-lg font-medium text-white mb-4">Manage Preset Locations</h3>
        <!-- Add Location Form -->
        <div class="mb-6">
          <h4 class="text-md font-semibold text-white mb-2">Add New Location</h4>
          <div class="flex space-x-4 mb-4">
            <input
              v-model="newLocation.name"
              placeholder="Location Name (e.g., Home, Depot)"
              class="border rounded p-2 flex-1 bg-gray-700 text-white"
            />
            <input
              v-model="newLocation.latitude"
              type="number"
              step="any"
              placeholder="Latitude"
              class="border rounded p-2 w-32 bg-gray-700 text-white"
            />
            <input
              v-model="newLocation.longitude"
              type="number"
              step="any"
              placeholder="Longitude"
              class="border rounded p-2 w-32 bg-gray-700 text-white"
            />
            <button
              @click="addLocation"
              :disabled="isAddingLocation"
              class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-500 flex items-center"
              :class="{ 'opacity-50 cursor-not-allowed': isAddingLocation }"
            >
              <span v-if="isAddingLocation" class="inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              {{ isAddingLocation ? 'Adding...' : 'Add' }}
            </button>
          </div>
          <div v-if="locationError" class="text-red-400 text-sm mb-2">{{ locationError }}</div>
          <!-- Map Search with Suggestions -->
          <div class="mb-4 relative">
            <label for="locationMapSearch" class="block text-sm font-medium text-gray-300">Search Location</label>
            <input
              type="text"
              id="locationMapSearch"
              v-model="locationSearchQuery"
              @input="debounceSearch"
              placeholder="Enter city or address"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            />
            <div v-if="searchError" class="text-red-400 text-sm mt-1">{{ searchError }}</div>
            <ul
              v-if="addressSuggestions.length"
              class="absolute z-10 w-full bg-gray-700 border border-gray-600 rounded-md mt-1 max-h-48 overflow-y-auto"
            >
              <li
                v-for="suggestion in addressSuggestions"
                :key="suggestion.id"
                @click="selectAddress(suggestion)"
                class="px-4 py-2 text-white text-sm hover:bg-gray-600 cursor-pointer"
              >
                {{ suggestion.place_name }}
              </li>
            </ul>
          </div>
          <!-- Map -->
          <div v-if="mapboxToken" class="mb-4 h-64">
            <div id="locationMap" class="w-full h-full rounded-md"></div>
          </div>
          <div v-else class="text-yellow-400 mb-4">Mapbox token is missing</div>
        </div>
        <!-- Locations Table -->
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-gray-900">
              <th class="border p-2 text-left text-xs font-medium text-gray-300 uppercase">Name</th>
              <th class="border p-2 text-left text-xs font-medium text-gray-300 uppercase">Latitude</th>
              <th class="border p-2 text-left text-xs font-medium text-gray-300 uppercase">Longitude</th>
              <th class="border p-2 text-left text-xs font-medium text-gray-300 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="location in locations" :key="location.id" class="hover:bg-gray-700">
              <td class="border p-2">
                <input
                  v-if="editingLocationId === location.id"
                  v-model="editLocationForm.name"
                  class="border rounded p-1 w-full bg-gray-700 text-white"
                />
                <span v-else class="text-white text-sm">{{ location.name }}</span>
              </td>
              <td class="border p-2">
                <input
                  v-if="editingLocationId === location.id"
                  v-model="editLocationForm.latitude"
                  type="number"
                  step="any"
                  class="border rounded p-1 w-full bg-gray-700 text-white"
                />
                <span v-else class="text-white text-sm">{{ location.latitude }}</span>
              </td>
              <td class="border p-2">
                <input
                  v-if="editingLocationId === location.id"
                  v-model="editLocationForm.longitude"
                  type="number"
                  step="any"
                  class="border rounded p-1 w-full bg-gray-700 text-white"
                />
                <span v-else class="text-white text-sm">{{ location.longitude }}</span>
              </td>
              <td class="border p-2">
                <button
                  v-if="editingLocationId === location.id"
                  @click="updateLocation"
                  :disabled="isUpdatingLocation"
                  class="bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-500 flex items-center text-sm"
                  :class="{ 'opacity-50 cursor-not-allowed': isUpdatingLocation }"
                >
                  <span v-if="isUpdatingLocation" class="inline-block w-3 h-3 mr-1 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  {{ isUpdatingLocation ? 'Saving...' : 'Save' }}
                </button>
                <button
                  v-if="editingLocationId === location.id"
                  @click="cancelEditLocation"
                  class="bg-gray-600 text-white px-2 py-1 rounded hover:bg-gray-500 ml-2 text-sm"
                >
                  Cancel
                </button>
                <button
                  v-else
                  @click="startEditLocation(location)"
                  class="bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-500 text-sm"
                >
                  Edit
                </button>
                <button
                  @click="deleteLocation(location)"
                  :disabled="isDeletingLocation"
                  class="bg-red-600 text-white px-2 py-1 rounded hover:bg-red-500 ml-2 flex items-center text-sm"
                  :class="{ 'opacity-50 cursor-not-allowed': isDeletingLocation }"
                >
                  <span v-if="isDeletingLocation" class="inline-block w-3 h-3 mr-1 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  {{ isDeletingLocation ? 'Deleting...' : 'Delete' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="flex justify-end mt-4">
          <button
            @click="showLocationModal = false"
            class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
          >
            Close
          </button>
        </div>
      </div>
    </div>
    <!-- Edit Modal -->
    <div v-if="showEditModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-medium text-white mb-4">Edit Activity</h3>
        <form @submit.prevent="editFormSubmit">
          <div class="mb-4">
            <label for="editDatetime" class="block text-sm font-medium text-gray-300">Date & Time</label>
            <input
              type="datetime-local"
              id="editDatetime"
              v-model="editForm.datetime"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.datetime" class="text-red-400 text-sm mt-1">{{ $page.props.errors.datetime }}</div>
          </div>
          <div class="mb-4" v-if="!isFixedLocationActivity(editForm.activity)">
            <label for="editLatitude" class="block text-sm font-medium text-gray-300">Latitude</label>
            <input
              type="number"
              id="editLatitude"
              v-model="editForm.latitude"
              step="any"
              min="-90"
              max="90"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.latitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.latitude }}</div>
          </div>
          <div class="mb-4" v-if="!isFixedLocationActivity(editForm.activity)">
            <label for="editLongitude" class="block text-sm font-medium text-gray-300">Longitude</label>
            <input
              type="number"
              id="editLongitude"
              v-model="editForm.longitude"
              step="any"
              min="-180"
              max="180"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.longitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.longitude }}</div>
          </div>
          <div class="mb-4" v-if="isFixedLocationActivity(editForm.activity)">
            <p class="text-gray-300 text-sm">Location is fixed for {{ getAlias(editForm.activity) }} ({{ getFixedLocationName(editForm.activity) }})</p>
          </div>
          <div class="mb-4">
            <label for="editActivity" class="block text-sm font-medium text-gray-300">Activity</label>
            <select
              id="editActivity"
              v-model="editForm.activity"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            >
              <option v-for="type in activityTypes" :key="type.name" :value="type.name">{{ type.name }}</option>
            </select>
            <div v-if="$page.props.errors.activity" class="text-red-400 text-sm mt-1">{{ $page.props.errors.activity }}</div>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              @click="showEditModal = false"
              class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="isEditingActivity"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500 flex items-center text-sm"
              :class="{ 'opacity-50 cursor-not-allowed': isEditingActivity }"
            >
              <span v-if="isEditingActivity" class="inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              {{ isEditingActivity ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Create Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-3xl">
        <h3 class="text-lg font-sm text-white mb-4">Add New Activity</h3>
        <form @submit.prevent="createFormSubmit">
          <div class="mb-4">
            <label for="createDatetime" class="block text-sm font-medium text-gray-300">Date & Time</label>
            <input
              type="datetime-local"
              id="createDatetime"
              v-model="createForm.datetime"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.datetime" class="text-red-400 text-sm mt-1">{{ $page.props.errors.datetime }}</div>
          </div>
          <!-- Map Search -->
          <div class="mb-4" v-if="!isFixedLocationActivity(createForm.activity)">
            <label for="mapSearch" class="block text-sm font-medium text-gray-300">Search Location</label>
            <input
              type="text"
              id="mapSearch"
              v-model="searchQuery"
              placeholder="Enter city or address"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            />
          </div>
          <!-- Map -->
          <div v-if="mapboxToken && !isFixedLocationActivity(createForm.activity)" class="mb-4 h-96">
            <div id="createMap" class="w-full h-full rounded-md"></div>
          </div>
          <div v-else-if="!isFixedLocationActivity(createForm.activity)" class="text-yellow-400 mb-4">Mapbox token is missing</div>
          <div class="mb-4" v-if="!isFixedLocationActivity(createForm.activity)">
            <label for="createLatitude" class="block text-sm font-medium text-gray-300">Latitude</label>
            <input
              type="number"
              id="createLatitude"
              v-model="createForm.latitude"
              step="any"
              min="-90"
              max="90"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.latitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.latitude }}</div>
          </div>
          <div class="mb-4" v-if="!isFixedLocationActivity(createForm.activity)">
            <label for="createLongitude" class="block text-sm font-medium text-gray-300">Longitude</label>
            <input
              type="number"
              id="createLongitude"
              v-model="createForm.longitude"
              step="any"
              min="-180"
              max="180"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.longitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.longitude }}</div>
          </div>
          <div class="mb-4" v-if="isFixedLocationActivity(createForm.activity)">
            <p class="text-gray-300 text-sm">Location will be set to {{ getFixedLocationName(createForm.activity) }}</p>
          </div>
          <div class="mb-4">
            <label for="createActivity" class="block text-sm font-medium text-gray-300">Activity</label>
            <select
              id="createActivity"
              v-model="createForm.activity"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            >
              <option v-for="type in activityTypes" :key="type.name" :value="type.name">{{ type.name }}</option>
            </select>
            <div v-if="$page.props.errors.activity" class="text-red-400 text-sm mt-1">{{ $page.props.errors.activity }}</div>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              @click="showCreateModal = false"
              class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="isCreatingActivity"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500 flex items-center text-sm"
              :class="{ 'opacity-50 cursor-not-allowed': isCreatingActivity }"
            >
              <span v-if="isCreatingActivity" class="inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              {{ isCreatingActivity ? 'Creating...' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Manual Entry Modal -->
    <div v-if="showManualModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-3xl">
        <h3 class="text-lg font-medium text-white mb-4">Add {{ getAlias(manualForm.activity) }} for {{ formatDate(manualDate, 'date') }}</h3>
        <form @submit.prevent="manualFormSubmit">
          <div class="mb-4">
            <label for="manualDatetime" class="block text-sm font-medium text-gray-300">Date & Time</label>
            <input
              type="datetime-local"
              id="manualDatetime"
              v-model="manualForm.datetime"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.datetime" class="text-red-400 text-sm mt-1">{{ $page.props.errors.datetime }}</div>
          </div>
          <!-- Map Search -->
          <div class="mb-4" v-if="!isFixedLocationActivity(manualForm.activity)">
            <label for="manualMapSearch" class="block text-sm font-medium text-gray-300">Search Location</label>
            <input
              type="text"
              id="manualMapSearch"
              v-model="manualSearchQuery"
              placeholder="Enter city or address"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            />
          </div>
          <!-- Map -->
          <div v-if="mapboxToken && !isFixedLocationActivity(manualForm.activity)" class="mb-4 h-96">
            <div id="manualMap" class="w-full h-full rounded-md"></div>
          </div>
          <div v-else-if="!isFixedLocationActivity(manualForm.activity)" class="text-yellow-400 mb-4">Mapbox token is missing</div>
          <div class="mb-4" v-if="!isFixedLocationActivity(manualForm.activity)">
            <label for="manualLatitude" class="block text-sm font-medium text-gray-300">Latitude</label>
            <input
              type="number"
              id="manualLatitude"
              v-model="manualForm.latitude"
              step="any"
              min="-90"
              max="90"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.latitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.latitude }}</div>
          </div>
          <div class="mb-4" v-if="!isFixedLocationActivity(manualForm.activity)">
            <label for="manualLongitude" class="block text-sm font-medium text-gray-300">Longitude</label>
            <input
              type="number"
              id="manualLongitude"
              v-model="manualForm.longitude"
              step="any"
              min="-180"
              max="180"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.longitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.longitude }}</div>
          </div>
          <div class="mb-4" v-if="isFixedLocationActivity(manualForm.activity)">
            <p class="text-gray-300 text-sm">Location will be set to {{ getFixedLocationName(manualForm.activity) }}</p>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              @click="showManualModal = false"
              class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="isSavingManual"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500 flex items-center text-sm"
              :class="{ 'opacity-50 cursor-not-allowed': isSavingManual }"
            >
              <span v-if="isSavingManual" class="inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              {{ isSavingManual ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import mapboxgl from 'mapbox-gl';
import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder';
import VueCal from 'vue-cal';
import 'vue-cal/dist/vuecal.css';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
  activities: {
    type: Array,
    default: () => [],
  },
  activityDates: {
    type: Array,
    default: () => [],
  },
  activityTypes: {
    type: Array,
    default: () => [],
  },
  locations: {
    type: Array,
    default: () => [],
  },
  mapboxToken: {
    type: String,
    required: false,
  },
  errors: Object,
  flash: Object,
});

const dateFilter = ref(new Date());
const localActivities = ref(props.activities);
const showEditModal = ref(false);
const showCreateModal = ref(false);
const showManualModal = ref(false);
const showLocationModal = ref(false);
const searchQuery = ref('');
const manualSearchQuery = ref('');
const locationSearchQuery = ref('');
const addressSuggestions = ref([]);
const searchError = ref('');
const locationError = ref('');
const isCreatingActivity = ref(false);
const isEditingActivity = ref(false);
const isSavingManual = ref(false);
const isAddingLocation = ref(false);
const isUpdatingLocation = ref(false);
const isDeletingLocation = ref(false);
const isUpdatingActivityLocation = ref({});
const selectedLocation = ref({});
const highlightedEvent = ref(null);
const highlightedMarker = ref(null);
const markers = ref({});
const manualDate = ref('');
let map = null;
let createMap = null;
let createMarker = null;
let manualMap = null;
let manualMarker = null;
let locationMap = null;
let locationMarker = null;

const createForm = useForm({
  datetime: '',
  latitude: '',
  longitude: '',
  activity: '',
});

const editForm = useForm({
  id: null,
  datetime: '',
  latitude: '',
  longitude: '',
  activity: '',
  is_manual: false,
});

const manualForm = useForm({
  datetime: '',
  latitude: '',
  longitude: '',
  activity: '',
  is_manual: true,
});

const newLocation = ref({
  name: '',
  latitude: '',
  longitude: '',
});

const editingLocationId = ref(null);
const editLocationForm = ref({
  name: '',
  latitude: '',
  longitude: '',
});

// Find matching location for an activity's coordinates
const findMatchingLocation = (latitude, longitude) => {
  if (!latitude || !longitude) return '';
  const tolerance = 0.000001; // Handle floating-point precision
  return props.locations.find(loc => 
    Math.abs(parseFloat(loc.latitude) - parseFloat(latitude)) < tolerance &&
    Math.abs(parseFloat(loc.longitude) - parseFloat(longitude)) < tolerance
  )?.id || '';
};

// Initialize selectedLocation for all activities
const initializeSelectedLocations = () => {
  props.activities.forEach(activity => {
    if (activity.id) {
      selectedLocation.value[activity.id] = findMatchingLocation(activity.latitude, activity.longitude);
    }
  });
};

// Define getAlias before calendarEvents
const getAlias = (activityName) => {
  const type = props.activityTypes.find((t) => t.name === activityName);
  return type ? type.alias : activityName;
};

const getColor = (activityName) => {
  const type = props.activityTypes.find((t) => t.name === activityName);
  return type ? type.color : '#4f46e5';
};

const isFixedLocationActivity = (activityName) => {
  return ['Left Home', 'Arrive Depot', 'Start Loading', 'Leave Depot', 'Arrive Home'].includes(activityName);
};

const getFixedLocationName = (activityName) => {
  if (['Left Home', 'Arrive Home'].includes(activityName)) return 'Home';
  if (['Arrive Depot', 'Start Loading', 'Leave Depot'].includes(activityName)) return 'Depot';
  return '';
};

// Map styles
const mapStyles = [
  { id: 'mapbox://styles/mapbox/dark-v11', name: 'Dark' },
  { id: 'mapbox://styles/mapbox/light-v11', name: 'Light' },
  { id: 'mapbox://styles/mapbox/streets-v12', name: 'Streets' },
  { id: 'mapbox://styles/mapbox/satellite-streets-v12', name: 'Satellite' },
];
const selectedMapStyle = ref(mapStyles[0].id);

const calendarEvents = ref(props.activities.map((activity) => ({
  id: activity.id,
  start: new Date(activity.datetime),
  end: new Date(new Date(activity.datetime).getTime() + 30 * 60000),
  title: getAlias(activity.activity),
  class: `activity-event activity-${activity.activity.replace(/\s+/g, '-')}`,
})));

const groupedActivities = computed(() => {
  const groups = {};
  const activities = [...props.activities].sort((a, b) => {
    const dateA = new Date(a.datetime).toISOString().split('T')[0];
    const dateB = new Date(b.datetime).toISOString().split('T')[0];
    if (dateA !== dateB) return dateA.localeCompare(dateB);
    const orderA = props.activityTypes.findIndex((t) => t.name === a.activity);
    const orderB = props.activityTypes.findIndex((t) => t.name === b.activity);
    return orderA - orderB;
  });

  activities.forEach((activity) => {
    const date = new Date(activity.datetime).toISOString().split('T')[0];
    if (!groups[date]) groups[date] = [];
    groups[date].push(activity);
  });

  Object.keys(groups).forEach((date) => {
    const existingActivities = groups[date].map((act) => act.activity);
    props.activityTypes.forEach((type) => {
      if (!existingActivities.includes(type.name)) {
        groups[date].push({
          activity: type.name,
          date,
          is_manual: false,
        });
      }
    });
    groups[date].sort((a, b) => {
      const orderA = props.activityTypes.findIndex((t) => t.name === a.activity);
      const orderB = props.activityTypes.findIndex((t) => t.name === b.activity);
      return orderA - orderB;
    });
  });

  return groups;
});

const hasDuplicates = (activity) => {
  if (!activity.id) return false;
  return props.activities.some((other) => {
    if (other.id === activity.id) return false;
    const activityDate = new Date(activity.datetime).toISOString().split('T')[0];
    const otherDate = new Date(other.datetime).toISOString().split('T')[0];
    return other.activity === activity.activity && otherDate === activityDate;
  });
};

const markAsCorrect = (activity) => {
  if (!confirm('Mark this as the correct activity and delete duplicates?')) return;
  router.post(`/activity/${activity.id}/mark-as-correct`, { id: activity.id }, {
    onSuccess: () => {
      localActivities.value = props.activities;
      initializeSelectedLocations();
      updateCalendarEvents();
    },
    onError: (errors) => {
      props.errors.global = errors.message || 'Failed to mark activity as correct';
    },
  });
};

const formatDate = (dateString, type) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  if (type === 'date') {
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
  } else if (type === 'time') {
    return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });
  }
  return date.toLocaleString();
};

const createFormSubmit = () => {
  isCreatingActivity.value = true;
  locationError.value = '';
  console.log('Creating activity:', createForm.data());
  createForm.post('/activities', {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      showCreateModal.value = false;
      createForm.reset();
      searchQuery.value = '';
      router.reload({
        only: ['activities', 'activityDates'],
        preserveState: true,
        onSuccess: () => {
          localActivities.value = props.activities;
          initializeSelectedLocations();
          updateCalendarEvents();
          updateMapMarkers();
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ');
      console.error('Create activity errors:', errors);
    },
    onFinish: () => {
      isCreatingActivity.value = false;
    },
  });
};

const editFormSubmit = () => {
  isEditingActivity.value = true;
  props.errors.global = '';
  console.log('Editing activity:', editForm.data());
  editForm.put(`/activity/${editForm.id}`, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      showEditModal.value = false;
      router.reload({
        only: ['activities'],
        preserveState: true,
        onSuccess: () => {
          localActivities.value = props.activities;
          initializeSelectedLocations();
          updateCalendarEvents();
          updateMapMarkers();
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ') || 'Failed to update activity';
      console.error('Edit activity errors:', errors);
    },
    onFinish: () => {
      isEditingActivity.value = false;
    },
  });
};

const manualFormSubmit = () => {
  isSavingManual.value = true;
  locationError.value = '';
  console.log('Saving manual activity:', manualForm.data());
  manualForm.post('/activities', {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      showManualModal.value = false;
      manualForm.reset();
      manualSearchQuery.value = '';
      router.reload({
        only: ['activities', 'activityDates'],
        preserveState: true,
        onSuccess: () => {
          localActivities.value = props.activities;
          initializeSelectedLocations();
          updateCalendarEvents();
          updateMapMarkers();
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ');
      console.error('Save manual activity errors:', errors);
    },
    onFinish: () => {
      isSavingManual.value = false;
    },
  });
};

const updateActivityLocation = (activityId, locationId) => {
  if (!locationId) {
    selectedLocation.value[activityId] = '';
    return;
  }
  isUpdatingActivityLocation.value[activityId] = true;
  props.errors.global = '';
  const location = props.locations.find(loc => loc.id === parseInt(locationId));
  if (!location) {
    props.errors.global = 'Selected location not found';
    isUpdatingActivityLocation.value[activityId] = false;
    selectedLocation.value[activityId] = '';
    return;
  }
  console.log(`Updating activity ${activityId} to location ${location.name}:`, { latitude: location.latitude, longitude: location.longitude });
  router.patch(`/activity/${activityId}`, {
    latitude: location.latitude,
    longitude: location.longitude,
  }, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ['activities'],
        preserveState: true,
        onSuccess: () => {
          localActivities.value = props.activities;
          initializeSelectedLocations();
          updateCalendarEvents();
          updateMapMarkers();
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ') || 'Failed to update activity location';
      console.error('Update activity location errors:', errors);
      selectedLocation.value[activityId] = findMatchingLocation(
        props.activities.find(act => act.id === activityId)?.latitude,
        props.activities.find(act => act.id === activityId)?.longitude
      );
    },
    onFinish: () => {
      isUpdatingActivityLocation.value[activityId] = false;
    },
  });
};

const openLocationModal = () => {
  showLocationModal.value = true;
  newLocation.value = { name: '', latitude: '', longitude: '' };
  locationSearchQuery.value = '';
  addressSuggestions.value = [];
  searchError.value = '';
  locationError.value = '';
  setTimeout(() => initLocationMap(), 0);
};

const addLocation = () => {
  isAddingLocation.value = true;
  locationError.value = '';
  console.log('Adding location:', newLocation.value);
  router.post('/locations', newLocation.value, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      newLocation.value = { name: '', latitude: '', longitude: '' };
      locationSearchQuery.value = '';
      addressSuggestions.value = [];
      searchError.value = '';
      router.reload({
        only: ['locations'],
        preserveState: true,
        onSuccess: () => {
          initializeSelectedLocations();
        },
      });
    },
    onError: (errors) => {
      locationError.value = Object.values(errors).join(', ') || 'Failed to add location';
      console.error('Add location errors:', errors);
    },
    onFinish: () => {
      isAddingLocation.value = false;
    },
  });
};

const startEditLocation = (location) => {
  editingLocationId.value = location.id;
  editLocationForm.value = {
    name: location.name,
    latitude: location.latitude,
    longitude: location.longitude,
  };
  locationSearchQuery.value = '';
  addressSuggestions.value = [];
  searchError.value = '';
  locationError.value = '';
  setTimeout(() => initLocationMap(), 0);
  if (location.latitude && location.longitude) {
    updateLocationMarker(location.longitude, location.latitude);
  }
};

const updateLocation = () => {
  isUpdatingLocation.value = true;
  locationError.value = '';
  console.log('Updating location:', editLocationForm.value);
  router.put(`/locations/${editingLocationId.value}`, editLocationForm.value, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      editingLocationId.value = null;
      editLocationForm.value = { name: '', latitude: '', longitude: '' };
      locationSearchQuery.value = '';
      addressSuggestions.value = [];
      searchError.value = '';
      router.reload({
        only: ['locations'],
        preserveState: true,
        onSuccess: () => {
          initializeSelectedLocations();
        },
      });
    },
    onError: (errors) => {
      locationError.value = Object.values(errors).join(', ') || 'Failed to update location';
      console.error('Update location errors:', errors);
    },
    onFinish: () => {
      isUpdatingLocation.value = false;
    },
  });
};

const cancelEditLocation = () => {
  editingLocationId.value = null;
  editLocationForm.value = { name: '', latitude: '', longitude: '' };
  locationSearchQuery.value = '';
  addressSuggestions.value = [];
  searchError.value = '';
  locationError.value = '';
};

const deleteLocation = (location) => {
  if (!confirm(`Delete location ${location.name}?`)) return;
  isDeletingLocation.value = true;
  locationError.value = '';
  console.log('Deleting location:', location.id);
  router.delete(`/locations/${location.id}`, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ['locations'],
        preserveState: true,
        onSuccess: () => {
          initializeSelectedLocations();
        },
      });
    },
    onError: (errors) => {
      locationError.value = Object.values(errors).join(', ') || 'Failed to delete location';
      console.error('Delete location errors:', errors);
    },
    onFinish: () => {
      isDeletingLocation.value = false;
    },
  });
};

const searchAddresses = async () => {
  if (!props.mapboxToken) {
    searchError.value = 'Mapbox token is missing';
    addressSuggestions.value = [];
    return;
  }

  if (!locationSearchQuery.value.trim()) {
    addressSuggestions.value = [];
    searchError.value = '';
    return;
  }

  try {
    console.log('Searching for:', locationSearchQuery.value);
    const response = await axios.get('https://api.mapbox.com/geocoding/v5/mapbox.places/' + encodeURIComponent(locationSearchQuery.value) + '.json', {
      params: {
        access_token: props.mapboxToken,
        proximity: '-2.490,53.074',
        country: 'GB',
        autocomplete: true,
        limit: 5,
      },
    });
    console.log('API response:', response.data);
    addressSuggestions.value = response.data.features;
    searchError.value = '';
  } catch (error) {
    console.error('Error fetching address suggestions:', error);
    if (error.response) {
      searchError.value = `Search failed: ${error.response.status} ${error.response.statusText}`;
    } else {
      searchError.value = 'Search failed: Unable to connect to Mapbox API';
    }
    addressSuggestions.value = [];
  }
};

const debounceSearch = debounce(searchAddresses, 300);

const selectAddress = (suggestion) => {
  const [lng, lat] = suggestion.center;
  if (editingLocationId.value) {
    editLocationForm.value.latitude = lat;
    editLocationForm.value.longitude = lng;
  } else {
    newLocation.value.latitude = lat;
    newLocation.value.longitude = lng;
  }
  updateLocationMarker(lng, lat);
  addressSuggestions.value = [];
  locationSearchQuery.value = suggestion.place_name;
  searchError.value = '';
};

const initLocationMap = () => {
  if (!props.mapboxToken) {
    props.errors.global = 'Mapbox token is missing';
    searchError.value = 'Mapbox token is missing';
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    locationMap = new mapboxgl.Map({
      container: 'locationMap',
      style: selectedMapStyle.value,
      center: [-2.490, 53.074],
      zoom: 10,
    });

    locationMap.on('load', () => {
      locationMap.on('click', (e) => {
        if (editingLocationId.value) {
          editLocationForm.value.latitude = e.lngLat.lat;
          editLocationForm.value.longitude = e.lngLat.lng;
        } else {
          newLocation.value.latitude = e.lngLat.lat;
          newLocation.value.longitude = e.lngLat.lng;
        }
        updateLocationMarker(e.lngLat.lng, e.lngLat.lat);
        addressSuggestions.value = [];
        searchError.value = '';
      });
    });
  } catch (err) {
    props.errors.global = `Error initializing location map: ${err.message}`;
    searchError.value = `Map initialization failed: ${err.message}`;
    console.error(err);
  }
};

const updateLocationMarker = (lng, lat) => {
  if (locationMarker) locationMarker.remove();
  const el = document.createElement('div');
  el.className = 'marker';
  el.style.backgroundColor = '#4f46e5';
  el.style.width = '12px';
  el.style.height = '12px';
  el.style.borderRadius = '50%';
  el.style.border = '2px solid white';

  locationMarker = new mapboxgl.Marker(el).setLngLat([lng, lat]).addTo(locationMap);
  locationMap.flyTo({ center: [lng, lat], zoom: 14 });
};

const handleEventDrop = ({ event }) => {
  const activity = localActivities.value.find((act) => act.id === event.id);
  if (!activity) return;

  const newDatetime = event.start.toISOString();
  router.patch(`/activity/${activity.id}`, { datetime: newDatetime }, {
    onSuccess: () => {
      localActivities.value = props.activities;
      initializeSelectedLocations();
      updateCalendarEvents();
    },
    onError: (errors) => {
      props.errors.global = errors.datetime || 'Failed to update activity time';
    },
  });
};

const handleEventClick = ({ event }) => {
  clearHighlights();
  highlightedEvent.value = event.id;
  const marker = markers.value[event.id];
  if (marker) {
    highlightedMarker.value = event.id;
    const el = marker.getElement();
    el.style.backgroundColor = '#ff4500';
    el.style.width = '16px';
    el.style.height = '16px';
    map.flyTo({ center: marker.getLngLat(), zoom: 12 });
  }
};

const clearHighlights = () => {
  if (highlightedEvent.value) {
    highlightedEvent.value = null;
  }
  if (highlightedMarker.value) {
    const marker = markers.value[highlightedMarker.value];
    if (marker) {
      const el = marker.getElement();
      const activity = localActivities.value.find((act) => act.id === highlightedMarker.value);
      el.style.backgroundColor = activity ? getColor(activity.activity) : '#4f46e5';
      el.style.width = '12px';
      el.style.height = '12px';
    }
    highlightedMarker.value = null;
  }
};

const updateCalendarEvents = () => {
  calendarEvents.value = props.activities.map((activity) => ({
    id: activity.id,
    start: new Date(activity.datetime),
    end: new Date(new Date(activity.datetime).getTime() + 30 * 60000),
    title: getAlias(activity.activity),
    class: `activity-event activity-${activity.activity.replace(/\s+/g, '-')}`,
  }));
};

const openEditModal = (activity) => {
  editForm.setData({
    id: activity.id,
    datetime: new Date(activity.datetime).toISOString().slice(0, 16),
    latitude: activity.latitude,
    longitude: activity.longitude,
    activity: activity.activity,
    is_manual: activity.is_manual,
  });
  showEditModal.value = true;
};

const openCreateModal = () => {
  createForm.reset();
  createForm.datetime = new Date().toISOString().slice(0, 16);
  searchQuery.value = '';
  showCreateModal.value = true;
};

const openManualModal = (activity, date) => {
  manualForm.reset();
  manualForm.activity = activity;
  manualForm.datetime = `${date}T07:00`;
  manualForm.is_manual = true;
  manualSearchQuery.value = '';
  manualDate.value = date;
  showManualModal.value = true;
};

const deleteActivity = (id) => {
  if (!confirm('Are you sure you want to delete this activity?')) return;
  router.delete(`/activity/${id}`, {
    onSuccess: () => {
      localActivities.value = props.activities;
      initializeSelectedLocations();
      router.get('/activities', {}, { preserveState: true });
    },
  });
};

const initMap = () => {
  if (!props.mapboxToken) {
    props.errors.global = 'Mapbox token is missing';
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    map = new mapboxgl.Map({
      container: 'map',
      style: selectedMapStyle.value,
      center: [-2.490, 53.074],
      zoom: 10,
    });

    map.on('load', () => {
      updateMapMarkers();
    });
  } catch (err) {
    props.errors.global = `Error initializing map: ${err.message}`;
    console.error(err);
  }
};

const updateMapMarkers = () => {
  if (!map) return;

  Object.values(markers.value).forEach((marker) => marker.remove());
  markers.value = {};

  if (localActivities.value.length > 0) {
    const bounds = new mapboxgl.LngLatBounds();
    localActivities.value.forEach((activity) => {
      const el = document.createElement('div');
      el.className = 'marker';
      el.style.backgroundColor = getColor(activity.activity);
      el.style.width = '12px';
      el.style.height = '12px';
      el.style.borderRadius = '50%';
      el.style.border = '2px solid white';

      const popup = new mapboxgl.Popup({ offset: 25 }).setHTML(`
        <div class="text-gray-800">
          <strong>${getAlias(activity.activity)}</strong><br>
          Time: ${formatDate(activity.datetime, 'time')}<br>
          Lat: ${activity.latitude}<br>
          Lng: ${activity.longitude}
        </div>
      `);

      const marker = new mapboxgl.Marker({ element: el, draggable: true })
        .setLngLat([activity.longitude, activity.latitude])
        .setPopup(popup)
        .addTo(map);

      marker.on('dragend', () => {
        const lngLat = marker.getLngLat();
        router.patch(
          `/activity/${activity.id}`,
          {
            latitude: lngLat.lat,
            longitude: lngLat.lng,
          },
          {
            onSuccess: () => {
              localActivities.value = props.activities;
              initializeSelectedLocations();
              updateCalendarEvents();
            },
            onError: (errors) => {
              props.errors.global = errors.latitude || errors.longitude || 'Failed to update activity location';
            },
          }
        );
      });

      el.addEventListener('click', () => {
        clearHighlights();
        highlightedMarker.value = activity.id;
        el.style.backgroundColor = '#ff4500';
        el.style.width = '16px';
        el.style.height = '16px';
        highlightedEvent.value = activity.id;
      });

      markers.value[activity.id] = marker;
      bounds.extend([activity.longitude, activity.latitude]);
    });

    map.fitBounds(bounds, { padding: 50 });
  }
};

const initCreateMap = () => {
  if (!props.mapboxToken) {
    props.errors.global = 'Mapbox token is missing';
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    createMap = new mapboxgl.Map({
      container: 'createMap',
      style: selectedMapStyle.value,
      center: [-2.490, 53.074],
      zoom: 10,
    });

    createMap.on('load', () => {
      const geocoder = new MapboxGeocoder({
        accessToken: props.mapboxToken,
        mapboxgl: mapboxgl,
      });

      createMap.addControl(geocoder);

      geocoder.on('result', (e) => {
        const [lng, lat] = e.result.center;
        createForm.latitude = lat;
        createForm.longitude = lng;
        updateCreateMarker(lng, lat);
      });

      createMap.on('click', (e) => {
        createForm.latitude = e.lngLat.lat;
        createForm.longitude = e.lngLat.lng;
        updateCreateMarker(e.lngLat.lng, e.lngLat.lat);
      });
    });
  } catch (err) {
    props.errors.global = `Error initializing create map: ${err.message}`;
    console.error(err);
  }
};

const initManualMap = () => {
  if (!props.mapboxToken) {
    props.errors.global = 'Mapbox token is missing';
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    manualMap = new mapboxgl.Map({
      container: 'manualMap',
      style: selectedMapStyle.value,
      center: [-2.490, 53.074],
      zoom: 10,
    });

    manualMap.on('load', () => {
      const geocoder = new MapboxGeocoder({
        accessToken: props.mapboxToken,
        mapboxgl: mapboxgl,
      });

      manualMap.addControl(geocoder);

      geocoder.on('result', (e) => {
        const [lng, lat] = e.result.center;
        manualForm.latitude = lat;
        manualForm.longitude = lng;
        updateManualMarker(lng, lat);
      });

      manualMap.on('click', (e) => {
        manualForm.latitude = e.lngLat.lat;
        manualForm.longitude = e.lngLat.lng;
        updateManualMarker(e.lngLat.lng, e.lngLat.lat);
      });
    });
  } catch (err) {
    props.errors.global = `Error initializing manual map: ${err.message}`;
    console.error(err);
  }
};

const updateCreateMarker = (lng, lat) => {
  if (createMarker) createMarker.remove();
  const el = document.createElement('div');
  el.className = 'marker';
  el.style.backgroundColor = getColor(createForm.activity || props.activityTypes[0]?.name);
  el.style.width = '12px';
  el.style.height = '12px';
  el.style.borderRadius = '50%';
  el.style.border = '2px solid white';

  createMarker = new mapboxgl.Marker(el).setLngLat([lng, lat]).addTo(createMap);
};

const updateManualMarker = (lng, lat) => {
  if (manualMarker) manualMarker.remove();
  const el = document.createElement('div');
  el.className = 'marker';
  el.style.backgroundColor = getColor(manualForm.activity);
  el.style.width = '12px';
  el.style.height = '12px';
  el.style.borderRadius = '50%';
  el.style.border = '2px solid white';

  manualMarker = new mapboxgl.Marker(el).setLngLat([lng, lat]).addTo(manualMap);
};

watch(selectedMapStyle, (newStyle) => {
  if (map) {
    map.setStyle(newStyle);
    map.on('style.load', () => {
      updateMapMarkers();
    });
  }
  if (createMap) {
    createMap.setStyle(newStyle);
    createMap.on('style.load', () => {
      if (createForm.latitude && createForm.longitude) {
        updateCreateMarker(createForm.longitude, createForm.latitude);
      }
    });
  }
  if (manualMap) {
    manualMap.setStyle(newStyle);
    map.on('style.load', () => {
      if (manualForm.latitude && manualForm.longitude) {
        updateManualMarker(manualForm.longitude, manualForm.latitude);
      }
    });
  }
  if (locationMap) {
    locationMap.setStyle(newStyle);
    map.on('style.load', () => {
      if ((editingLocationId.value && editLocationForm.value.latitude && editLocationForm.value.longitude) ||
          (newLocation.value.latitude && newLocation.value.longitude)) {
        updateLocationMarker(
          editingLocationId.value ? editLocationForm.value.longitude : newLocation.value.longitude,
          editingLocationId.value ? editLocationForm.value.latitude : newLocation.value.latitude
        );
      }
    });
  }
});

onMounted(() => {
  console.log('Props received:', props);
  try {
    localActivities.value = props.activities || [];
    initializeSelectedLocations();
    initMap();
    updateCalendarEvents();
  } catch (err) {
    props.errors.global = `Error initializing component: ${err.message}`;
    console.error(err);
  }
});

watch(localActivities, () => {
  initializeSelectedLocations();
  updateMapMarkers();
  updateCalendarEvents();
});

watch(showCreateModal, (newVal) => {
  if (newVal) {
    setTimeout(() => initCreateMap(), 0);
  }
});

watch(showManualModal, (newVal) => {
  if (newVal) {
    setTimeout(() => initManualMap(), 0);
  }
});

watch(dateFilter, () => {
  router.get(
    '/activities',
    { date: dateFilter.value.toISOString().split('T')[0] },
    {
      preserveState: true,
      onSuccess: () => {
        localActivities.value = props.activities;
        initializeSelectedLocations();
        updateCalendarEvents();
      },
    }
  );
});
</script>

<style>
#map,
#createMap,
#manualMap,
#locationMap {
  height: 100%;
}
.vuecal__event.activity-event {
  color: white;
  font-size: 0.75rem;
  padding: 2px;
}
.vuecal__event.activity-event.highlighted {
  background-color: #ff4500;
}
.vuecal__cell--selected {
  background-color: rgba(79, 70, 229, 0.2);
}
.vuecal__time-cell {
  font-size: 0.75rem;
}
.vuecal__cell-content {
  font-size: 0.75rem;
}
.vuecal__event.activity-Left-Home { background-color: #10B981; }
.vuecal__event.activity-Arrive-Depot { background-color: #3B82F6; }
.vuecal__event.activity-Start-Loading { background-color: #F59E0B; }
.vuecal__event.activity-Leave-Depot { background-color: #EF4444; }
.vuecal__event.activity-First-Drop { background-color: #8B5CF6; }
.vuecal__event.activity-Last-Drop { background-color: #EC4899; }
.vuecal__event.activity-Arrive-Home { background-color: #22D3EE; }
</style>