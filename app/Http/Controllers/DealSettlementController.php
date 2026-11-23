<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealAllocationRequest;
use App\Http\Requests\DealDestinationLineRequest;
use App\Http\Requests\DealExpenseRequest;
use App\Http\Requests\DealQuantityAdjustmentRequest;
use App\Http\Requests\DealSourceLineRequest;
use App\Models\Deal;
use App\Models\DealAllocation;
use App\Models\DealDestination;
use App\Models\DealExpense;
use App\Models\DealQuantityAdjustment;
use App\Models\DealSource;
use App\Services\DealAllocationService;
use App\Services\DealCommercialRecordService;
use App\Services\DealService;
use App\Services\DealSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DealSettlementController extends Controller
{
    public function storeSource(DealSourceLineRequest $request, Deal $deal, DealService $service): RedirectResponse
    {
        $service->addSource($deal, $request->validated());

        return $this->back($deal, __('settlements.source_added'));
    }

    public function storeDestination(DealDestinationLineRequest $request, Deal $deal, DealService $service): RedirectResponse
    {
        $service->addDestination($deal, $request->validated());

        return $this->back($deal, __('settlements.destination_added'));
    }

    public function destroySource(Deal $deal, DealSource $dealSource, DealService $service): RedirectResponse
    {
        abort_unless($dealSource->deal_id === $deal->id, 404);
        $service->removeSource($dealSource);

        return $this->back($deal, __('common.success'));
    }

    public function destroyDestination(Deal $deal, DealDestination $dealDestination, DealService $service): RedirectResponse
    {
        abort_unless($dealDestination->deal_id === $deal->id, 404);
        $service->removeDestination($dealDestination);

        return $this->back($deal, __('common.success'));
    }

    public function complete(Deal $deal, Request $request, DealService $service): RedirectResponse
    {
        $service->complete($deal, $request->user()->id);

        return $this->back($deal, __('deals.completed'));
    }

    public function storeAllocation(DealAllocationRequest $request, Deal $deal, DealAllocationService $service): RedirectResponse
    {
        $source = $deal->sources()->findOrFail($request->integer('deal_source_id'));
        $destination = $deal->destinations()->findOrFail($request->integer('deal_destination_id'));
        $service->allocate($deal, $source, $destination, (string) $request->validated('quantity_kg'), $request->user()->id);

        return $this->back($deal, __('settlements.allocation_saved'));
    }

    public function destroyAllocation(Deal $deal, DealAllocation $dealAllocation, DealAllocationService $service): RedirectResponse
    {
        abort_unless($dealAllocation->deal_id === $deal->id, 404);
        $service->delete($dealAllocation);

        return $this->back($deal, __('settlements.allocation_deleted'));
    }

    public function storeAdjustment(DealQuantityAdjustmentRequest $request, Deal $deal, DealCommercialRecordService $service): RedirectResponse
    {
        $service->createAdjustment($deal, $request->validated(), $request->user()->id);

        return $this->back($deal, __('settlements.adjustment_saved'));
    }

    public function confirmAdjustment(Request $request, Deal $deal, DealQuantityAdjustment $dealQuantityAdjustment, DealCommercialRecordService $service): RedirectResponse
    {
        abort_unless($dealQuantityAdjustment->deal_id === $deal->id, 404);
        $service->confirmAdjustment($dealQuantityAdjustment, $request->user()->id);

        return $this->back($deal, __('settlements.adjustment_confirmed'));
    }

    public function storeExpense(DealExpenseRequest $request, Deal $deal, DealCommercialRecordService $service): RedirectResponse
    {
        $service->createExpense($deal, $request->validated(), $request->user()->id);

        return $this->back($deal, __('settlements.expense_saved'));
    }

    public function confirmExpense(Request $request, Deal $deal, DealExpense $dealExpense, DealCommercialRecordService $service): RedirectResponse
    {
        abort_unless($dealExpense->deal_id === $deal->id, 404);
        $service->confirmExpense($dealExpense, $request->user()->id);

        return $this->back($deal, __('settlements.expense_confirmed'));
    }

    public function destroyAdjustment(Deal $deal, DealQuantityAdjustment $dealQuantityAdjustment, DealCommercialRecordService $service): RedirectResponse
    {
        abort_unless($dealQuantityAdjustment->deal_id === $deal->id, 404);
        $service->deleteAdjustment($dealQuantityAdjustment);

        return $this->back($deal, __('common.success'));
    }

    public function destroyExpense(Deal $deal, DealExpense $dealExpense, DealCommercialRecordService $service): RedirectResponse
    {
        abort_unless($dealExpense->deal_id === $deal->id, 404);
        $service->deleteExpense($dealExpense);

        return $this->back($deal, __('common.success'));
    }

    public function prepare(Request $request, Deal $deal, DealSettlementService $service): RedirectResponse
    {
        $service->prepare($deal, $request->user()->id, $request->string('notes')->trim()->value() ?: null);

        return $this->back($deal, __('settlements.prepared'));
    }

    public function confirm(Request $request, Deal $deal, DealSettlementService $service): RedirectResponse
    {
        $service->confirm($deal, $request->user()->id, $request->string('notes')->trim()->value() ?: null);

        return $this->back($deal, __('settlements.confirmed'));
    }

    private function back(Deal $deal, string $message): RedirectResponse
    {
        return redirect()->route('deals.show', $deal)->with('success', $message);
    }
}
