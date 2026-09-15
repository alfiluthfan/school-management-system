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
                    . 'pada %s pukul %s dengan keterlambatan %d menit.',
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

        $amount = 'Rp' . number_format(
            (float) $payment->amount,
            0,
            ',',
            '.'
        );

        return [
            'subject' => 'Konfirmasi Pembayaran SPP',
            'message' => sprintf(
                'Yth. Orang Tua/Wali, pembayaran SPP %s atas nama %s '
                    . 'sebesar %s telah diterima. No. kuitansi: %s.',
                $period,
                $studentName,
                $amount,
                $payment->receipt_number
            ),
        ];
    }

    public function sppOverdue(
        \App\Models\Finance\SppBill $bill,
        \Carbon\CarbonImmutable $asOfDate
    ): array {
        $bill->loadMissing('student.user');

        $studentName = $bill->student->user?->name
            ?? $bill->student->nis;

        $period = sprintf(
            '%02d/%d',
            $bill->billing_month,
            $bill->billing_year
        );

        $outstanding = bcsub(
            $bill->amount,
            $bill->paid_amount,
            2
        );

        $formattedOutstanding = 'Rp' . number_format(
            (int) bcadd($outstanding, '0', 0),
            0,
            ',',
            '.'
        );

        $dueDate = $bill->due_date->format('d-m-Y');

        $overdueDays = $bill->due_date
            ->startOfDay()
            ->diffInDays(
                $asOfDate->startOfDay(),
                false
            );

        return [
            'subject' => 'Pengingat Tunggakan SPP',
            'message' => sprintf(
                'Yth. Orang Tua/Wali, SPP %s atas nama %s '
                    . 'memiliki sisa tagihan %s dan telah melewati '
                    . 'jatuh tempo %s selama %d hari. '
                    . 'Mohon melakukan pembayaran sesuai ketentuan sekolah.',
                $period,
                $studentName,
                $formattedOutstanding,
                $dueDate,
                $overdueDays
            ),
        ];
    }
}
