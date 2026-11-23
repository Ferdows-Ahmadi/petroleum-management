<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Enums\WeighingStatus;
use App\Http\Requests\WeighingRecordRequest;
use App\Models\SaleItem;
use App\Models\WeighingRecord;
use App\Services\PrivateEvidenceService;
use App\Services\WeighingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WeighingRecordController extends Controller
{
    public function index(Request $request): View
    {
        $weighings = WeighingRecord::query()
            ->with(['saleItem.sale.customer', 'saleItem.product', 'dealDestination.deal.product', 'dealDestination.customer'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->value();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('vehicle_number', 'like', "%{$search}%")
                        ->orWhere('station_name', 'like', "%{$search}%")
                        ->orWhereHas('saleItem.sale', fn (Builder $sale) => $sale->where('reference_number', 'like', "%{$search}%"))
                        ->orWhereHas('dealDestination.deal', fn (Builder $deal) => $deal->where('reference_number', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($request->input('status'), array_column(WeighingStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->latest('weighing_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('weighings.index', compact('weighings'));
    }

    public function create(Request $request): View
    {
        return view('weighings.form', [
            'weighingRecord' => new WeighingRecord(['weighing_at' => now()]),
            'saleItems' => $this->availableSaleItems(),
            'selectedSaleItemId' => $request->integer('sale_item_id') ?: null,
        ]);
    }

    public function store(WeighingRecordRequest $request, WeighingService $service, PrivateEvidenceService $evidence): RedirectResponse
    {
        $data = $request->validated();
        $path = $request->file('ticket_image') ? $evidence->storeWeighbridgeBill($request->file('ticket_image')) : null;

        try {
            if ($path) {
                $data['ticket_image_path'] = $path;
            }
            $record = $service->create(Arr::except($data, 'ticket_image'), $request->user()->id);
        } catch (\Throwable $exception) {
            if ($path) {
                $evidence->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('weighings.show', $record)->with('success', __('weighings.created'));
    }

    public function show(WeighingRecord $weighingRecord): View
    {
        $weighingRecord->load(['saleItem.sale.customer', 'saleItem.product', 'saleItem.warehouse', 'dealDestination.deal.product', 'dealDestination.customer', 'creator', 'finalizer']);

        return view('weighings.show', compact('weighingRecord'));
    }

    public function edit(WeighingRecord $weighingRecord): View|RedirectResponse
    {
        if ($weighingRecord->status !== WeighingStatus::Draft) {
            return redirect()->route('weighings.show', $weighingRecord)->with('error', __('weighings.errors.finalized_immutable'));
        }

        if ($weighingRecord->deal_destination_id) {
            return redirect()->route('deals.show', $weighingRecord->dealDestination->deal);
        }

        $weighingRecord->load(['saleItem.sale.customer', 'saleItem.product', 'saleItem.warehouse']);

        return view('weighings.form', [
            'weighingRecord' => $weighingRecord,
            'saleItems' => collect([$weighingRecord->saleItem]),
            'selectedSaleItemId' => $weighingRecord->sale_item_id,
        ]);
    }

    public function update(WeighingRecordRequest $request, WeighingRecord $weighingRecord, WeighingService $service, PrivateEvidenceService $evidence): RedirectResponse
    {
        $data = $request->validated();
        $oldPath = $weighingRecord->ticket_image_path;
        $newPath = $request->file('ticket_image') ? $evidence->storeWeighbridgeBill($request->file('ticket_image')) : null;

        try {
            if ($newPath) {
                $data['ticket_image_path'] = $newPath;
            }
            $service->update($weighingRecord, Arr::except($data, 'ticket_image'));
        } catch (\Throwable $exception) {
            if ($newPath) {
                $evidence->delete($newPath);
            }
            throw $exception;
        }

        if ($newPath && $oldPath) {
            $evidence->delete($oldPath);
        }

        return redirect()->route('weighings.show', $weighingRecord)->with('success', __('weighings.updated'));
    }

    public function finalize(Request $request, WeighingRecord $weighingRecord, WeighingService $service): RedirectResponse
    {
        $changed = $service->finalize($weighingRecord, $request->user()->id);

        return redirect()->route('weighings.show', $weighingRecord)->with('success', $changed ? __('weighings.finalized') : __('weighings.already_finalized'));
    }

    public function ticket(WeighingRecord $weighingRecord, PrivateEvidenceService $evidence): StreamedResponse
    {
        abort_unless($weighingRecord->ticket_image_path, 404);

        return $evidence->response(
            $weighingRecord->ticket_image_path,
            'weighbridge-ticket-'.$weighingRecord->id.'.'.pathinfo($weighingRecord->ticket_image_path, PATHINFO_EXTENSION),
        );
    }

    /** @return Collection<int, SaleItem> */
    private function availableSaleItems()
    {
        return SaleItem::query()
            ->whereDoesntHave('weighingRecord')
            ->whereHas('sale', fn (Builder $query) => $query->where('status', TransactionStatus::Draft->value))
            ->with(['sale.customer', 'product', 'warehouse'])
            ->orderBy('id')
            ->get();
    }
}
