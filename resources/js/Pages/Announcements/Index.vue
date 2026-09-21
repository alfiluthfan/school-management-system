<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Archive, ArrowUpRight, ChevronLeft, ChevronRight, Clock3, FileText, Filter, Inbox, Megaphone, Plus, RotateCcw, Search, ShieldCheck } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import AnnouncementStatus from '../../Components/Announcements/AnnouncementStatus.vue'
import { announcementDate } from '../../lib/announcement.js'
import '../../../css/announcements.css'

const props = defineProps({ announcements: { type: Object, required: true } })
const filters = reactive({ ...props.announcements.filters })
const busy = ref(false)
const error = ref('')
const records = computed(() => props.announcements.records)
const mine = computed(() => props.announcements.tab === 'mine')
watch(() => props.announcements.filters, value => Object.assign(filters, value))
function apply() {
  error.value = ''
  const payload = { tab: props.announcements.tab, per_page: filters.per_page }
  if (filters.search) payload.search = filters.search
  if (filters.target_scope) payload.target_scope = filters.target_scope
  if (mine.value && filters.status) payload.status = filters.status
  router.get('/announcements', payload, { preserveState: true, preserveScroll: true, replace: true,
    onStart: () => { busy.value = true }, onFinish: () => { busy.value = false },
    onError: errors => { error.value = Object.values(errors)[0] || 'Gagal memuat pengumuman.' },
  })
}
function reset() { Object.assign(filters, { search: '', target_scope: '', status: '', per_page: 20 }); apply() }
const tiles = [
  { key: 'total', label: 'Semua', value: '', icon: Megaphone },
  { key: 'draft', label: 'Draft', value: 'DRAFT', icon: FileText },
  { key: 'published', label: 'Dipublikasikan', value: 'PUBLISHED', icon: Clock3 },
  { key: 'archived', label: 'Arsip', value: 'ARCHIVED', icon: Archive },
]
</script>
<template>
  <AppLayout title="Pengumuman" subtitle="Informasi sekolah dan kelas yang sesuai dengan akses akun Anda.">
    <div class="ann-page">
      <div class="ann-topline">
        <nav class="ann-tabs" aria-label="Jenis pengumuman">
          <Link v-for="tab in announcements.tabs" :key="tab.value" :href="`/announcements?tab=${tab.value}`" class="ann-tab" :class="{ selected: announcements.tab === tab.value }" :aria-current="announcements.tab === tab.value ? 'page' : undefined">
            <Megaphone v-if="tab.value === 'feed'" :size="17" aria-hidden="true" /><FileText v-else :size="17" aria-hidden="true" />{{ tab.label }}
          </Link>
        </nav>
        <Link v-if="announcements.can.create" href="/announcements/create" class="button button-primary"><Plus :size="17" aria-hidden="true"/> Buat pengumuman</Link>
      </div>
      <div class="ann-note"><ShieldCheck :size="18" aria-hidden="true"/><span>{{ mine ? 'Hanya draft dan pengumuman yang Anda buat ditampilkan di sini.' : 'Feed mengikuti role, keanggotaan kelas aktif, dan waktu publikasi yang diperiksa oleh server.' }}</span></div>
      <div v-if="mine && announcements.counts" class="ann-stats" aria-label="Status pengumuman saya">
        <button v-for="tile in tiles" :key="tile.key" type="button" class="ann-stat" :class="{ selected: filters.status === tile.value }" :aria-pressed="filters.status === tile.value" @click="filters.status=tile.value; apply()"><span><component :is="tile.icon" :size="17" aria-hidden="true"/>{{ tile.label }}</span><strong>{{ announcements.counts[tile.key] ?? 0 }}</strong></button>
      </div>
      <section class="panel ann-panel" aria-labelledby="ann-list-title">
        <header class="ann-heading"><div><p class="ann-kicker">KOMUNIKASI SEKOLAH</p><h2 id="ann-list-title">{{ mine ? 'Pengumuman yang saya kelola' : 'Informasi terbaru untuk saya' }}</h2><p>Gunakan pencarian untuk menemukan pengumuman yang dibutuhkan.</p></div><span class="ann-total">{{ records.total }} pengumuman</span></header>
        <form class="ann-filters" @submit.prevent="apply">
          <label class="ann-field ann-search">Cari pengumuman<div class="ann-search-box"><Search :size="17" aria-hidden="true"/><input v-model.trim="filters.search" type="search" maxlength="100" placeholder="Cari judul atau isi" /></div></label>
          <label class="ann-field">Cakupan<select v-model="filters.target_scope"><option value="">Semua cakupan</option><option v-for="item in announcements.options.scopes" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
          <label v-if="mine" class="ann-field">Status<select v-model="filters.status"><option value="">Semua status</option><option v-for="item in announcements.options.statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
          <label class="ann-field ann-small">Per halaman<select v-model.number="filters.per_page"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option></select></label>
          <div class="ann-filter-actions"><button class="button button-primary" type="submit" :disabled="busy"><Filter :size="16" aria-hidden="true"/> {{ busy ? 'Memuat…' : 'Terapkan' }}</button><button type="button" class="button button-secondary" :disabled="busy" @click="reset"><RotateCcw :size="16" aria-hidden="true"/> Reset</button></div>
        </form>
        <p v-if="error" role="alert" class="ann-error">{{ error }}</p>
        <div v-if="!records.data.length" class="ann-empty" role="status"><Inbox :size="37" aria-hidden="true"/><h3>{{ mine ? 'Belum ada pengumuman Anda' : 'Belum ada pengumuman untuk Anda' }}</h3><p>Belum ada informasi yang sesuai dengan akses dan filter ini.</p><button type="button" class="button button-secondary" @click="reset">Bersihkan filter</button></div>
        <template v-else>
          <div class="ann-list">
            <article v-for="item in records.data" :key="item.uuid" class="ann-card">
              <div class="ann-card-top"><AnnouncementStatus :status="item.status" :visibility="item.visibility"/><span class="ann-scope">{{ item.scope.label }}<template v-if="item.school_class"> · {{ item.school_class.name }}</template></span></div>
              <h3><Link :href="`/announcements/${encodeURIComponent(item.uuid)}`">{{ item.title }}</Link></h3>
              <p class="ann-excerpt">{{ item.excerpt }}</p>
              <div class="ann-card-foot"><span><Clock3 :size="14" aria-hidden="true"/> {{ announcementDate(item.publish_at || item.created_at) }}</span><span>{{ item.creator?.name || 'Sekolah' }}</span><Link :href="`/announcements/${encodeURIComponent(item.uuid)}`" class="ann-detail-link" :aria-label="`Buka pengumuman ${item.title}`">Baca detail <ArrowUpRight :size="15" aria-hidden="true"/></Link></div>
            </article>
          </div>
          <nav class="ann-pager" aria-label="Halaman pengumuman"><span>Menampilkan {{ records.from }}–{{ records.to }} dari {{ records.total }}</span><div><Link v-if="records.previous" :href="records.previous" class="button button-secondary" preserve-scroll><ChevronLeft :size="16" aria-hidden="true"/> Sebelumnya</Link><span>Halaman {{ records.current_page }}/{{ records.last_page }}</span><Link v-if="records.next" :href="records.next" class="button button-secondary" preserve-scroll>Berikutnya <ChevronRight :size="16" aria-hidden="true"/></Link></div></nav>
        </template>
      </section>
    </div>
  </AppLayout>
</template>
