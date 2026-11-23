<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Deal;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', ['products' => Product::orderBy('name')->paginate(15)]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('products.form', ['product' => new Product]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return redirect()->route('products.index')->with('success', __('products.created'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): RedirectResponse
    {
        return redirect()->route('products.edit', $product);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product): View
    {
        return view('products.form', compact('product'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index')->with('success', __('products.updated'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        if ($product->inventoryMovements()->exists() || PurchaseItem::where('product_id', $product->id)->exists() || SaleItem::where('product_id', $product->id)->exists()
            || Deal::query()->where('product_id', $product->id)->exists()) {
            return back()->with('error', __('trades.errors.master_in_use'));
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', __('products.deleted'));
    }
}
