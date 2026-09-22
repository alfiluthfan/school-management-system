<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { Search, Wallet, ReceiptText, ArrowDownCircle, ArrowUpCircle, ShieldCheck, History, ArrowLeft } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import { rupiah, localDateTime } from '../../lib/format.js'
import '../../../css/finance-cashier.css'

const props = defineProps({ cashier: { type: Object, required: true } })
const query = ref(props.cashier.search || '')
const selected = computed(() => props.cashier.selected)
const tab = ref('DEPOSIT')
const newKey = () => crypto.randomUUID()
const saving = useForm({ student_uuid: selected.value?.uuid ?? '', request_key: newKey(), operation: 'DEPOSIT', amount: '', description: '' })
const payment = useForm({ student_uuid: selected.value?.uuid ?? '', request_key: newKey(), bill_uuid: '', amount: '', notes: '' })
const outstandingBills = computed(() => selected.value?.bills.filter(b => b.status !== 'CANCELLED' && Number(b.remaining) > 0) || [])
watch(() => props.cashier.selected?.uuid, (id) => {
  saving.reset(); saving.clearErrors(); saving.student_uuid = id || ''; saving.request_key = newKey()
  payment.reset(); payment.clearErrors(); payment.student_uuid = id || ''; payment.request_key = newKey()
  payment.bill_uuid = ''; tab.value = 'DEPOSIT'
})
function findStudent() {
  router.get('/finance/cashier', { search: query.value.trim() }, { preserveState: true, replace: true, preserveScroll: true })
}
function chooseStudent(uuid) {
  router.get('/finance/cashier', { student: uuid, search: query.value.trim() }, { preserveState: true, preserveScroll: true })
}
function recordSaving() {
  saving.operation = tab.value
  if (!confirm(`${tab.value === 'DEPOSIT' ? 'Konfirmasi setoran' : 'Konfirmasi penarikan'} ${rupiah(saving.amount)} untuk ${selected.value.name}?`)) return
  saving.post('/finance/cashier/savings', { preserveScroll: true, onSuccess: () => {
    saving.reset('amount', 'description'); saving.request_key = newKey()
  } })
}
function recordPayment() {
  if (!confirm(`Konfirmasi pembayaran SPP tunai ${rupiah(payment.amount)} untuk ${selected.value.name}?`)) return
  payment.post('/finance/cashier/spp', { preserveScroll: true, onSuccess: () => {
    payment.reset('amount', 'notes'); payment.request_key = newKey()
  } })
}
</script>

