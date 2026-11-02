// Tabs of the Reports page
export const reportTabs = [
    { label: 'Sales', to: '/reports', exact: true },
    { label: 'Purchases', to: '/reports/purchases', exact: false },
    { label: 'Profit & Loss', to: '/reports/profit-loss', exact: false },
    { label: 'Expenses', to: '/reports/expenses', exact: false },
    { label: 'Stock Valuation', to: '/reports/stock-valuation', exact: false },
];

export const paymentStatusLabels = { paid: 'Paid', partial: 'Partially paid', unpaid: 'Unpaid' };

export const money = (value) =>
    Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Negative amounts are shown with a minus sign, e.g. "- 1,200.00"
export const signedMoney = (value) => (Number(value) < 0 ? `- ${money(Math.abs(Number(value)))}` : money(value));

export const percent = (value) => (value === null || value === undefined ? '-' : `${Number(value).toFixed(1)}%`);

// Text colour for profit figures: red when it is a loss
export const profitClass = (value) => (Number(value) < 0 ? 'text-red-600' : 'text-slate-800');

// A plain date like 2026-10-19 is read as local time, so the day never shifts
const toDate = (value) => new Date(String(value).length === 10 ? `${value}T00:00:00` : value);

export const formatDate = (value) =>
    value ? toDate(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';

export const formatDay = (value) => (value ? toDate(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : '');

export const formatMonth = (value) =>
    value ? new Date(`${value}-01T00:00:00`).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }) : '';

export function periodLabel(from, to) {
    if (from && to) return `${formatDate(from)} to ${formatDate(to)}`;
    if (from) return `From ${formatDate(from)}`;
    if (to) return `Up to ${formatDate(to)}`;
    return 'All time';
}