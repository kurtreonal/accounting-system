export const ACCOUNTING_ENGINE_VERSION = 'accounting-v1';

export const money = (value) => Math.round((Number(value) || 0) * 100) / 100;

export const documentTotals = (lines, discount = 0) => {
    const calculatedLines = lines.map((line) => {
        const quantity = Number(line.quantity) || 0;
        const unitPrice = Number(line.unit_price) || 0;
        const taxRate = Number(line.tax_rate) || 0;
        const subtotal = money(quantity * unitPrice);
        const tax = money(subtotal * taxRate / 100);

        return { ...line, quantity, unit_price: unitPrice, tax_rate: taxRate, subtotal, tax, total: money(subtotal + tax) };
    });
    const subtotal = money(calculatedLines.reduce((sum, line) => sum + line.subtotal, 0));
    const tax = money(calculatedLines.reduce((sum, line) => sum + line.tax, 0));
    const normalizedDiscount = money(discount);

    return { lines: calculatedLines, subtotal, tax, discount: normalizedDiscount, total: money(subtotal + tax - normalizedDiscount) };
};

export const journalTotals = (lines) => {
    const totalDebit = money(lines.reduce((sum, line) => sum + money(line.debit), 0));
    const totalCredit = money(lines.reduce((sum, line) => sum + money(line.credit), 0));
    const difference = money(Math.abs(totalDebit - totalCredit));
    const validLines = lines.length >= 2 && lines.every((line) => {
        const debit = money(line.debit);
        const credit = money(line.credit);
        return debit >= 0 && credit >= 0 && ((debit > 0) !== (credit > 0));
    });

    return { total_debit: totalDebit, total_credit: totalCredit, difference, balanced: validLines && totalDebit > 0 && difference <= 0.004 };
};

export const allocationTotal = (allocations) => money(allocations.reduce((sum, allocation) => sum + money(allocation.amount), 0));

export const validateAllocations = (allocations, documents, numberField) => {
    if (allocations.length === 0) throw new Error('Add at least one payment allocation.');
    const documentMap = new Map(documents.map((document) => [String(document[numberField]), document]));
    const seen = new Set();
    allocations.forEach((allocation) => {
        const number = String(allocation[numberField] || '');
        const amount = money(allocation.amount);
        const document = documentMap.get(number);
        if (!document) throw new Error('Select an open document for every allocation.');
        if (seen.has(number)) throw new Error('Allocate each document only once.');
        if (amount <= 0) throw new Error('Allocation amount must be greater than zero.');
        if (amount > money(document.remaining_balance) + 0.004) throw new Error('Allocation cannot exceed remaining open balance.');
        seen.add(number);
    });

    return allocationTotal(allocations);
};

const accountName = (account) => String(account?.name || '').toLowerCase();
const accountText = (account) => `${accountName(account)} ${String(account?.sub_type || '').toLowerCase()}`;
const findAccount = (accounts, predicate, label) => {
    const account = accounts.find(predicate);
    if (!account) throw new Error(`Missing active ${label} account mapping.`);
    return account;
};
const byCode = (accounts, code) => findAccount(accounts, (account) => String(account.code) === String(code), `account ${code}`);
const receivable = (accounts) => findAccount(accounts, (account) => account.type === 'Asset' && accountName(account).includes('receivable'), 'Accounts Receivable');
const payable = (accounts) => findAccount(accounts, (account) => account.type === 'Liability' && accountName(account).includes('payable') && !accountName(account).includes('tax'), 'Accounts Payable');
const revenue = (accounts) => findAccount(accounts, (account) => account.type === 'Revenue', 'Revenue');
const outputTax = (accounts) => findAccount(accounts, (account) => accountName(account).includes('output tax'), 'Output Tax');
const inputTax = (accounts) => findAccount(accounts, (account) => accountName(account).includes('input tax'), 'Input Tax');
const cash = (accounts, code) => {
    const account = byCode(accounts, code);
    if (account.type !== 'Asset' || (!accountText(account).includes('cash') && !accountText(account).includes('bank'))) {
        throw new Error('Selected account is not active Cash or Bank.');
    }
    return account;
};
const postingLine = (account, debit, credit) => ({ account_code: String(account.code), debit: money(debit), credit: money(credit) });
const finishPosting = (sourceKey, date, sourceType, lines) => {
    const totals = journalTotals(lines);
    if (!totals.balanced) throw new Error('Generated journal entry is not balanced.');

    return { engine: ACCOUNTING_ENGINE_VERSION, source_key: sourceKey, date, source_type: sourceType, lines };
};

export const createPosting = (event, source, accounts) => {
    if (!Array.isArray(accounts) || accounts.length === 0) throw new Error('No active account mappings available.');

    if (event === 'credit-sale') {
        const lines = [
            postingLine(receivable(accounts), source.total, 0),
            postingLine(revenue(accounts), 0, source.subtotal),
        ];
        if (money(source.tax) > 0) lines.push(postingLine(outputTax(accounts), 0, source.tax));
        return finishPosting(`invoice:${source.invoice_number}`, source.invoice_date, 'Invoice', lines);
    }

    if (event === 'customer-payment') {
        return finishPosting(`customer-payment:${source.request_token}`, source.payment_date, 'Payment', [
            postingLine(cash(accounts, source.cash_account_code), source.amount, 0),
            postingLine(receivable(accounts), 0, source.amount),
        ]);
    }

    if (event === 'vendor-bill') {
        const lines = source.lines.map((line) => postingLine(byCode(accounts, line.account_code), line.subtotal, 0));
        if (money(source.tax) > 0) lines.push(postingLine(inputTax(accounts), source.tax, 0));
        lines.push(postingLine(payable(accounts), 0, source.total));
        return finishPosting(`bill:${source.bill_number}`, source.bill_date, 'Bill', lines);
    }

    if (event === 'vendor-payment') {
        return finishPosting(`vendor-payment:${source.request_token}`, source.payment_date, 'Vendor Payment', [
            postingLine(payable(accounts), source.amount, 0),
            postingLine(cash(accounts, source.cash_account_code), 0, source.amount),
        ]);
    }

    throw new Error(`Unsupported accounting event: ${event}`);
};
