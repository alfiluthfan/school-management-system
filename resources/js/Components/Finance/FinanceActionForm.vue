<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { AlertCircle, CheckCircle2, X } from 'lucide-vue-next'
import { rupiah } from '../../lib/format.js'
import '../../../css/finance.css'

const props = defineProps({
  kind: { type: String, required: true }, endpoint: { type: String, required: true },
  maximum: { type: String, default: '' }, methods: { type: Array, default: () => [] },
  label: { type: String, required: true },
})
const form = useForm({ amount: '', description: '', payment_method: 'CASH', reference_number: '', notes: '' })
const confirming = ref(false)
const isPayment = computed(() => props.kind === 'payment')
const isWithdrawal = computed(() => props.kind === 'withdraw')
function submit() {
  if (!form.amount || Number(form.amount) <= 0 || (props.maximum && Number(form.amount) > Number(props.maximum))) return
  confirming.value = true
}
function confirm() {
  form.transform(data => isPayment.value
    ? { amount: data.amount, payment_method: data.payment_method, reference_number: data.reference_number, notes: data.notes }
    : { amount: data.amount, description: data.description }
  ).post(props.endpoint, { preserveScroll: true, onSuccess: () => { confirming.value = false; form.reset() }, onError: () => { confirming.value = false } })
}
</script>
<template>
  <section class="finance-action" :aria-label="label">
    <h3>{{ label }}</h3><p class="finance-muted">Periksa nominal sebelum mengirim. Sistem akan memvalidasi ulang saldo dan sisa tagihan.</p>
    <form @submit.prevent="submit" class="finance-form">
      <label class="finance-field">Nominal (Rp)<input v-model="form.amount" name="amount" type="number" inputmode="decimal" min="0.01" step="0.01" :max="maximum || undefined" required placeholder="Contoh: 50000"/><small v-if="maximum">Batas yang tersedia: {{ rupiah(maximum) }}</small><small v-if="form.errors.amount" class="finance-field-error">{{ form.errors.amount }}</small></label>
      <label v-if="isPayment" class="finance-field">Metode pembayaran<select v-model="form.payment_method"><option v-for="method in methods" :key="method.value" :value="method.value">{{ method.label }}</option></select><small v-if="form.errors.payment_method" class="finance-field-error">{{ form.errors.payment_method }}</small></label>
      <label v-if="isPayment" class="finance-field">Nomor referensi (opsional)<input v-model.trim="form.reference_number" type="text" maxlength="100"/><small v-if="form.errors.reference_number" class="finance-field-error">{{ form.errors.reference_number }}</small></label>
      <label class="finance-field finance-full">{{ isPayment ? 'Catatan pembayaran' : 'Keterangan transaksi' }} (opsional)<textarea v-if="isPayment" v-model.trim="form.notes" rows="2" maxlength="1000"/><textarea v-else v-model.trim="form.description" rows="2" maxlength="500"/><small v-if="form.errors.description || form.errors.notes" class="finance-field-error">{{ form.errors.description || form.errors.notes }}</small></label>
      <p v-if="form.errors.bill || form.errors.account" class="finance-error" role="alert"><AlertCircle :size="16"/>{{ form.errors.bill || form.errors.account }}</p>
      <button class="button button-primary finance-full" type="submit" :disabled="form.processing || !form.amount || Number(form.amount) <= 0 || (!!maximum && Number(form.amount) > Number(maximum))">{{ form.processing ? 'Menyimpan…' : label }}</button>
    </form>
    <div v-if="confirming" class="finance-dialog-backdrop"><div class="finance-dialog" role="alertdialog" aria-modal="true" aria-labelledby="finance-confirm-title" aria-describedby="finance-confirm-desc">
      <button type="button" class="finance-dialog-x" @click="confirming=false" aria-label="Batalkan"><X :size="19"/></button>
      <CheckCircle2 :size="25" aria-hidden="true"/><h3 id="finance-confirm-title">Konfirmasi {{ label.toLowerCase() }}</h3>
      <p id="finance-confirm-desc">Anda akan mencatat {{ isPayment ? 'pembayaran' : isWithdrawal ? 'penarikan' : 'setoran' }} sebesar <strong>{{ rupiah(form.amount) }}</strong>. Pastikan nominal benar sebelum melanjutkan.</p>
      <div class="finance-dialog-actions"><button type="button" class="button button-secondary" :disabled="form.processing" @click="confirming=false">Kembali</button><button type="button" class="button button-primary" :disabled="form.processing" @click="confirm">{{ form.processing ? 'Memproses…' : 'Ya, simpan transaksi' }}</button></div>
    </div></div>
  </section>
</template>
