# Nexii-Tech Accounting System

A functional, JSON-backed accounting demonstration built for **Nexii-Tech Solutions Inc.** The application models the complete path from transaction entry and review to posting, ledgers, balances, reports, and audit history—without requiring a production database.

> **Demo environment:** This project uses fictional records stored in local JSON files. It is intended for demonstrations, internal evaluation, and OJT development—not production accounting.

## Highlights

- Double-entry journal validation and controlled posting
- Role-aware interfaces and server-side permission enforcement
- Accounts Receivable and Accounts Payable workflows
- Customer, vendor, and expense payment review flows
- General Ledger and financial reports derived from posted journals
- Cash and bank activity with reconciliation safeguards
- CSV, PDF, and print outputs where supported
- Read-only audit history with record-detail modals
- Light and dark themes with responsive navigation
- Recoverable demo-data reset that preserves configuration

## Modules

| Area | Capabilities |
| --- | --- |
| Dashboard | Cash, receivables, payables, revenue, expenses, net income, aging, and recent activity |
| Chart of Accounts | Account configuration, status management, ledger access, CSV/PDF export |
| Journal Entries | Draft, review, posting, reversal, print, CSV/PDF export |
| General Ledger | Posted account activity, running balances, filters, CSV/PDF export |
| Sales & Revenue | Customer master data, invoice creation and posting |
| Accounts Receivable | Open invoices, aging, payment allocation and posting |
| Accounts Payable | Vendors, bills, aging, vendor payment workflow, expense payables |
| Cash & Bank | Cash accounts, transactions, reversals, activity, reconciliation |
| Expenses | Paid/unpaid expenses, approval, settlement, and reversal workflows |
| Financial Reports | Trial Balance, Income Statement, Balance Sheet, and expense reporting |
| Tax Settings | Static VAT/tax-code configuration and summaries |
| Audit Trail | Searchable event history, CSV export, print, and record details |
| Users & Settings | Demo users, company/system preferences, and Reset Demo Data |

## Technology

- PHP 8.3+
- Laravel 13
- Blade templates
- JavaScript ES modules
- Tailwind CSS 4
- Vite 8
- Pest/PHPUnit and Node.js test runners
- FPDF-based PDF generation

## Installation

### Requirements

- PHP 8.3 or newer with the extensions required by Laravel
- Composer
- Node.js and npm

### Setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000).

The Composer shortcut performs dependency installation, environment creation, key generation, and the frontend build:

```powershell
composer setup
```

## Development

Start Laravel and Vite together:

```powershell
composer run dev
```

When serving the application to another device, build the frontend first and bind Laravel to all network interfaces:

```powershell
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Open `http://<computer-ip>:8000` from the other device. If the page is unstyled, confirm `public/hot` is absent, rebuild with `npm run build`, and hard-refresh the browser.

## Architecture

The project deliberately separates accounting rules from page rendering:

```text
Blade forms and JavaScript previews
                │
                ▼
Laravel controllers and permission middleware
                │
                ▼
AccountingPostingService and workflow services
                │
                ├── Journal entries
                ├── Account balances
                ├── Cash/bank activity
                ├── Source-document status
                └── Audit events
```

Important locations:

| Path | Purpose |
| --- | --- |
| `app/Services/Accounting` | Posting, ledger, dashboard, cash, and report logic |
| `app/Services/DemoData` | JSON repositories and coordinated demo reset |
| `config/demo_permissions.php` | Central role-permission map |
| `resources/js/accounting-engine.js` | Shared browser-side calculations and posting previews |
| `resources/views` | Blade pages, shared layout, print views, and modals |
| `storage/demo-data` | Mutable fictional accounting records |
| `tests/Feature` | Laravel workflow, permission, export, and reporting coverage |
| `tests/js` | Accounting-engine and permission-helper tests |

Laravel remains the authority for every mutation. JavaScript hides forbidden controls and improves usability, but cannot bypass route middleware or server-side state validation.

## Workflow Principles

- Draft and For Review records never change accounting balances.
- Only valid posted or approved source documents create journal entries.
- Total journal debits must equal total journal credits.
- Posted transactions are immutable and use controlled reversals.
- Each source posting uses an idempotency key to prevent duplicate journals.
- Report, ledger, dashboard, AR, AP, and cash totals derive from posted data.
- Multi-file workflows use snapshot-and-rollback coordination on failure.

## Demo Roles

| Role | Intended access |
| --- | --- |
| Administrator | Full application, configuration, users, approvals, reversals, and reporting |
| Accountant | Accounting configuration, master data, approvals, posting, reversals, cash/bank, reports, and audit |
| Encoder / Staff | Create and maintain drafts, then submit transactions for review |
| Viewer / Auditor | Read-only posted records, reports, exports, print, and audit history |

## Verification

```powershell
php artisan test
npm run test:accounting
npm run test:roles
npm run build
vendor\bin\pint --test
```

## Demo Data and Reset

Application records are stored in `storage/demo-data/*.json`. Use fictional data only because changes persist in the working copy.

Administrators can run **Users & Settings → System → Reset Demo Data**. The reset requires confirmation and a five-second safety countdown. It clears transactional records, preserves users/settings/tax codes, preserves the Chart of Accounts with all balances reset to zero, and records one new reset event in the Audit Trail.

## Documentation

See [Accounting System Documentation](output/ACCOUNTING_SYSTEM_DOCUMENTATION.md) for module instructions, role rules, accounting entries, end-to-end test scenarios, troubleshooting, and operational guidance.

## Security Notice

Authentication, roles, JSON persistence, and user switching are demonstration implementations. Do not store real credentials, bank details, tax identifiers, customer data, or production transactions in this repository. A production deployment requires database-backed persistence, hardened authentication, authorization policies, secrets management, backups, locking/concurrency controls, and a formal accounting review.
