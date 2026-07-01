import Alpine from 'alpinejs';

window.Alpine = Alpine;

function emptyInvoiceItem() {
    return {
        product_id: '',
        name: '',
        description: '',
        quantity: 1,
        unit_price: 0,
        tax_rate: 0,
    };
}

// Client-side preview only — the server always recalculates totals
// authoritatively from the persisted item rows (never trusts this).
Alpine.data('invoiceForm', (initialItems, products) => ({
    products,
    items: initialItems.length ? initialItems : [emptyInvoiceItem()],

    addItem() {
        this.items.push(emptyInvoiceItem());
    },

    removeItem(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
        }
    },

    applyProduct(index) {
        const item = this.items[index];
        const product = this.products.find((candidate) => String(candidate.id) === String(item.product_id));

        if (product) {
            item.name = product.name;
            item.description = product.description ?? '';
            item.unit_price = product.unit_price;
            item.tax_rate = product.tax_rate;
        }
    },

    lineSubtotal(item) {
        return (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
    },

    lineTax(item) {
        return this.lineSubtotal(item) * ((Number(item.tax_rate) || 0) / 100);
    },

    lineTotal(item) {
        return this.lineSubtotal(item) + this.lineTax(item);
    },

    subtotal() {
        return this.items.reduce((sum, item) => sum + this.lineSubtotal(item), 0);
    },

    taxTotal() {
        return this.items.reduce((sum, item) => sum + this.lineTax(item), 0);
    },

    grandTotal() {
        return this.subtotal() + this.taxTotal();
    },

    money(value) {
        return (Math.round((value + Number.EPSILON) * 100) / 100).toFixed(2);
    },
}));

Alpine.start();
