<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import RemoteOptionSelect from '../../Components/MasterData/RemoteOptionSelect.vue'
import { UsersRound, Search, Plus, Pencil, LockKeyhole, UserCheck, UserX,
  CalendarCheck2, Link2, GraduationCap, KeyRound, X, LoaderCircle,
  Info, AlertCircle, ArrowRight, ChevronLeft, ChevronRight, ShieldCheck } from 'lucide-vue-next'

const props = defineProps({ master: { type: Object, required: true } })
const page = usePage()
const selfUuid = computed(() => page.props.auth?.user?.uuid)
const kind = computed(() => props.master.filters.kind)
const selected = ref(null)
const editorOpen = ref(false)
const editMode = ref('create')
const specialMode = ref('')
const operationError = ref('')
const editorDialog = ref(null)
const specialDialog = ref(null)
let returnFocus = null
const statusText = value => ({ ACTIVE: 'Aktif', INACTIVE: 'Tidak aktif', ARCHIVED: 'Diarsipkan', GRADUATED: 'Lulus', TRANSFERRED: 'Pindah', RESIGNED: 'Mengundurkan diri', RETIRED: 'Pensiun', REGISTERED: 'Terdaftar' }[value] ?? value ?? '—')
const focusDialog = dialog => nextTick(() => dialog.value?.querySelector('input:not([type=hidden]), select, textarea, button')?.focus())
const trapFocus = (event, dialog) => {
  if (event.key !== 'Tab' || !dialog) return
  const items = [...dialog.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]')]
  if (!items.length) return
  const first = items[0], last = items[items.length - 1]
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}
const restoreFocus = () => nextTick(() => returnFocus?.focus?.())
const search = ref(props.master.filters.search || '')
const perPage = ref(props.master.filters.per_page || 15)
watch(() => props.master.filters, filters => {
  search.value = filters.search || ''
  perPage.value = filters.per_page || 15
})

