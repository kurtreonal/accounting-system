import { allocationTotal, createPosting, documentTotals, validateAllocations } from './accounting-engine';

import { can } from './demo-access';

const setupAccountsPayable = () => {
    const page = document.querySelector('#accounts-payable-page');
    const dataElement = document.querySelector('#ap-data');
    if (!page || !dataElement) return;

    const data = JSON.parse(dataElement.textContent || '{"bills":[],"vendors":[],"payments":[],"cashAccounts":[],"purchaseAccounts":[]}');
    const bills = data.bills || [];
    const vendors = data.vendors || [];
    const payments = data.payments || [];
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const canMutate = can('drafts.manage');
    const canApprove = can('transactions.approve');
    const csrf = document.querySelector('#ap-bill-form input[name="_token"]').value;
    const billRows = document.querySelector('#ap-bill-rows');
    const search = document.querySelector('#ap-search');
    const status = document.querySelector('#ap-status');
    const pageSize = 10;
    let currentPage = 1;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const requestToken = () => globalThis.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        return (character === 'x' ? random : (random & 0x3) | 0x8).toString(16);
    });
    const statusClass = (value) => ({
        Paid: 'bg-emerald-100 text-emerald-700',
        'Partially Paid': 'bg-sky-100 text-sky-700',
        Overdue: 'bg-red-100 text-red-700',
        Draft: 'bg-slate-100 text-slate-600',
        Unpaid: 'bg-amber-100 text-amber-700',
    }[value] || 'bg-slate-100 text-slate-600');
    const setModal = (name, open) => {
        const modal = document.querySelector(`#ap-${name}-modal`);
        modal.classList.toggle('hidden', !open);
        modal.classList.toggle('flex', open);
        modal.setAttribute('aria-hidden', String(!open));
        document.body.classList.toggle('overflow-hidden', open);
    };
    const fetchJson = async (url, method, payload) => {
        const response = await fetch(url, {
            method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: payload ? JSON.stringify(payload) : undefined,
        });
        let result;
        try {
            result = await response.json();
        } catch (_) {
            result = { message: 'Server returned invalid response.' };
        }
        return { response, result };
    };
    const showFormMessage = (selector, value, success = false) => {
        const element = document.querySelector(selector);
        element.textContent = value;
        element.className = `rounded-lg px-3 py-2 text-xs ${success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`;
    };

    const filteredBills = () => {
        const needle = search.value.trim().toLowerCase();
        return bills.filter((bill) => {
            const searchable = [bill.bill_number, bill.reference, bill.vendor_name].join(' ').toLowerCase();
            return (!needle || searchable.includes(needle)) && (!status.value || bill.display_status === status.value);
        });
    };
    const actionButton = (label, attribute, value, enabled = true) => enabled
        ? `<button type="button" ${attribute}="${escapeHtml(value)}" class="apm-row-action">${label}</button>`
        : `<button type="button" class="apm-row-action opacity-50" disabled title="Action unavailable for current role or configuration.">${label}</button>`;
    const renderBills = () => {
        const filtered = filteredBills();
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        currentPage = Math.max(1, Math.min(currentPage, totalPages));
        const visible = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize);

        billRows.innerHTML = visible.length ? visible.map((bill) => {
            const actions = [actionButton('View', 'data-view-bill', bill.bill_number)];
            if (bill.status === 'Draft') {
                actions.push(actionButton('Edit', 'data-edit-bill', bill.bill_number, canMutate));
                actions.push(actionButton('Post', 'data-post-bill', bill.bill_number, canApprove));
            } else if (Number(bill.remaining_balance) > 0) {
                actions.push(actionButton('Pay', 'data-pay-bill', bill.bill_number, canMutate && data.cashAccounts.length > 0));
            }
            return `<tr class="apm-table-row">
                <td><button type="button" data-view-bill="${escapeHtml(bill.bill_number)}" class="apm-code text-left text-blue-600 hover:underline">${escapeHtml(bill.bill_number)}</button><p class="mt-1 text-[10px] text-slate-400">${escapeHtml(bill.reference)}</p></td>
                <td class="apm-code">${escapeHtml(bill.bill_date)}</td><td class="apm-code">${escapeHtml(bill.due_date)}</td>
                <td class="font-medium text-slate-800">${escapeHtml(bill.vendor_name)}</td>
                <td class="apm-money text-right">${money.format(Number(bill.total))}</td>
                <td class="apm-money text-right">${Number(bill.amount_paid) > 0 ? money.format(Number(bill.amount_paid)) : '-'}</td>
                <td class="apm-money text-right">${money.format(Number(bill.remaining_balance))}</td>
                <td><span class="inline-flex rounded-md px-2 py-1 text-[10px] font-medium ${statusClass(bill.display_status)}">${escapeHtml(bill.display_status)}</span></td>
                <td class="apm-actions">${actions.join(' ')}</td>
            </tr>`;
        }).join('') : '<tr><td colspan="9" class="px-5 py-14 text-center"><i class="fa-regular fa-file-lines text-2xl text-slate-300" aria-hidden="true"></i><p class="mt-2 text-sm font-medium text-slate-600">No vendor bills found</p><p class="mt-1 text-xs text-slate-400">Create first bill or change filters.</p></td></tr>';

        document.querySelector('#ap-bill-count').textContent = filtered.length;
        document.querySelector('#ap-page-summary').textContent = filtered.length
            ? `Showing ${(currentPage - 1) * pageSize + 1}-${Math.min(currentPage * pageSize, filtered.length)} of ${filtered.length} records`
            : 'Showing 0 records';
        document.querySelector('#ap-page-number').textContent = currentPage;
        document.querySelector('#ap-prev').disabled = currentPage === 1;
        document.querySelector('#ap-next').disabled = currentPage === totalPages;
        const parameters = new URLSearchParams();
        if (search.value.trim()) parameters.set('search', search.value.trim());
        if (status.value) parameters.set('status', status.value);
        const query = parameters.toString();
        document.querySelector('#ap-export').href = `${page.dataset.exportUrl}${query ? `?${query}` : ''}`;
        document.querySelector('#ap-export-pdf').href = `${page.dataset.pdfUrl}${query ? `?${query}` : ''}`;
    };

    document.querySelectorAll('[data-ap-tab]').forEach((button) => button.addEventListener('click', () => {
        document.querySelectorAll('[data-ap-tab]').forEach((tab) => {
            tab.classList.remove('border-blue-600', 'text-blue-600');
            tab.setAttribute('aria-selected', 'false');
        });
        document.querySelectorAll('[data-ap-panel]').forEach((panel) => { panel.hidden = panel.dataset.apPanel !== button.dataset.apTab; });
        button.classList.add('border-blue-600', 'text-blue-600');
        button.setAttribute('aria-selected', 'true');
        if (button.dataset.apTab === 'aging') renderAging();
    }));
    search.addEventListener('input', () => { currentPage = 1; renderBills(); });
    status.addEventListener('change', () => { currentPage = 1; renderBills(); });
    document.querySelector('#ap-prev').addEventListener('click', () => { currentPage -= 1; renderBills(); });
    document.querySelector('#ap-next').addEventListener('click', () => { currentPage += 1; renderBills(); });

    const billModal = document.querySelector('#ap-bill-modal');
    const billForm = document.querySelector('#ap-bill-form');
    const billLineRows = document.querySelector('#ap-bill-lines');
    const billLineTemplate = document.querySelector('#ap-bill-line-template');
    let currentAttachment = null;
    const clearBillErrors = () => {
        billForm.querySelectorAll('[data-bill-error], [data-line-error]').forEach((element) => { element.textContent = ''; });
        billForm.querySelector('[data-bill-message]').classList.add('hidden');
    };
    const updateBillTotals = () => {
        const rows = [...billLineRows.querySelectorAll('tr')];
        const totals = documentTotals(rows.map((row) => ({
            quantity: row.querySelector('[data-bill-quantity]').value,
            unit_price: row.querySelector('[data-bill-price]').value,
            tax_rate: row.querySelector('[data-bill-tax-rate]').value,
        })));
        rows.forEach((row, index) => {
            row.querySelector('[data-bill-line-total]').textContent = money.format(totals.lines[index].total);
        });
        document.querySelector('#ap-bill-subtotal').textContent = money.format(totals.subtotal);
        document.querySelector('#ap-bill-tax').textContent = money.format(totals.tax);
        document.querySelector('#ap-bill-total').textContent = money.format(totals.total);

        return totals;
    };
    const addBillLine = (line = {}) => {
        const row = billLineTemplate.content.cloneNode(true).firstElementChild;
        row.querySelector('[data-bill-account]').value = line.account_code || '';
        row.querySelector('[data-bill-description]').value = line.description || '';
        row.querySelector('[data-bill-quantity]').value = line.quantity ?? 1;
        row.querySelector('[data-bill-price]').value = line.unit_price ?? '';
        if (line.tax_rate !== undefined) row.querySelector('[data-bill-tax-rate]').value = line.tax_rate;
        billLineRows.append(row);
        updateBillTotals();
    };
    const showCurrentAttachment = () => {
        const element = document.querySelector('#ap-attachment-current');
        element.classList.toggle('hidden', !currentAttachment);
        element.textContent = currentAttachment ? `Current: ${currentAttachment.name}` : '';
    };
    const resetBillForm = (bill = null) => {
        billForm.reset();
        clearBillErrors();
        billLineRows.replaceChildren();
        currentAttachment = bill?.attachment || null;
        billForm.elements.bill_number.value = bill?.bill_number || '';
        document.querySelector('#ap-bill-title').textContent = bill ? `Edit ${bill.bill_number}` : 'New Vendor Bill';
        if (bill) {
            ['vendor_id', 'reference', 'bill_date', 'due_date', 'memo'].forEach((field) => { billForm.elements[field].value = bill[field] ?? ''; });
            bill.lines.forEach(addBillLine);
        } else {
            billForm.elements.bill_date.value = new Date().toISOString().slice(0, 10);
            const due = new Date(); due.setDate(due.getDate() + 30);
            billForm.elements.due_date.value = due.toISOString().slice(0, 10);
            addBillLine();
        }
        showCurrentAttachment();
        updateBillTotals();
    };
    const updateDueDate = () => {
        if (!billForm.elements.bill_date.value) return;
        const vendor = vendors.find((item) => String(item.id) === String(billForm.elements.vendor_id.value));
        const date = new Date(`${billForm.elements.bill_date.value}T00:00:00`);
        date.setDate(date.getDate() + Number(vendor?.payment_terms_days || 0));
        billForm.elements.due_date.value = date.toISOString().slice(0, 10);
    };
    const showBillErrors = (result) => {
        showFormMessage('[data-bill-message]', result.message || 'Unable to save vendor bill.');
        Object.entries(result.errors || {}).forEach(([field, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : messages;
            const lineMatch = field.match(/^lines\.(\d+)\.(.+)$/);
            if (lineMatch) {
                const row = billLineRows.children[Number(lineMatch[1])];
                const target = row?.querySelector(`[data-line-error="${CSS.escape(lineMatch[2])}"]`);
                if (target) target.textContent = message;
                return;
            }
            const target = billForm.querySelector(`[data-bill-error="${CSS.escape(field.split('.')[0])}"]`);
            if (target) target.textContent = message;
        });
    };
    document.querySelector('#ap-new-bill')?.addEventListener('click', () => { resetBillForm(); setModal('bill', true); billForm.elements.vendor_id.focus(); });
    document.querySelector('#ap-add-bill-line').addEventListener('click', () => addBillLine());
    billLineRows.addEventListener('input', updateBillTotals);
    billLineRows.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-bill-line]');
        if (!button) return;
        button.closest('tr').remove();
        updateBillTotals();
    });
    billForm.elements.vendor_id.addEventListener('change', updateDueDate);
    billForm.elements.bill_date.addEventListener('change', updateDueDate);
    billForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearBillErrors();
        if (billLineRows.children.length === 0) {
            billForm.querySelector('[data-bill-error="lines"]').textContent = 'Add at least one line.';
            return;
        }
        const file = billForm.elements.attachment_file.files[0];
        if (file && file.size > 10485760) {
            billForm.querySelector('[data-bill-error="attachment"]').textContent = 'Attachment must not exceed 10 MB.';
            return;
        }
        if (file && file.type !== 'application/pdf' && !file.type.startsWith('image/')) {
            billForm.querySelector('[data-bill-error="attachment"]').textContent = 'Attachment must be PDF or image.';
            return;
        }
        const submit = document.querySelector('#ap-bill-submit');
        submit.disabled = true;
        const billNumber = billForm.elements.bill_number.value;
        const payload = {
            vendor_id: billForm.elements.vendor_id.value,
            reference: billForm.elements.reference.value.trim(),
            bill_date: billForm.elements.bill_date.value,
            due_date: billForm.elements.due_date.value,
            memo: billForm.elements.memo.value.trim(),
            attachment: file ? { name: file.name, type: file.type, size: file.size } : currentAttachment,
            lines: [...billLineRows.querySelectorAll('tr')].map((row) => ({
                account_code: row.querySelector('[data-bill-account]').value,
                description: row.querySelector('[data-bill-description]').value.trim(),
                quantity: row.querySelector('[data-bill-quantity]').value,
                unit_price: row.querySelector('[data-bill-price]').value,
                tax_rate: row.querySelector('[data-bill-tax-rate]').value,
            })),
        };
        try {
            const url = billNumber ? `${page.dataset.billUrl}/${encodeURIComponent(billNumber)}` : page.dataset.billUrl;
            const { response, result } = await fetchJson(url, billNumber ? 'PUT' : 'POST', payload);
            if (!response.ok) { showBillErrors(result); submit.disabled = false; return; }
            showFormMessage('[data-bill-message]', result.message, true);
            window.setTimeout(() => window.location.reload(), 650);
        } catch (_) {
            showFormMessage('[data-bill-message]', 'Network error. Vendor bill was not saved.');
            submit.disabled = false;
        }
    });

    const vendorForm = document.querySelector('#ap-vendor-form');
    const clearVendorErrors = () => {
        vendorForm.querySelectorAll('[data-vendor-error]').forEach((element) => { element.textContent = ''; });
        vendorForm.querySelector('[data-vendor-message]').classList.add('hidden');
    };
    const resetVendorForm = (vendor = null) => {
        vendorForm.reset(); clearVendorErrors();
        vendorForm.elements.vendor_id.value = vendor?.id || '';
        document.querySelector('#ap-vendor-title').textContent = vendor ? `Edit ${vendor.code}` : 'New Vendor';
        if (vendor) ['code', 'name', 'contact_person', 'email', 'phone', 'address', 'tax_id', 'payment_terms_days', 'opening_balance'].forEach((field) => { vendorForm.elements[field].value = vendor[field] ?? ''; });
        else { vendorForm.elements.payment_terms_days.value = 30; vendorForm.elements.opening_balance.value = 0; }
    };
    const showVendorErrors = (result) => {
        showFormMessage('[data-vendor-message]', result.message || 'Unable to save vendor.');
        Object.entries(result.errors || {}).forEach(([field, messages]) => {
            const target = vendorForm.querySelector(`[data-vendor-error="${CSS.escape(field)}"]`);
            if (target) target.textContent = Array.isArray(messages) ? messages[0] : messages;
        });
    };
    document.querySelector('#ap-new-vendor')?.addEventListener('click', () => { resetVendorForm(); setModal('vendor', true); vendorForm.elements.code.focus(); });
    vendorForm.addEventListener('submit', async (event) => {
        event.preventDefault(); clearVendorErrors();
        const id = vendorForm.elements.vendor_id.value;
        const payload = Object.fromEntries(['code', 'name', 'contact_person', 'email', 'phone', 'address', 'tax_id', 'payment_terms_days', 'opening_balance'].map((field) => [field, vendorForm.elements[field].value.trim()]));
        const submit = document.querySelector('#ap-vendor-submit'); submit.disabled = true;
        try {
            const { response, result } = await fetchJson(id ? `${page.dataset.vendorUrl}/${id}` : page.dataset.vendorUrl, id ? 'PUT' : 'POST', payload);
            if (!response.ok) { showVendorErrors(result); submit.disabled = false; return; }
            showFormMessage('[data-vendor-message]', result.message, true);
            window.setTimeout(() => window.location.reload(), 650);
        } catch (_) {
            showFormMessage('[data-vendor-message]', 'Network error. Vendor was not saved.'); submit.disabled = false;
        }
    });
    document.querySelector('#ap-vendor-rows').addEventListener('click', async (event) => {
        const edit = event.target.closest('[data-edit-vendor]');
        if (edit) {
            const vendor = vendors.find((item) => String(item.id) === edit.dataset.editVendor);
            if (vendor) { resetVendorForm(vendor); setModal('vendor', true); }
            return;
        }
        const toggle = event.target.closest('[data-toggle-vendor]');
        if (!toggle || !globalThis.confirm(`Set vendor status to ${toggle.dataset.nextStatus}?`)) return;
        toggle.disabled = true;
        try {
            const { response, result } = await fetchJson(`${page.dataset.vendorUrl}/${toggle.dataset.toggleVendor}/status`, 'PATCH', { status: toggle.dataset.nextStatus });
            if (!response.ok) { globalThis.alert(result.message || 'Unable to update vendor status.'); toggle.disabled = false; return; }
            window.location.reload();
        } catch (_) {
            globalThis.alert('Network error. Vendor status was not updated.'); toggle.disabled = false;
        }
    });
    const filterVendors = () => {
        const needle = document.querySelector('#ap-vendor-search').value.trim().toLowerCase();
        const vendorStatus = document.querySelector('#ap-vendor-status').value;
        document.querySelectorAll('#ap-vendor-rows tr[data-vendor-id]').forEach((row) => {
            const vendor = vendors.find((item) => String(item.id) === row.dataset.vendorId);
            const matchesSearch = !needle || row.textContent.toLowerCase().includes(needle);
            const matchesStatus = !vendorStatus || vendor?.status === vendorStatus;
            row.hidden = !matchesSearch || !matchesStatus;
        });
    };
    document.querySelector('#ap-vendor-search').addEventListener('input', filterVendors);
    document.querySelector('#ap-vendor-status').addEventListener('change', filterVendors);

    const paymentForm = document.querySelector('#ap-payment-form');
    let editingPayment = '';
    const allocationRows = document.querySelector('#ap-allocation-rows');
    const allocationTemplate = document.querySelector('#ap-allocation-template');
    const openBillsFor = (vendorId) => bills.filter((bill) => String(bill.vendor_id) === String(vendorId) && bill.status !== 'Draft' && Number(bill.remaining_balance) > 0);
    const clearPaymentErrors = () => {
        paymentForm.querySelectorAll('[data-payment-error], [data-allocation-error], [data-allocation-amount-error]').forEach((element) => { element.textContent = ''; });
        paymentForm.querySelector('[data-payment-message]').classList.add('hidden');
    };
    const updatePaymentTotal = () => {
        const total = allocationTotal([...allocationRows.querySelectorAll('[data-allocation-amount]')].map((input) => ({ amount: input.value })));
        document.querySelector('#ap-payment-total').textContent = money.format(total);
    };
    const billOptions = (selected = '') => `<option value="">Select bill</option>${openBillsFor(paymentForm.elements.vendor_id.value).map((bill) => `<option value="${escapeHtml(bill.bill_number)}" ${bill.bill_number === selected ? 'selected' : ''}>${escapeHtml(bill.bill_number)} - ${money.format(Number(bill.remaining_balance))}</option>`).join('')}`;
    const updateAllocation = (row, keepAmount = false) => {
        const bill = bills.find((item) => item.bill_number === row.querySelector('[data-allocation-bill]').value);
        row.querySelector('[data-allocation-remaining]').textContent = money.format(Number(bill?.remaining_balance || 0));
        if (!keepAmount) row.querySelector('[data-allocation-amount]').value = bill ? Number(bill.remaining_balance).toFixed(2) : '';
        updatePaymentTotal();
    };
    const addAllocation = (billNumber = '') => {
        clearPaymentErrors();
        if (!paymentForm.elements.vendor_id.value) { paymentForm.querySelector('[data-payment-error="vendor_id"]').textContent = 'Select vendor first.'; return; }
        if (openBillsFor(paymentForm.elements.vendor_id.value).length === 0) { showFormMessage('[data-payment-message]', 'Selected vendor has no open posted bills.'); return; }
        const row = allocationTemplate.content.cloneNode(true).firstElementChild;
        row.querySelector('[data-allocation-bill]').innerHTML = billOptions(billNumber);
        allocationRows.append(row); updateAllocation(row);
    };
    const resetPaymentForm = () => {
        editingPayment = '';
        paymentForm.reset(); paymentForm.elements.request_token.value = requestToken(); allocationRows.replaceChildren(); clearPaymentErrors(); updatePaymentTotal();
    };
    const editPayment = (payment) => {
        resetPaymentForm(); editingPayment = payment.payment_number;
        paymentForm.elements.request_token.value = payment.request_token;
        paymentForm.elements.vendor_id.value = payment.vendor_id;
        paymentForm.elements.payment_date.value = payment.payment_date;
        paymentForm.elements.cash_account_code.value = payment.cash_account_code;
        paymentForm.elements.reference.value = payment.reference || '';
        paymentForm.elements.memo.value = payment.memo || '';
        (payment.allocations || []).forEach((allocation) => {
            const row = allocationTemplate.content.cloneNode(true).firstElementChild;
            row.querySelector('[data-allocation-bill]').innerHTML = billOptions(allocation.bill_number);
            allocationRows.append(row);
            row.querySelector('[data-allocation-amount]').value = Number(allocation.amount).toFixed(2);
            updateAllocation(row, true);
        });
        setModal('payment', true);
    };
    document.querySelector('#ap-add-allocation').addEventListener('click', () => addAllocation());
    paymentForm.elements.vendor_id.addEventListener('change', () => { allocationRows.replaceChildren(); clearPaymentErrors(); updatePaymentTotal(); });
    allocationRows.addEventListener('change', (event) => { if (event.target.matches('[data-allocation-bill]')) updateAllocation(event.target.closest('tr')); });
    allocationRows.addEventListener('input', updatePaymentTotal);
    allocationRows.addEventListener('click', (event) => { const button = event.target.closest('[data-remove-allocation]'); if (button) { button.closest('tr').remove(); updatePaymentTotal(); } });
    const showPaymentErrors = (result) => {
        showFormMessage('[data-payment-message]', result.message || 'Unable to post vendor payment.');
        Object.entries(result.errors || {}).forEach(([field, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : messages;
            const match = field.match(/^allocations\.(\d+)\.(bill_number|amount)$/);
            if (match) {
                const row = allocationRows.children[Number(match[1])];
                const target = match[2] === 'amount' ? row?.querySelector('[data-allocation-amount-error]') : row?.querySelector('[data-allocation-error]');
                if (target) target.textContent = message;
                return;
            }
            const target = paymentForm.querySelector(`[data-payment-error="${CSS.escape(field)}"]`);
            if (target) target.textContent = message;
        });
    };
    paymentForm.addEventListener('submit', async (event) => {
        event.preventDefault(); clearPaymentErrors();
        if (allocationRows.children.length === 0) { paymentForm.querySelector('[data-payment-error="allocations"]').textContent = 'Add at least one bill.'; return; }
        const allocations = [...allocationRows.querySelectorAll('tr')].map((row) => ({ bill_number: row.querySelector('[data-allocation-bill]').value, amount: row.querySelector('[data-allocation-amount]').value }));
        try {
            validateAllocations(allocations, openBillsFor(paymentForm.elements.vendor_id.value), 'bill_number');
        } catch (problem) {
            showFormMessage('[data-payment-message]', problem.message);
            return;
        }
        const payload = {
            request_token: paymentForm.elements.request_token.value,
            vendor_id: paymentForm.elements.vendor_id.value,
            payment_date: paymentForm.elements.payment_date.value,
            cash_account_code: paymentForm.elements.cash_account_code.value,
            reference: paymentForm.elements.reference.value.trim(), memo: paymentForm.elements.memo.value.trim(),
            allocations,
        };
        const submit = document.querySelector('#ap-payment-submit'); submit.disabled = true;
        try {
            const { response, result } = await fetchJson(editingPayment ? `${page.dataset.paymentUrl}/${encodeURIComponent(editingPayment)}` : page.dataset.paymentUrl, editingPayment ? 'PUT' : 'POST', payload);
            if (!response.ok) { showPaymentErrors(result); submit.disabled = false; return; }
            showFormMessage('[data-payment-message]', result.message, true); window.setTimeout(() => window.location.reload(), 650);
        } catch (_) {
            showFormMessage('[data-payment-message]', 'Network error. Vendor payment was not submitted.'); submit.disabled = false;
        }
    });
    document.querySelector('[data-ap-panel="payments"]')?.addEventListener('click', async (event) => {
        const edit = event.target.closest('[data-ap-edit-payment]');
        if (edit) {
            const payment = payments.find((item) => item.payment_number === edit.dataset.apEditPayment);
            if (payment) editPayment(payment);
            return;
        }
        const action = event.target.closest('[data-ap-payment-action]');
        if (!action || !globalThis.confirm(`${action.dataset.apPaymentAction.replace('-', ' ')} ${action.dataset.paymentNumber}?`)) return;
        const suffix = action.dataset.apPaymentAction === 'delete' ? '' : `/${action.dataset.apPaymentAction}`;
        const { response, result } = await fetchJson(`${page.dataset.paymentUrl}/${encodeURIComponent(action.dataset.paymentNumber)}${suffix}`, action.dataset.apPaymentAction === 'delete' ? 'DELETE' : 'POST', {});
        if (!response.ok) { globalThis.alert(result.message || 'Payment action failed.'); return; }
        window.location.reload();
    });

    const showBillDetail = (bill) => {
        document.querySelector('#ap-detail-title').textContent = bill.bill_number;
        document.querySelector('#ap-detail-subtitle').textContent = `${bill.vendor_name} - ${bill.display_status}`;
        document.querySelector('#ap-detail-content').innerHTML = `<dl class="grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
            <div><dt class="text-slate-500">Vendor reference</dt><dd class="mt-1 font-medium text-slate-800">${escapeHtml(bill.reference)}</dd></div>
            <div><dt class="text-slate-500">Bill date</dt><dd class="mt-1 font-medium text-slate-800">${escapeHtml(bill.bill_date)}</dd></div>
            <div><dt class="text-slate-500">Due date</dt><dd class="mt-1 font-medium text-slate-800">${escapeHtml(bill.due_date)}</dd></div>
            <div><dt class="text-slate-500">Journal</dt><dd class="mt-1 font-medium text-slate-800">${escapeHtml(bill.journal_entry_id || 'Not posted')}</dd></div>
        </dl><div class="mt-5 overflow-x-auto rounded-lg border border-slate-200"><table class="w-full min-w-[540px] text-xs"><thead class="bg-slate-50 text-[10px] text-slate-500 uppercase"><tr><th class="px-3 py-2 text-left">Account</th><th class="px-3 py-2 text-left">Description</th><th class="px-3 py-2 text-right">Subtotal</th><th class="px-3 py-2 text-right">Tax</th></tr></thead><tbody>${bill.lines.map((line) => `<tr class="border-t border-slate-100"><td class="px-3 py-2">${escapeHtml(line.account_name)}</td><td class="px-3 py-2">${escapeHtml(line.description)}</td><td class="px-3 py-2 text-right font-mono">${money.format(Number(line.subtotal))}</td><td class="px-3 py-2 text-right font-mono">${money.format(Number(line.tax))}</td></tr>`).join('')}</tbody></table></div>
        <div class="mt-4 flex flex-col gap-2 text-xs sm:flex-row sm:items-center sm:justify-between"><p class="text-slate-500"><i class="fa-solid fa-paperclip mr-1" aria-hidden="true"></i>${bill.attachment ? escapeHtml(bill.attachment.name) : 'No attachment'}</p><div class="grid grid-cols-2 gap-x-5 gap-y-1 text-right"><span class="text-slate-500">Total</span><strong>${money.format(Number(bill.total))}</strong><span class="text-slate-500">Balance</span><strong>${money.format(Number(bill.remaining_balance))}</strong></div></div>`;
        setModal('detail', true);
    };
    billRows.addEventListener('click', async (event) => {
        const view = event.target.closest('[data-view-bill]');
        if (view) { const bill = bills.find((item) => item.bill_number === view.dataset.viewBill); if (bill) showBillDetail(bill); return; }
        const edit = event.target.closest('[data-edit-bill]');
        if (edit) { const bill = bills.find((item) => item.bill_number === edit.dataset.editBill); if (bill) { resetBillForm(bill); setModal('bill', true); } return; }
        const pay = event.target.closest('[data-pay-bill]');
        if (pay) {
            const bill = bills.find((item) => item.bill_number === pay.dataset.payBill);
            if (bill) { resetPaymentForm(); paymentForm.elements.vendor_id.value = bill.vendor_id; addAllocation(bill.bill_number); setModal('payment', true); }
            return;
        }
        const post = event.target.closest('[data-post-bill]');
        if (!post || !globalThis.confirm(`Post ${post.dataset.postBill}? Posted bills cannot be edited.`)) return;
        post.disabled = true;
        try {
            const bill = bills.find((item) => item.bill_number === post.dataset.postBill);
            const posting = createPosting('vendor-bill', bill, data.accounts || []);
            const { response, result } = await fetchJson(`${page.dataset.billUrl}/${encodeURIComponent(post.dataset.postBill)}/post`, 'POST', { posting });
            if (!response.ok) { globalThis.alert(result.message || 'Unable to post vendor bill.'); post.disabled = false; return; }
            window.location.reload();
        } catch (_) {
            globalThis.alert('Network error. Vendor bill was not posted.'); post.disabled = false;
        }
    });

    const bucketName = (days) => days <= 0 ? 'Current' : days <= 30 ? '1-30 Days' : days <= 60 ? '31-60 Days' : days <= 90 ? '61-90 Days' : 'Over 90 Days';
    const paidThrough = (billNumber, asOf) => payments.filter((payment) => payment.status === 'Posted' && payment.payment_date <= asOf).reduce((total, payment) => total + (payment.allocations || []).filter((allocation) => allocation.bill_number === billNumber).reduce((sum, allocation) => sum + Number(allocation.amount || 0), 0), 0);
    const renderAging = () => {
        const asOf = document.querySelector('#ap-as-of').value;
        const vendorFilter = document.querySelector('#ap-aging-vendor').value;
        const bucketNames = ['Current', '1-30 Days', '31-60 Days', '61-90 Days', 'Over 90 Days'];
        const totals = Object.fromEntries(bucketNames.map((name) => [name, 0]));
        const vendorRows = new Map();
        bills.filter((bill) => bill.status !== 'Draft' && bill.bill_date <= asOf && (!vendorFilter || String(bill.vendor_id) === vendorFilter)).forEach((bill) => {
            const balance = Math.max(0, Number(bill.total) - Number(bill.amount_paid || 0) - paidThrough(bill.bill_number, asOf));
            if (balance <= 0) return;
            const days = Math.floor((new Date(`${asOf}T00:00:00`) - new Date(`${bill.due_date}T00:00:00`)) / 86400000);
            const bucket = bucketName(days); totals[bucket] += balance;
            if (!vendorRows.has(String(bill.vendor_id))) vendorRows.set(String(bill.vendor_id), { name: bill.vendor_name, ...Object.fromEntries(bucketNames.map((name) => [name, 0])) });
            vendorRows.get(String(bill.vendor_id))[bucket] += balance;
        });
        document.querySelector('#ap-aging-cards').innerHTML = bucketNames.map((name, index) => `<article class="apm-summary-card dashboard-enter" style="animation-delay:${index * 50}ms"><p>${name}</p><strong>${money.format(totals[name])}</strong><span>Outstanding balance</span></article>`).join('');
        const rows = [...vendorRows.values()];
        document.querySelector('#ap-aging-rows').innerHTML = rows.length ? rows.map((vendor) => {
            const total = bucketNames.reduce((sum, name) => sum + vendor[name], 0);
            return `<tr class="apm-table-row"><td class="font-medium text-slate-800">${escapeHtml(vendor.name)}</td>${bucketNames.map((name) => `<td class="apm-money text-right">${money.format(vendor[name])}</td>`).join('')}<td class="apm-money text-right font-bold">${money.format(total)}</td></tr>`;
        }).join('') : '<tr><td colspan="7" class="px-5 py-14 text-center text-slate-500">No open vendor bills for selected filters.</td></tr>';
    };
    document.querySelector('#ap-as-of').addEventListener('change', renderAging);
    document.querySelector('#ap-aging-vendor').addEventListener('change', renderAging);

    document.querySelectorAll('[data-ap-close]').forEach((button) => button.addEventListener('click', () => setModal(button.dataset.apClose, false)));
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        ['bill', 'vendor', 'payment', 'detail'].forEach((name) => {
            const modal = document.querySelector(`#ap-${name}-modal`);
            if (!modal.classList.contains('hidden')) setModal(name, false);
        });
    });

    renderBills();
};

document.addEventListener('DOMContentLoaded', setupAccountsPayable);
