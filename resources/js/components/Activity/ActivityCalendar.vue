<template>
  <div class="mb-6 flex-1">
    <label class="block text-sm font-medium text-gray-300 mb-2">Activities Calendar</label>
    <vue-cal
      :events="calendarEvents"
      :active-date="dateFilter"
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
      @event-drop="$emit('event-dropped', $event)"
      @event-click="$emit('event-clicked', $event)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import VueCal from 'vue-cal';
import 'vue-cal/dist/vuecal.css';

const props = defineProps({
  activities: Array,
  activityTypes: Array,
  dateFilter: Date,
});

const emit = defineEmits(['date-changed', 'event-dropped', 'event-clicked']);

const getAlias = (activityName) => {
  const type = props.activityTypes.find((t) => t.name === activityName);
  return type ? type.alias : activityName;
};

const calendarEvents = computed(() => props.activities.map((activity) => ({
  id: activity.id,
  start: new Date(activity.datetime),
  end: new Date(new Date(activity.datetime).getTime() + 30 * 60000),
  title: getAlias(activity.activity),
  class: `activity-event activity-${activity.activity.replace(/\s+/g, '-')}`,
})));
</script>

<style>
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
.vuecal__cells--week .vuecal__cell {
  padding-right: 8px;
}
</style>