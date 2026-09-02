(() => {
    'use strict';

    const OUTPUT_WIDTH = 1020;
    const OUTPUT_HEIGHT = 1900;
    const PHOTO_SIZE = 800;
    const PHOTO_TOP_OFFSET = 110;
    const TEMPLATE_COUNT = 27;
    const TEMPLATE_Y = [850, 735, 820, 800, 850, 850, 850, 850, 850, 850, 850, 820, 850, 800, 800, 850, 850, 850, 850, 850, 850, 850, 850, 820, 850, 820, 800];

    const elements = {};
    let pendingWorkbook = null;
    let pendingEmployees = [];
    let employees = [];
    let cards = [];
    const selectedCardIds = new Set();
    let dialogCard = null;

    document.addEventListener('DOMContentLoaded', initialize);

    async function initialize() {
        Object.assign(elements, {
            progressWrap: byId('progressWrap'), progressText: byId('progressText'), progressValue: byId('progressValue'), progressBar: byId('progressBar'),
            notice: byId('notice'),
            cardSearch: byId('cardSearch'), cardCount: byId('cardCount'), cardEmpty: byId('cardEmpty'), cardGrid: byId('cardGrid'), prepareEmailButton: byId('prepareEmailButton'), downloadAllButton: byId('downloadAllButton'),
            cardDialog: byId('cardDialog'), dialogClose: byId('dialogClose'),
            dialogImage: byId('dialogImage'), dialogName: byId('dialogName'), dialogDownload: byId('dialogDownload')
        });

        if (!window.JSZip) {
            showNotice('Required browser libraries could not be loaded.', 'error');
            return;
        }

        bindEvents();
        try { await refreshData(); } catch (error) { showNotice(`Could not connect to the server: ${error.message}`, 'error'); }
    }

    function bindEvents() {
        elements.cardSearch.addEventListener('input', renderCards);
        elements.prepareEmailButton.addEventListener('click', prepareBirthdayEmail);
        elements.downloadAllButton.addEventListener('click', downloadAllCards);
        elements.dialogClose.addEventListener('click', () => elements.cardDialog.close());
        elements.dialogDownload.addEventListener('click', () => dialogCard && downloadBlob(dialogCard.blob, cardFileName(dialogCard.fullName)));
        elements.cardDialog.addEventListener('close', () => { elements.dialogImage.src = ''; dialogCard = null; });
    }


    async function handleExcel(file) {
        if (!file) return;
        if (!/\.(xlsx|xlsm)$/i.test(file.name)) {
            showNotice('Please choose an XLSX or XLSM workbook.', 'error'); return;
        }
        try {
            setProgress(15, 'Reading workbook…');
            const buffer = await file.arrayBuffer();
            const workbook = XLSX.read(buffer, { type: 'array', cellDates: true });
            const sheet = workbook.Sheets[workbook.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: false, dateNF: 'yyyy-mm-dd', defval: '' });
            validateHeaders(rows[0] || []);
            const existingIds = new Set(employees.map(item => normalize(item.id)));
            const seenIds = new Set();
            pendingEmployees = rows.slice(1).map((row, index) => createEmployee(row, index + 2, existingIds, seenIds)).filter(Boolean);
            if (!pendingEmployees.length) throw new Error('No employee rows were found below the header.');
            pendingWorkbook = { file, blob: new Blob([buffer], { type: file.type || 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }) };
            renderExcelPreview();
            setProgress(100, 'Workbook ready');
            setTimeout(() => { elements.progressWrap.hidden = true; }, 500);
        } catch (error) {
            elements.progressWrap.hidden = true;
            pendingWorkbook = null; pendingEmployees = [];
            showNotice(error.message, 'error');
        }
    }

    function validateHeaders(headers) {
        const expected = [
            ['employeeid', 'employee id', 'empid', 'emp id'], ['fullname', 'full name', 'name'], ['location'], ['des', 'designation'], ['email', 'email address'], ['dob', 'date of birth'], ['doj', 'date of joining', 'joining date']
        ];
        const invalid = expected.some((accepted, index) => !accepted.includes(normalize(headers[index])));
        if (invalid) throw new Error('Invalid columns. Row 1 must be: A Employee ID, B Full Name, C Location, D DES, E Email, F DOB, G DOJ.');
    }

    function createEmployee(row, rowNumber, existingIds, seenIds) {
        const id = String(row[0] || '').trim();
        const fullName = String(row[1] || '').trim();
        if (!id && !fullName) return null;
        let status = 'New';
        if (!id || !fullName) status = 'Invalid: ID and name required';
        else if (existingIds.has(normalize(id))) status = 'Duplicate: already saved';
        else if (seenIds.has(normalize(id))) status = 'Duplicate ID in workbook';
        if (id) seenIds.add(normalize(id));
        return { id, fullName, location: String(row[2] || '').trim(), designation: String(row[3] || '').trim(), email: String(row[4] || '').trim(), dob: String(row[5] || '').trim(), doj: String(row[6] || '').trim(), photo: false, birthdayCard: false, createdAt: new Date().toISOString(), rowNumber, status };
    }

    function renderExcelPreview() {
        elements.previewBody.replaceChildren();
        pendingEmployees.slice(0, 100).forEach(employee => {
            const row = document.createElement('tr');
            [employee.id, employee.fullName, employee.location, employee.designation, employee.email, employee.dob, employee.doj, employee.status].forEach(value => {
                const cell = document.createElement('td'); cell.textContent = value; row.appendChild(cell);
            });
            elements.previewBody.appendChild(row);
        });
        const newCount = pendingEmployees.filter(item => item.status === 'New').length;
        const skipped = pendingEmployees.length - newCount;
        elements.previewSummary.textContent = `${pendingWorkbook.file.name}: ${newCount} new employee(s), ${skipped} invalid or duplicate row(s).${pendingEmployees.length > 100 ? ' Showing the first 100 rows.' : ''}`;
        elements.processButton.disabled = newCount === 0;
        elements.excelPreview.hidden = false;
    }

    async function saveWorkbookAndEmployees() {
        if (!pendingWorkbook) return;
        const valid = pendingEmployees.filter(item => item.status === 'New').map(({ status, rowNumber, ...employee }) => employee);
        try {
            setProgress(20, 'Saving timestamped Excel sheet…');
            const stamp = timestamp();
            const extension = pendingWorkbook.file.name.split('.').pop().toLowerCase();
            const savedName = `Birthdays_${stamp}.${extension}`;
            setProgress(55, 'Saving employee records…');
            for (const employee of valid) await HRCanvasAPI.createEmployee(employee);
            setProgress(100, 'Completed');
            showNotice(`Saved ${savedName} and added ${valid.length} employee(s). ${pendingEmployees.length - valid.length} row(s) skipped.`, 'success');
            pendingWorkbook = null; pendingEmployees = []; elements.excelInput.value = ''; elements.excelPreview.hidden = true; elements.uploadPanel.hidden = true;
            await refreshData();
            setTimeout(() => { elements.progressWrap.hidden = true; }, 700);
        } catch (error) {
            elements.progressWrap.hidden = true; showNotice(`Could not save the workbook: ${error.message}`, 'error');
        }
    }

    async function generateAndSaveCard(employee, photoBlob) {
        const templateNumber = Math.floor(Math.random() * TEMPLATE_COUNT) + 1;
        const template = await loadImage(readTemplateData(templateNumber));
        const photo = await loadImage(photoBlob);
        const canvas = document.createElement('canvas'); canvas.width = OUTPUT_WIDTH; canvas.height = OUTPUT_HEIGHT;
        const context = canvas.getContext('2d'); context.drawImage(template, 0, 0, OUTPUT_WIDTH, OUTPUT_HEIGHT);
        const centerX = 510; const centerY = TEMPLATE_Y[templateNumber - 1] + PHOTO_TOP_OFFSET;
        const x = Math.max(0, Math.min(centerX - PHOTO_SIZE / 2, OUTPUT_WIDTH - PHOTO_SIZE));
        const y = Math.max(0, Math.min(centerY - PHOTO_SIZE / 2, OUTPUT_HEIGHT - PHOTO_SIZE));
        context.save(); roundedRect(context, x, y, PHOTO_SIZE, PHOTO_SIZE, 80); context.clip(); drawImageContain(context, photo, x, y, PHOTO_SIZE, PHOTO_SIZE); context.restore();
        const blob = await canvasToBlob(canvas, 'image/jpeg', .95);
        const fileName = cardFileName(employee.fullName);
        const saved = await HRCanvasAPI.uploadCard(employee.id, templateNumber, blob);
        return { id: employee.id, employeeId: employee.id, fullName: employee.fullName, templateNumber, fileName, blob, url: saved.url, createdAt: new Date().toISOString() };
    }

    function renderCards() {
        const query = normalize(elements.cardSearch.value);
        const visible = cards.filter(card => normalize(`${card.employeeId} ${card.fullName}`).includes(query));
        elements.cardGrid.replaceChildren();
        visible.forEach(card => {
            const article = document.createElement('article'); article.className = 'card-item';
            const image = document.createElement('img'); image.src = card.url; image.alt = `Birthday card for ${card.fullName}`;
            image.addEventListener('click', () => previewCard(card));
            const meta = document.createElement('div'); meta.className = 'card-meta';
            const selectLabel = document.createElement('label'); selectLabel.className = 'card-select'; const select = document.createElement('input'); select.type = 'checkbox'; select.checked = selectedCardIds.has(card.id); select.addEventListener('change', () => { if (select.checked) selectedCardIds.add(card.id); else selectedCardIds.delete(card.id); updateEmailButton(); }); selectLabel.append(select, document.createTextNode('Include in email'));
            const name = document.createElement('strong'); name.textContent = card.fullName;
            const detail = document.createElement('small'); detail.textContent = `${card.employeeId} · Template ${card.templateNumber}`;
            const buttons = document.createElement('div'); buttons.className = 'card-buttons';
            const download = document.createElement('button'); download.type = 'button'; download.textContent = 'Download'; download.addEventListener('click', () => downloadBlob(card.blob, cardFileName(card.fullName)));
            const regenerate = document.createElement('button'); regenerate.type = 'button'; regenerate.textContent = 'Regenerate'; regenerate.addEventListener('click', () => regenerateCard(card.employeeId));
            buttons.append(download, regenerate); meta.append(selectLabel, name, detail, buttons); article.append(image, meta); elements.cardGrid.appendChild(article);
        });
        elements.cardCount.textContent = `${cards.length} card${cards.length === 1 ? '' : 's'}`;
        elements.cardEmpty.hidden = cards.length > 0;
        elements.downloadAllButton.disabled = cards.length === 0;
        updateEmailButton();
    }

    function updateEmailButton() { const count = cards.filter(card => selectedCardIds.has(card.id)).length; elements.prepareEmailButton.disabled = count === 0; elements.prepareEmailButton.textContent = count ? `Prepare Email (${count})` : 'Prepare Email'; }

    async function prepareBirthdayEmail() { const selected = cards.filter(card => selectedCardIds.has(card.id)); if (!selected.length) return; const subject = 'Birthday Cards', names = selected.map((card, index) => `${index + 1}. ${card.fullName}`).join('\n'), body = `Subject: Birthday Cards\n\nDear Team,\n\nPlease find the generated birthday cards for the following employees:\n\n${names}\n\nKindly review and share the attached cards.\n\nRegards,\nHR Team`, files = selected.map(card => new File([card.blob], cardFileName(card.fullName), { type: 'image/jpeg' })); try { if (navigator.share && navigator.canShare?.({ files })) { await navigator.share({ title: subject, text: body, files }); showNotice('The selected birthday cards were attached. Enter the recipient in Outlook, copy Birthday Cards into Subject, review the message, and click Send.', 'success'); return; } } catch (error) { if (error.name === 'AbortError') return; } selected.forEach(card => downloadBlob(card.blob, cardFileName(card.fullName))); window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`; showNotice('Sharing attachments is unavailable in this browser. The JPG cards were downloaded and a draft was opened; attach them manually before sending.', 'warning'); }

    async function regenerateCard(employeeId) {
        const employee = employees.find(item => item.id === employeeId); let photo;
        try { const metadata = await HRCanvasAPI.getPhoto(employeeId); photo = await HRCanvasAPI.fetchBlob(metadata.url); } catch { photo = null; }
        if (!employee || !photo) { showNotice('The employee photo is unavailable. Upload it again.', 'error'); return; }
        try { setProgress(25, 'Regenerating card…'); await generateAndSaveCard(employee, photo); setProgress(100, 'Card regenerated'); showNotice(`Created a new card for ${employee.fullName}.`, 'success'); await refreshData(); setTimeout(() => { elements.progressWrap.hidden = true; }, 600); }
        catch (error) { elements.progressWrap.hidden = true; showNotice(error.message, 'error'); }
    }

    function previewCard(card) {
        dialogCard = card; elements.dialogImage.src = card.url; elements.dialogName.textContent = card.fullName; elements.cardDialog.showModal();
    }

    async function downloadAllCards() {
        try {
            setProgress(10, 'Creating ZIP archive…'); const zip = new JSZip();
            cards.forEach(card => zip.file(cardFileName(card.fullName), card.blob));
            const blob = await zip.generateAsync({ type: 'blob', compression: 'DEFLATE' }, metadata => setProgress(Math.round(metadata.percent), 'Creating ZIP archive…'));
            downloadBlob(blob, `BirthdayCards_${timestamp()}.zip`); setProgress(100, 'ZIP ready'); setTimeout(() => { elements.progressWrap.hidden = true; }, 600);
        } catch (error) { elements.progressWrap.hidden = true; showNotice(`ZIP creation failed: ${error.message}`, 'error'); }
    }

    async function exportBackup() {
        const payload = { version: 1, exportedAt: new Date().toISOString(), employees: employees.map(({ photo, birthdayCard, ...rest }) => ({ ...rest, photo, birthdayCard })), cards: cards.map(({ blob, ...card }) => card) };
        downloadBlob(new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' }), `HRCanvas_Backup_${timestamp()}.json`);
    }

    async function importBackup(file) {
        if (!file) return;
        try {
            const payload = JSON.parse(await file.text()); if (!Array.isArray(payload.employees)) throw new Error('Backup does not contain an employees list.');
            for (const employee of payload.employees) { if (employee.id && employee.fullName) { try { await HRCanvasAPI.createEmployee(employee); } catch (error) { if (!/already exists/i.test(error.message)) throw error; } } }
            showNotice(`Imported ${payload.employees.length} employee record(s). Photos and generated images must be uploaded again.`, 'success'); await refreshData();
        } catch (error) { showNotice(`Backup import failed: ${error.message}`, 'error'); }
        elements.importInput.value = '';
    }

    async function refreshData() {
        const previousCardIds = new Set(cards.map(card => card.id));
        employees = (await HRCanvasAPI.listEmployees()).sort((a, b) => a.fullName.localeCompare(b.fullName));
        const serverCards = (await HRCanvasAPI.listCards()).sort((a, b) => String(b.createdAt).localeCompare(String(a.createdAt)));
        cards = await Promise.all(serverCards.map(async card => ({ ...card, id: card.employeeId, blob: await HRCanvasAPI.fetchBlob(card.url) })));
        const currentCardIds = new Set(cards.map(card => card.id)); for (const id of selectedCardIds) { if (!currentCardIds.has(id)) selectedCardIds.delete(id); } cards.forEach(card => { if (!previousCardIds.has(card.id)) selectedCardIds.add(card.id); });
        renderCards();
    }

    function readTemplateData(templateNumber) {
        const source = window.BIRTHDAY_TEMPLATE_DATA?.[templateNumber];
        if (!source) throw new Error(`Embedded template ${templateNumber} is unavailable.`);
        return source;
    }

    function setProgress(value, label) { elements.progressWrap.hidden = false; elements.progressBar.value = value; elements.progressValue.textContent = `${value}%`; elements.progressText.textContent = label; }
    function showNotice(message, type) { elements.notice.textContent = message; elements.notice.className = `notice ${type}`; elements.notice.hidden = false; elements.notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
    function appendCell(row, value) { const cell = document.createElement('td'); cell.textContent = value; row.appendChild(cell); }
    function appendEmployeeCell(row, employee) { const cell = document.createElement('td'); const name = document.createElement('strong'); name.textContent = employee.fullName; const email = document.createElement('div'); email.textContent = employee.email || '—'; email.style.color = '#777'; cell.append(name, email); row.appendChild(cell); }
    function appendStatusCell(row, value) { const cell = document.createElement('td'); const status = document.createElement('span'); status.className = `status${value ? ' yes' : ''}`; status.textContent = value ? 'Yes' : 'No'; cell.appendChild(status); row.appendChild(cell); }
    function byId(id) { return document.getElementById(id); }
    function normalize(value) { return String(value ?? '').trim().toLowerCase().replace(/\s+/g, ' '); }
    function cardFileName(fullName) { const readable = String(fullName || 'Employee').replace(/[&_]+/g, ' ').replace(/[<>:"/\\|?*\x00-\x1F]+/g, '').trim().replace(/\s+/g, ' ') || 'Employee'; return `${readable}.jpg`; }
    function timestamp() { const date = new Date(); const pad = value => String(value).padStart(2, '0'); return `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}_${pad(date.getHours())}${pad(date.getMinutes())}${pad(date.getSeconds())}`; }
    function loadImage(source) { return new Promise((resolve, reject) => { const image = new Image(); let url = source; if (source instanceof Blob) url = URL.createObjectURL(source); image.onload = () => { if (source instanceof Blob) URL.revokeObjectURL(url); resolve(image); }; image.onerror = () => { if (source instanceof Blob) URL.revokeObjectURL(url); reject(new Error('The image could not be loaded.')); }; image.src = url; }); }
    function roundedRect(context, x, y, width, height, radius) { context.beginPath(); context.roundRect(x, y, width, height, radius); }
    function drawImageContain(context, image, x, y, width, height) { const ratio = Math.min(width / image.naturalWidth, height / image.naturalHeight); const drawWidth = image.naturalWidth * ratio, drawHeight = image.naturalHeight * ratio; context.fillStyle = '#f4f4f4'; context.fillRect(x, y, width, height); context.drawImage(image, x + (width - drawWidth) / 2, y + (height - drawHeight) / 2, drawWidth, drawHeight); }
    function canvasToBlob(canvas, type, quality) { return new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('The card could not be exported.')), type, quality)); }
    function downloadBlob(blob, fileName) { const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = fileName; document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000); }
})();
