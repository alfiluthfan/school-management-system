<script setup>
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, CalendarClock, Megaphone, Pencil, ShieldCheck, Users } from 'lucide-vue-next'
import AppLayout from '../../Layouts/AppLayout.vue'
import AnnouncementStatus from '../../Components/Announcements/AnnouncementStatus.vue'
import AnnouncementLifecycleActions from '../../Components/Announcements/AnnouncementLifecycleActions.vue'
import { announcementDate } from '../../lib/announcement.js'
import '../../../css/announcements.css'

const props = defineProps({ announcement: { type: Object, required: true } })
</script>
<template>
  <AppLayout title="Detail pengumuman" subtitle="Informasi yang telah diperiksa dan dibatasi menurut akses akun Anda.">
    <div class="ann-page ann-detail-page">
      <Link :href="announcement.mine ? '/announcements?tab=mine' : '/announcements'" class="ann-back"><ArrowLeft :size="16" aria-hidden="true"/> {{ announcement.mine ? 'Kembali ke pengumuman saya' : 'Kembali ke feed' }}</Link>
      <article class="panel ann-detail" aria-labelledby="ann-detail-title">
        <div class="ann-detail-top"><span class="ann-detail-icon"><Megaphone :size="23" aria-hidden="true"/></span><AnnouncementStatus :status="announcement.status" :visibility="announcement.visibility"/></div>
        <p class="ann-kicker">{{ announcement.scope.label }}<template v-if="announcement.school_class"> · {{ announcement.school_class.name }}</template></p>
        <h2 id="ann-detail-title">{{ announcement.title }}</h2>
        <div class="ann-byline"><span>{{ announcement.creator?.name || 'Sekolah' }}</span><span>·</span><span>Dibuat {{ announcementDate(announcement.created_at) }}</span></div>
        <div v-if="announcement.visibility === 'scheduled'" class="ann-note"><CalendarClock :size="18" aria-hidden="true"/> Belum terlihat oleh penerima. Dijadwalkan terbit {{ announcementDate(announcement.publish_at) }}.</div>
        <div v-if="announcement.status.value === 'DRAFT'" class="ann-note"><ShieldCheck :size="18" aria-hidden="true"/> Ini masih draft dan hanya terlihat oleh pembuatnya.</div>
        <div class="ann-content">{{ announcement.content }}</div>
        <dl class="ann-metadata"><div><dt>Cakupan</dt><dd>{{ announcement.scope.label }}{{ announcement.school_class ? ` · ${announcement.school_class.name}` : '' }}</dd></div><div><dt>Publikasi</dt><dd>{{ announcementDate(announcement.publish_at) }}</dd></div><div><dt>Berakhir</dt><dd>{{ announcementDate(announcement.expired_at) }}</dd></div><div v-if="announcement.target_roles"><dt>Penerima yang dipilih</dt><dd>{{ announcement.target_roles.join(', ') }}</dd></div></dl>
        <div v-if="announcement.can.update || announcement.can.publish || announcement.can.archive" class="ann-detail-actions"><Link v-if="announcement.can.update" :href="`/announcements/${encodeURIComponent(announcement.uuid)}/edit`" class="button button-secondary"><Pencil :size="16" aria-hidden="true"/> Edit draft</Link><AnnouncementLifecycleActions :announcement="announcement"/></div>
        <div v-else class="ann-note ann-readonly"><Users :size="17" aria-hidden="true"/> Pengumuman ini tersedia untuk dibaca. Tidak ada tindakan pengelolaan untuk akun Anda.</div>
      </article>
    </div>
  </AppLayout>
</template>
