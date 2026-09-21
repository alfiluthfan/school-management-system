export const number = (value) => new Intl.NumberFormat('id-ID').format(Number(value ?? 0))
// Presentation only. Keep monetary calculations on server as decimals, not JS float.
export const rupiah = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0))
export const localDateTime = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(value)) : '—'
