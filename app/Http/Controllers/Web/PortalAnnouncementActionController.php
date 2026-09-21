<?php
namespace App\Http\Controllers\Web;

use App\Actions\Announcements\ArchiveAnnouncementAction;
use App\Actions\Announcements\CreateAnnouncementAction;
use App\Actions\Announcements\PublishAnnouncementAction;
use App\Actions\Announcements\UpdateAnnouncementAction;
use App\Enums\Communication\AnnouncementTargetScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\PublishAnnouncementRequest;
use App\Http\Requests\Announcements\StoreAnnouncementRequest;
use App\Http\Requests\Announcements\UpdateAnnouncementRequest;
use App\Models\Academic\SchoolClass;
use App\Models\Communication\Announcement;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PortalAnnouncementActionController extends Controller
{
    public function store(StoreAnnouncementRequest $request, CreateAnnouncementAction $action): RedirectResponse
    {
        Gate::authorize('viewAny', Announcement::class);
        $data = $request->validated();
        $scope = AnnouncementTargetScope::from($data['target_scope']);
        $class = null;
        if ($scope === AnnouncementTargetScope::School) {
            Gate::authorize('createSchool', Announcement::class);
        } else {
            $class = SchoolClass::query()->where('uuid', $data['class_uuid'])->firstOrFail();
            Gate::authorize('createForClass', [Announcement::class, $class]);
        }
        $announcement = $action->execute(
            actor: $request->user(), title: $data['title'], content: $data['content'],
            targetScope: $scope, schoolClass: $class, targetRoles: $data['target_roles'],
            expiredAt: ! empty($data['expired_at'])
                ? CarbonImmutable::parse($data['expired_at'], config('app.timezone')) : null
        );
        return to_route('portal.announcements.show', $announcement)
            ->with('success', 'Draft pengumuman berhasil dibuat. Belum terlihat oleh penerima.');
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement,
        UpdateAnnouncementAction $action): RedirectResponse
    {
        Gate::authorize('update', $announcement);
        $action->execute(actor: $request->user(), announcement: $announcement,
            changes: $request->validated());
        return to_route('portal.announcements.show', $announcement)
            ->with('success', 'Draft pengumuman berhasil diperbarui.');
    }

    public function publish(PublishAnnouncementRequest $request, Announcement $announcement,
        PublishAnnouncementAction $action): RedirectResponse
    {
        Gate::authorize('publish', $announcement);
        $data = $request->validated();
        $publishAt = ! empty($data['publish_at'])
            ? CarbonImmutable::parse($data['publish_at'], config('app.timezone')) : null;
        $provided = array_key_exists('expired_at', $data);
        $expiredAt = $provided && ! empty($data['expired_at'])
            ? CarbonImmutable::parse($data['expired_at'], config('app.timezone')) : null;
        $action->execute(actor: $request->user(), announcement: $announcement,
            publishAt: $publishAt, expiredAtWasProvided: $provided, expiredAt: $expiredAt);
        return to_route('portal.announcements.show', $announcement)
            ->with('success', $publishAt && $publishAt->isFuture()
                ? 'Publikasi berhasil dijadwalkan. Penerima akan melihatnya pada waktunya.'
                : 'Pengumuman berhasil dipublikasikan.');
    }

    public function archive(Request $request, Announcement $announcement,
        ArchiveAnnouncementAction $action): RedirectResponse
    {
        Gate::authorize('archive', $announcement);
        $action->execute(actor: $request->user(), announcement: $announcement);
        return to_route('portal.announcements.show', $announcement)
            ->with('success', 'Pengumuman diarsipkan dan tidak lagi muncul dalam feed penerima.');
    }
}
