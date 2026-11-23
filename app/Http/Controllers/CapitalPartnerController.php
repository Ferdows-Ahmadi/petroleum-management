<?php

namespace App\Http\Controllers;

use App\Http\Requests\CapitalPartnerRequest;
use App\Models\CapitalPartner;
use App\Services\CapitalBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CapitalPartnerController extends Controller
{
    public function index(CapitalBalanceService $balances): View
    {
        $partners = CapitalPartner::query()->orderBy('name')->get();
        $summaries = $partners->mapWithKeys(fn (CapitalPartner $partner): array => [$partner->id => $balances->summaryFor($partner)]);

        return view('capital.partners.index', compact('partners', 'summaries'));
    }

    public function create(): View
    {
        return view('capital.partners.form', ['capitalPartner' => new CapitalPartner(['is_active' => true])]);
    }

    public function store(CapitalPartnerRequest $request): RedirectResponse
    {
        $partner = CapitalPartner::create($request->validated());

        return redirect()->route('capital-partners.show', $partner)->with('success', __('capital.partner_created'));
    }

    public function show(CapitalPartner $capitalPartner, CapitalBalanceService $balances): View
    {
        return view('capital.partners.show', [
            'capitalPartner' => $capitalPartner,
            'summary' => $balances->summaryFor($capitalPartner),
            'statement' => $balances->statementFor($capitalPartner),
        ]);
    }

    public function edit(CapitalPartner $capitalPartner): View
    {
        return view('capital.partners.form', compact('capitalPartner'));
    }

    public function update(CapitalPartnerRequest $request, CapitalPartner $capitalPartner): RedirectResponse
    {
        $capitalPartner->update($request->validated());

        return redirect()->route('capital-partners.show', $capitalPartner)->with('success', __('capital.partner_updated'));
    }

    public function destroy(CapitalPartner $capitalPartner): RedirectResponse
    {
        if ($capitalPartner->transactions()->exists()) {
            return back()->with('error', __('capital.errors.partner_in_use'));
        }
        $capitalPartner->delete();

        return redirect()->route('capital-partners.index')->with('success', __('capital.partner_deleted'));
    }
}
