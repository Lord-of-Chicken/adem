let toastRoot = null;

function ensureToastRoot() {
    if (toastRoot) {
        return toastRoot;
    }

    toastRoot = document.createElement('div');
    toastRoot.className = 'ui-toast-stack';
    document.body.appendChild(toastRoot);

    return toastRoot;
}

export function showToast(message, type = 'info') {
    if (!message) {
        return;
    }

    const root = ensureToastRoot();
    const toast = document.createElement('div');
    toast.className = `ui-toast ui-toast--${type}`;
    toast.textContent = message;

    root.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.add('ui-toast--visible');
    });

    window.setTimeout(() => {
        toast.classList.remove('ui-toast--visible');
        window.setTimeout(() => {
            toast.remove();
        }, 220);
    }, 3200);
}
