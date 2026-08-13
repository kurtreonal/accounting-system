import { can } from './demo-access';

const setupTaxSettings = () => {
    const page = document.querySelector('#tax-settings-page');
    if (!page) return;

    const initial = JSON.parse(document.querySelector('#tax-settings-data').textContent);
    const taxCodes = initial.taxCodes || [];
    const canAdminister = can('tax.manage');
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
    const form = document.querySelector('#tax-form');
    const pageSize = 8;
    let currentPage = 1;
    let sort = { field: 'code', direction: 'asc' };

    const urlFor = (id, action = '') => `${page.dataset.storeUrl}/${id}${action ? `/${action}` : ''}`;
    const request = async (url, method, payload) => {
        const response = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: payload ? JSON.stringify(payload) : undefined });
        let result = {};
        try { result = await response.json(); } catch (_) { result = { message: 'The server returned an invalid response.' }; }
        return { response, result };
    };
    const setModal = (open) => { const modal = document.querySelector('#tax-form-modal'); modal.classList.toggle('hidden', !open); modal.classList.toggle('flex', open); modal.setAttribute('aria-hidden', String(!open)); };
    const typeClass = (type) => ({ VAT: 'bg-blue-100 text-blue-700', EWT: 'bg-amber-100 text-amber-700', DST: 'bg-fuchsia-100 text-fuchsia-700' })[type] || 'bg-slate-100 text-slate-600';
    const filtered = () => {
        const search = document.querySelector('#tax-search').value.trim().toLowerCase();
        const type = document.querySelector('#tax-type-filter').value;
        const status = document.querySelector('#tax-status-filter').value;
        return taxCodes.filter((row) => (!search || `${row.code} ${row.name} ${row.applies_to}`.toLowerCase().includes(search)) && (!type || row.type === type) && (!status || row.status === status)).sort((left, right) => {
            const a = sort.field === 'rate' ? Number(left.rate) : String(left[sort.field]); const b = sort.field === 'rate' ? Number(right.rate) : String(right[sort.field]);
            return (a > b ? 1 : a < b ? -1 : 0) * (sort.direction === 'asc' ? 1 : -1);
        });
    };
    const action = (label, attribute, value, classes = '') => `<button type="button" ${attribute}="${escapeHtml(value)}" class="rounded px-2 py-1 text-[10px] font-medium text-blue-600 transition hover:bg-blue-50 ${classes}">${label}</button>`;
    const renderRates = () => {
        const rows = filtered(); const pages = Math.max(1, Math.ceil(rows.length / pageSize)); currentPage = Math.min(currentPage, pages); const start = (currentPage - 1) * pageSize; const visible = rows.slice(start, start + pageSize);
        document.querySelector('#tax-rate-rows').innerHTML = visible.length ? visible.map((row) => {
            const actions = [action('View', 'data-tax-view', row.id)];
            if (canAdminister) {
                actions.push(action('Edit', 'data-tax-edit', row.id));
                actions.push(action(row.status === 'Active' ? 'Disable' : 'Enable', 'data-tax-status', row.id, row.status === 'Active' ? 'text-rose-600' : 'text-emerald-600'));
                if (row.status === 'Active' && !row.is_default) actions.push(action('Set Default', 'data-tax-default', row.id));
            }
            return `<tr class="apm-table-row"><td><span class="font-mono text-[11px] font-semibold text-blue-600">${escapeHtml(row.code)}</span>${row.is_default ? '<span class="ml-2 rounded bg-blue-50 px-1.5 py-0.5 text-[9px] font-semibold text-blue-600">Default</span>' : ''}</td><td class="font-medium text-slate-800">${escapeHtml(row.name)}</td><td class="apm-money text-right">${Number(row.rate).toFixed(2)}%</td><td class="text-center"><span class="rounded-full px-2 py-1 text-[9px] font-semibold ${typeClass(row.type)}">${escapeHtml(row.type)}</span></td><td>${escapeHtml(row.applies_to)}</td><td class="text-center"><span class="rounded-full px-2 py-1 text-[9px] font-semibold ${row.status === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">${escapeHtml(row.status)}</span></td><td class="print:hidden"><div class="flex justify-end">${actions.length ? actions.join('') : '<span class="text-[10px] text-slate-400">View only</span>'}</div></td></tr>`;
        }).join('') : '<tr><td colspan="7" class="px-4 py-14 text-center"><i class="fa-solid fa-percent text-3xl text-slate-300"></i><p class="mt-3 text-sm font-medium text-slate-600">No tax rates found</p><p class="mt-1 text-xs text-slate-400">Adjust the filters or add a tax rate.</p></td></tr>';
        document.querySelector('#tax-rate-count').textContent = `${rows.length} configured rate${rows.length === 1 ? '' : 's'}`;
        document.querySelector('#tax-page-summary').textContent = rows.length ? `Showing ${start + 1}–${Math.min(start + pageSize, rows.length)} of ${rows.length}` : 'Showing 0 records';
        document.querySelector('#tax-page-number').textContent = currentPage;
        document.querySelector('#tax-prev').disabled = currentPage === 1; document.querySelector('#tax-next').disabled = currentPage === pages;
    };
    const showForm = (row = null) => {
        form.reset(); form.querySelectorAll('[data-tax-error]').forEach((target) => { target.textContent = ''; }); form.querySelector('[data-tax-message]').classList.add('hidden');
        form.elements.id.value = row?.id || ''; document.querySelector('#tax-form-title').textContent = row ? `Edit ${row.code}` : 'Add Tax Rate';
        if (row) { ['code', 'name', 'rate', 'type', 'applies_to'].forEach((field) => { form.elements[field].value = row[field]; }); form.elements.is_default.checked = Boolean(row.is_default); }
        setModal(true); setTimeout(() => form.elements.code.focus(), 0);
    };
    const showMessage = (message, success = false) => { const target = form.querySelector('[data-tax-message]'); target.textContent = message; target.className = `mt-3 rounded-lg p-3 text-xs ${success ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`; };
    const showView = (row) => {
        document.querySelector('#tax-view-title').textContent = `${row.code} · ${row.name}`;
        document.querySelector('#tax-view-content').innerHTML = `<dl class="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-4 text-xs"><div><dt class="text-slate-400">Rate</dt><dd class="mt-1 font-mono font-bold text-slate-900">${Number(row.rate).toFixed(2)}%</dd></div><div><dt class="text-slate-400">Type</dt><dd class="mt-1 font-semibold text-slate-800">${escapeHtml(row.type)}</dd></div><div class="col-span-2"><dt class="text-slate-400">Applies to</dt><dd class="mt-1 text-slate-800">${escapeHtml(row.applies_to)}</dd></div><div><dt class="text-slate-400">Status</dt><dd class="mt-1 font-semibold ${row.status === 'Active' ? 'text-emerald-600' : 'text-slate-500'}">${escapeHtml(row.status)}</dd></div><div><dt class="text-slate-400">Default</dt><dd class="mt-1 font-semibold text-slate-800">${row.is_default ? `Yes, for ${escapeHtml(row.type)}` : 'No'}</dd></div></dl><p class="mt-3 text-[10px] text-slate-400">Last updated ${escapeHtml(new Date(row.updated_at).toLocaleString('en-PH'))}</p>`;
        const modal = document.querySelector('#tax-view-modal'); modal.classList.remove('hidden'); modal.classList.add('flex'); modal.setAttribute('aria-hidden', 'false');
    };
    const updateMetrics = (metrics) => {
        Object.entries(metrics).forEach(([key, value]) => { const target = document.querySelector(`[data-tax-metric="${key}"]`); target.textContent = money.format(Number(value)); if (key === 'net') { target.classList.toggle('!text-emerald-600', Number(value) < 0); target.classList.toggle('!text-rose-600', Number(value) >= 0); } });
    };
    const summaryQuery = () => { const params = new URLSearchParams(new FormData(document.querySelector('#tax-summary-filters'))); [...params.entries()].forEach(([key, value]) => { if (!value) params.delete(key); }); return params; };
    const renderSummary = (summary) => {
        updateMetrics(summary.metrics);
        document.querySelector('#tax-summary-rows').innerHTML = summary.rows.length ? summary.rows.map((row) => `<tr class="apm-table-row"><td class="font-mono text-[11px]">${escapeHtml(row.date)}</td><td><a href="${escapeHtml(row.link)}" class="font-medium text-blue-600 hover:underline">${escapeHtml(row.reference)}</a><p class="mt-0.5 font-mono text-[9px] text-slate-400">${escapeHtml(row.journal_number)}</p></td><td><span class="font-mono text-blue-600">${escapeHtml(row.tax_code)}</span><p class="text-[9px] text-slate-400">${escapeHtml(row.tax_name)}</p></td><td>${escapeHtml(row.direction)}</td><td class="apm-money text-right">${Number(row.rate).toFixed(2)}%</td><td class="apm-money text-right">${money.format(Number(row.taxable_amount))}</td><td class="apm-money text-right">${money.format(Number(row.tax_amount))}</td></tr>`).join('') : '<tr><td colspan="7" class="px-4 py-14 text-center"><i class="fa-solid fa-file-circle-xmark text-3xl text-slate-300"></i><p class="mt-3 text-sm font-medium text-slate-600">No posted tax activity</p><p class="mt-1 text-xs text-slate-400">No tax-account journal lines match the selected filters.</p></td></tr>';
        document.querySelector('#tax-summary-foot').innerHTML = `<tr><td colspan="5" class="px-4 py-3 text-right">Net VAT Payable / (Credit)</td><td></td><td class="px-4 py-3 text-right font-mono">${escapeHtml(money.format(Number(summary.metrics.net)))}</td></tr>`;
        document.querySelector('#tax-summary-count').textContent = `${summary.record_count} posted tax entr${summary.record_count === 1 ? 'y' : 'ies'}`;
        document.querySelector('#tax-summary-generated').textContent = `Generated ${new Date(summary.generated_at).toLocaleString('en-PH')}`;
        document.querySelector('#tax-summary-export').href = `${page.dataset.exportUrl}?view=summary&${summaryQuery().toString()}`;
    };
    const loadSummary = async () => {
        const loading = document.querySelector('#tax-summary-loading'); const content = document.querySelector('#tax-summary-content'); const error = document.querySelector('#tax-summary-error');
        loading.classList.remove('hidden'); content.classList.add('hidden'); error.classList.add('hidden');
        try { const response = await fetch(`${page.dataset.summaryUrl}?${summaryQuery().toString()}`, { headers: { Accept: 'application/json' } }); const result = await response.json(); if (!response.ok) { error.textContent = result.message || 'Unable to generate summary.'; error.classList.remove('hidden'); return; } renderSummary(result.summary); }
        catch (_) { error.textContent = 'Network error. The VAT summary could not be generated.'; error.classList.remove('hidden'); }
        finally { loading.classList.add('hidden'); content.classList.remove('hidden'); }
    };

    document.querySelectorAll('[data-tax-tab]').forEach((button) => button.addEventListener('click', () => { document.querySelectorAll('[data-tax-tab]').forEach((tab) => { const active = tab === button; tab.setAttribute('aria-selected', String(active)); tab.classList.toggle('border-blue-600', active); tab.classList.toggle('text-blue-600', active); }); document.querySelectorAll('[data-tax-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.taxPanel !== button.dataset.taxTab)); }));
    if (document.querySelector('#tax-add')) document.querySelector('#tax-add').addEventListener('click', () => showForm());
    document.querySelectorAll('[data-tax-close]').forEach((button) => button.addEventListener('click', () => setModal(false)));
    document.querySelectorAll('[data-tax-view-close]').forEach((button) => button.addEventListener('click', () => { const modal = document.querySelector('#tax-view-modal'); modal.classList.add('hidden'); modal.classList.remove('flex'); modal.setAttribute('aria-hidden', 'true'); }));
    ['#tax-search', '#tax-type-filter', '#tax-status-filter'].forEach((selector) => document.querySelector(selector).addEventListener('input', () => { currentPage = 1; renderRates(); }));
    document.querySelectorAll('[data-tax-sort]').forEach((button) => button.addEventListener('click', () => { const field = button.dataset.taxSort; sort = { field, direction: sort.field === field && sort.direction === 'asc' ? 'desc' : 'asc' }; currentPage = 1; renderRates(); }));
    document.querySelector('#tax-prev').addEventListener('click', () => { currentPage--; renderRates(); }); document.querySelector('#tax-next').addEventListener('click', () => { currentPage++; renderRates(); });
    document.querySelector('#tax-rate-rows').addEventListener('click', async (event) => {
        const target = event.target.closest('[data-tax-view], [data-tax-edit], [data-tax-status], [data-tax-default]'); if (!target) return;
        const attribute = [...target.attributes].find((item) => item.name.startsWith('data-tax-')); const id = Number(attribute.value); const row = taxCodes.find((item) => Number(item.id) === id); if (!row) return;
        if (attribute.name === 'data-tax-view') { showView(row); return; }
        if (attribute.name === 'data-tax-edit') { showForm(row); return; }
        const defaultAction = attribute.name === 'data-tax-default'; const nextStatus = row.status === 'Active' ? 'Inactive' : 'Active';
        const confirmation = defaultAction ? `Set ${row.code} as the default ${row.type} code?` : `${nextStatus === 'Inactive' ? 'Disable' : 'Enable'} ${row.code}?`;
        if (!confirm(confirmation)) return; target.disabled = true;
        const { response, result } = await request(defaultAction ? urlFor(id, 'default') : urlFor(id, 'status'), defaultAction ? 'POST' : 'PATCH', defaultAction ? null : { status: nextStatus });
        if (!response.ok) { alert(result.message || 'Unable to update tax code.'); target.disabled = false; return; } location.reload();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); form.querySelectorAll('[data-tax-error]').forEach((target) => { target.textContent = ''; }); const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
        const values = Object.fromEntries(new FormData(form)); const id = values.id; const payload = { code: values.code, name: values.name, rate: Number(values.rate), type: values.type, applies_to: values.applies_to, is_default: form.elements.is_default.checked };
        const { response, result } = await request(id ? urlFor(id) : page.dataset.storeUrl, id ? 'PUT' : 'POST', payload);
        if (!response.ok) { Object.entries(result.errors || {}).forEach(([field, messages]) => { const target = form.querySelector(`[data-tax-error="${field}"]`); if (target) target.textContent = messages[0]; }); showMessage(result.message || 'Unable to save tax rate.'); submit.disabled = false; return; }
        showMessage(result.message, true); setTimeout(() => location.reload(), 500);
    });
    document.querySelector('#tax-summary-filters').addEventListener('submit', (event) => { event.preventDefault(); loadSummary(); });

    renderRates(); renderSummary(initial.summary);
};

document.addEventListener('DOMContentLoaded', setupTaxSettings);
