<script setup>
import { computed, nextTick, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { CheckCheck, XCircle, ShieldAlert, ArrowLeft } from 'lucide-vue-next'
const props = defineProps({ approval: { type: Object, required: true } })
const dialog = ref(null)
const notesInput = ref(null)
const mode = ref(null)
const form = useForm({ review_notes: '' })
const canDecide = computed(() => props.approval?.status?.value === 'PENDING' && (props.approval?.can?.approve || props.approval?.can?.reject))
const rejection = computed(() => mode.value === 'reject')
function open(which) {
  if (!canDecide.value || (which === 'approve' && !props.approval.can.approve) || (which === 'reject' && !props.approval.can.reject)) return
  mode.value = which
  form.reset()
  form.clearErrors()
  dialog.value?.showModal()
  nextTick(() => notesInput.value?.focus())
}
function close() {
  if (form.processing) return
  dialog.value?.close()
  form.reset()
  form.clearErrors()
  mode.value = null
}
function submit() {
  if (form.processing || !mode.value) return
  if (rejection.value && form.review_notes.trim().length < 5) {
    form.setError('review_notes', 'Alasan penolakan minimal 5 karakter.')
    notesInput.value?.focus()
    return
  }
  form.clearErrors()
  const action = mode.value
  form.post(`/approvals/${encodeURIComponent(props.approval.uuid)}/${action}`, {
    preserveScroll: true,
    onSuccess: () => { dialog.value?.close(); form.reset(); form.clearErrors(); mode.value = null },
  })
}
</script>
<template>
  <section v-if="canDecide" class="panel decision-panel" aria-labelledby="approval-decision-title">
    <div class="decision-heading"><ShieldAlert :size="20" aria-hidden="true"/><div><h2 id="approval-decision-title">Tinjau pengajuan</h2><p>Periksa detail dan bukti sebelum mencatat keputusan. Keputusan akan diproses server.</p></div></div>
    <div class="decision-buttons">
      <button v-if="approval.can.approve" type="button" class="button button-primary" @click="open('approve')"><CheckCheck :size="17" aria-hidden="true"/> Setujui</button>
      <button v-if="approval.can.reject" type="button" class="button button-secondary danger-button" @click="open('reject')"><XCircle :size="17" aria-hidden="true"/> Tolak</button>
    </div>
  </section>
  <dialog ref="dialog" class="approval-dialog" aria-labelledby="decision-dialog-title" @cancel="(event) => { if (form.processing) event.preventDefault() }">
    <form v-if="mode" novalidate @submit.prevent="submit">
      <div class="dialog-icon" :class="{ danger: rejection }"><component :is="rejection ? XCircle : CheckCheck" :size="25" aria-hidden="true"/></div>
      <h2 id="decision-dialog-title">{{ rejection ? 'Tolak pengajuan ini?' : 'Setujui pengajuan ini?' }}</h2>
      <p>{{ rejection ? 'Cantumkan alasan yang jelas untuk pemohon. Alasan penolakan wajib diisi.' : 'Persetujuan akan menjalankan operasi domain melalui Generic Approval Workflow. Periksa detail sebelum melanjutkan.' }}</p>
      <label for="decision-review-notes">{{ rejection ? 'Alasan penolakan (wajib)' : 'Catatan persetujuan (opsional)' }}</label>
      <textarea id="decision-review-notes" ref="notesInput" v-model="form.review_notes" rows="4" maxlength="1000" :required="rejection" :aria-invalid="Boolean(form.errors.review_notes)" :aria-describedby="form.errors.review_notes ? 'review-notes-error' : undefined" placeholder="Tuliskan catatan keputusan…" />
      <small class="character-count">{{ form.review_notes.length }}/1000 karakter</small>
      <p v-if="form.errors.review_notes" id="review-notes-error" class="decision-error" role="alert">{{ form.errors.review_notes }}</p>
      <p v-if="form.errors.approval" class="decision-error" role="alert">{{ form.errors.approval }}</p>
      <div class="dialog-actions"><button type="button" class="button button-secondary" :disabled="form.processing" @click="close"><ArrowLeft :size="16" aria-hidden="true"/> Kembali</button><button type="submit" class="button" :class="rejection ? 'submit-reject' : 'button-primary'" :disabled="form.processing"><component :is="rejection ? XCircle : CheckCheck" :size="16" aria-hidden="true"/> {{ form.processing ? 'Memproses…' : (rejection ? 'Konfirmasi penolakan' : 'Konfirmasi persetujuan') }}</button></div>
    </form>
  </dialog>
</template>
<style scoped>
.decision-panel{border:1px solid #cfebe3;background:#fafffc}.decision-heading{display:flex;gap:12px;color:#13746b}.decision-heading svg{flex-shrink:0;margin-top:2px}.decision-heading h2{font-size:18px;color:#17374a;margin:0 0 5px}.decision-heading p{font-size:12px;color:#61778a;line-height:1.65;margin:0}.decision-buttons{display:flex;gap:9px;flex-wrap:wrap;margin-top:19px}.danger-button{color:#a83649;border-color:#efcfd5}.approval-dialog{border:1px solid #dce8ee;box-shadow:0 25px 80px #112f4560;border-radius:18px;width:min(calc(100% - 26px),510px);padding:26px;color:#243d4f}.approval-dialog::backdrop{background:#10293dc2;backdrop-filter:blur(2px)}.dialog-icon{width:47px;height:47px;display:grid;place-items:center;border-radius:14px;background:#e4f6ef;color:#0c8660;margin-bottom:15px}.dialog-icon.danger{background:#ffeaed;color:#b23a50}.approval-dialog h2{margin:0 0 8px;font-size:22px}.approval-dialog p{font-size:13px;line-height:1.7;color:#60778b}.approval-dialog label{display:block;font-weight:800;color:#314c61;font-size:12px;margin:20px 0 7px}.approval-dialog textarea{display:block;width:100%;resize:vertical;min-height:105px;border:1px solid #cadbe5;border-radius:9px;padding:12px;line-height:1.55;color:#24394b;font:inherit;font-size:13px}.approval-dialog textarea:focus-visible{outline:3px solid #9bdee1;outline-offset:2px}.character-count{display:block;text-align:right;color:#75899c;font-size:11px;margin-top:5px}.decision-error{margin:8px 0;color:#a82c46!important;background:#fff0f3;padding:10px;border-radius:8px}.dialog-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:9px;margin-top:23px}.submit-reject{color:#fff;background:#ae334a}.submit-reject:hover{background:#8d253a}@media(max-width:500px){.approval-dialog{padding:18px}.dialog-actions button{flex:1}}
</style>
