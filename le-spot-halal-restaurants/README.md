# Le spot halal — restaurant listing

Desktop-only HTML, CSS, and JavaScript for Figma frame `2:513`:
https://www.figma.com/design/vKBLU6mx3eUbyaZgU4FLRZ/Untitled?node-id=2-513

## Run

Run `python3 download-assets.py`, then open `index.html`. Alternatively serve this folder with `python3 -m http.server 8000` and visit `http://localhost:8000`.

The desktop reference is 1920 × 2835 pixels. There are no mobile breakpoints or build dependencies. The page uses semantic header, navigation, heading, filter buttons, articles, and footer sections with CSS Grid and Flexbox. Parkinsans and its license are bundled locally. Existing homepage navigation points to the sibling `../le-spot-halal-homepage/` folder.

## Behavior and supplied data

Cuisine buttons filter the five supplied restaurant cards; multiple cuisines use OR matching. Reset clears filters and search. Sorting supports original order, cuisine, and rating. The header search opens a local search dialog; the map button opens a dialog linking to a Google Maps search. Contact and add-restaurant buttons use the design's email address. No unknown legal or social URLs were invented.

The Figma screen shows `27 spots trouvés.` but supplies only five cards; all five are named `Le Comptoir`, rated 4.9, with the same quotation. Those values are preserved rather than inventing 22 restaurants or changing their names. After using a filter, search, sort, or reset, the result count reflects the five actual local records. The initial yellow Burgers chip preserves the reference appearance without hiding the other cuisines shown in the design.

## Original assets: download blocked

All 15 Figma asset paths are local references; there are no temporary runtime Figma URLs. This workspace's network policy still blocks the original asset downloads with HTTP 403. Original photographs and SVGs have not been substituted, and the page is not visually complete until they are downloaded. Run the included `download-assets.py` on a connection that allows `www.figma.com`.

The manifest was retrieved on October 21, 2026. The temporary Figma download URLs expire after seven days; refresh them with Figma MCP if needed. Original layered image fills and crop geometry are preserved. Asset geometry requires a final visual check after the original SVGs and photographs become available.

## Validation

Chromium checks passed at 1920px: full page 1920 × 2835, first card x=625 / y≈502 / width=939 / height≈311.5, footer y≈2242, five cards, local font loading, cuisine filtering, reset, empty state, sorting, and map dialog. No JavaScript errors were reported. `validation.json` contains the results and explicitly records missing images. Full asset and screenshot matching is still blocked by the asset-download limitation.
