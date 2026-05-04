export const byId = (id) => document.getElementById(id);

export const asInt = (value, fallback = 0) => {
    const parsed = Number.parseInt(value, 10);

    return Number.isNaN(parsed) ? fallback : parsed;
};

export const postFormJson = async (url, formData, extraHeaders = {}) => {
    const response = await fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...extraHeaders,
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
};
