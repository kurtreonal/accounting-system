# Nexii-Tech Accounting System Documentation

**Organization:** Nexii-Tech Solutions Inc.  
**Application type:** Laravel and JavaScript static-data accounting demonstration  
**Persistence:** Local JSON files; no production database  
**Currency:** Philippine peso (PHP)  
**Default timezone:** Asia/Manila  
**Document updated:** August 14, 2026

---

## 1. Purpose and Scope

The Nexii-Tech Accounting System is a functional demonstration of a double-entry accounting workflow. It allows users to encode source documents, review them, post balanced journal entries, inspect account ledgers, settle receivables and payables, and generate financial summaries.

The application is designed for live demonstrations, internal evaluation, and OJT development. It uses fictional JSON data so that the complete accounting flow can be tested without MySQL or another production database.

### 1.1 Core guarantees

- Draft and For Review records do not affect financial balances.
- Accounting effects occur only after an authorized posting or approval.
- Every posted journal must have equal total debits and credits.
- Posted records cannot be silently edited.
- Reversals preserve the original transaction and create offsetting history.
- Duplicate source posting is blocked by stable source keys.
- Dashboard, ledger, AR, AP, cash, and reports use the same posted records.
- Mutation routes enforce permissions on the server even when UI controls are hidden.

### 1.2 Demonstration boundary

This project is not production accounting software. Authentication, user management, role switching, and persistence are demonstration implementations. Do not enter real customer, supplier, bank, employee, tax, or company-sensitive data.

---

## 2. System Architecture

### 2.1 Technology stack

| Layer | Technology |
| --- | --- |
| Application framework | Laravel 13 / PHP 8.3+ |
| Views | Blade templates |
| Browser behavior | JavaScript ES modules |
| Styling | Tailwind CSS 4 |
| Asset pipeline | Vite 8 |
| Persistence | JSON files under `storage/demo-data` |
| PDF output | FPDF services |
| PHP tests | Pest/PHPUnit through `php artisan test` |
| JavaScript tests | Node.js built-in test runner |

### 2.2 Request and posting flow

```text
User action
    │
    ▼
Blade form + JavaScript validation/preview
    │
    ▼
Laravel route permission middleware
    │
    ▼
Controller workflow and document-state validation
    │
    ▼
Accounting/data services
    ├── validate accounts and totals
    ├── write source status
    ├── create one balanced journal
    ├── update account balances
    ├── create cash activity when applicable
    └── record audit history
```

The browser-side accounting engine provides immediate calculations and previews. `AccountingPostingService` is the authoritative server-side posting gate.

### 2.3 Important directories

| Location | Responsibility |
| --- | --- |
| `app/Http/Controllers` | Page endpoints, request validation, and workflow actions |
| `app/Services/Accounting` | Posting, reports, ledger, dashboard, and cash activity |
| `app/Services/DemoData` | JSON repositories and coordinated reset behavior |
| `app/Services/Exports` | CSV/PDF-supporting export logic and PDF documents |
| `config/demo_permissions.php` | Shared static permission map |
| `resources/views` | Application, modal, export, and print interfaces |
| `resources/js` | Shared UI, permissions, calculations, and page workflows |
| `storage/demo-data` | Mutable fictional records |
| `tests/Feature` | Server workflow and access-control tests |
| `tests/js` | Shared accounting and permission tests |

---

## 3. Installation and Operation

### 3.1 Requirements

- PHP 8.3 or newer
- Composer
- Node.js and npm
- Required PHP extensions for Laravel

### 3.2 First-time setup

From the repository root, run:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

Open `http://localhost:8000`.

Alternatively, run the bundled Composer setup script:

```powershell
composer setup
```

### 3.3 Development mode

```powershell
composer dev
```

This starts the development processes configured by `scripts/dev.ps1`.

### 3.4 Serving on a local network

Build static frontend assets and expose Laravel on all interfaces:

```powershell
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Open `http://<server-ip>:8000` on another device. For example: `http://192.168.1.26:8000`.

If the remote page has no styling:

