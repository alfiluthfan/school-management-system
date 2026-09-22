<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Check, CircleAlert, LockKeyhole, LoaderCircle, UserPlus, X } from 'lucide-vue-next'

const props = defineProps({
  open: { type: Boolean, required: true },
  initialRole: { type: String, default: '' },
  roles: { type: Array, default: () => [] },
  options: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['close'])
const dialog = ref(null)
const form = useForm({
  role: '', name: '', username: '', email: '', phone: '', password: '', password_confirmation: '',
  profile: {},
})
const profileRole = computed(() => ['student', 'teacher', 'parent'].includes(form.role))
const roleName = computed(() => props.roles.find(r => r.value === form.role)?.label || '')
const opts = key => props.options?.[key] || []
const input = (key, label, required = true, type = 'text') => ({ key, label, required, type })
const select = (key, label, list) => ({ key, label, required: true, type: 'select', options: opts(list) })
const common = [input('gender', 'Jenis kelamin'), input('birth_place', 'Tempat lahir'),
  input('birth_date', 'Tanggal lahir', true, 'date'), input('address', 'Alamat')]
const fields = computed(() => {
  if (form.role === 'student') return [input('nis', 'NIS'), input('nisn', 'NISN', false),
    select('gender', 'Jenis kelamin', 'genders'), common[1], common[2], common[3],
    input('admission_date', 'Tanggal masuk sekolah', true, 'date'),
    input('graduation_date', 'Tanggal lulus', false, 'date')]
  if (form.role === 'teacher') return [input('nip', 'NIP'), input('employee_number', 'Nomor pegawai'),
    select('gender', 'Jenis kelamin', 'genders'), common[1], common[2], common[3],
    select('employment_status', 'Status kepegawaian', 'employment_statuses'),
    input('join_date', 'Tanggal bergabung', true, 'date')]
  if (form.role === 'parent') return [input('occupation', 'Pekerjaan'), common[3]]
  return []
})
const initialProfile = role => role === 'student' ? { status: 'ACTIVE' } :
  role === 'teacher' ? { status: 'ACTIVE', employment_status: 'PERMANENT' } : {}
const setRole = value => {
  if (!props.roles.some(r => r.value === value)) return
  form.role = value
  form.profile = initialProfile(value)
  form.clearErrors()
}
watch(() => props.open, value => {
  if (!value) return
  form.reset(); form.clearErrors(); form.profile = {}
  if (props.initialRole && props.roles.some(r => r.value === props.initialRole)) setRole(props.initialRole)
  nextTick(() => dialog.value?.querySelector('input[type=radio]:checked, input:not([type=hidden]), button')?.focus())
})
const close = () => {
  if (form.processing) return
  form.reset(); form.clearErrors(); emit('close')
}
const submit = () => {
  if (!form.role) { form.setError('role', 'Pilih satu role terlebih dahulu.'); return }
  const role = form.role
  form.transform(values => ({
    role, name: values.name, username: values.username, email: values.email, phone: values.phone || null,
    password: values.password, password_confirmation: values.password_confirmation,
    ...(profileRole.value ? {
      profile: {
        ...Object.fromEntries(fields.value.map(field => [field.key, values.profile[field.key] || null])),
        ...(role === 'student' || role === 'teacher' ? { status: 'ACTIVE' } : {}),
      },
    } : {}),
  }))
  form.post('/master-data/accounts', {
    preserveScroll: true,
    onSuccess: () => { form.reset(); form.clearErrors(); emit('close') },
    onFinish: () => { form.password = ''; form.password_confirmation = '' },
  })
}
const trap = event => {
  if (event.key !== 'Tab' || !dialog.value) return
  const controls = [...dialog.value.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled])')]
  if (!controls.length) return
  if (event.shiftKey && document.activeElement === controls[0]) { event.preventDefault(); controls.at(-1).focus() }
  else if (!event.shiftKey && document.activeElement === controls.at(-1)) { event.preventDefault(); controls[0].focus() }
}
</script>

