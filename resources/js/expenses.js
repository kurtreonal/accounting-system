const setupExpenses = () => {
    const page = document.querySelector('#expenses-page');
    if (!page) return;

    const records = JSON.parse(document.querySelector('#expense-data').textContent).expenses || [];
    const role = page.dataset.userRole;
    const canMutate = role !== 'Viewer / Auditor';
    const canApprove = ['Administrator', 'Accountant'].includes(role);
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
    const form = document.querySelector('#expense-form');
    const pageSize = 8;
    let currentPage = 1;
    let sort = { field: 'date', direction: 'desc' };
    let receipt = null;

    const token = () => globalThis.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => { const number = Math.random() * 16 | 0; return (character === 'x' ? number : (number & 3 | 8)).toString(16); });
    const urlFor = (expenseNumber, action = '') => `${page.dataset.storeUrl}/${encodeURIComponent(expenseNumber)}${action ? `/${action}` : ''}`;
    const setModal = (name, open) => { const modal = document.querySelector(`#expense-${name}-modal`); modal.classList.toggle('hidden', !open); modal.classList.toggle('flex', open); modal.setAttribute('aria-hidden', String(!open)); };
    const showMessage = (message, success = false) => { const target = document.querySelector('[data-expense-message]'); target.textContent = message; target.className = `mt-3 rounded-lg p-3 text-xs ${success ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`; };
    const clearErrors = () => document.querySelectorAll('#expense-form [data-error]').forEach((element) => { element.textContent = ''; });
    const showErrors = (errors = {}) => Object.entries(errors).forEach(([field, messages]) => { const target = form.querySelector(`[data-error="${field.split('.')[0]}"]`); if (target) target.textContent = messages[0]; });
    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, { ...options, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...(options.headers || {}) } });
        let result = {};
        try { result = await response.json(); } catch (_) { result = { message: 'The server returned an invalid response.' }; }
        return { response, result };
    };

    const statusBadge = (status) => ({
        Draft: 'bg-slate-100 text-slate-600',
        'For Review': 'bg-amber-100 text-amber-700',
        Approved: 'bg-blue-100 text-blue-700',
    })[status] || 'bg-slate-100 text-slate-600';

    const filtered = () => {
        const search = document.querySelector('#expense-search').value.trim().toLowerCase();
        const category = document.querySelector('#expense-category-filter').value;
        const status = document.querySelector('#expense-status-filter').value;
        const payment = document.querySelector('#expense-payment-filter').value;
        const from = document.querySelector('#expense-date-from').value;
        const to = document.querySelector('#expense-date-to').value;
        const min = Number(document.querySelector('#expense-min').value || 0);
        const maxInput = document.querySelector('#expense-max').value;
        const max = maxInput === '' ? Number.POSITIVE_INFINITY : Number(maxInput);
        return records.filter((row) => (!search || `${row.expense_number} ${row.payee} ${row.memo} ${row.category_name}`.toLowerCase().includes(search))
            && (!category || String(row.category_account_code) === category)
            && (!status || row.status === status)
            && (!payment || row.payment_status === payment)
            && (!from || row.date >= from) && (!to || row.date <= to)
            && Number(row.total) >= min && Number(row.total) <= max)
            .sort((left, right) => {
                const a = sort.field === 'total' ? Number(left[sort.field]) : String(left[sort.field]);
                const b = sort.field === 'total' ? Number(right[sort.field]) : String(right[sort.field]);
                return (a > b ? 1 : a < b ? -1 : 0) * (sort.direction === 'asc' ? 1 : -1);
            });
    };

    const render = () => {
        const rows = filtered();
        const pages = Math.max(1, Math.ceil(rows.length / pageSize));
        currentPage = Math.min(currentPage, pages);
        const start = (currentPage - 1) * pageSize;
        const visible = rows.slice(start, start + pageSize);
        document.querySelector('#expense-rows').innerHTML = visible.length ? visible.map((row) => {
            const actions = [`<button type="button" data-expense-view="${escapeHtml(row.expense_number)}" class="apm-icon-button" title="View expense" aria-label="View ${escapeHtml(row.expense_number)}"><i class="fa-solid fa-eye"></i></button>`];
            if (canMutate && row.status === 'Draft') {
                actions.push(`<button type="button" data-expense-edit="${escapeHtml(row.expense_number)}" class="apm-icon-button" title="Edit draft" aria-label="Edit ${escapeHtml(row.expense_number)}"><i class="fa-solid fa-pen"></i></button>`);
                actions.push(`<button type="button" data-expense-submit="${escapeHtml(row.expense_number)}" class="apm-icon-button text-amber-600" title="Submit for review" aria-label="Submit ${escapeHtml(row.expense_number)} for review"><i class="fa-solid fa-paper-plane"></i></button>`);
                actions.push(`<button type="button" data-expense-delete="${escapeHtml(row.expense_number)}" class="apm-icon-button text-rose-600" title="Delete draft" aria-label="Delete ${escapeHtml(row.expense_number)}"><i class="fa-solid fa-trash"></i></button>`);
            }
            if (canApprove && row.status === 'For Review') actions.push(`<button type="button" data-expense-approve="${escapeHtml(row.expense_number)}" class="rounded-md bg-blue-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-blue-500">Approve</button>`);
            return `<tr class="apm-table-row"><td><button type="button" data-expense-view="${escapeHtml(row.expense_number)}" class="font-mono text-[11px] font-semibold text-blue-600 hover:underline">${escapeHtml(row.expense_number)}</button></td><td class="font-mono text-[11px]">${escapeHtml(row.date)}</td><td><p class="font-medium text-slate-800">${escapeHtml(row.payee)}</p><p class="mt-0.5 max-w-52 truncate text-[10px] text-slate-400" title="${escapeHtml(row.memo)}">${escapeHtml(row.memo)}</p></td><td>${escapeHtml(row.category_name)}</td><td><p>${escapeHtml(row.payment_method)}</p><span class="text-[10px] ${row.payment_status === 'Paid' ? 'text-emerald-600' : 'text-amber-600'}">${escapeHtml(row.payment_status)}</span></td><td class="apm-money text-right">${money.format(Number(row.total))}<p class="text-[9px] text-slate-400">Tax ${money.format(Number(row.tax))}</p></td><td class="text-center">${row.receipt?.name ? `<span title="${escapeHtml(row.receipt.name)}" class="text-emerald-600"><i class="fa-solid fa-paperclip"></i><span class="sr-only">Attached</span></span>` : '<span class="text-slate-300">—</span>'}</td><td>${escapeHtml(row.created_by?.name || 'Demo User')}</td><td class="text-center"><span class="inline-flex rounded-full px-2 py-1 text-[10px] font-semibold ${statusBadge(row.status)}">${escapeHtml(row.status)}</span></td><td class="print:hidden"><div class="flex items-center justify-end gap-1">${actions.join('')}</div></td></tr>`;
        }).join('') : '<tr><td colspan="10" class="px-4 py-14 text-center"><i class="fa-solid fa-receipt text-3xl text-slate-300"></i><p class="mt-3 text-sm font-medium text-slate-600">No expenses found</p><p class="mt-1 text-xs text-slate-400">Adjust the filters or create a new expense.</p></td></tr>';
        document.querySelector('#expense-result-count').textContent = `${rows.length} matching record${rows.length === 1 ? '' : 's'}`;
        document.querySelector('#expense-page-summary').textContent = rows.length ? `Showing ${start + 1}–${Math.min(start + pageSize, rows.length)} of ${rows.length}` : 'Showing 0 records';
        document.querySelector('#expense-page-number').textContent = currentPage;
        document.querySelector('#expense-prev').disabled = currentPage === 1;
        document.querySelector('#expense-next').disabled = currentPage === pages;
    };

    const updateTotals = () => {
        const subtotal = Number(form.elements.amount.value || 0);
        const tax = subtotal * Number(form.elements.tax_rate.value || 0) / 100;
        document.querySelector('#expense-tax-preview').textContent = money.format(tax);
        document.querySelector('#expense-total-preview').textContent = money.format(subtotal + tax);
    };
    const updatePaymentFields = () => { const paid = form.elements.payment_status.value === 'Paid'; document.querySelector('[data-cash-account]').classList.toggle('hidden', !paid); form.elements.cash_account_code.required = paid; };
    const showReceipt = (metadata, dataUrl = '') => {
        const target = document.querySelector('#expense-receipt-preview');
        if (!metadata?.name) { target.classList.add('hidden'); target.innerHTML = ''; return; }
        target.classList.remove('hidden');
        target.innerHTML = `${dataUrl && metadata.type.startsWith('image/') ? `<img src="${dataUrl}" alt="Receipt preview" class="mb-2 max-h-36 rounded-lg object-contain">` : '<i class="fa-solid fa-file-lines mr-2 text-blue-600"></i>'}<strong>${escapeHtml(metadata.name)}</strong><span class="ml-2 text-slate-400">${(Number(metadata.size) / 1024).toFixed(1)} KB</span>`;
    };

    const openForm = (row = null) => {
        form.reset(); clearErrors(); receipt = row?.receipt || null;
        form.elements.expense_number.value = row?.expense_number || '';
        form.elements.request_token.value = row?.request_token || token();
        form.elements.date.value = row?.date || new Date().toISOString().slice(0, 10);
        ['payee', 'category_account_code', 'tax_rate', 'payment_status', 'payment_method', 'cash_account_code', 'memo'].forEach((field) => { if (row) form.elements[field].value = row[field] ?? ''; });
        form.elements.amount.value = row?.subtotal ?? '';
        document.querySelector('#expense-form-title').textContent = row ? `Edit ${row.expense_number}` : 'New Expense';
        document.querySelector('[data-expense-message]').classList.add('hidden');
        showReceipt(receipt); updateTotals(); updatePaymentFields(); setModal('form', true);
        setTimeout(() => form.elements.date.focus(), 0);
    };

    const openView = (row) => {
        document.querySelector('#expense-view-title').textContent = row.expense_number;
        document.querySelector('#expense-view-content').innerHTML = `<div class="flex items-start justify-between"><div><p class="text-sm font-semibold text-slate-900">${escapeHtml(row.payee)}</p><p class="mt-1 text-xs text-slate-500">${escapeHtml(row.date)} · ${escapeHtml(row.category_name)}</p></div><span class="rounded-full px-2 py-1 text-[10px] font-semibold ${statusBadge(row.status)}">${escapeHtml(row.status)}</span></div><dl class="mt-4 grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-4 text-xs"><div><dt class="text-slate-400">Subtotal</dt><dd class="mt-1 font-mono font-semibold">${money.format(Number(row.subtotal))}</dd></div><div><dt class="text-slate-400">Tax (${Number(row.tax_rate)}%)</dt><dd class="mt-1 font-mono font-semibold">${money.format(Number(row.tax))}</dd></div><div><dt class="text-slate-400">Total</dt><dd class="mt-1 font-mono font-bold text-blue-700">${money.format(Number(row.total))}</dd></div><div><dt class="text-slate-400">Payment</dt><dd class="mt-1 font-semibold">${escapeHtml(row.payment_status)} · ${escapeHtml(row.payment_method)}</dd></div></dl><div class="mt-4"><p class="text-[10px] uppercase text-slate-400">Memo</p><p class="mt-1 text-xs text-slate-700">${escapeHtml(row.memo)}</p></div><div class="mt-4 border-t border-slate-100 pt-3 text-xs"><p><span class="text-slate-400">Receipt:</span> ${row.receipt?.name ? `<i class="fa-solid fa-paperclip ml-1 text-emerald-600"></i> ${escapeHtml(row.receipt.name)}` : 'None'}</p><p class="mt-2"><span class="text-slate-400">Journal:</span> ${row.journal_entry_id ? `<a class="font-mono text-blue-600 hover:underline" href="/journal-entries?entry=${encodeURIComponent(row.journal_entry_id)}">${escapeHtml(row.journal_entry_id)}</a>` : 'Not posted'}</p></div>`;
        setModal('view', true);
    };

    const runAction = async (row, action, method, confirmation) => {
        if (!confirm(confirmation)) return;
        const button = document.querySelector(`[data-expense-${action}="${CSS.escape(row.expense_number)}"]`); if (button) button.disabled = true;
        try {
            const endpoint = action === 'submit' ? 'submit-review' : action === 'delete' ? '' : action;
            const { response, result } = await fetchJson(urlFor(row.expense_number, endpoint), { method });
            if (!response.ok) { alert(result.message || 'Unable to complete the action.'); if (button) button.disabled = false; return; }
            location.reload();
        } catch (_) { alert('Network error. No changes were made.'); if (button) button.disabled = false; }
    };

    document.querySelector('#expense-rows').addEventListener('click', (event) => {
        const target = event.target.closest('[data-expense-view], [data-expense-edit], [data-expense-submit], [data-expense-approve], [data-expense-delete]'); if (!target) return;
        const attribute = [...target.attributes].find((item) => item.name.startsWith('data-expense-'));
        const action = attribute.name.replace('data-expense-', ''); const row = records.find((item) => item.expense_number === attribute.value); if (!row) return;
        if (action === 'view') openView(row);
        if (action === 'edit') openForm(row);
        if (action === 'submit') runAction(row, 'submit', 'POST', `Submit ${row.expense_number} for review? It will no longer be editable.`);
        if (action === 'approve') runAction(row, 'approve', 'POST', `Approve and post ${row.expense_number}? This updates the ledger and cannot be edited afterward.`);
        if (action === 'delete') runAction(row, 'delete', 'DELETE', `Delete draft ${row.expense_number}? This cannot be undone.`);
    });

    if (document.querySelector('#expense-new')) document.querySelector('#expense-new').addEventListener('click', () => openForm());
    document.querySelectorAll('[data-expense-close]').forEach((button) => button.addEventListener('click', () => setModal(button.dataset.expenseClose, false)));
    form.elements.amount.addEventListener('input', updateTotals); form.elements.tax_rate.addEventListener('change', updateTotals); form.elements.payment_status.addEventListener('change', updatePaymentFields);
    document.querySelector('#expense-receipt').addEventListener('change', (event) => {
        const file = event.target.files[0]; const error = form.querySelector('[data-error="receipt"]'); error.textContent = '';
        if (!file) { receipt = null; showReceipt(null); return; }
        if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type) || file.size > 5242880) { event.target.value = ''; receipt = null; showReceipt(null); error.textContent = 'Choose a JPG, PNG, or PDF no larger than 5 MB.'; return; }
        receipt = { name: file.name, type: file.type, size: file.size };
        if (file.type.startsWith('image/')) { const reader = new FileReader(); reader.addEventListener('load', () => showReceipt(receipt, reader.result)); reader.readAsDataURL(file); } else showReceipt(receipt);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault(); clearErrors();
        const action = event.submitter?.value || 'draft'; const buttons = form.querySelectorAll('[type="submit"]'); buttons.forEach((button) => { button.disabled = true; });
        const values = Object.fromEntries(new FormData(form));
        const payload = { ...values, action, amount: Number(values.amount), tax_rate: Number(values.tax_rate), receipt };
        const editing = Boolean(values.expense_number); const url = editing ? urlFor(values.expense_number) : page.dataset.storeUrl;
        try {
            const { response, result } = await fetchJson(url, { method: editing ? 'PUT' : 'POST', body: JSON.stringify(payload) });
            if (!response.ok) { showErrors(result.errors); showMessage(result.message || 'Unable to save expense.'); buttons.forEach((button) => { button.disabled = false; }); return; }
            showMessage(result.message, true); setTimeout(() => location.reload(), 500);
        } catch (_) { showMessage('Network error. Expense was not saved.'); buttons.forEach((button) => { button.disabled = false; }); }
    });

    ['#expense-search', '#expense-category-filter', '#expense-status-filter', '#expense-payment-filter', '#expense-date-from', '#expense-date-to', '#expense-min', '#expense-max'].forEach((selector) => document.querySelector(selector).addEventListener('input', () => { currentPage = 1; render(); }));
    document.querySelector('#expense-clear-filters').addEventListener('click', () => { ['#expense-search', '#expense-category-filter', '#expense-status-filter', '#expense-payment-filter', '#expense-date-from', '#expense-date-to', '#expense-min', '#expense-max'].forEach((selector) => { document.querySelector(selector).value = ''; }); currentPage = 1; render(); });
    document.querySelectorAll('[data-expense-sort]').forEach((button) => button.addEventListener('click', () => { const field = button.dataset.expenseSort; sort = { field, direction: sort.field === field && sort.direction === 'asc' ? 'desc' : 'asc' }; render(); }));
    document.querySelector('#expense-prev').addEventListener('click', () => { currentPage--; render(); }); document.querySelector('#expense-next').addEventListener('click', () => { currentPage++; render(); });
    render();
};

document.addEventListener('DOMContentLoaded', setupExpenses);
