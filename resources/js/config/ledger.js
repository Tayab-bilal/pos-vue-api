// The two ledgers. "party" is who the statement is about.
export const ledgerKinds = {
    customer: {
        title: 'Customer Ledger',
        plural: 'Customers',
        party: 'Customer',
        listApi: '/api/ledgers/customers',
        statementApi: (id) => `/api/ledgers/customers/${id}`,
        listPath: '/ledgers',
        statementPath: (id) => `/ledgers/customers/${id}`,
        partyPath: (id) => `/customers/${id}`,
        outstandingLabel: 'Receivable (they owe us)',
        totalLabel: 'Total Receivable',
    },
    supplier: {
        title: 'Supplier Ledger',
        plural: 'Suppliers',
        party: 'Supplier',
        listApi: '/api/ledgers/suppliers',
        statementApi: (id) => `/api/ledgers/suppliers/${id}`,
        listPath: '/ledgers/suppliers',
        statementPath: (id) => `/ledgers/suppliers/${id}`,
        partyPath: (id) => `/suppliers/${id}`,
        outstandingLabel: 'Payable (we owe them)',
        totalLabel: 'Total Payable',
    },
};

// Where a statement entry came from, so a row can open the original document
export function entryLink(entry) {
    const id = entry.document_id;
    if (!id) return null;

    return (
        {
            sale: `/sales/${id}`,
            purchase: `/purchases/${id}`,
            payment: `/payments/${id}`,
            sale_return: `/returns/${id}`,
            purchase_return: `/returns/purchases/${id}`,
        }[entry.document_type] ?? null
    );
}