1. Stop the Vite development server.
2. Confirm `public/hot` does not exist.
3. Run `npm run build` again.
4. Restart Laravel with `--host=0.0.0.0`.
5. Hard-refresh the remote browser.

---

## 4. Authentication, Roles, and Permissions

Users sign in with an email and password. The server assigns the role stored in the selected user record; the login page does not allow a user to choose a role.

### 4.1 Role matrix

| Capability | Administrator | Accountant | Encoder / Staff | Viewer / Auditor |
| --- | :---: | :---: | :---: | :---: |
| View posted records | Yes | Yes | Yes | Yes |
| View reports | Yes | Yes | No | Yes |
| View audit trail | Yes | Yes | No | Yes |
| Create/edit drafts | Yes | Yes | Yes | No |
| Submit drafts for review | Yes | Yes | Yes | No |
| Approve/post transactions | Yes | Yes | No | No |
| Reverse transactions | Yes | Yes | No | No |
| Manage customers/vendors | Yes | Yes | No | No |
| Manage Chart of Accounts | Yes | Yes | No | No |
| Manage Cash & Bank | Yes | Yes | No | No |
| Manage Tax Settings | Yes | No | No | No |
| Manage users/settings | Yes | No | No | No |

### 4.2 Enforcement behavior

- Forbidden actions are hidden from the interface.
- Contextually unavailable actions may be disabled with an explanation.
- Mutation routes use `demo.permission` middleware.
- Controllers independently validate document state and accounting conditions.
- Viewer datasets exclude drafts and pending records.
- JavaScript permission checks improve the interface but are not the security authority.

---

## 5. Module Guide

### 5.1 Dashboard

The Dashboard summarizes posted accounting activity:

- Cash and bank position
- Accounts Receivable and overdue receivables
- Accounts Payable and overdue payables
- Revenue, expenses, and net income
- Aging summaries and recent activity

Draft and For Review documents are intentionally excluded.

### 5.2 Chart of Accounts

The Chart of Accounts stores account code, name, type, subtype, status, and balance. Administrator and Accountant roles can create, edit, activate, deactivate, and delete eligible accounts.

Important rules:

- Account codes must be unique.
- Inactive accounts cannot receive new postings.
- Referenced accounts cannot be deleted when doing so would break posted history.
- Account balances are updated only by posted journals or controlled reversals.
- CSV and PDF exports are available.

### 5.3 Journal Entries

Manual journals follow:

```text
Draft → For Review → Posted → Reversed
```

- Drafts can be edited or deleted by permitted roles.
- Encoders can submit drafts for review.
- Accountants and Administrators can return a record to Draft or post it.
- Posting is blocked until debits equal credits and all accounts are valid.
- Reversal creates an offsetting entry; it does not erase the original.
- Source-generated journals must be controlled through their source workflow.

Journal lists and individual entries support appropriate CSV, PDF, and print outputs.

### 5.4 General Ledger

The General Ledger is derived from Posted and Reversed journal lines. It provides account-level activity, debit/credit values, source references, filters, and running balances. It is not maintained as a separate editable dataset.

### 5.5 Sales and Revenue

This module manages customers and customer invoices.

When a credit invoice is posted:

| Entry | Debit | Credit |
| --- | ---: | ---: |
| Accounts Receivable | Gross invoice amount | — |
| Sales Revenue | — | Net revenue |
| Output Tax Payable, when configured | — | Tax amount |

Only Administrator and Accountant roles manage customer master data. Encoders may prepare transaction drafts but cannot post them.

### 5.6 Accounts Receivable

Accounts Receivable displays posted open invoices, paid balances, overdue amounts, and aging. Invoice codes open an in-page detail modal rather than redirecting to another module.

Customer payments follow:

```text
Draft → For Review → Posted
```

On posting:

| Entry | Debit | Credit |
| --- | ---: | ---: |
| Selected Cash/Bank | Payment amount | — |
| Accounts Receivable | — | Payment amount |

The server revalidates open balances and allocations immediately before posting. Draft payments do not change invoice balances, cash, reports, or aging.

### 5.7 Accounts Payable

Accounts Payable manages vendors, bills, outgoing vendor payments, balances, and aging. It also shows approved unpaid expenses as read-only **Expense Payables**.

