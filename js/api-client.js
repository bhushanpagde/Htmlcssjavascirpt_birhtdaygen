(() => {
    'use strict';

    const scriptUrl = document.currentScript?.src || window.location.href;
    const apiBase = new URL('../api/', scriptUrl);

    async function request(path, options = {}) {
        const response = await fetch(new URL(path, apiBase), { cache: 'no-store', ...options });
        const contentType = response.headers.get('content-type') || '';
        const payload = contentType.includes('application/json') ? await response.json() : null;
        if (!response.ok || payload?.ok === false) {
            throw new Error(payload?.error?.message || `Server request failed (${response.status}).`);
        }
        return payload;
    }

    function json(method, body) {
        return { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
    }

    function form(values) {
        const body = new FormData();
        Object.entries(values).forEach(([key, value]) => body.append(key, value));
        return { method: 'POST', body };
    }

    window.HRCanvasAPI = Object.freeze({
        listEmployees: async () => (await request('employees.php')).employees,
        createEmployee: employee => request('employees.php', json('POST', employee)),
        updateEmployee: (id, employee) => request(`employees.php?id=${encodeURIComponent(id)}`, json('PUT', employee)),
        deleteEmployee: id => request(`employees.php?id=${encodeURIComponent(id)}`, { method: 'DELETE' }),
        uploadWorkbook: (workbook, employees) => request('workbooks.php', form({ workbook, employees: JSON.stringify(employees) })),
        listPhotos: async () => (await request('photos.php')).photos,
        getPhoto: async id => (await request(`photos.php?employeeId=${encodeURIComponent(id)}`)).photos[0],
        uploadPhoto: (employeeId, photo) => request('photos.php', form({ employeeId, photo })),
        deletePhoto: employeeId => request(`photos.php?employeeId=${encodeURIComponent(employeeId)}`, { method: 'DELETE' }),
        listCards: async () => (await request('cards.php')).cards,
        uploadCard: (employeeId, templateNumber, card) => request('cards.php', form({ employeeId, templateNumber: String(templateNumber), card })),
        deleteCard: employeeId => request(`cards.php?employeeId=${encodeURIComponent(employeeId)}`, { method: 'DELETE' }),
        fetchBlob: async url => {
            const response = await fetch(url, { cache: 'no-store' });
            if (!response.ok) throw new Error(`Could not load server file (${response.status}).`);
            return response.blob();
        }
    });
})();