<template>
  <div v-if="open" class="onboard-backdrop" @click.self="close">
    <section ref="dialog" class="onboard-dialog" role="dialog" aria-modal="true" aria-labelledby="onboard-title" @keydown.esc="close" @keydown="trap">
      <header class="onboard-head">
        <div><p class="onboard-kicker">SATU AKUN · SATU ROLE · SATU PROSES</p><h2 id="onboard-title">Tambah pengguna sekolah</h2><p>Buat akun dan profil terkait sekaligus. Nama lengkap cukup diisi sekali.</p></div>
        <button type="button" class="onboard-close" :disabled="form.processing" aria-label="Tutup" @click="close"><X :size="21" /></button>
      </header>
      <form class="onboard-body" @submit.prevent="submit" novalidate>
        <section class="onboard-section" aria-labelledby="onboard-role-title">
          <h3 id="onboard-role-title"><span>1</span> Pilih satu role</h3>
          <p>Role menentukan profil tambahan yang diperlukan. Tidak ada pilihan multi-role.</p>
          <div class="onboard-roles" role="radiogroup" aria-label="Role pengguna">
            <label v-for="option in roles" :key="option.value" :class="['onboard-role', { 'is-selected': form.role === option.value }]">
              <input :checked="form.role === option.value" type="radio" name="onboard-role" :value="option.value" @change="setRole(option.value)" />
              {{ option.label }} <Check v-if="form.role === option.value" :size="15" />
            </label>
          </div>
          <small v-if="form.errors.role" class="onboard-error" role="alert">{{ form.errors.role }}</small>
        </section>
        <section v-if="form.role" class="onboard-section" aria-labelledby="onboard-identity-title">
          <h3 id="onboard-identity-title"><span>2</span> Identitas dan akun {{ roleName }}</h3>
          <p>Nama ini otomatis digunakan pada profil Siswa, Guru, atau Orang Tua.</p>
          <div class="onboard-grid">
            <label>Nama lengkap *<input v-model="form.name" maxlength="150" required autocomplete="name" :aria-invalid="!!form.errors.name" /><small v-if="form.errors.name" class="onboard-error">{{ form.errors.name }}</small></label>
            <label>Username *<input v-model="form.username" maxlength="80" required autocomplete="off" :aria-invalid="!!form.errors.username" /><small v-if="form.errors.username" class="onboard-error">{{ form.errors.username }}</small></label>
            <label>Email *<input v-model="form.email" type="email" maxlength="255" required autocomplete="off" :aria-invalid="!!form.errors.email" /><small v-if="form.errors.email" class="onboard-error">{{ form.errors.email }}</small></label>
            <label>Nomor telepon (opsional)<input v-model="form.phone" maxlength="25" autocomplete="off" /><small v-if="form.errors.phone" class="onboard-error">{{ form.errors.phone }}</small></label>
            <label>Kata sandi sementara *<input v-model="form.password" type="password" minlength="12" required autocomplete="new-password" /><small v-if="form.errors.password" class="onboard-error">{{ form.errors.password }}</small></label>
            <label>Konfirmasi kata sandi *<input v-model="form.password_confirmation" type="password" minlength="12" required autocomplete="new-password" /><small v-if="form.errors.password_confirmation" class="onboard-error">{{ form.errors.password_confirmation }}</small></label>
          </div>
          <p class="onboard-hint"><LockKeyhole :size="14" /> Bagikan kata sandi secara aman; jangan menyimpannya di keterangan atau catatan.</p>
        </section>
        <section v-if="profileRole" class="onboard-section" aria-labelledby="onboard-profile-title">
          <h3 id="onboard-profile-title"><span>3</span> Data khusus {{ roleName }}</h3>
          <p>Profil akan dibuat otomatis dan terhubung ke akun tadi. Status awal aktif.</p>
          <div class="onboard-grid">
            <label v-for="field in fields" :key="field.key" :class="{ 'onboard-wide': field.key === 'address' }">
              {{ field.label }} <span v-if="field.required">*</span>
              <select v-if="field.type === 'select'" v-model="form.profile[field.key]" :required="field.required" :aria-invalid="!!form.errors[`profile.${field.key}`]">
                <option value="">Pilih opsi</option><option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
              <textarea v-else-if="field.key === 'address'" v-model="form.profile[field.key]" rows="3" maxlength="1000" required :aria-invalid="!!form.errors[`profile.${field.key}`]" />
              <input v-else v-model="form.profile[field.key]" :type="field.type" :required="field.required" :aria-invalid="!!form.errors[`profile.${field.key}`]" autocomplete="off" />
              <small v-if="form.errors[`profile.${field.key}`]" class="onboard-error">{{ form.errors[`profile.${field.key}`] }}</small>
            </label>
          </div>
        </section>
        <p v-if="form.errors.profile || form.errors.roles" role="alert" class="onboard-notice"><CircleAlert :size="16" /> {{ form.errors.profile || form.errors.roles }}</p>
        <p v-if="form.role && !profileRole" class="onboard-note"><UserPlus :size="17" /> {{ roleName }} akan langsung dibuat sebagai akun dengan satu role. Tidak ada profil Guru atau Siswa tambahan.</p>
        <footer class="onboard-footer"><button type="button" class="onboard-secondary" :disabled="form.processing" @click="close">Batal</button><button type="submit" class="onboard-primary" :disabled="form.processing || !form.role"><LoaderCircle v-if="form.processing" :size="16" class="onboard-spin" /> {{ form.processing ? 'Menyimpan...' : 'Buat akun & profil' }}</button></footer>
      </form>
    </section>
  </div>
