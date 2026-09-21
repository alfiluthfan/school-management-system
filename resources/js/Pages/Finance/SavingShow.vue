<script setup>
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, Wallet, ArrowDownToLine, ArrowUpFromLine, History, ShieldCheck, ChevronLeft, ChevronRight, Inbox } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import FinanceActionForm from '../../Components/Finance/FinanceActionForm.vue'
import FinanceApprovalRequest from '../../Components/Finance/FinanceApprovalRequest.vue'
import { rupiah, localDateTime } from '../../lib/format.js'
import '../../../css/finance.css'
const props = defineProps({ finance: { type: Object, required: true } })
const account = props.finance.account
</script>
<template>
  <AppLayout title="Detail tabungan" subtitle="Saldo, informasi rekening, serta transaksi yang boleh Anda lihat.">
    <div class="finance-page">
      <Link href="/finance?type=savings" class="finance-back"><ArrowLeft :size="16"/>Kembali ke daftar tabungan</Link>
      <div class="finance-detail-grid"><section class="finance-hero"><div class="finance-hero-icon"><Wallet :size="23"/></div><span>Saldo rekening {{ account.number }}</span><strong>{{ rupiah(account.balance) }}</strong><p>{{ account.student.name }} · NIS {{ account.student.nis }}</p><span class="finance-status" :data-status="account.status.value">{{ account.status.label }}</span></section>
        <div class="finance-side"><section v-if="finance.can.deposit" class="panel"><div class="finance-card-title"><ArrowDownToLine :size="19"/><h2>Setoran baru</h2></div><FinanceActionForm kind="deposit" label="Catat setoran" :endpoint="`/finance/savings/${account.uuid}/deposit`"/></section>
          <section v-if="finance.can.withdraw" class="panel"><div class="finance-card-title"><ArrowUpFromLine :size="19"/><h2>Penarikan</h2></div><FinanceActionForm kind="withdraw" label="Catat penarikan" :maximum="account.balance" :endpoint="`/finance/savings/${account.uuid}/withdraw`"/></section>
          <div v-if="!finance.can.deposit && !finance.can.withdraw" class="finance-note"><ShieldCheck :size="17"/>Transaksi hanya dapat dicatat oleh petugas yang berwenang.</div>
        </div></div>
      <section class="panel" aria-labelledby="saving-history"><div class="finance-heading"><div><p class="finance-kicker">MUTASI REKENING</p><h2 id="saving-history"><History :size="20"/> Riwayat transaksi</h2></div></div>
        <div v-if="finance.transactions === null" class="finance-empty"><ShieldCheck :size="26"/><p>Riwayat transaksi tidak termasuk hak akses akun ini.</p></div>
        <div v-else-if="!finance.transactions.data.length" class="finance-empty"><Inbox :size="32"/><p>Belum ada transaksi tercatat.</p></div>
        <template v-else><div class="finance-scroll" role="region" tabindex="0" aria-label="Mutasi rekening"><table class="finance-table"><thead><tr><th>Nomor transaksi</th><th>Tanggal</th><th>Jenis</th><th>Nominal</th><th>Saldo sesudah</th><th>Pengajuan</th></tr></thead><tbody><tr v-for="t in finance.transactions.data" :key="t.uuid"><td><strong>{{ t.number }}</strong><small>{{ t.description || '—' }}</small></td><td>{{ localDateTime(t.date) }}</td><td>{{ t.type.label }}</td><td><strong>{{ rupiah(t.amount) }}</strong></td><td>{{ rupiah(t.balance_after) }}</td><td><FinanceApprovalRequest v-if="t.can_request_reversal" label="Ajukan reversal" :endpoint="`/finance/savings/${account.uuid}/transactions/${t.uuid}/reversal-requests`"/><span v-else>—</span></td></tr></tbody></table></div>
          <nav class="finance-pager" aria-label="Pagination mutasi"><span>{{ finance.transactions.from }}–{{ finance.transactions.to }} dari {{ finance.transactions.total }}</span><div><Link v-if="finance.transactions.previous" :href="finance.transactions.previous" class="button button-secondary"><ChevronLeft :size="16"/>Sebelumnya</Link><span>Halaman {{ finance.transactions.current_page }}/{{ finance.transactions.last_page }}</span><Link v-if="finance.transactions.next" :href="finance.transactions.next" class="button button-secondary">Berikutnya <ChevronRight :size="16"/></Link></div></nav>
        </template>
      </section>
    </div>
  </AppLayout>
</template>
