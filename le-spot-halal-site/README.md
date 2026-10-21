# Le spot halal — shared static site

**This is the complete working site folder.** It contains the ready-to-open pages, all shared assets, and editable sources. All five requested page types have been refined: homepage, restaurant listing, restaurant details, map, and add-restaurant form; original export folders remain unchanged as references.

## Open these complete pages

| Page | Complete file | Preview URL |
| --- | --- | --- |
| Homepage | `index.html` | http://localhost:8000/ |
| Restaurant listing | `restaurants.html` | http://localhost:8000/restaurants.html |
| Restaurant details | `details.html` | http://localhost:8000/details.html |
| Restaurant map | `map.html` | http://localhost:8000/map.html |
| Add a restaurant | `add-restaurant.html` | http://localhost:8000/add-restaurant.html |

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

The five pages share **112 unique image/SVG assets**. Parkinsans and Inter fonts and their licenses are bundled locally. All runtime asset paths are local. Temporary Figma URLs exist only in download tooling and can expire after seven days; refresh original or shared manifests through Figma MCP when needed.

Audit the shared files without changing or downloading anything:

```bash
python3 download-assets.py --check
```

Reuse files from another checkout or directory:

```bash
python3 download-assets.py --assets-dir /path/to/original-downloads
```

Subdirectories are searched. Repeat `--assets-dir` for multiple locations; add `--local-only` to avoid network requests. Success ends with `All 112 original assets are ready`; failed paths are listed explicitly.

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
├── map.html                           # Complete generated restaurant map
├── add-restaurant.html                # Complete generated restaurant form
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

Edit source templates/data, then run `python3 build.py` to regenerate **all five complete pages**, including shared header/footer changes. Do not maintain another header/footer copy in generated HTML. Page metadata and brand artwork are registered in `build.py`.

`python3 build.py --check` detects outdated generated files. `--page home`, `--page restaurants`, `--page details`, `--page map`, or `--page add-restaurant` limits a build/check to one page. Shared CSS is scoped under `.lsh-site`; page composition uses `.page-home`, `.page-restaurants`, `.page-details`, `.page-map`, or `.page-add-restaurant`. Future pages belong here using the same shell, components, tokens, and assets.

## Responsive behavior and static scope

The homepage preserves its supplied 390px mobile and 1920px desktop references, including mobile blue hero/nearby panels and desktop contact panel. Its card grid changes from one to two, three, and five columns as space permits.

The listing preserves its 390 × 2949 mobile and 1920 × 2835 desktop references. Phones use stacked filters, sorting, and vertical cards; tablets use two card columns. From 1024px it uses a filter sidebar and horizontal result cards. All pages share the same responsive header/footer implementation; page settings select their supplied SVG artwork. Navigation collapses below 1024px and remains visible without JavaScript.

The details page preserves its 390 × 3961 mobile and 1920 × 3272 desktop layouts. Phones show practical information, map, about, menu, reservation, and reviews in sequence. Tablets use a two-column hero; laptops use independent content/sidebar columns. Large desktops reveal the additional photo panels. The supplied “Photo 2” and “Photo 3” panels are intentional placeholders: the original exports provide only one restaurant photo, reused in the thumbnails. Gallery, menu anchors, telephone/email links, and map links work. Delivery and reservation dialogs provide contact details; the displayed reservation times remain static samples. The matching Healthy listing card links to this page.

Details content lives in `src/pages/details.html` and `src/data/restaurant-details.json`; repeated menu/review markup is in `src/partials/menu-item.html` and `review.html`. Its page styles/scripts are `assets/css/pages/details.css` and `assets/js/details.js`. Shared header/footer edits apply to every page.

The map page preserves the supplied 390 × 2440 mobile and 1920 × 2306 desktop references. Phones show a 430px map followed by six horizontal cards. Tablets show two card columns below the map; laptops use a scrollable results panel beside it. Wider desktops show two columns of vertical cards. The map uses the original raster artwork with seven positioned HTML markers, without a live map SDK, geographic coordinates, zoom, or panning. Local search, multi-cuisine filters, reset, empty results, keyboard marker/card selection, and city/filter dialogs work. Genève is the only supplied city; the duplicate Pizza Time card remains. Marker positions adapt to the two supplied layouts. The Le Comptoir title links to `details.html`, and shared header/footer Carte links now open `map.html` on every page.

