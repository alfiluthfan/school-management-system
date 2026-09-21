<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { CalendarDays, ClipboardCheck, Clock3, Filter, RotateCcw, Search, Users, GraduationCap, Info, ChevronLeft, ChevronRight, MapPin, ArrowRight, AlertCircle, ListFilter } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import AttendanceGpsAction from '../../Components/Attendance/AttendanceGpsAction.vue'
import AttendanceStatusBadge from '../../Components/Attendance/AttendanceStatusBadge.vue'

const props = defineProps({ tabs: { type: Array, required: true }, attendance: { type: Object, required: true } })
const filters = reactive({ ...props.attendance.filters })
const filtering = ref(false)
const filterError = ref('')
watch(() => props.attendance.filters, (value) => Object.assign(filters, value))
const type = computed(() => props.attendance.type)
const studentMode = computed(() => type.value === 'students')
const summary = computed(() => props.attendance.summary)
const records = computed(() => props.attendance.records)
const selfToday = computed(() => props.attendance.own_today)
const hasSelfActions = computed(() => props.attendance.can.check_in || props.attendance.can.check_out)
const nextAction = computed(() => {
  if (!selfToday.value) return props.attendance.can.check_in ? 'in' : null
  if (selfToday.value.check_in_at && !selfToday.value.check_out_at && props.attendance.can.check_out) return 'out'
  return null
})
const statuses = [
  { key: 'present', name: 'Hadir', icon: ClipboardCheck },
  { key: 'late', name: 'Terlambat', icon: Clock3 },
  { key: 'sick', name: 'Sakit', icon: Info },
  { key: 'permission', name: 'Izin', icon: CalendarDays },
  { key: 'absent', name: 'Tidak hadir', icon: AlertCircle },
]
function dateLabel(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'Asia/Jakarta' })
    .format(new Date(`${value}T12:00:00+07:00`))
}
function timeLabel(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' }).format(new Date(value)) + ' WIB'
}
function applyFilters() {
  filterError.value = ''
  const query = { type: type.value }
  for (const [key, value] of Object.entries(filters)) {
    if (value !== '' && value !== null && value !== undefined) query[key] = value
  }
  // Other tab's filters must never follow the selected tab.
  if (studentMode.value) delete query.teacher_uuid
  else delete query.class_uuid
  router.get('/attendance', query, {
    preserveState: true, preserveScroll: true, replace: true,
    onStart: () => { filtering.value = true },
    onFinish: () => { filtering.value = false },
    onError: (errors) => { filterError.value = Object.values(errors)[0] ?? 'Filter tidak dapat diterapkan.' },
  })
}
function resetFilters() {
  filterError.value = ''
  router.get('/attendance', { type: type.value }, {
    preserveScroll: true, replace: true,
    onStart: () => { filtering.value = true },
    onFinish: () => { filtering.value = false },
  })
}
</script>

