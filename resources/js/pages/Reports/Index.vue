<template>
    <div class="container">
        <h1>Manifest Summary Report</h1>
        <form @submit.prevent="filter" class="mb-4">
            <div class="row">
                <div class="col-md-4">
                    <label for="period_id" class="form-label">Select Period</label>
                    <select v-model="selectedPeriod" id="period_id" class="form-control">
                        <option value="">All Periods</option>
                        <option v-for="period in periods" :key="period.id" :value="period.id">
                            {{ period.name }} ({{ period.start_date }} - {{ period.end_date }})
                        </option>
                    </select>
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form>

        <div v-if="Object.keys(reportData).length === 0">
            <p>No data available for the selected period.</p>
        </div>
        <table v-else class="table">
            <thead>
                <tr>
                    <th>Parcel Type</th>
                    <th>Manifested</th>
                    <th>Re-manifested</th>
                    <th>Carried Forward</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(totals, parcelType) in reportData" :key="parcelType">
                    <td>{{ parcelType }}</td>
                    <td>{{ totals.manifested }}</td>
                    <td>{{ totals.re_manifested }}</td>
                    <td>{{ totals.carried_forward }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import { useForm } from '@inertiajs/vue3';

export default {
    props: {
        periods: Array,
        selectedPeriodId: String,
        reportData: Object,
    },
    setup(props) {
        const form = useForm({
            period_id: props.selectedPeriodId || '',
        });

        function filter() {
            form.get(route('reports.index'), {
                preserveState: true,
                preserveScroll: true,
            });
        }

        return { form, filter, selectedPeriod: form.period_id };
    },
};
</script>
