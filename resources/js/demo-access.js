const accessElement = () => typeof document === 'undefined' ? null : document.querySelector('#demo-access');

export const hasPermission = (permissions, permission) => Array.isArray(permissions)
    && (permissions.includes('*') || permissions.includes(permission));

export const currentAccess = () => {
    const element = accessElement();
    if (!element) return { role: '', permissions: [] };

    try {
        const value = JSON.parse(element.textContent || '{}');
        return {
            role: typeof value.role === 'string' ? value.role : '',
            permissions: Array.isArray(value.permissions) ? value.permissions : [],
        };
    } catch (_) {
        return { role: '', permissions: [] };
    }
};

export const can = (permission) => {
    const { permissions } = currentAccess();
    return hasPermission(permissions, permission);
};

export const isViewer = () => currentAccess().role === 'Viewer / Auditor';
