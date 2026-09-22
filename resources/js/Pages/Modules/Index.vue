<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import { ClipboardCheck, CheckCheck, Wallet, Megaphone, FileChartColumn, FileDown, LockKeyhole, CheckCircle2, ArrowUpRight, Database } from 'lucide-vue-next'

const page = usePage()
const permissions = computed(() => page.props.auth?.user?.permissions ?? [])
const has = (names) => names.some((name) => permissions.value.includes(name))
const modules = [
  { title: 'Absensi', href: '/attendance', description: 'Kehadiran siswa, guru, dan check-in mandiri.', icon: ClipboardCheck, permissions: ['student-attendance.view.all','student-attendance.view.class','student-attendance.view.own','student-attendance.view.child','teacher-attendance.view.own','teacher-attendance.view.all'] },
  { title: 'Persetujuan', href: '/approvals', description: 'Approval koreksi absensi, izin guru, dan transaksi.', icon: CheckCheck, permissions: ['approval.view.own','approval.view.all'] },
  { title: 'Keuangan', href: '/finance', description: 'Saldo tabungan, tagihan SPP, dan transaksi.', icon: Wallet, permissions: ['saving.balance.view.all','saving.balance.view.own','saving.balance.view.child','spp.bill.view.all','spp.bill.view.own','spp.bill.view.child'] },
  { title: 'Pengumuman', href: '/announcements', description: 'Berita sekolah dan kelas berdasarkan audience.', icon: Megaphone, permissions: ['announcement.view'] },
  { title: 'Laporan', href: '/reports', description: 'Analitik kehadiran, SPP, dan tabungan.', icon: FileChartColumn, permissions: ['report.attendance.student','report.attendance.teacher','report.saving','report.spp'] },
  { title: 'Master Data', href: '/master-data', description: 'Akun, profil akademik, kelas, dan tahun ajaran.', icon: Database, permissions: ['user.view.all','student.view.all','student.view.class','teacher.view.all','parent.view.all','class.view.all','class.view.assigned','academic-year.view'] },
  { title: 'Ekspor laporan', href: '/reports', description: 'Antrean PDF/Excel dengan download privat.', icon: FileDown, permissions: ['report.export.pdf','report.export.excel'] },
]
</script>
<template>
  <AppLayout title="Modul aplikasi" subtitle="Fitur yang tersedia sesuai izin akses Anda dan peta modul berikutnya.">
    <section class="panel roadmap-intro"><div class="roadmap-icon"><CheckCircle2 :size="23" aria-hidden="true" /></div><div><h2>Seluruh modul operasional dan pelaporan terhubung</h2><p>Login, dashboard, absensi, persetujuan, keuangan, pengumuman, serta pelaporan dan ekspor sudah terhubung ke backend sesuai izin akses.</p></div></section>
    <div class="section-heading"><div><p class="page-eyebrow">MODUL SEKOLAH</p><h2>Ruang kerja per modul</h2></div></div>
    <div class="module-grid"><article v-for="(module, index) in modules" :key="module.title" class="module-card" :class="{ 'module-unavailable': !has(module.permissions) }"><div class="module-card-top"><span class="module-icon"><component :is="module.icon" :size="23" aria-hidden="true" /></span><span class="phase-label">Tahap {{ index + 2 }}</span></div><h3>{{ module.title }}</h3><p>{{ module.description }}</p><Link v-if="module.href && has(module.permissions)" :href="module.href" class="module-access" :aria-label="`Buka modul ${module.title}`"><CheckCircle2 :size="15" aria-hidden="true" /> Buka modul <ArrowUpRight :size="15" aria-hidden="true" /></Link><span v-else-if="has(module.permissions)" class="module-access"><CheckCircle2 :size="15" aria-hidden="true" /> Izin dasar tersedia · UI menyusul</span><span v-else class="module-access module-access-muted"><LockKeyhole :size="15" aria-hidden="true" /> Tidak termasuk akses akun ini</span></article></div>
  </AppLayout>
</template>
