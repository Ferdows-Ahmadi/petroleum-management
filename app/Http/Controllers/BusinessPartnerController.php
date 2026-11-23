<?php

namespace App\Http\Controllers;

use App\Enums\PartnerRole;
use App\Http\Requests\BusinessPartnerRequest;
use App\Models\BusinessPartner;
use App\Models\DealExpense;
use App\Services\PartnerLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BusinessPartnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $partners = BusinessPartner::query()
            ->with('roles')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->value();
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when(in_array($request->input('status'), ['active', 'inactive'], true), fn (Builder $query) => $query->where('is_active', $request->input('status') === 'active'))
            ->when(in_array($request->input('role'), array_column(PartnerRole::cases(), 'value'), true), fn (Builder $query) => $query->withRole($request->input('role')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('partners.index', ['partners' => $partners, 'partnerRoles' => PartnerRole::cases()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('partners.form', [
            'partner' => new BusinessPartner,
            'partnerRoles' => PartnerRole::cases(),
            'selectedRoles' => [],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BusinessPartnerRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $roles = $validated['roles'];
        unset($validated['roles']);

        $partner = DB::transaction(function () use ($validated, $roles): BusinessPartner {
            $partner = BusinessPartner::create($validated);
            $partner->roles()->createMany(array_map(fn (string $role): array => ['role' => $role], $roles));

            return $partner;
        });

        return redirect()->route('partners.show', $partner)->with('success', __('partners.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(BusinessPartner $businessPartner, PartnerLedgerService $ledger): View
    {
        $businessPartner->load('roles');

        return view('partners.show', ['partner' => $businessPartner, 'partnerBalance' => $ledger->balanceFor($businessPartner)]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BusinessPartner $businessPartner): View
    {
        $businessPartner->load('roles');

        return view('partners.form', [
            'partner' => $businessPartner,
            'partnerRoles' => PartnerRole::cases(),
            'selectedRoles' => $businessPartner->roles->pluck('role.value')->all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BusinessPartnerRequest $request, BusinessPartner $businessPartner): RedirectResponse
    {
        $validated = $request->validated();
        $roles = $validated['roles'];
        unset($validated['roles']);

        DB::transaction(function () use ($businessPartner, $validated, $roles): void {
            $businessPartner->update($validated);
            $businessPartner->roles()->delete();
            $businessPartner->roles()->createMany(array_map(fn (string $role): array => ['role' => $role], $roles));
        });

        if ($request->input('return_to') === 'accounts') {
            return redirect()->route('office.accounts', ['partner_id' => $businessPartner->id])
                ->with('success', __('partners.updated'));
        }

        return redirect()->route('partners.show', $businessPartner)->with('success', __('partners.updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BusinessPartner $businessPartner): RedirectResponse
    {
        if ($businessPartner->purchases()->exists() || $businessPartner->sales()->exists() || $businessPartner->financialTransactions()->exists()
            || $businessPartner->moneyMovements()->exists() || $businessPartner->sarafMoneyMovements()->exists()
            || $businessPartner->openingBalance()->exists() || $businessPartner->contracts()->exists()
            || $businessPartner->dealSources()->exists() || $businessPartner->dealDestinations()->exists()
            || DealExpense::query()->where('payee_id', $businessPartner->id)->exists()) {
            return back()->with('error', __('trades.errors.master_in_use'));
        }

        $businessPartner->delete();

        return redirect()->route('partners.index')->with('success', __('partners.deleted'));
    }
}
