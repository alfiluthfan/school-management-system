<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\PortalAnnouncementIndexRequest;
use App\Models\Communication\Announcement;
use App\Queries\Announcements\PortalAnnouncementPageQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PortalAnnouncementController extends Controller
{
    public function index(PortalAnnouncementIndexRequest $request, PortalAnnouncementPageQuery $query): Response
    {
        Gate::authorize('viewAny', Announcement::class);
        return Inertia::render('Announcements/Index', [
            'announcements' => fn () => $query->index($request->user(), $request->validated()),
        ]);
    }

    public function create(Request $request, PortalAnnouncementPageQuery $query): Response
    {
        Gate::authorize('viewAny', Announcement::class);
        return Inertia::render('Announcements/Form', [
            'editor' => ['mode' => 'create', 'announcement' => null,
                'options' => $query->formOptions($request->user())],
        ]);
    }

    public function show(Request $request, Announcement $announcement,
        PortalAnnouncementPageQuery $query): Response
    {
        Gate::authorize('viewAny', Announcement::class);
        Gate::authorize('view', $announcement);
        return Inertia::render('Announcements/Show', [
            'announcement' => fn () => $query->detail($request->user(), $announcement),
        ]);
    }

    public function edit(Request $request, Announcement $announcement,
        PortalAnnouncementPageQuery $query): Response
    {
        Gate::authorize('viewAny', Announcement::class);
        Gate::authorize('update', $announcement);
        return Inertia::render('Announcements/Form', [
            'editor' => ['mode' => 'edit', 'announcement' => $query->detail($request->user(), $announcement),
                'options' => $query->formOptions($request->user(), forEdit: true)],
        ]);
    }
}
