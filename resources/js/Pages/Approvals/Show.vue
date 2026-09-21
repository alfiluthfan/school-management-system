<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ArrowLeft, ClipboardCheck, UserRound, Clock3, FileText, History, Info, CheckCircle2 } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import ApprovalStatusBadge from '../../Components/Approvals/ApprovalStatusBadge.vue'
import ApprovalDecisionPanel from '../../Components/Approvals/ApprovalDecisionPanel.vue'
const props = defineProps({ approval: { type: Object, required: true } })
const a = computed(() => props.approval)
const page = usePage()
const workflowError = computed(() => page.props.errors?.approval ?? null)
const fieldLabels = { status:'Status baru', check_in_at:'Waktu masuk', check_out_at:'Waktu pulang', notes:'Catatan', amount:'Nominal', payment_method:'Metode pembayaran', reference_number:'Nomor referensi' }
const changes = computed(() => Object.entries(a.value.request_payload?.changes ?? {}).map(([key, value]) => ({ name: fieldLabels[key] ?? key, value: value === null ? 'Dikosongkan' : String(value) })))
function formatDate(value) { if (!value) return '—'; const date=new Date(value); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID',{dateStyle:'long',timeStyle:'short',timeZone:'Asia/Jakarta'}).format(date)+' WIB' }
const summary = computed(() => {
 const e=a.value.entity ?? {}
 if (e.student?.name) return e.student.name
 if (e.teacher?.name) return e.teacher.name
 if (e.payment_number) return e.payment_number
 if (e.transaction_number) return e.transaction_number
 return e.uuid ?? 'Entitas tidak tersedia'
})
const entityDetails = computed(() => {
 const e=a.value.entity ?? {}
 return [
  { label:'Siswa', value:e.student?.name }, { label:'NIS',value:e.student?.nis },
  { label:'Guru',value:e.teacher?.name },{ label:'NIP',value:e.teacher?.nip },
  { label:'Nomor pembayaran', value:e.payment_number },{ label:'Nomor kuitansi', value:e.receipt_number },
  { label:'Nomor transaksi', value:e.transaction_number },{ label:'Nominal',value:e.amount },
  { label:'Tanggal absensi',value:e.attendance_date },{ label:'Mulai izin',value:e.start_date },
  { label:'Selesai izin',value:e.end_date },{ label:'Jenis izin',value:e.leave_type?.label },
  { label:'Status entitas',value:e.status?.label },
 ].filter(item => item.value !== null && item.value !== undefined && item.value !== '')
})
</script>
<template>
  <AppLayout title="Detail persetujuan" subtitle="Periksa pengajuan dan catatan keputusan yang tersimpan pada workflow.">
    <div class="approval-detail-page">
      <Link href="/approvals" class="back-approvals"><ArrowLeft :size="17" aria-hidden="true"/> Kembali ke daftar</Link>
      <div v-if="workflowError" class="approval-workflow-error" role="alert">{{ workflowError }}</div>
      <div class="detail-grid">
        <div class="detail-main">
          <section class="panel approval-detail-hero" aria-labelledby="approval-title"><div class="detail-hero-top"><span class="detail-icon"><ClipboardCheck :size="24" aria-hidden="true"/></span><ApprovalStatusBadge :status="a.status.value" :label="a.status.label"/></div><p class="approval-overline">{{ a.module.label }} / {{ a.action.label }}</p><h2 id="approval-title">{{ a.reason }}</h2><p class="detail-id">ID publik: {{ a.uuid }}</p></section>
          <section class="panel detail-section" aria-labelledby="entity-title"><header><FileText :size="19" aria-hidden="true"/><h2 id="entity-title">Objek pengajuan</h2></header><p class="entity-name">{{ summary }}</p><dl class="detail-definition"><div v-for="item in entityDetails" :key="item.label"><dt>{{ item.label }}</dt><dd>{{ item.value }}</dd></div></dl><p v-if="!entityDetails.length" class="helper-note">Detail objek tidak tersedia. Informasi pengajuan tetap tersimpan.</p></section>
          <section class="panel detail-section" aria-labelledby="changes-title"><header><Info :size="19" aria-hidden="true"/><h2 id="changes-title">Perubahan yang diminta</h2></header><dl v-if="changes.length" class="detail-definition"><div v-for="change in changes" :key="change.name"><dt>{{ change.name }}</dt><dd>{{ change.value }}</dd></div></dl><p v-else class="helper-note">Pengajuan ini tidak memiliki perubahan field terstruktur. Lihat alasan dan informasi objek di atas.</p></section>
          <ApprovalDecisionPanel :approval="a"/>
          <section v-if="a.status.value !== 'PENDING'" class="panel detail-section" aria-labelledby="result-title"><header><CheckCircle2 :size="19" aria-hidden="true"/><h2 id="result-title">Hasil keputusan</h2></header><p><ApprovalStatusBadge :status="a.status.value" :label="a.status.label"/></p><p v-if="a.review_notes" class="review-text">{{ a.review_notes }}</p><p v-else class="helper-note">Tidak ada catatan keputusan.</p></section>
        </div>
        <aside class="detail-side" aria-label="Informasi dan kronologi"><section class="panel detail-section"><header><UserRound :size="19" aria-hidden="true"/><h2>Informasi pihak</h2></header><dl class="detail-definition"><div><dt>Diajukan oleh</dt><dd>{{ a.requester?.name ?? 'Tidak tersedia' }}</dd></div><div><dt>Diajukan pada</dt><dd>{{ formatDate(a.created_at) }}</dd></div><div><dt>Ditinjau oleh</dt><dd>{{ a.reviewer?.name ?? 'Belum ditinjau' }}</dd></div><div><dt>Keputusan pada</dt><dd>{{ formatDate(a.reviewed_at) }}</dd></div></dl></section>
          <section class="panel detail-section"><header><History :size="19" aria-hidden="true"/><h2>Riwayat keputusan</h2></header><ol class="approval-timeline"><li v-for="event in a.timeline" :key="event.key"><span class="timeline-dot"><Clock3 :size="13" aria-hidden="true"/></span><div><strong>{{ event.title }}</strong><span>{{ event.actor ?? 'Sistem' }}</span><time v-if="event.at" :datetime="event.at">{{ formatDate(event.at) }}</time><span v-else>Waktu tidak tercatat</span></div></li></ol><p class="timeline-note">Kronologi berasal dari waktu pengajuan dan keputusan pada approval. Bukan daftar audit log lengkap.</p></section></aside>
      </div>
    </div>
  </AppLayout>
</template>
<style scoped>
.approval-detail-page{display:grid;gap:17px}.approval-workflow-error{background:#fff0f3;border:1px solid #f1c6d1;border-radius:10px;padding:14px;color:#a52e49;font-size:13px}.back-approvals{display:inline-flex;align-items:center;gap:7px;color:#087b86;font-size:12px;font-weight:800;width:max-content}.back-approvals:hover{text-decoration:underline}.detail-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(270px,1fr);gap:18px;align-items:start}.detail-main,.detail-side{display:grid;gap:17px;min-width:0}.approval-detail-hero{background:linear-gradient(120deg,#fff,#f1faf9);border-color:#d5eeeb}.detail-hero-top{display:flex;align-items:center;justify-content:space-between;gap:9px}.detail-icon{display:grid;place-items:center;background:#d9f4ef;color:#0d7c7b;border-radius:12px;width:48px;height:48px}.approval-overline{color:#16858d;font-weight:850;font-size:11px;letter-spacing:.08em;text-transform:uppercase;margin:21px 0 7px}.approval-detail-hero h2{font-size:clamp(20px,2.1vw,26px);line-height:1.45;color:#233d51;overflow-wrap:anywhere;margin:0 0 13px}.detail-id{font-size:11px;color:#8195a2;margin:0;overflow-wrap:anywhere}.detail-section header{display:flex;gap:9px;align-items:center;margin-bottom:19px;color:#087f8c}.detail-section h2{margin:0;color:#243e51;font-size:17px}.entity-name{font-weight:850;color:#20384b;margin:0 0 14px;overflow-wrap:anywhere}.detail-definition{display:grid;gap:12px;margin:0}.detail-definition>div{display:flex;justify-content:space-between;gap:14px;border-bottom:1px solid #edf2f6;padding-bottom:12px}.detail-definition>div:last-child{border:0;padding-bottom:0}.detail-definition dt{color:#73899a;font-size:12px;min-width:100px}.detail-definition dd{text-align:right;font-weight:750;color:#263d51;font-size:12px;margin:0;overflow-wrap:anywhere;white-space:pre-wrap}.review-text{font-size:13px;color:#3e5769;line-height:1.8;background:#f3f8fb;border-radius:9px;padding:15px;white-space:pre-wrap}.helper-note{color:#73899b;font-size:12px;line-height:1.6}.approval-timeline{list-style:none;padding:0;margin:0;display:grid;gap:23px}.approval-timeline li{display:flex;gap:11px;position:relative}.approval-timeline li:not(:last-child):before{content:'';position:absolute;top:29px;left:12px;bottom:-24px;border-left:1px dashed #b5d9d9}.timeline-dot{display:grid;place-items:center;flex-shrink:0;width:26px;height:26px;border-radius:100px;background:#e0f5f1;color:#168b86;z-index:1}.approval-timeline li>div{display:grid;gap:5px}.approval-timeline strong{font-size:12px;color:#284256}.approval-timeline li span:not(.timeline-dot),.approval-timeline time{font-size:11px;color:#708697}.timeline-note{border-top:1px solid #ecf1f5;padding-top:14px;margin:20px 0 0;font-size:11px;color:#8498a5;line-height:1.6}@media(max-width:940px){.detail-grid{grid-template-columns:1fr}}@media(max-width:580px){.detail-definition>div{display:grid;gap:5px}.detail-definition dd{text-align:left}}
</style>
