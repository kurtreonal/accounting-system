const setupRecordDetails = () => {
    const modal = document.querySelector('#record-detail-modal');
    if (!modal) return;

    const title = modal.querySelector('[data-record-detail-title]');
    const subtitle = modal.querySelector('[data-record-detail-subtitle]');
    const status = modal.querySelector('[data-record-detail-status]');
    const content = modal.querySelector('[data-record-detail-content]');
    let trigger = null;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const statusClass = (value) => ({
        Posted: 'bg-emerald-100 text-emerald-700', Paid: 'bg-emerald-100 text-emerald-700', Active: 'bg-emerald-100 text-emerald-700', Approved: 'bg-emerald-100 text-emerald-700',
        'Partially Paid': 'bg-sky-100 text-sky-700', 'For Review': 'bg-amber-100 text-amber-700', Unpaid: 'bg-amber-100 text-amber-700',
        Overdue: 'bg-red-100 text-red-700', Reversed: 'bg-red-100 text-red-700', Inactive: 'bg-slate-100 text-slate-600', Draft: 'bg-slate-100 text-slate-600',
    }[value] || 'bg-slate-100 text-slate-600');

    const setOpen = (open) => {
        modal.classList.toggle('hidden', !open);
        modal.classList.toggle('flex', open);
        modal.setAttribute('aria-hidden', String(!open));
        document.body.classList.toggle('overflow-hidden', open);
        if (!open) trigger?.focus();
    };

    const render = (record) => {
        title.textContent = record.title;
        subtitle.textContent = record.identifier;
        status.textContent = record.status || '';
        status.className = `inline-flex rounded-md px-2 py-1 text-[10px] font-semibold ${statusClass(record.status)}`;
        const fields = (record.fields || []).map((field) => `<div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800"><dt class="text-[10px] font-medium uppercase tracking-wide text-slate-400">${escapeHtml(field.label)}</dt><dd class="mt-1 break-words text-xs font-medium text-slate-800 dark:text-slate-100">${escapeHtml(field.value)}</dd></div>`).join('');
        const sections = (record.sections || []).map((section) => `<section class="mt-5"><h3 class="mb-2 text-xs font-semibold text-slate-800 dark:text-slate-100">${escapeHtml(section.title)}</h3><div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700"><table class="w-full min-w-[620px] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500 dark:bg-slate-800"><tr>${(section.columns || []).map((column) => `<th class="px-3 py-2">${escapeHtml(column)}</th>`).join('')}</tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">${(section.rows || []).map((row) => `<tr>${row.map((value) => `<td class="px-3 py-2 text-slate-700 dark:text-slate-200">${escapeHtml(value)}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${section.columns.length}" class="px-3 py-6 text-center text-slate-500">No line details.</td></tr>`}</tbody></table></div></section>`).join('');
        content.innerHTML = `<dl class="grid gap-3 sm:grid-cols-2">${fields}</dl>${sections}`;
    };

    const linkedRecord = (anchor) => {
        if (!anchor?.href) return null;
        const url = new URL(anchor.href, globalThis.location.href);
        if (url.origin !== globalThis.location.origin) return null;
        if (url.pathname === '/journal-entries' && url.searchParams.get('entry')) return ['journal_entry', url.searchParams.get('entry')];
        if (url.pathname === '/sales-revenue' && url.searchParams.get('invoice')) return ['sales_invoice', url.searchParams.get('invoice')];
        if (url.pathname === '/general-ledger' && url.searchParams.get('account')) return ['account', url.searchParams.get('account')];
        const printedInvoice = url.pathname.match(/^\/sales-revenue\/invoices\/([^/]+)\/print$/);
        if (printedInvoice) return ['sales_invoice', decodeURIComponent(printedInvoice[1])];
        if (url.pathname === '/expenses' && url.hash.length > 1) return ['expense', decodeURIComponent(url.hash.slice(1))];
        return null;
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-record-detail]');
        const linked = button ? null : linkedRecord(event.target.closest('a[href]'));
        const resource = button?.dataset.recordResource || linked?.[0];
        const identifier = button?.dataset.recordId || linked?.[1];
        if (!resource || !identifier) return;
        event.preventDefault();
        trigger = button || event.target.closest('a[href]');
        title.textContent = 'Record Details';
        subtitle.textContent = identifier;
        status.textContent = '';
        content.innerHTML = '<div class="grid min-h-40 place-items-center text-sm text-slate-500"><span><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading record…</span></div>';
        setOpen(true);

        try {
            const endpoint = `${modal.dataset.endpoint}/${encodeURIComponent(resource)}/${encodeURIComponent(identifier)}`;
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Record details could not be loaded.');
            render(result.record);
        } catch (problem) {
            content.innerHTML = `<div class="rounded-lg bg-red-50 p-4 text-xs text-red-700"><i class="fa-solid fa-circle-exclamation mr-1"></i>${escapeHtml(problem.message)}</div>`;
        }
    });

    modal.querySelectorAll('[data-record-detail-close]').forEach((button) => button.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) setOpen(false); });
};

document.addEventListener('DOMContentLoaded', setupRecordDetails);