<template>
  <AppLayout title="Absensi" subtitle="Pantau catatan kehadiran sesuai hak akses. Check-in dan check-out hanya untuk akun sendiri.">
    <div class="attendance-page">
      <nav class="attendance-tabs" aria-label="Jenis absensi">
        <Link v-for="tab in tabs" :key="tab.type" :href="`/attendance?type=${tab.type}`" class="attendance-tab"
          :class="{ 'tab-active': type === tab.type }" :aria-current="type === tab.type ? 'page' : undefined">
          <GraduationCap v-if="tab.type === 'students'" :size="18" aria-hidden="true" />
          <Users v-else :size="18" aria-hidden="true" />{{ tab.label }}
        </Link>
      </nav>

      <section v-if="hasSelfActions" class="attendance-self panel" aria-labelledby="self-title">
        <div class="self-copy">
          <p class="eyebrow-line"><MapPin :size="16" aria-hidden="true" /> ABSENSI MANDIRI · {{ attendance.today_date }}</p>
          <h2 id="self-title">{{ selfToday?.check_out_at ? 'Absensi hari ini selesai' : selfToday?.check_in_at ? 'Sudah check-in' : 'Siap melakukan check-in?' }}</h2>
          <p v-if="selfToday?.check_out_at">Check-in {{ timeLabel(selfToday.check_in_at) }} dan check-out {{ timeLabel(selfToday.check_out_at) }} telah tercatat.</p>
          <p v-else-if="selfToday?.check_in_at">Tercatat pada {{ timeLabel(selfToday.check_in_at) }}. Check-out hanya tersedia sesuai jadwal sekolah.</p>
          <p v-else>Izinkan lokasi saat diminta browser. Sistem akan memeriksa jadwal dan radius sekolah sebelum menyimpan absensi.</p>
          <AttendanceStatusBadge v-if="selfToday" :status="selfToday.status" :label="selfToday.status_label" />
        </div>
        <div class="self-action">
          <AttendanceGpsAction v-if="nextAction" :key="`${type}-${nextAction}`" :kind="type" :action="nextAction" />
          <div v-else class="self-finished" role="status"><ClipboardCheck :size="22" aria-hidden="true" />{{ selfToday?.check_out_at ? 'Absensi hari ini sudah lengkap.' : 'Belum ada tindakan absensi yang tersedia.' }}</div>
        </div>
      </section>
      <div v-else class="attendance-notice" role="note"><Info :size="17" aria-hidden="true" />Halaman ini adalah pemantauan kehadiran. Check-in/check-out hanya tersedia untuk siswa atau guru yang bersangkutan.</div>

      <section class="attendance-summary" aria-label="Ringkasan catatan sesuai filter">
        <div class="attendance-summary-main"><span><ListFilter :size="18" aria-hidden="true" /> Catatan dalam filter</span><strong>{{ records.total.toLocaleString('id-ID') }}</strong><small>Bukan persentase kehadiran seluruh siswa/guru</small></div>
        <div v-for="item in statuses" :key="item.key" class="attendance-summary-item">
          <span><component :is="item.icon" :size="17" aria-hidden="true" /> {{ item.name }}</span>
          <strong>{{ (summary[item.key] ?? 0).toLocaleString('id-ID') }}</strong>
        </div>
      </section>

      <section class="panel attendance-history" aria-labelledby="history-title">
        <div class="attendance-head"><div><p class="eyebrow-line">DATA KEHADIRAN</p><h2 id="history-title">Riwayat {{ studentMode ? 'siswa' : 'guru' }}</h2><p>Filter dan pencarian diterapkan pada data yang boleh dilihat akun Anda.</p></div><span class="attendance-count">{{ records.total }} catatan</span></div>
        <form class="attendance-filters" @submit.prevent="applyFilters" aria-label="Filter absensi">
          <div class="filter-field filter-search"><label for="att-search">Nama atau {{ studentMode ? 'NIS' : 'NIP / nomor pegawai' }}</label><div class="search-wrap"><Search :size="16" aria-hidden="true" /><input id="att-search" v-model.trim="filters.search" type="search" maxlength="100" placeholder="Cari nama…" /></div></div>
          <div class="filter-field"><label for="att-from">Dari tanggal</label><input id="att-from" v-model="filters.from" type="date" :max="filters.to || undefined" /></div>
          <div class="filter-field"><label for="att-to">Sampai tanggal</label><input id="att-to" v-model="filters.to" type="date" :min="filters.from || undefined" /></div>
          <div class="filter-field"><label for="att-status">Status</label><select id="att-status" v-model="filters.status"><option value="">Semua status</option><option value="PRESENT">Hadir</option><option value="LATE">Terlambat</option><option value="SICK">Sakit</option><option value="PERMISSION">Izin</option><option value="ABSENT">Tidak hadir</option></select></div>
          <div v-if="studentMode && attendance.classes.length" class="filter-field"><label for="att-class">Kelas</label><select id="att-class" v-model="filters.class_uuid"><option value="">Semua kelas dalam akses</option><option v-for="schoolClass in attendance.classes" :key="schoolClass.uuid" :value="schoolClass.uuid">{{ schoolClass.name }}</option></select></div>
          <div v-if="!studentMode && attendance.teachers.length" class="filter-field"><label for="att-teacher">Guru</label><select id="att-teacher" v-model="filters.teacher_uuid"><option value="">Semua guru dalam akses</option><option v-for="teacher in attendance.teachers" :key="teacher.uuid" :value="teacher.uuid">{{ teacher.name || teacher.nip }}</option></select></div>
          <div class="filter-field filter-page"><label for="att-per-page">Per halaman</label><select id="att-per-page" v-model.number="filters.per_page"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option></select></div>
          <div class="filter-actions"><button type="submit" class="button button-primary" :disabled="filtering"><Filter :size="17" aria-hidden="true" />{{ filtering ? 'Menerapkan…' : 'Terapkan' }}</button><button type="button" class="button button-secondary" :disabled="filtering" @click="resetFilters"><RotateCcw :size="16" aria-hidden="true" />Reset</button></div>
        </form>
        <p v-if="filterError" class="filter-error" role="alert">{{ filterError }}</p>
        <div v-if="!records.total" class="attendance-empty" role="status"><ClipboardCheck :size="34" aria-hidden="true" /><h3>Belum ada catatan untuk filter ini</h3><p>Ubah rentang tanggal, status, atau kata pencarian. Belum tercatat tidak otomatis berarti tidak hadir.</p><button type="button" class="button button-secondary" @click="resetFilters">Bersihkan filter <ArrowRight :size="16" aria-hidden="true" /></button></div>
        <template v-else>
          <div class="attendance-table-wrap" role="region" aria-label="Tabel riwayat absensi" tabindex="0">
            <table class="attendance-table"><thead><tr><th scope="col">Tanggal</th><th scope="col">{{ studentMode ? 'Siswa' : 'Guru' }}</th><th v-if="studentMode" scope="col">Kelas</th><th scope="col">Status</th><th scope="col">Masuk</th><th scope="col">Pulang</th><th scope="col">Terlambat</th><th scope="col">Lokasi sekolah</th></tr></thead>
              <tbody><tr v-for="record in records.data" :key="record.uuid"><td>{{ dateLabel(record.date) }}</td><td><strong class="person-name">{{ record.person.name || '—' }}</strong><small class="person-id">{{ record.person.identifier || '—' }}</small></td><td v-if="studentMode">{{ record.class?.name || '—' }}</td><td><AttendanceStatusBadge :status="record.status" :label="record.status_label" /></td><td>{{ timeLabel(record.check_in_at) }}</td><td>{{ timeLabel(record.check_out_at) }}</td><td>{{ record.late_minutes > 0 ? `${record.late_minutes} menit` : '—' }}</td><td>{{ record.school_location || '—' }}</td></tr></tbody>
            </table>
          </div>
          <div class="attendance-pagination"><p>Menampilkan {{ records.from }}–{{ records.to }} dari {{ records.total }} catatan</p><div class="page-controls"><Link v-if="records.previous" :href="records.previous" class="button button-secondary" preserve-scroll><ChevronLeft :size="17" aria-hidden="true" />Sebelumnya</Link><span>Halaman {{ records.current_page }} / {{ records.last_page }}</span><Link v-if="records.next" :href="records.next" class="button button-secondary" preserve-scroll>Berikutnya<ChevronRight :size="17" aria-hidden="true" /></Link></div></div>
        </template>
      </section>
      <p class="attendance-footnote"><Info :size="16" aria-hidden="true" />Waktu ditampilkan dalam WIB. Data yang belum memiliki check-in bukan otomatis berstatus tidak hadir; hanya status yang dicatat server yang ditampilkan.</p>
    </div>
  </AppLayout>
