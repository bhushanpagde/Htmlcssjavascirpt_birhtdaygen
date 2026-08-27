# XAMPP Migration Checklist

Use this checklist to migrate HR Canvas from browser-only IndexedDB storage to shared XAMPP server storage. Do not place MySQL's live data directory inside OneDrive. Sync exported backups and generated files instead.

## 1. Baseline and recovery

- [ ] Export the current browser data before migration.
- [x] Confirm the latest standalone version is committed and pushed to GitHub.
- [x] Create a migration branch (`xampp-migration`) from `main`.
- [x] Record the current IndexedDB database name (`birthday-studio`, version 2).
- [x] Keep the standalone version usable on `main` until server migration is accepted.

## 2. XAMPP application structure

- [ ] Install and start Apache and MySQL/MariaDB in XAMPP.
- [ ] Deploy the application under `C:\xampp\htdocs\hrcanvas`.
- [x] Create a PHP API directory such as `api/`.
- [x] Create storage directories for photos, cards, certificates, workbooks, and backups (server write access still needs verification).
- [ ] Keep configuration and credentials outside publicly downloadable folders where possible.
- [x] Add `.gitignore` rules for credentials, uploaded files, generated files, logs, and local backups.
- [ ] Confirm `http://localhost/hrcanvas/` loads without console or missing-file errors.

## 3. Database design

- [ ] Apply the version-controlled schema to create the `hrcanvas` database.
- [x] Define an `employees` table with ID, name, location, email, DOB, DOJ, and timestamps.
- [x] Define a `photos` table containing employee ID, safe file name, relative path, MIME type, size, and timestamp.
- [x] Define a `birthday_cards` table containing employee ID, template number, file name, relative path, and timestamp.
- [x] Define a `certificates` table containing employee ID, certificate text, unit-head name, PDF/thumbnail paths, and timestamp.
- [x] Define a `files` table for uploaded workbook metadata and paths.
- [x] Define a `settings` table.
- [x] Define primary keys, foreign keys, unique constraints, and indexes.
- [x] Define consistent database date fields and API aliases.

## 4. PHP API

- [x] Add one shared database connection/configuration module.
- [x] Return JSON with consistent HTTP status codes and error objects.
- [ ] Test the implemented list, create, update, and delete employee endpoints on XAMPP.
- [ ] Implement photo upload, retrieval, replacement, and deletion endpoints.
- [ ] Implement workbook upload and import endpoints.
- [ ] Implement birthday-card save, list, download, regenerate, and delete endpoints.
- [ ] Implement certificate save, list, download, and delete endpoints.
- [ ] Implement settings read/update endpoints.
- [ ] Implement backup export and restore endpoints.
- [ ] Use database transactions for operations that update both a record and a file.

## 5. File storage safety

- [ ] Generate server-side safe filenames; never trust uploaded filenames.
- [ ] Validate allowed extensions, MIME types, and actual image/file contents.
- [ ] Enforce upload-size limits in PHP and the application.
- [ ] Prevent path traversal and executable uploads.
- [ ] Prevent script execution inside storage/upload directories.
- [ ] Use stable relative paths in the database instead of machine-specific absolute paths.
- [ ] Delete or archive replaced files only after the database update succeeds.
- [ ] Define a recovery process for orphaned database records and files.

## 6. Frontend conversion

- [ ] Add one reusable JavaScript API client.
- [ ] Replace IndexedDB calls in `js/app.js`.
- [ ] Replace IndexedDB calls in `js/employees.js`.
- [ ] Replace IndexedDB calls in `js/birthday-cards.js`.
- [ ] Replace IndexedDB calls in `js/awards-certificates.js`.
- [ ] Replace IndexedDB calls in `js/work-anniversary.js`.
- [ ] Replace IndexedDB calls in `js/farewell-card.js`.
- [ ] Replace any remaining `indexedDB.open`, `put`, `get`, `getAll`, and `delete` storage operations.
- [ ] Change image/card/certificate displays from Blob object URLs to server URLs where appropriate.
- [ ] Keep canvas generation in the browser initially, then upload the generated Blob to PHP.
- [ ] Show clear loading, success, validation, network, and server-error messages.
- [ ] Prevent duplicate submissions while an upload or save is running.

## 7. Existing browser-data migration

- [ ] Decide whether old browser data must be imported or can be re-entered.
- [ ] Extend export so it can include photos/cards, or provide a one-time migration uploader.
- [ ] Detect duplicate employee IDs during import.
- [ ] Produce an import report showing added, skipped, and failed records/files.
- [ ] Verify imported employee counts and sample photos/cards against IndexedDB.
- [ ] Disable browser writes only after migration verification passes.

## 8. Authentication and network access

- [ ] Add login and logout if the application will be reachable by other computers.
- [ ] Store passwords with PHP password hashing, never as plain text.
- [ ] Add role/permission checks for delete, restore, and settings operations.
- [ ] Add session security and CSRF protection for state-changing requests.
- [ ] Restrict Apache/firewall access to the intended office network.
- [ ] Use HTTPS if the application is accessible beyond the local machine.
- [ ] Do not expose database credentials or server paths to frontend JavaScript.

## 9. OneDrive and backups

- [ ] Choose whether OneDrive receives generated output, backups, or both.
- [ ] Do not synchronize MySQL's live data directory.
- [ ] Schedule database dumps into a OneDrive-synced backup folder.
- [ ] Copy generated photos/cards/certificates to a OneDrive-synced folder only after successful saves.
- [ ] Define retention rules for daily/weekly backups.
- [ ] Test restoring the database and files into a clean XAMPP installation.
- [ ] Document conflict handling if more than one computer can modify synced files.

## 10. Cross-check and acceptance tests

- [ ] A new employee appears after refreshing the page and in another browser.
- [ ] Employee edits and deletions remain correct after refresh.
- [ ] Excel import stores both records and the source workbook.
- [ ] Photo upload, display, replacement, and deletion work from another computer.
- [ ] Birthday-card generation saves a real JPG on the server and remains visible after browser data is cleared.
- [ ] ZIP/download and email preparation still work.
- [ ] Certificate generation saves a real PDF and thumbnail on the server.
- [ ] Work-anniversary and farewell workflows load shared employee data correctly.
- [ ] Invalid file types, oversized uploads, duplicate IDs, and interrupted requests fail safely.
- [ ] Concurrent edits do not silently overwrite newer data.
- [ ] Clearing browser storage does not remove server records or files.
- [ ] Server restart does not lose records or files.
- [ ] Database backup and restore are successfully tested.
- [ ] No secrets, uploaded employee data, or generated output are tracked by Git.
- [ ] `git diff --check` passes and the final migration is reviewed before merging to `main`.

## Definition of done

The migration is complete only when every active workflow uses the PHP API, all persistent records/files survive browser-storage clearing and server restarts, backup restoration has been tested, and the acceptance checklist above passes.
