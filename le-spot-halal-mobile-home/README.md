# Le spot halal — static mobile homepage

Figma frame `2:1394`, Home — Mobile:
https://www.figma.com/design/vKBLU6mx3eUbyaZgU4FLRZ/Untitled?node-id=2-1394

## Open and download assets

Run `python3 download-assets.py` in this folder, then open `index.html`. You can also serve the folder with `python3 -m http.server 8000`.

The asset manifest and download script include all 26 Figma asset constants with their exact local filenames. Runtime HTML uses only local `assets/` references. Asset downloads are left to the owner. URLs were retrieved on October 21, 2026 and expire after seven days; refresh them through Figma MCP when necessary. Parkinsans and its license are bundled locally.

## Static mobile implementation

This folder is separate from every desktop page. The supplied reference is **390 × 6167 px**. Layout is fluid below 390px and centered at a maximum width of 390px on larger screens. There are no tablet, laptop, or desktop layouts or breakpoints; those can be merged separately later.

HTML and CSS only: **no JavaScript, forms, inputs, functional buttons, links, search/filter behavior, dialogs, validation, draft saving, storage, requests, or navigation**. Search, category chips, restaurant cards, and calls to action are static visual elements exactly as requested. The included Python script only downloads assets; it is not part of the webpage.

The bright blue hero/nearby sections, five restaurant cards, type sizes, flags, image slots, invitation sections, and footer follow this mobile Figma frame. Desktop colors or content were not substituted.

## Merge notes

- The root is `.mobile-home`; generated style classes use the `mh` prefix to avoid the earlier desktop class names.
- Base resets are scoped to `.mobile-home`; the standalone body only clears its margin and sets the page background.
- Keep `assets/`, `styles.css`, and the relative file references together, or adjust paths when merging.
- The source Figma node IDs remain in `data-node-id` attributes to help compare sections.
- The mobile markup does not import or change any existing desktop files.

## Verification

Chromium rendering was checked at 320, 375, 390, 430, and 768px viewport widths. The last two simply center the same mobile layout; they are not additional responsive designs. Checks verify no horizontal overflow, five cards, local font loading, local asset paths, zero scripts, zero interactive elements, and no JavaScript errors. Results are in `validation.json`.

Visual layout was checked against the reference. The original image/SVG download and final asset inspection are left to the owner.
