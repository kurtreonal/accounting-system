const setupUsersSettings = () => {
    const page = document.querySelector('#users-settings-page');
    if (!page) return;

    const data = JSON.parse(document.querySelector('#users-settings-data').textContent);
    const users = data.users || [];
    const currentUserId = Number(data.currentUserId);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const rows = document.querySelector('#user-rows');
    const userForm = document.querySelector('#user-form');
    const passwordForm = document.querySelector('#user-password-form');
    const pageSize = 8;
    let currentPage = 1;
    let sort = { field: 'name', direction: 'asc' };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
    const initials = (name) => String(name).trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    const roleStyle = (role) => ({
        Administrator: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
        Accountant: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
        'Encoder / Staff': 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
        'Viewer / Auditor': 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    })[role] || 'bg-slate-100 text-slate-600';
    const avatarStyle = (role) => ({ Administrator: 'bg-blue-600', Accountant: 'bg-emerald-600', 'Encoder / Staff': 'bg-amber-500', 'Viewer / Auditor': 'bg-slate-500' })[role] || 'bg-slate-500';
    const userUrl = (id, suffix = '') => page.dataset.userUrl + '/' + id + suffix;
    const setModal = (name, open) => {
        const modal = document.querySelector('#user-' + name + '-modal');
        modal.classList.toggle('hidden', !open);
        modal.classList.toggle('flex', open);
        modal.setAttribute('aria-hidden', String(!open));
        document.body.classList.toggle('overflow-hidden', open);
    };
    const request = async (url, method, payload = {}) => {
        const response = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload) });
        let result;
        try { result = await response.json(); } catch (_) { result = { message: 'Server returned invalid response.' }; }
        return { response, result };
    };
    const showMessage = (target, message, success = false) => {
        target.textContent = message;
        target.className = 'mt-3 rounded-lg p-3 text-xs ' + (success ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300');
    };

    const filtered = () => {
        const search = document.querySelector('#user-search').value.trim().toLowerCase();
        const role = document.querySelector('#user-role-filter').value;
        const department = document.querySelector('#user-department-filter').value;
        const status = document.querySelector('#user-status-filter').value;
        return users.filter((user) => (!search || [user.name, user.email, user.employee_code, user.position].join(' ').toLowerCase().includes(search))
            && (!role || user.role === role)
            && (!department || user.department === department)
            && (!status || (status === 'active') === Boolean(user.active)))
            .sort((left, right) => String(left[sort.field] || '').localeCompare(String(right[sort.field] || '')) * (sort.direction === 'asc' ? 1 : -1));
    };

    const render = () => {
        const filteredUsers = filtered();
        const pages = Math.max(1, Math.ceil(filteredUsers.length / pageSize));
        currentPage = Math.min(currentPage, pages);
        const start = (currentPage - 1) * pageSize;
        const visible = filteredUsers.slice(start, start + pageSize);
        rows.innerHTML = visible.length ? visible.map((user) => {
            const own = Number(user.id) === currentUserId;
            return '<tr class="apm-table-row">' +
                '<td><div class="flex items-center gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-full text-[10px] font-bold text-white ' + avatarStyle(user.role) + '">' + escapeHtml(initials(user.name)) + '</span><div><p class="font-semibold text-slate-800 dark:text-slate-100">' + escapeHtml(user.name) + '</p><p class="font-mono text-[10px] text-slate-400">' + escapeHtml(user.employee_code) + '</p></div></div></td>' +
                '<td><p>' + escapeHtml(user.department) + '</p><p class="mt-0.5 text-[10px] text-slate-500">' + escapeHtml(user.position) + '</p></td>' +
                '<td>' + escapeHtml(user.email) + '</td><td><span class="inline-flex rounded-md px-2 py-1 text-[10px] font-semibold ' + roleStyle(user.role) + '">' + escapeHtml(user.role) + '</span></td>' +
                '<td><span class="rounded-md bg-slate-50 px-2 py-1 text-[10px] dark:bg-slate-800">' + escapeHtml(user.employment_type) + '</span></td>' +
                '<td><span class="inline-flex rounded-md px-2 py-1 text-[10px] font-medium ' + (user.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800') + '">' + (user.active ? 'Active' : 'Inactive') + '</span></td>' +
                '<td class="text-right"><div class="flex justify-end gap-1"><button type="button" data-edit-user="' + user.id + '" class="apm-row-action">Edit</button><button type="button" data-reset-user="' + user.id + '" class="apm-row-action">Reset PW</button><button type="button" data-status-user="' + user.id + '" class="apm-row-action ' + (own ? 'opacity-50' : '') + '" ' + (own ? 'disabled title="Current account cannot be deactivated"' : '') + '>' + (user.active ? 'Deactivate' : 'Activate') + '</button></div></td></tr>';
        }).join('') : '<tr><td colspan="7" class="px-5 py-14 text-center text-slate-500"><i class="fa-solid fa-users text-2xl text-slate-300"></i><p class="mt-2">No users match selected filters.</p></td></tr>';
        document.querySelector('[data-user-count]').textContent = filteredUsers.length;
        document.querySelector('#user-page-summary').textContent = filteredUsers.length ? 'Showing ' + (start + 1) + '–' + Math.min(start + pageSize, filteredUsers.length) + ' of ' + filteredUsers.length : 'Showing 0 records';
        document.querySelector('#user-page-number').textContent = currentPage;
        document.querySelector('#user-prev').disabled = currentPage === 1;
        document.querySelector('#user-next').disabled = currentPage === pages;
    };

    const openUserForm = (user = null) => {
        userForm.reset();
        userForm.querySelectorAll('[data-user-error]').forEach((element) => { element.textContent = ''; });
        userForm.querySelector('[data-user-message]').classList.add('hidden');
        userForm.elements.user_id.value = user?.id || '';
        ['employee_code', 'name', 'email', 'role', 'department', 'position', 'employment_type'].forEach((field) => { if (user) userForm.elements[field].value = user[field] ?? ''; });
        const passwordFields = userForm.querySelector('[data-new-password-fields]');
        passwordFields.classList.toggle('hidden', Boolean(user));
        passwordFields.classList.toggle('contents', !user);
        userForm.elements.password.required = !user;
        userForm.elements.password_confirmation.required = !user;
        document.querySelector('#user-form-title').textContent = user ? 'Edit ' + user.name : 'New User';
        setModal('form', true);
    };

    document.querySelectorAll('[data-settings-tab]').forEach((button) => button.addEventListener('click', () => {
        document.querySelectorAll('[data-settings-tab]').forEach((tab) => {
            const active = tab === button;
            tab.setAttribute('aria-selected', String(active));
            tab.classList.toggle('border-blue-600', active);
            tab.classList.toggle('text-blue-600', active);
        });
        document.querySelectorAll('[data-settings-panel]').forEach((panel) => { panel.hidden = panel.dataset.settingsPanel !== button.dataset.settingsTab; });
    }));

    document.querySelector('#new-user').addEventListener('click', () => openUserForm());
    rows.addEventListener('click', async (event) => {
        const edit = event.target.closest('[data-edit-user]');
        if (edit) { openUserForm(users.find((user) => Number(user.id) === Number(edit.dataset.editUser))); return; }
        const reset = event.target.closest('[data-reset-user]');
        if (reset) {
            const user = users.find((item) => Number(item.id) === Number(reset.dataset.resetUser));
            passwordForm.reset();
            passwordForm.elements.user_id.value = user.id;
            passwordForm.querySelector('[data-password-user]').textContent = user.name + ' · ' + user.email;
            passwordForm.querySelector('[data-password-message]').classList.add('hidden');
            setModal('password', true);
            return;
        }
        const statusButton = event.target.closest('[data-status-user]');
        if (!statusButton) return;
        const user = users.find((item) => Number(item.id) === Number(statusButton.dataset.statusUser));
        if (!confirm((user.active ? 'Deactivate ' : 'Activate ') + user.name + '?')) return;
        statusButton.disabled = true;
        try {
            const { response, result } = await request(userUrl(user.id, '/status'), 'PATCH', { active: !user.active });
            if (!response.ok) { alert(result.message || 'Unable to update account status.'); statusButton.disabled = false; return; }
            location.reload();
        } catch (_) { alert('Network error. Account status not changed.'); statusButton.disabled = false; }
    });

    userForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        userForm.querySelectorAll('[data-user-error]').forEach((element) => { element.textContent = ''; });
        const values = Object.fromEntries(new FormData(userForm));
        const id = values.user_id;
        delete values.user_id;
        if (id) { delete values.password; delete values.password_confirmation; }
        const submit = userForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const { response, result } = await request(id ? userUrl(id) : page.dataset.userUrl, id ? 'PUT' : 'POST', values);
            if (!response.ok) {
                Object.entries(result.errors || {}).forEach(([field, messages]) => {
                    const target = userForm.querySelector('[data-user-error="' + CSS.escape(field) + '"]');
                    if (target) target.textContent = messages[0];
                });
                showMessage(userForm.querySelector('[data-user-message]'), result.message || 'Unable to save user.');
                submit.disabled = false;
                return;
            }
            showMessage(userForm.querySelector('[data-user-message]'), result.message, true);
            setTimeout(() => location.reload(), 450);
        } catch (_) { showMessage(userForm.querySelector('[data-user-message]'), 'Network error. User not saved.'); submit.disabled = false; }
    });

    passwordForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const values = Object.fromEntries(new FormData(passwordForm));
        const id = values.user_id;
        delete values.user_id;
        const submit = passwordForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            const { response, result } = await request(userUrl(id, '/password'), 'POST', values);
            showMessage(passwordForm.querySelector('[data-password-message]'), result.message || (response.ok ? 'Password reset.' : 'Unable to reset password.'), response.ok);
            if (response.ok) setTimeout(() => setModal('password', false), 600);
            else submit.disabled = false;
        } catch (_) { showMessage(passwordForm.querySelector('[data-password-message]'), 'Network error. Password not changed.'); submit.disabled = false; }
    });

    const setupSettingsForm = (selector, url, messageSelector) => {
        const form = document.querySelector(selector);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = form.querySelector('[type="submit"]');
            submit.disabled = true;
            try {
                const { response, result } = await request(url, 'PUT', Object.fromEntries(new FormData(form)));
                showMessage(form.querySelector(messageSelector), result.message || (response.ok ? 'Settings saved.' : 'Unable to save settings.'), response.ok);
                submit.disabled = false;
            } catch (_) { showMessage(form.querySelector(messageSelector), 'Network error. Settings not saved.'); submit.disabled = false; }
        });
    };
    setupSettingsForm('#company-settings-form', page.dataset.companyUrl, '[data-company-message]');
    setupSettingsForm('#system-settings-form', page.dataset.systemUrl, '[data-system-message]');

    const resetForm = document.querySelector('#demo-reset-form');
    const resetOpen = document.querySelector('#open-demo-reset');
    const resetCancel = document.querySelector('#cancel-demo-reset');
    const resetError = resetForm.querySelector('[data-reset-error]');
    const resetStatus = resetForm.querySelector('[data-reset-status]');
    const resetSubmit = resetForm.querySelector('[type="submit"]');
    let resetTimer = null;
    let resetRunning = false;
    let resetAttempt = 0;

    const clearResetTimer = () => {
        if (resetTimer !== null) window.clearInterval(resetTimer);
        resetTimer = null;
    };
    const closeReset = () => {
        resetAttempt++;
        clearResetTimer();
        resetRunning = false;
        resetSubmit.disabled = false;
        resetCancel.disabled = false;
        resetForm.elements.confirmation.readOnly = false;
        resetForm.reset();
        resetForm.hidden = true;
        resetError.classList.add('hidden');
        resetStatus.classList.add('hidden');
        resetOpen.disabled = false;
    };
    resetOpen.addEventListener('click', () => {
        resetForm.hidden = false;
        resetOpen.disabled = true;
        resetForm.elements.confirmation.focus();
    });
    resetCancel.addEventListener('click', closeReset);
    resetForm.elements.confirmation.addEventListener('input', () => resetError.classList.add('hidden'));
    resetForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (resetRunning) return;

        const confirmation = resetForm.elements.confirmation.value.trim();
        if (confirmation !== 'RESET DEMO DATA') {
            resetError.textContent = 'Confirmation must exactly match RESET DEMO DATA.';
            resetError.classList.remove('hidden');
            resetForm.elements.confirmation.focus();
            return;
        }

        resetError.classList.add('hidden');
        resetRunning = true;
        const attempt = ++resetAttempt;
        resetSubmit.disabled = true;
        resetForm.elements.confirmation.readOnly = true;
        resetStatus.classList.remove('hidden');
        resetStatus.textContent = 'Validating reset request…';

        try {
            const prepared = await request(page.dataset.resetPrepareUrl, 'POST', { confirmation });
            if (attempt !== resetAttempt) return;
            if (!prepared.response.ok) throw new Error(prepared.result.message || 'Reset validation failed.');

            let remaining = Number(prepared.result.wait_seconds || 5);
            resetStatus.textContent = 'Resetting all demo data in ' + remaining + ' seconds…';
            resetTimer = window.setInterval(async () => {
                if (attempt !== resetAttempt) { clearResetTimer(); return; }
                remaining--;
                if (remaining > 0) {
                    resetStatus.textContent = 'Resetting all demo data in ' + remaining + ' seconds…';
                    return;
                }

                clearResetTimer();
                resetCancel.disabled = true;
                resetStatus.textContent = 'Restoring original demo data…';
                try {
                    const completed = await request(page.dataset.resetUrl, 'POST', { confirmation, token: prepared.result.token });
                    if (!completed.response.ok) throw new Error(completed.result.message || 'Demo data reset failed.');
                    resetStatus.textContent = completed.result.message;
                    window.setTimeout(() => window.location.reload(), 700);
                } catch (error) {
                    resetStatus.classList.add('hidden');
                    resetError.textContent = error.message || 'Demo data reset failed.';
                    resetError.classList.remove('hidden');
                    resetRunning = false;
                    resetSubmit.disabled = false;
                    resetCancel.disabled = false;
                    resetForm.elements.confirmation.readOnly = false;
                }
            }, 1000);
        } catch (error) {
            if (attempt !== resetAttempt) return;
            resetStatus.classList.add('hidden');
            resetError.textContent = error.message || 'Unable to start demo reset.';
            resetError.classList.remove('hidden');
            resetRunning = false;
            resetSubmit.disabled = false;
            resetCancel.disabled = false;
            resetForm.elements.confirmation.readOnly = false;
        }
    });

    document.querySelectorAll('[data-user-close]').forEach((button) => button.addEventListener('click', () => setModal(button.dataset.userClose, false)));
    ['#user-search', '#user-role-filter', '#user-department-filter', '#user-status-filter'].forEach((selector) => document.querySelector(selector).addEventListener('input', () => { currentPage = 1; render(); }));
    document.querySelectorAll('[data-user-sort]').forEach((button) => button.addEventListener('click', () => {
        const field = button.dataset.userSort;
        sort = { field, direction: sort.field === field && sort.direction === 'asc' ? 'desc' : 'asc' };
        render();
    }));
    document.querySelector('#user-prev').addEventListener('click', () => { currentPage--; render(); });
    document.querySelector('#user-next').addEventListener('click', () => { currentPage++; render(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { ['form', 'password'].forEach((name) => setModal(name, false)); closeReset(); } });
    render();
};

document.addEventListener('DOMContentLoaded', setupUsersSettings);
