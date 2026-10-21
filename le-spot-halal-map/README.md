# Le spot halal — map page

Desktop HTML, CSS, and JavaScript for Figma frame `2:2492`:
https://www.figma.com/design/vKBLU6mx3eUbyaZgU4FLRZ/Untitled?node-id=2-2492

## Open and download assets

1. Run `python3 download-assets.py` in this folder to download all 21 original image/SVG assets into `assets/`.
2. Open `index.html`, or run `python3 -m http.server 8000` and visit `http://localhost:8000`.

Asset downloads are intentionally left to the owner. `asset-manifest.json` contains the original Figma source URLs and local filenames. There are no temporary Figma URLs in runtime HTML or CSS. URLs were retrieved on October 21, 2026 and expire after seven days; refresh the manifest with Figma MCP if needed. The script skips existing non-empty files, downloads with curl, writes through temporary files, and reports failures. Parkinsans and its license are included locally.

## Layout

The desktop reference is 1920 × 2306 pixels. There are no mobile breakpoints or build dependencies. The page reuses the preceding page's header/footer styling and uses semantic sections, a two-column restaurant grid, and a positioned map surface. The original map image crop, all seven marker positions, and six restaurant card slots are preserved. The duplicate Pizza Time card is part of the supplied design and is retained.

The map is the original design's raster map image with interactive HTML markers, not a live map SDK. Marker-to-card associations support local selection; geographic coordinates, zoom, panning, routing, and live restaurant data were not supplied. Genève is the only supplied city, so the city dialog offers that city without inventing additional map data. The selected large green marker retains its original reference size and position.

## Controls

- Search filters the six cards and their associated markers.
- Cuisine filters support multiple selections and can be reset.
- Selecting a card highlights associated markers; selecting a marker highlights its linked card.
- City and filter controls open accessible native dialogs.
- Header search focuses the search input.
- Homepage links point to the sibling `../le-spot-halal-homepage/` folder; Carte links to this page.
- Contact/add actions use the design's email address. Unknown legal/social destinations are left without invented links.

## Validation

Chromium checks passed: page size 1920 × 2306, six cards, seven markers, map at x=678 / y≈487 / size 1155 × 1153, footer at y≈1714, font loading, two Pizza search matches, one Healthy filter match, reset, empty results, marker/card selection, city dialog, and filter dialog. No JavaScript errors. Results are in `validation.json`.

The reference screenshot was used to check the layout. Original images and icons are reserved for the owner's download step, so final appearance with those files has not been inspected here.
