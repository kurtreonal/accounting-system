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

const setupPagePrinting = () => {
    const updateGeneratedTime = () => {
        document.querySelectorAll('[data-print-generated]').forEach((element) => {
            element.textContent = new Date().toLocaleString('en-PH', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            });
        });
    };

    document.querySelectorAll('[data-print-page]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
    window.addEventListener('beforeprint', updateGeneratedTime);
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
document.addEventListener('DOMContentLoaded', setupSidebarToggle);
document.addEventListener('DOMContentLoaded', setupPagePrinting);
document.addEventListener('DOMContentLoaded', setupProfileLogout);
