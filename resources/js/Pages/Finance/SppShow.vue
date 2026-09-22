<script setup>
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, ReceiptText, CreditCard, History, ShieldCheck, ChevronLeft, ChevronRight, Inbox } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import FinanceActionForm from '../../Components/Finance/FinanceActionForm.vue'
import FinanceApprovalRequest from '../../Components/Finance/FinanceApprovalRequest.vue'
import { rupiah, localDateTime } from '../../lib/format.js'
import '../../../css/finance.css'
const props = defineProps({ finance: { type: Object, required: true } })
const bill = props.finance.bill
</script>
<template>
  <AppLayout title="Detail tagihan SPP" subtitle="Informasi tagihan dan pembayaran sesuai otorisasi akun Anda.">
    <div class="finance-page">
      <Link href="/finance?type=spp" class="finance-back"><ArrowLeft :size="16"/>Kembali ke daftar SPP</Link>
      <div class="finance-detail-grid"><section class="finance-hero"><div class="finance-hero-icon"><ReceiptText :size="23"/></div><span>Sisa tagihan {{ bill.number }}</span><strong>{{ rupiah(bill.outstanding) }}</strong><p>{{ bill.student.name }} · NIS {{ bill.student.nis }}</p><dl><div><dt>Nominal tagihan</dt><dd>{{ rupiah(bill.amount) }}</dd></div><div><dt>Sudah dibayar</dt><dd>{{ rupiah(bill.paid_amount) }}</dd></div><div><dt>Periode</dt><dd>{{ String(bill.period.month).padStart(2, '0') }}/{{ bill.period.year }}</dd></div><div><dt>Jatuh tempo</dt><dd>{{ bill.due_date || '—' }}</dd></div></dl><span class="finance-status" :data-status="bill.status.value">{{ bill.status.label }}</span></section>
        <div class="finance-side"><section v-if="finance.can.pay" class="panel"><div class="finance-card-title"><CreditCard :size="19"/><h2>Catat pembayaran</h2></div><FinanceActionForm kind="payment" label="Catat pembayaran" :endpoint="`/finance/spp/${bill.uuid}/payments`" :maximum="bill.outstanding" :methods="finance.methods"/></section>
          <div v-else class="finance-note"><ShieldCheck :size="17"/>Tidak ada tindakan pembayaran yang tersedia untuk akun atau tagihan ini.</div></div></div>
      <section class="panel" aria-labelledby="payment-history"><div class="finance-heading"><div><p class="finance-kicker">PEMBAYARAN</p><h2 id="payment-history"><History :size="20"/>Riwayat pembayaran</h2></div></div>
        <div v-if="finance.payments === null" class="finance-empty"><ShieldCheck :size="26"/><p>Riwayat pembayaran tidak termasuk hak akses akun ini.</p></div>
        <div v-else-if="!finance.payments.data.length" class="finance-empty"><Inbox :size="32"/><p>Belum ada pembayaran yang tercatat.</p></div>
        <template v-else><div class="finance-scroll" role="region" tabindex="0" aria-label="Riwayat pembayaran"><table class="finance-table"><thead><tr><th>Nomor / Kuitansi</th><th>Tanggal</th><th>Nominal</th><th>Metode</th><th>Status</th><th>Petugas / catatan</th><th>Pengajuan</th></tr></thead><tbody><tr v-for="p in finance.payments.data" :key="p.uuid"><td><strong>{{ p.number }}</strong><small>{{ p.receipt_number }}</small></td><td>{{ localDateTime(p.date) }}</td><td><strong>{{ rupiah(p.amount) }}</strong></td><td>{{ p.method.label }}</td><td><span class="finance-status" :data-status="p.status.value">{{ p.status.label }}</span></td><td>{{ p.recorded_by || '—' }}<small>{{ p.notes || '—' }}</small></td><td><FinanceApprovalRequest v-if="p.can_request_void" label="Ajukan pembatalan" :endpoint="`/finance/spp/${bill.uuid}/payments/${p.uuid}/void-requests`"/><span v-else>—</span></td></tr></tbody></table></div>
          <nav class="finance-pager" aria-label="Pagination pembayaran"><span>{{ finance.payments.from }}–{{ finance.payments.to }} dari {{ finance.payments.total }}</span><div><Link v-if="finance.payments.previous" :href="finance.payments.previous" class="button button-secondary"><ChevronLeft :size="16"/>Sebelumnya</Link><span>Halaman {{ finance.payments.current_page }}/{{ finance.payments.last_page }}</span><Link v-if="finance.payments.next" :href="finance.payments.next" class="button button-secondary">Berikutnya <ChevronRight :size="16"/></Link></div></nav>
        </template>
      </section>
    </div>
  </AppLayout>
</template>
