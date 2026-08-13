const setupCashBank = () => {
    const page = document.querySelector('#cash-bank-page');
    if (!page) return;

    const data = JSON.parse(document.querySelector('#cb-data').textContent);
    const accounts = data.cashAccounts || [];
    const postingAccounts = data.postingAccounts || [];
    const transactions = data.transactions || [];
    const reconciliations = data.reconciliations || [];
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);
    const account = (code) => accounts.find((row) => String(row.code) === String(code));
    const label = (row) => row.type === 'transfer' ? `${account(row.from_account_code)?.name || row.from_account_code} → ${account(row.to_account_code)?.name || row.to_account_code}` : account(row.account_code)?.name || row.account_code;
    const signedAmount = (row, code = '') => {
        const amount = Number(row.amount || 0);
        if (row.type === 'transfer') return String(row.to_account_code) === String(code) ? amount : -amount;
        return ['deposit', 'interest'].includes(row.type) ? amount : -amount;
    };
    const showMessage = (selector, message, success = false) => {
        const target = document.querySelector(selector); target.textContent = message; target.className = `mt-3 rounded-lg p-3 text-xs ${success ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
    };
    const fetchJson = async (url, payload) => {
        const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify(payload) });
        return { response, result: await response.json() };
    };
    const token = () => globalThis.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => { const number = Math.random() * 16 | 0; return (char === 'x' ? number : (number & 3 | 8)).toString(16); });
    const setModal = (name, open) => { const modal = document.querySelector(`#cb-${name}-modal`); modal.classList.toggle('hidden', !open); modal.classList.toggle('flex', open); modal.setAttribute('aria-hidden', String(!open)); };

    document.querySelectorAll('[data-cb-tab]').forEach((tab) => tab.addEventListener('click', () => {
        document.querySelectorAll('[data-cb-tab]').forEach((item) => { const active = item === tab; item.setAttribute('aria-selected', String(active)); item.classList.toggle('border-blue-600', active); item.classList.toggle('text-blue-600', active); });
        document.querySelectorAll('[data-cb-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.cbPanel !== tab.dataset.cbTab));
    }));

    document.querySelector('#cb-account-cards').innerHTML = accounts.length ? accounts.map((row, index) => {
        const recent = transactions.filter((transaction) => String(transaction.account_code) === String(row.code) || String(transaction.from_account_code) === String(row.code) || String(transaction.to_account_code) === String(row.code)).slice(0, 3);
        return `<article class="dashboard-enter rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md" style="animation-delay:${index * 45}ms"><div class="flex items-start justify-between"><div class="flex gap-3"><span class="grid size-9 place-items-center rounded-lg ${row.sub_type === 'Bank' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600'}"><i class="fa-solid ${row.sub_type === 'Bank' ? 'fa-building-columns' : 'fa-wallet'}"></i></span><div><h3 class="text-sm font-semibold text-slate-800">${escapeHtml(row.name)}</h3><p class="mt-0.5 text-[10px] text-slate-500">${escapeHtml(row.sub_type)} · ${escapeHtml(row.code)}</p></div></div><span class="apm-active-badge">Active</span></div><p class="mt-5 text-[10px] uppercase tracking-wide text-slate-400">Current balance</p><strong class="mt-1 block font-mono text-xl text-slate-900">${money.format(Number(row.balance))}</strong><div class="mt-4 border-t border-slate-100 pt-3"><p class="text-[10px] font-semibold uppercase text-slate-400">Recent activity</p>${recent.length ? recent.map((item) => `<div class="mt-2 flex items-center justify-between text-[11px]"><span class="truncate pr-3 text-slate-600">${escapeHtml(item.description)}</span><span class="font-mono ${signedAmount(item, row.code) >= 0 ? 'text-emerald-600' : 'text-rose-600'}">${signedAmount(item, row.code) >= 0 ? '+' : ''}${money.format(signedAmount(item, row.code))}</span></div>`).join('') : '<p class="mt-2 text-[11px] text-slate-400">No recent transactions.</p>'}</div></article>`;
    }).join('') : '<div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center text-sm text-slate-500">No cash or bank accounts yet.</div>';

    const renderTransactions = () => {
        const search = document.querySelector('#cb-search').value.trim().toLowerCase();
        const accountCode = document.querySelector('#cb-account-filter').value;
        const type = document.querySelector('#cb-type-filter').value;
        const rows = transactions.filter((row) => (!search || `${row.reference} ${row.description} ${row.transaction_number}`.toLowerCase().includes(search)) && (!type || row.type === type) && (!accountCode || [row.account_code, row.from_account_code, row.to_account_code].map(String).includes(accountCode)));
        document.querySelector('#cb-transaction-rows').innerHTML = rows.length ? rows.map((row) => `<tr class="apm-table-row"><td>${escapeHtml(row.date)}</td><td><p class="font-medium text-slate-800">${escapeHtml(row.reference || row.transaction_number)}</p><p class="text-[10px] text-slate-400">${escapeHtml(row.journal_entry_id || '')}</p></td><td><span class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-medium capitalize text-slate-600">${escapeHtml(row.type.replace('_', ' '))}</span></td><td>${escapeHtml(label(row))}</td><td>${escapeHtml(row.description)}</td><td class="apm-money text-right ${['deposit', 'interest'].includes(row.type) ? 'text-emerald-600' : 'text-slate-800'}">${money.format(Number(row.amount))}</td><td class="text-center"><span class="rounded-full px-2 py-1 text-[10px] font-semibold ${row.cleared ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">${row.cleared ? 'Cleared' : 'Uncleared'}</span></td></tr>`).join('') : '<tr><td colspan="7" class="px-4 py-14 text-center text-slate-500">No transactions match the selected filters.</td></tr>';
    };
    ['#cb-search', '#cb-account-filter', '#cb-type-filter'].forEach((selector) => document.querySelector(selector).addEventListener('input', renderTransactions));

    const cashOptions = `<option value="">Select account</option>${accounts.map((row) => `<option value="${escapeHtml(row.code)}">${escapeHtml(row.name)} — ${money.format(Number(row.balance))}</option>`).join('')}`;
    const postingOptions = `<option value="">Select offset account</option>${postingAccounts.map((row) => `<option value="${escapeHtml(row.code)}">${escapeHtml(row.code)} · ${escapeHtml(row.name)}</option>`).join('')}`;
    ['account_code', 'from_account_code', 'to_account_code'].forEach((name) => { document.querySelector(`#cb-transaction-form [name="${name}"]`).innerHTML = cashOptions; });
    document.querySelector('#cb-transaction-form [name="offset_account_code"]').innerHTML = postingOptions;
    const transactionForm = document.querySelector('#cb-transaction-form');
    const titles = { deposit: 'Record Deposit', withdrawal: 'Record Withdrawal', transfer: 'Transfer Funds', charge: 'Record Bank Charge', interest: 'Record Bank Interest' };
    const openTransaction = (type) => { transactionForm.reset(); transactionForm.elements.request_token.value = token(); transactionForm.elements.type.value = type; transactionForm.elements.date.value = new Date().toISOString().slice(0, 10); document.querySelector('#cb-transaction-title').textContent = titles[type]; document.querySelectorAll('[data-standard-account]').forEach((row) => row.classList.toggle('hidden', type === 'transfer')); document.querySelectorAll('[data-transfer-account]').forEach((row) => row.classList.toggle('hidden', type !== 'transfer')); document.querySelector('[data-transaction-message]').classList.add('hidden'); setModal('transaction', true); };
    document.querySelectorAll('[data-open-transaction]').forEach((button) => button.addEventListener('click', () => openTransaction(button.dataset.openTransaction)));
    transactionForm.addEventListener('submit', async (event) => {
        event.preventDefault(); const submit = document.querySelector('#cb-transaction-submit'); submit.disabled = true;
        const payload = Object.fromEntries(new FormData(transactionForm)); payload.amount = Number(payload.amount);
        try { const { response, result } = await fetchJson(page.dataset.transactionUrl, payload); if (!response.ok) { showMessage('[data-transaction-message]', result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to post transaction.'); submit.disabled = false; return; } showMessage('[data-transaction-message]', result.message, true); setTimeout(() => location.reload(), 500); } catch (_) { showMessage('[data-transaction-message]', 'Network error. Transaction was not posted.'); submit.disabled = false; }
    });

    document.querySelector('#cb-add-account').addEventListener('click', () => { document.querySelector('#cb-account-form').reset(); document.querySelector('[data-account-message]').classList.add('hidden'); setModal('account', true); });
    document.querySelector('#cb-account-form').addEventListener('submit', async (event) => { event.preventDefault(); const form = event.currentTarget; const submit = form.querySelector('[type="submit"]'); submit.disabled = true; try { const { response, result } = await fetchJson(page.dataset.accountUrl, Object.fromEntries(new FormData(form))); if (!response.ok) { showMessage('[data-account-message]', result.message || 'Unable to create account.'); submit.disabled = false; return; } showMessage('[data-account-message]', result.message, true); setTimeout(() => location.reload(), 500); } catch (_) { showMessage('[data-account-message]', 'Network error. Account was not created.'); submit.disabled = false; } });

    const reconciliationForm = document.querySelector('#cb-reconciliation-form');
    const renderReconciliation = () => {
        const code = reconciliationForm.elements.account_code.value; const selectedAccount = account(code); const uncleared = transactions.filter((row) => !row.cleared && [row.account_code, row.from_account_code, row.to_account_code].map(String).includes(code));
        if (selectedAccount && !reconciliationForm.elements.statement_balance.value) reconciliationForm.elements.statement_balance.value = Number(selectedAccount.balance).toFixed(2);
        const statement = Number(reconciliationForm.elements.statement_balance.value || 0); const book = Number(selectedAccount?.balance || 0); const checked = [...document.querySelectorAll('#cb-reconcile-rows input:checked')].map((input) => Number(input.value)); const unclearedAmount = uncleared.filter((row) => !checked.includes(Number(row.id))).reduce((sum, row) => sum + signedAmount(row, code), 0); const difference = statement + unclearedAmount - book;
        document.querySelector('#cb-reconciliation-summary').innerHTML = [['Book balance', book], ['Statement balance', statement], ['Uncleared items', unclearedAmount], ['Difference', difference]].map(([name, value]) => `<div class="rounded-lg bg-slate-50 p-3"><p class="text-[10px] text-slate-500">${name}</p><strong class="mt-1 block font-mono text-xs ${name === 'Difference' && Math.abs(value) > .004 ? 'text-rose-600' : 'text-slate-800'}">${money.format(value)}</strong></div>`).join('');
        document.querySelector('#cb-reconcile-rows').innerHTML = !code ? '<tr><td colspan="5" class="p-8 text-center text-slate-500">Select an account to begin.</td></tr>' : uncleared.length ? uncleared.map((row) => `<tr class="border-t border-slate-100"><td class="p-2"><input type="checkbox" name="transaction_ids[]" value="${row.id}" ${checked.includes(Number(row.id)) ? 'checked' : ''} class="size-4 rounded border-slate-300"></td><td class="p-2">${escapeHtml(row.date)}</td><td class="p-2">${escapeHtml(row.reference || row.transaction_number)}</td><td class="p-2">${escapeHtml(row.description)}</td><td class="p-2 text-right font-mono ${signedAmount(row, code) >= 0 ? 'text-emerald-600' : 'text-rose-600'}">${money.format(signedAmount(row, code))}</td></tr>`).join('') : '<tr><td colspan="5" class="p-8 text-center text-slate-500">No uncleared transactions for this account.</td></tr>';
    };
    reconciliationForm.elements.account_code.addEventListener('change', () => { reconciliationForm.elements.statement_balance.value = ''; renderReconciliation(); }); reconciliationForm.elements.statement_balance.addEventListener('input', renderReconciliation); document.querySelector('#cb-reconcile-rows').addEventListener('change', renderReconciliation);
    reconciliationForm.addEventListener('submit', async (event) => { event.preventDefault(); const submit = document.querySelector('#cb-reconcile-submit'); submit.disabled = true; const formData = new FormData(reconciliationForm); const payload = { account_code: formData.get('account_code'), statement_date: formData.get('statement_date'), statement_balance: Number(formData.get('statement_balance')), notes: formData.get('notes'), transaction_ids: formData.getAll('transaction_ids[]').map(Number) }; try { const { response, result } = await fetchJson(page.dataset.reconciliationUrl, payload); if (!response.ok) { showMessage('[data-reconciliation-message]', result.message || Object.values(result.errors || {})[0]?.[0] || 'Unable to reconcile.'); submit.disabled = false; return; } showMessage('[data-reconciliation-message]', result.message, true); setTimeout(() => location.reload(), 500); } catch (_) { showMessage('[data-reconciliation-message]', 'Network error. Reconciliation was not saved.'); submit.disabled = false; } });
    document.querySelector('#cb-reconciliation-history').innerHTML = reconciliations.length ? reconciliations.map((row) => `<article class="rounded-lg border border-slate-200 p-3"><div class="flex justify-between"><div><p class="text-xs font-semibold text-slate-800">${escapeHtml(row.reference)}</p><p class="mt-1 text-[10px] text-slate-500">${escapeHtml(account(row.account_code)?.name || row.account_code)} · ${escapeHtml(row.statement_date)}</p></div><span class="apm-active-badge">Balanced</span></div><div class="mt-3 flex justify-between text-[11px]"><span class="text-slate-500">Statement balance</span><strong class="font-mono">${money.format(Number(row.statement_balance))}</strong></div></article>`).join('') : '<p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-xs text-slate-500">No reconciliation history yet.</p>';

    document.querySelectorAll('[data-cb-close]').forEach((button) => button.addEventListener('click', () => setModal(button.dataset.cbClose, false)));
    renderTransactions(); renderReconciliation();
};

document.addEventListener('DOMContentLoaded', setupCashBank);