const form = useForm({
  name: '', username: '', email: '', phone: '', password: '', password_confirmation: '',
  roles: [], user_uuid: '', nis: '', nisn: '', gender: '', birth_place: '', birth_date: '',
  address: '', admission_date: '', graduation_date: '', status: '', nip: '',
  employee_number: '', employment_status: '', join_date: '', occupation: '',
  year_ref: '', teacher_uuid: '', code: '', grade_level: '', major: '',
  start_date: '', end_date: '',
})
const auxiliary = useForm({
  password: '', password_confirmation: '', student_uuid: '', class_uuid: '', joined_at: '',
  relationship: 'FATHER', is_primary_contact: false, receive_notification: true,
})
const choice = (key) => props.master.options?.[key] || []
const remoteResource = key => ({ user_uuid: 'users', year_ref: 'years', teacher_uuid: 'teachers', class_uuid: 'classes', student_uuid: 'students' })[key]
const text = (key, label, required = true) => ({ key, label, type: 'text', required })
const date = (key, label, required = true) => ({ key, label, type: 'date', required })
const select = (key, label, options, required = true) => ({ key, label, type: 'select', options, required })
const fields = computed(() => {
  const creating = editMode.value === 'create'
  const identity = creating ? [select('user_uuid', 'Akun pengguna', choice('users'))] : []
  const gender = select('gender', 'Jenis kelamin', choice('genders'))
  const birthplace = text('birth_place', 'Tempat lahir')
  const birthdate = date('birth_date', 'Tanggal lahir')
  const address = { key: 'address', label: 'Alamat', type: 'textarea', required: true }
  const status = (key) => select('status', 'Status', choice(key))
  switch (kind.value) {
    case 'users': return [
      text('name', 'Nama lengkap'), text('username', 'Username'),
      { key: 'email', label: 'Email', type: 'email', required: true },
      text('phone', 'Nomor telepon', false),
      ...(creating ? [
        { key: 'password', label: 'Kata sandi (minimal 12 karakter)', type: 'password', required: true },
        { key: 'password_confirmation', label: 'Konfirmasi kata sandi', type: 'password', required: true },
      ] : []),
      ...(props.master.can.assign_role && (creating || selected.value?.ref !== selfUuid.value)
        ? [{ key: 'roles', label: 'Role pengguna', type: 'roles', options: choice('roles'), required: true }] : []),
    ]
    case 'students': return [
      ...identity, text('nis', 'NIS'), text('nisn', 'NISN', false), gender, birthplace, birthdate,
      address, date('admission_date', 'Tanggal masuk'), date('graduation_date', 'Tanggal lulus', false),
      status('student_statuses'),
    ]
    case 'teachers': return [
      ...identity, text('nip', 'NIP'), text('employee_number', 'Nomor pegawai'),
      gender, birthplace, birthdate, address,
      select('employment_status', 'Status kepegawaian', choice('employment_statuses')),
      date('join_date', 'Tanggal bergabung'), status('teacher_statuses'),
    ]
    case 'parents': return [...identity, text('occupation', 'Pekerjaan'), address]
    case 'classes': return [
      select('year_ref', 'Tahun ajaran', choice('years')),
      select('teacher_uuid', 'Wali kelas (opsional)', choice('teachers'), false),
      text('code', 'Kode kelas'), text('name', 'Nama kelas'), text('grade_level', 'Tingkat'),
      text('major', 'Jurusan', false), status('class_statuses'),
    ]
    case 'years': return [text('name', 'Nama tahun ajaran'), date('start_date', 'Tanggal mulai'),
      date('end_date', 'Tanggal selesai')]
    default: return []
  }
})
const selectedTab = computed(() => props.master.tabs.find(t => t.key === kind.value))
const submitFilters = () => router.get('/master-data', {
  kind: kind.value, search: search.value.trim() || undefined, per_page: perPage.value,
}, { preserveState: true, replace: true, preserveScroll: true })
const openEditor = (record = null) => {
  if (!(record ? props.master.can.update : props.master.can.create)) return
  form.reset(); form.clearErrors(); operationError.value = ''
  selected.value = record
  editMode.value = record ? 'edit' : 'create'
  if (record) Object.assign(form, record.fields || {})
  else {
    if (kind.value === 'students' || kind.value === 'teachers' || kind.value === 'classes') form.status = 'ACTIVE'
    if (kind.value === 'teachers') form.employment_status = 'PERMANENT'
  }
  returnFocus = document.activeElement
  editorOpen.value = true
  focusDialog(editorDialog)
}
const closeEditor = () => { if (!form.processing) { editorOpen.value = false; form.clearErrors(); form.password = ''; form.password_confirmation = ''; restoreFocus() } }
const save = () => {
  operationError.value = ''
  const keys = fields.value.map(field => field.key)
  form.transform(data => Object.fromEntries(keys.map(key => [key, data[key]])))
  const path = editMode.value === 'create' ? `/master-data/${kind.value}`
    : `/master-data/${kind.value}/${selected.value.ref}`
  form.submit(editMode.value === 'create' ? 'post' : 'patch', path, {
    preserveScroll: true, onSuccess: closeEditor,
    onError: errors => { operationError.value = Object.values(errors)[0] || 'Periksa kembali kolom formulir.' },
  })
}
const openSpecial = (record, mode) => {
  returnFocus = document.activeElement
  selected.value = record; specialMode.value = mode; operationError.value = ''
  auxiliary.reset(); auxiliary.clearErrors()
  if (mode === 'enroll') auxiliary.joined_at = ''
  focusDialog(specialDialog)
}
const closeSpecial = () => { if (!auxiliary.processing) { specialMode.value = ''; auxiliary.clearErrors(); auxiliary.password = ''; auxiliary.password_confirmation = ''; restoreFocus() } }
const specialFields = computed(() => {
  if (specialMode.value === 'password') return [
    { key: 'password', label: 'Kata sandi baru', type: 'password', required: true },
    { key: 'password_confirmation', label: 'Konfirmasi kata sandi baru', type: 'password', required: true },
  ]
  if (specialMode.value === 'enroll') return [
    select('class_uuid', 'Kelas tujuan', choice('classes')),
    date('joined_at', 'Tanggal masuk kelas'),
  ]
  if (specialMode.value === 'link') return [
    select('student_uuid', 'Siswa', choice('students')),
    select('relationship', 'Hubungan', [
      { value: 'FATHER', label: 'Ayah' }, { value: 'MOTHER', label: 'Ibu' },
      { value: 'GUARDIAN', label: 'Wali' },
    ]),
    { key: 'is_primary_contact', label: 'Kontak utama', type: 'checkbox' },
    { key: 'receive_notification', label: 'Terima notifikasi', type: 'checkbox' },
  ]
  return []
})
const submitSpecial = () => {
  operationError.value = ''
  const urls = {
    password: `/master-data/users/${selected.value.ref}/reset-password`,
    enroll: `/master-data/students/${selected.value.ref}/enrollments`,
    link: `/master-data/parents/${selected.value.ref}/students`,
  }
  const keys = specialFields.value.map(f => f.key)
  auxiliary.transform(data => Object.fromEntries(keys.map(key => [key, data[key]])))
  auxiliary.post(urls[specialMode.value], {
    preserveScroll: true, onSuccess: closeSpecial,
    onError: errors => { operationError.value = Object.values(errors)[0] || 'Permintaan belum dapat diproses.' },
    onFinish: () => { auxiliary.password = ''; auxiliary.password_confirmation = '' },
  })
}
const changeUserState = (row) => {
  const next = row.status !== 'ACTIVE'
  const verb = next ? 'aktifkan' : 'nonaktifkan'
  if (!window.confirm(`Yakin ingin ${verb} akun ${row.title}?`)) return
  operationError.value = ''
  router.patch(`/master-data/users/${row.ref}/state`, { active: next }, {
    preserveScroll: true, onError: errors => { operationError.value = Object.values(errors)[0] || 'Gagal mengubah status akun.' },
  })
}
const activateYear = row => {
  if (!window.confirm(`Aktifkan ${row.title} dan nonaktifkan tahun ajaran lainnya?`)) return
  operationError.value = ''
  router.post(`/master-data/years/${row.ref}/activate`, {}, {
    preserveScroll: true, onError: errors => { operationError.value = Object.values(errors)[0] || 'Gagal mengaktifkan tahun ajaran.' },
  })
}
const visibleActions = row => props.master.can.update ||
  (kind.value === 'users' && (props.master.can.activate_user || props.master.can.deactivate_user || props.master.can.reset_password)) ||
  (kind.value === 'years' && props.master.can.activate_year) ||
  (kind.value === 'students' && props.master.can.enroll) ||
  (kind.value === 'parents' && props.master.can.link_parent)
