<script setup>
import { computed } from 'vue'
import { CheckCircle2, Clock3, HeartPulse, CalendarCheck2, CircleMinus } from 'lucide-vue-next'
const props = defineProps({ status: { type: String, required: true }, label: { type: String, default: '' } })
const entries = {
  PRESENT: { text: 'Hadir', icon: CheckCircle2, tone: 'present' },
  LATE: { text: 'Terlambat', icon: Clock3, tone: 'late' },
  SICK: { text: 'Sakit', icon: HeartPulse, tone: 'sick' },
  PERMISSION: { text: 'Izin', icon: CalendarCheck2, tone: 'permission' },
  ABSENT: { text: 'Tidak hadir', icon: CircleMinus, tone: 'absent' },
}
const value = computed(() => entries[props.status] ?? { text: props.label || 'Tidak diketahui', icon: CircleMinus, tone: 'unknown' })
</script>
<template>
  <span class="attendance-badge" :class="`badge-${value.tone}`">
    <component :is="value.icon" :size="14" aria-hidden="true" />{{ label || value.text }}
  </span>
</template>
<style scoped>
.attendance-badge{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;padding:6px 10px;border-radius:999px;font-weight:750;font-size:11px}.badge-present{color:#11765e;background:#e5f7ef}.badge-late{color:#916113;background:#fff3d6}.badge-sick{color:#346caa;background:#eaf2fc}.badge-permission{color:#6d559f;background:#f1ecfc}.badge-absent{color:#a6384b;background:#fcebee}.badge-unknown{color:#576b7d;background:#f0f4f7}
</style>
