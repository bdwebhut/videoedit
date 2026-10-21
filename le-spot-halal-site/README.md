# Le spot halal — shared static site

**This is the complete working site folder.** It contains the ready-to-open pages, all shared assets, and editable sources. The homepage, restaurant listing, and restaurant details have been refined so far; original export folders remain unchanged as references.

## Open these complete pages

| Page | Complete file | Preview URL |
| --- | --- | --- |
| Homepage | `index.html` | http://localhost:8000/ |
| Restaurant listing | `restaurants.html` | http://localhost:8000/restaurants.html |
| Restaurant details | `details.html` | http://localhost:8000/details.html |

**Files in `src/pages/` are editing templates, not standalone webpages.** They contain page content only. The builder adds the shared document, header, footer, styles, and scripts. Open the complete files above to see the full design.

## Update, download, and preview on Linux

```bash
cd ~/le-spot-halal-local/videoedit
git pull --ff-only origin main
cd le-spot-halal-site
python3 download-assets.py
python3 -m http.server 8000 --bind 127.0.0.1
```

Open a preview URL from the table and hard-refresh with **Ctrl+Shift+R**. Stop the server with **Ctrl+C**. Normal previewing needs no npm, system installation, or WordPress setup.

The importer checks every original `le-spot-halal-*/assets/` folder, including nested caches, and reads both original manifest formats for URL fallbacks. It reuses valid originals before downloading missing files with curl (or Python's standard library if curl is unavailable). JPEG/WebP photos exported with `.png` filenames are accepted. Existing valid files stay in place.

The three pages share **87 unique image/SVG assets**. Parkinsans and Inter fonts and their licenses are bundled locally. All runtime asset paths are local. Temporary Figma URLs exist only in download tooling and can expire after seven days; refresh original or shared manifests through Figma MCP when needed.

Audit the shared files without changing or downloading anything:

```bash
python3 download-assets.py --check
```

Reuse files from another checkout or directory:

```bash
python3 download-assets.py --assets-dir /path/to/original-downloads
```

Subdirectories are searched. Repeat `--assets-dir` for multiple locations; add `--local-only` to avoid network requests. Success ends with `All 87 original assets are ready`; failed paths are listed explicitly.

## One folder, one zip

When all requested pages are migrated, zip **the whole `le-spot-halal-site` folder**, not `src/pages/`. This keeps every complete page, image, stylesheet, script, font, and editable source together for the later WordPress conversion.

From inside the site folder, first check:

```bash
python3 build.py
python3 download-assets.py --check
```

Once both commands succeed:

```bash
cd ..
python3 -m zipfile -c le-spot-halal-site.zip le-spot-halal-site
```

The archive contains one self-contained `le-spot-halal-site/` folder. Browsing its generated pages requires no other export folder, runtime template fetching, backend, or external CDN. Serving it locally is the recommended preview method.

## Where to edit

```text
le-spot-halal-site/
├── index.html                         # Complete generated homepage
├── restaurants.html                   # Complete generated restaurant listing
├── details.html                       # Complete generated restaurant details
├── build.py                           # Build all pages from shared sources
├── download-assets.py
├── asset-manifest.json
├── src/
│   ├── layout.html                    # Shared full document shell
│   ├── partials/
│   │   ├── header.html                # One responsive header implementation
│   │   ├── footer.html                # One responsive footer implementation
│   │   ├── restaurant-card.html       # Homepage card component
│   │   ├── listing-card.html          # Horizontal/stacked listing component
│   │   ├── info-dialog.html           # Shared restaurant/map dialog
│   │   └── listing-search.html
│   ├── pages/
│   │   ├── home.html                  # Content template, not a preview file
│   │   └── restaurants.html           # Content template, not a preview file
│   └── data/
│       ├── home-restaurants.json
│       └── listing-restaurants.json
├── assets/
│   ├── css/
│   │   ├── tokens.css                 # Shared colors, font, spacing, radii
│   │   ├── base.css                   # Scoped reset and utilities
│   │   ├── components.css             # Shared buttons, cards, search, dialogs
│   │   ├── layout.css                 # Shared responsive header/footer
│   │   └── pages/
│   │       ├── home.css
│   │       └── restaurants.css
│   ├── js/
│   │   ├── site.js                    # Shared menu and dialog behavior
│   │   ├── home.js
│   │   └── restaurants.js
│   ├── fonts/
│   ├── images/
│   └── icons/
└── tests/
```

Edit source templates/data, then run `python3 build.py` to regenerate **all three complete pages**, including shared header/footer changes. Do not maintain another header/footer copy in generated HTML. Page metadata and brand artwork are registered in `build.py`.

`python3 build.py --check` detects outdated generated files. `--page home`, `--page restaurants`, or `--page details` limits a build/check to one page. Shared CSS is scoped under `.lsh-site`; page composition uses `.page-home`, `.page-restaurants`, or `.page-details`. Future pages belong here using the same shell, components, tokens, and assets.

## Responsive behavior and static scope

The homepage preserves its supplied 390px mobile and 1920px desktop references, including mobile blue hero/nearby panels and desktop contact panel. Its card grid changes from one to two, three, and five columns as space permits.

The listing preserves its 390 × 2949 mobile and 1920 × 2835 desktop references. Phones use stacked filters, sorting, and vertical cards; tablets use two card columns. From 1024px it uses a filter sidebar and horizontal result cards. All pages share the same responsive header/footer implementation; page settings select their supplied SVG artwork. Navigation collapses below 1024px and remains visible without JavaScript.

The details page preserves its 390 × 3961 mobile and 1920 × 3272 desktop layouts. Phones show practical information, map, about, menu, reservation, and reviews in sequence. Tablets use a two-column hero; laptops use independent content/sidebar columns. Large desktops reveal the additional photo panels. The supplied “Photo 2” and “Photo 3” panels are intentional placeholders: the original exports provide only one restaurant photo, reused in the thumbnails. Gallery, menu anchors, telephone/email links, and map links work. Delivery and reservation dialogs provide contact details; the displayed reservation times remain static samples. The matching Healthy listing card links to this page.

Details content lives in `src/pages/details.html` and `src/data/restaurant-details.json`; repeated menu/review markup is in `src/partials/menu-item.html` and `review.html`. Its page styles/scripts are `assets/css/pages/details.css` and `assets/js/details.js`. Shared header/footer edits apply to every page.

Content is static. The listing preserves the reference's five cards named “Le Comptoir,” their cuisine/location labels, and the initial “27 spots trouvés.” label. Using a local filter, sort, or search recomputes the count from the actual five records. No extra restaurant records were invented.

Local search, cuisine filters, sorting, dialogs, email links, and homepage/listing/details navigation work in the browser. No database, authentication, submission API, tracking, storage, or WordPress theme was added. Other page connections and dynamic features remain for later tasks.

## Verification

Python checks require only the standard library:

```bash
python3 build.py --check
python3 -m unittest discover -s tests -p 'test_*.py' -v
```

Optional browser checks use `playwright-core` and an existing Chromium installation. With the site served at port 8000:

```bash
SITE_URL=http://127.0.0.1:8000/ node tests/responsive.cjs
SITE_URL=http://127.0.0.1:8000/ node tests/listing.cjs
SITE_URL=http://127.0.0.1:8000/ node tests/details.cjs
```

`PLAYWRIGHT_MODULE` may point to another existing Playwright installation; `CHROMIUM_PATH` overrides `/usr/bin/chromium`. Screenshots and reports go to ignored `tests/results/`.

All three pages are checked at 20 widths from 320px to 2560px for overflow, type, responsive layouts, keyboard navigation, local controls, dialogs, and no-JavaScript navigation. Complete original-image verification still requires the user's local assets; no stock substitutions were made. The supplied palette and its existing contrast limitations are preserved. Reports are recorded in `validation.json`, `validation-restaurants.json`, and `validation-details.json`.