When a bill is posted:

| Entry | Debit | Credit |
| --- | ---: | ---: |
| Expense or Asset | Net amount | — |
| Input Tax Receivable, when configured | Tax amount | — |
| Accounts Payable | — | Gross bill amount |

Vendor payments follow:

```text
Draft → For Review → Posted
```

On posting:

| Entry | Debit | Credit |
| --- | ---: | ---: |
| Accounts Payable | Payment amount | — |
| Selected Cash/Bank | — | Payment amount |

Vendor and bill codes open shared record-detail modals. CSV and PDF exports are available for the payable list.

### 5.8 Cash and Bank

This module provides cash/bank accounts, deposits, withdrawals, adjustments, source-generated activity, transaction reversal, and reconciliation.

Key safeguards:

- Only Administrator and Accountant roles can post cash/bank mutations.
- Source payments generate cash activity once.
- Reconciled or cleared activity cannot be reversed through an unsafe path.
- Cash and bank totals derive from posted accounting effects.

### 5.9 Expenses

Expenses follow:

```text
Draft → For Review → Approved → Reversed
```

Approved means that the source journal was posted successfully.

For a directly paid expense:

| Entry | Debit | Credit |
| --- | ---: | ---: |
| Expense | Net amount | — |
| Input Tax Receivable, when configured | Tax amount | — |
| Selected Cash/Bank | — | Gross amount |

For an unpaid expense:

| Entry | Debit | Credit |
| --- | ---: | ---: |
| Expense | Net amount | — |
| Input Tax Receivable, when configured | Tax amount | — |
| Accounts Payable | — | Gross amount |

Unpaid expenses require a due date and appear in AP totals and aging. Settlement is full-payment-only and remains managed inside Expenses.

Expense payments use `EPY-YYYY-####` and follow:

```text
Draft → For Review → Posted → Reversed
```

A Posted payment debits Accounts Payable and credits the selected Cash/Bank account. A payment must be reversed before reversing a previously settled unpaid expense.

### 5.10 Financial Reports

Financial reports derive from posted journal entries and include:

- Trial Balance
- Income Statement
- Balance Sheet
- Expense reporting

The Trial Balance must remain balanced. Draft records do not appear in statements. CSV export is available from the reporting module.

### 5.11 Tax Settings

Administrator-only tax configuration includes tax name/code, rate, type, status, default selection, summary, and CSV export. Invalid or inactive tax codes are rejected during posting.

### 5.12 Audit Trail

The Audit Trail records who performed an action, their role, the action, module, record identifier, timestamp, and practical Before/After status values.

**Before** shows the relevant state before the action. **After** shows the resulting state. For example, submitting an expense may show `Draft` before and `For Review` after.

Features include:

- Automatic search and select-filter updates
- Table and timeline views
- CSV export and print
- Record-detail modal for linked identifiers
- Theme-consistent light and dark interfaces

KPI cards are excluded from print output.

### 5.13 Users and Settings

Administrator-only features include:

- Create and edit demo users
- Assign roles and account status
- Reset demo passwords
- Update company information
- Update fiscal year, currency, date, numbering, and timezone preferences
- Export settings
- Reset Demo Data

Login-presence tracking is intentionally outside the current demo scope.

---

## 6. Transaction Status Reference

| Status | Accounting effect | Editable? |
| --- | --- | --- |
| Draft | None | Yes, with draft permission |
| For Review | None | No normal editing; authorized return to Draft |
| Posted / Approved | Journal and balances updated | No |
| Paid | Related posted settlement completed | No |
| Reversed | Original retained and offsetting journal posted | No |

Document status and payment status are separate where necessary. For example, an Approved unpaid expense has a posted source journal but remains Unpaid until its expense payment posts.

---

## 7. JSON Data Storage

