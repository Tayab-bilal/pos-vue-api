// Where a search result opens
export function searchLink(type, id) {
    return (
        {
            product: `/products/${id}`,
            customer: `/customers/${id}`,
            supplier: `/suppliers/${id}`,
            sale: `/sales/${id}`,
            purchase: `/purchases/${id}`,
            sale_return: `/returns/${id}`,
            purchase_return: `/returns/purchases/${id}`,
            payment: `/payments/${id}`,
            expense: `/expenses/${id}`,
        }[type] ?? null
    );
}

// Icon of each group of results
export const groupIcons = {
    products: 'products',
    customers: 'customers',
    suppliers: 'suppliers',
    sales: 'sales',
    purchases: 'purchases',
    returns: 'returns',
    payments: 'banknotes',
    expenses: 'banknotes',
};

// Badge colours
export const toneClasses = {
    success: 'badge-success',
    warning: 'badge-warning',
    danger: 'badge-danger',
    neutral: 'bg-slate-100 text-slate-600',
};