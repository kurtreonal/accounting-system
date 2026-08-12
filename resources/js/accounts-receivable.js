import { allocationTotal, createPosting, validateAllocations } from './accounting-engine';

const setupAccountsReceivable = () => {
    const page = document.querySelector('#accounts-receivable-page');
    const dataElement = document.querySelector('#ar-data');
    if (!page || !dataElement) return;

    const data = JSON.parse(dataElement.textContent || '{"invoices":[],"customers":[],"cashAccounts":[]}');
    const invoices = data.invoices || [];
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const modal = document.querySelector('#ar-payment-modal');
    const form = document.querySelector('#ar-payment-form');
    const invoiceRows = document.querySelector('#ar-invoice-rows');
    const allocationRows = document.querySelector('#ar-allocation-rows');
    const allocationTemplate = document.querySelector('#ar-allocation-template');
    const search = document.querySelector('#ar-search');
    const status = document.querySelector('#ar-status');
    const csrf = form.querySelector('input[name="_token"]').value;
    const canPostPayment = ['Administrator', 'Accountant'].includes(page.dataset.userRole) && data.cashAccounts.length > 0;
    const pageSize = 10;
    let currentPage = 1;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    const token = () => globalThis.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        return (character === 'x' ? random : (random & 0x3) | 0x8).toString(16);
    });
    const statusClass = (value) => ({
        Paid: 'bg-emerald-100 text-emerald-700',
        'Partially Paid': 'bg-sky-100 text-sky-700',
        Overdue: 'bg-red-100 text-red-700',
        Draft: 'bg-slate-100 text-slate-600',
        Unpaid: 'bg-amber-100 text-amber-700',
    }[value] || 'bg-amber-100 text-amber-700');
    const openInvoicesFor = (customerId) => invoices.filter((invoice) => (
        String(invoice.customer_id) === String(customerId)
        && invoice.status !== 'Draft'
        && Number(invoice.remaining_balance) > 0
    ));

    const filteredInvoices = () => {
        const needle = search.value.trim().toLowerCase();
        return invoices.filter((invoice) => {
            const searchable = [invoice.invoice_number, invoice.customer_name, invoice.reference].join(' ').toLowerCase();
            return (!needle || searchable.includes(needle)) && (!status.value || invoice.display_status === status.value);
        });
    };

    const renderInvoices = () => {
        const filtered = filteredInvoices();
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        currentPage = Math.max(1, Math.min(currentPage, totalPages));
        const visible = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize);

        if (visible.length === 0) {
            invoiceRows.innerHTML = '<tr><td colspan="9" class="px-5 py-14 text-center"><i class="fa-regular fa-file-lines text-2xl text-slate-300" aria-hidden="true"></i><p class="mt-2 text-sm font-medium text-slate-600">No invoices found</p><p class="mt-1 text-xs text-slate-400">Create and post invoice from Sales and Revenue.</p></td></tr>';
        } else {
            invoiceRows.innerHTML = visible.map((invoice) => {
                const payable = invoice.status !== 'Draft' && Number(invoice.remaining_balance) > 0;
                const action = payable && canPostPayment
                    ? `<button type="button" data-record-payment="${escapeHtml(invoice.invoice_number)}" class="apm-row-action">Record Payment</button>`
                    : payable
                        ? `<button type="button" class="apm-row-action opacity-50" disabled title="Only Administrators and Accountants with an active cash or bank account can post payments.">Record Payment</button>`
                        : `<a class="apm-row-action inline-block" href="${page.dataset.salesUrl}?invoice=${encodeURIComponent(invoice.invoice_number)}">View</a>`;
                return `<tr class="apm-table-row">
                    <td><a href="${page.dataset.salesUrl}?invoice=${encodeURIComponent(invoice.invoice_number)}" class="apm-code text-blue-600 hover:underline">${escapeHtml(invoice.invoice_number)}</a></td>
                    <td class="apm-code">${escapeHtml(invoice.invoice_date)}</td>
                    <td class="apm-code">${escapeHtml(invoice.due_date)}</td>
                    <td class="font-medium text-slate-800">${escapeHtml(invoice.customer_name)}</td>
                    <td class="apm-money text-right">${money.format(Number(invoice.total))}</td>
                    <td class="apm-money text-right">${Number(invoice.amount_paid) > 0 ? money.format(Number(invoice.amount_paid)) : '—'}</td>
                    <td class="apm-money text-right">${money.format(Number(invoice.remaining_balance))}</td>
                    <td><span class="inline-flex rounded-md px-2 py-1 text-[10px] font-medium ${statusClass(invoice.display_status)}">${escapeHtml(invoice.display_status)}</span></td>
                    <td class="apm-actions">${action}</td>
                </tr>`;
            }).join('');
        }

        document.querySelector('#ar-invoice-count').textContent = filtered.length;
        document.querySelector('#ar-page-summary').textContent = filtered.length
            ? `Showing ${(currentPage - 1) * pageSize + 1}–${Math.min(currentPage * pageSize, filtered.length)} of ${filtered.length} records`
            : 'Showing 0 records';
        document.querySelector('#ar-page-number').textContent = currentPage;
        document.querySelector('#ar-prev').disabled = currentPage === 1;
        document.querySelector('#ar-next').disabled = currentPage === totalPages;

        const parameters = new URLSearchParams();
        if (search.value.trim()) parameters.set('search', search.value.trim());
        if (status.value) parameters.set('status', status.value);
        const query = parameters.toString();
        document.querySelector('#ar-export').href = `${page.dataset.exportUrl}${query ? `?${query}` : ''}`;
    };

    const clearErrors = () => {
        form.querySelectorAll('[data-error], [data-allocation-error], [data-allocation-amount-error]').forEach((element) => { element.textContent = ''; });
        form.querySelector('[data-ar-message]').classList.add('hidden');
    };
    const showMessage = (value, success = false) => {
        const element = form.querySelector('[data-ar-message]');
        element.textContent = value;
        element.className = `rounded-lg px-3 py-2 text-xs ${success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`;
    };
    const showErrors = (result) => {
        showMessage(result.message || 'Unable to post payment.');
        Object.entries(result.errors || {}).forEach(([field, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : messages;
            const allocationMatch = field.match(/^allocations\.(\d+)\.(invoice_number|amount)$/);
            if (allocationMatch) {
                const row = allocationRows.children[Number(allocationMatch[1])];
                const target = allocationMatch[2] === 'amount'
                    ? row?.querySelector('[data-allocation-amount-error]')
                    : row?.querySelector('[data-allocation-error]');
                if (target) target.textContent = message;
                return;
            }
            const target = form.querySelector(`[data-error="${CSS.escape(field)}"]`);
            if (target) target.textContent = message;
        });
    };
    const setModal = (open) => {
        modal.classList.toggle('hidden', !open);
        modal.classList.toggle('flex', open);
        modal.setAttribute('aria-hidden', String(!open));
        document.body.classList.toggle('overflow-hidden', open);
        if (open) form.elements.customer_id.focus();
    };

    const invoiceOptions = (selected = '') => {
        const options = openInvoicesFor(form.elements.customer_id.value).map((invoice) => (
            `<option value="${escapeHtml(invoice.invoice_number)}" ${invoice.invoice_number === selected ? 'selected' : ''}>${escapeHtml(invoice.invoice_number)} — ${money.format(Number(invoice.remaining_balance))}</option>`
        )).join('');
        return `<option value="">Select invoice</option>${options}`;
    };
    const updateTotal = () => {
        const total = allocationTotal([...allocationRows.querySelectorAll('[data-allocation-amount]')]
            .map((input) => ({ amount: input.value })));
        document.querySelector('#ar-payment-total').textContent = money.format(total);
    };
    const updateAllocation = (row, keepAmount = false) => {
        const invoice = invoices.find((item) => item.invoice_number === row.querySelector('[data-allocation-invoice]').value);
        row.querySelector('[data-allocation-remaining]').textContent = invoice ? money.format(Number(invoice.remaining_balance)) : money.format(0);
        if (!keepAmount) row.querySelector('[data-allocation-amount]').value = invoice ? Number(invoice.remaining_balance).toFixed(2) : '';
        updateTotal();
    };
    const addAllocation = (invoiceNumber = '') => {
        clearErrors();
        if (!form.elements.customer_id.value) {
            form.querySelector('[data-error="customer_id"]').textContent = 'Select customer first.';
            return;
        }
        const openInvoices = openInvoicesFor(form.elements.customer_id.value);
        if (openInvoices.length === 0) {
            showMessage('Selected customer has no open posted invoices.');
            return;
        }
        const row = allocationTemplate.content.cloneNode(true).firstElementChild;
        row.querySelector('[data-allocation-invoice]').innerHTML = invoiceOptions(invoiceNumber);
        allocationRows.append(row);
        updateAllocation(row);
    };
    const resetPaymentForm = () => {
        form.reset();
        form.elements.request_token.value = token();
        allocationRows.replaceChildren();
        clearErrors();
        updateTotal();
    };

    document.querySelectorAll('[data-ar-tab]').forEach((button) => button.addEventListener('click', () => {
        document.querySelectorAll('[data-ar-tab]').forEach((tab) => {
            tab.classList.remove('border-blue-600', 'text-blue-600');
            tab.setAttribute('aria-selected', 'false');
        });
        document.querySelectorAll('[data-ar-panel]').forEach((panel) => { panel.hidden = panel.dataset.arPanel !== button.dataset.arTab; });
        button.classList.add('border-blue-600', 'text-blue-600');
        button.setAttribute('aria-selected', 'true');
        if (button.dataset.arTab === 'aging') renderAging();
    }));
    search.addEventListener('input', () => { currentPage = 1; renderInvoices(); });
    status.addEventListener('change', () => { currentPage = 1; renderInvoices(); });
    document.querySelector('#ar-prev').addEventListener('click', () => { currentPage -= 1; renderInvoices(); });
    document.querySelector('#ar-next').addEventListener('click', () => { currentPage += 1; renderInvoices(); });
    invoiceRows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-record-payment]');
        if (!button) return;
        const invoice = invoices.find((item) => item.invoice_number === button.dataset.recordPayment);
        if (!invoice) return;
        resetPaymentForm();
        form.elements.customer_id.value = invoice.customer_id;
        addAllocation(invoice.invoice_number);
        setModal(true);
    });
    document.querySelector('#ar-add-allocation').addEventListener('click', () => addAllocation());
    form.elements.customer_id.addEventListener('change', () => { allocationRows.replaceChildren(); clearErrors(); updateTotal(); });
    allocationRows.addEventListener('change', (event) => {
        if (event.target.matches('[data-allocation-invoice]')) updateAllocation(event.target.closest('tr'));
    });
    allocationRows.addEventListener('input', updateTotal);
    allocationRows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-allocation]');
        if (!button) return;
        button.closest('tr').remove();
        updateTotal();
    });
    document.querySelectorAll('[data-ar-close]').forEach((button) => button.addEventListener('click', () => setModal(false)));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) setModal(false); });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();
        if (allocationRows.children.length === 0) {
            form.querySelector('[data-error="allocations"]').textContent = 'Add at least one invoice.';
            return;
        }

        const allocations = [...allocationRows.querySelectorAll('tr')].map((row) => ({
            invoice_number: row.querySelector('[data-allocation-invoice]').value,
            amount: row.querySelector('[data-allocation-amount]').value,
        }));
        let total;
        try {
            total = validateAllocations(allocations, openInvoicesFor(form.elements.customer_id.value), 'invoice_number');
        } catch (problem) {
            showMessage(problem.message);
            return;
        }
        const payload = {
            request_token: form.elements.request_token.value,
            customer_id: form.elements.customer_id.value,
            payment_date: form.elements.payment_date.value,
            cash_account_code: form.elements.cash_account_code.value,
            reference: form.elements.reference.value.trim(),
            memo: form.elements.memo.value.trim(),
            allocations,
        };
        try {
            payload.posting = createPosting('customer-payment', { ...payload, amount: total }, data.accounts || []);
        } catch (problem) {
            showMessage(problem.message);
            return;
        }

        const submit = document.querySelector('#ar-payment-submit');
        submit.disabled = true;

        try {
            const response = await fetch(page.dataset.paymentUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok) {
                showErrors(result);
                submit.disabled = false;
                return;
            }
            showMessage(result.message, true);
            window.setTimeout(() => window.location.reload(), 650);
        } catch (_) {
            showMessage('Network error. Payment was not submitted.');
            submit.disabled = false;
        }
    });

    const bucketName = (days) => days <= 0 ? 'Current' : days <= 30 ? '1–30 Days' : days <= 60 ? '31–60 Days' : days <= 90 ? '61–90 Days' : 'Over 90 Days';
    const renderAging = () => {
        const asOf = document.querySelector('#ar-as-of').value;
        const bucketNames = ['Current', '1–30 Days', '31–60 Days', '61–90 Days', 'Over 90 Days'];
        const totals = Object.fromEntries(bucketNames.map((name) => [name, 0]));
        const customers = new Map();

        invoices.filter((invoice) => invoice.status !== 'Draft' && Number(invoice.remaining_balance) > 0 && invoice.invoice_date <= asOf).forEach((invoice) => {
            const days = Math.floor((new Date(`${asOf}T00:00:00`) - new Date(`${invoice.due_date}T00:00:00`)) / 86400000);
            const bucket = bucketName(days);
            const balance = Number(invoice.remaining_balance);
            totals[bucket] += balance;
            if (!customers.has(String(invoice.customer_id))) {
                customers.set(String(invoice.customer_id), { name: invoice.customer_name, ...Object.fromEntries(bucketNames.map((name) => [name, 0])) });
            }
            customers.get(String(invoice.customer_id))[bucket] += balance;
        });

        document.querySelector('#ar-aging-cards').innerHTML = bucketNames.map((name, index) => `<article class="apm-summary-card dashboard-enter" style="animation-delay:${index * 50}ms"><p>${name}</p><strong>${money.format(totals[name])}</strong><span>Outstanding balance</span></article>`).join('');
        const agingRows = [...customers.values()];
        document.querySelector('#ar-aging-rows').innerHTML = agingRows.length
            ? agingRows.map((customer) => {
                const total = bucketNames.reduce((sum, name) => sum + customer[name], 0);
                return `<tr class="apm-table-row"><td class="font-medium text-slate-800">${escapeHtml(customer.name)}</td>${bucketNames.map((name) => `<td class="apm-money text-right">${money.format(customer[name])}</td>`).join('')}<td class="apm-money text-right font-bold">${money.format(total)}</td></tr>`;
            }).join('')
            : '<tr><td colspan="7" class="px-5 py-14 text-center text-slate-500">No open invoices for selected date.</td></tr>';
    };
    document.querySelector('#ar-as-of').addEventListener('change', renderAging);

    renderInvoices();
};

document.addEventListener('DOMContentLoaded', setupAccountsReceivable);
