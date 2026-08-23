Browser libraries bundled for offline use:

- SheetJS Community Edition 0.20.3 (xlsx.full.min.js)
  https://docs.sheetjs.com/docs/getting-started/installation/standalone/

- JSZip 3.10.1 (jszip.min.js)
  https://stuk.github.io/jszip/

These files are loaded locally; the Birthday Cards page does not require a CDN at runtime.

- birthday-template-data.js
  Generated from the 27 local JPG templates. It embeds them as data URLs so Canvas export remains origin-safe when the app is opened directly with file://.
