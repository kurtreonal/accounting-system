const setupJournalEntries = () => {
    const page = document.querySelector('#journal-entries-page');
    const dataElement = document.querySelector('#journal-entry-data');
    const modal = document.querySelector('#journal-modal');
    const form = document.querySelector('#journal-form');
    const lineTemplate = document.querySelector('#journal-line-template');

    if (!page || !dataElement || !modal || !form || !lineTemplate) return;

    const entries = JSON.parse(dataElement.textContent || '[]');
    const userRole = page.dataset.userRole;
    const canMutate = userRole !== 'Viewer / Auditor';
    const canApprove = ['Administrator', 'Accountant'].includes(userRole);
    const csrfToken = form.querySelector('input[name="_token"]').value;
    const modalPanel = document.querySelector('#journal-modal-panel');
    const modalTitle = document.querySelector('#journal-modal-title');
    const modalStatus = document.querySelector('#journal-modal-status');
    const newButton = document.querySelector('#new-journal-button');
    const addLineButton = document.querySelector('#add-journal-line');
    const saveButton = document.querySelector('#save-journal-draft');
    const saveSubmitButton = document.querySelector('#save-submit-journal');
    const modalPrint = document.querySelector('#journal-modal-print');
    const numberInput = document.querySelector('#journal-number');
    const dateInput = document.querySelector('#journal-date');
    const referenceInput = document.querySelector('#journal-reference');
    const sourceInput = document.querySelector('#journal-source');
    const descriptionInput = document.querySelector('#journal-description');
    const linesBody = document.querySelector('#journal-lines');
    const totalDebit = document.querySelector('#journal-total-debit');
    const totalCredit = document.querySelector('#journal-total-credit');
    const balanceState = document.querySelector('#journal-balance-state');
    const tableBody = document.querySelector('#journal-table-body');
    const searchInput = document.querySelector('#journal-search');
    const statusFilter = document.querySelector('#journal-status-filter');
    const tabs = document.querySelector('#journal-tabs');
    const countText = document.querySelector('#journal-count');
    const recordCount = document.querySelector('#journal-record-count');
    const pagination = document.querySelector('#journal-pagination');
    const exportLink = document.querySelector('#journal-export');
    const toast = document.querySelector('#journal-toast');
    const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const pageSize = 10;
    let currentPage = 1;
    let activeStatus = '';
    let editingNumber = null;
    let viewing = false;
    let submitting = false;
    let toastTimer;

    const endpoint = (template, journalNumber) => template.replace('__NUMBER__', encodeURIComponent(journalNumber));
    const pluralize = (count, singular, plural = `${singular}s`) => count === 1 ? singular : plural;
    const today = () => {
        const date = new Date();
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    };

    const showToast = (message, isError = false) => {
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.className = `fixed right-4 bottom-4 z-[60] max-w-sm rounded-lg border bg-white px-4 py-3 text-xs font-medium shadow-lg ${isError ? 'border-red-200 text-red-700' : 'border-emerald-200 text-emerald-700'}`;
        toastTimer = window.setTimeout(() => toast.classList.add('hidden'), 4000);
    };

    const request = async (url, method = 'POST', payload) => {
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
            const error = new Error(result.message || 'Unable to update journal entry.');
            error.errors = result.errors || {};
            throw error;
        }

        return result;
    };

    const clearErrors = () => {
        form.querySelectorAll('[data-journal-error], [data-line-error]').forEach((element) => {
            element.textContent = '';
        });
    };

    const displayErrors = (errors = {}) => {
        Object.entries(errors).forEach(([field, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : messages;
            const lineMatch = field.match(/^lines\.(\d+)\.(account_code|amount)$/);
            if (lineMatch) {
                const row = linesBody.querySelectorAll('[data-journal-line]')[Number(lineMatch[1])];
                const target = row?.querySelector(`[data-line-error="${lineMatch[2]}"]`);
                if (target) target.textContent = message;
                return;
            }

            const target = form.querySelector(`[data-journal-error="${field.split('.')[0]}"]`);
            if (target && !target.textContent) target.textContent = message;
        });
    };

    const lineValues = () => [...linesBody.querySelectorAll('[data-journal-line]')].map((row) => ({
        account_code: row.querySelector('[data-line-field="account_code"]').value,
        description: row.querySelector('[data-line-field="description"]').value.trim(),
        party_reference: row.querySelector('[data-line-field="party_reference"]').value.trim(),
        cost_center: row.querySelector('[data-line-field="cost_center"]').value.trim(),
        debit: row.querySelector('[data-line-field="debit"]').value,
        credit: row.querySelector('[data-line-field="credit"]').value,
    }));

    const calculateTotals = () => {
        const lines = lineValues();
        const debit = lines.reduce((sum, line) => sum + (Number(line.debit) || 0), 0);
        const credit = lines.reduce((sum, line) => sum + (Number(line.credit) || 0), 0);
        const balanced = lines.length >= 2 && debit > 0 && credit > 0 && Math.abs(debit - credit) < 0.005;
        totalDebit.textContent = currency.format(debit);
        totalCredit.textContent = currency.format(credit);
        balanceState.textContent = balanced ? 'Entry balanced and ready for review.' : `Entry not balanced. Difference: ${currency.format(Math.abs(debit - credit))}`;
        balanceState.className = balanced
            ? 'mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700'
            : 'mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800';
        saveSubmitButton.disabled = !balanced || submitting;
        saveSubmitButton.classList.toggle('opacity-50', !balanced);
        saveSubmitButton.classList.toggle('cursor-not-allowed', !balanced);

        return balanced;
    };

    const addLine = (line = {}, readOnly = false) => {
        const row = lineTemplate.content.firstElementChild.cloneNode(true);
        Object.entries(line).forEach(([field, value]) => {
            const control = row.querySelector(`[data-line-field="${field}"]`);
            if (control) control.value = Number.isFinite(value) ? String(value) : (value ?? '');
        });
        row.querySelectorAll('input, select').forEach((control) => {
            control.disabled = readOnly;
        });
        row.querySelector('[data-remove-line]').classList.toggle('hidden', readOnly);
        linesBody.append(row);
        calculateTotals();
    };

    const setReadOnly = (readOnly) => {
        [dateInput, referenceInput, sourceInput, descriptionInput].forEach((control) => {
            control.disabled = readOnly;
        });
        linesBody.querySelectorAll('input, select').forEach((control) => {
            control.disabled = readOnly;
        });
        linesBody.querySelectorAll('[data-remove-line]').forEach((button) => button.classList.toggle('hidden', readOnly));
        addLineButton.classList.toggle('hidden', readOnly);
        saveButton.classList.toggle('hidden', readOnly);
        saveSubmitButton.classList.toggle('hidden', readOnly);
    };

    const showModal = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        modalPanel.classList.remove('dashboard-enter');
        void modalPanel.offsetWidth;
        modalPanel.classList.add('dashboard-enter');
    };

    const resetForm = () => {
        form.reset();
        clearErrors();
        linesBody.replaceChildren();
        editingNumber = null;
        viewing = false;
        numberInput.value = 'Generated when saved';
        dateInput.value = today();
        sourceInput.value = 'Manual';
        modalPrint.classList.add('hidden');
        addLine({}, false);
        addLine({}, false);
        setReadOnly(false);
        calculateTotals();
    };

    const openCreate = () => {
        if (!canMutate) return;
        resetForm();
        modalTitle.textContent = 'New Journal Entry';
        modalStatus.textContent = 'Draft - journal number generated when saved';
        saveButton.textContent = 'Save Draft';
        showModal();
        dateInput.focus();
    };

    const fillForm = (entry, readOnly) => {
        form.reset();
        clearErrors();
        linesBody.replaceChildren();
        editingNumber = entry.journal_number;
        viewing = readOnly;
        numberInput.value = entry.journal_number;
        dateInput.value = entry.date;
        referenceInput.value = entry.reference || '';
        sourceInput.value = entry.source_type;
        descriptionInput.value = entry.description;
        entry.lines.forEach((line) => addLine(line, readOnly));
        modalTitle.textContent = readOnly ? `View ${entry.journal_number}` : `Edit ${entry.journal_number}`;
        const linkText = entry.reversal_entry_number
            ? ` - Reversal entry: ${entry.reversal_entry_number}`
            : entry.reversal_of ? ` - Reversal of: ${entry.reversal_of}` : '';
        modalStatus.textContent = `${entry.status}${linkText}`;
        modalPrint.href = endpoint(page.dataset.printUrlTemplate, entry.journal_number);
        modalPrint.classList.toggle('hidden', !readOnly);
        saveButton.textContent = 'Save Changes';
        setReadOnly(readOnly);
        calculateTotals();
        showModal();
    };

    const closeModal = () => {
        if (submitting) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        editingNumber = null;
        viewing = false;
    };

    const payload = () => ({
        date: dateInput.value,
        reference: referenceInput.value.trim(),
        description: descriptionInput.value.trim(),
        source_type: sourceInput.value,
        lines: lineValues(),
    });

    const clientValid = (mustBalance = false) => {
        clearErrors();
        let valid = true;
        if (!dateInput.value) {
            form.querySelector('[data-journal-error="date"]').textContent = 'Transaction date is required.';
            valid = false;
        }
        if (!descriptionInput.value.trim()) {
            form.querySelector('[data-journal-error="description"]').textContent = 'Description is required.';
            valid = false;
        }

        const rows = [...linesBody.querySelectorAll('[data-journal-line]')];
        if (rows.length < 2) {
            form.querySelector('[data-journal-error="lines"]').textContent = 'Add at least two journal lines.';
            valid = false;
        }
        rows.forEach((row) => {
            const account = row.querySelector('[data-line-field="account_code"]');
            const debit = Number(row.querySelector('[data-line-field="debit"]').value) || 0;
            const credit = Number(row.querySelector('[data-line-field="credit"]').value) || 0;
            if (!account.value) {
                row.querySelector('[data-line-error="account_code"]').textContent = 'Account is required.';
                valid = false;
            }
            if ((debit > 0) === (credit > 0)) {
                row.querySelector('[data-line-error="amount"]').textContent = 'Enter debit or credit, not both.';
                valid = false;
            }
        });
        if (mustBalance && !calculateTotals()) {
            form.querySelector('[data-journal-error="lines"]').textContent = 'Balance entry before submitting for review.';
            valid = false;
        }
        return valid;
    };

    const saveDraft = async () => {
        if (submitting || viewing || !clientValid(false)) return null;
        submitting = true;
        saveButton.disabled = true;
        saveSubmitButton.disabled = true;
        const originalLabel = saveButton.textContent;
        saveButton.textContent = 'Saving...';

        try {
            const result = editingNumber
                ? await request(endpoint(page.dataset.updateUrlTemplate, editingNumber), 'PUT', payload())
                : await request(page.dataset.storeUrl, 'POST', payload());
            const index = entries.findIndex((entry) => entry.journal_number === result.entry.journal_number);
            if (index === -1) entries.unshift(result.entry);
            else entries[index] = result.entry;
            editingNumber = result.entry.journal_number;
            showToast(result.message);
            renderEntries();
            return result.entry;
        } catch (error) {
            displayErrors(error.errors);
            showToast(error.message, true);
            return null;
        } finally {
            submitting = false;
            saveButton.disabled = false;
            saveButton.textContent = originalLabel;
            calculateTotals();
        }
    };

    const runAction = async (entry, action) => {
        const labels = {
            submit: ['Submit this journal entry for review?', page.dataset.submitUrlTemplate],
            return: ['Return this journal entry to draft?', page.dataset.returnUrlTemplate],
            post: ['Post this journal entry? Posted entries cannot be edited or deleted.', page.dataset.postUrlTemplate],
            reverse: ['Reverse this posted journal entry? An offsetting entry will be created.', page.dataset.reverseUrlTemplate],
            delete: ['Delete this draft journal entry?', page.dataset.deleteUrlTemplate],
        };
        const [confirmation, template] = labels[action];
        if (!window.confirm(confirmation)) return;

        try {
            const result = await request(endpoint(template, entry.journal_number), action === 'delete' ? 'DELETE' : 'POST');
            if (action === 'delete') {
                entries.splice(entries.indexOf(entry), 1);
            } else if (action === 'reverse') {
                const index = entries.findIndex((item) => item.journal_number === entry.journal_number);
                entries[index] = result.entry;
                entries.unshift(result.reversal);
            } else {
                const index = entries.findIndex((item) => item.journal_number === entry.journal_number);
                entries[index] = result.entry;
            }
            showToast(result.message);
            renderEntries();
        } catch (error) {
            showToast(error.message, true);
        }
    };

    const addCell = (row, text, className = '') => {
        const cell = row.insertCell();
        cell.textContent = text;
        if (className) cell.className = className;
        return cell;
    };

    const actionButton = (label, action, entry, style = 'text-slate-600 hover:bg-blue-100 hover:text-blue-700') => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.dataset.action = action;
        button.dataset.number = entry.journal_number;
        button.className = `cursor-pointer rounded-md px-2.5 py-1.5 text-[11px] font-medium transition duration-150 active:scale-95 ${style}`;
        return button;
    };

    const statusBadge = (status) => {
        const badge = document.createElement('span');
        const styles = {
            Draft: 'bg-slate-100 text-slate-600',
            'For Review': 'bg-amber-100 text-amber-700',
            Posted: 'bg-emerald-100 text-emerald-700',
            Reversed: 'bg-red-100 text-red-700',
        };
        badge.className = `inline-flex rounded-md px-2 py-1 text-[10px] font-medium ${styles[status] || styles.Draft}`;
        badge.textContent = status;
        return badge;
    };

    const createRow = (entry) => {
        const row = document.createElement('tr');
        row.className = 'apm-table-row dashboard-enter';
        const numberCell = row.insertCell();
        const numberButton = actionButton(entry.journal_number, 'view', entry, 'font-mono text-blue-600 hover:bg-blue-50 hover:text-blue-800');
        numberCell.append(numberButton);
        addCell(row, entry.date, 'font-mono text-[11px]');
        addCell(row, entry.description, 'font-medium text-slate-800');
        addCell(row, entry.reference || '-', 'font-mono text-[11px]');
        addCell(row, currency.format(Number(entry.total_debit)), 'apm-money');
        addCell(row, entry.created_by?.name || '-', 'whitespace-nowrap');
        const statusCell = row.insertCell();
        statusCell.append(statusBadge(entry.status));
        const actionsCell = row.insertCell();
        actionsCell.className = 'whitespace-nowrap px-4 py-3';
        const actions = document.createElement('div');
        actions.className = 'flex flex-wrap items-center gap-1';
        actions.append(actionButton('View', 'view', entry));

        if (entry.status === 'Draft' && canMutate) {
            actions.append(actionButton('Edit', 'edit', entry), actionButton('Submit', 'submit', entry, 'bg-blue-50 text-blue-700 hover:bg-blue-100'), actionButton('Delete', 'delete', entry, 'text-red-600 hover:bg-red-50'));
        } else if (entry.status === 'For Review' && canApprove) {
            actions.append(actionButton('Return', 'return', entry, 'bg-amber-50 text-amber-700 hover:bg-amber-100'), actionButton('Post', 'post', entry, 'bg-blue-600 text-white hover:bg-blue-500'));
        } else if (entry.status === 'Posted') {
            actions.append(actionButton('Print', 'print', entry));
            if (canApprove && !entry.reversal_of) actions.append(actionButton('Reverse', 'reverse', entry, 'text-red-600 hover:bg-red-50'));
        } else if (entry.status === 'Reversed') {
            actions.append(actionButton('Print', 'print', entry));
        }

        actionsCell.append(actions);
        return row;
    };

    const filteredEntries = () => {
        const search = searchInput.value.trim().toLowerCase();
        const status = activeStatus || statusFilter.value;
        return entries.filter((entry) => {
            const haystack = `${entry.journal_number} ${entry.reference || ''} ${entry.description}`.toLowerCase();
            return (!search || haystack.includes(search)) && (!status || entry.status === status);
        });
    };

    const renderPagination = (pageCount) => {
        pagination.replaceChildren();
        if (pageCount <= 1) return;
        const addButton = (label, targetPage, disabled = false, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = disabled;
            button.className = `apm-page-button ${active ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-500' : ''}`;
            button.addEventListener('click', () => {
                currentPage = targetPage;
                renderEntries();
            });
            pagination.append(button);
        };
        addButton('Prev', currentPage - 1, currentPage === 1);
        for (let number = 1; number <= pageCount; number += 1) addButton(String(number), number, false, number === currentPage);
        addButton('Next', currentPage + 1, currentPage === pageCount);
    };

    const updateExport = () => {
        const url = new URL(exportLink.href);
        url.search = '';
        if (searchInput.value.trim()) url.searchParams.set('search', searchInput.value.trim());
        const status = activeStatus || statusFilter.value;
        if (status) url.searchParams.set('status', status);
        exportLink.href = url.toString();
    };

    const renderEntries = () => {
        const filtered = filteredEntries();
        const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        const start = (currentPage - 1) * pageSize;
        const visible = filtered.slice(start, start + pageSize);
        tableBody.replaceChildren();
        if (visible.length === 0) {
            const row = document.createElement('tr');
            const cell = addCell(row, entries.length === 0 ? 'No journal entries yet. Select New Journal Entry to create one.' : 'No journal entries match current filters.', 'px-4 py-10 text-center text-xs text-slate-500');
            cell.colSpan = 8;
            tableBody.append(row);
        } else {
            visible.forEach((entry) => tableBody.append(createRow(entry)));
        }
        countText.textContent = `${filtered.length} ${pluralize(filtered.length, 'entry', 'entries')}`;
        recordCount.textContent = filtered.length === 0 ? 'Showing 0 records' : `Showing ${start + 1}-${start + visible.length} of ${filtered.length} records`;
        renderPagination(pageCount);
        updateExport();
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const saved = await saveDraft();
        if (saved) closeModal();
    });
    saveSubmitButton.addEventListener('click', async () => {
        if (!clientValid(true)) return;
        const saved = await saveDraft();
        if (!saved) return;
        try {
            submitting = true;
            const result = await request(endpoint(page.dataset.submitUrlTemplate, saved.journal_number));
            const index = entries.findIndex((entry) => entry.journal_number === saved.journal_number);
            entries[index] = result.entry;
            showToast(result.message);
            renderEntries();
            submitting = false;
            closeModal();
        } catch (error) {
            submitting = false;
            showToast(error.message, true);
        }
    });
    newButton?.addEventListener('click', openCreate);
    addLineButton.addEventListener('click', () => addLine({}, false));
    linesBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-line]');
        if (!button || viewing) return;
        button.closest('[data-journal-line]').remove();
        calculateTotals();
    });
    linesBody.addEventListener('input', (event) => {
        const input = event.target.closest('[data-line-field="debit"], [data-line-field="credit"]');
        if (input && Number(input.value) > 0) {
            const opposite = input.dataset.lineField === 'debit' ? 'credit' : 'debit';
            input.closest('[data-journal-line]').querySelector(`[data-line-field="${opposite}"]`).value = '';
        }
        calculateTotals();
    });
    tableBody.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const entry = entries.find((item) => item.journal_number === button.dataset.number);
        if (!entry) return;
        if (button.dataset.action === 'view') fillForm(entry, true);
        else if (button.dataset.action === 'edit') fillForm(entry, false);
        else if (button.dataset.action === 'print') window.open(endpoint(page.dataset.printUrlTemplate, entry.journal_number), '_blank', 'noopener');
        else runAction(entry, button.dataset.action);
    });
    tabs.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-status]');
        if (!tab) return;
        activeStatus = tab.dataset.status;
        statusFilter.value = activeStatus;
        tabs.querySelectorAll('[data-status]').forEach((item) => {
            const active = item === tab;
            item.setAttribute('aria-selected', String(active));
            item.classList.toggle('border-blue-600', active);
            item.classList.toggle('text-blue-600', active);
        });
        currentPage = 1;
        renderEntries();
    });
    [searchInput, statusFilter].forEach((control) => control.addEventListener('input', () => {
        if (control === statusFilter) {
            activeStatus = control.value;
            tabs.querySelectorAll('[data-status]').forEach((item) => {
                const active = item.dataset.status === activeStatus;
                item.setAttribute('aria-selected', String(active));
                item.classList.toggle('border-blue-600', active);
                item.classList.toggle('text-blue-600', active);
            });
        }
        currentPage = 1;
        renderEntries();
    }));
    modal.querySelectorAll('[data-journal-close]').forEach((button) => button.addEventListener('click', closeModal));
    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });
    document.querySelector('#journal-print-list').addEventListener('click', () => window.print());
    renderEntries();
    const requestedEntry = new URLSearchParams(window.location.search).get('entry');
    const linkedEntry = entries.find((entry) => entry.journal_number === requestedEntry);
    if (linkedEntry) fillForm(linkedEntry, true);
};

document.addEventListener('DOMContentLoaded', setupJournalEntries);
