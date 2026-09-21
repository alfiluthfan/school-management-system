<script setup>
import { computed } from 'vue'
import { visibilityText } from '../../lib/announcement.js'
const props = defineProps({ status: { type: Object, required: true }, visibility: { type: String, required: true } })
const text = computed(() => visibilityText[props.visibility] || props.status.label)
</script>
<template>
  <span class="ann-status" :data-visibility="visibility">{{ text }}</span>
</template>
