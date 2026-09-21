<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Wallet, ReceiptText, Search, Filter, RotateCcw, ChevronLeft, ChevronRight, ArrowUpRight, Inbox, ShieldCheck } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import { rupiah, localDateTime } from '../../lib/format.js'
import '../../../css/finance.css'

const props = defineProps({ finance: { type: Object, required: true } })
const filters = reactive({ ...props.finance.filters })
const busy = ref(false)
const error = ref('')
const type = computed(() => props.finance.type)
const records = computed(() => props.finance.records)
watch(() => props.finance.filters, (value) => Object.assign(filters, value))
function apply() {
  error.value = ''
  if (filters.from && filters.to && filters.from > filters.to) { error.value = 'Tanggal akhir tidak boleh lebih awal.'; return }
  const data = { type: type.value }
  Object.entries(filters).forEach(([key, value]) => { if (value !== '' && value !== null && value !== undefined) data[key] = value })
  router.get('/finance', data, { preserveState: true, preserveScroll: true, replace: true,
    onStart: () => { busy.value = true }, onFinish: () => { busy.value = false },
    onError: (errors) => { error.value = Object.values(errors)[0] ?? 'Filter gagal.' } })
}
function reset() { Object.assign(filters, { status: '', search: '', from: '', to: '', per_page: 20 }); apply() }
const period = item => `${String(item.period?.month ?? '').padStart(2, '0')}/${item.period?.year ?? ''}`
</script>

<template>
  <AppLayout title="Keuangan" subtitle="Saldo tabungan dan tagihan SPP yang diizinkan untuk akun Anda.">
    <div class="finance-page">
      <nav class="finance-tabs" aria-label="Jenis data keuangan">
        <Link v-for="tab in finance.tabs" :key="tab.type" :href="`/finance?type=${tab.type}`" class="finance-tab"
          :class="{ selected: type === tab.type }" :aria-current="type === tab.type ? 'page' : undefined">
          <Wallet v-if="tab.type === 'savings'" :size="18" aria-hidden="true" /><ReceiptText v-else :size="18" aria-hidden="true" />{{ tab.label }}
        </Link>
      </nav>
      <div class="finance-note"><ShieldCheck :size="17" aria-hidden="true" /> Informasi ditentukan dari izin dan relasi akun di server. Nominal saldo dan sisa tagihan mengikuti data resmi backend.</div>
      <section class="finance-overview" aria-label="Ringkasan data keuangan sesuai filter"><div><p>{{ finance.summary.title }}</p><strong>{{ rupiah(finance.summary.amount) }}</strong><small>Agregasi sesuai filter dan izin akun; bukan total seluruh sekolah jika akses Anda terbatas.</small></div><span>{{ records.total }} catatan ditemukan</span></section>
      <section class="panel" aria-labelledby="finance-list-title">
        <header class="finance-heading"><div><p class="finance-kicker">CATATAN KEUANGAN</p><h2 id="finance-list-title">{{ type === 'savings' ? 'Rekening tabungan' : 'Tagihan SPP' }}</h2><p>Pencarian dan filter hanya berlaku dalam data yang boleh Anda lihat.</p></div><span class="finance-pill">{{ records.total }} data</span></header>
        <form class="finance-filters" @submit.prevent="apply">
          <label class="finance-field finance-search">Cari nama, NIS atau nomor <div class="finance-search-wrap"><Search :size="16" aria-hidden="true"/><input v-model.trim="filters.search" type="search" maxlength="100" placeholder="Masukkan kata kunci" /></div></label>
          <label class="finance-field">Status <select v-model="filters.status"><option value="">Semua status</option><option v-for="status in finance.statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select></label>
          <label class="finance-field">{{ type === 'savings' ? 'Dibuka dari' : 'Jatuh tempo dari' }}<input v-model="filters.from" type="date" :max="filters.to || undefined" /></label>
          <label class="finance-field">Sampai tanggal<input v-model="filters.to" type="date" :min="filters.from || undefined" /></label>
          <label class="finance-field finance-small">Per halaman<select v-model.number="filters.per_page"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option></select></label>
          <div class="finance-filter-actions"><button class="button button-primary" type="submit" :disabled="busy"><Filter :size="16" aria-hidden="true"/>{{ busy ? 'Memuat…' : 'Terapkan' }}</button><button class="button button-secondary" type="button" :disabled="busy" @click="reset"><RotateCcw :size="16" aria-hidden="true"/>Reset</button></div>
        </form>
        <p v-if="error" role="alert" class="finance-error">{{ error }}</p>
        <div v-if="!records.data.length" class="finance-empty" role="status"><Inbox :size="35" aria-hidden="true"/><h3>Belum ada data</h3><p>Tidak ada catatan sesuai akses dan filter saat ini.</p><button type="button" class="button button-secondary" @click="reset">Bersihkan filter</button></div>
        <template v-else>
          <div class="finance-scroll" role="region" aria-label="Tabel catatan keuangan" tabindex="0">
            <table class="finance-table"><thead><tr><th scope="col">Nomor</th><th scope="col">Siswa</th><th scope="col">{{ type === 'savings' ? 'Saldo' : 'Tagihan / Sisa' }}</th><th scope="col">{{ type === 'savings' ? 'Dibuka' : 'Periode / Jatuh tempo' }}</th><th scope="col">Status</th><th scope="col">Detail</th></tr></thead>
              <tbody><tr v-for="item in records.data" :key="item.uuid"><td><strong>{{ item.number }}</strong></td><td><strong>{{ item.student.name || '—' }}</strong><small>{{ item.student.nis || '—' }}</small></td>
                <td v-if="type === 'savings'"><strong>{{ rupiah(item.balance) }}</strong></td><td v-else><strong>{{ rupiah(item.amount) }}</strong><small>Sisa {{ rupiah(item.outstanding) }}</small></td>
                <td v-if="type === 'savings'">{{ localDateTime(item.opened_at) }}</td><td v-else>{{ period(item) }}<small>{{ item.due_date || '—' }}</small></td>
                <td><span class="finance-status" :data-status="item.status.value">{{ item.status.label }}</span></td>
                <td><Link :href="type === 'savings' ? `/finance/savings/${item.uuid}` : `/finance/spp/${item.uuid}`" class="finance-link">Lihat <ArrowUpRight :size="15" aria-hidden="true"/></Link></td></tr></tbody>
            </table>
          </div>
          <nav class="finance-pager" aria-label="Halaman hasil"><span>{{ records.from }}–{{ records.to }} dari {{ records.total }} data</span><div><Link v-if="records.previous" :href="records.previous" class="button button-secondary" preserve-scroll><ChevronLeft :size="16"/>Sebelumnya</Link><span>Halaman {{ records.current_page }}/{{ records.last_page }}</span><Link v-if="records.next" :href="records.next" class="button button-secondary" preserve-scroll>Berikutnya <ChevronRight :size="16"/></Link></div></nav>
        </template>
      </section>
    </div>
  </AppLayout>
</template>
