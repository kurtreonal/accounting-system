const setupFinancialReports = () => {
    const page = document.querySelector('#financial-reports-page');
    if (!page) return;

    const initial = JSON.parse(document.querySelector('#financial-report-data').textContent);
    const form = document.querySelector('#report-filters');
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
    let activeReport = initial.report.key;
    let currentReport = initial.report;

    const filterMap = {
        'sales-report': ['party', 'status'],
        'expense-report': ['category', 'status'],
        'tax-summary': ['tax_code'],
    };
    const kpiLabels = {
        revenue: 'Revenue', expenses: 'Expenses', net_income: 'Net Income', assets: 'Assets', liabilities: 'Liabilities', equity_and_earnings: 'Equity & Earnings',
        operating: 'Operating', investing: 'Investing', financing: 'Financing', net_change: 'Net Cash Change', input_tax: 'Input Tax', output_tax: 'Output Tax', net_tax: 'Net Output Tax',
    };

    const query = () => {
        const params = new URLSearchParams(new FormData(form));
        params.set('report', activeReport);
        [...params.entries()].forEach(([key, value]) => { if (!value) params.delete(key); });
        return params;
    };
    const setLoading = (loading) => {
        document.querySelector('#report-loading').classList.toggle('hidden', !loading);
        document.querySelector('#report-content').classList.toggle('hidden', loading);
        form.querySelector('[type="submit"]').disabled = loading;
    };
    const updateFilters = () => {
        const visible = filterMap[activeReport] || [];
        document.querySelectorAll('[data-report-filter]').forEach((label) => { const show = visible.includes(label.dataset.reportFilter); label.classList.toggle('hidden', !show); if (!show) label.querySelector('select').value = ''; });
        if (activeReport === 'expense-report' && !form.elements.status.value) form.elements.status.value = 'Approved';
        document.querySelectorAll('[data-report-key]').forEach((button) => {
            const active = button.dataset.reportKey === activeReport;
            button.setAttribute('aria-pressed', String(active));
        });
    };
    const renderValue = (row, column) => {
        const value = row[column.key];
        if (column.money) return value === undefined || value === null || Math.abs(Number(value)) < 0.005 ? '—' : money.format(Number(value));
        return value ?? '—';
    };
    const render = (report) => {
        currentReport = report;
        document.querySelector('#report-title').textContent = report.title;
        document.querySelector('#report-period').textContent = report.period;
        const health = document.querySelector('#report-health');
        health.classList.toggle('hidden', report.balanced === null);
        if (report.balanced !== null) {
            health.textContent = report.balanced ? 'Balanced' : 'Out of Balance';
            health.className = `rounded-full px-2.5 py-1 text-[10px] font-semibold ${report.balanced ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`;
        }

        document.querySelector('#report-head').innerHTML = `<tr>${report.columns.map((column) => `<th class="px-4 py-3 ${column.money ? 'text-right' : 'text-left'}">${escapeHtml(column.label)}</th>`).join('')}</tr>`;
        const dataRows = report.rows.filter((row) => row.row_type !== 'group');
        document.querySelector('#report-body').innerHTML = report.rows.map((row) => {
            if (row.row_type === 'group') return `<tr class="border-t border-slate-200 bg-slate-50/70"><th colspan="${report.columns.length}" class="px-4 py-2 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">${escapeHtml(row.account_name)}</th></tr>`;
            return `<tr class="apm-table-row">${report.columns.map((column, index) => `<td class="${column.money ? 'apm-money text-right' : ''}">${index === 0 && row.link ? `<a href="${escapeHtml(row.link)}" class="font-medium text-blue-600 hover:underline">${escapeHtml(renderValue(row, column))}</a>` : escapeHtml(renderValue(row, column))}</td>`).join('')}</tr>`;
        }).join('');

        const totalLabel = report.totals.label || 'TOTAL';
        document.querySelector('#report-foot').innerHTML = `<tr>${report.columns.map((column, index) => {
            const value = report.totals[column.key];
            const content = index === 0 ? totalLabel : value === undefined ? '' : (column.money ? money.format(Number(value)) : value);
            return `<td class="px-4 py-3 ${column.money ? 'text-right font-mono' : ''}">${escapeHtml(content)}</td>`;
        }).join('')}</tr>${report.totals.secondary_amount !== undefined ? `<tr><td colspan="${report.columns.length - 1}" class="px-4 pb-3 text-right text-[10px] text-slate-500">Liabilities & Equity</td><td class="px-4 pb-3 text-right font-mono">${escapeHtml(money.format(Number(report.totals.secondary_amount)))}</td></tr>` : ''}`;

        const kpis = Object.entries(report.kpis || {});
        const kpiTarget = document.querySelector('#report-kpis');
        kpiTarget.classList.toggle('hidden', kpis.length === 0);
        kpiTarget.innerHTML = kpis.map(([key, value]) => `<article class="rounded-lg bg-slate-50 p-3"><p class="text-[10px] text-slate-500">${escapeHtml(kpiLabels[key] || key.replaceAll('_', ' '))}</p><strong class="mt-1 block font-mono text-sm ${Number(value) < 0 ? 'text-rose-600' : 'text-slate-800'}">${escapeHtml(money.format(Number(value)))}</strong></article>`).join('');
        document.querySelector('#report-empty').classList.toggle('hidden', dataRows.length > 0);
        document.querySelector('#report-head').parentElement.classList.toggle('hidden', dataRows.length === 0);
        document.querySelector('#report-record-count').textContent = `${report.record_count} record${report.record_count === 1 ? '' : 's'} · Posted journals only for financial statements`;
        document.querySelector('#report-generated').textContent = `Generated ${new Date(report.generated_at).toLocaleString('en-PH')}`;
        document.querySelector('#report-export').href = `${page.dataset.exportUrl}?${query().toString()}`;
        document.querySelector(`[data-report-summary="${CSS.escape(report.key)}"]`).textContent = report.summary;
        const url = new URL(location.href); url.searchParams.set('report', activeReport); history.replaceState({}, '', url);
    };

    const loadReport = async () => {
        const error = document.querySelector('#report-filter-error'); error.classList.add('hidden'); setLoading(true);
        try {
            const response = await fetch(`${page.dataset.reportUrl}?${query().toString()}`, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) { error.textContent = result.message || 'Unable to generate report.'; error.classList.remove('hidden'); return; }
            render(result.report);
        } catch (_) { error.textContent = 'Network error. The report could not be generated.'; error.classList.remove('hidden'); }
        finally { setLoading(false); }
    };

    document.querySelectorAll('[data-report-key]').forEach((button) => button.addEventListener('click', () => {
        if (button.dataset.reportKey !== activeReport) form.elements.status.value = '';
        activeReport = button.dataset.reportKey;
        updateFilters();
        loadReport();
    }));
    form.addEventListener('submit', (event) => { event.preventDefault(); loadReport(); });
    document.querySelectorAll('[data-report-period]').forEach((button) => button.addEventListener('click', () => {
        const now = new Date(); const to = new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate())); let from;
        if (button.dataset.reportPeriod === 'month') from = new Date(Date.UTC(now.getFullYear(), now.getMonth(), 1));
        else if (button.dataset.reportPeriod === 'quarter') from = new Date(Date.UTC(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1));
        else from = new Date(Date.UTC(now.getFullYear(), 0, 1));
        form.elements.date_from.value = from.toISOString().slice(0, 10); form.elements.date_to.value = to.toISOString().slice(0, 10); loadReport();
    }));

    updateFilters(); render(currentReport);
};

document.addEventListener('DOMContentLoaded', setupFinancialReports);
