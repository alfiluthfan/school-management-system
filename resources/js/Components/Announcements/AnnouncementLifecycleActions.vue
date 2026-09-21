<script setup>
import { computed, nextTick, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Archive, CalendarClock, Send, ShieldCheck, X } from 'lucide-vue-next'
import { announcementLocalInput } from '../../lib/announcement.js'

const props = defineProps({ announcement: { type: Object, required: true } })
const publishDialog = ref(null)
const archiveDialog = ref(null)
const firstInput = ref(null)
const mode = ref('now')
const publishForm = useForm({ publish_at: '', expired_at: announcementLocalInput(props.announcement.expired_at) })
const archiveForm = useForm({})
const error = ref('')
const canPublish = computed(() => props.announcement.can?.publish === true)
const canArchive = computed(() => props.announcement.can?.archive === true)
function openPublish() {
  if (!canPublish.value) return
  mode.value = 'now'
  error.value = ''
  publishForm.reset()
  publishForm.expired_at = announcementLocalInput(props.announcement.expired_at)
  publishForm.clearErrors()
  publishDialog.value?.showModal()
  nextTick(() => firstInput.value?.focus())
}
function publish() {
  if (publishForm.processing || !canPublish.value) return
  error.value = ''
  if (mode.value === 'schedule' && !publishForm.publish_at) {
    error.value = 'Isi waktu publikasi terlebih dahulu.'
    return
  }
  if (mode.value === 'schedule' && publishForm.expired_at && publishForm.expired_at <= publishForm.publish_at) {
    error.value = 'Waktu berakhir harus setelah waktu publikasi.'
    return
  }
  publishForm.transform(data => ({
    publish_at: mode.value === 'schedule' ? data.publish_at : null,
    expired_at: data.expired_at || null,
  })).post(`/announcements/${encodeURIComponent(props.announcement.uuid)}/publish`, {
    preserveScroll: true,
    onSuccess: () => publishDialog.value?.close(),
  })
}
function archive() {
  if (archiveForm.processing || !canArchive.value) return
  archiveForm.post(`/announcements/${encodeURIComponent(props.announcement.uuid)}/archive`, {
    preserveScroll: true,
    onSuccess: () => archiveDialog.value?.close(),
  })
}
</script>
<template>
  <div v-if="canPublish || canArchive" class="ann-action-toolbar" aria-label="Kelola pengumuman">
    <button v-if="canPublish" type="button" class="button button-primary" @click="openPublish"><Send :size="16" aria-hidden="true"/> Terbitkan</button>
    <button v-if="canArchive" type="button" class="button button-secondary ann-danger" @click="archiveDialog?.showModal()"><Archive :size="16" aria-hidden="true"/> Arsipkan</button>
  </div>
  <dialog ref="publishDialog" class="ann-dialog" aria-labelledby="ann-publish-title" @cancel="event => { if (publishForm.processing) event.preventDefault() }">
    <form novalidate @submit.prevent="publish">
      <div class="ann-dialog-icon"><CalendarClock :size="25" aria-hidden="true" /></div>
      <h2 id="ann-publish-title">Publikasikan pengumuman</h2>
      <p>Setelah dipublikasikan, draft tidak dapat diedit. Pengumuman terjadwal baru terlihat oleh audiens pada waktu yang ditentukan.</p>
      <fieldset class="ann-radio-group"><legend>Waktu publikasi</legend>
        <label><input ref="firstInput" v-model="mode" type="radio" value="now" /> Terbitkan sekarang</label>
        <label><input v-model="mode" type="radio" value="schedule" /> Jadwalkan publikasi</label>
      </fieldset>
      <label v-if="mode === 'schedule'" class="ann-field">Publikasi pada (WIB)
        <input v-model="publishForm.publish_at" type="datetime-local" required :aria-invalid="Boolean(publishForm.errors.publish_at)" />
        <small v-if="publishForm.errors.publish_at" role="alert" class="ann-error">{{ publishForm.errors.publish_at }}</small>
      </label>
      <label class="ann-field">Berakhir pada (WIB, opsional)
        <input v-model="publishForm.expired_at" type="datetime-local" :aria-invalid="Boolean(publishForm.errors.expired_at)" />
        <small>Biarkan kosong jika pengumuman tidak memiliki tanggal kedaluwarsa.</small>
        <small v-if="publishForm.errors.expired_at" role="alert" class="ann-error">{{ publishForm.errors.expired_at }}</small>
      </label>
      <p v-if="error || publishForm.errors.announcement" role="alert" class="ann-error">{{ error || publishForm.errors.announcement }}</p>
      <div class="ann-dialog-footer">
        <button type="button" class="button button-secondary" :disabled="publishForm.processing" @click="publishDialog?.close()"><X :size="16" aria-hidden="true"/> Batal</button>
        <button type="submit" class="button button-primary" :disabled="publishForm.processing"><Send :size="16" aria-hidden="true"/> {{ publishForm.processing ? 'Memproses…' : mode === 'schedule' ? 'Jadwalkan' : 'Publikasikan' }}</button>
      </div>
    </form>
  </dialog>
  <dialog ref="archiveDialog" class="ann-dialog" aria-labelledby="ann-archive-title" @cancel="event => { if (archiveForm.processing) event.preventDefault() }">
    <form novalidate @submit.prevent="archive">
      <div class="ann-dialog-icon ann-icon-danger"><Archive :size="25" aria-hidden="true" /></div>
      <h2 id="ann-archive-title">Arsipkan pengumuman ini?</h2>
      <p>Pengumuman dihapus dari feed penerima, tetapi catatan dan auditnya tetap disimpan. Tindakan ini tidak dapat dibatalkan melalui UI ini.</p>
      <p v-if="archiveForm.errors.announcement" role="alert" class="ann-error">{{ archiveForm.errors.announcement }}</p>
      <div class="ann-dialog-footer">
        <button type="button" class="button button-secondary" :disabled="archiveForm.processing" @click="archiveDialog?.close()">Kembali</button>
        <button type="submit" class="button ann-button-danger" :disabled="archiveForm.processing"><ShieldCheck :size="16" aria-hidden="true" /> {{ archiveForm.processing ? 'Memproses…' : 'Ya, arsipkan' }}</button>
      </div>
    </form>
  </dialog>
</template>
