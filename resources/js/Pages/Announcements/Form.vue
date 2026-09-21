<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, FileText, Info, Save, ShieldCheck } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import { announcementLocalInput } from '../../lib/announcement.js'
import '../../../css/announcements.css'

const props = defineProps({ editor: { type: Object, required: true } })
const edit = computed(() => props.editor.mode === 'edit')
const original = props.editor.announcement
const options = props.editor.options
const initialScope = options.can_school ? 'SCHOOL' : 'CLASS'
const form = useForm({
  title: original?.title ?? '', content: original?.content ?? '',
  target_scope: original?.scope?.value ?? initialScope,
  class_uuid: original?.school_class?.uuid ?? '',
  target_roles: [...(original?.target_roles ?? [])],
  expired_at: announcementLocalInput(original?.expired_at),
})
const clientError = ref('')
const back = computed(() => edit.value ? `/announcements/${encodeURIComponent(original.uuid)}` : '/announcements?tab=mine')
function submit() {
  clientError.value = ''
  if (!form.target_roles.length) { clientError.value = 'Pilih minimal satu kelompok penerima.'; return }
  if (!edit.value && form.target_scope === 'CLASS' && !form.class_uuid) {
    clientError.value = 'Pilih kelas tujuan.'; return
  }
  form.transform(data => {
    const base = { title: data.title, content: data.content, target_roles: data.target_roles, expired_at: data.expired_at || null }
    return edit.value ? base : {
      ...base, target_scope: data.target_scope,
      ...(data.target_scope === 'CLASS' ? { class_uuid: data.class_uuid } : {}),
    }
  })
  if (edit.value) form.patch(`/announcements/${encodeURIComponent(original.uuid)}`, { preserveScroll: true })
  else form.post('/announcements', { preserveScroll: true })
}
</script>
<template>
  <AppLayout :title="edit ? 'Edit draft' : 'Buat pengumuman'" subtitle="Tentukan penerima secara tepat sebelum pengumuman diterbitkan.">
    <div class="ann-page ann-edit-page">
      <Link :href="back" class="ann-back"><ArrowLeft :size="16" aria-hidden="true"/> Kembali</Link>
      <section class="panel ann-form-panel" aria-labelledby="ann-form-title">
        <div class="ann-form-header"><div class="ann-detail-icon"><FileText :size="24" aria-hidden="true"/></div><div><p class="ann-kicker">{{ edit ? 'PERBARUI DRAFT' : 'DRAFT BARU' }}</p><h2 id="ann-form-title">{{ edit ? 'Edit isi pengumuman' : 'Susun informasi untuk audiens' }}</h2><p>Simpan sebagai draft terlebih dahulu. Penerima belum melihat draft.</p></div></div>
        <form novalidate @submit.prevent="submit" class="ann-editor">
          <label class="ann-field">Judul pengumuman <span aria-hidden="true">*</span>
            <input v-model.trim="form.title" type="text" minlength="5" maxlength="160" required :aria-invalid="Boolean(form.errors.title)" placeholder="Contoh: Jadwal kegiatan sekolah" />
            <small>{{ form.title.length }}/160 karakter</small><small v-if="form.errors.title" role="alert" class="ann-error">{{ form.errors.title }}</small>
          </label>
          <label class="ann-field">Isi pengumuman <span aria-hidden="true">*</span>
            <textarea v-model="form.content" rows="9" minlength="10" maxlength="10000" required :aria-invalid="Boolean(form.errors.content)" placeholder="Tuliskan informasi penting dengan jelas: apa, kapan, di mana, dan tindak lanjut yang diperlukan." />
            <small>Gunakan paragraf biasa; teks ditampilkan sebagai teks, bukan HTML. {{ form.content.length }}/10000 karakter.</small><small v-if="form.errors.content" role="alert" class="ann-error">{{ form.errors.content }}</small>
          </label>
          <fieldset class="ann-recipient"><legend>Sasaran pengumuman</legend>
            <template v-if="!edit">
              <label class="ann-field">Cakupan <span aria-hidden="true">*</span><select v-model="form.target_scope" @change="form.class_uuid=''" required>
                <option v-if="options.can_school" value="SCHOOL">Seluruh sekolah</option><option v-if="options.classes.length" value="CLASS">Kelas tertentu</option>
              </select></label>
              <label v-if="form.target_scope === 'CLASS'" class="ann-field">Kelas tujuan <span aria-hidden="true">*</span><select v-model="form.class_uuid" required :aria-invalid="Boolean(form.errors.class_uuid)"><option value="">Pilih kelas</option><option v-for="klass in options.classes" :key="klass.uuid" :value="klass.uuid">{{ klass.name }} ({{ klass.code }})</option></select><small v-if="form.errors.class_uuid" role="alert" class="ann-error">{{ form.errors.class_uuid }}</small></label>
            </template>
            <div v-else class="ann-note"><ShieldCheck :size="17" aria-hidden="true"/> Cakupan terkunci setelah draft dibuat: {{ original.scope.label }}{{ original.school_class ? ` · ${original.school_class.name}` : '' }}.</div>
            <fieldset class="ann-roles"><legend>Kelompok penerima <span aria-hidden="true">*</span></legend><p>Pilih kelompok role yang benar. Untuk cakupan kelas, hanya anggota kelas yang relevan yang akan menerima.</p><div class="ann-role-grid"><label v-for="role in options.roles" :key="role.name" class="ann-role-option"><input v-model="form.target_roles" type="checkbox" :value="role.name"/><span>{{ role.label }}</span></label></div><small v-if="form.errors.target_roles" role="alert" class="ann-error">{{ form.errors.target_roles }}</small></fieldset>
          </fieldset>
          <label class="ann-field">Tanggal berakhir (WIB, opsional)<input v-model="form.expired_at" type="datetime-local" :aria-invalid="Boolean(form.errors.expired_at)"/><small>Harus setelah waktu publikasi saat pengumuman diterbitkan.</small><small v-if="form.errors.expired_at" role="alert" class="ann-error">{{ form.errors.expired_at }}</small></label>
          <p v-if="clientError || form.errors.announcement" class="ann-error" role="alert">{{ clientError || form.errors.announcement }}</p>
          <div class="ann-note"><Info :size="17" aria-hidden="true"/> Draft tidak dikirim sebagai notifikasi WhatsApp. Publikasi dilakukan melalui tindakan terpisah setelah detail diperiksa.</div>
          <div class="ann-editor-footer"><Link :href="back" class="button button-secondary">Batal</Link><button type="submit" class="button button-primary" :disabled="form.processing"><Save :size="17" aria-hidden="true"/> {{ form.processing ? 'Menyimpan…' : edit ? 'Simpan perubahan' : 'Simpan draft' }}</button></div>
        </form>
      </section>
    </div>
  </AppLayout>
</template>
