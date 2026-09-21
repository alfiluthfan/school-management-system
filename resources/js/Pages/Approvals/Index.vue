<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ClipboardList, CheckCheck, Clock3, XCircle, History, Search, Filter, RotateCcw, ChevronLeft, ChevronRight, ArrowUpRight, Inbox } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import ApprovalStatusBadge from '../../Components/Approvals/ApprovalStatusBadge.vue'

const props = defineProps({ approvals: { type: Object, required: true } })
const filters = reactive({ ...props.approvals.filters })
const busy = ref(false)
watch(() => props.approvals.filters, (value) => Object.assign(filters, value))
const records = computed(() => props.approvals.records)
const counts = computed(() => props.approvals.counts)
const currentScope = computed(() => props.approvals.scope)
const statusTiles = [
  { value: '', title: 'Semua', key: 'total', icon: ClipboardList },
  { value: 'PENDING', title: 'Menunggu', key: 'pending', icon: Clock3 },
  { value: 'APPROVED', title: 'Disetujui', key: 'approved', icon: CheckCheck },
  { value: 'REJECTED', title: 'Ditolak', key: 'rejected', icon: XCircle },
  { value: 'CANCELLED', title: 'Dibatalkan', key: 'cancelled', icon: History },
]
function submit() {
  if (filters.from && filters.to && filters.from > filters.to) return
  const values = Object.fromEntries(Object.entries(filters).filter(([key, value]) => key !== 'page' && value !== '' && value !== null && value !== undefined))
  busy.value = true
  router.get('/approvals', values, { preserveScroll: true, preserveState: true, onFinish: () => { busy.value = false } })
}
function setStatus(status) { filters.status = status; submit() }
function reset() { Object.assign(filters, { scope: currentScope.value, status: '', module: '', action: '', search: '', from: '', to: '', per_page: 20 }); submit() }
function formatDate(value) { if (!value) return '—'; const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(date) + ' WIB' }
</script>
<template>
  <AppLayout title="Persetujuan" subtitle="Tinjau pengajuan dan telusuri riwayat keputusan sesuai izin akun Anda.">
    <div class="approval-index">
      <div v-if="approvals.can_view_all" class="approval-scope" role="group" aria-label="Cakupan pengajuan"><button type="button" :class="{ selected: filters.scope === 'all' }" @click="filters.scope='all'; submit()">Semua pengajuan</button><button type="button" :class="{ selected: filters.scope === 'mine' }" @click="filters.scope='mine'; submit()">Pengajuan saya</button></div>
      <section class="approval-stat-grid" aria-label="Ringkasan pengajuan">
        <button v-for="tile in statusTiles" :key="tile.key" type="button" class="approval-stat" :class="{ selected: filters.status === tile.value }" :aria-pressed="filters.status === tile.value" @click="setStatus(tile.value)"><span><component :is="tile.icon" :size="17" aria-hidden="true"/>{{ tile.title }}</span><strong>{{ counts[tile.key] ?? 0 }}</strong></button>
      </section>
      <section class="panel" aria-labelledby="approvals-heading">
        <header class="approval-section-head"><div><p class="approval-kicker">WORKFLOW</p><h2 id="approvals-heading">Daftar pengajuan</h2><p>Data ditampilkan setelah diverifikasi oleh server. Riwayat tersedia melalui filter status.</p></div><span class="approval-total">{{ records.total }} pengajuan</span></header>
        <form class="approval-filters" @submit.prevent="submit">
          <div class="approval-field approval-search"><label for="approval-search">Cari alasan atau pemohon</label><div class="approval-input-icon"><Search :size="17" aria-hidden="true"/><input id="approval-search" v-model="filters.search" type="search" maxlength="100" placeholder="Nama pemohon / alasan"/></div></div>
          <div class="approval-field"><label for="approval-module">Modul</label><select id="approval-module" v-model="filters.module"><option value="">Semua modul</option><option v-for="option in approvals.options.modules" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
          <div class="approval-field"><label for="approval-action">Jenis tindakan</label><select id="approval-action" v-model="filters.action"><option value="">Semua tindakan</option><option v-for="option in approvals.options.actions" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
          <div class="approval-field"><label for="approval-from">Dari tanggal</label><input id="approval-from" v-model="filters.from" type="date"/></div>
          <div class="approval-field"><label for="approval-to">Sampai tanggal</label><input id="approval-to" v-model="filters.to" type="date" :min="filters.from || undefined"/></div>
          <div class="approval-field approval-small"><label for="approval-size">Per halaman</label><select id="approval-size" v-model.number="filters.per_page"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option></select></div>
          <div class="approval-filter-actions"><button type="submit" class="button button-primary" :disabled="busy || (filters.from && filters.to && filters.from > filters.to)"><Filter :size="16" aria-hidden="true"/>{{ busy ? 'Memuat…' : 'Terapkan' }}</button><button type="button" class="button button-secondary" :disabled="busy" @click="reset"><RotateCcw :size="16" aria-hidden="true"/>Reset</button></div>
          <p v-if="filters.from && filters.to && filters.from > filters.to" class="approval-validation" role="alert">Tanggal akhir harus sama atau sesudah tanggal awal.</p>
        </form>
        <div v-if="!records.data.length" class="approval-empty" role="status"><Inbox :size="35" aria-hidden="true"/><h3>Belum ada pengajuan</h3><p>Tidak ada pengajuan yang sesuai dengan cakupan dan filter ini. Coba atur ulang filter untuk melihat riwayat lainnya.</p><button type="button" class="button button-secondary" @click="reset">Atur ulang filter</button></div>
        <template v-else>
          <div class="approval-table-wrap"><table class="approval-table"><thead><tr><th scope="col">Pengajuan</th><th scope="col">Pemohon</th><th scope="col">Modul &amp; tindakan</th><th scope="col">Diajukan</th><th scope="col">Status</th><th scope="col">Detail</th></tr></thead><tbody><tr v-for="item in records.data" :key="item.uuid"><td><strong>{{ item.reason }}</strong><small>{{ item.uuid.slice(0, 8).toUpperCase() }}</small></td><td>{{ item.requester?.name ?? '—' }}</td><td><strong class="module-name">{{ item.module.label }}</strong><small>{{ item.action.label }}</small></td><td>{{ formatDate(item.created_at) }}</td><td><ApprovalStatusBadge :status="item.status.value" :label="item.status.label"/></td><td><Link :href="`/approvals/${encodeURIComponent(item.uuid)}`" class="approval-detail-link" :aria-label="`Detail pengajuan ${item.uuid}`">Lihat <ArrowUpRight :size="15" aria-hidden="true"/></Link></td></tr></tbody></table></div>
          <nav class="approval-pager" aria-label="Pagination pengajuan"><span>Menampilkan {{ records.from }}–{{ records.to }} dari {{ records.total }}</span><div><Link v-if="records.previous" :href="records.previous" class="button button-secondary" preserve-scroll><ChevronLeft :size="16" aria-hidden="true"/> Sebelumnya</Link><span>Halaman {{ records.current_page }} / {{ records.last_page }}</span><Link v-if="records.next" :href="records.next" class="button button-secondary" preserve-scroll>Berikutnya <ChevronRight :size="16" aria-hidden="true"/></Link></div></nav>
        </template>
      </section>
    </div>
  </AppLayout>
