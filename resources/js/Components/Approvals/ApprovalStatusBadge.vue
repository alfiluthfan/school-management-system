<script setup>
import { computed } from 'vue'
import { Clock3, CheckCircle2, XCircle, CircleMinus } from 'lucide-vue-next'
const props = defineProps({ status: { type: String, required: true }, label: { type: String, default: '' } })
const options = {
  PENDING: { icon: Clock3, label: 'Menunggu', tone: 'pending' },
  APPROVED: { icon: CheckCircle2, label: 'Disetujui', tone: 'approved' },
  REJECTED: { icon: XCircle, label: 'Ditolak', tone: 'rejected' },
  CANCELLED: { icon: CircleMinus, label: 'Dibatalkan', tone: 'cancelled' },
}
const item = computed(() => options[props.status] ?? { icon: CircleMinus, label: 'Status tidak diketahui', tone: 'cancelled' })
</script>
<template>
  <span class="approval-badge" :class="`tone-${item.tone}`">
    <component :is="item.icon" :size="14" aria-hidden="true" />
    {{ label || item.label }}
  </span>
</template>
<style scoped>
.approval-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:750;white-space:nowrap}
.tone-pending{color:#946000;background:#fff4d8}.tone-approved{color:#117053;background:#e4f6ed}.tone-rejected{color:#ac3d4e;background:#ffedf0}.tone-cancelled{color:#566a7d;background:#edf1f5}
</style>
