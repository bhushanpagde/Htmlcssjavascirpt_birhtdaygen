(() => {
    'use strict';
    const DB_NAME = 'birthday-studio';
    const DB_VERSION = 2;
    const UPCOMING_DAYS = 30;
    const elements = {};
    let database;
    let anniversaryRecords = [];

    document.addEventListener('DOMContentLoaded', initialize);

    async function initialize() {
        ['todayDate','todayCount','upcomingCount','missingCount','todayEmpty','todayGrid','anniversarySearch','upcomingEmpty','upcomingTableWrap','upcomingBody']
            .forEach(id => elements[id] = document.getElementById(id));
        database = await openDatabase();
        elements.anniversarySearch.addEventListener('input', renderUpcoming);
        elements.todayDate.textContent = new Date().toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' });
        await checkAnniversaries();
    }

    async function checkAnniversaries() {
        const employees = await getAll('employees');
        const today = startOfDay(new Date());
        let missing = 0;
        anniversaryRecords = employees.map(employee => {
            const joiningDate = parseEmployeeDate(employee.doj);
            if (!joiningDate) { missing += 1; return null; }
            const anniversaryDate = anniversaryInRange(joiningDate, today);
            const daysAway = Math.round((anniversaryDate - today) / 86400000);
            const years = anniversaryDate.getFullYear() - joiningDate.getFullYear();
            return { employee, joiningDate, anniversaryDate, daysAway, years };
        }).filter(record => record && record.years > 0 && record.daysAway >= 0 && record.daysAway <= UPCOMING_DAYS)
          .sort((a, b) => a.daysAway - b.daysAway || a.employee.fullName.localeCompare(b.employee.fullName));

        const todayRecords = anniversaryRecords.filter(record => record.daysAway === 0);
        elements.todayCount.textContent = todayRecords.length;
        elements.upcomingCount.textContent = anniversaryRecords.filter(record => record.daysAway > 0).length;
        elements.missingCount.textContent = missing;
        renderToday(todayRecords);
        renderUpcoming();
    }

    function renderToday(records) {
        elements.todayGrid.replaceChildren();
        records.forEach(record => {
            const card = document.createElement('article'); card.className = 'anniversary-person';
            const name = document.createElement('strong'); name.textContent = record.employee.fullName;
            const details = document.createElement('span'); details.textContent = `${record.employee.id} · ${record.employee.location || 'Location not set'}`;
            const badge = document.createElement('span'); badge.className = 'anniversary-badge'; badge.textContent = `${record.years} year${record.years === 1 ? '' : 's'} completed`;
            card.append(name, details, badge); elements.todayGrid.appendChild(card);
        });
        elements.todayEmpty.hidden = records.length > 0;
    }

    function renderUpcoming() {
        const query = normalize(elements.anniversarySearch.value);
        const visible = anniversaryRecords.filter(record => record.daysAway > 0 && normalize(`${record.employee.fullName} ${record.employee.location}`).includes(query));
        elements.upcomingBody.replaceChildren();
        visible.forEach(record => {
            const row = document.createElement('tr');
            [record.employee.id, record.employee.fullName, record.employee.location || '—', record.employee.doj, `${record.years} year${record.years === 1 ? '' : 's'}`, record.anniversaryDate.toLocaleDateString()]
                .forEach(value => appendCell(row, value));
            const status = document.createElement('td'); status.className = 'anniversary-status'; status.textContent = 'Awaiting anniversary template'; row.appendChild(status);
            elements.upcomingBody.appendChild(row);
        });
        elements.upcomingEmpty.hidden = visible.length > 0;
        elements.upcomingTableWrap.hidden = visible.length === 0;
    }

    function parseEmployeeDate(value) {
        const text = String(value || '').trim();
        if (!text) return null;
        const iso = text.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
        if (iso) return startOfDay(new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3])));
        const match = text.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);
        if (match) return startOfDay(new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1])));
        const direct = new Date(text);
        return Number.isNaN(direct.getTime()) ? null : startOfDay(direct);
    }

    function anniversaryInRange(joiningDate, today) {
        let anniversary = new Date(today.getFullYear(), joiningDate.getMonth(), joiningDate.getDate());
        if (anniversary < today) anniversary = new Date(today.getFullYear() + 1, joiningDate.getMonth(), joiningDate.getDate());
        return anniversary;
    }

    function startOfDay(date) { return new Date(date.getFullYear(), date.getMonth(), date.getDate()); }
    function normalize(value) { return String(value || '').trim().toLowerCase(); }
    function appendCell(row, value) { const cell = document.createElement('td'); cell.textContent = value; row.appendChild(cell); }

    function openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => {
                const db = request.result;
                ['employees','photos','cards','files','certificates'].forEach(store => {
                    if (!db.objectStoreNames.contains(store)) db.createObjectStore(store, { keyPath: 'id' });
                });
                if (!db.objectStoreNames.contains('settings')) db.createObjectStore('settings', { keyPath: 'key' });
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    function getAll(storeName) {
        return new Promise((resolve, reject) => {
            const request = database.transaction(storeName, 'readonly').objectStore(storeName).getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
})();
