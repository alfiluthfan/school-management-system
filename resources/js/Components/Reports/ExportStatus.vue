<script setup>
import { CheckCircle2, Clock3, LoaderCircle, XCircle, CalendarX2 } from 'lucide-vue-next'
const props = defineProps({ status: { type: String, required: true } })
const labels = { QUEUED: 'Dalam antrean', PROCESSING: 'Sedang diproses', READY: 'Siap diunduh', FAILED: 'Gagal', EXPIRED: 'Kedaluwarsa' }
const icons = { QUEUED: Clock3, PROCESSING: LoaderCircle, READY: CheckCircle2, FAILED: XCircle, EXPIRED: CalendarX2 }
</script>
<template>
  <span class="rpt-status" :class="`rpt-status-${status.toLowerCase()}`"><component :is="icons[status] || Clock3" :size="15" aria-hidden="true"/>{{ labels[status] || 'Tidak diketahui' }}</span>
</template>
