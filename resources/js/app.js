import '@fortawesome/fontawesome-free/css/all.min.css';
import './chart-of-accounts';
import './journal-entries';
import './general-ledger';
import './sales-revenue';
import './accounts-receivable';
import './accounts-payable';
import './cash-bank';
import './expenses';
import './financial-reports';
import './tax-settings';
import './audit-trail';
import './users-settings';
import './record-details';

const setupSidebarToggle = () => {
    const button = document.querySelector('#sidebar-toggle');
    const overlay = document.querySelector('#sidebar-overlay');

    if (!button || !overlay) return;

    const root = document.documentElement;
    const desktop = window.matchMedia('(min-width: 64rem)');

    const setDesktopState = (collapsed, persist = true) => {
        root.classList.toggle('sidebar-collapsed', collapsed);
        button.setAttribute('aria-expanded', String(!collapsed));
        button.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
        button.title = collapsed ? 'Expand navigation' : 'Collapse navigation';

        if (persist) {
            try {
                localStorage.setItem('apm-sidebar-collapsed', String(collapsed));
            } catch (_) {
                // The sidebar still works if storage is unavailable.
            }
        }
    };

    const setMobileState = (open) => {
        root.classList.toggle('sidebar-mobile-open', open);
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        button.title = open ? 'Close navigation' : 'Open navigation';
    };

    let savedCollapsed = false;
    try {
        savedCollapsed = localStorage.getItem('apm-sidebar-collapsed') === 'true';
    } catch (_) {
        // Use the expanded default when storage is unavailable.
    }

    root.classList.toggle('sidebar-collapsed', savedCollapsed);
    if (desktop.matches) setDesktopState(savedCollapsed, false);
    else setMobileState(false);

    button.addEventListener('click', () => {
        if (desktop.matches) setDesktopState(!root.classList.contains('sidebar-collapsed'));
        else setMobileState(!root.classList.contains('sidebar-mobile-open'));
    });

    overlay.addEventListener('click', () => setMobileState(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('sidebar-mobile-open')) setMobileState(false);
    });

    desktop.addEventListener('change', (event) => {
        root.classList.remove('sidebar-mobile-open');
        if (event.matches) setDesktopState(root.classList.contains('sidebar-collapsed'), false);
        else setMobileState(false);
    });
};

const setupThemeToggle = () => {
    const button = document.querySelector('#theme-toggle');

    if (!button) return;

    const root = document.documentElement;
    const themeColor = document.querySelector('meta[name="theme-color"]');

    const applyTheme = (theme, persist = true) => {
        const isDark = theme === 'dark';
        const nextLabel = isDark ? 'Switch to light mode' : 'Switch to dark mode';

        root.classList.toggle('dark', isDark);
        root.dataset.theme = theme;
        button.setAttribute('aria-label', nextLabel);
        button.setAttribute('aria-pressed', String(isDark));
        button.title = nextLabel;

        if (themeColor) themeColor.content = isDark ? '#020617' : '#0f172a';

        if (persist) {
            try {
                localStorage.setItem('apm-theme', theme);
            } catch (_) {
                // The visual toggle still works if storage is unavailable.
            }
        }
    };

    applyTheme(root.classList.contains('dark') ? 'dark' : 'light', false);
    button.addEventListener('click', () => applyTheme(root.classList.contains('dark') ? 'light' : 'dark'));
};

const setupDemoUserSwitcher = () => {
    const toggle = document.querySelector('#demo-user-toggle');
    const menu = document.querySelector('#demo-user-menu');

    if (!toggle || !menu) return;

    const close = (restoreFocus = false) => {
        menu.classList.add('hidden');
        menu.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus) toggle.focus();
    };

    const position = () => {
        const rect = toggle.getBoundingClientRect();
        const gap = 8;
        const menuWidth = menu.offsetWidth || 288;
        menu.style.left = `${Math.max(gap, Math.min(window.innerWidth - menuWidth - gap, rect.right - menuWidth))}px`;
        menu.style.top = `${Math.min(window.innerHeight - menu.offsetHeight - gap, rect.bottom + gap)}px`;
    };

    const open = () => {
        menu.classList.remove('hidden');
        menu.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
        position();
        menu.querySelector('button:not(:disabled)')?.focus();
    };

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        if (menu.classList.contains('hidden')) open();
        else close(true);
    });

    menu.querySelectorAll('[data-demo-user-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
        });
    });

    document.addEventListener('click', (event) => {
        if (!menu.classList.contains('hidden') && !menu.contains(event.target)) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.classList.contains('hidden')) close(true);
    });
    window.addEventListener('resize', () => {
        if (!menu.classList.contains('hidden')) position();
    });
};

