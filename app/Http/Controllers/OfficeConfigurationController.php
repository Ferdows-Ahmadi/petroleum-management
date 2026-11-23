<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfficeConfigurationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tab = in_array($request->input('tab'), ['company', 'products', 'warehouses', 'users', 'financial'], true) ? $request->input('tab') : 'company';

        return view('office.configuration', [
            'tab' => $tab,
            'products' => Product::query()->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
            'accounts' => FinancialAccount::query()->orderBy('name')->get(),
        ]);
    }
}
