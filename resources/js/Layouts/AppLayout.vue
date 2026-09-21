<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { LayoutDashboard, Boxes, ClipboardCheck, GraduationCap, Menu, X, LogOut, ChevronRight, PanelLeftClose, ShieldCheck } from 'lucide-vue-next'

const props = defineProps({ title: { type: String, default: 'Dashboard' }, subtitle: { type: String, default: '' } })
const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const roles = computed(() => user.value?.roles ?? [])
const role = computed(() => ({ admin: 'Administrator', principal: 'Kepala Sekolah', teacher: 'Guru', student: 'Siswa', parent: 'Orang Tua / Wali' }[roles.value[0]] ?? 'Pengguna'))
const initials = computed(() => (user.value?.name ?? '?').split(/\s+/).slice(0, 2).map(x => x[0]?.toUpperCase()).join(''))
const drawerOpen = ref(false)
const busy = ref(false)
let offStart = null
let offFinish = null
let offNavigate = null
onMounted(() => {
  offStart = router.on('start', () => { busy.value = true })
  offFinish = router.on('finish', () => { busy.value = false })
  offNavigate = router.on('navigate', () => { drawerOpen.value = false })
})
onUnmounted(() => { offStart?.(); offFinish?.(); offNavigate?.() })
const canAttendance = computed(() => ['student-attendance.view.all', 'student-attendance.view.class', 'student-attendance.view.own', 'student-attendance.view.child', 'teacher-attendance.view.all', 'teacher-attendance.view.own'].some(permission => user.value?.permissions?.includes(permission)))
const active = (route) => page.url === route || page.url.startsWith(`${route}?`)
</script>

<template>
  <Head :title="title" />
  <a class="skip-link" href="#main-content">Lewati navigasi ke konten</a>
  <div class="app-shell">
    <button v-if="drawerOpen" class="drawer-scrim" type="button" aria-label="Tutup menu navigasi" @click="drawerOpen = false"></button>
    <aside class="sidebar" :class="{ 'sidebar-open': drawerOpen }" aria-label="Navigasi utama">
      <div class="side-head"><Link href="/dashboard" class="brand" aria-label="SchoolOS, ke dashboard"><span class="brand-icon"><GraduationCap :size="26" aria-hidden="true" /></span><span>School<span class="brand-accent">OS</span><small>Management</small></span></Link><button type="button" class="mobile-close icon-button" aria-label="Tutup navigasi" @click="drawerOpen = false"><X :size="21" /></button></div>
      <nav class="nav-list" aria-label="Menu aplikasi">
        <p class="nav-label">RUANG KERJA</p>
        <Link class="nav-link" href="/dashboard" :class="{ 'nav-active': active('/dashboard') }" :aria-current="active('/dashboard') ? 'page' : undefined"><LayoutDashboard :size="19" aria-hidden="true" /><span>Dashboard</span><ChevronRight v-if="active('/dashboard')" :size="15" class="nav-chevron" aria-hidden="true" /></Link>
        <Link class="nav-link" href="/modules" :class="{ 'nav-active': active('/modules') }" :aria-current="active('/modules') ? 'page' : undefined"><Boxes :size="19" aria-hidden="true" /><span>Modul aplikasi</span><ChevronRight v-if="active('/modules')" :size="15" class="nav-chevron" aria-hidden="true" /></Link>
        <Link v-if="canAttendance" class="nav-link" href="/attendance" :class="{ 'nav-active': active('/attendance') }" :aria-current="active('/attendance') ? 'page' : undefined"><ClipboardCheck :size="19" aria-hidden="true" /><span>Absensi</span><ChevronRight v-if="active('/attendance')" :size="15" class="nav-chevron" aria-hidden="true" /></Link>
        <p class="nav-label nav-next-label">AKAN DIINTEGRASIKAN</p>
        <div class="nav-disabled" title="Fitur akan tersedia pada fase integrasi berikutnya"><span>Persetujuan</span><small>Berikutnya</small></div>
        <div class="nav-disabled" title="Fitur akan tersedia pada fase integrasi berikutnya"><span>Keuangan · Pengumuman</span><small>Berikutnya</small></div>
        <div class="nav-disabled" title="Fitur akan tersedia pada fase integrasi berikutnya"><span>Laporan · Ekspor</span><small>Berikutnya</small></div>
      </nav>
      <div class="sidebar-bottom"><div class="sidebar-secure"><ShieldCheck :size="18" aria-hidden="true"/><span>Data mengikuti izin akses server.</span></div><div class="sidebar-account"><div class="avatar" aria-hidden="true">{{ initials }}</div><div class="user-summary"><strong>{{ user?.name }}</strong><span>{{ role }}</span></div><Link href="/logout" method="post" as="button" type="button" class="logout-button" title="Keluar" aria-label="Keluar dari akun"><LogOut :size="19" aria-hidden="true" /></Link></div></div>
    </aside>

    <div class="workspace">
      <header class="topbar"><div class="topbar-title"><button type="button" class="menu-button icon-button" aria-label="Buka navigasi" :aria-expanded="drawerOpen" @click="drawerOpen = true"><Menu :size="22" aria-hidden="true" /></button><div><span class="topbar-kicker">SchoolOS / Ruang kerja</span><strong>{{ title }}</strong></div></div><div class="topbar-actions"><span class="topbar-role">{{ role }}</span><div class="avatar avatar-light" :title="user?.name" aria-hidden="true">{{ initials }}</div></div></header>
      <div v-if="busy" class="navigation-progress" role="status" aria-live="polite"><span>Memuat halaman…</span></div>
      <main id="main-content" class="main-content" tabindex="-1"><div class="page-heading"><div><p class="page-eyebrow">Sistem informasi sekolah</p><h1>{{ title }}</h1><p v-if="subtitle" class="page-subtitle">{{ subtitle }}</p></div><slot name="actions" /></div><div v-if="$page.props.flash?.success" class="alert alert-success" role="status">{{ $page.props.flash.success }}</div><slot /></main>
    </div>
  </div>
</template>
