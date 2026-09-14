<?php

namespace Tests\Feature\Authorization;

use Illuminate\Support\Facades\Gate;

class RbacHelperTest extends AuthorizationTestCase
{
    public function test_teacher_inherits_expected_permissions_from_role(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $this->assertTrue($teacher->hasRole('teacher'));
        $this->assertTrue($teacher->hasPermission('student-attendance.view.class'));
        $this->assertTrue($teacher->hasPermission('teacher-attendance.check-in'));
        $this->assertFalse($teacher->hasPermission('spp.payment.create'));
        $this->assertFalse($teacher->hasPermission('approval.approve'));
    }

    public function test_principal_can_use_global_permission_gate(): void
    {
        $principal = $this->createPrincipalUser();
        $this->assertTrue(Gate::forUser($principal)->allows('report.analytics'));
        $this->assertFalse(Gate::forUser($principal)->allows('spp.payment.create'));
    }

    public function test_admin_receives_operational_permissions(): void
    {
        $admin = $this->createAdminUser();
        $this->assertTrue($admin->hasPermission('spp.payment.create'));
        $this->assertTrue($admin->hasPermission('student.create'));
        $this->assertTrue($admin->hasPermission('audit.view'));
    }
}
