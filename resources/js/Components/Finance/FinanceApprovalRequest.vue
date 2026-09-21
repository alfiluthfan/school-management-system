<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { ShieldCheck, X } from 'lucide-vue-next'
import '../../../css/finance.css'
const props = defineProps({ endpoint: { type: String, required: true }, label: { type: String, required: true } })
const opened = ref(false)
const confirmed = ref(false)
const form = useForm({ reason: '' })
function submit() {
  if (!confirmed.value) { confirmed.value = true; return }
  form.post(props.endpoint, { preserveScroll: true, onSuccess: () => { opened.value = false; confirmed.value = false; form.reset() }, onError: () => { confirmed.value = false } })
}
function close() { opened.value = false; confirmed.value = false; form.clearErrors() }
</script>
<template>
  <button type="button" class="finance-link" :aria-label="label" @click="opened=true">{{ label }}</button>
  <div v-if="opened" class="finance-dialog-backdrop"><div class="finance-dialog" role="dialog" aria-modal="true" aria-labelledby="finance-request-title">
    <button type="button" class="finance-dialog-x" aria-label="Tutup" @click="close"><X :size="18"/></button>
    <ShieldCheck :size="25" aria-hidden="true"/><h3 id="finance-request-title">{{ label }}</h3>
    <p>Permintaan ini membutuhkan persetujuan pihak berwenang. Mengirim permintaan tidak langsung mengubah saldo atau tagihan.</p>
    <form @submit.prevent="submit"><label class="finance-field">Alasan pengajuan<textarea v-model.trim="form.reason" rows="3" minlength="5" maxlength="1000" required placeholder="Jelaskan alasan minimal 5 karakter"/><small v-if="form.errors.reason" role="alert" class="finance-field-error">{{ form.errors.reason }}</small><small v-if="form.errors.approval" role="alert" class="finance-field-error">{{ form.errors.approval }}</small></label>
      <p v-if="confirmed" class="finance-note">Periksa alasan dan konfirmasi sekali lagi untuk mengirim permintaan.</p>
      <div class="finance-dialog-actions"><button type="button" class="button button-secondary" :disabled="form.processing" @click="close">Batal</button><button type="submit" class="button button-primary" :disabled="form.processing || form.reason.length < 5">{{ form.processing ? 'Mengirim…' : confirmed ? 'Konfirmasi pengajuan' : 'Lanjutkan' }}</button></div>
    </form>
  </div></div>
</template>
