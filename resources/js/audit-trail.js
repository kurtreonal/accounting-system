const setupAuditTrail = () => {
    const page = document.querySelector('#audit-trail-page');
    if (!page) return;

    const rows = [...page.querySelectorAll('[data-audit-row]')];
    const pageSize = Number(page.dataset.pageSize) || 8;
    const summary = page.querySelector('[data-audit-summary]');
    const pageNumber = page.querySelector('[data-audit-page]');
    const previous = page.querySelector('[data-audit-prev]');
    const next = page.querySelector('[data-audit-next]');
    let currentPage = 1;
    let activeView = 'table';

    page.querySelectorAll('[data-audit-filters] select').forEach((select) => {
        select.addEventListener('change', () => select.form?.requestSubmit());
    });

    const renderPage = () => {
        const pages = Math.max(1, Math.ceil(rows.length / pageSize));
        currentPage = Math.max(1, Math.min(currentPage, pages));
        rows.forEach((row, index) => { row.hidden = index < (currentPage - 1) * pageSize || index >= currentPage * pageSize; });
        if (summary) summary.textContent = rows.length ? `Showing ${(currentPage - 1) * pageSize + 1}–${Math.min(currentPage * pageSize, rows.length)} of ${rows.length} records` : 'Showing 0 records';
        if (pageNumber) pageNumber.textContent = currentPage;
        if (previous) previous.disabled = currentPage === 1;
        if (next) next.disabled = currentPage === pages;
    };

    previous?.addEventListener('click', () => { currentPage -= 1; renderPage(); });
    next?.addEventListener('click', () => { currentPage += 1; renderPage(); });
    page.querySelectorAll('[data-audit-view]').forEach((button) => button.addEventListener('click', () => {
        activeView = button.dataset.auditView;
        page.querySelectorAll('[data-audit-view]').forEach((tab) => {
            const selected = tab === button;
            tab.setAttribute('aria-selected', String(selected));
            tab.classList.toggle('bg-blue-600', selected);
            tab.classList.toggle('text-white', selected);
            tab.classList.toggle('text-slate-600', !selected);
        });
        page.querySelectorAll('[data-audit-panel]').forEach((panel) => { panel.hidden = panel.dataset.auditPanel !== activeView; });
    }));

    window.addEventListener('beforeprint', () => {
        page.querySelector('[data-audit-panel="table"]').hidden = false;
        rows.forEach((row) => { row.hidden = false; });
    });
    window.addEventListener('afterprint', () => {
        page.querySelectorAll('[data-audit-panel]').forEach((panel) => { panel.hidden = panel.dataset.auditPanel !== activeView; });
        renderPage();
    });

    renderPage();
};

document.addEventListener('DOMContentLoaded', setupAuditTrail);