| File | Stored records |
| --- | --- |
| `accounts.json` | Chart of Accounts and current balances |
| `journal_entries.json` | Manual and source-generated journals |
| `customers.json` | Customer master data |
| `invoices.json` | Customer invoices |
| `customer_payments.json` | AR payment drafts and postings |
| `vendors.json` | Vendor master data |
| `bills.json` | Vendor bills |
| `vendor_payments.json` | AP payment drafts and postings |
| `expenses.json` | Expense documents |
| `expense_payments.json` | Unpaid-expense settlements |
| `bank_transactions.json` | Cash/bank activity |
| `bank_reconciliations.json` | Reconciliation records |
| `tax_codes.json` | Demo tax setup |
| `users.json` | Demo accounts, password hashes, and roles |
| `settings.json` | Company and system preferences |
| `audit_logs.json` | User and system event history |

JSON writes are local and persistent. Avoid manually editing related files independently because a transaction may span source data, journals, balances, cash activity, and audit records.

---

## 8. Reset Demo Data

Open **Users & Settings → System → Reset Demo Data** as an Administrator.

The interface requires validation and a five-second countdown before confirmation. A successful reset:

- Clears customers, vendors, invoices, bills, payments, expenses, journals, bank transactions, and reconciliations
- Preserves the Chart of Accounts but changes every balance to zero
- Preserves demo users
- Preserves company/system settings
- Preserves tax codes
- Clears old audit events and writes one new Reset Demo Data event

The reset service snapshots managed files before mutation and restores them if a coordinated write fails.

---

## 9. End-to-End Sample Data Flow

The following fictional scenario demonstrates how values move across modules. Use active accounts and the configured demo VAT rate.

### 9.1 Post a customer invoice

Create a customer, then prepare an invoice:

| Field | Sample value |
| --- | --- |
| Customer | Blue Harbor Trading |
| Invoice date | 2026-08-14 |
| Due date | 2026-09-13 |
| Net services | PHP 10,000.00 |
| VAT at 12% | PHP 1,200.00 |
| Gross invoice | PHP 11,200.00 |

Post the invoice as an Accountant or Administrator.

Expected results:

- Invoice becomes Posted/Unpaid.
- AR increases by PHP 11,200.00.
- Revenue increases by PHP 10,000.00.
- Output Tax Payable increases by PHP 1,200.00.
- One balanced journal is linked to the invoice.
- AR aging and Dashboard update.
- Audit Trail records the posting.

### 9.2 Collect the invoice

Create a PHP 11,200.00 customer payment, submit it for review, then post it.

Expected results:

- Cash/Bank increases by PHP 11,200.00.
- AR decreases by PHP 11,200.00.
- Invoice balance becomes zero and status becomes Paid.
- The invoice leaves open AR aging.
- One payment journal and one cash movement are created.

### 9.3 Post a vendor bill

Create a vendor bill:

| Field | Sample value |
| --- | --- |
| Vendor | Northstar Office Supply |
| Expense account | Office Supplies Expense |
| Net purchase | PHP 5,000.00 |
| Input VAT at 12% | PHP 600.00 |
| Gross payable | PHP 5,600.00 |

Expected posting:

- Debit Office Supplies Expense: PHP 5,000.00
- Debit Input Tax Receivable: PHP 600.00
- Credit Accounts Payable: PHP 5,600.00
- AP totals and aging increase by PHP 5,600.00.

### 9.4 Pay the vendor

Create, submit, and post a PHP 5,600.00 vendor payment.

Expected results:

- Accounts Payable decreases by PHP 5,600.00.
- Cash/Bank decreases by PHP 5,600.00.
- Bill becomes Paid and leaves open AP aging.
- Cash/Bank and Audit Trail show the linked payment.

### 9.5 Approve and settle an unpaid expense

Create a PHP 2,240.00 unpaid utility expense, including PHP 240.00 input VAT, and provide a due date.

After approval:

- Debit Utilities Expense: PHP 2,000.00
- Debit Input Tax Receivable: PHP 240.00
- Credit Accounts Payable: PHP 2,240.00
- Expense appears as an Expense Payable in AP.
- No cash movement exists yet.

Create an expense payment, submit it, and post it:

- Debit Accounts Payable: PHP 2,240.00
- Credit Cash/Bank: PHP 2,240.00
- Expense becomes Paid and leaves AP aging.

### 9.6 Verify reports