</template>

<style scoped>
.onboard-backdrop{position:fixed;inset:0;background:#102237ad;z-index:90;display:grid;place-items:center;padding:18px}.onboard-dialog{width:min(890px,100%);max-height:92vh;overflow-y:auto;background:#fff;border-radius:20px;box-shadow:0 24px 85px #0d25373d}.onboard-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:24px 28px;border-bottom:1px solid #dfebf1}.onboard-head h2{font-size:23px;margin:5px 0}.onboard-head p{font-size:12px;color:#587489;line-height:1.6;margin:5px 0 0}.onboard-kicker{font-size:10px!important;letter-spacing:.13em;font-weight:800;color:#0b7c85!important}.onboard-close{background:#fff;border:0;cursor:pointer;padding:8px;color:#536d82}.onboard-body{padding:25px 28px;display:grid;gap:23px}.onboard-section{border:1px solid #dfeaf1;border-radius:14px;padding:19px;display:grid;gap:12px}.onboard-section h3{font-size:15px;display:flex;gap:11px;align-items:center;margin:0;color:#233e56}.onboard-section h3 span{background:#d9f1f1;color:#057883;border-radius:100%;height:26px;width:26px;display:grid;place-items:center}.onboard-section>p{font-size:12px;color:#657d91;margin:0}.onboard-roles{display:flex;flex-wrap:wrap;gap:9px}.onboard-role{display:flex;align-items:center;gap:7px;border:1px solid #d3e2ec;border-radius:11px;padding:11px 13px;cursor:pointer;font-size:12px;font-weight:700}.onboard-role.is-selected{background:#e6f7f5;color:#076d75;border-color:#3c9da4}.onboard-role input{accent-color:#087a83}.onboard-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.onboard-grid label{display:grid;gap:7px;font-size:12px;font-weight:700;color:#334c63}.onboard-grid input,.onboard-grid select,.onboard-grid textarea{width:100%;min-height:42px;border:1px solid #cfdeea;border-radius:9px;padding:10px 12px;background:white;color:#1b374d;font:inherit;font-weight:400;font-size:13px}.onboard-grid [aria-invalid=true]{border-color:#c34051}.onboard-wide{grid-column:1/-1}.onboard-error{font-size:11px;color:#af2b46}.onboard-note,.onboard-notice,.onboard-hint{display:flex;gap:8px;align-items:center;background:#f0f9fc;padding:12px;border-radius:9px;font-size:12px;color:#356176}.onboard-notice{background:#fff0f1;color:#b03747}.onboard-footer{border-top:1px solid #e5edf3;padding-top:17px;display:flex;justify-content:flex-end;gap:9px}.onboard-footer button{border-radius:9px;min-height:42px;padding:9px 15px;font-size:12px;font-weight:750;cursor:pointer}.onboard-secondary{border:1px solid #d2e0ea;background:#fff;color:#29445c}.onboard-primary{border:1px solid #087883;background:#087883;color:#fff;display:flex;align-items:center;gap:8px}.onboard-footer button:disabled{opacity:.5;cursor:not-allowed}.onboard-spin{animation:onboard-rotate 1s linear infinite}@keyframes onboard-rotate{to{transform:rotate(360deg)}}@media(max-width:680px){.onboard-grid{grid-template-columns:1fr}.onboard-head,.onboard-body{padding:16px}.onboard-section{padding:13px}.onboard-dialog{max-height:96vh}.onboard-footer{flex-direction:column}.onboard-footer button{width:100%;justify-content:center}}@media(prefers-reduced-motion:reduce){.onboard-spin{animation:none}}
</style>