<template>
  <AppLayout title="Kasir keuangan" subtitle="Cari siswa, kemudian catat transaksi tunai dengan tanggal otomatis dari server.">
    <div class="cashier-page">
      <div class="cashier-top"><Link href="/finance" class="button button-secondary"><ArrowLeft :size="16"/> Kembali ke keuangan</Link><span class="cashier-notice"><ShieldCheck :size="16"/> Transaksi tercatat dan dapat dilihat siswa/orang tua sesuai hak akses.</span></div>
      <section class="cashier-panel" aria-labelledby="cashier-search-title">
        <h2 id="cashier-search-title"><Search :size="20"/> Cari siswa</h2>
        <form class="cashier-search" @submit.prevent="findStudent"><label for="cashier-query">Nama atau NIS (minimal 2 karakter)</label><div><input id="cashier-query" v-model="query" type="search" maxlength="100" placeholder="Ketik nama atau NIS siswa" autocomplete="off"/><button class="button button-primary" type="submit">Cari</button></div></form>
        <p v-if="cashier.search && cashier.search.trim().length < 2" class="cashier-help">Masukkan minimal 2 karakter untuk memulai pencarian.</p>
        <p v-if="cashier.search && cashier.search.trim().length >= 2 && !cashier.results.length" class="cashier-help">Tidak ada siswa aktif yang cocok.</p>
        <div v-if="cashier.results.length" class="cashier-results"><button v-for="item in cashier.results" :key="item.uuid" type="button" :class="{ current: item.uuid === selected?.uuid }" @click="chooseStudent(item.uuid)"><strong>{{ item.name }}</strong><small>NIS {{ item.nis }}</small></button></div>
        <small class="cashier-help">Maksimal 20 hasil; persempit kata pencarian jika siswa belum ditemukan.</small>
      </section>
      <template v-if="selected">
        <section class="cashier-panel cashier-student"><div><p>SISWA DIPILIH</p><h2>{{ selected.name }}</h2><span>NIS {{ selected.nis }}</span></div><div><small>Saldo tabungan</small><strong>{{ rupiah(selected.account?.balance || '0') }}</strong><small v-if="!selected.account">Rekening otomatis dibuka saat setoran pertama.</small><small v-else>Status: {{ selected.account.status }}</small></div></section>
        <div class="cashier-columns">
          <section class="cashier-panel" aria-labelledby="cashier-saving-title"><h2 id="cashier-saving-title"><Wallet :size="21"/> Transaksi tabungan</h2>
            <div class="cashier-switch"><button v-if="cashier.can.deposit" type="button" :class="{ active: tab === 'DEPOSIT' }" @click="tab = 'DEPOSIT'"><ArrowDownCircle :size="16"/> Setoran</button><button v-if="cashier.can.withdraw" type="button" :class="{ active: tab === 'WITHDRAW' }" @click="tab = 'WITHDRAW'"><ArrowUpCircle :size="16"/> Penarikan</button></div>
            <form v-if="cashier.can.deposit || cashier.can.withdraw" @submit.prevent="recordSaving" class="cashier-form"><label>Nominal (Rp)<input v-model="saving.amount" type="number" min="0.01" max="9999999999.99" step="0.01" required placeholder="Contoh: 20000"/></label><p v-if="saving.errors.amount" role="alert" class="cashier-error">{{ saving.errors.amount }}</p>
              <label>{{ tab === 'WITHDRAW' ? 'Alasan penarikan (wajib)' : 'Keterangan setoran (opsional)' }}<textarea v-model="saving.description" :required="tab === 'WITHDRAW'" rows="3" maxlength="500" placeholder="Catatan transaksi"/></label><p v-if="saving.errors.description" role="alert" class="cashier-error">{{ saving.errors.description }}</p><p v-if="saving.errors.student_uuid || saving.errors.request_key" role="alert" class="cashier-error">{{ saving.errors.student_uuid || saving.errors.request_key }}</p>
              <button class="button button-primary" type="submit" :disabled="saving.processing || (tab === 'WITHDRAW' && (!selected.account || selected.account.status !== 'ACTIVE'))">{{ saving.processing ? 'Menyimpan…' : tab === 'DEPOSIT' ? 'Catat setoran' : 'Catat penarikan' }}</button><small>Waktu, pencatat, dan saldo setelah transaksi ditetapkan oleh server.</small></form>
            <p v-else class="cashier-help">Anda tidak memiliki izin transaksi tabungan.</p>
          </section>
          <section class="cashier-panel" aria-labelledby="cashier-spp-title"><h2 id="cashier-spp-title"><ReceiptText :size="21"/> Pembayaran SPP tunai</h2><form v-if="cashier.can.pay" @submit.prevent="recordPayment" class="cashier-form"><label>Tagihan bulan<select v-model="payment.bill_uuid" required><option value="" disabled>Pilih tagihan belum lunas</option><option v-for="bill in outstandingBills" :key="bill.uuid" :value="bill.uuid">{{ bill.period }} · sisa {{ rupiah(bill.remaining) }}</option></select></label><p v-if="payment.errors.bill_uuid" role="alert" class="cashier-error">{{ payment.errors.bill_uuid }}</p><label>Nominal pembayaran (Rp)<input v-model="payment.amount" type="number" min="0.01" max="9999999999.99" step="0.01" required placeholder="Contoh: 150000"/></label><p v-if="payment.errors.amount || payment.errors.bill" role="alert" class="cashier-error">{{ payment.errors.amount || payment.errors.bill }}</p><label>Catatan (opsional)<textarea v-model="payment.notes" rows="3" maxlength="1000" placeholder="Contoh: pembayaran tunai di loket"/></label><p v-if="payment.errors.request_key" role="alert" class="cashier-error">{{ payment.errors.request_key }}</p><button class="button button-primary" type="submit" :disabled="payment.processing || !outstandingBills.length">{{ payment.processing ? 'Menyimpan…' : 'Catat pembayaran CASH' }}</button><small v-if="!outstandingBills.length">Belum ada tagihan terbuka. Tagihan dibuat oleh generator SPP bulanan.</small><small>Kuitansi, tanggal, dan Admin pencatat dibuat oleh server.</small></form><p v-else class="cashier-help">Anda tidak memiliki izin pembayaran SPP.</p></section>
        </div>
        <div class="cashier-columns">
          <section class="cashier-panel"><h2><History :size="20"/> Riwayat tabungan</h2><p v-if="!selected.savings_history.length" class="cashier-help">Belum ada transaksi.</p><div v-for="item in selected.savings_history" :key="item.number" class="cashier-entry"><strong>{{ item.type }} · {{ rupiah(item.amount) }}</strong><small>{{ localDateTime(item.date) }} · Saldo {{ rupiah(item.balance_after) }} · {{ item.recorded_by || '—' }}</small><small>{{ item.description || 'Tanpa keterangan' }}</small></div><Link v-if="selected.account" :href="`/finance/savings/${selected.account.uuid}`">Lihat riwayat lengkap →</Link></section>
          <section class="cashier-panel"><h2><History :size="20"/> Tagihan & pembayaran</h2><p v-if="!selected.bills.length" class="cashier-help">Belum ada tagihan SPP bulanan.</p><div v-for="bill in selected.bills" :key="bill.uuid" class="cashier-entry"><strong>{{ bill.period }} · {{ bill.status }}</strong><small>Total {{ rupiah(bill.amount) }} · Sisa {{ rupiah(bill.remaining) }}</small><Link :href="`/finance/spp/${bill.uuid}`">Lihat tagihan & kuitansi →</Link></div><h3 v-if="selected.payments.length">Pembayaran terbaru</h3><div v-for="item in selected.payments" :key="item.receipt" class="cashier-entry"><strong>{{ item.receipt }} · {{ rupiah(item.amount) }}</strong><small>{{ localDateTime(item.date) }} · {{ item.recorded_by || '—' }} · {{ item.notes || 'Pembayaran tunai' }}</small></div></section>
        </div>
      </template>
      <section v-else class="cashier-panel cashier-help">Pilih siswa melalui pencarian untuk mulai mencatat setoran, penarikan, atau pembayaran.</section>
    </div>
  </AppLayout>
</template>
