<script setup>
import { computed } from 'vue'
import { BarChart3, Table2 } from 'lucide-vue-next'
const props = defineProps({
  rows: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
  money: { type: Boolean, default: false },
})
const number = value => Number(value || 0)
const peak = computed(() => Math.max(1, ...props.rows.flatMap(row => props.series.map(metric => number(row[metric.key])))))
const show = value => props.money ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(number(value)) : new Intl.NumberFormat('id-ID').format(number(value))
const height = value => `${Math.max(0, (number(value) / peak.value) * 100)}%`
</script>

<template>
  <section class="rpt-chart" aria-labelledby="report-trend-title">
    <div class="rpt-section-title"><div><BarChart3 :size="21" aria-hidden="true"/><h3 id="report-trend-title">Tren harian</h3></div><p>Angka berasal dari laporan backend.</p></div>
    <div v-if="!rows.length" class="rpt-empty" role="status">Belum ada data harian untuk rentang tanggal ini.</div>
    <template v-else>
      <div class="rpt-chart-legend" aria-hidden="true"><span v-for="(metric, i) in series" :key="metric.key"><i :class="`rpt-legend-${i}`"/>{{ metric.label }}</span></div>
      <div class="rpt-chart-scroll" role="img" :aria-label="`Grafik batang harian ${series.map(s => s.label).join(' dan ')}. Data lengkap tersedia di tabel di bawah.`">
        <div class="rpt-chart-bars" :style="{ minWidth: `${Math.max(510, rows.length * 33)}px` }">
          <div v-for="row in rows" :key="row.date" class="rpt-chart-day">
            <div class="rpt-chart-group"><div v-for="(metric,i) in series" :key="metric.key" class="rpt-chart-bar" :class="`rpt-chart-bar-${i}`" :style="{ height: height(row[metric.key]) }" :title="`${row.date} · ${metric.label}: ${show(row[metric.key])}`"></div></div>
            <span class="rpt-chart-date">{{ String(row.date || '').slice(5) }}</span>
          </div>
        </div>
      </div>
      <details class="rpt-chart-details"><summary><Table2 :size="17" aria-hidden="true"/> Lihat data grafik sebagai tabel ({{ rows.length }} hari)</summary>
        <div class="rpt-table-scroll"><table class="rpt-table"><thead><tr><th scope="col">Tanggal</th><th v-for="metric in series" :key="metric.key" scope="col">{{ metric.label }}</th></tr></thead><tbody><tr v-for="row in rows" :key="row.date"><th scope="row">{{ row.date }}</th><td v-for="metric in series" :key="metric.key">{{ show(row[metric.key]) }}</td></tr></tbody></table></div>
      </details>
    </template>
  </section>
</template>