Edit `src/pages/map.html`, `src/partials/map-card.html`, `src/partials/map-dialogs.html`, and `src/data/map-restaurants.json` / `map-markers.json`. The page stylesheet and script are `assets/css/pages/map.css` and `assets/js/map.js`.

The add-restaurant page preserves all six sections, 98 controls, empty initial answers, public/private notices, uploads, and confirmations from the desktop export. No mobile reference was supplied for this page; its phone/tablet layouts follow the shared site's visual language. Associated fields stack, section links form a compact grid, and each day's opening hours and closed checkbox stay together. Descriptive control names replace Figma node identifiers; each section is a separate editable partial under `src/partials/add-restaurant/`. The complete template is `src/pages/add-restaurant.html`, with page CSS/JS in `assets/css/pages/add-restaurant.css` and `assets/js/add-restaurant.js`.

Form behavior stays local: required fields, valid opening-hour ranges, services, file type/size (PDF/JPG/PNG, 10 MiB each), and photo consent are checked in the browser. “Enregistrer un brouillon” explicitly saves answers, including private contact details, only in this browser's localStorage; files must be selected again. A valid request downloads a JSON file containing answers and attachment metadata, with a clear **nothing was sent** message. No endpoint, upload, publication, or request processing is connected. Without JavaScript, content remains readable and editable, while draft/submit buttons are disabled to prevent accidental URL submission. All shared “Ajouter” links now open this page.

Content is static. The listing preserves the reference's five cards named “Le Comptoir,” their cuisine/location labels, and the initial “27 spots trouvés.” label. Using a local filter, sort, or search recomputes the count from the actual five records. No extra restaurant records were invented.

Local search, cuisine filters, sorting, dialogs, email links, and homepage/listing/details/map navigation work in the browser. No database, authentication, submission API, tracking, or WordPress theme was added. The add-restaurant draft is stored locally only when explicitly requested. Other page connections and dynamic features remain for later tasks.

## Homepage animation preview

The first motion pass is limited to `index.html`. It uses locally bundled **GSAP 3.14.2**, ScrollTrigger, SplitText, and ScrollSmoother from the official npm package. Text lines reveal as you scroll and split again when fonts/widths change; cards and sections fade/slide into view once; decorative stickers float while visible; buttons lift gently on desktop hover. Existing layout, colors, photos, and content remain the reference.

ScrollSmoother runs on desktop devices with a fine pointer from 1024px; phones/tablets keep native touch scrolling. OS/browser **Reduce motion** disables reveals, floating, hover motion, and smooth scrolling, including live changes to that preference. The page stays readable with JavaScript disabled or a missing animation dependency. Homepage search, filtering, anchor links, keyboard focus, and dialogs continue working. Other pages load none of these animation files.

Change timing and target selectors in `assets/js/home-motion.js`; hover/line styles are in `assets/css/pages/home-motion.css`. `src/partials/home-motion-assets.html` registers the local dependencies through the shared builder. No CDN, npm install, or extra asset download is needed for normal previewing. Vendor versions/source/license notices are in `assets/js/vendor/gsap/NOTICE.txt`. Preview locally, pull updates, and hard-refresh as usual. The homepage banner actions now link to the existing map and add-restaurant pages.

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
SITE_URL=http://127.0.0.1:8000/ node tests/map.cjs
SITE_URL=http://127.0.0.1:8000/ node tests/add-restaurant.cjs
SITE_URL=http://127.0.0.1:8000/ node tests/home-motion.cjs
```

`PLAYWRIGHT_MODULE` may point to another existing Playwright installation; `CHROMIUM_PATH` overrides `/usr/bin/chromium`. Screenshots and reports go to ignored `tests/results/`.

All five pages are checked at 20 widths from 320px to 2560px for overflow, type, responsive layouts, keyboard navigation, local controls, dialogs, and no-JavaScript navigation. Complete original-image verification still requires the user's local assets; no stock substitutions were made. The supplied palette and its existing contrast limitations are preserved. Reports are recorded in `validation.json`, `validation-restaurants.json`, `validation-details.json`, `validation-map.json`, and `validation-add-restaurant.json`.

`tests/home-motion.cjs` additionally checks normal-motion behavior, 20 live resize widths, local dependency requests, responsive line splitting, scrolling/filtering, hover/float effects, preference changes, keyboard dialogs, failure recovery, and static fallbacks. Its report is `validation-home-motion.json`. Complete original-photo/SVG appearance still requires the local downloader.
