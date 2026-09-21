<script setup>
import { computed } from 'vue'
const props = defineProps({ title: String, data: { type: Object, required: true }, note: String })
const items = computed(() => [
  { key: 'present', label: 'Hadir', tone: 'present' }, { key: 'late', label: 'Terlambat', tone: 'late' },
  { key: 'sick', label: 'Sakit', tone: 'sick' }, { key: 'permission', label: 'Izin', tone: 'permission' },
  { key: 'absent', label: 'Tanpa keterangan', tone: 'absent' },
])
const total = computed(() => Number(props.data.total || 0))
const percent = (value) => total.value ? Math.max(0, Math.min(100, Number(value || 0) / total.value * 100)) : 0
</script>
<template>
  <section class="panel"><div class="panel-head"><div><h2>{{ title }}</h2><p>{{ note || 'Distribusi catatan absensi pada tanggal terpilih' }}</p></div><span class="count-pill">{{ total }} catatan</span></div><div v-if="total === 0" class="empty-inline">Belum ada catatan absensi untuk tanggal ini. Angka nol bukan berarti semua siswa atau guru tidak hadir.</div><div v-else class="breakdown"><div v-for="item in items" :key="item.key" class="breakdown-row"><div class="breakdown-title"><span class="legend" :class="`legend-${item.tone}`"></span><span>{{ item.label }}</span><strong>{{ data[item.key] ?? 0 }}</strong></div><div class="bar-track"><span class="bar-fill" :class="`bar-${item.tone}`" :style="{ width: `${percent(data[item.key])}%` }"></span></div></div></div></section>
</template>
