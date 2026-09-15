<?php

namespace Database\Seeders\Support;

final class RbacCatalog
{
    /**
     * System roles.
     *
     * @return array<string, array{display_name: string, description: string}>
     */
    public static function roles(): array
    {
        return [
            'admin' => [
                'display_name' => 'Admin',
                'description' => 'Mengelola master data, operasional sekolah, transaksi, laporan, dan konfigurasi sistem.',
            ],
            'principal' => [
                'display_name' => 'Kepala Sekolah',
                'description' => 'Monitoring sekolah, laporan, analitik, approval, dan pengumuman tingkat sekolah.',
            ],
            'teacher' => [
                'display_name' => 'Guru',
                'description' => 'Absensi pribadi, pengajuan izin, monitoring kelas yang menjadi tanggung jawab, dan pengumuman kelas.',
            ],
            'student' => [
                'display_name' => 'Siswa',
                'description' => 'Absensi pribadi, melihat tabungan, status SPP, kelas, dan pengumuman.',
            ],
            'parent' => [
                'display_name' => 'Orang Tua / Wali',
                'description' => 'Monitoring anak terkait absensi, tabungan, SPP, notifikasi, dan pengumuman.',
            ],
        ];
    }

    /**
     * Permission catalog grouped by module.
     *
     * @return array<string, array<string, string>>
     */
    public static function permissions(): array
    {
        return [
            'profile' => [
                'profile.view.own' => 'Lihat profil sendiri',
                'profile.update.own' => 'Perbarui profil sendiri',
                'password.change.own' => 'Ubah password sendiri',
            ],

            'user' => [
                'user.view.all' => 'Lihat seluruh akun pengguna',
                'user.create' => 'Buat akun pengguna',
                'user.update' => 'Perbarui akun pengguna',
                'user.activate' => 'Aktifkan akun pengguna',
                'user.deactivate' => 'Nonaktifkan akun pengguna',
                'user.password.reset' => 'Reset password pengguna',
                'role.assign' => 'Tetapkan role pengguna',
            ],

            'student' => [
                'student.view.all' => 'Lihat seluruh siswa',
                'student.view.class' => 'Lihat siswa pada kelas yang menjadi tanggung jawab',
                'student.view.own' => 'Lihat data siswa sendiri',
                'student.view.child' => 'Lihat data anak yang terhubung',
                'student.create' => 'Buat data siswa',
                'student.update' => 'Perbarui data siswa',
                'student.delete' => 'Soft delete / nonaktifkan data siswa',
            ],

            'teacher' => [
                'teacher.view.all' => 'Lihat seluruh guru',
                'teacher.create' => 'Buat data guru',
                'teacher.update' => 'Perbarui data guru',
                'teacher.delete' => 'Soft delete / nonaktifkan data guru',
            ],

            'parent' => [
                'parent.view.all' => 'Lihat seluruh orang tua / wali',
                'parent.view.own' => 'Lihat data orang tua / wali sendiri',
                'parent.create' => 'Buat data orang tua / wali',
                'parent.update' => 'Perbarui data orang tua / wali',
                'parent.delete' => 'Soft delete / nonaktifkan data orang tua / wali',
            ],

            'class' => [
                'class.view.all' => 'Lihat seluruh kelas',
                'class.view.assigned' => 'Lihat kelas yang menjadi tanggung jawab',
                'class.view.own' => 'Lihat kelas siswa sendiri',
                'class.view.child' => 'Lihat kelas anak yang terhubung',
                'class.create' => 'Buat kelas',
                'class.update' => 'Perbarui kelas',
                'class.delete' => 'Soft delete / arsipkan kelas',
            ],

            'academic-year' => [
                'academic-year.view' => 'Lihat tahun ajaran',
                'academic-year.create' => 'Buat tahun ajaran',
                'academic-year.update' => 'Perbarui tahun ajaran',
                'academic-year.activate' => 'Aktifkan tahun ajaran',
            ],

            'student-attendance' => [
                'student-attendance.check-in' => 'Check-in absensi siswa sendiri',
                'student-attendance.check-out' => 'Check-out absensi siswa sendiri',
                'student-attendance.view.own' => 'Lihat absensi siswa sendiri',
                'student-attendance.view.child' => 'Lihat absensi anak',
                'student-attendance.view.class' => 'Lihat absensi siswa pada kelas yang menjadi tanggung jawab',
                'student-attendance.view.all' => 'Lihat seluruh absensi siswa',
                'student-attendance.create.manual' => 'Input absensi siswa secara manual',
                'student-attendance.update' => 'Perbarui absensi siswa',
                'student-attendance.correct' => 'Koreksi absensi siswa',
                'student-attendance.mark.sick' => 'Tandai siswa sakit',
                'student-attendance.mark.permission' => 'Tandai siswa izin',
                'student-attendance.report' => 'Lihat laporan absensi siswa',
                'student-attendance.export' => 'Export laporan absensi siswa',
            ],

            'teacher-attendance' => [
                'teacher-attendance.check-in' => 'Check-in absensi guru sendiri',
                'teacher-attendance.check-out' => 'Check-out absensi guru sendiri',
                'teacher-attendance.view.own' => 'Lihat absensi guru sendiri',
                'teacher-attendance.view.all' => 'Lihat seluruh absensi guru',
                'teacher-attendance.create.manual' => 'Input absensi guru secara manual',
                'teacher-attendance.update' => 'Perbarui absensi guru',
                'teacher-attendance.correct' => 'Koreksi absensi guru',
                'teacher-attendance.report' => 'Lihat laporan absensi guru',
                'teacher-attendance.export' => 'Export laporan absensi guru',
            ],

            'teacher-leave' => [
                'teacher-leave.create' => 'Ajukan izin / cuti guru',
                'teacher-leave.view.own' => 'Lihat pengajuan izin sendiri',
                'teacher-leave.view.all' => 'Lihat seluruh pengajuan izin guru',
                'teacher-leave.approve' => 'Setujui pengajuan izin guru',
                'teacher-leave.reject' => 'Tolak pengajuan izin guru',
                'teacher-leave.cancel.own' => 'Batalkan pengajuan izin sendiri',
            ],

            'saving' => [
                'saving.balance.view.all' => 'Lihat saldo seluruh siswa',
                'saving.balance.view.own' => 'Lihat saldo tabungan sendiri',
                'saving.balance.view.child' => 'Lihat saldo tabungan anak',
                'saving.transaction.view.all' => 'Lihat seluruh transaksi tabungan',
                'saving.transaction.view.own' => 'Lihat transaksi tabungan sendiri',
                'saving.transaction.view.child' => 'Lihat transaksi tabungan anak',
                'saving.deposit.create' => 'Input setoran tabungan',
                'saving.withdrawal.create' => 'Input penarikan tabungan',
                'saving.transaction.update' => 'Perbarui transaksi tabungan yang masih diizinkan',
                'saving.transaction.void' => 'Ajukan / proses pembatalan transaksi tabungan',
                'saving.report.view' => 'Lihat laporan tabungan',
                'saving.report.export' => 'Export laporan tabungan',
            ],

            'spp' => [
                'spp.bill.view.all' => 'Lihat seluruh tagihan SPP',
                'spp.bill.view.own' => 'Lihat tagihan SPP sendiri',
                'spp.bill.view.child' => 'Lihat tagihan SPP anak',
                'spp.bill.create' => 'Buat tagihan SPP',
                'spp.bill.update' => 'Perbarui tagihan SPP',
                'spp.bill.cancel' => 'Ajukan / proses pembatalan tagihan SPP',
                'spp.payment.create' => 'Input pembayaran SPP',
                'spp.payment.view.all' => 'Lihat seluruh pembayaran SPP',
                'spp.payment.view.own' => 'Lihat pembayaran SPP sendiri',
                'spp.payment.view.child' => 'Lihat pembayaran SPP anak',
                'spp.payment.correct' => 'Ajukan koreksi pembayaran SPP',
                'spp.payment.void' => 'Ajukan pembatalan pembayaran SPP',
                'spp.arrears.view.all' => 'Lihat seluruh tunggakan SPP',
                'spp.arrears.view.own' => 'Lihat tunggakan SPP sendiri',
                'spp.arrears.view.child' => 'Lihat tunggakan SPP anak',
                'spp.report.view' => 'Lihat laporan SPP',
                'spp.report.export' => 'Export laporan SPP',
            ],

            'notification' => [
                'notification.view.own' => 'Lihat notifikasi sendiri',
                'notification.view.logs' => 'Lihat log pengiriman notifikasi',
                'notification.send.manual' => 'Kirim notifikasi manual',
                'notification.configuration' => 'Kelola konfigurasi notifikasi',
            ],

            'announcement' => [
                'announcement.view' => 'Lihat pengumuman',
                'announcement.create.school' => 'Buat pengumuman tingkat sekolah',
                'announcement.create.class' => 'Buat pengumuman tingkat kelas',
                'announcement.update.own' => 'Perbarui pengumuman sendiri',
                'announcement.delete.own' => 'Hapus / arsipkan pengumuman sendiri',
                'announcement.publish' => 'Publikasikan pengumuman',
            ],

            'dashboard' => [
                'dashboard.admin' => 'Akses dashboard admin',
                'dashboard.principal' => 'Akses dashboard kepala sekolah',
                'dashboard.teacher' => 'Akses dashboard guru',
                'dashboard.student' => 'Akses dashboard siswa',
                'dashboard.parent' => 'Akses dashboard orang tua / wali',
            ],

            'report' => [
                'report.attendance.student' => 'Lihat laporan absensi siswa',
                'report.attendance.teacher' => 'Lihat laporan absensi guru',
                'report.saving' => 'Lihat laporan tabungan',
                'report.spp' => 'Lihat laporan SPP',
                'report.export.pdf' => 'Export laporan PDF',
                'report.export.excel' => 'Export laporan Excel',
                'report.analytics' => 'Lihat analitik sekolah',
            ],

            'audit' => [
                'audit.view' => 'Lihat audit log',
                'audit.filter.user' => 'Filter audit berdasarkan pengguna',
                'audit.filter.module' => 'Filter audit berdasarkan modul',
                'audit.export' => 'Export audit log',
            ],

            'approval' => [
                'approval.submit' => 'Ajukan approval',
                'approval.view.own' => 'Lihat approval yang diajukan sendiri',
                'approval.view.all' => 'Lihat seluruh approval',
                'approval.approve' => 'Setujui approval',
                'approval.reject' => 'Tolak approval',
            ],
        ];
    }

