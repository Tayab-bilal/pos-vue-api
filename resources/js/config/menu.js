export const menu = [
    {
        heading: 'Main',
        items: [{ label: 'Dashboard', path: '/', icon: 'dashboard' }],
    },
    {
        heading: 'Inventory',
        items: [
            { label: 'Products', path: '/products', icon: 'products' },
            { label: 'Categories', path: '/categories', icon: 'categories' },
            { label: 'Units', path: '/units', icon: 'units' },
            { label: 'Stock', path: '/stock', icon: 'stock' },
        ],
    },
    {
        heading: 'Trading',
        items: [
            { label: 'Purchases', path: '/purchases', icon: 'purchases' },
            { label: 'Sales', path: '/sales', icon: 'sales' },
            { label: 'Returns', path: '/returns', icon: 'returns' },
        ],
    },
    {
        heading: 'Contacts',
        items: [
            { label: 'Suppliers', path: '/suppliers', icon: 'suppliers' },
            { label: 'Customers', path: '/customers', icon: 'customers' },
        ],
    },
    {
        heading: 'Finance',
        items: [
            { label: 'Payments', path: '/payments', icon: 'returns' },
            { label: 'Expenses', path: '/expenses', icon: 'banknotes' },
            { label: 'Ledgers', path: '/ledgers', icon: 'ledgers' },
            { label: 'Reports', path: '/reports', icon: 'reports' },
        ],
    },
];