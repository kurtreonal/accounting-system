# APM Customs Accounting System

Laravel, Blade, and JavaScript accounting demonstration using JSON files instead of a database. Built from [`instructions.md`](instructions.md) for OJT demonstrations and internal testing.

## Requirements

- PHP 8.3+
- Composer
- Node.js and npm

## Setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

Open `http://localhost:8000`. Demo users and password hashes are stored in `storage/demo-data/users.json`. Obtain plaintext demo credentials from the project supervisor; do not commit them.

For local development on the configured Windows/Herd environment:

```powershell
composer dev
```

## Accounting architecture

- `resources/js/accounting-engine.js` is the shared browser calculation and posting-preview engine.
- `app/Services/Accounting/AccountingPostingService.php` is the single server-side posting gate. It validates client previews, account mappings, balance equality, unique source keys, journal transitions, account updates, reversals, and audit metadata.
- `storage/demo-data/*.json` contains shared demo records. No migrations, Eloquent persistence, or production database are used.
- Posted source records link to one journal entry. Duplicate source posting and duplicate payment tokens are blocked.
- Source-generated journals cannot be reversed directly until matching invoice, payment, and bill void workflows exist. Manual journals support offsetting reversal.

Supported posting events:

- Credit invoice: debit Accounts Receivable; credit Revenue and optional Output Tax.
- Customer payment: debit Cash/Bank; credit Accounts Receivable.
- Vendor bill: debit Expense/Asset and optional Input Tax; credit Accounts Payable.
- Vendor payment: debit Accounts Payable; credit Cash/Bank.
- Manual journal: user-selected balanced debit and credit lines.

## Verification

```powershell
npm run test:accounting
npm run build
php artisan test
vendor\bin\pint --test
```

## Demo-data warning

Use fictional records only. JSON mutations persist in the local working copy. A safe Reset Demo Data workflow is not implemented yet; restore an approved seed snapshot manually when preparing a fresh demonstration.

## Current limitations

Cash/Bank management, Expenses, Trial Balance, financial statements, Tax Settings, administration pages, source-document voiding, and multi-file rollback remain pending. See `Accounting_System_TODO.md` in project handoff files for current status.
