<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { LoaderCircle, Search, X } from 'lucide-vue-next'

const props = defineProps({
  id: { type: String, required: true },
  resource: { type: String, required: true },
  context: { type: String, required: true },
  modelValue: { type: [String, Number], default: '' },
  required: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])
const term = ref('')
const choices = ref([])
const selected = ref(null)
const loading = ref(false)
const error = ref('')
const more = ref(null)
let timer = null
let controller = null
let sequence = 0
let selectedContextKey = ''

const cancel = () => {
  if (timer) clearTimeout(timer)
  timer = null
  if (controller) controller.abort()
  controller = null
}
const query = async (page = 1, hydrate = false) => {
  const current = ++sequence
  cancel()
  controller = new AbortController()
  loading.value = true
  error.value = ''
  const params = new URLSearchParams({ resource: props.resource, context: props.context })
  if (hydrate) params.set('selected', String(props.modelValue))
  else { params.set('search', term.value.trim()); params.set('page', String(page)) }
  try {
    const response = await fetch(`/master-data/options?${params}`, {
      credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal,
    })
    if (!response.ok) throw new Error(response.status === 401 || response.status === 419
      ? 'Sesi telah berakhir. Silakan login kembali.'
      : response.status === 403 ? 'Anda tidak memiliki izin mencari data ini.' : 'Pencarian gagal. Coba lagi.')
    const result = await response.json()
    if (current !== sequence) return
    if (hydrate) selected.value = result.selected || null
    else {
      choices.value = page === 1 ? result.items : [...choices.value, ...result.items]
      more.value = result.next_page
    }
  } catch (e) {
    if (e.name !== 'AbortError' && current === sequence) error.value = e.message
  } finally {
    if (current === sequence) loading.value = false
  }
}

watch(() => [props.modelValue, props.resource, props.context], () => {
  const contextKey = `${props.context}:${props.resource}`
  // A result selected from this component already has the correct label.
  // Avoid re-fetching and racing the subsequent search-field reset.
  if (selected.value && selectedContextKey === contextKey &&
      String(selected.value.value) === String(props.modelValue)) return
  selectedContextKey = contextKey
  // Load an existing class/teacher/year label even if not on any search page.
  sequence++
  cancel()
  choices.value = []
  more.value = null
  if (props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined) {
    selected.value = { value: String(props.modelValue), label: 'Memuat pilihan tersimpan…' }
    query(1, true)
  } else selected.value = null
}, { immediate: true })

watch(term, text => {
  sequence++
  cancel()
  choices.value = []
  more.value = null
  error.value = ''
  if (text.trim().length < 2) { loading.value = false; return }
  timer = setTimeout(() => query(1), 300)
})

const choose = item => {
  selected.value = item
  emit('update:modelValue', String(item.value))
  term.value = ''
  choices.value = []
  more.value = null
}
const clear = () => {
  selected.value = null
  emit('update:modelValue', '')
  term.value = ''
}
onBeforeUnmount(() => { sequence++; cancel() })
</script>

<template>
  <div class="md-remote-select" :aria-invalid="invalid">
    <div v-if="selected" class="md-remote-selected">
      <span>{{ selected.label }}</span>
      <button type="button" :aria-label="`Hapus ${selected.label}`" @click="clear"><X :size="15" /></button>
    </div>
    <div class="md-remote-search">
      <Search :size="16" aria-hidden="true" />
      <input :id="id" v-model="term" type="search" autocomplete="off"
        :placeholder="selected ? 'Cari untuk mengganti pilihan…' : 'Ketik minimal 2 karakter…'"
        :aria-invalid="invalid" :aria-describedby="`${id}-hint`" />
      <LoaderCircle v-if="loading" :size="16" class="md-spin" aria-label="Memuat" />
    </div>
    <small :id="`${id}-hint`">{{ required ? 'Wajib memilih satu hasil pencarian.' : 'Opsional. Pilihan dapat dikosongkan.' }}</small>
    <p v-if="error" role="alert" class="md-field-error">{{ error }}</p>
    <ul v-if="choices.length" class="md-remote-results" :aria-label="`Hasil pencarian ${resource}`">
      <li v-for="item in choices" :key="item.value">
        <button type="button" @click="choose(item)">{{ item.label }}</button>
      </li>
    </ul>
    <p v-else-if="term.trim().length >= 2 && !loading && !error" class="md-form-help">Tidak ada hasil.</p>
    <button v-if="more" class="button button-secondary" type="button" :disabled="loading" @click="query(more)">Muat hasil berikutnya</button>
  </div>
</template>

<style scoped>
.md-remote-select { display: grid; gap: .45rem; min-width: 0; }
.md-remote-selected { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .6rem .8rem; border: 1px solid #b6ccdd; border-radius: .65rem; background: #eff6ff; font-size: .85rem; color: #123456; }
.md-remote-selected button { border: 0; background: transparent; cursor: pointer; color: #1e3a5f; }
.md-remote-search { display: flex; align-items: center; gap: .45rem; padding: .15rem .6rem; border: 1px solid #cbd5e1; border-radius: .65rem; }
.md-remote-search input { flex: 1; min-width: 0; padding: .55rem 0; border: 0; outline: none; background: transparent; font: inherit; }
.md-remote-search:focus-within { outline: 2px solid #2563eb; outline-offset: 2px; }
.md-remote-results { list-style: none; padding: .2rem; margin: 0; border: 1px solid #cbd5e1; border-radius: .65rem; max-height: 14rem; overflow-y: auto; }
.md-remote-results li button { width: 100%; text-align: left; border: 0; background: transparent; border-radius: .35rem; padding: .6rem .7rem; cursor: pointer; font: inherit; }
.md-remote-results li button:hover,.md-remote-results li button:focus { background: #eff6ff; }
.md-remote-select small { font-size: .77rem; color: #64748b; }
</style>
