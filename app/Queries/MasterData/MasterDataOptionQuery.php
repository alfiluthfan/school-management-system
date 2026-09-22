<?php

namespace App\Queries\MasterData;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Academic\Teacher;
use App\Models\Auth\User;
use App\Support\MasterData\MasterDataCatalog as Catalog;
use Illuminate\Database\Eloquent\Builder;

/** Search is authorization-scoped at the query, never filtered after fetching records. */
final class MasterDataOptionQuery
{
    public const RESOURCES = ['users', 'years', 'teachers', 'classes', 'students'];

    public function search(User $actor, string $resource, string $context, string $term, ?string $selected, int $page): array
    {
        abort_unless(in_array($resource, self::RESOURCES, true), 404);
        $authorized = match ($resource) {
            'users' => in_array($context, ['students', 'teachers', 'parents'], true)
                && Catalog::canCreate($actor, $context),
            'years', 'teachers' => $context === 'classes'
                && (Catalog::canCreate($actor, 'classes') || Catalog::canUpdate($actor, 'classes')),
            'classes' => $context === 'students' && $actor->hasPermission('student.update')
                && $actor->hasPermission('class.view.all'),
            'students' => $context === 'parents' && $actor->hasPermission('parent.update')
                && $actor->hasPermission('student.view.all'),
        };
        abort_unless($authorized, 403);

        $base = match ($resource) {
            'users' => User::query()->where('is_active', true)
                ->whereHas('roles', fn (Builder $q) => $q->where('name', match ($context) {
                    'students' => 'student', 'teachers' => 'teacher', 'parents' => 'parent',
                }))->whereDoesntHave(match ($context) {
                    'students' => 'student', 'teachers' => 'teacher', 'parents' => 'guardian',
                }),
            'years' => AcademicYear::query(),
            'teachers' => Teacher::query()->where('status', 'ACTIVE')->with('user'),
            'classes' => SchoolClass::query()->where('status', 'ACTIVE')->with('academicYear'),
            'students' => Student::query()->with('user'),
        };

        // Selected item hydration uses the SAME eligible base query, but no search restriction.
        $chosen = null;
        if ($selected !== null && $selected !== '') {
            $key = $resource === 'years' ? 'id' : 'uuid';
            $chosenModel = (clone $base)->where($key, $selected)->first();
            $chosen = $chosenModel ? $this->map($resource, $chosenModel) : null;
        }

        // Never enumerate the whole school without a search term.
        if (mb_strlen($term) < 2) {
            return ['items' => [], 'selected' => $chosen, 'next_page' => null];
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $base->where(function (Builder $q) use ($resource, $like): void {
            match ($resource) {
                'users' => $q->where('name', 'like', $like)->orWhere('username', 'like', $like),
                'years' => $q->where('name', 'like', $like),
                'teachers' => $q->where('nip', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)),
                'classes' => $q->where('name', 'like', $like)->orWhere('code', 'like', $like),
                'students' => $q->where('nis', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)),
            };
        });
        $sort = match ($resource) {
            'users' => 'name', 'years' => 'start_date', 'teachers' => 'nip',
            'classes' => 'code', 'students' => 'nis',
        };
        $base->orderBy($sort, $resource === 'years' ? 'desc' : 'asc')->orderBy('id');
        // simplePaginate avoids an expensive total COUNT on every keystroke.
        $result = $base->simplePaginate(20, ['*'], 'page', $page);
        return [
            'items' => $result->getCollection()->map(fn ($model) => $this->map($resource, $model))->values()->all(),
            'selected' => $chosen,
            'next_page' => $result->hasMorePages() ? $page + 1 : null,
        ];
    }

    private function map(string $resource, object $model): array
    {
        return match ($resource) {
            'users' => ['value' => $model->uuid, 'label' => $model->name.' ('.$model->username.')'],
            'years' => ['value' => (string) $model->id, 'label' => $model->name],
            'teachers' => ['value' => $model->uuid,
                'label' => ($model->user?->name ?? $model->nip).' · '.$model->nip],
            'classes' => ['value' => $model->uuid,
                'label' => $model->name.' · '.($model->academicYear?->name ?? '-')],
            'students' => ['value' => $model->uuid,
                'label' => ($model->user?->name ?? '-').' · '.$model->nis],
        };
    }
}