</template>
<style scoped>
.approval-index{display:grid;gap:20px}.approval-scope{display:flex;gap:4px;background:#ecf3f6;border-radius:12px;padding:5px;width:max-content;max-width:100%}.approval-scope button{font-size:12px;font-weight:750;color:#687d8b;border:0;background:transparent;padding:11px 16px;border-radius:9px}.approval-scope button.selected{color:#087883;background:#fff;box-shadow:0 2px 8px #15364a12}.approval-stat-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.approval-stat{border:1px solid #e0e8ee;background:#fff;border-radius:13px;padding:16px;text-align:left;display:flex;flex-direction:column;gap:14px;min-width:0}.approval-stat.selected{border-color:#43abb5;background:#effbfa;box-shadow:0 0 0 1px #43abb5}.approval-stat>span{display:flex;align-items:center;gap:8px;color:#627b8c;font-weight:750;font-size:12px}.approval-stat>strong{font-size:27px;color:#17354b}.approval-stat.selected>strong{color:#087f8c}.approval-section-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:19px}.approval-kicker{font-size:10px;color:#138b95;font-weight:850;letter-spacing:.12em;margin-bottom:7px}.approval-section-head h2{font-size:20px;margin:0 0 5px}.approval-section-head p:last-child{font-size:12px;color:#72899a;margin:0}.approval-total{background:#e9f6f6;color:#0b7e86;padding:8px 11px;border-radius:99px;font-weight:750;font-size:11px}.approval-filters{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;background:#f7fafc;border:1px solid #e1eaf0;border-radius:13px;padding:15px;margin-bottom:20px}.approval-field{display:grid;gap:6px;flex:1 1 140px;min-width:132px}.approval-search{flex:1.6 1 215px}.approval-small{flex:0 1 110px;min-width:105px}.approval-field label{font-size:11px;font-weight:800;color:#4a667a}.approval-field input,.approval-field select{height:41px;width:100%;border:1px solid #d2e0e9;border-radius:9px;background:#fff;padding:0 10px;color:#2c4556;font:inherit;font-size:12px;min-width:0}.approval-input-icon{position:relative}.approval-input-icon svg{position:absolute;top:12px;left:11px;color:#7e94a4}.approval-input-icon input{padding-left:35px}.approval-filter-actions{display:flex;gap:8px;flex-wrap:wrap}.approval-validation{width:100%;color:#a12b49;font-size:12px;margin:0}.approval-table-wrap{overflow:auto;border:1px solid #e3ebf1;border-radius:11px}.approval-table{width:100%;min-width:815px;border-collapse:collapse;text-align:left}.approval-table th{font-size:10px;letter-spacing:.06em;background:#f4f8fb;color:#647f90;text-transform:uppercase;padding:15px 13px}.approval-table td{border-top:1px solid #e9eff4;padding:15px 13px;color:#4a6275;font-size:12px;vertical-align:middle}.approval-table td strong{display:block;color:#293f54;font-size:12px;line-height:1.5;max-width:240px}.approval-table td small{display:block;color:#8b9eab;font-size:10px;margin-top:5px}.approval-table tr:hover td{background:#fbfefe}.approval-detail-link{display:inline-flex;gap:5px;color:#087f8c;font-weight:800;align-items:center;white-space:nowrap}.approval-detail-link:hover{text-decoration:underline}.approval-empty{padding:52px 20px;text-align:center;display:grid;justify-items:center;gap:9px;color:#7a91a0}.approval-empty svg{color:#278e96}.approval-empty h3{font-size:17px;margin:0;color:#284356}.approval-empty p{font-size:12px;line-height:1.65;max-width:390px;margin:0 0 8px}.approval-pager{margin-top:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;font-size:12px;color:#6e8294}.approval-pager>div{display:flex;align-items:center;gap:9px;flex-wrap:wrap}@media(max-width:1050px){.approval-stat-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:620px){.approval-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.approval-filters{padding:12px}.approval-pager{flex-direction:column;align-items:flex-start}.approval-section-head{align-items:flex-start}}
</style>
