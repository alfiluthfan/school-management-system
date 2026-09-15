<?php

namespace App\Http\Resources\Attendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAttendanceResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'attendance_date' =>
                $this->attendance_date
                    ?->toDateString(),

            'check_in_at' =>
                $this->check_in_at
                    ?->toIso8601String(),

            'check_out_at' =>
                $this->check_out_at
                    ?->toIso8601String(),

            'status' => [
                'value' =>
                    $this->status->value,
                'label' =>
                    $this->status->label(),
            ],

            'late_minutes' =>
                $this->late_minutes,

            'source' => [
                'value' =>
                    $this->source->value,
                'label' =>
                    $this->source->label(),
            ],

            'location' => [
                'check_in' => [
                    'latitude' =>
                        $this->check_in_latitude,
                    'longitude' =>
                        $this->check_in_longitude,
                ],
                'check_out' => [
                    'latitude' =>
                        $this->check_out_latitude,
                    'longitude' =>
                        $this->check_out_longitude,
                ],
                'accuracy' =>
                    $this->location_accuracy,
                'distance_from_school' =>
                    $this->distance_from_school,
            ],

            'notes' =>
                $this->notes,

            'teacher' => $this->whenLoaded(
                'teacher',
                function (): array {
                    return [
                        'uuid' =>
                            $this->teacher->uuid,
                        'nip' =>
                            $this->teacher->nip,
                        'employee_number' =>
                            $this->teacher
                                ->employee_number,
                        'name' =>
                            $this->teacher
                                ->user?->name,
                    ];
                }
            ),

            'schedule' => $this->whenLoaded(
                'schedule',
                function (): ?array {
                    if (! $this->schedule) {
                        return null;
                    }

                    return [
                        'uuid' =>
                            $this->schedule->uuid,
                        'name' =>
                            $this->schedule->name,
                    ];
                }
            ),

            'school_location' =>
                $this->whenLoaded(
                    'schoolLocation',
                    function (): ?array {
                        if (
                            ! $this
                                ->schoolLocation
                        ) {
                            return null;
                        }

                        return [
                            'uuid' =>
                                $this
                                    ->schoolLocation
                                    ->uuid,
                            'code' =>
                                $this
                                    ->schoolLocation
                                    ->code,
                            'name' =>
                                $this
                                    ->schoolLocation
                                    ->name,
                        ];
                    }
                ),

            'correction' => [
                'reason' =>
                    $this->correction_reason,
                'corrected_by' =>
                    $this->whenLoaded(
                        'correctedBy',
                        fn (): ?array =>
                            $this->correctedBy
                                ? [
                                    'uuid' =>
                                        $this
                                            ->correctedBy
                                            ->uuid,
                                    'name' =>
                                        $this
                                            ->correctedBy
                                            ->name,
                                ]
                                : null
                    ),
            ],

            'created_at' =>
                $this->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $this->updated_at
                    ?->toIso8601String(),
        ];
    }
}
