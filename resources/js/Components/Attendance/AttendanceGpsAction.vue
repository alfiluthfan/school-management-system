<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { MapPin, LoaderCircle, LogIn, LogOut, AlertCircle } from 'lucide-vue-next'

const props = defineProps({
  kind: { type: String, required: true }, // students | teachers
  action: { type: String, required: true }, // in | out
})
const form = useForm({ latitude: null, longitude: null, accuracy: null })
const locating = ref(false)
const gpsError = ref('')
const working = computed(() => locating.value || form.processing)
const label = computed(() => props.action === 'in' ? 'Check-in' : 'Check-out')
const route = computed(() => `/attendance/${props.kind}/check-${props.action}`)
let active = true
onBeforeUnmount(() => { active = false })

function positionError(error) {
  if (error?.code === 1) return 'Izin lokasi ditolak. Izinkan akses lokasi untuk situs ini melalui pengaturan browser, lalu coba lagi.'
  if (error?.code === 2) return 'Lokasi belum tersedia. Aktifkan layanan lokasi/GPS dan coba lagi di area sekolah.'
  if (error?.code === 3) return 'Pencarian lokasi melebihi 15 detik. Coba lagi di area yang memiliki sinyal lebih baik.'
  return 'Lokasi tidak dapat diperoleh. Periksa pengaturan perangkat dan coba lagi.'
}

function submitWithLocation() {
  if (working.value) return
  gpsError.value = ''
  form.clearErrors()
  if (typeof window === 'undefined' || !window.isSecureContext) {
    gpsError.value = 'Akses lokasi memerlukan HTTPS. Untuk pengembangan, buka melalui localhost atau 127.0.0.1.'
    return
  }
  if (!navigator.geolocation) {
    gpsError.value = 'Browser ini tidak mendukung akses lokasi. Gunakan browser/perangkat yang mendukung GPS.'
    return
  }
  locating.value = true
  // Explicit user click only. Do not continuously track location or cache it.
  navigator.geolocation.getCurrentPosition(
    (position) => {
      if (!active) return
      locating.value = false
      const { latitude, longitude, accuracy } = position.coords
      if (![latitude, longitude, accuracy].every(Number.isFinite)) {
        gpsError.value = 'Data lokasi tidak valid. Perbarui lokasi dan coba lagi.'
        return
      }
      form.latitude = latitude
      form.longitude = longitude
      form.accuracy = Math.round(accuracy * 100) / 100
      form.post(route.value, {
        preserveScroll: true,
        // Laravel redirects validation errors into Inertia form errors.
        onFinish: () => form.reset('latitude', 'longitude', 'accuracy'),
      })
    },
    (error) => {
      if (!active) return
      locating.value = false
      gpsError.value = positionError(error)
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
  )
}
</script>

<template>
  <div class="gps-action">
    <button type="button" class="button button-primary gps-submit" :disabled="working"
      :aria-busy="working" @click="submitWithLocation">
      <LoaderCircle v-if="working" :size="19" class="spinning" aria-hidden="true" />
      <LogIn v-else-if="action === 'in'" :size="19" aria-hidden="true" />
      <LogOut v-else :size="19" aria-hidden="true" />
      {{ locating ? 'Mencari lokasi…' : form.processing ? 'Menyimpan absensi…' : `Gunakan lokasi & ${label}` }}
      <MapPin v-if="!working" :size="16" aria-hidden="true" />
    </button>
    <p class="gps-help">Lokasi hanya diminta saat tombol ditekan. Validasi jadwal, akurasi, dan area sekolah dilakukan oleh server.</p>
    <p v-if="gpsError" class="gps-error" role="alert"><AlertCircle :size="17" aria-hidden="true" />{{ gpsError }}</p>
    <div v-if="Object.keys(form.errors).length" class="gps-errors" role="alert">
      <p><AlertCircle :size="16" aria-hidden="true" />Absensi belum dapat dicatat:</p>
      <ul><li v-for="(message, key) in form.errors" :key="key">{{ message }}</li></ul>
    </div>
  </div>
</template>

<style scoped>
.gps-action{display:grid;gap:10px}.gps-submit{width:100%;min-height:49px;flex-wrap:wrap}.gps-help{font-size:12px;line-height:1.65;color:#64788b;margin:0}.gps-error,.gps-errors{background:#fff4f4;border:1px solid #f1c3c9;border-radius:10px;padding:12px;color:#9c3045;font-size:12px;line-height:1.6;margin:0}.gps-error,.gps-errors>p{display:flex;align-items:flex-start;gap:9px}.gps-errors p{margin:0 0 7px;font-weight:750}.gps-errors ul{margin:0 0 0 27px;padding:0}.spinning{animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
</style>
