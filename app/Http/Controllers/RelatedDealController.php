<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Support\RelatedDeals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelatedDealController extends Controller
{
    public function __invoke(Request $request, RelatedDeals $deals): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'partner_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $page = $deals->query($filters['partner_id'] ?? null, $filters['search'] ?? '')
            ->simplePaginate(RelatedDeals::PAGE_SIZE);

        return response()->json([
            'options' => $page->getCollection()->map(fn (Deal $deal) => ['id' => $deal->id, 'label' => RelatedDeals::label($deal)]),
            'has_more' => $page->hasMorePages(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
