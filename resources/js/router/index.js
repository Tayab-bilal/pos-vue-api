import { createRouter, createWebHistory } from 'vue-router';
import AppLayout from '../layouts/applayout.vue';
import Login from '../pages/login.vue';
import Dashboard from '../pages/dashboard.vue';
import PlaceholderPage from '../pages/placeholderpage.vue';
import Categories from '../pages/categories.vue';
import Units from '../pages/units.vue';
import Products from '../pages/products.vue';
import ProductForm from '../pages/productform.vue';
import ProductShow from '../pages/productshow.vue';
import Suppliers from '../pages/suppliers.vue';
import SupplierForm from '../pages/supplierform.vue';
import SupplierShow from '../pages/suppliershow.vue';
import Customers from '../pages/customers.vue';
import CustomerForm from '../pages/customerform.vue';
import CustomerShow from '../pages/customershow.vue';
import Purchases from '../pages/purchases.vue';
import PurchaseForm from '../pages/purchaseform.vue';
import PurchaseShow from '../pages/purchaseshow.vue';
import PurchaseReturnForm from '../pages/purchasereturnform.vue';
import PurchaseReturns from '../pages/purchasereturns.vue';
import PurchaseReturnShow from '../pages/purchasereturnshow.vue';
import Sales from '../pages/sales.vue';
import SaleForm from '../pages/saleform.vue';
import SaleShow from '../pages/saleshow.vue';
import SaleReturnForm from '../pages/salereturnform.vue';
import SaleReturns from '../pages/salereturns.vue';
import SaleReturnShow from '../pages/salereturnshow.vue';
import Stock from '../pages/stock.vue';
import StockMovements from '../pages/stockmovements.vue';
import StockAdjustments from '../pages/stockadjustments.vue';
import StockAdjustmentForm from '../pages/stockadjustmentform.vue';
import StockAdjustmentShow from '../pages/stockadjustmentshow.vue';
import Payments from '../pages/payments.vue';
import PaymentForm from '../pages/paymentform.vue';
import PaymentShow from '../pages/paymentshow.vue';
import Expenses from '../pages/expenses.vue';
import ExpenseCategories from '../pages/expensecategories.vue';
import ExpenseForm from '../pages/expenseform.vue';
import ExpenseShow from '../pages/expenseshow.vue';
import Ledgers from '../pages/ledgers.vue';
import LedgerStatement from '../pages/ledgerstatement.vue';
import ReportTrade from '../pages/reporttrade.vue';
import ReportProfitLoss from '../pages/reportprofitloss.vue';
import ReportExpenses from '../pages/reportexpenses.vue';
import ReportStock from '../pages/reportstock.vue';
import { menu } from '../config/menu';
import { useAuthStore } from '../stores/auth';

// Add a module's page here once it is built (key = path name)
const pages = {
    categories: Categories,
    units: Units,
    products: Products,
    suppliers: Suppliers,
    customers: Customers,
    purchases: Purchases,
    sales: Sales,
    returns: SaleReturns,
    stock: Stock,
    payments: Payments,
    expenses: Expenses,
    ledgers: Ledgers,
    reports: ReportTrade,
};

const menuRoutes = menu
    .flatMap((group) => group.items)
    .map((item) => {
        if (item.path === '/') {
            return { path: '', name: 'dashboard', component: Dashboard, meta: { title: item.label } };
        }
        const name = item.path.slice(1);
        const meta = { title: item.label };
        // These pages serve more than one list, so they need to know which one they are
        if (name === 'payments') meta.kind = 'receipt';
        if (name === 'ledgers') meta.kind = 'customer';
        if (name === 'reports') meta.kind = 'sale';
        return { path: name, name, component: pages[name] ?? PlaceholderPage, meta };
    });

