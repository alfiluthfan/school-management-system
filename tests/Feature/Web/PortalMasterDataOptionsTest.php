<?php

namespace Tests\Feature\Web;

use App\Models\Academic\AcademicYear;
use App\Models\Auth\User;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalMasterDataOptionsTest extends HttpApiTestCase
{
    private function url(array $args = []): string
    {
        return '/master-data/options?'.http_build_query([
            'resource' => 'users', 'context' => 'students', 'search' => 'alpha', ...$args,
        ]);
    }

    public function test_guest_and_non_editors_cannot_enumerate_selectors(): void
    {
        $this->get($this->url())->assertRedirect('/login');
        $principal = $this->createApiPrincipal();
        $this->actingAs($principal, 'web')->get($this->url())->assertForbidden();
        [$teacher] = $this->createApiTeacher();
        $this->actingAs($teacher, 'web')->get($this->url([
            'resource' => 'students', 'context' => 'parents',
        ]))->assertForbidden();
    }

    public function test_option_search_only_returns_eligible_users_with_minimum_two_characters(): void
    {
        $admin = $this->createApiAdmin();
        $eligible = $this->createApiUserWithRole('student');
        $excluded = $this->createApiUserWithRole('teacher');
        $this->actingAs($admin, 'web')->get($this->url(['search' => '']))
            ->assertOk()->assertJsonPath('items', []);
        $this->get($this->url(['search' => 'a']))
            ->assertOk()->assertJsonPath('items', []);
        $this->get($this->url(['search' => $eligible->username]))
            ->assertOk()->assertJsonPath('items.0.value', $eligible->uuid)
            ->assertDontSee($excluded->uuid);
        $this->get($this->url(['resource' => 'teachers', 'context' => 'students']))
            ->assertForbidden();
    }

    public function test_selected_ref_can_be_hydrated_independent_of_search_and_unauthorized_reference_cannot(): void
    {
        $admin = $this->createApiAdmin();
        $target = $this->createApiUserWithRole('student');
        $this->actingAs($admin, 'web')->get($this->url([
            'search' => '', 'selected' => $target->uuid,
        ]))->assertOk()->assertJsonPath('selected.value', $target->uuid)
            ->assertJsonPath('items', []);
        $this->get($this->url(['search' => '', 'selected' => $this->createApiUserWithRole('teacher')->uuid]))
            ->assertOk()->assertJsonPath('selected', null);
        $this->get($this->url(['selected' => 'INVALID']))->assertSessionHasErrors('selected');
    }

    public function test_year_options_use_existing_integer_reference_and_support_pagination(): void
    {
        $admin = $this->createApiAdmin();
        $year = $this->createApiAcademicYear();
        $this->actingAs($admin, 'web')->get($this->url([
            'resource' => 'years', 'context' => 'classes', 'search' => '', 'selected' => (string) $year->id,
        ]))->assertOk()->assertJsonPath('selected.value', (string) $year->id);
        $this->get($this->url([
            'resource' => 'years', 'context' => 'classes', 'search' => '202',
        ]))->assertOk()->assertJsonStructure(['items', 'selected', 'next_page']);
    }

    public function test_results_are_paginated_instead_of_truncated_to_first_page(): void
    {
        $admin = $this->createApiAdmin();
        $role = \App\Models\Auth\Role::query()->where('name', 'student')->firstOrFail();
        for ($i = 1; $i <= 25; $i++) {
            $account = User::query()->create([
                'name' => 'PhaseEightLookup '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'username' => 'phase8look-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'email' => 'phase8look-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT).'@example.test',
                'password' => 'password', 'is_active' => true,
            ]);
            $account->roles()->attach($role->id);
        }
        $this->actingAs($admin, 'web')->get($this->url(['search' => 'PhaseEightLookup']))
            ->assertOk()->assertJsonCount(20, 'items')->assertJsonPath('next_page', 2);
        $this->get($this->url(['search' => 'PhaseEightLookup', 'page' => 2]))
            ->assertOk()->assertJsonCount(5, 'items')->assertJsonPath('next_page', null);
    }

    public function test_invalid_scope_or_search_parameters_do_not_expand_results(): void
    {
        $admin = $this->createApiAdmin();
        $this->actingAs($admin, 'web')->get($this->url(['page' => -1]))
            ->assertSessionHasErrors('page');
        $this->get($this->url(['resource' => 'all']))->assertSessionHasErrors('resource');
        $this->get($this->url(['search' => str_repeat('x', 91)]))
            ->assertSessionHasErrors('search');
    }
}
