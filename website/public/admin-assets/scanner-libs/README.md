# POS scanner dependencies

Served locally; the POS never sends product codes or camera frames to a CDN.

- @zxing/browser 0.1.5, UMD bundle:
  https://cdn.jsdelivr.net/npm/@zxing/browser@0.1.5/umd/zxing-browser.min.js
  MIT license in ZXING-LICENSE.txt.
  Bundles ZXing core; Apache 2.0 license retained in ZXING-CORE-LICENSE.txt.
- JsBarcode 3.11.6, Code128 build:
  https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/barcodes/JsBarcode.code128.min.js
  MIT license in JSBARCODE-LICENSE.txt.

Printed Code128 barcodes encode exactly the Product QR Code field, without any prefix or product ID.
Existing supplier QR and product codes are preserved and also supported by POS lookup.
