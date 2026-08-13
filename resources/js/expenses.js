import { can } from './demo-access';

const setupExpenses = () => {
    const page = document.querySelector('#expenses-page');
    if (!page) return;

    const data = JSON.parse(document.querySelector('#expense-data').textContent);
    const records = data.expenses || [];
    const payments = data.payments || [];
    const canDraft = can('drafts.manage');
    const canApprove = can('transactions.approve');
    const canReverse = can('transactions.reverse');
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const expenseForm = document.querySelector('#expense-form');
    const paymentForm = document.querySelector('#expense-payment-form');
    const pageSize = 8;
    let currentPage = 1;
    let sort = { field: 'date', direction: 'desc' };
    let receipt = null;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
    const token = () => globalThis.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const number = Math.random() * 16 | 0;
        return (character === 'x' ? number : (number & 3 | 8)).toString(16);
    });
    const expenseUrl = (number, action = '') => page.dataset.storeUrl + '/' + encodeURIComponent(number) + (action ? '/' + action : '');
    const paymentUrl = (number = '', action = '') => page.dataset.paymentUrl + (number ? '/' + encodeURIComponent(number) : '') + (action ? '/' + action : '');
    const setModal = (name, open) => {
        const modal = document.querySelector('#expense-' + name + '-modal');
        modal.classList.toggle('hidden', !open);
        modal.classList.toggle('flex', open);
        modal.setAttribute('aria-hidden', String(!open));
    };
    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...(options.headers || {}),
            },
        });
        let result;
        try { result = await response.json(); } catch (_) { result = { message: 'Server returned an invalid response.' }; }
        return { response, result };
    };
    const statusClass = (status) => ({
        Draft: 'bg-slate-100 text-slate-600',
        'For Review': 'bg-amber-100 text-amber-700',
        Approved: 'bg-blue-100 text-blue-700',
        Posted: 'bg-emerald-100 text-emerald-700',
        Reversed: 'bg-rose-100 text-rose-700',
    })[status] || 'bg-slate-100 text-slate-600';
    const journalLink = (number) => number
        ? '<a href="/journal-entries?entry=' + encodeURIComponent(number) + '" class="font-mono text-blue-600 hover:underline">' + escapeHtml(number) + '</a>'
        : '<span class="text-slate-400">Not posted</span>';

    const filtered = () => {
        const search = document.querySelector('#expense-search').value.trim().toLowerCase();
        const category = document.querySelector('#expense-category-filter').value;
        const status = document.querySelector('#expense-status-filter').value;
        const payment = document.querySelector('#expense-payment-filter').value;
        const from = document.querySelector('#expense-date-from').value;
        const to = document.querySelector('#expense-date-to').value;
        const min = Number(document.querySelector('#expense-min').value || 0);
        const maxValue = document.querySelector('#expense-max').value;
        const max = maxValue === '' ? Number.POSITIVE_INFINITY : Number(maxValue);
        return records.filter((row) => (!search || (row.expense_number + ' ' + row.payee + ' ' + row.memo + ' ' + row.category_name).toLowerCase().includes(search))
            && (!category || String(row.category_account_code) === category)
            && (!status || row.status === status)
            && (!payment || row.payment_status === payment)
            && (!from || row.date >= from)
            && (!to || row.date <= to)
            && Number(row.total) >= min
            && Number(row.total) <= max)
            .sort((left, right) => {
                const a = sort.field === 'total' ? Number(left[sort.field]) : String(left[sort.field]);
                const b = sort.field === 'total' ? Number(right[sort.field]) : String(right[sort.field]);
                return (a > b ? 1 : a < b ? -1 : 0) * (sort.direction === 'asc' ? 1 : -1);
            });
    };

    const expenseActions = (row) => {
        const actions = ['<button type="button" data-expense-view="' + escapeHtml(row.expense_number) + '" class="apm-icon-button" title="View expense"><i class="fa-solid fa-eye"></i></button>'];
        const activePayment = payments.find((payment) => payment.expense_number === row.expense_number && payment.status !== 'Reversed');
        if (canDraft && row.status === 'Draft') {
            actions.push('<button type="button" data-expense-edit="' + escapeHtml(row.expense_number) + '" class="apm-icon-button" title="Edit draft"><i class="fa-solid fa-pen"></i></button>');
            actions.push('<button type="button" data-expense-submit="' + escapeHtml(row.expense_number) + '" class="apm-icon-button text-amber-600" title="Submit for review"><i class="fa-solid fa-paper-plane"></i></button>');
            actions.push('<button type="button" data-expense-delete="' + escapeHtml(row.expense_number) + '" class="apm-icon-button text-rose-600" title="Delete draft"><i class="fa-solid fa-trash"></i></button>');
        }
        if (canApprove && row.status === 'For Review') {
            actions.push('<button type="button" data-expense-return="' + escapeHtml(row.expense_number) + '" class="apm-outline-button">Return</button>');
            actions.push('<button type="button" data-expense-approve="' + escapeHtml(row.expense_number) + '" class="apm-primary-button">Approve</button>');
        }
        if (canDraft && row.status === 'Approved' && row.payment_status === 'Unpaid' && !activePayment) {
            actions.push('<button type="button" data-expense-pay="' + escapeHtml(row.expense_number) + '" class="apm-primary-button">Pay</button>');
        }
        if (canReverse && row.status === 'Approved' && !row.payment_journal_entry_id) {
            actions.push('<button type="button" data-expense-reverse="' + escapeHtml(row.expense_number) + '" class="apm-outline-button text-rose-600" title="Reverse approved expense">Reverse</button>');
        }
        return actions.join('');
    };

    const renderExpenses = () => {
        const rows = filtered();
        const pages = Math.max(1, Math.ceil(rows.length / pageSize));
        currentPage = Math.min(currentPage, pages);
        const start = (currentPage - 1) * pageSize;
        const visible = rows.slice(start, start + pageSize);
        document.querySelector('#expense-rows').innerHTML = visible.length ? visible.map((row) =>
            '<tr class="apm-table-row">' +
            '<td><button type="button" data-record-detail data-record-resource="expense" data-record-id="' + escapeHtml(row.expense_number) + '" class="font-mono text-[11px] font-semibold text-blue-600 hover:underline">' + escapeHtml(row.expense_number) + '</button></td>' +
            '<td class="font-mono text-[11px]">' + escapeHtml(row.date) + '</td>' +
            '<td><p class="font-medium text-slate-800">' + escapeHtml(row.payee) + '</p><p class="mt-0.5 max-w-52 truncate text-[10px] text-slate-400" title="' + escapeHtml(row.memo) + '">' + escapeHtml(row.memo) + '</p></td>' +
            '<td>' + escapeHtml(row.category_name) + '</td>' +
            '<td><p>' + escapeHtml(row.payment_method || 'Pay later') + '</p><span class="text-[10px] ' + (row.payment_status === 'Paid' ? 'text-emerald-600' : 'text-amber-600') + '">' + escapeHtml(row.payment_status) + '</span>' + (row.due_date ? '<p class="text-[9px] text-slate-400">Due ' + escapeHtml(row.due_date) + '</p>' : '') + '</td>' +
            '<td class="apm-money text-right">' + money.format(Number(row.total)) + '<p class="text-[9px] text-slate-400">Tax ' + money.format(Number(row.tax)) + '</p></td>' +
            '<td class="text-center">' + (row.receipt?.name ? '<span title="' + escapeHtml(row.receipt.name) + '" class="text-emerald-600"><i class="fa-solid fa-paperclip"></i></span>' : '<span class="text-slate-300">—</span>') + '</td>' +
            '<td>' + escapeHtml(row.created_by?.name || 'Demo User') + '</td>' +
            '<td class="text-center"><span class="inline-flex rounded-full px-2 py-1 text-[10px] font-semibold ' + statusClass(row.status) + '">' + escapeHtml(row.status) + '</span></td>' +
            '<td class="print:hidden"><div class="flex items-center justify-end gap-1">' + expenseActions(row) + '</div></td></tr>'
        ).join('') : '<tr><td colspan="10" class="px-4 py-14 text-center text-slate-500">No expenses found.</td></tr>';
        document.querySelector('#expense-result-count').textContent = rows.length + ' matching record' + (rows.length === 1 ? '' : 's');
        document.querySelector('#expense-page-summary').textContent = rows.length ? 'Showing ' + (start + 1) + '–' + Math.min(start + pageSize, rows.length) + ' of ' + rows.length : 'Showing 0 records';
        document.querySelector('#expense-page-number').textContent = currentPage;
        document.querySelector('#expense-prev').disabled = currentPage === 1;
        document.querySelector('#expense-next').disabled = currentPage === pages;
    };

    const paymentActions = (payment) => {
        const actions = [];
        if (canDraft && payment.status === 'Draft') {
            actions.push('<button type="button" data-payment-edit="' + escapeHtml(payment.payment_number) + '" class="apm-icon-button" title="Edit payment"><i class="fa-solid fa-pen"></i></button>');
            actions.push('<button type="button" data-payment-submit="' + escapeHtml(payment.payment_number) + '" class="apm-icon-button text-amber-600" title="Submit payment"><i class="fa-solid fa-paper-plane"></i></button>');
            actions.push('<button type="button" data-payment-delete="' + escapeHtml(payment.payment_number) + '" class="apm-icon-button text-rose-600" title="Delete payment"><i class="fa-solid fa-trash"></i></button>');
        }
        if (canApprove && payment.status === 'For Review') {
            actions.push('<button type="button" data-payment-return="' + escapeHtml(payment.payment_number) + '" class="apm-outline-button">Return</button>');
            actions.push('<button type="button" data-payment-post="' + escapeHtml(payment.payment_number) + '" class="apm-primary-button">Post</button>');
        }
        if (canReverse && payment.status === 'Posted') {
            actions.push('<button type="button" data-payment-reverse="' + escapeHtml(payment.payment_number) + '" class="apm-outline-button text-rose-600">Reverse</button>');
        }
        return actions.join('') || '<span class="text-slate-400">Read only</span>';
    };

    const renderPayments = () => {
        const target = document.querySelector('#expense-payment-rows');
        target.innerHTML = payments.length ? payments.map((payment) =>
            '<tr class="apm-table-row">' +
            '<td><button type="button" data-record-detail data-record-resource="expense_payment" data-record-id="' + escapeHtml(payment.payment_number) + '" class="font-mono text-blue-600 hover:underline">' + escapeHtml(payment.payment_number) + '</button></td>' +
            '<td><button type="button" data-record-detail data-record-resource="expense" data-record-id="' + escapeHtml(payment.expense_number) + '" class="font-mono text-blue-600 hover:underline">' + escapeHtml(payment.expense_number) + '</button></td>' +
            '<td class="font-mono">' + escapeHtml(payment.payment_date) + '</td>' +
            '<td><p>' + escapeHtml(payment.payee) + '</p><p class="text-[10px] text-slate-400">' + escapeHtml(payment.payment_method) + '</p></td>' +
            '<td class="apm-money text-right">' + money.format(Number(payment.amount)) + '</td>' +
            '<td>' + journalLink(payment.journal_entry_id) + '</td>' +
            '<td><span class="inline-flex rounded-full px-2 py-1 text-[10px] font-semibold ' + statusClass(payment.status) + '">' + escapeHtml(payment.status) + '</span></td>' +
            '<td class="print:hidden"><div class="flex items-center justify-end gap-1">' + paymentActions(payment) + '</div></td></tr>'
        ).join('') : '<tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No expense payments yet.</td></tr>';
    };

    const updateTotals = () => {
        const subtotal = Number(expenseForm.elements.amount.value || 0);
        const tax = subtotal * Number(expenseForm.elements.tax_rate.value || 0) / 100;
        document.querySelector('#expense-tax-preview').textContent = money.format(tax);
        document.querySelector('#expense-total-preview').textContent = money.format(subtotal + tax);
    };
    const updatePaymentFields = () => {
        const paid = expenseForm.elements.payment_status.value === 'Paid';
        document.querySelector('[data-cash-account]').classList.toggle('hidden', !paid);
        document.querySelector('[data-payment-method]').classList.toggle('hidden', !paid);
        document.querySelector('[data-expense-due-date]').classList.toggle('hidden', paid);
        expenseForm.elements.cash_account_code.required = paid;
        expenseForm.elements.payment_method.required = paid;
        expenseForm.elements.due_date.required = !paid;
        if (paid) expenseForm.elements.due_date.value = '';
        else {
            expenseForm.elements.cash_account_code.value = '';
            expenseForm.elements.payment_method.value = '';
        }
    };
    const showReceipt = (metadata, dataUrl = '') => {
        const target = document.querySelector('#expense-receipt-preview');
        if (!metadata?.name) { target.classList.add('hidden'); target.innerHTML = ''; return; }
        target.classList.remove('hidden');
        target.innerHTML = (dataUrl && metadata.type.startsWith('image/') ? '<img src="' + dataUrl + '" alt="Receipt preview" class="mb-2 max-h-36 rounded-lg object-contain">' : '<i class="fa-solid fa-file-lines mr-2 text-blue-600"></i>') +
            '<strong>' + escapeHtml(metadata.name) + '</strong><span class="ml-2 text-slate-400">' + (Number(metadata.size) / 1024).toFixed(1) + ' KB</span>';
    };
    const clearExpenseErrors = () => expenseForm.querySelectorAll('[data-error]').forEach((element) => { element.textContent = ''; });
    const showExpenseErrors = (errors = {}) => Object.entries(errors).forEach(([field, messages]) => {
        const target = expenseForm.querySelector('[data-error="' + field.split('.')[0] + '"]');
        if (target) target.textContent = messages[0];
    });
    const showExpenseMessage = (message, success = false) => {
        const target = document.querySelector('[data-expense-message]');
        target.textContent = message;
        target.className = 'mt-3 rounded-lg p-3 text-xs ' + (success ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700');
    };

    const openExpenseForm = (row = null) => {
        expenseForm.reset();
        clearExpenseErrors();
        receipt = row?.receipt || null;
        expenseForm.elements.expense_number.value = row?.expense_number || '';
        expenseForm.elements.request_token.value = row?.request_token || token();
        expenseForm.elements.date.value = row?.date || new Date().toISOString().slice(0, 10);
        ['payee', 'category_account_code', 'tax_rate', 'payment_status', 'payment_method', 'due_date', 'cash_account_code', 'memo'].forEach((field) => {
            if (row) expenseForm.elements[field].value = row[field] ?? '';
        });
        expenseForm.elements.amount.value = row?.subtotal ?? '';
        document.querySelector('#expense-form-title').textContent = row ? 'Edit ' + row.expense_number : 'New Expense';
        document.querySelector('[data-expense-message]').classList.add('hidden');
        showReceipt(receipt);
        updateTotals();
        updatePaymentFields();
        setModal('form', true);
    };

    const openView = (row) => {
        document.querySelector('#expense-view-title').textContent = row.expense_number;
        document.querySelector('#expense-view-content').innerHTML =
            '<div class="flex items-start justify-between"><div><p class="text-sm font-semibold text-slate-900">' + escapeHtml(row.payee) + '</p><p class="mt-1 text-xs text-slate-500">' + escapeHtml(row.date) + ' · ' + escapeHtml(row.category_name) + '</p></div><span class="rounded-full px-2 py-1 text-[10px] font-semibold ' + statusClass(row.status) + '">' + escapeHtml(row.status) + '</span></div>' +
            '<dl class="mt-4 grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-4 text-xs"><div><dt class="text-slate-400">Subtotal</dt><dd class="mt-1 font-mono font-semibold">' + money.format(Number(row.subtotal)) + '</dd></div><div><dt class="text-slate-400">Tax</dt><dd class="mt-1 font-mono font-semibold">' + money.format(Number(row.tax)) + '</dd></div><div><dt class="text-slate-400">Total</dt><dd class="mt-1 font-mono font-bold">' + money.format(Number(row.total)) + '</dd></div><div><dt class="text-slate-400">Payment</dt><dd class="mt-1 font-semibold">' + escapeHtml(row.payment_status) + (row.due_date ? ' · due ' + escapeHtml(row.due_date) : '') + '</dd></div></dl>' +
            '<div class="mt-4 text-xs"><p class="text-[10px] uppercase text-slate-400">Memo</p><p class="mt-1 text-slate-700">' + escapeHtml(row.memo) + '</p></div>' +
            '<div class="mt-4 border-t border-slate-100 pt-3 text-xs"><p><span class="text-slate-400">Source journal:</span> ' + journalLink(row.journal_entry_id) + '</p><p class="mt-2"><span class="text-slate-400">Payment journal:</span> ' + journalLink(row.payment_journal_entry_id) + '</p><p class="mt-2"><span class="text-slate-400">Reversal journal:</span> ' + journalLink(row.reversal_journal_entry_id) + '</p></div>';
        setModal('view', true);
    };

    const openPaymentForm = (expense, payment = null) => {
        paymentForm.reset();
        paymentForm.elements.request_token.value = payment?.request_token || token();
        paymentForm.elements.payment_number.value = payment?.payment_number || '';
        paymentForm.elements.expense_number.value = expense.expense_number;
        paymentForm.elements.expense_display.value = expense.expense_number + ' · ' + expense.payee;
        paymentForm.elements.amount_display.value = money.format(Number(expense.total));
        paymentForm.elements.payment_date.value = payment?.payment_date || new Date().toISOString().slice(0, 10);
        ['cash_account_code', 'payment_method', 'reference', 'memo'].forEach((field) => {
            if (payment) paymentForm.elements[field].value = payment[field] ?? '';
        });
        document.querySelector('#expense-payment-title').textContent = payment ? 'Edit ' + payment.payment_number : 'Pay ' + expense.expense_number;
        document.querySelector('[data-expense-payment-message]').classList.add('hidden');
        paymentForm.querySelectorAll('[data-payment-error]').forEach((element) => { element.textContent = ''; });
        setModal('payment', true);
    };

    const runAction = async (url, method, confirmation, button) => {
        if (!confirm(confirmation)) return;
        if (button) button.disabled = true;
        try {
            const { response, result } = await fetchJson(url, { method });
            if (!response.ok) {
                alert(result.message || 'Unable to complete action.');
                if (button) button.disabled = false;
                return;
            }
            location.reload();
        } catch (_) {
            alert('Network error. No changes made.');
            if (button) button.disabled = false;
        }
    };

    document.querySelector('#expense-rows').addEventListener('click', (event) => {
        const target = event.target.closest('[data-expense-view], [data-expense-edit], [data-expense-submit], [data-expense-return], [data-expense-approve], [data-expense-delete], [data-expense-pay], [data-expense-reverse]');
        if (!target) return;
        const attribute = [...target.attributes].find((item) => item.name.startsWith('data-expense-'));
        const action = attribute.name.replace('data-expense-', '');
        const row = records.find((item) => item.expense_number === attribute.value);
        if (!row) return;
        if (action === 'view') openView(row);
        if (action === 'edit') openExpenseForm(row);
        if (action === 'pay') openPaymentForm(row);
        if (action === 'submit') runAction(expenseUrl(row.expense_number, 'submit-review'), 'POST', 'Submit ' + row.expense_number + ' for review?', target);
        if (action === 'return') runAction(expenseUrl(row.expense_number, 'return-draft'), 'POST', 'Return ' + row.expense_number + ' to Draft?', target);
        if (action === 'approve') runAction(expenseUrl(row.expense_number, 'approve'), 'POST', 'Approve and post ' + row.expense_number + '?', target);
        if (action === 'delete') runAction(expenseUrl(row.expense_number), 'DELETE', 'Delete draft ' + row.expense_number + '?', target);
        if (action === 'reverse') runAction(expenseUrl(row.expense_number, 'reverse'), 'POST', 'Reverse ' + row.expense_number + '? This creates an offset journal.', target);
    });

    document.querySelector('#expense-payment-rows').addEventListener('click', (event) => {
        const target = event.target.closest('[data-payment-edit], [data-payment-submit], [data-payment-delete], [data-payment-return], [data-payment-post], [data-payment-reverse]');
        if (!target) return;
        const attribute = [...target.attributes].find((item) => item.name.startsWith('data-payment-'));
        const action = attribute.name.replace('data-payment-', '');
        const payment = payments.find((item) => item.payment_number === attribute.value);
        const expense = payment && records.find((item) => item.expense_number === payment.expense_number);
        if (!payment || !expense) return;
        if (action === 'edit') openPaymentForm(expense, payment);
        if (action === 'submit') runAction(paymentUrl(payment.payment_number, 'submit-review'), 'POST', 'Submit ' + payment.payment_number + ' for review?', target);
        if (action === 'delete') runAction(paymentUrl(payment.payment_number), 'DELETE', 'Delete draft ' + payment.payment_number + '?', target);
        if (action === 'return') runAction(paymentUrl(payment.payment_number, 'return-draft'), 'POST', 'Return ' + payment.payment_number + ' to Draft?', target);
        if (action === 'post') runAction(paymentUrl(payment.payment_number, 'post'), 'POST', 'Post ' + payment.payment_number + '? This updates AP, cash, and ledger.', target);
        if (action === 'reverse') runAction(paymentUrl(payment.payment_number, 'reverse'), 'POST', 'Reverse ' + payment.payment_number + '?', target);
    });

    if (document.querySelector('#expense-new')) document.querySelector('#expense-new').addEventListener('click', () => openExpenseForm());
    document.querySelectorAll('[data-expense-close]').forEach((button) => button.addEventListener('click', () => setModal(button.dataset.expenseClose, false)));
    expenseForm.elements.amount.addEventListener('input', updateTotals);
    expenseForm.elements.tax_rate.addEventListener('change', updateTotals);
    expenseForm.elements.payment_status.addEventListener('change', updatePaymentFields);
    document.querySelector('#expense-receipt').addEventListener('change', (event) => {
        const file = event.target.files[0];
        const error = expenseForm.querySelector('[data-error="receipt"]');
        error.textContent = '';
        if (!file) { receipt = null; showReceipt(null); return; }
        if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type) || file.size > 5242880) {
            event.target.value = '';
            receipt = null;
            showReceipt(null);
            error.textContent = 'Choose JPG, PNG, or PDF no larger than 5 MB.';
            return;
        }
        receipt = { name: file.name, type: file.type, size: file.size };
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.addEventListener('load', () => showReceipt(receipt, reader.result));
            reader.readAsDataURL(file);
        } else showReceipt(receipt);
    });

    expenseForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearExpenseErrors();
        const buttons = expenseForm.querySelectorAll('[type="submit"]');
        buttons.forEach((button) => { button.disabled = true; });
        const values = Object.fromEntries(new FormData(expenseForm));
        const payload = { ...values, action: event.submitter?.value || 'draft', amount: Number(values.amount), tax_rate: Number(values.tax_rate), receipt };
        const editing = Boolean(values.expense_number);
        try {
            const { response, result } = await fetchJson(editing ? expenseUrl(values.expense_number) : page.dataset.storeUrl, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(payload) });
            if (!response.ok) {
                showExpenseErrors(result.errors);
                showExpenseMessage(result.message || 'Unable to save expense.');
                buttons.forEach((button) => { button.disabled = false; });
                return;
            }
            showExpenseMessage(result.message, true);
            setTimeout(() => location.reload(), 400);
        } catch (_) {
            showExpenseMessage('Network error. Expense not saved.');
            buttons.forEach((button) => { button.disabled = false; });
        }
    });

    paymentForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        paymentForm.querySelectorAll('[data-payment-error]').forEach((element) => { element.textContent = ''; });
        const button = paymentForm.querySelector('[type="submit"]');
        button.disabled = true;
        const values = Object.fromEntries(new FormData(paymentForm));
        const editing = Boolean(values.payment_number);
        try {
            const { response, result } = await fetchJson(editing ? paymentUrl(values.payment_number) : page.dataset.paymentUrl, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(values) });
            if (!response.ok) {
                Object.entries(result.errors || {}).forEach(([field, messages]) => {
                    const target = paymentForm.querySelector('[data-payment-error="' + field + '"]');
                    if (target) target.textContent = messages[0];
                });
                const message = document.querySelector('[data-expense-payment-message]');
                message.textContent = result.message || 'Unable to save payment.';
                message.className = 'mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-700';
                button.disabled = false;
                return;
            }
            location.reload();
        } catch (_) {
            const message = document.querySelector('[data-expense-payment-message]');
            message.textContent = 'Network error. Payment not saved.';
            message.className = 'mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-700';
            button.disabled = false;
        }
    });

    ['#expense-search', '#expense-category-filter', '#expense-status-filter', '#expense-payment-filter', '#expense-date-from', '#expense-date-to', '#expense-min', '#expense-max'].forEach((selector) => {
        document.querySelector(selector).addEventListener('input', () => { currentPage = 1; renderExpenses(); });
    });
    document.querySelector('#expense-clear-filters').addEventListener('click', () => {
        ['#expense-search', '#expense-category-filter', '#expense-status-filter', '#expense-payment-filter', '#expense-date-from', '#expense-date-to', '#expense-min', '#expense-max'].forEach((selector) => { document.querySelector(selector).value = ''; });
        currentPage = 1;
        renderExpenses();
    });
    document.querySelectorAll('[data-expense-sort]').forEach((button) => button.addEventListener('click', () => {
        const field = button.dataset.expenseSort;
        sort = { field, direction: sort.field === field && sort.direction === 'asc' ? 'desc' : 'asc' };
        renderExpenses();
    }));
    document.querySelector('#expense-prev').addEventListener('click', () => { currentPage--; renderExpenses(); });
    document.querySelector('#expense-next').addEventListener('click', () => { currentPage++; renderExpenses(); });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') ['form', 'view', 'payment'].forEach((name) => setModal(name, false));
    });
    renderExpenses();
    renderPayments();
};

document.addEventListener('DOMContentLoaded', setupExpenses);
