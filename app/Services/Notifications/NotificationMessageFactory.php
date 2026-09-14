<?php

namespace App\Services\Notifications;

use App\Models\Attendance\StudentAttendance;
use App\Models\Finance\SppPayment;

final class NotificationMessageFactory
{
    /**
     * @return array{subject: string, message: string}
     */
    public function studentLate(
        StudentAttendance $attendance
    ): array {
        $attendance->loadMissing('student.user');

        $studentName = $attendance->student->user?->name
            ?? $attendance->student->nis;

        $date = $attendance->attendance_date?->format('d-m-Y')
            ?? '-';

        $time = $attendance->check_in_at?->format('H:i')
            ?? '-';

        return [
            'subject' => 'Pemberitahuan Siswa Terlambat',
            'message' => sprintf(
                'Yth. Orang Tua/Wali, %s tercatat datang terlambat '
                .'pada %s pukul %s dengan keterlambatan %d menit.',
                $studentName,
                $date,
                $time,
                (int) $attendance->late_minutes
            ),
        ];
    }

    /**
     * @return array{subject: string, message: string}
     */
    public function sppPaymentPosted(
        SppPayment $payment
    ): array {
        $payment->loadMissing([
            'bill.student.user',
        ]);

        $student = $payment->bill->student;
        $studentName = $student->user?->name
            ?? $student->nis;

        $period = sprintf(
            '%02d/%d',
            $payment->bill->billing_month,
            $payment->bill->billing_year
        );

        $amount = 'Rp'.number_format(
            (float) $payment->amount,
            0,
            ',',
            '.'
        );

        return [
            'subject' => 'Konfirmasi Pembayaran SPP',
            'message' => sprintf(
                'Yth. Orang Tua/Wali, pembayaran SPP %s atas nama %s '
                .'sebesar %s telah diterima. No. kuitansi: %s.',
                $period,
                $studentName,
                $amount,
                $payment->receipt_number
            ),
        ];
    }
}
