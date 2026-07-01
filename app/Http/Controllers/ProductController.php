<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(protected ProductService $products) {}

    public function index(Request $request): View
    {
        return view('products.index', [
            'products' => $this->products->paginateForUser(
                $request->user(),
                $request->string('search')->trim()->value() ?: null,
                $request->string('status')->value() ?: null,
            ),
            'search' => $request->string('search')->value(),
            'status' => $request->string('status')->value(),
        ]);
    }

    public function create(): View
    {
        return view('products.create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->products->create($request->user(), $request->productData());

        return redirect()->route('products.index')->with('status', 'product-created');
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('products.edit', ['product' => $product]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->update($product, $request->productData());

        return redirect()->route('products.index')->with('status', 'product-updated');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->products->delete($product);

        return redirect()->route('products.index')->with('status', 'product-deleted');
    }
}
