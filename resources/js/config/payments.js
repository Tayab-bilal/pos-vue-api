// Payment methods
export const paymentMethods = {
    cash: 'Cash',
    bank_transfer: 'Bank transfer',
    cheque: 'Cheque',
    mobile_wallet: 'Mobile wallet',
    other: 'Other',
};

// The two kinds of payment document. "party" is who the money is with.
export const paymentKinds = {
    receipt: {
        title: 'Receipt',
        plural: 'Receipts',
        document: 'Payment Receipt',
        party: 'Customer',
        invoice: 'Sale',
        listApi: '/api/receipts',
        listPath: '/payments',
        createPath: '/payments/receipts/create',
    },
    payment: {
        title: 'Payment',
        plural: 'Payments',
        document: 'Payment Voucher',
        party: 'Supplier',
        invoice: 'Purchase',
        listApi: '/api/supplier-payments',
        listPath: '/payments/suppliers',
        createPath: '/payments/suppliers/create',
    },
};