<template>
    <canvas ref="chartCanvas"></canvas>
  </template>

  <script setup lang="ts">
  import { ref, onMounted, watch } from 'vue';
  import Chart from 'chart.js/auto';

  const props = defineProps<{
    chartData: object;
    options: object;
  }>();

  const chartCanvas = ref<HTMLCanvasElement | null>(null);
  let chartInstance: Chart | null = null;

  const renderChart = () => {
    if (chartCanvas.value) {
      chartInstance?.destroy();
      chartInstance = new Chart(chartCanvas.value, {
        type: 'line',
        data: props.chartData,
        options: props.options,
      });
    }
  };

  onMounted(() => {
    renderChart();
  });

  watch(() => props.chartData, () => {
    renderChart();
  }, { deep: true });
  </script>