const setupProfilePicture = () => {
    const openButton = document.querySelector('[data-profile-picture-open]');
    const modal = document.querySelector('#profile-picture-modal');
    const input = modal?.querySelector('#profile-picture-input');
    const editor = modal?.querySelector('[data-profile-picture-editor]');
    const canvas = modal?.querySelector('[data-profile-picture-canvas]');
    const saveButton = modal?.querySelector('[data-profile-picture-save]');
    const removeButton = modal?.querySelector('[data-profile-picture-remove]');
    const message = modal?.querySelector('[data-profile-picture-message]');

    if (!openButton || !modal || !input || !editor || !canvas || !saveButton || !removeButton || !message) return;

    const context = canvas.getContext('2d');
    let sourceImage = null;
    let sourceUrl = null;
    let busy = false;
    let offsetX = 0;
    let offsetY = 0;
    let dragPointer = null;
    let previousPointerX = 0;
    let previousPointerY = 0;

    const showMessage = (text, type = 'error') => {
        message.textContent = text;
        message.classList.remove('hidden', 'border-red-200', 'bg-red-50', 'text-red-700', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
        message.classList.add(...(type === 'success'
            ? ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700']
            : ['border-red-200', 'bg-red-50', 'text-red-700']));
    };

    const clearMessage = () => {
        message.textContent = '';
        message.classList.add('hidden');
    };

    const draw = () => {
        if (!sourceImage || !context) return;
        const size = 256;
        const scale = Math.max(size / sourceImage.naturalWidth, size / sourceImage.naturalHeight);
        const width = sourceImage.naturalWidth * scale;
        const height = sourceImage.naturalHeight * scale;
        const maxX = Math.max(0, (width - size) / 2);
        const maxY = Math.max(0, (height - size) / 2);
        offsetX = Math.max(-maxX, Math.min(maxX, offsetX));
        offsetY = Math.max(-maxY, Math.min(maxY, offsetY));
        const x = ((size - width) / 2) + offsetX;
        const y = ((size - height) / 2) + offsetY;

        context.clearRect(0, 0, size, size);
        context.drawImage(sourceImage, x, y, width, height);
    };

    const resetEditor = () => {
        if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        sourceUrl = null;
        sourceImage = null;
        offsetX = 0;
        offsetY = 0;
        dragPointer = null;
        input.value = '';
        editor.classList.add('hidden');
        saveButton.disabled = true;
        clearMessage();
        context?.clearRect(0, 0, 256, 256);
    };

    const close = () => {
        if (busy) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        resetEditor();
        document.querySelector('[data-profile-toggle]')?.focus();
    };

    const open = () => {
        const profileMenu = document.querySelector('#profile-menu');
        profileMenu?.classList.add('hidden');
        profileMenu?.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('[data-profile-toggle]').forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        input.focus();
    };

    const saveAvatar = async (avatarDataUrl) => {
        busy = true;
        saveButton.disabled = true;
        removeButton.disabled = true;
        const saveLabel = saveButton.querySelector('span');
        if (saveLabel) saveLabel.textContent = 'Saving...';
        clearMessage();

        try {
            const response = await fetch(modal.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ avatar_data_url: avatarDataUrl }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Unable to update the profile picture.');
            showMessage(result.message || 'Profile picture updated.', 'success');
            window.setTimeout(() => window.location.reload(), 350);
        } catch (error) {
            showMessage(error instanceof Error ? error.message : 'Unable to update the profile picture.');
            busy = false;
            saveButton.disabled = !sourceImage;
            removeButton.disabled = modal.dataset.hasAvatar !== 'true';
            if (saveLabel) saveLabel.textContent = 'Save picture';
        }
    };

    openButton.addEventListener('click', open);
    modal.querySelectorAll('[data-profile-picture-close]').forEach((button) => button.addEventListener('click', close));

    input.addEventListener('change', () => {
        clearMessage();
        const file = input.files?.[0];
        if (!file) return resetEditor();
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            resetEditor();
            return showMessage('Choose a JPG, PNG, or WebP image.');
        }
        if (file.size > 5 * 1024 * 1024) {
            resetEditor();
            return showMessage('The source image must be 5 MB or smaller.');
        }

        if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        sourceUrl = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            sourceImage = image;
            offsetX = 0;
            offsetY = 0;
            editor.classList.remove('hidden');
            saveButton.disabled = false;
            draw();
        };
        image.onerror = () => {
            resetEditor();
            showMessage('The selected image could not be read.');
        };
        image.src = sourceUrl;
    });

    canvas.addEventListener('pointerdown', (event) => {
        if (!sourceImage) return;
        dragPointer = event.pointerId;
        previousPointerX = event.clientX;
        previousPointerY = event.clientY;
        canvas.setPointerCapture(event.pointerId);
    });

    canvas.addEventListener('pointermove', (event) => {
        if (dragPointer !== event.pointerId || !sourceImage) return;
        const scale = 256 / canvas.getBoundingClientRect().width;
        offsetX += (event.clientX - previousPointerX) * scale;
        offsetY += (event.clientY - previousPointerY) * scale;
        previousPointerX = event.clientX;
        previousPointerY = event.clientY;
        draw();
    });

    const finishDragging = (event) => {
        if (dragPointer !== event.pointerId) return;
        dragPointer = null;
        if (canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
    };
    canvas.addEventListener('pointerup', finishDragging);
    canvas.addEventListener('pointercancel', finishDragging);

    canvas.addEventListener('keydown', (event) => {
        if (!sourceImage || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const movement = event.shiftKey ? 10 : 3;
        if (event.key === 'ArrowLeft') offsetX -= movement;
        if (event.key === 'ArrowRight') offsetX += movement;
        if (event.key === 'ArrowUp') offsetY -= movement;
        if (event.key === 'ArrowDown') offsetY += movement;
        draw();
    });

    saveButton.addEventListener('click', () => {
        if (!sourceImage || busy) return;
        const prompt = modal.dataset.hasAvatar === 'true'
            ? 'Replace your current profile picture with this crop?'
            : 'Use this crop as your profile picture?';
        if (!window.confirm(prompt)) return;
        saveAvatar(canvas.toDataURL('image/jpeg', 0.86));
    });

    removeButton.addEventListener('click', () => {
        if (busy || modal.dataset.hasAvatar !== 'true' || !window.confirm('Remove your profile picture and use the default user icon?')) return;
        saveAvatar(null);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
};

const setupPagePrinting = () => {
    const printSheet = document.querySelector('#apm-print-sheet');
    const tableContainer = printSheet?.querySelector('[data-print-tables]');

    const preparePrintTables = () => {
        if (!printSheet || !tableContainer) return;

        tableContainer.replaceChildren();

        const tables = [...document.querySelectorAll('#app-shell > main table')]
            .filter((table) => !table.closest('[hidden]') && !table.closest('.hidden'));

        tables.forEach((table) => {
            const clone = table.cloneNode(true);

            const sourceCells = [...table.querySelectorAll('th, td')];
            const clonedCells = [...clone.querySelectorAll('th, td')];
            clonedCells.forEach((cell, index) => {
                cell.style.textAlign = window.getComputedStyle(sourceCells[index]).textAlign;
            });

            clone.querySelectorAll('[class~="print:hidden"], [data-print-exclude]').forEach((element) => element.remove());
            clone.querySelectorAll('button, a').forEach((element) => element.replaceWith(document.createTextNode(element.textContent.trim())));
            clone.removeAttribute('class');
            clone.querySelectorAll('[class]').forEach((element) => element.removeAttribute('class'));
            tableContainer.append(clone);
        });

        if (tables.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'apm-print-empty';
            empty.textContent = 'No table data available.';
            tableContainer.append(empty);
        }
    };

    document.querySelectorAll('[data-print-page]').forEach((button) => {
        button.addEventListener('click', () => {
            preparePrintTables();
            window.print();
        });
    });
    window.addEventListener('beforeprint', () => {
        if (tableContainer?.childElementCount === 0) preparePrintTables();
    });
    window.addEventListener('afterprint', () => tableContainer?.replaceChildren());
};

const setupProfileLogout = () => {
    const toggles = [...document.querySelectorAll('[data-profile-toggle]')];
    const menu = document.querySelector('#profile-menu');
    const logoutButton = menu?.querySelector('[data-logout-open]');
    const modal = document.querySelector('#logout-confirmation-modal');
    const form = modal?.querySelector('[data-logout-form]');

    if (toggles.length === 0 || !menu || !logoutButton || !modal || !form) return;

    let activeToggle = null;

    const closeMenu = (restoreFocus = false) => {
        menu.classList.add('hidden');
        menu.setAttribute('aria-hidden', 'true');
        toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
        if (restoreFocus) activeToggle?.focus();
    };

    const positionMenu = (toggle) => {
        const rect = toggle.getBoundingClientRect();
        const gap = 8;
        const menuWidth = 256;
        const menuHeight = menu.offsetHeight || 176;
        const fromSidebar = Boolean(toggle.closest('#app-sidebar'));
        const left = fromSidebar
            ? Math.min(window.innerWidth - menuWidth - gap, rect.right + gap)
            : Math.max(gap, Math.min(window.innerWidth - menuWidth - gap, rect.right - menuWidth));
        const top = fromSidebar
            ? Math.max(gap, Math.min(window.innerHeight - menuHeight - gap, rect.bottom - menuHeight))
            : Math.min(window.innerHeight - menuHeight - gap, rect.bottom + gap);

        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;
    };

    const openMenu = (toggle) => {
        activeToggle = toggle;
        menu.classList.remove('hidden');
        menu.setAttribute('aria-hidden', 'false');
        toggles.forEach((item) => item.setAttribute('aria-expanded', String(item === toggle)));
        positionMenu(toggle);
        logoutButton.focus();
    };

    const closeModal = (restoreFocus = true) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        if (restoreFocus) activeToggle?.focus();
    };

    const openModal = () => {
        closeMenu();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        modal.querySelector('[data-logout-cancel]')?.focus();
    };

    toggles.forEach((toggle) => toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        if (!menu.classList.contains('hidden') && activeToggle === toggle) closeMenu(true);
        else openMenu(toggle);
    }));

    logoutButton.addEventListener('click', openModal);
    modal.querySelectorAll('[data-logout-cancel]').forEach((button) => button.addEventListener('click', () => closeModal()));
    form.addEventListener('submit', () => {
        const submit = form.querySelector('button[type="submit"]');
        if (!submit) return;
        submit.disabled = true;
        submit.querySelector('span').textContent = 'Logging out...';
    });

    document.addEventListener('click', (event) => {
        if (!menu.classList.contains('hidden') && !menu.contains(event.target) && !toggles.some((toggle) => toggle.contains(event.target))) closeMenu();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (!modal.classList.contains('hidden')) closeModal();
        else if (!menu.classList.contains('hidden')) closeMenu(true);
    });
    window.addEventListener('resize', () => {
        if (!menu.classList.contains('hidden') && activeToggle) positionMenu(activeToggle);
    });
};

document.addEventListener('DOMContentLoaded', setupThemeToggle);
document.addEventListener('DOMContentLoaded', setupDemoUserSwitcher);
document.addEventListener('DOMContentLoaded', setupProfilePicture);
document.addEventListener('DOMContentLoaded', setupSidebarToggle);
document.addEventListener('DOMContentLoaded', setupPagePrinting);
document.addEventListener('DOMContentLoaded', setupProfileLogout);
