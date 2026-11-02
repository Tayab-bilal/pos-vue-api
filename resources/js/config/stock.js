// Labels for every kind of stock movement written by the system
export const movementTypes = {
    opening: 'Opening stock',
    purchase: 'Purchase',
    purchase_edit: 'Purchase edited',
    purchase_cancel: 'Purchase cancelled',
    sale: 'Sale',
    sale_edit: 'Sale edited',
    sale_cancel: 'Sale cancelled',
    sale_return: 'Sales return',
    sale_return_cancel: 'Sales return cancelled',
    purchase_return: 'Purchase return',
    purchase_return_cancel: 'Purchase return cancelled',
    adjustment: 'Adjustment',
    adjustment_cancel: 'Adjustment cancelled',
};

// Stock adjustment types. "decrease" types can only reduce stock.
export const adjustmentTypes = {
    damage: { label: 'Damage', hint: 'Damaged goods. Reduces stock.', decrease: true },
    loss: { label: 'Loss / Theft', hint: 'Lost or stolen goods. Reduces stock.', decrease: true },
    expired: { label: 'Expired', hint: 'Expired goods. Reduces stock.', decrease: true },
    count_correction: { label: 'Count correction', hint: 'Fix stock after a physical count. Can add or remove.', decrease: false },
    other: { label: 'Other', hint: 'Any other reason. Can add or remove.', decrease: false },
};

// Where a stock movement came from, so the history can link back to the document
export function movementLink(movement) {
    const id = movement.reference_id;
    if (!id) return null;

    return (
        {
            purchase: `/purchases/${id}`,
            sale: `/sales/${id}`,
            sale_return: `/returns/${id}`,
            purchase_return: `/returns/purchases/${id}`,
            stock_adjustment: `/stock/adjustments/${id}`,
            product: `/products/${id}`,
        }[movement.reference_type] ?? null
    );
}