// Pages that are not in the sidebar (Add / Edit / Show screens)
const extraRoutes = [
    { path: 'products/create', name: 'products.create', component: ProductForm, meta: { title: 'Add Product' } },
    { path: 'products/:id/edit', name: 'products.edit', component: ProductForm, meta: { title: 'Edit Product' } },
    { path: 'products/:id', name: 'products.show', component: ProductShow, meta: { title: 'Product Details' } },

    { path: 'suppliers/create', name: 'suppliers.create', component: SupplierForm, meta: { title: 'Add Supplier' } },
    { path: 'suppliers/:id/edit', name: 'suppliers.edit', component: SupplierForm, meta: { title: 'Edit Supplier' } },
    { path: 'suppliers/:id', name: 'suppliers.show', component: SupplierShow, meta: { title: 'Supplier Details' } },

    { path: 'customers/create', name: 'customers.create', component: CustomerForm, meta: { title: 'Add Customer' } },
    { path: 'customers/:id/edit', name: 'customers.edit', component: CustomerForm, meta: { title: 'Edit Customer' } },
    { path: 'customers/:id', name: 'customers.show', component: CustomerShow, meta: { title: 'Customer Details' } },

    { path: 'purchases/create', name: 'purchases.create', component: PurchaseForm, meta: { title: 'New Purchase' } },
    { path: 'purchases/:id/edit', name: 'purchases.edit', component: PurchaseForm, meta: { title: 'Edit Purchase' } },
    { path: 'purchases/:id/return', name: 'purchases.return', component: PurchaseReturnForm, meta: { title: 'New Purchase Return' } },
    { path: 'purchases/:id', name: 'purchases.show', component: PurchaseShow, meta: { title: 'Purchase Details' } },

    { path: 'sales/create', name: 'sales.create', component: SaleForm, meta: { title: 'New Sale' } },
    { path: 'sales/:id/edit', name: 'sales.edit', component: SaleForm, meta: { title: 'Edit Sale' } },
    { path: 'sales/:id/return', name: 'sales.return', component: SaleReturnForm, meta: { title: 'New Sales Return' } },
    { path: 'sales/:id', name: 'sales.show', component: SaleShow, meta: { title: 'Sale Details' } },

    { path: 'returns/purchases', name: 'returns.purchases', component: PurchaseReturns, meta: { title: 'Purchase Returns' } },
    { path: 'returns/purchases/:id', name: 'returns.purchases.show', component: PurchaseReturnShow, meta: { title: 'Return Details' } },
    { path: 'returns/:id', name: 'returns.show', component: SaleReturnShow, meta: { title: 'Return Details' } },

    { path: 'stock/movements', name: 'stock.movements', component: StockMovements, meta: { title: 'Stock Movements' } },
    { path: 'stock/adjustments', name: 'stock.adjustments', component: StockAdjustments, meta: { title: 'Stock Adjustments' } },
    { path: 'stock/adjustments/create', name: 'stock.adjustments.create', component: StockAdjustmentForm, meta: { title: 'New Stock Adjustment' } },
    { path: 'stock/adjustments/:id', name: 'stock.adjustments.show', component: StockAdjustmentShow, meta: { title: 'Adjustment Details' } },

    { path: 'payments/suppliers', name: 'payments.suppliers', component: Payments, meta: { title: 'Supplier Payments', kind: 'payment' } },
    { path: 'payments/receipts/create', name: 'payments.receipts.create', component: PaymentForm, meta: { title: 'New Receipt', kind: 'receipt' } },
    { path: 'payments/suppliers/create', name: 'payments.suppliers.create', component: PaymentForm, meta: { title: 'New Payment', kind: 'payment' } },
    { path: 'payments/:id', name: 'payments.show', component: PaymentShow, meta: { title: 'Payment Details' } },

    { path: 'expenses/categories', name: 'expenses.categories', component: ExpenseCategories, meta: { title: 'Expense Categories' } },
    { path: 'expenses/create', name: 'expenses.create', component: ExpenseForm, meta: { title: 'Add Expense' } },
    { path: 'expenses/:id/edit', name: 'expenses.edit', component: ExpenseForm, meta: { title: 'Edit Expense' } },
    { path: 'expenses/:id', name: 'expenses.show', component: ExpenseShow, meta: { title: 'Expense Details' } },

    { path: 'ledgers/suppliers', name: 'ledgers.suppliers', component: Ledgers, meta: { title: 'Supplier Ledgers', kind: 'supplier' } },
    { path: 'ledgers/customers/:id', name: 'ledgers.customer.statement', component: LedgerStatement, meta: { title: 'Customer Ledger', kind: 'customer' } },
    { path: 'ledgers/suppliers/:id', name: 'ledgers.supplier.statement', component: LedgerStatement, meta: { title: 'Supplier Ledger', kind: 'supplier' } },

    { path: 'reports/purchases', name: 'reports.purchases', component: ReportTrade, meta: { title: 'Purchases Report', kind: 'purchase' } },
    { path: 'reports/profit-loss', name: 'reports.profit-loss', component: ReportProfitLoss, meta: { title: 'Profit & Loss' } },
    { path: 'reports/expenses', name: 'reports.expenses', component: ReportExpenses, meta: { title: 'Expenses Report' } },
    { path: 'reports/stock-valuation', name: 'reports.stock-valuation', component: ReportStock, meta: { title: 'Stock Valuation' } },
];

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: Login, meta: { title: 'Sign in', guest: true } },
        { path: '/', component: AppLayout, children: [...menuRoutes, ...extraRoutes] },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});

// Every page except the login page needs a signed-in user
router.beforeEach(async (to) => {
    const auth = useAuthStore();

    // On the first visit (or a refresh) ask the server who is signed in
    if (!auth.ready) await auth.fetchUser();

    // The login page is only for people who are not signed in
    if (to.meta.guest) return auth.isLoggedIn ? { path: '/' } : true;

    if (auth.isLoggedIn) return true;

    // Remember where they were going, so login can take them back there
    return { path: '/login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } };
});

export default router;