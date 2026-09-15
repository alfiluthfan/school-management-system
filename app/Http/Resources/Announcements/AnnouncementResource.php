<?php

namespace App\Http\Resources\Announcements;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'content' => $this->content,

            'target_scope' => [
                'value' =>
                    $this->target_scope->value,
                'label' =>
                    $this->target_scope->label(),
            ],

            'status' => [
                'value' =>
                    $this->status->value,
                'label' =>
                    $this->status->label(),
            ],

            'publish_at' =>
                $this->publish_at
                    ?->toIso8601String(),

            'expired_at' =>
                $this->expired_at
                    ?->toIso8601String(),

            'creator' => $this->whenLoaded(
                'creator',
                fn (): array => [
                    'uuid' =>
                        $this->creator->uuid,
                    'name' =>
                        $this->creator->name,
                ]
            ),

            'school_class' => $this->whenLoaded(
                'schoolClass',
                function (): ?array {
                    if (! $this->schoolClass) {
                        return null;
                    }

                    return [
                        'uuid' =>
                            $this->schoolClass->uuid,
                        'code' =>
                            $this->schoolClass->code,
                        'name' =>
                            $this->schoolClass->name,
                        'grade_level' =>
                            $this->schoolClass
                                ->grade_level,
                    ];
                }
            ),

            'target_roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles
                    ->map(
                        fn ($role): array => [
                            'name' =>
                                $role->name,
                            'display_name' =>
                                $role->display_name,
                        ]
                    )
                    ->values()
            ),

            'created_at' =>
                $this->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $this->updated_at
                    ?->toIso8601String(),
        ];
    }
}
