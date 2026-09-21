<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { AlertCircle, ArrowDownToLine, ArrowRight, BarChart3, CalendarDays, ChevronLeft, ChevronRight, FileDown, FileSpreadsheet, FileText, Filter, Inbox, RefreshCw, ShieldCheck, Wallet, X } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import ReportTrendChart from '../../Components/Reports/ReportTrendChart.vue'
import ExportStatus from '../../Components/Reports/ExportStatus.vue'
import '../../../css/reports.css'

const props = defineProps({
  reporting: { type: Object, required: true },
  report: { type: Object, required: true },
  exports: { type: Object, required: true },
})
const filters = reactive({ ...props.reporting.filters })
watch(() => props.reporting.filters, next => Object.assign(filters, next))
const activeType = computed(() => props.reporting.filters.report_type)
const formError = ref('')
const exportError = ref('')
const format = ref(props.reporting.formats[0]?.value ?? 'pdf')
const busy = ref(false)
const exporting = ref(false)
const polling = ref(false)
let timer
const canCreate = computed(() => props.reporting.can_export && props.reporting.formats.length > 0)
const queueActive = computed(() => props.exports.data.some(item => ['QUEUED', 'PROCESSING'].includes(item.status)))
const money = value => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
const count = value => new Intl.NumberFormat('id-ID').format(Number(value || 0))
const dateTime = value => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(value)) : '—'
const typeLabel = value => props.reporting.types.find(type => type.value === value)?.label || value
const dateRows = computed(() => activeType.value === 'spp' ? (props.report.collections?.daily ?? []) : (props.report.daily ?? []))
const chartSeries = computed(() => {
  if (activeType.value === 'savings') return [{ key: 'deposits', label: 'Setoran' }, { key: 'withdrawals', label: 'Penarikan' }]
  if (activeType.value === 'spp') return [{ key: 'amount', label: 'Penerimaan' }]
  return [{ key: 'total', label: 'Catatan absensi' }, { key: 'late', label: 'Terlambat' }]
})
const chartMoney = computed(() => ['savings', 'spp'].includes(activeType.value))
const metrics = computed(() => {
  const r = props.report
  if (activeType.value === 'savings') return [
    { label: 'Rekening dalam cakupan', value: count(r.snapshot?.accounts), hint: 'Snapshot saldo saat laporan dibuka' },
    { label: 'Saldo seluruh rekening', value: money(r.snapshot?.total_balance), hint: 'Saldo terkini, bukan saldo historis' },
    { label: 'Setoran periode', value: money(r.transactions?.deposit?.amount), hint: `${count(r.transactions?.deposit?.count)} transaksi posted` },
    { label: 'Penarikan periode', value: money(r.transactions?.withdrawal?.amount), hint: 'Belum dikurangi reversal' },
  ]
  if (activeType.value === 'spp') return [
    { label: 'Tagihan jatuh tempo', value: count(r.bills?.count), hint: 'Cohort berdasarkan due_date' },
    { label: 'Sisa tagihan cohort', value: money(r.bills?.outstanding_amount), hint: 'Saldo terkini tagihan dalam periode' },
    { label: 'Pembayaran diterima', value: money(r.collections?.amount), hint: 'Arus pembayaran posted dalam periode' },
    { label: 'Progres tagihan cohort', value: `${r.bills?.collection_ratio_percent ?? 0}%`, hint: 'Bukan rasio penerimaan dalam periode' },
  ]
  return [
    { label: 'Catatan kehadiran', value: count(r.summary?.total_records), hint: 'Jumlah catatan, bukan jumlah seluruh siswa/guru' },
    { label: 'Hadir', value: count(r.summary?.present), hint: 'Berstatus PRESENT' },
    { label: 'Terlambat', value: count(r.summary?.late), hint: 'Berstatus LATE' },
    { label: 'Total keterlambatan', value: `${count(r.summary?.late_minutes?.total)} menit`, hint: 'Akumulasi dari data tercatat' },
  ]
})
function payload() {
  const data = { report_type: filters.report_type, from: filters.from, to: filters.to }
  if (filters.report_type === 'student_attendance' && filters.class_uuid) data.class_uuid = filters.class_uuid
  if (filters.report_type === 'teacher_attendance' && filters.teacher_uuid) data.teacher_uuid = filters.teacher_uuid
  if (filters.export_status) data.export_status = filters.export_status
  return data
}
function apply() {
  formError.value = ''
  router.get('/reports', payload(), { replace: true, preserveState: true, preserveScroll: true,
    onStart: () => { busy.value = true }, onFinish: () => { busy.value = false },
    onError: errors => { formError.value = Object.values(errors)[0] || 'Filter laporan tidak valid.' },
  })
}
function changeType() {
  filters.class_uuid = ''
  filters.teacher_uuid = ''
  apply()
}
function reset() {
  filters.class_uuid = ''
  filters.teacher_uuid = ''
  filters.export_status = ''
  apply()
}
function submitExport() {
  if (!canCreate.value || exporting.value) return
  exportError.value = ''
  // A fresh report uses exactly the server-applied period/filter; never export
  // unsaved edits to the date filter or mix UUID fields from another report.
  const applied = props.reporting.filters
  const data = { report_type: applied.report_type, format: format.value, from: applied.from, to: applied.to }
  if (applied.report_type === 'student_attendance' && applied.class_uuid) data.class_uuid = applied.class_uuid
  if (applied.report_type === 'teacher_attendance' && applied.teacher_uuid) data.teacher_uuid = applied.teacher_uuid
  router.post('/reports/exports', data, { preserveScroll: true,
    onStart: () => { exporting.value = true }, onFinish: () => { exporting.value = false },
    onError: errors => { exportError.value = Object.values(errors)[0] || 'Permintaan ekspor ditolak oleh server.' },
  })
}
function refreshExports() {
  if (polling.value) return
  polling.value = true
  router.reload({ only: ['exports'], preserveScroll: true,
    onFinish: () => { polling.value = false },
  })
}
onMounted(() => {
  timer = window.setInterval(() => {
    if (queueActive.value && !document.hidden && !polling.value && !busy.value && !exporting.value) refreshExports()
  }, 5000)
})
onUnmounted(() => window.clearInterval(timer))
</script>

