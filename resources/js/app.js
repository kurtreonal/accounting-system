import '@fortawesome/fontawesome-free/css/all.min.css';
import './chart-of-accounts';
import './journal-entries';
import './general-ledger';
import './sales-revenue';
import './accounts-receivable';
import './accounts-payable';

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

document.addEventListener('DOMContentLoaded', setupThemeToggle);
document.addEventListener('DOMContentLoaded', setupSidebarToggle);
document.addEventListener('DOMContentLoaded', setupPagePrinting);
