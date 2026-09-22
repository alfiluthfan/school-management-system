<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Queries\MasterData\MasterDataPageQuery;
use App\Support\MasterData\MasterDataCatalog as Catalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PortalMasterDataController extends Controller
{
    public function index(Request $request, MasterDataPageQuery $query): Response
    {
        $tabs = Catalog::tabs($request->user());
        abort_if($tabs === [], 403);
        $valid = $request->validate([
            'kind' => ['sometimes', Rule::in(Catalog::KINDS)],
            'search' => ['sometimes', 'nullable', 'string', 'max:90'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 15, 25])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $kind = $valid['kind'] ?? $tabs[0]['key'];
        abort_unless(Catalog::canView($request->user(), $kind), 403);
        return Inertia::render('MasterData/Index', [
            'master' => fn (): array => $query->index(
                $request->user(), $kind, trim($valid['search'] ?? ''), (int) ($valid['per_page'] ?? 15)
            ),
        ]);
    }
}