<template>
  <AppLayout title="Laporan & Ekspor" subtitle="Analitik terpercaya dan ekspor terotorisasi berdasarkan cakupan data akun Anda.">
    <div class="rpt-page">
      <div class="rpt-info"><ShieldCheck :size="19" aria-hidden="true"/><span>Setiap angka dibaca dari query laporan backend. File ekspor hanya dapat diunduh oleh pemilik yang masih memiliki izin dan scope yang sesuai.</span></div>
      <section class="panel rpt-panel" aria-labelledby="report-filter-title">
        <div class="rpt-heading"><div><p class="rpt-kicker">01 / PILIH LAPORAN</p><h2 id="report-filter-title">Atur periode dan cakupan</h2><p>Laporan interaktif dibatasi maksimal 366 hari.</p></div><CalendarDays :size="25" aria-hidden="true"/></div>
        <form class="rpt-filters" @submit.prevent="apply">
          <label>Jenis laporan<select v-model="filters.report_type" :disabled="busy" @change="changeType"><option v-for="item in reporting.types" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
          <label>Dari tanggal<input v-model="filters.from" type="date" required :disabled="busy" /></label>
          <label>Sampai tanggal<input v-model="filters.to" type="date" required :disabled="busy" /></label>
          <label v-if="filters.report_type === 'student_attendance' && activeType === filters.report_type && reporting.options.classes.length">Kelas (opsional)<select v-model="filters.class_uuid" :disabled="busy"><option value="">Semua kelas yang dapat diakses</option><option v-for="item in reporting.options.classes" :key="item.uuid" :value="item.uuid">{{ item.label }}</option></select></label>
          <label v-if="filters.report_type === 'teacher_attendance' && activeType === filters.report_type && reporting.options.teachers.length">Guru (opsional)<select v-model="filters.teacher_uuid" :disabled="busy"><option value="">Semua guru dalam cakupan</option><option v-for="item in reporting.options.teachers" :key="item.uuid" :value="item.uuid">{{ item.label }}</option></select></label>
          <div class="rpt-filter-buttons"><button type="submit" class="button button-primary" :disabled="busy"><Filter :size="17" aria-hidden="true"/>{{ busy ? 'Memuat…' : 'Terapkan filter' }}</button><button type="button" class="button button-secondary" :disabled="busy" @click="reset">Reset tambahan</button></div>
        </form>
        <p v-if="formError" class="rpt-error" role="alert"><AlertCircle :size="17" aria-hidden="true"/>{{ formError }}</p>
        <p v-if="$page.props.errors?.from || $page.props.errors?.to || $page.props.errors?.class_uuid || $page.props.errors?.teacher_uuid" class="rpt-error" role="alert">{{ $page.props.errors.from || $page.props.errors.to || $page.props.errors.class_uuid || $page.props.errors.teacher_uuid }}</p>
      </section>
      <section class="rpt-results" aria-labelledby="report-summary-title">
        <div class="rpt-heading rpt-result-heading"><div><p class="rpt-kicker">02 / RINGKASAN</p><h2 id="report-summary-title">{{ typeLabel(activeType) }}</h2><p>Periode {{ reporting.filters.from }} sampai {{ reporting.filters.to }}</p></div><BarChart3 :size="24" aria-hidden="true"/></div>
        <div class="rpt-metrics"><article v-for="(metric,index) in metrics" :key="metric.label" class="rpt-metric"><span class="rpt-metric-icon"><Wallet v-if="activeType === 'savings' || activeType === 'spp'" :size="19" aria-hidden="true"/><BarChart3 v-else :size="19" aria-hidden="true"/></span><p>{{ metric.label }}</p><strong>{{ metric.value }}</strong><small>{{ metric.hint }}</small></article></div>
        <ReportTrendChart :rows="dateRows" :series="chartSeries" :money="chartMoney" />
        <section v-if="activeType === 'student_attendance' || activeType === 'teacher_attendance'" class="rpt-breakdown" aria-label="Rincian status kehadiran"><h3>Rincian status</h3><div><span>Hadir <strong>{{ count(report.summary?.present) }}</strong></span><span>Terlambat <strong>{{ count(report.summary?.late) }}</strong></span><span>Sakit <strong>{{ count(report.summary?.sick) }}</strong></span><span>Izin <strong>{{ count(report.summary?.permission) }}</strong></span><span>Alpa <strong>{{ count(report.summary?.absent) }}</strong></span></div><p>Catatan yang belum dibuat tidak dihitung sebagai alpa; ini bukan persentase kehadiran seluruh sekolah.</p></section>
        <section v-else-if="activeType === 'savings'" class="rpt-breakdown" aria-label="Rincian transaksi tabungan"><h3>Jenis transaksi lain</h3><div><span>Reversal <strong>{{ money(report.transactions?.reversal?.amount) }}</strong></span><span>Penyesuaian <strong>{{ money(report.transactions?.adjustment?.amount) }}</strong></span><span>Jumlah transaksi <strong>{{ count(report.transactions?.count) }}</strong></span></div><p>Reversal ditampilkan terpisah karena arah ekonominya bergantung pada transaksi asal.</p></section>
        <section v-else class="rpt-breakdown" aria-label="Status tagihan SPP"><h3>Status tagihan</h3><div><span>Menunggu <strong>{{ count(report.bills?.pending) }}</strong></span><span>Sebagian <strong>{{ count(report.bills?.partial) }}</strong></span><span>Lunas <strong>{{ count(report.bills?.paid) }}</strong></span><span>Jatuh tempo <strong>{{ count(report.bills?.overdue) }}</strong></span><span>Batal <strong>{{ count(report.bills?.cancelled) }}</strong></span></div><p>Cohort tagihan ditentukan oleh tanggal jatuh tempo; penerimaan kas ditentukan oleh tanggal pembayaran.</p></section>
      </section>
      <section class="panel rpt-panel" aria-labelledby="export-create-title">
        <div class="rpt-heading"><div><p class="rpt-kicker">03 / EKSPOR TERJADWAL</p><h2 id="export-create-title">Buat PDF atau Excel</h2><p>Ekspor menggunakan filter yang sudah diterapkan: {{ reporting.filters.from }} – {{ reporting.filters.to }}.</p></div><FileDown :size="25" aria-hidden="true"/></div>
        <form v-if="canCreate" class="rpt-export-form" @submit.prevent="submitExport"><label>Format file<select v-model="format" :disabled="exporting"><option v-for="item in reporting.formats" :key="item.value" :value="item.value">{{ item.label }}</option></select></label><button class="button button-primary" type="submit" :disabled="exporting"><FileDown :size="18" aria-hidden="true"/>{{ exporting ? 'Mengirim permintaan…' : 'Masukkan antrean ekspor' }}</button></form>
        <p v-else class="rpt-muted">Akun Anda dapat melihat laporan, tetapi belum memiliki izin ekspor PDF/Excel. Hubungi administrator bila diperlukan.</p>
        <p v-if="exportError" class="rpt-error" role="alert"><AlertCircle :size="17" aria-hidden="true"/>{{ exportError }}</p>
        <p v-if="$page.props.errors?.format || $page.props.errors?.report_type" class="rpt-error" role="alert">{{ $page.props.errors.format || $page.props.errors.report_type }}</p>
      </section>
      <section v-if="canCreate" class="panel rpt-panel" aria-labelledby="export-history-title">
        <div class="rpt-heading"><div><p class="rpt-kicker">04 / BERKAS SAYA</p><h2 id="export-history-title">Riwayat ekspor privat</h2><p>Hanya file milik Anda. Status aktif diperbarui setiap 5 detik selama halaman terlihat.</p></div><button type="button" class="button button-secondary" :disabled="polling" @click="refreshExports"><RefreshCw :size="17" aria-hidden="true"/>{{ polling ? 'Memuat…' : 'Perbarui' }}</button></div>
        <div v-if="queueActive" class="rpt-info" role="status"><RefreshCw :size="16" aria-hidden="true"/>Masih ada laporan yang diproses. Biarkan queue worker tetap berjalan.</div>
        <form class="rpt-status-filter" @submit.prevent="apply"><label>Filter status<select v-model="filters.export_status" @change="apply"><option value="">Semua status</option><option value="QUEUED">Antrean</option><option value="PROCESSING">Diproses</option><option value="READY">Siap</option><option value="FAILED">Gagal</option><option value="EXPIRED">Kedaluwarsa</option></select></label></form>
        <div v-if="!exports.data.length" class="rpt-empty" role="status"><Inbox :size="31" aria-hidden="true"/><strong>Belum ada ekspor untuk filter ini.</strong><span>Kirim permintaan di formulir di atas, atau ubah filter status.</span></div>
        <div v-else class="rpt-table-scroll"><table class="rpt-table"><thead><tr><th scope="col">Laporan</th><th scope="col">Periode</th><th scope="col">Format</th><th scope="col">Diajukan</th><th scope="col">Status</th><th scope="col">Unduh</th></tr></thead><tbody><tr v-for="item in exports.data" :key="item.uuid"><td><strong>{{ typeLabel(item.report_type) }}</strong><small v-if="item.filters?.class_uuid || item.filters?.teacher_uuid">Memiliki filter khusus</small></td><td>{{ item.period.from }} – {{ item.period.to }}</td><td><span class="rpt-format"><FileText v-if="item.format === 'pdf'" :size="16" aria-hidden="true"/><FileSpreadsheet v-else :size="16" aria-hidden="true"/>{{ item.format === 'pdf' ? 'PDF' : 'Excel' }}</span></td><td>{{ dateTime(item.created_at) }}</td><td><ExportStatus :status="item.status"/></td><td><a v-if="item.download_url" class="button button-secondary rpt-download" :href="item.download_url" :aria-label="`Unduh ${typeLabel(item.report_type)} format ${item.format}`"><ArrowDownToLine :size="16" aria-hidden="true"/> Unduh</a><span v-else class="rpt-muted">{{ item.status === 'FAILED' ? 'Buat permintaan baru' : 'Tidak tersedia' }}</span></td></tr></tbody></table></div>
        <nav v-if="exports.last_page > 1" class="rpt-pagination" aria-label="Halaman ekspor"><span>{{ exports.total }} ekspor · halaman {{ exports.current_page }}/{{ exports.last_page }}</span><div><Link v-if="exports.previous" class="button button-secondary" :href="exports.previous" :only="['exports']" preserve-scroll><ChevronLeft :size="16"/> Sebelumnya</Link><Link v-if="exports.next" class="button button-secondary" :href="exports.next" :only="['exports']" preserve-scroll>Berikutnya <ChevronRight :size="16"/></Link></div></nav>
      </section>
    </div>
  </AppLayout>
</template>
