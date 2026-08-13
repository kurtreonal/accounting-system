const setupCashBank = () => {
    const page = document.querySelector('#cash-bank-page');
    if (!page) return;

    const data = JSON.parse(document.querySelector('#cb-data')?.textContent || '{}');
    const accounts = data.cashAccounts || [];
    const activeCashAccounts = data.activeCashAccounts || [];
    const postingAccounts = data.postingAccounts || [];
    const transactions = data.transactions || [];
    const reconciliations = data.reconciliations || [];
    const canManage = ['Administrator', 'Accountant'].includes(page.dataset.userRole);
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const pageSize = 8;
    let transactionPage = 1;
    let activityCode = '';

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
    const endpoint = (template, key, marker) => template.replace(marker, encodeURIComponent(key));
    const account = (code) => postingAccounts.find((row) => String(row.code) === String(code)) || accounts.find((row) => String(row.code) === String(code));
    const token = () => globalThis.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => { const number = Math.random() * 16 | 0; return (character === 'x' ? number : (number & 3 | 8)).toString(16); });
    const setModal = (name, open) => {
        const modal = document.querySelector(`#cb-${name}-modal`);
        if (!modal) return;
        modal.classList.toggle('hidden', !open);
        modal.classList.toggle('flex', open);
        modal.setAttribute('aria-hidden', String(!open));
        document.body.classList.toggle('overflow-hidden', open);
    };
    const showMessage = (selector, message, success = false) => {
        const target = document.querySelector(selector);
        if (!target) return;
        target.textContent = message;
        target.className = `mt-3 rounded-lg p-3 text-xs ${success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`;
    };
    const request = async (url, method = 'GET', payload) => {
        const response = await fetch(url, {
            method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: payload === undefined ? undefined : JSON.stringify(payload),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to complete the request.');
            error.errors = result.errors || {};
            throw error;
        }
        return result;
    };
    const cashLabel = (row) => `${row.code} · ${row.name} — ${money.format(Number(row.balance))}`;
    const cashOptions = (includeInactive = false) => (includeInactive ? accounts : activeCashAccounts).map((row) => `<option value="${escapeHtml(row.code)}">${escapeHtml(cashLabel(row))}</option>`).join('');
    const touches = (row, code) => [row.account_code, row.from_account_code, row.to_account_code].map(String).includes(String(code));
    const signedAmount = (row, code) => {
        if (row.cash_effects && Object.hasOwn(row.cash_effects, code)) return Number(row.cash_effects[code]);
        const amount = Number(row.amount || 0);
        if (row.type === 'transfer') return String(row.to_account_code) === String(code) ? amount : -amount;
        if (row.debit !== undefined || row.credit !== undefined) return Number(row.debit || 0) - Number(row.credit || 0);
        if (row.type === 'adjustment') return row.direction === 'increase' ? amount : -amount;
        return ['deposit', 'interest'].includes(row.type) ? amount : -amount;
    };
    const transactionAccountLabel = (row) => row.type === 'transfer'
        ? `${account(row.from_account_code)?.name || row.from_account_code} → ${account(row.to_account_code)?.name || row.to_account_code}`
        : account(row.account_code)?.name || row.account_code || 'Multiple accounts';
    const counterpartLabel = (row) => row.offset_account_name || account(row.offset_account_code)?.name || '';

    const purposeMap = {
        deposit: [
            ['revenue', 'Sales or other revenue', ['Revenue']], ['owner_contribution', 'Owner contribution', ['Equity']],
            ['liability', 'Financing or liability proceeds', ['Liability']], ['asset_recovery', 'Asset recovery or refund', ['Asset']],
        ],
        withdrawal: [
            ['expense', 'Operating expense', ['Expense']], ['asset_purchase', 'Asset purchase', ['Asset']],
            ['liability_payment', 'Liability payment', ['Liability']], ['owner_distribution', 'Owner distribution', ['Equity']],
        ],
        charge: [['bank_charge', 'Bank charge', ['Expense']]],
        interest: [['interest_income', 'Interest income', ['Revenue']]],
        adjustment_increase: [
            ['revenue', 'Revenue correction', ['Revenue']], ['owner_contribution', 'Equity correction', ['Equity']],
            ['liability', 'Liability correction', ['Liability']], ['asset_recovery', 'Asset correction', ['Asset']],
        ],
        adjustment_decrease: [
            ['expense', 'Expense correction', ['Expense']], ['asset_purchase', 'Asset correction', ['Asset']],
            ['liability_payment', 'Liability correction', ['Liability']], ['owner_distribution', 'Equity correction', ['Equity']],
        ],
    };
    const isCashOrBank = (row) => row.type === 'Asset' && ['cash', 'bank'].includes(String(row.sub_type || '').trim().toLowerCase());
    const isControlAccount = (row) => ['receivable', 'payable', 'tax'].some((word) => `${row.name} ${row.sub_type}`.toLowerCase().includes(word));
    const eligibleOffsets = (type, purpose, cashCode, search = '') => {
        const purposeConfig = (purposeMap[type] || []).find(([value]) => value === purpose);
        const allowedTypes = purposeConfig?.[2] || [];
        const needle = search.trim().toLowerCase();
        return postingAccounts.filter((row) => row.status === 'Active' && String(row.code) !== String(cashCode)
            && !isCashOrBank(row) && !isControlAccount(row) && allowedTypes.includes(row.type)
            && (!needle || `${row.code} ${row.name} ${row.type} ${row.sub_type}`.toLowerCase().includes(needle)));
    };
    const offsetOption = (row) => `<option value="${escapeHtml(row.code)}">${escapeHtml(row.code)} · ${escapeHtml(row.name)} · ${escapeHtml(row.type)} / ${escapeHtml(row.sub_type)} · ${escapeHtml(money.format(Number(row.balance)))}</option>`;

    document.querySelectorAll('[data-cb-tab]').forEach((tab) => tab.addEventListener('click', () => {
        document.querySelectorAll('[data-cb-tab]').forEach((item) => {
            const active = item === tab;
            item.setAttribute('aria-selected', String(active));
            item.classList.toggle('border-blue-600', active);
            item.classList.toggle('text-blue-600', active);
        });
        document.querySelectorAll('[data-cb-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.cbPanel !== tab.dataset.cbTab));
    }));

    const renderAccountCards = () => {
        const container = document.querySelector('#cb-account-cards');
        container.innerHTML = accounts.length ? accounts.map((row, index) => {
            const recent = transactions.filter((transaction) => touches(transaction, row.code)).slice(0, 3);
            const statusClass = row.status === 'Active' ? 'apm-active-badge' : 'rounded-md bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-600';
            const statusAction = row.status === 'Active' ? 'deactivate' : 'activate';
            return `<article class="dashboard-enter rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md" style="animation-delay:${index * 45}ms">
                <div class="flex items-start justify-between gap-3"><div class="flex min-w-0 gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-lg ${row.sub_type === 'Bank' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600'}"><i class="fa-solid ${row.sub_type === 'Bank' ? 'fa-building-columns' : 'fa-wallet'}"></i></span><div class="min-w-0"><h3 class="truncate text-sm font-semibold text-slate-800">${escapeHtml(row.name)}</h3><p class="mt-0.5 text-[10px] text-slate-500">${escapeHtml(row.sub_type)} · ${escapeHtml(row.code)}</p></div></div><span class="${statusClass}">${escapeHtml(row.status)}</span></div>
                <p class="mt-5 text-[10px] uppercase tracking-wide text-slate-400">Posted balance</p><strong class="mt-1 block font-mono text-xl ${Number(row.balance) < 0 ? 'text-red-600' : 'text-slate-900'}">${escapeHtml(money.format(Number(row.balance)))}</strong>
                <div class="mt-4 border-t border-slate-100 pt-3"><p class="text-[10px] font-semibold uppercase text-slate-400">Recent activity</p>${recent.length ? recent.map((item) => { const effect = signedAmount(item, row.code); return `<div class="mt-2 flex items-center justify-between text-[11px]"><span class="truncate pr-3 text-slate-600">${escapeHtml(item.description)}</span><span class="font-mono ${effect >= 0 ? 'text-emerald-600' : 'text-red-600'}">${effect >= 0 ? '+' : ''}${escapeHtml(money.format(effect))}</span></div>`; }).join('') : '<p class="mt-2 text-[11px] text-slate-400">No posted activity.</p>'}</div>
                <div class="mt-4 flex flex-wrap gap-1.5 border-t border-slate-100 pt-3"><button type="button" data-account-action="activity" data-code="${escapeHtml(row.code)}" class="apm-row-action">View Activity</button>${canManage ? `<button type="button" data-account-action="edit" data-code="${escapeHtml(row.code)}" class="apm-row-action">Edit</button><button type="button" data-account-action="adjust" data-code="${escapeHtml(row.code)}" class="apm-row-action" ${row.status !== 'Active' ? 'disabled' : ''}>Adjust</button><button type="button" data-account-action="${statusAction}" data-code="${escapeHtml(row.code)}" class="apm-row-action">${statusAction === 'activate' ? 'Activate' : 'Deactivate'}</button>` : ''}</div>
            </article>`;
        }).join('') : '<div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center text-sm text-slate-500">No actual Cash or Bank accounts exist in Chart of Accounts.</div>';
    };

    const filters = ['#cb-search', '#cb-account-filter', '#cb-type-filter', '#cb-status-filter', '#cb-date-from', '#cb-date-to'];
    const filteredTransactions = () => {
        const search = document.querySelector('#cb-search').value.trim().toLowerCase();
        const code = document.querySelector('#cb-account-filter').value;
        const type = document.querySelector('#cb-type-filter').value;
        const status = document.querySelector('#cb-status-filter').value;
        const from = document.querySelector('#cb-date-from').value;
        const to = document.querySelector('#cb-date-to').value;
        return transactions.filter((row) => {
            const haystack = `${row.reference || ''} ${row.description || ''} ${row.transaction_number || ''} ${row.journal_entry_id || ''} ${row.source_label || ''}`.toLowerCase();
            return (!search || haystack.includes(search)) && (!code || touches(row, code)) && (!type || row.type === type)
                && (!status || row.status === status) && (!from || row.date >= from) && (!to || row.date <= to);
        });
    };
    const updateExport = () => {
        const url = new URL(page.dataset.exportUrl, window.location.origin);
        const map = { search: '#cb-search', account: '#cb-account-filter', type: '#cb-type-filter', status: '#cb-status-filter', date_from: '#cb-date-from', date_to: '#cb-date-to' };
        Object.entries(map).forEach(([key, selector]) => { const value = document.querySelector(selector).value.trim(); if (value) url.searchParams.set(key, value); });
        document.querySelector('#cb-export').href = url.toString();
    };
    const renderPagination = (count) => {
        const totalPages = Math.max(1, Math.ceil(count / pageSize));
        transactionPage = Math.min(transactionPage, totalPages);
        const container = document.querySelector('#cb-pagination');
        container.innerHTML = '';
        if (totalPages <= 1) return;
        const add = (label, target, disabled, active = false) => {
            const button = document.createElement('button'); button.type = 'button'; button.textContent = label; button.disabled = disabled;
            button.className = `apm-page-button ${active ? 'border-blue-600 bg-blue-600 text-white' : ''}`;
            button.addEventListener('click', () => { transactionPage = target; renderTransactions(); }); container.append(button);
        };
        add('Prev', transactionPage - 1, transactionPage === 1);
        for (let number = 1; number <= totalPages; number += 1) add(String(number), number, false, number === transactionPage);
        add('Next', transactionPage + 1, transactionPage === totalPages);
    };
    const renderTransactions = () => {
        const filtered = filteredTransactions();
        const start = (transactionPage - 1) * pageSize;
        const visible = filtered.slice(start, start + pageSize);
        document.querySelector('#cb-transaction-rows').innerHTML = visible.length ? visible.map((row) => {
            const canReverse = canManage && row.id !== null && ['Cash/Bank', 'Bank Transfer', 'Cash Adjustment'].includes(row.source_type) && row.status === 'Uncleared' && !row.reversal_of;
            return `<tr class="apm-table-row"><td>${escapeHtml(row.date)}</td><td><p class="font-medium text-slate-800">${escapeHtml(row.reference || row.transaction_number)}</p><p class="text-[10px] text-slate-400">${escapeHtml(row.journal_entry_id || '')}</p></td><td><span class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-medium capitalize text-slate-600">${escapeHtml(String(row.type).replaceAll('_', ' '))}</span><p class="mt-1 text-[10px] text-slate-500">${escapeHtml(row.source_label || row.source_type)}</p></td><td>${escapeHtml(transactionAccountLabel(row))}</td><td>${escapeHtml(counterpartLabel(row) || '—')}</td><td class="max-w-64"><p class="truncate">${escapeHtml(row.description)}</p></td><td class="apm-money text-right">${escapeHtml(money.format(Number(row.amount)))}</td><td class="text-center"><span class="rounded-full px-2 py-1 text-[10px] font-semibold ${row.status === 'Cleared' ? 'bg-emerald-100 text-emerald-700' : row.status === 'Reversed' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-700'}">${escapeHtml(row.status)}</span></td><td class="text-right"><button type="button" class="apm-row-action" data-view-movement="${escapeHtml(row.movement_id)}">View</button>${canReverse ? `<button type="button" class="apm-row-action text-red-600" data-reverse-transaction="${escapeHtml(row.id)}">Reverse</button>` : ''}</td></tr>`;
        }).join('') : '<tr><td colspan="9" class="px-4 py-14 text-center text-slate-500">No posted cash movements match the selected filters.</td></tr>';
        document.querySelector('#cb-transaction-count').textContent = filtered.length ? `Showing ${start + 1}–${Math.min(start + visible.length, filtered.length)} of ${filtered.length} movements` : 'Showing 0 movements';
        renderPagination(filtered.length); updateExport();
    };
    filters.forEach((selector) => document.querySelector(selector).addEventListener('input', () => { transactionPage = 1; renderTransactions(); }));

    const transactionForm = document.querySelector('#cb-transaction-form');
    ['account_code', 'from_account_code', 'to_account_code'].forEach((name) => { transactionForm.elements[name].innerHTML = `<option value="">Select an actual account</option>${cashOptions()}`; });
    const titles = { deposit: 'Record Deposit', withdrawal: 'Record Withdrawal', transfer: 'Transfer Funds', charge: 'Record Bank Charge', interest: 'Record Bank Interest' };
    const renderPurposeOptions = (select, type) => { select.innerHTML = `<option value="">Select purpose</option>${(purposeMap[type] || []).map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('')}`; };
    const renderTransactionOffsets = () => {
        const type = transactionForm.elements.type.value; const purpose = transactionForm.elements.purpose.value;
        const cashCode = transactionForm.elements.account_code.value; const search = transactionForm.elements.offset_search.value;
        const current = transactionForm.elements.offset_account_code.value;
        const options = eligibleOffsets(type, purpose, cashCode, search);
        transactionForm.elements.offset_account_code.innerHTML = options.map(offsetOption).join('');
        if (options.some((row) => String(row.code) === String(current))) transactionForm.elements.offset_account_code.value = current;
        const empty = document.querySelector('#cb-offset-empty');
        empty.classList.toggle('hidden', options.length > 0 || !purpose);
        empty.innerHTML = options.length || !purpose ? '' : `No eligible actual account exists. <a class="font-semibold underline" href="${escapeHtml(page.dataset.chartUrl)}">Open Chart of Accounts</a>.`;
        document.querySelector('#cb-transaction-submit').disabled = type !== 'transfer' && options.length === 0;
        renderTransactionPreview();
    };
    const previewLine = (label, accountRow, debit, credit, after) => `<div class="grid grid-cols-[1fr_auto_auto] gap-3 border-t border-slate-200 py-2 first:border-0"><span><strong class="text-slate-800">${escapeHtml(accountRow?.code || '')} · ${escapeHtml(accountRow?.name || label)}</strong>${after === null ? '' : `<small class="block text-slate-500">After: ${escapeHtml(money.format(after))}</small>`}</span><span class="font-mono text-emerald-600">${debit ? `Dr ${escapeHtml(money.format(debit))}` : ''}</span><span class="font-mono text-red-600">${credit ? `Cr ${escapeHtml(money.format(credit))}` : ''}</span></div>`;
    const renderTransactionPreview = () => {
        const type = transactionForm.elements.type.value; const amount = Number(transactionForm.elements.amount.value || 0); const preview = document.querySelector('#cb-transaction-preview');
        if (type === 'transfer') {
            const from = account(transactionForm.elements.from_account_code.value); const to = account(transactionForm.elements.to_account_code.value);
            preview.innerHTML = `<p class="mb-2 font-semibold text-slate-700">Posting preview</p>${previewLine('Destination', to, amount, 0, to ? Number(to.balance) + amount : null)}${previewLine('Source', from, 0, amount, from ? Number(from.balance) - amount : null)}`;
            return;
        }
        const cash = account(transactionForm.elements.account_code.value); const offset = account(transactionForm.elements.offset_account_code.value);
        const inflow = ['deposit', 'interest'].includes(type);
        preview.innerHTML = `<p class="mb-2 font-semibold text-slate-700">Posting preview</p>${previewLine('Cash / bank', cash, inflow ? amount : 0, inflow ? 0 : amount, cash ? Number(cash.balance) + (inflow ? amount : -amount) : null)}${previewLine('Offset', offset, inflow ? 0 : amount, inflow ? amount : 0, offset ? Number(offset.balance) + (['Asset', 'Expense'].includes(offset.type) ? (inflow ? -amount : amount) : (inflow ? amount : -amount)) : null)}`;
    };
    const openTransaction = (type) => {
        transactionForm.reset(); transactionForm.elements.request_token.value = token(); transactionForm.elements.type.value = type;
        transactionForm.elements.date.value = new Date().toISOString().slice(0, 10); document.querySelector('#cb-transaction-title').textContent = titles[type];
        renderPurposeOptions(transactionForm.elements.purpose, type);
        document.querySelectorAll('[data-standard-account], [data-purpose-field], [data-offset-field]').forEach((row) => row.classList.toggle('hidden', type === 'transfer'));
        document.querySelectorAll('[data-transfer-account]').forEach((row) => row.classList.toggle('hidden', type !== 'transfer'));
        document.querySelector('[data-transaction-message]').classList.add('hidden'); renderTransactionOffsets(); renderTransactionPreview(); setModal('transaction', true);
    };
    document.querySelectorAll('[data-open-transaction]').forEach((button) => button.addEventListener('click', () => openTransaction(button.dataset.openTransaction)));
    ['account_code', 'purpose', 'offset_account_code', 'amount', 'from_account_code', 'to_account_code'].forEach((name) => transactionForm.elements[name].addEventListener('input', name === 'account_code' || name === 'purpose' ? renderTransactionOffsets : renderTransactionPreview));
    transactionForm.elements.offset_search.addEventListener('input', renderTransactionOffsets);
    transactionForm.addEventListener('submit', async (event) => {
        event.preventDefault(); if (!transactionForm.reportValidity()) return;
        const submit = document.querySelector('#cb-transaction-submit'); submit.disabled = true;
        const payload = Object.fromEntries(new FormData(transactionForm)); delete payload.offset_search; payload.amount = Number(payload.amount);
        if (!window.confirm('Post this balanced transaction? Posted transactions cannot be edited.')) { submit.disabled = false; return; }
        try { const result = await request(page.dataset.transactionUrl, 'POST', payload); showMessage('[data-transaction-message]', result.message, true); window.setTimeout(() => location.reload(), 450); }
        catch (error) { showMessage('[data-transaction-message]', error.message); submit.disabled = false; }
    });

    const accountForm = document.querySelector('#cb-account-form');
    const openAccountForm = (row = null) => {
        accountForm.reset(); accountForm.elements.code.value = row?.code || ''; accountForm.elements.name.value = row?.name || ''; accountForm.elements.kind.value = row?.sub_type || 'Bank';
        document.querySelector('#cb-account-title').textContent = row ? 'Edit cash or bank account' : 'Add cash or bank account';
        document.querySelector('#cb-account-code').textContent = row ? `Account code ${row.code} · Posted balance ${money.format(Number(row.balance))}` : 'New accounts always start at zero.';
        document.querySelector('[data-account-message]').classList.add('hidden'); setModal('account', true);
    };
    document.querySelector('#cb-add-account').addEventListener('click', () => openAccountForm());
    accountForm.addEventListener('submit', async (event) => {
        event.preventDefault(); if (!accountForm.reportValidity()) return;
        const submit = accountForm.querySelector('[type="submit"]'); submit.disabled = true;
        const payload = Object.fromEntries(new FormData(accountForm)); const code = payload.code; delete payload.code;
        try { const result = await request(code ? endpoint(page.dataset.accountUpdateUrl, code, '__CODE__') : page.dataset.accountUrl, code ? 'PUT' : 'POST', payload); showMessage('[data-account-message]', result.message, true); window.setTimeout(() => location.reload(), 450); }
        catch (error) { showMessage('[data-account-message]', error.message); submit.disabled = false; }
    });

    const adjustForm = document.querySelector('#cb-adjust-form');
    const renderAdjustPurpose = () => {
        const type = `adjustment_${adjustForm.elements.direction.value}`; const previous = adjustForm.elements.purpose.value;
        renderPurposeOptions(adjustForm.elements.purpose, type); if ([...adjustForm.elements.purpose.options].some((option) => option.value === previous)) adjustForm.elements.purpose.value = previous;
        renderAdjustOffsets();
    };
    const renderAdjustOffsets = () => {
        const type = `adjustment_${adjustForm.elements.direction.value}`; const code = adjustForm.elements.account_code.value;
        const options = eligibleOffsets(type, adjustForm.elements.purpose.value, code, adjustForm.elements.offset_search.value); const current = adjustForm.elements.offset_account_code.value;
        adjustForm.elements.offset_account_code.innerHTML = options.map(offsetOption).join(''); if (options.some((row) => String(row.code) === String(current))) adjustForm.elements.offset_account_code.value = current;
        const empty = document.querySelector('#cb-adjust-offset-empty'); empty.classList.toggle('hidden', options.length > 0 || !adjustForm.elements.purpose.value);
        empty.innerHTML = options.length || !adjustForm.elements.purpose.value ? '' : `No eligible actual account exists. <a class="font-semibold underline" href="${escapeHtml(page.dataset.chartUrl)}">Open Chart of Accounts</a>.`;
        adjustForm.querySelector('[type="submit"]').disabled = options.length === 0; renderAdjustPreview();
    };
    const renderAdjustPreview = () => {
        const cash = account(adjustForm.elements.account_code.value); const offset = account(adjustForm.elements.offset_account_code.value); const amount = Number(adjustForm.elements.amount.value || 0); const increase = adjustForm.elements.direction.value === 'increase';
        document.querySelector('#cb-adjust-preview').innerHTML = `<p class="mb-2 font-semibold text-slate-700">Controlled adjustment preview</p><div class="mb-2 grid grid-cols-3 gap-2 text-center"><span class="rounded bg-white p-2"><small class="block text-slate-500">Current</small><strong>${money.format(Number(cash?.balance || 0))}</strong></span><span class="rounded bg-white p-2"><small class="block text-slate-500">Adjustment</small><strong class="${increase ? 'text-emerald-600' : 'text-red-600'}">${increase ? '+' : '-'}${money.format(amount)}</strong></span><span class="rounded bg-white p-2"><small class="block text-slate-500">Result</small><strong>${money.format(Number(cash?.balance || 0) + (increase ? amount : -amount))}</strong></span></div>${previewLine('Cash / bank', cash, increase ? amount : 0, increase ? 0 : amount, cash ? Number(cash.balance) + (increase ? amount : -amount) : null)}${previewLine('Offset', offset, increase ? 0 : amount, increase ? amount : 0, null)}`;
    };
    const openAdjustment = (row) => {
        adjustForm.reset(); adjustForm.elements.request_token.value = token(); adjustForm.elements.account_code.value = row.code; adjustForm.elements.date.value = new Date().toISOString().slice(0, 10);
        document.querySelector('#cb-adjust-account').textContent = `${row.code} · ${row.name} · Current ${money.format(Number(row.balance))}`; document.querySelector('[data-adjust-message]').classList.add('hidden'); renderAdjustPurpose(); setModal('adjust', true);
    };
    adjustForm.elements.direction.addEventListener('change', renderAdjustPurpose); adjustForm.elements.purpose.addEventListener('change', renderAdjustOffsets); adjustForm.elements.offset_search.addEventListener('input', renderAdjustOffsets); adjustForm.elements.offset_account_code.addEventListener('change', renderAdjustPreview); adjustForm.elements.amount.addEventListener('input', renderAdjustPreview);
    adjustForm.addEventListener('submit', async (event) => {
        event.preventDefault(); if (!adjustForm.reportValidity()) return; const submit = adjustForm.querySelector('[type="submit"]'); submit.disabled = true;
        const payload = Object.fromEntries(new FormData(adjustForm)); const code = payload.account_code; delete payload.account_code; delete payload.offset_search; payload.amount = Number(payload.amount);
        if (!window.confirm('Post this balance adjustment? It will create a journal entry and cannot be edited.')) { submit.disabled = false; return; }
        try { const result = await request(endpoint(page.dataset.adjustUrl, code, '__CODE__'), 'POST', payload); showMessage('[data-adjust-message]', result.message, true); window.setTimeout(() => location.reload(), 450); }
        catch (error) { showMessage('[data-adjust-message]', error.message); submit.disabled = false; }
    });

    const loadActivity = async () => {
        if (!activityCode) return; const url = new URL(endpoint(page.dataset.activityUrl, activityCode, '__CODE__'), window.location.origin);
        const search = document.querySelector('#cb-activity-search').value.trim(); const from = document.querySelector('#cb-activity-from').value; const to = document.querySelector('#cb-activity-to').value;
        if (search) url.searchParams.set('search', search); if (from) url.searchParams.set('date_from', from); if (to) url.searchParams.set('date_to', to);
        document.querySelector('#cb-activity-rows').innerHTML = '<tr><td colspan="7" class="p-10 text-center text-slate-500">Loading activity…</td></tr>';
        try {
            const result = await request(url.toString()); document.querySelector('#cb-activity-title').textContent = `${result.account.code} · ${result.account.name}`;
            document.querySelector('#cb-activity-summary').textContent = `Beginning ${money.format(Number(result.beginning_balance))} · Ending ${money.format(Number(result.ending_balance))}`;
            document.querySelector('#cb-activity-rows').innerHTML = result.rows.length ? result.rows.map((row) => `<tr class="apm-table-row"><td>${escapeHtml(row.date)}</td><td><p class="font-medium text-slate-800">${escapeHtml(row.reference || row.transaction_number)}</p><p class="text-[10px] text-slate-400">${escapeHtml(row.journal_entry_id)}</p></td><td><a href="${escapeHtml(row.source_url)}" class="text-blue-600 hover:underline">${escapeHtml(row.source_label)}</a></td><td>${escapeHtml(row.counterpart || '—')}</td><td class="apm-money text-right text-emerald-600">${row.debit ? escapeHtml(money.format(Number(row.debit))) : '—'}</td><td class="apm-money text-right text-red-600">${row.credit ? escapeHtml(money.format(Number(row.credit))) : '—'}</td><td class="apm-money text-right">${escapeHtml(money.format(Number(row.running_balance)))}</td></tr>`).join('') : '<tr><td colspan="7" class="p-10 text-center text-slate-500">No posted activity matches these filters.</td></tr>';
        } catch (error) { document.querySelector('#cb-activity-rows').innerHTML = `<tr><td colspan="7" class="p-10 text-center text-red-600">${escapeHtml(error.message)}</td></tr>`; }
    };
    ['#cb-activity-search', '#cb-activity-from', '#cb-activity-to'].forEach((selector) => document.querySelector(selector).addEventListener('input', loadActivity));

    document.querySelector('#cb-account-cards').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-account-action]'); if (!button) return; const row = accounts.find((item) => String(item.code) === String(button.dataset.code)); if (!row) return;
        const action = button.dataset.accountAction;
        if (action === 'edit') { openAccountForm(row); return; }
        if (action === 'adjust') { openAdjustment(row); return; }
        if (action === 'activity') { activityCode = row.code; document.querySelector('#cb-activity-search').value = ''; document.querySelector('#cb-activity-from').value = ''; document.querySelector('#cb-activity-to').value = ''; setModal('activity', true); loadActivity(); return; }
        const nextStatus = action === 'activate' ? 'Active' : 'Inactive';
        if (!window.confirm(`${nextStatus === 'Active' ? 'Activate' : 'Deactivate'} ${row.name}?`)) return;
        button.disabled = true;
        try { await request(endpoint(page.dataset.accountStatusUrl, row.code, '__CODE__'), 'PATCH', { status: nextStatus }); location.reload(); }
        catch (error) { window.alert(error.message); button.disabled = false; }
    });

    document.querySelector('#cb-transaction-rows').addEventListener('click', async (event) => {
        const viewButton = event.target.closest('[data-view-movement]');
        if (viewButton) {
            const row = transactions.find((item) => item.movement_id === viewButton.dataset.viewMovement); if (!row) return;
            const fields = [['Date', row.date], ['Reference', row.reference || row.transaction_number], ['Journal', row.journal_entry_id || 'Not linked'], ['Type', String(row.type).replaceAll('_', ' ')], ['Source', row.source_label], ['Account', transactionAccountLabel(row)], ['Counterpart', counterpartLabel(row) || 'Multiple or none'], ['Amount', money.format(Number(row.amount))], ['Status', row.status], ['Description', row.description]];
            document.querySelector('#cb-detail-content').innerHTML = `<dl class="grid gap-3 sm:grid-cols-2">${fields.map(([label, value]) => `<div class="rounded-lg bg-slate-50 p-3 ${label === 'Description' ? 'sm:col-span-2' : ''}"><dt class="text-[10px] uppercase text-slate-500">${escapeHtml(label)}</dt><dd class="mt-1 text-xs font-medium text-slate-800">${escapeHtml(value)}</dd></div>`).join('')}</dl>${row.source_url ? `<a href="${escapeHtml(row.source_url)}" class="mt-4 inline-flex text-xs font-semibold text-blue-600 hover:underline">Open source module</a>` : ''}`; setModal('detail', true); return;
        }
        const reverseButton = event.target.closest('[data-reverse-transaction]'); if (!reverseButton || !window.confirm('Reverse this transaction with an offsetting posted journal?')) return;
        reverseButton.disabled = true;
        try { await request(endpoint(page.dataset.reverseUrl, reverseButton.dataset.reverseTransaction, '__ID__'), 'POST', {}); location.reload(); }
        catch (error) { window.alert(error.message); reverseButton.disabled = false; }
    });

    const reconciliationForm = document.querySelector('#cb-reconciliation-form');
    const renderReconciliation = () => {
        const code = reconciliationForm.elements.account_code.value; const selectedAccount = account(code); const statementDate = reconciliationForm.elements.statement_date.value;
        const uncleared = transactions.filter((row) => !row.cleared && row.status !== 'Reversed' && touches(row, code) && (!statementDate || row.date <= statementDate));
        const checked = [...document.querySelectorAll('#cb-reconcile-rows input:checked')].map((input) => input.value);
        const statement = Number(reconciliationForm.elements.statement_balance.value || 0); const book = Number(selectedAccount?.balance || 0);
        const unclearedAmount = uncleared.filter((row) => !checked.includes(row.movement_id)).reduce((sum, row) => sum + signedAmount(row, code), 0); const difference = statement + unclearedAmount - book;
        document.querySelector('#cb-reconciliation-summary').innerHTML = [['Book balance', book], ['Statement balance', statement], ['Uncleared items', unclearedAmount], ['Difference', difference]].map(([name, value]) => `<div class="rounded-lg bg-slate-50 p-3"><p class="text-[10px] text-slate-500">${name}</p><strong class="mt-1 block font-mono text-xs ${name === 'Difference' && Math.abs(value) > 0.004 ? 'text-red-600' : 'text-slate-800'}">${escapeHtml(money.format(value))}</strong></div>`).join('');
        document.querySelector('#cb-reconcile-rows').innerHTML = !code ? '<tr><td colspan="6" class="p-8 text-center text-slate-500">Select an account to begin.</td></tr>' : uncleared.length ? uncleared.map((row) => { const effect = signedAmount(row, code); return `<tr class="border-t border-slate-100"><td class="p-2"><input type="checkbox" name="movement_ids[]" value="${escapeHtml(row.movement_id)}" ${checked.includes(row.movement_id) ? 'checked' : ''} class="size-4 rounded border-slate-300"></td><td class="p-2">${escapeHtml(row.date)}</td><td class="p-2">${escapeHtml(row.reference || row.transaction_number)}</td><td class="p-2">${escapeHtml(row.source_label)}</td><td class="p-2">${escapeHtml(row.description)}</td><td class="p-2 text-right font-mono ${effect >= 0 ? 'text-emerald-600' : 'text-red-600'}">${escapeHtml(money.format(effect))}</td></tr>`; }).join('') : '<tr><td colspan="6" class="p-8 text-center text-slate-500">No uncleared posted movements for this account and date.</td></tr>';
        document.querySelector('#cb-reconcile-submit').disabled = !canManage || !code || Math.abs(difference) > 0.004;
    };
    reconciliationForm.elements.account_code.addEventListener('change', () => { reconciliationForm.elements.statement_balance.value = ''; renderReconciliation(); }); reconciliationForm.elements.statement_date.addEventListener('input', renderReconciliation); reconciliationForm.elements.statement_balance.addEventListener('input', renderReconciliation); document.querySelector('#cb-reconcile-rows').addEventListener('change', renderReconciliation);
    reconciliationForm.addEventListener('submit', async (event) => {
        event.preventDefault(); if (!reconciliationForm.reportValidity()) return; const submit = document.querySelector('#cb-reconcile-submit'); submit.disabled = true; const form = new FormData(reconciliationForm);
        const payload = { account_code: form.get('account_code'), statement_date: form.get('statement_date'), statement_balance: Number(form.get('statement_balance')), notes: form.get('notes'), movement_ids: form.getAll('movement_ids[]') };
        if (!window.confirm('Complete this balanced reconciliation? Cleared movements will be locked.')) { renderReconciliation(); return; }
        try { const result = await request(page.dataset.reconciliationUrl, 'POST', payload); showMessage('[data-reconciliation-message]', result.message, true); window.setTimeout(() => location.reload(), 450); }
        catch (error) { showMessage('[data-reconciliation-message]', error.message); renderReconciliation(); }
    });
    document.querySelector('#cb-reconciliation-history').innerHTML = reconciliations.length ? reconciliations.map((row) => `<article class="rounded-lg border border-slate-200 p-3"><div class="flex justify-between"><div><p class="text-xs font-semibold text-slate-800">${escapeHtml(row.reference)}</p><p class="mt-1 text-[10px] text-slate-500">${escapeHtml(account(row.account_code)?.name || row.account_code)} · ${escapeHtml(row.statement_date)}</p></div><span class="apm-active-badge">Balanced</span></div><dl class="mt-3 grid grid-cols-2 gap-2 text-[11px]"><div><dt class="text-slate-500">Statement</dt><dd class="font-mono">${escapeHtml(money.format(Number(row.statement_balance)))}</dd></div><div><dt class="text-slate-500">Cleared items</dt><dd>${(row.movement_ids || row.transaction_ids || []).length}</dd></div></dl>${row.notes ? `<p class="mt-2 text-[11px] text-slate-500">${escapeHtml(row.notes)}</p>` : ''}</article>`).join('') : '<p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-xs text-slate-500">No reconciliation history yet.</p>';

    document.querySelectorAll('[data-cb-close]').forEach((button) => button.addEventListener('click', () => setModal(button.dataset.cbClose, false)));
    renderAccountCards(); renderTransactions(); renderReconciliation();
};

document.addEventListener('DOMContentLoaded', setupCashBank);
