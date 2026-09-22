<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Queries\MasterData\MasterDataOptionQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PortalMasterDataOptionController extends Controller
{
    public function __invoke(Request $request, MasterDataOptionQuery $query): JsonResponse
    {
        $data = $request->validate([
            'resource' => ['required', Rule::in(MasterDataOptionQuery::RESOURCES)],
            'context' => ['required', Rule::in(['students', 'teachers', 'parents', 'classes'])],
            'search' => ['nullable', 'string', 'max:90'],
            'selected' => ['nullable', 'string', 'max:64'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);
        if (! empty($data['selected'])) {
            $rule = $data['resource'] === 'years'
                ? 'regex:/^[1-9][0-9]{0,14}$/' : 'uuid';
            validator($data, ['selected' => [$rule]])->validate();
        }
        return response()->json($query->search(
            $request->user(), $data['resource'], $data['context'],
            trim($data['search'] ?? ''), $data['selected'] ?? null, (int) ($data['page'] ?? 1)
        ))->header('Cache-Control', 'private, no-store');
    }
}
