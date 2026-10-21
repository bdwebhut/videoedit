# Le spot halal — static mobile map page

Figma frame `1:236`:
https://www.figma.com/design/mMPV21NfSMrHikvRVU0twE/Resturant?node-id=1-236

## Open and download assets

Run `python3 download-assets.py` in this folder, then open `index.html`. Alternatively serve the folder with `python3 -m http.server 8000`.

The manifest and downloader include all **15 original Figma asset constants** with their exact local filenames. Runtime markup uses only local paths under `assets/`. Asset downloading is left to the owner. URLs were retrieved on October 21, 2026 and expire after seven days; refresh them through Figma MCP if necessary. Parkinsans and its license are bundled locally.

## Static mobile implementation

Reference size: **390 × 2440 px**. The layout is fluid below 390px and centered at a maximum width of 390px on wider viewports. No tablet, laptop, or desktop layouts are added; this folder is separate for later merging.

**HTML and CSS only: no JavaScript, forms, inputs, functional buttons, links, search, filtering, location selection, marker clicks, map SDK, map navigation, storage, or dynamic behavior.** All apparent controls and seven map markers are visual elements. The Python downloader runs separately and is not webpage behavior.

The page includes the mobile header, title, search field and action, city/filter row, original cropped map image, seven colored markers, six horizontal restaurant cards, and footer. The repeated Pizza Time card is preserved from Figma. Original photo crops, badges, cuisine chips, neighborhoods, quotations, and ratings are retained.

## Merge notes

Base page resets are scoped under `.mobile-map`, except for the standalone body margin/background. Generated classes use the `mm` prefix to avoid previous desktop/mobile class names. Preserve relative asset/style paths or adjust them when merging. Original node IDs remain in `data-node-id` attributes. Other GitHub folders are not modified.

## Verification

Chromium checked viewports of 320, 375, 390, 430, and 768px. Wider viewports center the same mobile layout. The reference render is 390 × 2440, with the map at x=16 / y≈399 / size 358 × 430 and the footer at y≈1865. Checks confirm six cards, seven markers, no horizontal overflow, local font loading, local asset paths, zero scripts, zero interactive elements, and no runtime errors. Results are in `validation.json`.

The layout was checked against the Figma screenshot. Final appearance of the original downloaded images and icons is left to the owner.
