<?php

namespace Tests\Feature\Notifications;

use App\Enums\Academic\ParentRelationship;
use App\Services\Notifications\ParentRecipientResolver;

class ParentRecipientResolverTest extends NotificationTestCase
{
    public function test_it_returns_only_enabled_active_parents_with_phone(): void
    {
        [, $student] = $this->createApiStudent();

        [$primaryUser, $primaryGuardian] =
            $this->createApiParent();
        $primaryUser->update([
            'phone' => '0812-1111-2222',
        ]);

        [$secondaryUser, $secondaryGuardian] =
            $this->createApiParent();
        $secondaryUser->update([
            'phone' => '0813-3333-4444',
        ]);

        [$disabledUser, $disabledGuardian] =
            $this->createApiParent();
        $disabledUser->update([
            'phone' => '0814-5555-6666',
        ]);

        $student->guardians()->attach(
            $primaryGuardian->id,
            [
                'relationship' =>
                    ParentRelationship::Father->value,
                'is_primary_contact' => true,
                'receive_notification' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $student->guardians()->attach(
            $secondaryGuardian->id,
            [
                'relationship' =>
                    ParentRelationship::Mother->value,
                'is_primary_contact' => false,
                'receive_notification' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $student->guardians()->attach(
            $disabledGuardian->id,
            [
                'relationship' =>
                    ParentRelationship::Guardian->value,
                'is_primary_contact' => false,
                'receive_notification' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $recipients = app(
            ParentRecipientResolver::class
        )->forStudent($student);

        $this->assertCount(2, $recipients);
        $this->assertSame(
            $primaryUser->id,
            $recipients->first()->id
        );
        $this->assertTrue(
            $recipients->contains('id', $secondaryUser->id)
        );
        $this->assertFalse(
            $recipients->contains('id', $disabledUser->id)
        );
    }
}