</template>

<style scoped>
.attendance-page{display:grid;gap:21px}.attendance-tabs{display:flex;flex-wrap:wrap;gap:7px;border-bottom:1px solid #dce8ef;padding-bottom:12px}.attendance-tab{display:inline-flex;align-items:center;gap:9px;border:1px solid transparent;border-radius:10px;padding:11px 16px;color:#587083;font-size:13px;font-weight:750}.attendance-tab:hover{background:#eaf4f5;color:#126e7a}.attendance-tab.tab-active{color:#087f8c;background:#e4f6f5;border-color:#b8e0e1}.attendance-self{display:grid;grid-template-columns:minmax(0,1fr) minmax(250px,360px);align-items:center;gap:24px;border-color:#d0e9e8;background:linear-gradient(110deg,#ffffff,#f1faf9)}.eyebrow-line{font-size:11px;letter-spacing:.1em;font-weight:800;color:#168089;display:inline-flex;align-items:center;gap:7px;margin:0 0 12px}.self-copy h2{font-size:23px;color:#183348;margin:0 0 9px}.self-copy>p:not(.eyebrow-line){color:#64798b;font-size:13px;line-height:1.65;max-width:520px}.self-finished{display:flex;align-items:center;gap:10px;padding:18px;border-radius:12px;background:#edf8f3;color:#27644f;font-size:13px;font-weight:750;line-height:1.5}.attendance-notice{display:flex;gap:11px;align-items:flex-start;padding:15px 18px;background:#eef6fb;border:1px solid #d3e8f4;border-radius:12px;color:#3e677f;font-size:12px;line-height:1.65}.attendance-notice svg{flex-shrink:0}.attendance-summary{display:grid;grid-template-columns:minmax(170px,1.35fr) repeat(5,minmax(105px,1fr));gap:10px}.attendance-summary-main,.attendance-summary-item{display:flex;flex-direction:column;gap:11px;border:1px solid #e0eaf0;border-radius:13px;background:#fff;padding:17px}.attendance-summary-main{background:#13364c;color:#fff;border-color:#13364c}.attendance-summary-main>span,.attendance-summary-item>span{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700}.attendance-summary-main strong,.attendance-summary-item strong{font-size:27px;font-weight:800;line-height:1.1}.attendance-summary-main small{font-size:10px;line-height:1.4;color:#b5d1de}.attendance-summary-item>span{color:#678095}.attendance-summary-item strong{color:#243b4c}.attendance-history{min-width:0}.attendance-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:22px}.attendance-head h2{font-size:20px;margin:0 0 6px}.attendance-head p:last-child{font-size:12px;color:#73899b;margin:0}.attendance-count{border-radius:99px;background:#e9f6f6;padding:7px 11px;color:#107781;font-size:11px;font-weight:750;white-space:nowrap}.attendance-filters{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;border:1px solid #e4ebf1;border-radius:13px;padding:16px;margin-bottom:21px;background:#f9fbfd}.filter-field{min-width:137px;flex:1 1 138px;display:grid;gap:7px}.filter-field label{font-size:11px;font-weight:750;color:#455e73}.filter-field input,.filter-field select{width:100%;min-width:0;min-height:41px;border:1px solid #d7e3ea;border-radius:9px;background:#fff;padding:0 11px;color:#1f3c51;font-size:12px}.filter-field input:focus-visible,.filter-field select:focus-visible{outline:3px solid #91d7db;outline-offset:1px}.filter-search{flex:1.6 1 190px}.search-wrap{position:relative}.search-wrap svg{position:absolute;top:12px;left:11px;color:#7c90a1}.search-wrap input{padding-left:36px}.filter-page{flex:0 1 106px;min-width:95px}.filter-actions{display:flex;gap:8px;flex-wrap:wrap}.filter-error{color:#a02d43;background:#fff1f3;border:1px solid #f0c9d0;padding:12px;border-radius:9px;font-size:12px}.attendance-empty{text-align:center;display:grid;justify-items:center;gap:9px;padding:56px 20px;color:#7a8d9d}.attendance-empty svg{color:#258c93}.attendance-empty h3{color:#20394b;font-size:17px;margin:0}.attendance-empty p{font-size:12px;max-width:420px;line-height:1.65;margin:0 0 8px}.attendance-table-wrap{overflow-x:auto;max-width:100%;border:1px solid #e5ecf1;border-radius:11px}.attendance-table{border-collapse:collapse;width:100%;min-width:815px;text-align:left}.attendance-table th{padding:15px 14px;background:#f3f7fa;color:#557084;text-transform:uppercase;letter-spacing:.05em;font-size:10px;font-weight:800;white-space:nowrap}.attendance-table td{padding:14px;border-top:1px solid #ebf0f4;color:#455d70;font-size:12px;vertical-align:middle}.attendance-table tr:hover td{background:#fafdfd}.person-name{display:block;color:#273f52;font-size:12px}.person-id{display:block;margin-top:5px;font-size:10px;color:#8194a4}.attendance-pagination{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-top:19px}.attendance-pagination p{color:#6e8294;font-size:12px;margin:0}.page-controls{display:flex;align-items:center;gap:9px;flex-wrap:wrap}.page-controls span{color:#637b8c;font-size:11px}.attendance-footnote{font-size:11px;color:#7d8fa0;display:flex;align-items:flex-start;gap:7px;line-height:1.6}.attendance-footnote svg{flex-shrink:0}@media(max-width:1170px){.attendance-summary{grid-template-columns:repeat(3,minmax(0,1fr))}.attendance-self{grid-template-columns:1fr}}@media(max-width:700px){.attendance-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.attendance-summary-main{grid-column:span 2}.attendance-head{flex-direction:column}.attendance-filters{padding:12px}.filter-field{flex:1 1 145px}.filter-actions{width:100%}.filter-actions button{flex:1}.attendance-pagination{align-items:flex-start;flex-direction:column}.attendance-tab{flex:1;justify-content:center}.attendance-summary-main strong{font-size:25px}}@media(prefers-reduced-motion:reduce){.attendance-tab{transition:none}}
</style>
