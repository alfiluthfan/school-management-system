<?php

namespace Tests\Feature\Authorization;

use App\Authorization\SchoolDataScope;
use Illuminate\Support\Facades\Gate;

class FinanceAuthorizationTest extends AuthorizationTestCase
{
    public function test_saving_account_own_child_and_principal_rules(): void
    {
        [$studentUser, $student] = $this->createStudentUser();
        [, $otherStudent] = $this->createStudentUser();
        [$parentUser, $guardian] = $this->createGuardianUser();
        $principal = $this->createPrincipalUser();
        $this->linkGuardianToStudent($guardian, $student);

        $own = $this->createSavingAccount($student);
        $other = $this->createSavingAccount($otherStudent);

        $this->assertTrue(Gate::forUser($studentUser)->allows('view', $own));
        $this->assertFalse(Gate::forUser($studentUser)->allows('view', $other));
        $this->assertTrue(Gate::forUser($parentUser)->allows('view', $own));
        $this->assertFalse(Gate::forUser($parentUser)->allows('view', $other));
        $this->assertTrue(Gate::forUser($principal)->allows('view', $own));
        $this->assertFalse(Gate::forUser($principal)->allows('deposit', $own));

        $ids = SchoolDataScope::savingAccounts($studentUser)->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_spp_bill_own_child_and_list_scope_rules(): void
    {
        [$studentUser, $student] = $this->createStudentUser();
        [, $otherStudent] = $this->createStudentUser();
        [$parentUser, $guardian] = $this->createGuardianUser();
        $this->linkGuardianToStudent($guardian, $student);
        $year = $this->createAcademicYear();
        $own = $this->createSppBill($student, $year, 9);
        $other = $this->createSppBill($otherStudent, $year, 10);

        $this->assertTrue(Gate::forUser($studentUser)->allows('view', $own));
        $this->assertFalse(Gate::forUser($studentUser)->allows('view', $other));
        $this->assertTrue(Gate::forUser($parentUser)->allows('view', $own));
        $this->assertFalse(Gate::forUser($parentUser)->allows('view', $other));

        $ids = SchoolDataScope::sppBills($studentUser)->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }
}
