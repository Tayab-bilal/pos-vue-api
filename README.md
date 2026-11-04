# POS Management & Inventory System

A modern, full-stack Point of Sale (POS) and Inventory Management System built to manage daily business operations, sales, purchases, inventory, customers, and suppliers.

## Overview

The POS Management & Inventory System helps businesses manage products, stock, sales, purchases, and financial transactions through a centralized dashboard.

The application uses Laravel REST APIs for backend operations and Vue.js for a responsive frontend interface.

## Technologies Used

- **Backend:** Laravel 12, PHP
- **Frontend:** Vue.js 3, JavaScript
- **Styling:** Tailwind CSS 4
- **Database:** MySQL
- **API:** REST API
- **Authentication:** Laravel Sanctum
- **Build Tool:** Vite

## Main Features

- Dashboard with business statistics
- Product, category, and unit management
- Stock and inventory management
- Purchase and purchase return management
- Sales and sales return management
- Customer and supplier management
- Payment and outstanding balance management
- Expense and expense category management
- Customer and supplier ledgers
- Stock adjustments and stock movements
- Reports and business analytics

## Installation & Setup

### 1. Clone the Repository

```bash
git clone https://github.com/Tayab-bilal/pos-vue-api.git
cd pos-vue-api
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Configure your MySQL database credentials in the `.env` file.

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Start Development Servers

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

Open: http://127.0.0.1:8000

## Developer

**Tayab Bilal**

Full Stack Web Developer | Laravel & PHP Developer

GitHub: https://github.com/Tayab-bilal

## License

This project is available for learning and portfolio demonstration. Please contact the developer for other usage permissions.