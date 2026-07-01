<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportProductsCsvRequest;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductCsvImportController extends Controller
{
    public function __construct(protected ProductService $products) {}

    public function create(): View
    {
        return view('products.import');
    }

    public function store(ImportProductsCsvRequest $request): RedirectResponse
    {
        $result = $this->products->importFromCsv($request->user(), $request->file('file'));

        return redirect()->route('products.import.create')->with('importResult', $result);
    }
}
