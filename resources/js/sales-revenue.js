const setupSalesRevenue = () => {
    const page = document.querySelector('#sales-revenue-page');
    const dataElement = document.querySelector('#sales-data');
    if (!page || !dataElement) return;

    const data = JSON.parse(dataElement.textContent || '{"customers":[],"invoices":[]}');
    const customerModal = document.querySelector('#customers-modal');
    const invoiceModal = document.querySelector('#invoice-modal');
    const viewModal = document.querySelector('#invoice-view-modal');
    const customerForm = document.querySelector('#customer-form');
    const invoiceForm = document.querySelector('#invoice-form');
    const linesBody = document.querySelector('#invoice-lines');
    const lineTemplate = document.querySelector('#invoice-line-template');
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const csrf = invoiceForm?.querySelector('input[name="_token"]')?.value || '';
    let submitIntent = 'draft';
    let activeInvoice = null;

    const endpoint = (template, value) => template.replace('__INVOICE__', encodeURIComponent(value));

    const openModal = (modal) => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        modal.querySelector('input:not([type="hidden"]), select, button')?.focus();
    };

    const closeModal = (modal) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        if (![customerModal, invoiceModal, viewModal].some((item) => !item.classList.contains('hidden'))) {
            document.body.classList.remove('overflow-hidden');
        }
    };

    const showMessage = (form, message, success = false) => {
        const element = form.querySelector('[data-form-message]');
        element.textContent = message;
        element.className = `mt-3 rounded-lg px-3 py-2 text-xs ${success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`;
    };

    const clearErrors = (form) => {
        form.querySelectorAll('[data-error]').forEach((element) => { element.textContent = ''; });
        form.querySelector('[data-form-message]')?.classList.add('hidden');
    };

    const showErrors = (form, result) => {
        showMessage(form, result.message || 'Unable to save.');
        Object.entries(result.errors || {}).forEach(([field, messages]) => {
            const target = form.querySelector(`[data-error="${CSS.escape(field)}"]`);
            if (target) target.textContent = Array.isArray(messages) ? messages[0] : messages;
        });
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.headers || {}),
            },
        });
        const result = await response.json();
        if (!response.ok) throw Object.assign(new Error(result.message || 'Request failed.'), { result });
        return result;
    };

    const calculateLines = () => {
        let subtotal = 0;
        let tax = 0;
        linesBody.querySelectorAll('tr').forEach((row) => {
            const quantity = Number(row.querySelector('[data-line="quantity"]').value || 0);
            const price = Number(row.querySelector('[data-line="unit_price"]').value || 0);
            const rate = Number(row.querySelector('[data-line="tax_rate"]').value || 0);
            const lineSubtotal = quantity * price;
            const lineTax = lineSubtotal * (rate / 100);
            subtotal += lineSubtotal;
            tax += lineTax;
            row.querySelector('[data-line-total]').textContent = money.format(lineSubtotal + lineTax);
        });
        document.querySelector('#invoice-subtotal').textContent = money.format(subtotal);
        document.querySelector('#invoice-tax').textContent = money.format(tax);
        document.querySelector('#invoice-total').textContent = money.format(subtotal + tax);
    };

    const addLine = () => {
        linesBody.append(lineTemplate.content.cloneNode(true));
        calculateLines();
    };

    const invoicePayload = () => ({
        customer_id: invoiceForm.elements.customer_id.value,
        invoice_date: invoiceForm.elements.invoice_date.value,
        due_date: invoiceForm.elements.due_date.value,
        reference: invoiceForm.elements.reference.value.trim(),
        memo: invoiceForm.elements.memo.value.trim(),
        lines: [...linesBody.querySelectorAll('tr')].map((row) => ({
            description: row.querySelector('[data-line="description"]').value.trim(),
            quantity: row.querySelector('[data-line="quantity"]').value,
            unit_price: row.querySelector('[data-line="unit_price"]').value,
            tax_rate: row.querySelector('[data-line="tax_rate"]').value,
        })),
    });

    const postInvoice = async (invoiceNumber, button) => {
        button.disabled = true;
        const original = button.innerHTML;
        button.textContent = 'Posting...';
        try {
            await request(endpoint(page.dataset.postUrlTemplate, invoiceNumber), { method: 'POST', body: '{}' });
            window.location.reload();
        } catch (problem) {
            button.disabled = false;
            button.innerHTML = original;
            window.alert(problem.result?.message || problem.message);
        }
    };

    const createDetailRow = (label, value, valueClass = '') => {
        const wrapper = document.createElement('div');
        wrapper.className = 'flex justify-between gap-4 border-b border-slate-100 pb-2';
        const term = document.createElement('span');
        term.className = 'text-slate-500';
        term.textContent = label;
        const detail = document.createElement('strong');
        detail.className = `text-right font-medium text-slate-800 ${valueClass}`;
        detail.textContent = value;
        wrapper.append(term, detail);
        return wrapper;
    };

    const viewInvoice = (invoice) => {
        activeInvoice = invoice;
        document.querySelector('#invoice-view-title').textContent = invoice.invoice_number;
        document.querySelector('#invoice-view-status').textContent = invoice.display_status;
        const content = document.querySelector('#invoice-view-content');
        content.replaceChildren(
            createDetailRow('Customer', invoice.customer_name),
            createDetailRow('Invoice date', invoice.invoice_date, 'font-mono'),
            createDetailRow('Due date', invoice.due_date, 'font-mono'),
            createDetailRow('Reference', invoice.reference || '—'),
            createDetailRow('Line items', String(invoice.lines.length)),
            createDetailRow('Total', money.format(Number(invoice.total)), 'font-mono text-base'),
            createDetailRow('Remaining', money.format(Number(invoice.remaining_balance)), 'font-mono'),
            createDetailRow('Journal entry', invoice.journal_entry_id || 'Not posted', 'font-mono'),
        );
        const printLink = document.querySelector('#invoice-view-print');
        printLink.href = endpoint(page.dataset.printUrlTemplate, invoice.invoice_number);
        const postButton = document.querySelector('#invoice-view-post');
        const canPost = invoice.status === 'Draft' && ['Administrator', 'Accountant'].includes(page.dataset.userRole);
        postButton.classList.toggle('hidden', !canPost);
        openModal(viewModal);
    };

    document.querySelector('#manage-customers-button')?.addEventListener('click', () => openModal(customerModal));
    document.querySelector('#new-invoice-button')?.addEventListener('click', () => {
        if (data.customers.length === 0) {
            openModal(customerModal);
            showMessage(customerForm, 'Add an active customer before creating an invoice.');
            return;
        }
        if (linesBody.children.length === 0) addLine();
        openModal(invoiceModal);
    });
    document.querySelector('#add-invoice-line')?.addEventListener('click', addLine);
    linesBody?.addEventListener('input', calculateLines);
    linesBody?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-line]');
        if (!button) return;
        if (linesBody.children.length === 1) {
            window.alert('Invoice needs at least one line item.');
            return;
        }
        button.closest('tr').remove();
        calculateLines();
    });
    invoiceForm?.querySelectorAll('[data-invoice-intent]').forEach((button) => {
        button.addEventListener('click', () => { submitIntent = button.dataset.invoiceIntent; });
    });
    invoiceForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors(invoiceForm);
        const buttons = invoiceForm.querySelectorAll('button[type="submit"]');
        buttons.forEach((button) => { button.disabled = true; });
        let savedInvoice = null;
        try {
            const result = await request(page.dataset.invoiceUrl, { method: 'POST', body: JSON.stringify(invoicePayload()) });
            savedInvoice = result.invoice;
            if (submitIntent === 'post') {
                await request(endpoint(page.dataset.postUrlTemplate, result.invoice.invoice_number), { method: 'POST', body: '{}' });
            }
            showMessage(invoiceForm, submitIntent === 'post' ? 'Invoice posted.' : 'Invoice saved as draft.', true);
            window.setTimeout(() => window.location.reload(), 500);
        } catch (problem) {
            if (savedInvoice) {
                showMessage(invoiceForm, `Invoice ${savedInvoice.invoice_number} was saved as draft, but posting failed: ${problem.result?.message || problem.message}`);
                window.setTimeout(() => window.location.reload(), 1800);
                return;
            }
            showErrors(invoiceForm, problem.result || { message: problem.message });
            buttons.forEach((button) => { button.disabled = false; });
        }
    });
    customerForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors(customerForm);
        const button = customerForm.querySelector('button[type="submit"]');
        button.disabled = true;
        const body = Object.fromEntries(new FormData(customerForm));
        delete body._token;
        try {
            await request(page.dataset.customerUrl, { method: 'POST', body: JSON.stringify(body) });
            showMessage(customerForm, 'Customer created.', true);
            window.setTimeout(() => window.location.reload(), 500);
        } catch (problem) {
            showErrors(customerForm, problem.result || { message: problem.message });
            button.disabled = false;
        }
    });
    page.addEventListener('click', (event) => {
        const button = event.target.closest('[data-view-invoice]');
        if (!button) return;
        const invoice = data.invoices.find((item) => item.invoice_number === button.dataset.viewInvoice);
        if (invoice) viewInvoice(invoice);
    });
    document.querySelector('#invoice-view-post')?.addEventListener('click', (event) => {
        if (activeInvoice) postInvoice(activeInvoice.invoice_number, event.currentTarget);
    });
    document.querySelectorAll('[data-sales-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('[role="dialog"]')));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        [customerModal, invoiceModal, viewModal].forEach((modal) => {
            if (!modal.classList.contains('hidden')) closeModal(modal);
        });
    });

    const requested = new URLSearchParams(window.location.search).get('new');
    if (requested === 'invoice') document.querySelector('#new-invoice-button')?.click();
    const requestedInvoice = new URLSearchParams(window.location.search).get('invoice');
    const invoice = data.invoices.find((item) => item.invoice_number === requestedInvoice);
    if (invoice) viewInvoice(invoice);
};

document.addEventListener('DOMContentLoaded', setupSalesRevenue);
