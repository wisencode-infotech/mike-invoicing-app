@props(['invoice' => null, 'customers', 'products'])

@php
    $initialItems = $invoice
        ? $invoice->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'name' => $item->name,
            'description' => $item->description,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'tax_rate' => (float) $item->tax_rate,
        ])->values()
        : collect(old('items', []));

    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name,
        'description' => $product->description,
        'unit_price' => (float) $product->unit_price,
        'tax_rate' => (float) $product->tax_rate,
    ]);
@endphp

<div x-data="invoiceForm(@js($initialItems), @js($productOptions))">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="customer_id" :value="__('Customer')" />
            <select id="customer_id" name="customer_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('Select a customer') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(old('customer_id', $invoice?->customer_id) == $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('customer_id')" />
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="issue_date" :value="__('Issue Date')" />
            <x-text-input
                id="issue_date" name="issue_date" type="date" class="mt-1 block w-full"
                :value="old('issue_date', $invoice?->issue_date?->toDateString() ?? now()->toDateString())"
                required
            />
            <x-input-error class="mt-2" :messages="$errors->get('issue_date')" />
        </div>

        <div>
            <x-input-label for="due_date" :value="__('Due Date')" />
            <x-text-input
                id="due_date" name="due_date" type="date" class="mt-1 block w-full"
                :value="old('due_date', $invoice?->due_date?->toDateString() ?? now()->addDays((int) config('invoice.default_due_days'))->toDateString())"
                required
            />
            <x-input-error class="mt-2" :messages="$errors->get('due_date')" />
        </div>
    </div>

    <div class="mt-6">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Items') }}</h3>

        <div class="mt-2 overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Product') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Name') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Description') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('Qty') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('Unit Price') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('Tax %') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('Line Total') }}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <template x-for="(item, index) in items" :key="index">
                        <tr>
                            <td class="px-3 py-2">
                                <select x-model="item.product_id" @change="applyProduct(index)" :name="`items[${index}][product_id]`" class="block w-full min-w-[9rem] rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('— manual —') }}</option>
                                    <template x-for="product in products" :key="product.id">
                                        <option :value="product.id" x-text="product.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" x-model="item.name" :name="`items[${index}][name]`" required class="block w-full min-w-[10rem] rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" x-model="item.description" :name="`items[${index}][description]`" class="block w-full min-w-[10rem] rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0.01" x-model.number="item.quantity" :name="`items[${index}][quantity]`" required class="w-20 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" x-model.number="item.unit_price" :name="`items[${index}][unit_price]`" required class="w-24 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" max="100" x-model.number="item.tax_rate" :name="`items[${index}][tax_rate]`" class="w-20 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-medium text-gray-900" x-text="money(lineTotal(item))"></td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="removeItem(index)" x-show="items.length > 1" class="text-red-600 hover:text-red-900">{{ __('Remove') }}</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <button type="button" @click="addItem()" class="mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-900">{{ __('+ Add Item') }}</button>

        <x-input-error class="mt-2" :messages="$errors->get('items')" />

        <div class="mt-4 flex justify-end">
            <dl class="w-full max-w-xs space-y-1 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('Subtotal') }}</dt>
                    <dd class="font-medium text-gray-900" x-text="money(subtotal())"></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('Tax') }}</dt>
                    <dd class="font-medium text-gray-900" x-text="money(taxTotal())"></dd>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-1 text-base">
                    <dt class="font-semibold text-gray-900">{{ __('Total') }}</dt>
                    <dd class="font-semibold text-gray-900" x-text="money(grandTotal())"></dd>
                </div>
            </dl>
        </div>

        <p class="mt-1 text-right text-xs text-gray-400">{{ __('Totals shown are an estimate — the server recalculates authoritatively on save.') }}</p>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="notes" :value="__('Notes')" />
            <x-textarea id="notes" name="notes" rows="3" class="mt-1">{{ old('notes', $invoice?->notes) }}</x-textarea>
            <x-input-error class="mt-2" :messages="$errors->get('notes')" />
        </div>

        <div>
            <x-input-label for="terms" :value="__('Terms')" />
            <x-textarea id="terms" name="terms" rows="3" class="mt-1">{{ old('terms', $invoice?->terms) }}</x-textarea>
            <x-input-error class="mt-2" :messages="$errors->get('terms')" />
        </div>
    </div>
</div>
