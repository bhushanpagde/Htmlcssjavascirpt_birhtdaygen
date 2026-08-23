(() => {
    'use strict';
    const DB_NAME = 'birthday-studio';
    const DB_VERSION = 2;
    const elements = {};
    let database;

    document.addEventListener('DOMContentLoaded', initialize);

    async function initialize() {
        ['newFarewellButton', 'farewellDialog', 'farewellForm', 'farewellClose', 'farewellCancel', 'farewellEmployee']
            .forEach(id => elements[id] = document.getElementById(id));
        database = await openDatabase();
        elements.newFarewellButton.addEventListener('click', openForm);
        elements.farewellClose.addEventListener('click', () => elements.farewellDialog.close());
        elements.farewellCancel.addEventListener('click', () => elements.farewellDialog.close());
        elements.farewellForm.addEventListener('submit', event => event.preventDefault());
        await loadEmployees();
    }

    async function loadEmployees() {
        const employees = await getAll('employees');
        employees.sort((a, b) => a.fullName.localeCompare(b.fullName));
        elements.farewellEmployee.replaceChildren(new Option('Select an employee', ''));
        employees.forEach(employee => elements.farewellEmployee.add(new Option(`${employee.fullName} (${employee.id})`, employee.id)));
        elements.newFarewellButton.disabled = employees.length === 0;
    }

    function openForm() {
        elements.farewellForm.reset();
        elements.farewellDialog.showModal();
    }

    function openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => {
                const db = request.result;
                ['employees', 'photos', 'cards', 'files', 'certificates'].forEach(store => {
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
