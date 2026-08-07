const setupChartOfAccounts = () => {
    const modal = document.querySelector('#new-account-modal');
    const modalPanel = document.querySelector('#new-account-modal-panel');
    const modalTitle = document.querySelector('#new-account-modal-title');
    const openButton = document.querySelector('#new-account-button');
    const form = document.querySelector('#new-account-form');

    if (!modal || !openButton || !form) return;

    const codeInput = document.querySelector('#account-code');
    const nameInput = document.querySelector('#account-name');
    const typeInput = document.querySelector('#account-type');
    const subTypeInput = document.querySelector('#account-sub-type');
    const balanceInput = document.querySelector('#account-balance');
    const statusInput = document.querySelector('#account-status');
    const tableBody = document.querySelector('#accounts-table-body');
    const accountCount = document.querySelector('#account-count');
    const recordCount = document.querySelector('#accounts-record-count');
    const searchInput = document.querySelector('#account-search');
    const typeFilter = document.querySelector('#account-type-filter');
    const statusFilter = document.querySelector('#account-status-filter');
    const toast = document.querySelector('#account-toast');
    const submitButton = document.querySelector('#create-account-button');
    const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const accounts = [];
    let nextCode = 1;
    let editingCode = null;
    let toastTimer;

    const pluralize = (count, singular, plural = `${singular}s`) => count === 1 ? singular : plural;

    const clearErrors = () => {
        form.querySelectorAll('[data-error]').forEach((error) => {
            error.textContent = '';
        });
    };

    const playFadeUp = (element) => {
        element.classList.remove('dashboard-enter');
        void element.offsetWidth;
        element.classList.add('dashboard-enter');
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
        codeInput.value = String(nextCode);
        modalTitle.textContent = 'New Account';
        submitButton.textContent = 'Add Account';
        showModal();
    };

    const openEditModal = (account) => {
        editingCode = account.code;
        codeInput.value = account.code;
        nameInput.value = account.name;
        typeInput.value = account.type;
        subTypeInput.value = account.subType;
        balanceInput.value = account.balance.toFixed(2);
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

    const showError = (field, message) => {
        const error = form.querySelector(`[data-error="${field}"]`);
        if (error) error.textContent = message;
    };

    const readAccount = () => {
        clearErrors();

        const balanceValue = balanceInput.value.trim();
        const account = {
            code: editingCode ?? String(nextCode),
            name: nameInput.value.trim(),
            type: typeInput.value,
            subType: subTypeInput.value.trim(),
            balance: Number(balanceValue),
            status: statusInput.value,
        };
        let isValid = true;

        if (!account.name) {
            showError('accountName', 'Account name is required.');
            isValid = false;
        }
        if (!account.type) {
            showError('type', 'Account type is required.');
            isValid = false;
        }
        if (!account.subType) {
            showError('subType', 'Sub-type is required.');
            isValid = false;
        }
        if (!balanceValue || !Number.isFinite(account.balance) || account.balance < 0) {
            showError('balance', 'Enter a valid non-negative balance.');
            isValid = false;
        }

        return isValid ? account : null;
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
        addCell(row, account.subType);
        addCell(row, currency.format(account.balance), 'apm-money');

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

    const renderSummaryCards = () => {
        document.querySelectorAll('.apm-summary-card').forEach((card) => {
            const type = card.querySelector('p')?.textContent.trim();
            const matchingAccounts = accounts.filter((account) => account.type === type);
            const balance = matchingAccounts.reduce((total, account) => total + account.balance, 0);

            card.querySelector('strong').textContent = currency.format(balance);
            card.querySelector('span').textContent = `${matchingAccounts.length} ${pluralize(matchingAccounts.length, 'account')}`;
        });
    };

    const renderAccounts = () => {
        const visibleAccounts = filteredAccounts();
        tableBody.replaceChildren();

        if (visibleAccounts.length === 0) {
            const row = document.createElement('tr');
            const cell = addCell(
                row,
                accounts.length === 0
                    ? 'No accounts yet. Select + New Account to add one.'
                    : 'No accounts match the current search or filters.',
                'px-4 py-10 text-center text-xs text-slate-500',
            );
            cell.colSpan = 7;
            tableBody.append(row);
        } else {
            visibleAccounts.forEach((account) => tableBody.append(createAccountRow(account)));
        }

        accountCount.textContent = `${visibleAccounts.length} ${pluralize(visibleAccounts.length, 'account')}`;
        recordCount.textContent = `Showing ${visibleAccounts.length} ${pluralize(visibleAccounts.length, 'record')}`;
        renderSummaryCards();
    };

    const showSuccess = (message) => {
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.remove('hidden');
        toastTimer = window.setTimeout(() => toast.classList.add('hidden'), 3000);
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitButton.disabled = true;
        const account = readAccount();

        if (account) {
            if (editingCode === null) {
                accounts.push(account);
                nextCode += 1;
                showSuccess('Account added successfully.');
            } else {
                const index = accounts.findIndex((item) => item.code === editingCode);
                if (index !== -1) accounts[index] = account;
                showSuccess('Account updated successfully.');
            }

            renderAccounts();
            closeModal();
        }

        submitButton.disabled = false;
    });

    tableBody.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const account = accounts.find((item) => item.code === button.dataset.code);
        if (!account) return;

        if (button.dataset.action === 'edit') {
            openEditModal(account);
            return;
        }

        if (button.dataset.action === 'deactivate' && account.status === 'Active') {
            account.status = 'Inactive';
            renderAccounts();
            showSuccess('Account deactivated. Select the Inactive badge to reactivate it.');
            return;
        }

        if (button.dataset.action === 'reactivate' && account.status === 'Inactive') {
            account.status = 'Active';
            renderAccounts();
            showSuccess('Account reactivated successfully.');
            return;
        }

        if (button.dataset.action === 'delete' && window.confirm(`Delete account ${account.code} - ${account.name}?`)) {
            accounts.splice(accounts.indexOf(account), 1);
            renderAccounts();
            showSuccess('Account deleted successfully.');
        }
    });

    [searchInput, typeFilter, statusFilter].forEach((control) => control.addEventListener('input', renderAccounts));
    openButton.addEventListener('click', openCreateModal);
    modal.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', closeModal));
    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });

    renderAccounts();
};

document.addEventListener('DOMContentLoaded', setupChartOfAccounts);
