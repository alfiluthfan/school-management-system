<?php

namespace Tests\Feature\Actions;

use App\Actions\Attendance\CheckInStudentAction;
use App\Actions\Attendance\CheckOutStudentAction;
use App\Enums\Attendance\AttendanceStatus;
use App\Events\Attendance\StudentLateDetected;
use App\Models\Attendance\StudentAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class AttendanceActionTest extends ActionTestCase
{
    public function test_on_time_student_check_in_is_recorded_as_present(): void
    {
        Event::fake([StudentLateDetected::class]);

        $scenario = $this->createAttendanceScenario();

        $attendance = app(CheckInStudentAction::class)->execute(
            actor: $scenario['actor'],
            student: $scenario['student'],
            latitude: -6.2000000,
            longitude: 106.8000000,
            accuracy: 10,
            occurredAt: CarbonImmutable::parse(
                '2026-09-14 06:55:00',
                'Asia/Jakarta'
            )
        );

        $this->assertSame(
            AttendanceStatus::Present,
            $attendance->status
        );
        $this->assertSame(0, $attendance->late_minutes);
        $this->assertSame(
            $scenario['schoolClass']->id,
            $attendance->class_id
        );

        $this->assertDatabaseHas('student_attendances', [
            'id' => $attendance->id,
            'student_id' => $scenario['student']->id,
            'status' => AttendanceStatus::Present->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $attendance->id,
            'module' => 'attendance',
            'action' => 'CHECK_IN',
        ]);

        Event::assertNotDispatched(StudentLateDetected::class);
    }

    public function test_late_student_check_in_records_actual_late_minutes_and_event(): void
    {
        Event::fake([StudentLateDetected::class]);

        $scenario = $this->createAttendanceScenario();

        $attendance = app(CheckInStudentAction::class)->execute(
            actor: $scenario['actor'],
            student: $scenario['student'],
            latitude: -6.2000000,
            longitude: 106.8000000,
            accuracy: 10,
            occurredAt: CarbonImmutable::parse(
                '2026-09-14 07:10:00',
                'Asia/Jakarta'
            )
        );

        $this->assertSame(
            AttendanceStatus::Late,
            $attendance->status
        );

        // Tolerance controls status, but lateness is measured from 07:00.
        $this->assertSame(10, $attendance->late_minutes);

        Event::assertDispatched(
            StudentLateDetected::class,
            fn (StudentLateDetected $event): bool =>
                $event->studentAttendanceId === $attendance->id
        );
    }

    public function test_check_in_outside_geofence_is_rejected(): void
    {
        $scenario = $this->createAttendanceScenario();

        $this->expectException(ValidationException::class);

        app(CheckInStudentAction::class)->execute(
            actor: $scenario['actor'],
            student: $scenario['student'],
            latitude: -6.2500000,
            longitude: 106.8500000,
            accuracy: 10,
            occurredAt: CarbonImmutable::parse(
                '2026-09-14 06:55:00',
                'Asia/Jakarta'
            )
        );
    }

    public function test_duplicate_check_in_is_rejected(): void
    {
        $scenario = $this->createAttendanceScenario();
        $action = app(CheckInStudentAction::class);
        $time = CarbonImmutable::parse(
            '2026-09-14 06:55:00',
            'Asia/Jakarta'
        );

        $action->execute(
            actor: $scenario['actor'],
            student: $scenario['student'],
            latitude: -6.2000000,
            longitude: 106.8000000,
            accuracy: 10,
            occurredAt: $time
        );

        try {
            $action->execute(
                actor: $scenario['actor'],
                student: $scenario['student'],
                latitude: -6.2000000,
                longitude: 106.8000000,
                accuracy: 10,
                occurredAt: $time->addMinute()
            );

            $this->fail('Duplicate check-in should have failed.');
        } catch (ValidationException) {
            $this->assertSame(
                1,
                StudentAttendance::query()
                    ->where('student_id', $scenario['student']->id)
                    ->count()
            );
        }
    }

    public function test_successful_check_out_updates_same_attendance(): void
    {
        $scenario = $this->createAttendanceScenario();

        $attendance = app(CheckInStudentAction::class)->execute(
            actor: $scenario['actor'],
            student: $scenario['student'],
            latitude: -6.2000000,
            longitude: 106.8000000,
            accuracy: 10,
            occurredAt: CarbonImmutable::parse(
                '2026-09-14 06:55:00',
                'Asia/Jakarta'
            )
        );

        $updated = app(CheckOutStudentAction::class)->execute(
            actor: $scenario['actor'],
            student: $scenario['student'],
            latitude: -6.2000000,
            longitude: 106.8000000,
            accuracy: 10,
            occurredAt: CarbonImmutable::parse(
                '2026-09-14 15:00:00',
                'Asia/Jakarta'
            )
        );

        $this->assertSame($attendance->id, $updated->id);
        $this->assertNotNull($updated->check_out_at);

        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $attendance->id,
            'module' => 'attendance',
            'action' => 'CHECK_OUT',
        ]);
    }
}