After the scenarios:

1. Confirm every generated journal balances.
2. Open the General Ledger and inspect AR, AP, Cash/Bank, Revenue, Expenses, and Tax accounts.
3. Confirm the Trial Balance debit and credit totals match.
4. Confirm the Income Statement contains posted revenue and expenses only.
5. Confirm the Balance Sheet reflects cash, AR, AP, and tax balances.
6. Open record codes from Audit Trail and confirm the detail modal shows the linked document.

---

## 10. Validation and Reliability Rules

- Required document fields must be complete.
- Amounts must be numeric and greater than zero where applicable.
- Account and tax mappings must exist and be active.
- Journal debits must equal credits before posting.
- Payment allocations cannot exceed current open balances.
- A second payment against an already paid document is blocked.
- A stale or duplicate submission is revalidated on the server.
- Posted records are immutable.
- Reconciled cash activity blocks unsafe reversal.
- Source and payment reversals use offset journals and retain history.
- Coordinated workflows restore affected JSON files after a failed intermediate write.

---

## 11. Exports and Printing

Available output varies by module:

| Module | CSV | PDF | Print |
| --- | :---: | :---: | :---: |
| Chart of Accounts | Yes | Yes | Browser print where applicable |
| Journal Entries | Yes | Yes | Yes |
| General Ledger | Yes | Yes | Browser print where applicable |
| Accounts Receivable | Yes | — | Browser print |
| Accounts Payable | Yes | Yes | Browser print |
| Cash & Bank | Yes | — | Browser print |
| Expenses | Yes | — | Browser print |
| Financial Reports | Yes | — | Browser print |
| Tax Settings | Yes | — | Browser print |
| Audit Trail | Yes | — | Yes |
| Users & Settings | Settings export | — | — |

Exports are read-only operations. Viewer/Auditor access remains limited to permitted posted/report datasets.

---

## 12. Verification Commands

Run the complete checks from the repository root:

```powershell
php artisan test
npm run test:accounting
npm run test:roles
npm run build
vendor\bin\pint --test
```

Use `php artisan route:list` to inspect registered endpoints.

---

## 13. Troubleshooting

### Page loads without styling

- Run `npm run build`.
- Ensure `public/build/manifest.json` exists.
- Remove a stale `public/hot` only after stopping Vite.
- Hard-refresh the browser.

### Remote device cannot connect

- Serve with `--host=0.0.0.0`.
- Use the server computer's LAN IP, not `localhost`.
- Allow the selected port through the local firewall.
- Confirm both devices are on the same network.

### Record does not affect balances

Check its workflow status. Draft and For Review records correctly have no accounting effect.

### Payment cannot post

Confirm the source balance is still open, allocations equal the payment, the cash/bank account is active, and the record is For Review.

### Reversal is blocked

Check for a later payment, reconciled cash activity, or an attempt to reverse a source-generated journal directly. Reverse dependent records through their source workflow first.

### Reset event detail shows an error

Use the current `settings/all-demo-data` record-detail endpoint through Audit Trail. A successful reset should show preserved configuration, zeroed account balances, and cleared transactional datasets.

---

## 14. Production Migration Considerations

Before production use, replace the static demonstration layer with:

- Database-backed repositories and migrations
- Transactional database writes and row locking
- Production authentication, password policy, MFA, and session controls
- Laravel policies/gates and organization-level authorization
- Immutable audit storage and retention rules
- Encrypted secrets and sensitive fields
- Document attachment storage and malware scanning
- Backup, restore, disaster recovery, and monitoring
- Period locking and formal close procedures
- Sequential numbering controls
- Tax and financial-statement review by qualified professionals
- Concurrency, performance, and security testing

The current service boundaries are intended to make this migration possible without redesigning every page.

---

## 15. Document Maintenance

Update this guide whenever any of the following changes:

- Role permissions in `config/demo_permissions.php`
- Transaction statuses or posting entries
- JSON file structures
- Export availability
- Reset behavior
- Setup/build commands
- Module routes or user-visible workflows

Keep documentation claims tied to working behavior. Visible actions must function or clearly explain why they are unavailable.
