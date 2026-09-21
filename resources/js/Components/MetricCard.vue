<script setup>
import { computed } from 'vue'
import { ArrowUpRight } from 'lucide-vue-next'
const props = defineProps({ label: String, value: [String, Number], note: String, icon: Object, tone: { type: String, default: 'teal' } })
const toneClass = computed(() => `metric-${props.tone}`)
</script>
<template>
  <article class="metric-card"><div class="metric-top"><span class="metric-icon" :class="toneClass"><component :is="icon" :size="20" aria-hidden="true" /></span><ArrowUpRight :size="16" class="metric-corner" aria-hidden="true" /></div><p class="metric-label">{{ label }}</p><p class="metric-value">{{ value }}</p><p class="metric-note">{{ note }}</p></article>
</template>