</script>

<template>
  <AppLayout title="Master Data" subtitle="Kelola identitas dan referensi akademik sesuai hak akses.">
    <div class="md-page">
      <section class="md-intro" aria-label="Informasi pengelolaan">
        <span class="md-intro-icon"><ShieldCheck :size="22" aria-hidden="true" /></span>
        <div><strong>Perubahan tercatat dan diperiksa di server</strong><p>Role, penonaktifan akun, penempatan kelas, serta tahun ajaran memiliki validasi terpisah. Data keuangan tidak dapat diubah dari halaman ini.</p></div>
      </section>
      <p v-if="operationError" class="md-alert" role="alert"><AlertCircle :size="17" aria-hidden="true" /> {{ operationError }}</p>
      <nav class="md-tabs" aria-label="Kategori master data">
        <Link v-for="tab in master.tabs" :key="tab.key" :href="`/master-data?kind=${tab.key}`"
          :class="['md-tab', { 'md-tab-active': tab.key === kind }]" :aria-current="tab.key === kind ? 'page' : undefined">
          {{ tab.label }}
        </Link>
      </nav>
      <section class="md-panel" aria-label="Daftar master data">
        <header class="md-panel-head">
          <div><p class="md-kicker">DATA SEKOLAH</p><h2>{{ selectedTab?.label ?? 'Master data' }}</h2><p>{{ master.records.total }} data dalam akses Anda</p></div>
          <button v-if="master.can.create" type="button" class="button button-primary" @click="openEditor()"><Plus :size="17" aria-hidden="true" /> Tambah {{ selectedTab?.label }}</button>
        </header>
        <form class="md-filter" role="search" @submit.prevent="submitFilters">
          <label class="md-search"><span>Pencarian</span><span class="md-input-icon"><Search :size="17" aria-hidden="true" /><input v-model="search" type="search" maxlength="90" placeholder="Cari nama, kode, atau identitas" /></span></label>
          <label class="md-per-page"><span>Tampilkan</span><select v-model.number="perPage" @change="submitFilters"><option :value="10">10 data</option><option :value="15">15 data</option><option :value="25">25 data</option></select></label>
          <button type="submit" class="button button-secondary">Terapkan</button>
        </form>
        <div v-if="!master.records.data.length" class="md-empty"><UsersRound :size="28" aria-hidden="true" /><strong>Tidak ada data yang cocok</strong><p>Ubah kata kunci atau pilih kategori lain.</p></div>
        <div v-else class="md-table-wrap"><table class="md-table"><thead><tr><th scope="col">Identitas</th><th scope="col">Status</th><th v-if="master.records.data.some(visibleActions)" scope="col">Tindakan</th></tr></thead>
          <tbody><tr v-for="row in master.records.data" :key="row.ref">
            <td><strong>{{ row.title }}</strong><small>{{ row.subtitle }}</small></td>
            <td><span :class="['md-status', { 'md-status-active': row.status === 'ACTIVE', 'md-status-inactive': ['INACTIVE','ARCHIVED'].includes(row.status) }]">{{ statusText(row.status) }}</span></td>
            <td v-if="master.records.data.some(visibleActions)"><div class="md-actions">
              <button v-if="master.can.update" class="md-action" type="button" :aria-label="`Edit ${row.title}`" @click="openEditor(row)"><Pencil :size="15" aria-hidden="true" /> Edit</button>
              <button v-if="kind === 'users' && (row.status === 'ACTIVE' ? master.can.deactivate_user : master.can.activate_user)" class="md-action" type="button" @click="changeUserState(row)"><component :is="row.status === 'ACTIVE' ? UserX : UserCheck" :size="15" aria-hidden="true" /> {{ row.status === 'ACTIVE' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
              <button v-if="kind === 'users' && master.can.reset_password && row.ref !== selfUuid" class="md-action" type="button" @click="openSpecial(row, 'password')"><KeyRound :size="15" aria-hidden="true" /> Reset sandi</button>
              <button v-if="kind === 'years' && master.can.activate_year && row.status !== 'ACTIVE'" class="md-action" type="button" @click="activateYear(row)"><CalendarCheck2 :size="15" aria-hidden="true" /> Aktifkan</button>
              <button v-if="kind === 'students' && master.can.enroll" class="md-action" type="button" @click="openSpecial(row, 'enroll')"><GraduationCap :size="15" aria-hidden="true" /> Tempatkan kelas</button>
              <button v-if="kind === 'parents' && master.can.link_parent" class="md-action" type="button" @click="openSpecial(row, 'link')"><Link2 :size="15" aria-hidden="true" /> Tautkan siswa</button>
            </div></td>
          </tr></tbody></table></div>
        <footer class="md-pagination"><span>Halaman {{ master.records.current_page }} dari {{ master.records.last_page }}</span><div><Link v-if="master.records.prev_page_url" :href="master.records.prev_page_url" class="button button-secondary"><ChevronLeft :size="15" /> Sebelumnya</Link><Link v-if="master.records.next_page_url" :href="master.records.next_page_url" class="button button-secondary">Berikutnya <ChevronRight :size="15" /></Link></div></footer>
      </section>
      <div v-if="editorOpen" class="md-overlay" role="presentation" @click.self="closeEditor">
        <section ref="editorDialog" class="md-dialog" role="dialog" aria-modal="true" aria-labelledby="md-editor-title" @keydown.esc="closeEditor" @keydown="trapFocus($event, editorDialog)">
          <header><div><p class="md-kicker">{{ editMode === 'create' ? 'DATA BARU' : 'PERBARUI DATA' }}</p><h2 id="md-editor-title">{{ editMode === 'create' ? 'Tambah' : 'Edit' }} {{ selectedTab?.label }}</h2></div><button class="md-close" type="button" aria-label="Tutup formulir" :disabled="form.processing" @click="closeEditor"><X :size="21" /></button></header>
          <form class="md-dialog-content" @submit.prevent="save" novalidate>
            <p v-if="kind === 'users'" class="md-form-help">Role mengendalikan izin aplikasi. Gunakan kata sandi sementara yang kuat dan bagikan melalui kanal aman.</p>
            <div class="md-fields"><div v-for="field in fields" :key="field.key" class="md-field" :class="{'md-field-wide': field.type === 'textarea' || field.type === 'roles'}">
              <label :for="`md-${field.key}`">{{ field.label }} <span v-if="field.required" aria-hidden="true">*</span></label>
              <template v-if="field.type === 'roles'"><div :id="`md-${field.key}`" class="md-roles"><label v-for="option in field.options" :key="option.value"><input v-model="form.roles" type="checkbox" :value="option.value" />{{ option.label }}</label></div></template>
              <RemoteOptionSelect v-else-if="field.type === 'select' && remoteResource(field.key)"
                :id="`md-${field.key}`" v-model="form[field.key]" :resource="remoteResource(field.key)" :context="kind"
                :required="field.required" :invalid="!!form.errors[field.key]" />
              <select v-else-if="field.type === 'select'" :id="`md-${field.key}`" v-model="form[field.key]" :required="field.required" :aria-invalid="!!form.errors[field.key]"><option value="">{{ field.required ? 'Pilih opsi' : 'Tidak ditentukan' }}</option><option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option></select>
              <textarea v-else-if="field.type === 'textarea'" :id="`md-${field.key}`" v-model="form[field.key]" rows="3" :required="field.required" :aria-invalid="!!form.errors[field.key]" />
              <input v-else :id="`md-${field.key}`" v-model="form[field.key]" :type="field.type" :required="field.required" :aria-invalid="!!form.errors[field.key]" :autocomplete="field.type === 'password' ? 'new-password' : 'off'" />
              <small v-if="form.errors[field.key]" class="md-field-error" role="alert">{{ form.errors[field.key] }}</small>
            </div></div>
            <div class="md-dialog-actions"><button type="button" class="button button-secondary" :disabled="form.processing" @click="closeEditor">Batal</button><button type="submit" class="button button-primary" :disabled="form.processing"><LoaderCircle v-if="form.processing" :size="17" class="md-spin" />{{ form.processing ? 'Menyimpan...' : 'Simpan perubahan' }}</button></div>
          </form>
        </section>
      </div>
      <div v-if="specialMode" class="md-overlay" role="presentation" @click.self="closeSpecial">
        <section ref="specialDialog" class="md-dialog md-dialog-narrow" role="dialog" aria-modal="true" aria-labelledby="md-special-title" @keydown.esc="closeSpecial" @keydown="trapFocus($event, specialDialog)"><header><div><p class="md-kicker">TINDAKAN TERKONTROL</p><h2 id="md-special-title">{{ { password: 'Reset kata sandi', enroll: 'Penempatan kelas', link: 'Tautkan orang tua' }[specialMode] }}</h2><p class="md-form-help">{{ selected?.title }}</p></div><button type="button" class="md-close" :disabled="auxiliary.processing" aria-label="Tutup formulir" @click="closeSpecial"><X :size="21" /></button></header>
          <form class="md-dialog-content" @submit.prevent="submitSpecial"><p class="md-form-help">{{ specialMode === 'enroll' ? 'Siswa tidak boleh memiliki dua enrollment aktif pada tahun ajaran yang sama.' : specialMode === 'password' ? 'Kata sandi baru harus dibagikan melalui kanal aman, tidak disimpan di audit log.' : 'Pilih siswa yang terhubung dan tentukan kontak utama.' }}</p>
            <div class="md-fields"><div v-for="field in specialFields" :key="field.key" class="md-field md-field-wide"><label :for="`sp-${field.key}`">{{ field.label }}</label>
              <input v-if="field.type === 'checkbox'" :id="`sp-${field.key}`" v-model="auxiliary[field.key]" type="checkbox" />
              <RemoteOptionSelect v-else-if="field.type === 'select' && remoteResource(field.key)"
                  :id="`sp-${field.key}`" v-model="auxiliary[field.key]" :resource="remoteResource(field.key)" :context="kind"
                  :required="true" :invalid="!!auxiliary.errors[field.key]" />
              <select v-else-if="field.type === 'select'" :id="`sp-${field.key}`" v-model="auxiliary[field.key]" required><option value="">Pilih opsi</option><option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option></select>
              <input v-else :id="`sp-${field.key}`" v-model="auxiliary[field.key]" :type="field.type" required :autocomplete="field.type === 'password' ? 'new-password' : 'off'" />
              <small v-if="auxiliary.errors[field.key]" class="md-field-error" role="alert">{{ auxiliary.errors[field.key] }}</small>
            </div></div>
            <div class="md-dialog-actions"><button type="button" class="button button-secondary" :disabled="auxiliary.processing" @click="closeSpecial">Batal</button><button type="submit" class="button button-primary" :disabled="auxiliary.processing">{{ auxiliary.processing ? 'Memproses...' : 'Konfirmasi' }} <ArrowRight :size="15" /></button></div>
          </form>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.md-page{display:grid;gap:19px;max-width:1400px;margin-inline:auto}.md-intro{display:flex;gap:15px;padding:17px 20px;background:#eef8f8;border:1px solid #cbe6e5;border-radius:16px;color:#1e5966}.md-intro-icon{display:grid;place-items:center;background:#d6f0eb;width:42px;height:42px;flex-shrink:0;border-radius:12px}.md-intro strong{font-size:13px}.md-intro p,.md-form-help{color:#62788a;font-size:12px;line-height:1.65;margin:5px 0 0}.md-tabs{display:flex;gap:7px;flex-wrap:wrap;border-bottom:1px solid #dbe5ef;padding-bottom:12px}.md-tab{padding:10px 14px;border-radius:10px;font-size:13px;font-weight:700;color:#64768c}.md-tab:hover{background:#eaf2f5}.md-tab-active{background:#e4f4f4;color:#087684}.md-panel{background:#fff;border:1px solid #e0e8f0;border-radius:18px;padding:25px}.md-panel-head{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}.md-panel-head h2{font-size:22px;margin:5px 0}.md-panel-head p:not(.md-kicker){font-size:13px;color:#6f8298;margin:0}.md-kicker{font-size:10px;color:#087f8c;font-weight:800;letter-spacing:.12em;margin:0}.md-filter{display:flex;gap:11px;align-items:end;flex-wrap:wrap;margin:24px 0}.md-search{flex:1;min-width:200px}.md-search,.md-per-page,.md-field{display:grid;gap:8px;font-size:12px;font-weight:700;color:#314860}.md-input-icon{position:relative}.md-input-icon svg{position:absolute;left:13px;top:13px;color:#698098}.md-input-icon input{padding-left:40px!important}.md-filter input,.md-filter select,.md-field input:not([type=checkbox]),.md-field textarea,.md-field select{width:100%;min-height:43px;border:1px solid #cedbe7;border-radius:10px;background:#fff;color:#1f3952;padding:10px 12px;font:inherit;font-size:13px}.md-filter select{min-width:120px}.md-table-wrap{overflow-x:auto}.md-table{width:100%;border-collapse:collapse;text-align:left;font-size:13px}.md-table th{background:#f5f8fb;color:#6c7c90;font-size:11px;font-weight:800}.md-table :is(th,td){padding:16px 13px;border-bottom:1px solid #e6edf3;text-align:left;vertical-align:middle}.md-table td strong{display:block;color:#183048}.md-table td small{display:block;color:#77899b;font-size:11px;margin-top:4px}.md-status{display:inline-flex;border-radius:25px;background:#eaf1fb;color:#365d8c;padding:6px 10px;font-size:10px;font-weight:800}.md-status-active{background:#dcf5ea;color:#16734e}.md-status-inactive{background:#f1f2f5;color:#667589}.md-actions{display:flex;align-items:center;flex-wrap:wrap;gap:6px}.md-action{border:1px solid #e0e8ef;border-radius:8px;background:#fff;display:inline-flex;gap:6px;align-items:center;padding:8px 10px;color:#35647d;font-size:11px;font-weight:700;white-space:nowrap;cursor:pointer}.md-action:hover{background:#f0f8fa}.md-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:18px;color:#667b91;font-size:12px}.md-pagination>div{display:flex;gap:8px}.md-empty{display:grid;justify-items:center;padding:45px 16px;gap:10px;background:#f7fafc;border:1px dashed #d7e4ec;border-radius:13px;color:#71869c;text-align:center}.md-empty strong{color:#38566d}.md-empty p{font-size:12px;margin:0}.md-overlay{position:fixed;inset:0;background:#10223799;z-index:80;display:flex;justify-content:center;align-items:center;padding:20px}.md-dialog{width:min(820px,100%);max-height:90vh;overflow-y:auto;border-radius:19px;background:#fff;box-shadow:0 26px 90px #14284040}.md-dialog-narrow{width:min(520px,100%)}.md-dialog header{display:flex;align-items:start;justify-content:space-between;gap:12px;padding:24px 27px 16px;border-bottom:1px solid #e8eef4}.md-dialog header h2{font-size:23px;margin:7px 0 0}.md-dialog-content{padding:24px 27px;display:grid;gap:23px}.md-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.md-field-wide{grid-column:1/-1}.md-field label{color:#39526a}.md-field label span{color:#ae304a}.md-field input[aria-invalid=true],.md-field select[aria-invalid=true]{border-color:#b93c4d}.md-roles{display:flex;gap:12px;flex-wrap:wrap}.md-roles label{display:flex;align-items:center;gap:8px;padding:10px;border:1px solid #e1e9f1;border-radius:10px;font-size:12px}.md-field-error{font-size:11px;color:#b1334d}.md-dialog-actions{display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #ebf0f5;padding-top:19px}.md-close{background:transparent;border:0;color:#657a8c;padding:8px;cursor:pointer}.md-alert{display:flex;gap:9px;align-items:center;color:#aa3341;background:#fff1f2;border:1px solid #ffced4;padding:13px 15px;border-radius:11px;font-size:13px}.md-spin{animation:md-spin 1s linear infinite}@keyframes md-spin{to{transform:rotate(360deg)}}@media(max-width:680px){.md-panel{padding:16px}.md-panel-head>.button{width:100%}.md-fields{grid-template-columns:1fr}.md-dialog header,.md-dialog-content{padding:17px}.md-tabs{overflow-x:auto;flex-wrap:nowrap}.md-tab{white-space:nowrap}.md-intro{padding:13px}.md-filter .button{width:100%}}@media(prefers-reduced-motion:reduce){.md-spin{animation:none}}
</style>
