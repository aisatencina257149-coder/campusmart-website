# CampusMart POS - Sales & Point-of-Sale System

A complete, working Management Information System (MIS) final project: **Sales and Point-of-Sale (POS) System**.

Built with **HTML, CSS, JavaScript, PHP, MySQL** (recommended stack from the project guidelines).

## Features

### Required Features Implemented
- ✅ User login and authentication (role-based: Admin / Cashier)
- ✅ Dashboard with real-time stats (today's sales, low stock alerts, top products)
- ✅ Data entry and management (Products, Categories, Users)
- ✅ Full CRUD operations (Create, Read, Update, Delete/Deactivate)
- ✅ Search and filtering (products by name/barcode/category; sales by date/invoice)
- ✅ Report generation (daily sales, top products, payment methods, cashier performance)
- ✅ Data storage with proper database (MySQL)
- ✅ User-friendly Bootstrap 5 interface

### POS-Specific Features
- Product grid with click-to-add
- Cart management (qty adjust, remove, clear)
- Discount support
- Multiple payment methods (Cash, Card, GCash, Other)
- Change calculation
- Automatic stock deduction on sale
- Invoice generation + printable receipt
- Low stock alerts

## Demo Accounts

| Role    | Username | Password    |
|---------|----------|-------------|
| Admin   | admin    | admin123    |
| Cashier | cashier  | cashier123  |

## How to Run (on your machine with XAMPP)

1. **Copy the folder** `pos_system` into your XAMPP `htdocs` directory  
   (e.g. `C:\xampp\htdocs\pos_system`)

2. **Create the database**:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `pos_system`
   - Import the SQL file: `database/schema.sql` (or run the SQL provided below)

3. **Update database credentials** (if different) in:
   `includes/config.php`

4. **Start Apache + MySQL** in XAMPP Control Panel

5. **Open in browser**:
   http://localhost/pos_system/login.php

### Quick Database Setup (SQL)

```sql
CREATE DATABASE IF NOT EXISTS pos_system;
USE pos_system;

-- Then run the full schema from database/schema.sql
```

## Project Structure

```
pos_system/
├── api/
│   └── process_sale.php      # Handles checkout transactions
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── includes/
│   ├── config.php            # DB connection + helpers
│   ├── header.php
│   └── footer.php
├── login.php
├── logout.php
├── index.php                 # Dashboard
├── pos.php                   # Point of Sale interface
├── products.php              # Product management (CRUD)
├── sales.php                 # Sales history + details
├── reports.php               # Analytics & reports (Admin)
├── users.php                 # User management (Admin)
└── README.md
```

## Database Tables

- `users` – authentication & roles
- `categories` – product categories
- `products` – inventory with stock levels
- `sales` – transaction headers (invoice, totals, payment)
- `sale_items` – line items (with foreign keys)

All tables use proper **Primary Keys** and **Foreign Keys**.

## For Your MIS Documentation / Presentation

This system addresses a real operational need (school canteen / campus store sales tracking).

You can map it to the required sections:

1. **Introduction** – Manual sales recording is slow, error-prone, no real-time inventory.
2. **Problem Identification** – Lost sales data, stockouts, difficult reporting.
3. **Objectives** – Automate sales, track inventory, generate reports, improve decision-making.
4. **Scope** – Sales, inventory, basic reporting for a single location.
5. **System Users** – Admin (full access), Cashier (POS + view sales).
6. **Requirements** – Web browser, XAMPP (Apache + MySQL + PHP).
7. **Workflow** – Login → POS → Add items → Checkout → Stock update → Reports.
8. **Flowchart** – Draw based on the POS process.
9. **Database Design** – Use the ERD from the 5 tables above.
10. **System Design** – Screenshots of login, dashboard, POS, products, reports.
11. **IS Components** – People (cashiers/admins), Procedures (sale flow), Data (products/sales), Software (this app), Hardware (PC/browser), Networks (localhost or LAN).
12. **Expected Benefits** – Faster checkout, accurate inventory, sales insights, reduced errors.

## Notes for Presentation

- Demonstrate: Login → Add products if needed → Process a sale → Show stock decreased → View sales report.
- Highlight MIS concepts: data accuracy, decision support (reports/dashboard), operational efficiency.
- The system uses prepared statements (security), transactions (data integrity), and role-based access control.

---
**MIS Final Project – Sales and Point-of-Sale System**  
Ready for demonstration and documentation.