    /**
     * Permission names assigned to each system role.
     *
     * "*" means all permissions.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function rolePermissions(): array
    {
        return [
            'admin' => '*',

            'principal' => [
                'profile.view.own',
                'profile.update.own',
                'password.change.own',

                'user.view.all',

                'student.view.all',
                'teacher.view.all',
                'parent.view.all',
                'class.view.all',
                'academic-year.view',

                'student-attendance.view.all',
                'student-attendance.report',
                'student-attendance.export',

                'teacher-attendance.view.all',
                'teacher-attendance.correct',
                'teacher-attendance.report',
                'teacher-attendance.export',

                'teacher-leave.view.all',
                'teacher-leave.approve',
                'teacher-leave.reject',

                'saving.balance.view.all',
                'saving.transaction.view.all',
                'saving.report.view',
                'saving.report.export',

                'spp.bill.view.all',
                'spp.payment.view.all',
                'spp.arrears.view.all',
                'spp.report.view',
                'spp.report.export',

                'notification.view.own',
                'notification.view.logs',

                'announcement.view',
                'announcement.create.school',
                'announcement.update.own',
                'announcement.delete.own',
                'announcement.publish',

                'dashboard.principal',

                'report.attendance.student',
                'report.attendance.teacher',
                'report.saving',
                'report.spp',
                'report.export.pdf',
                'report.export.excel',
                'report.analytics',

                'audit.view',
                'audit.filter.user',
                'audit.filter.module',
                'audit.export',

                'approval.view.all',
                'approval.approve',
                'approval.reject',
            ],

            'teacher' => [
                'profile.view.own',
                'profile.update.own',
                'password.change.own',

                'student.view.class',
                'class.view.assigned',
                'academic-year.view',

                'student-attendance.view.class',
                'student-attendance.create.manual',
                'student-attendance.mark.sick',
                'student-attendance.mark.permission',
                'student-attendance.report',
                'student-attendance.export',
                'student-attendance.correct',

                'teacher-attendance.check-in',
                'teacher-attendance.check-out',
                'teacher-attendance.view.own',
                'teacher-attendance.report',

                'teacher-leave.create',
                'teacher-leave.view.own',
                'teacher-leave.cancel.own',

                'notification.view.own',

                'announcement.view',
                'announcement.create.class',
                'announcement.update.own',
                'announcement.delete.own',
                'announcement.publish',

                'dashboard.teacher',

                'report.attendance.student',
                'report.attendance.teacher',

                'approval.submit',
                'approval.view.own',
            ],

            'student' => [
                'profile.view.own',
                'profile.update.own',
                'password.change.own',

                'student.view.own',
                'class.view.own',
                'academic-year.view',

                'student-attendance.check-in',
                'student-attendance.check-out',
                'student-attendance.view.own',

                'saving.balance.view.own',
                'saving.transaction.view.own',

                'spp.bill.view.own',
                'spp.payment.view.own',
                'spp.arrears.view.own',

                'notification.view.own',
                'announcement.view',

                'dashboard.student',
            ],

            'parent' => [
                'profile.view.own',
                'profile.update.own',
                'password.change.own',

                'parent.view.own',
                'student.view.child',
                'class.view.child',
                'academic-year.view',

                'student-attendance.view.child',

                'saving.balance.view.child',
                'saving.transaction.view.child',

                'spp.bill.view.child',
                'spp.payment.view.child',
                'spp.arrears.view.child',

                'notification.view.own',
                'announcement.view',

                'dashboard.parent',
            ],
        ];
    }

    /**
     * Flatten permission catalog for convenient iteration.
     *
     * @return array<int, array{name: string, display_name: string, module: string, description: string}>
     */
    public static function flattenedPermissions(): array
    {
        $flattened = [];

        foreach (self::permissions() as $module => $permissions) {
            foreach ($permissions as $name => $displayName) {
                $flattened[] = [
                    'name' => $name,
                    'display_name' => $displayName,
                    'module' => $module,
                    'description' => $displayName,
                ];
            }
        }

        return $flattened;
    }
}
