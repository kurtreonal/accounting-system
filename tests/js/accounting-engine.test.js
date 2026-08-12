import assert from 'node:assert/strict';
import test from 'node:test';
import {
    ACCOUNTING_ENGINE_VERSION,
    createPosting,
    documentTotals,
    journalTotals,
    validateAllocations,
} from '../../resources/js/accounting-engine.js';

const accounts = [
    { code: '1000', name: 'Cash on Hand', type: 'Asset', sub_type: 'Cash' },
    { code: '1100', name: 'Accounts Receivable', type: 'Asset', sub_type: 'Current Asset' },
    { code: '1200', name: 'Computer Equipment', type: 'Asset', sub_type: 'Fixed Asset' },
    { code: '1300', name: 'Input Tax Receivable', type: 'Asset', sub_type: 'Current Asset' },
    { code: '2000', name: 'Accounts Payable', type: 'Liability', sub_type: 'Current Liability' },
    { code: '2100', name: 'Output Tax Payable', type: 'Liability', sub_type: 'Current Liability' },
    { code: '4000', name: 'Sales Revenue', type: 'Revenue', sub_type: 'Operating Revenue' },
    { code: '5100', name: 'Office Supplies Expense', type: 'Expense', sub_type: 'Operating Expense' },
];

test('document totals round each line consistently', () => {
    const totals = documentTotals([
        { quantity: 3, unit_price: 10.005, tax_rate: 12 },
        { quantity: 1, unit_price: 100, tax_rate: 0 },
    ]);
    assert.deepEqual(
        { subtotal: totals.subtotal, tax: totals.tax, total: totals.total },
        { subtotal: 130.02, tax: 3.6, total: 133.62 },
    );
});

test('all source transactions produce balanced journals through one engine', () => {
    const postings = [
        createPosting('credit-sale', {
            invoice_number: 'INV-2026-0001', invoice_date: '2026-08-12', subtotal: 1000, tax: 120, total: 1120,
        }, accounts),
        createPosting('customer-payment', {
            request_token: 'customer-token', payment_date: '2026-08-12', cash_account_code: '1000', amount: 400,
        }, accounts),
        createPosting('vendor-bill', {
            bill_number: 'BILL-2026-0001', bill_date: '2026-08-12', tax: 120, total: 1120,
            lines: [{ account_code: '5100', subtotal: 1000 }],
        }, accounts),
        createPosting('vendor-payment', {
            request_token: 'vendor-token', payment_date: '2026-08-12', cash_account_code: '1000', amount: 500,
        }, accounts),
    ];

    postings.forEach((posting) => {
        assert.equal(posting.engine, ACCOUNTING_ENGINE_VERSION);
        assert.equal(journalTotals(posting.lines).balanced, true);
    });
    assert.equal(postings[0].source_key, 'invoice:INV-2026-0001');
    assert.equal(postings[2].source_key, 'bill:BILL-2026-0001');
});

test('allocation validation blocks duplicates and overpayment', () => {
    const invoices = [{ invoice_number: 'INV-1', remaining_balance: 500 }];
    assert.equal(validateAllocations([{ invoice_number: 'INV-1', amount: 200 }], invoices, 'invoice_number'), 200);
    assert.throws(
        () => validateAllocations([{ invoice_number: 'INV-1', amount: 501 }], invoices, 'invoice_number'),
        /cannot exceed remaining open balance/,
    );
    assert.throws(
        () => validateAllocations([
            { invoice_number: 'INV-1', amount: 100 },
            { invoice_number: 'INV-1', amount: 100 },
        ], invoices, 'invoice_number'),
        /only once/,
    );
});
