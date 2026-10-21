# Le spot halal — static mobile restaurant listing

Figma frame `1:380`:
https://www.figma.com/design/mMPV21NfSMrHikvRVU0twE/Resturant?node-id=1-380

## Open and download assets

Run `python3 download-assets.py` in this folder, then open `index.html`. Alternatively serve the folder using `python3 -m http.server 8000`.

`asset-manifest.json` and the download script cover all **10 original assets** with their exact local filenames. Runtime HTML uses local paths under `assets/`; downloading the images/icons is left to the owner. Temporary Figma URLs were retrieved on October 21, 2026 and expire after seven days. Refresh them with Figma MCP if necessary. Parkinsans and its license are bundled locally.

## Static mobile layout

The reference frame is **390 × 2949 px**. The page is fluid below 390px and centered at a maximum width of 390px on wider viewports. There are no tablet/laptop/desktop breakpoints; this folder can be merged with those versions later.

**HTML and CSS only. No JavaScript, functional controls, links, forms, native inputs, filtering, sorting, search, dialogs, storage, or navigation.** The cuisine chips, reset text, and sorting bar are visual elements only. The Python downloader is separate from the webpage.

The design's initial `27 spots trouvés.` count, five cards named `Le Comptoir`, food photos, cuisine chips, neighborhoods, quotations, and 4.9 ratings are preserved. No extra restaurant data or behavior was invented. Header and footer follow this supplied mobile frame.

## Merge notes

All page resets are scoped under `.mobile-restaurants`, apart from the standalone body margin/background. Generated classes use the `ml` prefix to avoid the existing desktop and mobile-home class names. Keep the assets folder and relative paths together, or update paths during integration. Figma node IDs are retained in `data-node-id` attributes. Existing pages are not modified.

## Verification

Chromium checked 320, 375, 390, 430, and 768px viewports. Wider viewports simply center the same mobile page. At the reference width the rendered page is 390 × 2949, with the footer at y≈2374. Checks confirm no horizontal overflow, five cards, local font loading, local asset paths, zero scripts, zero interactive elements, and no runtime errors. `validation.json` contains the results.

The layout was compared against the supplied Figma screenshot. Final appearance of downloaded images/SVGs is left to the owner.
