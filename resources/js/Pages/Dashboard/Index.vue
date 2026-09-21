<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { CalendarDays, ClipboardCheck, Clock3, WalletCards, ReceiptText, CheckCheck, AlertTriangle, Inbox, RefreshCcw, Sparkles, ArrowRight, Activity } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import MetricCard from '../../Components/MetricCard.vue'
import AttendanceBreakdown from '../../Components/AttendanceBreakdown.vue'
import { localDateTime, number, rupiah } from '../../lib/format'

const props = defineProps({ overview: { type: Object, required: true } })
const selectedDate = ref(props.overview.date)
const refreshing = ref(false)
const sections = computed(() => props.overview)
const cards = computed(() => {
  const data = sections.value
  const result = []
  if (data.student_attendance) result.push({ label: 'Absensi siswa tercatat', value: number(data.student_attendance.total), note: 'Catatan untuk tanggal terpilih', icon: ClipboardCheck, tone: 'teal' })
  if (data.teacher_attendance) result.push({ label: 'Absensi guru tercatat', value: number(data.teacher_attendance.total), note: 'Sesuai hak akses Anda', icon: Clock3, tone: 'blue' })
  if (data.savings) result.push({ label: 'Saldo tabungan', value: rupiah(data.savings.total_balance), note: `${number(data.savings.accounts)} rekening dalam cakupan`, icon: WalletCards, tone: 'violet' })
  if (data.spp) result.push({ label: 'Sisa tagihan terbuka', value: rupiah(data.spp.outstanding_amount), note: `${number(data.spp.open_bills)} tagihan belum lunas`, icon: ReceiptText, tone: 'amber' })
  if (data.approvals) result.push({ label: 'Pengajuan menunggu', value: number(data.approvals.pending), note: 'Jumlah approval berstatus pending', icon: CheckCheck, tone: 'teal' })
  if (data.notifications) result.push({ label: 'Notifikasi gagal', value: number(data.notifications.failed), note: `${number(data.notifications.queued)} notifikasi dalam antrean`, icon: AlertTriangle, tone: 'rose' })
  return result
})
function updateDate() {
  if (!selectedDate.value) return
  refreshing.value = true
  router.get('/dashboard', { date: selectedDate.value }, {
    preserveScroll: true, replace: true, preserveState: false,
    onFinish: () => { refreshing.value = false },
  })
}
function refresh() { updateDate() }
</script>

<template>
  <AppLayout title="Dashboard" subtitle="Ringkasan operasional berdasarkan peran dan data yang boleh Anda lihat.">
    <template #actions>
      <form class="date-form" @submit.prevent="updateDate">
        <label class="date-input" for="dashboard-date"><CalendarDays :size="18" aria-hidden="true" /><span class="sr-only">Tanggal dashboard</span><input id="dashboard-date" v-model="selectedDate" type="date" name="date" required /></label>
        <button type="submit" class="button button-secondary" :disabled="refreshing"><RefreshCcw :size="16" :class="{ spinning: refreshing }" aria-hidden="true" /><span>{{ refreshing ? 'Memuat' : 'Terapkan' }}</span></button>
      </form>
    </template>

    <section class="welcome-band"><div><span class="eyebrow eyebrow-on-dark"><Sparkles :size="14" aria-hidden="true" /> RINGKASAN HARIAN</span><h2>Informasi penting, tanpa kehilangan konteks.</h2><p>Semua metrik berasal dari query backend dan dibatasi oleh hak akses Anda. Pilih tanggal untuk melihat absensi pada hari lain.</p></div><div class="welcome-decoration" aria-hidden="true"><Activity :size="72" :stroke-width="1.1" /></div></section>

    <div v-if="cards.length" class="metrics-grid" aria-label="Indikator utama"><MetricCard v-for="(card, index) in cards" :key="`${card.label}-${index}`" v-bind="card" /></div>
    <section v-else class="panel panel-empty"><Inbox :size="27" aria-hidden="true" /><h2>Belum ada ringkasan tersedia</h2><p>Data dashboard akan muncul setelah izin baca domain diberikan kepada akun ini.</p></section>

    <div v-if="sections.student_attendance || sections.teacher_attendance" class="section-heading"><div><p class="page-eyebrow">MONITORING</p><h2>Ringkasan kehadiran</h2></div><span>Periode: {{ sections.date }}</span></div>
    <div v-if="sections.student_attendance || sections.teacher_attendance" class="reports-grid"><AttendanceBreakdown v-if="sections.student_attendance" title="Absensi siswa" :data="sections.student_attendance" /><AttendanceBreakdown v-if="sections.teacher_attendance" title="Absensi guru" :data="sections.teacher_attendance" /></div>

    <div v-if="sections.activity?.recent || sections.approvals" class="section-heading"><div><p class="page-eyebrow">TINDAK LANJUT</p><h2>Aktivitas operasional</h2></div></div>
    <div v-if="sections.activity?.recent || sections.approvals" class="reports-grid">
      <section v-if="sections.activity?.recent" class="panel"><div class="panel-head"><div><h2>Aktivitas terakhir</h2><p>Audit event terbaru yang tersedia untuk peran Anda</p></div><span class="count-pill">Maks. 5</span></div><div v-if="!sections.activity.recent.length" class="empty-inline">Belum ada aktivitas tercatat.</div><ol v-else class="activity-list"><li v-for="(item, index) in sections.activity.recent" :key="`${item.created_at}-${index}`"><span class="activity-dot" aria-hidden="true"></span><div><strong>{{ item.action?.replaceAll('_', ' ') || 'Aktivitas' }}</strong><p>{{ item.module }} · {{ item.actor?.name || 'Sistem' }}</p></div><time :datetime="item.created_at">{{ localDateTime(item.created_at) }}</time></li></ol></section>
      <section v-if="sections.approvals" class="panel followup-panel"><div class="panel-head"><div><h2>Persetujuan</h2><p>Pengajuan yang menunggu penyelesaian</p></div><ClipboardCheck :size="21" aria-hidden="true" /></div><div class="followup-number">{{ number(sections.approvals.pending) }}</div><p class="followup-desc">Pengajuan pending dalam cakupan akun Anda. Manajemen approval akan tersedia pada tahap integrasi berikutnya.</p><a class="text-link" href="/modules">Lihat rencana modul <ArrowRight :size="16" aria-hidden="true" /></a></section>
    </div>
    <p class="data-note"><span aria-hidden="true">ⓘ</span> Jumlah absensi di atas adalah jumlah <strong>catatan yang tercatat</strong>, bukan total populasi sekolah. Nilai finansial adalah ringkasan saldo/tagihan saat ini sesuai data yang tersedia.</p>
  </AppLayout>
</template>
