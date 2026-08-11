const setupGeneralLedger = () => {
    const page = document.querySelector('#general-ledger-page');
    const dataElement = document.querySelector('#general-ledger-report');
    if (!page || !dataElement) return;

    let report = JSON.parse(dataElement.textContent || 'null');
    let selectedCode = report?.account?.code ? String(report.account.code) : '';
    let currentPage = 1;
    let requestNumber = 0;
    const pageSize = 10;
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const accountSearch = document.querySelector('#ledger-account-search');
    const entrySearch = document.querySelector('#ledger-entry-search');
    const dateFrom = document.querySelector('#ledger-date-from');
    const dateTo = document.querySelector('#ledger-date-to');
    const tableBody = document.querySelector('#ledger-table-body');
    const error = document.querySelector('#ledger-error');
    const exportLink = document.querySelector('#ledger-export');
    const pdfExportLink = document.querySelector('#ledger-export-pdf');
    const printButton = document.querySelector('#ledger-print');
    const pagination = document.querySelector('#ledger-pagination');
    let searchTimer;

    const el = (tag, text = '', className = '') => {
        const element = document.createElement(tag);
        element.textContent = text;
        if (className) element.className = className;
        return element;
    };

    const query = () => {
        const parameters = new URLSearchParams({ account: selectedCode });
        if (dateFrom.value) parameters.set('date_from', dateFrom.value);
        if (dateTo.value) parameters.set('date_to', dateTo.value);
        if (entrySearch.value.trim()) parameters.set('search', entrySearch.value.trim());
        return parameters;
    };

    const updateUrls = () => {
        if (!selectedCode) return;
        exportLink.href = `${page.dataset.exportUrl}?${query()}`;
        pdfExportLink.href = `${page.dataset.pdfUrl}?${query()}`;
        const browserUrl = new URL(window.location.href);
        browserUrl.search = query().toString();
        window.history.replaceState({}, '', browserUrl);
    };

    const addMoneyCell = (row, amount, allowDash = false) => {
        const cell = row.insertCell();
        cell.className = `apm-money text-right ${amount < 0 ? 'text-red-600' : ''}`;
        cell.textContent = allowDash && amount === 0 ? '—' : money.format(amount);
        return cell;
    };

    const createRow = (item) => {
        const row = document.createElement('tr');
        row.className = 'apm-table-row dashboard-enter';
        row.append(el('td', item.date, 'font-mono text-[11px]'));
        const journalCell = row.insertCell();
        const link = el('a', item.journal_number, 'font-mono text-blue-600 hover:underline');
        link.href = `${page.dataset.journalUrl}?entry=${encodeURIComponent(item.journal_number)}`;
        journalCell.append(link);
        const descriptionCell = row.insertCell();
        descriptionCell.append(el('span', item.line_description || item.description, 'font-medium'));
        if (item.reference) descriptionCell.append(el('span', item.reference, 'mt-0.5 block text-[10px] text-slate-400'));
        addMoneyCell(row, Number(item.debit), true);
        addMoneyCell(row, Number(item.credit), true);
        addMoneyCell(row, Number(item.running_balance));
        return row;
    };

    const renderPagination = (pageCount) => {
        pagination.replaceChildren();
        if (pageCount <= 1) return;
        const add = (label, target, disabled = false, active = false) => {
            const button = el('button', label, `apm-page-button ${active ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-500' : ''}`);
            button.type = 'button';
            button.disabled = disabled;
            button.addEventListener('click', () => {
                currentPage = target;
                render();
            });
            pagination.append(button);
        };
        add('Prev', currentPage - 1, currentPage === 1);
        for (let number = 1; number <= pageCount; number += 1) add(String(number), number, false, number === currentPage);
        add('Next', currentPage + 1, currentPage === pageCount);
    };

    const renderRows = (visible) => {
        tableBody.replaceChildren();
        const beginning = document.createElement('tr');
        beginning.className = 'ledger-opening-balance border-b border-slate-100 bg-slate-50/60';
        const label = el('td', 'Beginning balance', 'px-4 py-3 font-medium text-slate-500');
        label.colSpan = 5;
        beginning.append(label);
        const balance = el('td', money.format(Number(report.beginning_balance)), 'apm-money px-4 py-3 text-right');
        if (Number(report.beginning_balance) < 0) balance.classList.add('text-red-600');
        beginning.append(balance);
        tableBody.append(beginning);
        if (visible.length === 0) {
            const row = document.createElement('tr');
            const cell = el('td', 'No posted transactions match the selected account and filters.', 'px-4 py-10 text-center text-xs text-slate-500');
            cell.colSpan = 6;
            row.append(cell);
            tableBody.append(row);
        } else visible.forEach((item) => tableBody.append(createRow(item)));
    };

    const render = () => {
        if (!report) return;
        const rows = report.rows || [];
        const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        const start = (currentPage - 1) * pageSize;
        const visible = rows.slice(start, start + pageSize);
        renderRows(visible);

        document.querySelector('#ledger-account-summary').textContent = `${report.account.code} - ${report.account.name}`;
        document.querySelector('#ledger-type-summary').textContent = `${report.account.type} · ${report.account.sub_type || 'Unclassified'}`;
        document.querySelector('#ledger-balance-summary').textContent = money.format(Number(report.ending_balance));
        document.querySelector('#ledger-table-title').textContent = `${report.account.code} — ${report.account.name}`;
        document.querySelector('#ledger-row-count').textContent = `${rows.length} ${rows.length === 1 ? 'transaction' : 'transactions'}`;
        document.querySelector('#ledger-footer-count').textContent = `${rows.length} ${rows.length === 1 ? 'transaction' : 'transactions'}`;
        document.querySelector('#ledger-total-debit').textContent = money.format(Number(report.total_debit));
        document.querySelector('#ledger-total-credit').textContent = money.format(Number(report.total_credit));
        document.querySelector('#ledger-record-count').textContent = rows.length === 0 ? 'Showing 0 records' : `Showing ${start + 1}-${start + visible.length} of ${rows.length} records`;
        document.querySelector('#ledger-print-context').textContent = `Period: ${dateFrom.value || 'Beginning'} to ${dateTo.value || 'Present'}`;
        renderPagination(pageCount);
        updateUrls();
    };

    const load = async () => {
        if (!selectedCode) return;
        const thisRequest = ++requestNumber;
        error.classList.add('hidden');
        tableBody.classList.add('opacity-50');
        try {
            const response = await fetch(`${page.dataset.reportUrl}?${query()}`, { headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Unable to load the ledger.');
            if (thisRequest !== requestNumber) return;
            report = result.data;
            currentPage = 1;
            render();
        } catch (problem) {
            error.textContent = problem.message;
            error.classList.remove('hidden');
        } finally {
            if (thisRequest === requestNumber) tableBody.classList.remove('opacity-50');
        }
    };

    document.querySelector('#ledger-account-list')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-account-code]');
        if (!button) return;
        selectedCode = button.dataset.accountCode;
        document.querySelectorAll('[data-account-code]').forEach((item) => {
            const active = item === button;
            item.classList.toggle('bg-blue-600', active);
            item.classList.toggle('text-white', active);
            item.classList.toggle('shadow-sm', active);
            item.classList.toggle('text-slate-700', !active);
        });
        load();
    });

    accountSearch?.addEventListener('input', () => {
        let visible = 0;
        const needle = accountSearch.value.trim().toLowerCase();
        document.querySelectorAll('[data-account-code]').forEach((button) => {
            const show = !needle || button.dataset.accountSearch.includes(needle);
            button.classList.toggle('hidden', !show);
            if (show) visible += 1;
        });
        document.querySelector('#ledger-account-empty').classList.toggle('hidden', visible > 0);
    });

    [dateFrom, dateTo].forEach((input) => input.addEventListener('change', load));
    entrySearch.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(load, 250);
    });
    document.querySelector('#ledger-clear-filters')?.addEventListener('click', () => {
        dateFrom.value = '';
        dateTo.value = '';
        entrySearch.value = '';
        load();
    });
    window.addEventListener('beforeprint', () => {
        if (report) renderRows(report.rows || []);
    });
    window.addEventListener('afterprint', render);
    render();
};

document.addEventListener('DOMContentLoaded', setupGeneralLedger);
