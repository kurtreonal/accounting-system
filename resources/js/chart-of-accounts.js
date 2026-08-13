const setupChartOfAccounts = () => {
    const page = document.querySelector('#chart-of-accounts-page');
    const dataElement = document.querySelector('#chart-of-accounts-data');
    const modal = document.querySelector('#new-account-modal');
    const modalPanel = document.querySelector('#new-account-modal-panel');
    const form = document.querySelector('#new-account-form');

    if (!page || !dataElement || !modal || !form) return;

    const accounts = JSON.parse(dataElement.textContent || '[]');
    const modalTitle = document.querySelector('#new-account-modal-title');
    const openButton = document.querySelector('#new-account-button');
    const submitButton = document.querySelector('#create-account-button');
    const codeInput = document.querySelector('#account-code');
    const nameInput = document.querySelector('#account-name');
    const typeInput = document.querySelector('#account-type');
    const subTypeInput = document.querySelector('#account-sub-type');
    const balanceInput = document.querySelector('#account-balance');
    const statusInput = document.querySelector('#account-status');
    const tableBody = document.querySelector('#accounts-table-body');
    const accountCount = document.querySelector('#account-count');
    const recordCount = document.querySelector('#accounts-record-count');
    const pagination = document.querySelector('#accounts-pagination');
    const searchInput = document.querySelector('#account-search');
    const typeFilter = document.querySelector('#account-type-filter');
    const statusFilter = document.querySelector('#account-status-filter');
    const csvExport = document.querySelector('#accounts-export-csv');
    const pdfExport = document.querySelector('#accounts-export-pdf');
    const toast = document.querySelector('#account-toast');
    const csrfToken = form.querySelector('input[name="_token"]').value;
    const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const pageSize = 10;
    let currentPage = 1;
    let editingCode = null;
    let submitting = false;
    let toastTimer;

    const pluralize = (count, singular, plural = `${singular}s`) => count === 1 ? singular : plural;
    const endpoint = (template, code) => template.replace('__CODE__', encodeURIComponent(code));

    const clearErrors = () => {
        form.querySelectorAll('[data-error]').forEach((error) => {
            error.textContent = '';
        });
    };

    const displayErrors = (errors = {}) => {
        const fieldMap = { name: 'accountName', type: 'type', sub_type: 'subType', balance: 'balance', status: 'status' };

        Object.entries(errors).forEach(([field, messages]) => {
            const error = form.querySelector(`[data-error="${fieldMap[field] ?? field}"]`);
            if (error) error.textContent = Array.isArray(messages) ? messages[0] : messages;
        });
    };

    const playFadeUp = (element) => {
        element.classList.remove('dashboard-enter');
        void element.offsetWidth;
        element.classList.add('dashboard-enter');
    };

    const nextAccountCode = () => {
        const used = new Set(accounts.map((account) => account.code));
        let code = 1;
        while (used.has(String(code))) code += 1;
        return String(code);
    };

    const showModal = () => {
        clearErrors();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        playFadeUp(modalPanel);
        nameInput.focus();
    };

    const openCreateModal = () => {
        editingCode = null;
        statusInput.disabled = false;
        form.reset();
        codeInput.value = nextAccountCode();
        balanceInput.value = '0.00';
        modalTitle.textContent = 'New Account';
        submitButton.textContent = 'Add Account';
        showModal();
    };

    const openEditModal = (account) => {
        editingCode = account.code;
        codeInput.value = account.code;
        nameInput.value = account.name;
        typeInput.value = account.type;
        subTypeInput.value = account.sub_type;
        balanceInput.value = Number(account.balance).toFixed(2);
        statusInput.value = account.status;
        statusInput.disabled = true;
        modalTitle.textContent = 'Edit Account';
        submitButton.textContent = 'Save Changes';
        showModal();
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        editingCode = null;
        openButton.focus();
    };

    const showToast = (message, isError = false) => {
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.toggle('border-red-200', isError);
        toast.classList.toggle('text-red-700', isError);
        toast.classList.toggle('border-emerald-200', !isError);
        toast.classList.toggle('text-emerald-700', !isError);
        toast.classList.remove('hidden');
        toastTimer = window.setTimeout(() => toast.classList.add('hidden'), 3500);
    };

    const request = async (url, method, payload) => {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload === undefined ? undefined : JSON.stringify(payload),
        });
        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(result.message || 'Unable to update the account.');
            error.errors = result.errors;
            throw error;
        }

        return result;
    };

    const addCell = (row, text, className = '') => {
        const cell = row.insertCell();
        cell.textContent = text;
        if (className) cell.className = className;
        return cell;
    };

    const createActionButton = (label, action, code, className, disabled = false) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.dataset.action = action;
        button.dataset.code = code;
        button.disabled = disabled;
        button.className = `${className} rounded-md px-2.5 py-1.5 text-[11px] font-medium transition disabled:cursor-not-allowed disabled:opacity-45`;
        return button;
    };

    const createAccountRow = (account) => {
        const row = document.createElement('tr');
        row.className = 'apm-table-row dashboard-enter';
        addCell(row, account.code, 'apm-code');
        addCell(row, account.name, 'apm-account-name');
        addCell(row, account.type);
        addCell(row, account.sub_type);
        addCell(row, currency.format(Number(account.balance)), `apm-money ${Number(account.balance) < 0 ? 'text-red-600' : ''}`);

        const statusCell = row.insertCell();
        if (account.status === 'Active') {
            const badge = document.createElement('span');
            badge.className = 'apm-active-badge';
            badge.textContent = 'Active';
            statusCell.append(badge);
        } else {
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.dataset.action = 'reactivate';
            badge.dataset.code = account.code;
            badge.title = 'Reactivate account';
            badge.className = 'inline-flex cursor-pointer rounded-md bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-600 transition hover:bg-emerald-100 hover:text-emerald-700';
            badge.textContent = 'Inactive';
            statusCell.append(badge);
        }

        const actionCell = row.insertCell();
        actionCell.className = 'whitespace-nowrap px-4 py-3';
        const actions = document.createElement('div');
        actions.className = 'flex items-center gap-1.5';
        actions.append(
            createActionButton('Edit', 'edit', account.code, 'bg-slate-100 text-slate-700 hover:bg-slate-200'),
            createActionButton(
                account.status === 'Active' ? 'Deactivate' : 'Deactivated',
                'deactivate',
                account.code,
                'bg-orange-50 text-orange-700 hover:bg-orange-100',
                account.status === 'Inactive',
            ),
            createActionButton('Delete', 'delete', account.code, 'bg-red-50 text-red-700 hover:bg-red-100'),
        );
        actionCell.append(actions);
        return row;
    };

    const filteredAccounts = () => {
        const search = searchInput.value.trim().toLowerCase();
        return accounts.filter((account) =>
            (!search || account.code.toLowerCase().includes(search) || account.name.toLowerCase().includes(search))
            && (!typeFilter.value || account.type === typeFilter.value)
            && (!statusFilter.value || account.status === statusFilter.value)
        );
    };

    const updateExportLinks = () => {
        [csvExport, pdfExport].forEach((link) => {
            const url = new URL(link.href);
            url.search = '';
            if (searchInput.value.trim()) url.searchParams.set('search', searchInput.value.trim());
            if (typeFilter.value) url.searchParams.set('type', typeFilter.value);
            if (statusFilter.value) url.searchParams.set('status', statusFilter.value);
            link.href = url.toString();
        });
    };

    const renderSummaryCards = () => {
        document.querySelectorAll('.apm-summary-card').forEach((card) => {
            const type = card.querySelector('p')?.textContent.trim();
            const matches = accounts.filter((account) => account.type === type);
            const balance = matches.reduce((total, account) => total + Number(account.balance), 0);
            card.querySelector('strong').textContent = currency.format(balance);
            card.querySelector('span').textContent = `${matches.length} ${pluralize(matches.length, 'account')}`;
        });
    };

    const renderPagination = (pageCount) => {
        pagination.replaceChildren();
        if (pageCount <= 1) return;

        const addButton = (label, pageNumber, disabled = false, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = disabled;
            button.className = `apm-page-button ${active ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-500' : ''}`;
            if (active) button.setAttribute('aria-current', 'page');
            button.addEventListener('click', () => {
                currentPage = pageNumber;
                renderAccounts();
            });
            pagination.append(button);
        };

        addButton('‹ Prev', currentPage - 1, currentPage === 1);
        for (let pageNumber = 1; pageNumber <= pageCount; pageNumber += 1) {
            addButton(String(pageNumber), pageNumber, false, pageNumber === currentPage);
        }
        addButton('Next ›', currentPage + 1, currentPage === pageCount);
    };

    const renderAccounts = () => {
        const filtered = filteredAccounts();
        const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        const start = (currentPage - 1) * pageSize;
        const visible = filtered.slice(start, start + pageSize);
        tableBody.replaceChildren();

        if (visible.length === 0) {
            const row = document.createElement('tr');
            const cell = addCell(row, accounts.length === 0 ? 'No accounts yet. Select + New Account to add one.' : 'No accounts match the current filters.', 'px-4 py-10 text-center text-xs text-slate-500');
            cell.colSpan = 7;
            tableBody.append(row);
        } else {
            visible.forEach((account) => tableBody.append(createAccountRow(account)));
        }

        accountCount.textContent = `${filtered.length} ${pluralize(filtered.length, 'account')}`;
        recordCount.textContent = filtered.length === 0
            ? 'Showing 0 records'
            : `Showing ${start + 1}–${start + visible.length} of ${filtered.length} records`;
        renderPagination(pageCount);
        renderSummaryCards();
        updateExportLinks();
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submitting) return;

        clearErrors();
        submitting = true;
        submitButton.disabled = true;
        submitButton.textContent = editingCode === null ? 'Adding...' : 'Saving...';
        const payload = {
            name: nameInput.value.trim(),
            type: typeInput.value,
            sub_type: subTypeInput.value.trim(),
            status: statusInput.value,
        };
        if (editingCode === null) payload.balance = 0;

        try {
            if (editingCode === null) {
                const result = await request(page.dataset.storeUrl, 'POST', payload);
                accounts.unshift(result.account);
                currentPage = 1;
                showToast(result.message);
            } else {
                const result = await request(endpoint(page.dataset.updateUrlTemplate, editingCode), 'PUT', payload);
                const index = accounts.findIndex((account) => account.code === editingCode);
                if (index !== -1) accounts[index] = result.account;
                showToast(result.message);
            }
            renderAccounts();
            closeModal();
        } catch (error) {
            displayErrors(error.errors);
            showToast(error.message, true);
        } finally {
            submitting = false;
            submitButton.disabled = false;
            submitButton.textContent = editingCode === null ? 'Add Account' : 'Save Changes';
        }
    });

    tableBody.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;
        const account = accounts.find((item) => item.code === button.dataset.code);
        if (!account) return;

        if (button.dataset.action === 'edit') {
            openEditModal(account);
            return;
        }

        try {
            if (button.dataset.action === 'deactivate' && account.status === 'Active') {
                const result = await request(endpoint(page.dataset.statusUrlTemplate, account.code), 'PATCH', { status: 'Inactive' });
                Object.assign(account, result.account);
                renderAccounts();
                showToast('Account deactivated. Select the Inactive badge to reactivate it.');
            } else if (button.dataset.action === 'reactivate' && account.status === 'Inactive') {
                const result = await request(endpoint(page.dataset.statusUrlTemplate, account.code), 'PATCH', { status: 'Active' });
                Object.assign(account, result.account);
                renderAccounts();
                showToast('Account reactivated successfully.');
            } else if (button.dataset.action === 'delete' && window.confirm(`Delete account ${account.code} - ${account.name}?`)) {
                const result = await request(endpoint(page.dataset.deleteUrlTemplate, account.code), 'DELETE');
                accounts.splice(accounts.indexOf(account), 1);
                renderAccounts();
                showToast(result.message);
            }
        } catch (error) {
            showToast(error.message, true);
        }
    });

    [searchInput, typeFilter, statusFilter].forEach((control) => control.addEventListener('input', () => {
        currentPage = 1;
        renderAccounts();
    }));
    openButton.addEventListener('click', openCreateModal);
    modal.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', closeModal));
    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !submitting) closeModal();
    });
    window.addEventListener('beforeprint', () => {
        const filtered = filteredAccounts();
        tableBody.replaceChildren();
        if (filtered.length === 0) {
            const row = document.createElement('tr');
            const cell = addCell(row, 'No accounts match the current filters.', 'px-4 py-10 text-center text-xs text-slate-500');
            cell.colSpan = 7;
            tableBody.append(row);
        } else {
            filtered.forEach((account) => tableBody.append(createAccountRow(account)));
        }
    });
    window.addEventListener('afterprint', renderAccounts);
    renderAccounts();
};

document.addEventListener('DOMContentLoaded', setupChartOfAccounts);
