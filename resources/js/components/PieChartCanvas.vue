<template>
    <canvas ref="chartCanvas"></canvas>
  </template>

  <script setup>
  import { ref, onMounted, onUnmounted, watch } from 'vue';
  import { Chart } from 'chart.js/auto';

  const props = defineProps({
    chartData: {
      type: Object,
      required: true,
      default: () => ({ labels: [], datasets: [] }),
    },
    options: {
      type: Object,
      default: () => ({}),
    },
  });

  const chartCanvas = ref(null);
  let chartInstance = null;

  const renderChart = () => {
    if (!chartCanvas.value) return;
    if (!props.chartData || !props.chartData.labels || !props.chartData.datasets || props.chartData.datasets.length === 0) {
      console.warn('PieChartCanvas: Invalid chart data', props.chartData);
      return;
    }

    if (chartInstance) {
      chartInstance.destroy();
    }

    chartInstance = new Chart(chartCanvas.value, {
      type: 'pie',
      data: props.chartData,
      options: props.options,
    });
  };

  onMounted(() => {
    renderChart();
  });

  watch(() => props.chartData, () => {
    renderChart();
  }, { deep: true });

  onUnmounted(() => {
    if (chartInstance) {
      chartInstance.destroy();
    }
  });
  </script>
