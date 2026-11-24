<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Currency;
use App\Http\Requests\ContractRequest;
use App\Models\BusinessPartner;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $contracts = Contract::query()->with('partner')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->value();
                $query->where(fn (Builder $query) => $query->where('contract_number', 'like', "%{$search}%")->orWhereHas('partner', fn (Builder $partner) => $partner->where('name', 'like', "%{$search}%")));
            })
            ->when(in_array($request->input('status'), array_column(ContractStatus::cases(), 'value'), true), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->latest('start_date')->latest('id')->paginate(15)->withQueryString();

        return view('contracts.index', ['contracts' => $contracts, 'statuses' => ContractStatus::cases()]);
    }

    public function create(): View
    {
        return $this->form(new Contract(['start_date' => now(), 'contract_type' => ContractType::General]));
    }

    public function store(ContractRequest $request): RedirectResponse
    {
        $contract = Contract::create([...$request->validated(), 'created_by' => $request->user()->id]);

        return redirect()->route('contracts.show', $contract)->with('success', __('contracts.created'));
    }

    public function show(Contract $contract): View
    {
        $contract->load(['partner', 'creator']);

        return view('contracts.show', compact('contract'));
    }

    public function edit(Contract $contract): View
    {
        return $this->form($contract);
    }

    public function update(ContractRequest $request, Contract $contract): RedirectResponse
    {
        $contract->update($request->validated());

        return redirect()->route('contracts.show', $contract)->with('success', __('contracts.updated'));
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $contract->delete();

        return redirect()->route('contracts.index')->with('success', __('contracts.deleted'));
    }

    private function form(Contract $contract): View
    {
        return view('contracts.form', [
            'contract' => $contract,
            'partners' => BusinessPartner::query()->where('is_active', true)->orderBy('name')->get(),
            'types' => ContractType::cases(),
            'statuses' => ContractStatus::registrationChoices($contract->status),
            'currencies' => Currency::cases(),
        ]);
    }
}
