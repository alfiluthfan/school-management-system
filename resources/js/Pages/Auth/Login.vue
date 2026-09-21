<script setup>
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { BookOpenCheck, Eye, EyeOff, LockKeyhole, ShieldCheck, ArrowRight, GraduationCap } from 'lucide-vue-next'

const form = useForm({ identifier: '', password: '', remember: false })
const showPassword = ref(false)
const canSubmit = computed(() => !form.processing && form.identifier.trim() && form.password)
function submit() {
  form.post('/login', {
    onError: () => form.reset('password'),
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Masuk" />
  <div class="auth-screen">
    <section class="auth-story" aria-label="Tentang aplikasi">
      <div class="auth-brand"><span class="auth-brand-icon"><GraduationCap :size="27" aria-hidden="true" /></span><span>School<span class="brand-light">OS</span></span></div>
      <div class="story-content">
        <span class="eyebrow eyebrow-on-dark"><span class="eyebrow-dot"></span> Platform manajemen sekolah</span>
        <h1>Semua kegiatan sekolah, <em>dalam satu ruang kerja.</em></h1>
        <p>Kelola pembelajaran, pantau kehadiran, dan ikuti perkembangan administrasi melalui sistem yang aman dan terstruktur.</p>
        <div class="story-highlights">
          <div><BookOpenCheck :size="20" aria-hidden="true" /><span>Informasi lebih tertata</span></div>
          <div><ShieldCheck :size="20" aria-hidden="true" /><span>Akses sesuai peran pengguna</span></div>
        </div>
      </div>
      <span class="auth-story-footer">© {{ new Date().getFullYear() }} SchoolOS · Ruang kerja sekolah</span>
    </section>

    <main class="auth-form-panel">
      <div class="auth-mobile-brand"><GraduationCap :size="27" aria-hidden="true" /><strong>SchoolOS</strong></div>
      <div class="auth-card">
        <div class="login-icon"><LockKeyhole :size="25" aria-hidden="true" /></div>
        <span class="eyebrow">Selamat datang kembali</span>
        <h2>Masuk ke akun Anda</h2>
        <p class="form-intro">Gunakan email atau username sekolah yang sudah terdaftar.</p>

        <form class="auth-form" @submit.prevent="submit" novalidate>
          <div class="field">
            <label for="identifier">Email atau username</label>
            <input id="identifier" v-model="form.identifier" name="username" autocomplete="username" type="text" placeholder="nama@sekolah.sch.id" required :aria-invalid="Boolean(form.errors.identifier)" aria-describedby="identifier-error" autofocus />
            <p v-if="form.errors.identifier" id="identifier-error" class="field-error" role="alert">{{ form.errors.identifier }}</p>
          </div>
          <div class="field">
            <label for="password">Kata sandi</label>
            <div class="password-wrap">
              <input id="password" v-model="form.password" name="password" autocomplete="current-password" :type="showPassword ? 'text' : 'password'" placeholder="Masukkan kata sandi" required :aria-invalid="Boolean(form.errors.password)" aria-describedby="password-error" />
              <button class="reveal-password" type="button" :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" :aria-pressed="showPassword" @click="showPassword = !showPassword"><EyeOff v-if="showPassword" :size="19" /><Eye v-else :size="19" /></button>
            </div>
            <p v-if="form.errors.password" id="password-error" class="field-error" role="alert">{{ form.errors.password }}</p>
          </div>
          <label class="remember-row"><input v-model="form.remember" type="checkbox" name="remember" /><span>Ingat saya di perangkat ini</span></label>
          <button type="submit" class="button button-primary login-submit" :disabled="!canSubmit" :aria-busy="form.processing">
            <span>{{ form.processing ? 'Memverifikasi akun…' : 'Masuk ke dashboard' }}</span><ArrowRight :size="18" aria-hidden="true" />
          </button>
          <p class="form-help">Kesulitan mengakses akun? Hubungi administrator sekolah.</p>
        </form>
      </div>
      <p class="auth-footer">Akses berbasis sesi · Informasi sesuai hak akses Anda</p>
    </main>
  </div>
</template>
