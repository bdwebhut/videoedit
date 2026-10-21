# Le spot halal — shared static site

The refined homepage now combines the desktop and mobile Figma conversions into **one responsive HTML page**. This is the working folder for future refinements; the original export folders remain unchanged as references. Only the homepage has been migrated.

## Download and preview on Linux

From your existing local checkout:

```bash
cd ~/le-spot-halal-local/videoedit
git pull --ff-only origin main
cd le-spot-halal-site
python3 download-assets.py
python3 -m http.server 8000 --bind 127.0.0.1
```

Open http://localhost:8000 and hard-refresh with **Ctrl+Shift+R**. Stop the server with **Ctrl+C**. No system installation, npm, or WordPress setup is required.

The downloader first imports valid original files from `../le-spot-halal-homepage/assets/` and `../le-spot-halal-mobile-home/assets/`. It downloads only missing originals from official Figma URLs. The two manifests are merged into **58 unique assets**, stored once under `assets/images/` and `assets/icons/`. The local Parkinsans font and license are already included under `assets/fonts/`.

Files are validated before use: an HTML error response will not be saved as an image or SVG. Successful existing files are kept. Temporary Figma URLs can expire after seven days; update the shared manifest with fresh Figma MCP URLs if needed. `python3 download-assets.py --local-only` imports existing files without making network requests and reports any missing originals.

## Where to edit

```text
le-spot-halal-site/
├── index.html                    # Generated, ready-to-open homepage
├── build.py                      # Standard-library static HTML builder
├── download-assets.py            # Import/download originals into shared assets
├── asset-manifest.json           # Original URLs and source-folder provenance
├── src/
│   ├── layout.html               # Shared document shell
│   ├── partials/
│   │   ├── header.html
│   │   ├── footer.html
│   │   ├── restaurant-card.html
│   │   └── info-dialog.html
│   ├── pages/home.html            # Homepage sections, in reading order
│   └── data/home-restaurants.json # Static content for the five cards
├── assets/
│   ├── css/
│   │   ├── tokens.css            # Brand colors, font, spacing, radii, shadows
│   │   ├── base.css              # Scoped reset and global text utilities
│   │   ├── components.css        # Buttons, search, filters, cards, dialog
│   │   ├── layout.css            # Responsive header and footer
│   │   └── pages/home.css        # Homepage composition only
│   ├── js/
│   │   ├── site.js               # Shared mobile navigation
│   │   └── home.js               # Existing local homepage demo interactions
│   ├── fonts/
│   ├── images/
│   └── icons/
└── tests/
    ├── test_tools.py
    └── responsive.cjs
```

Edit the source partials/page/data, then regenerate the delivered HTML:

```bash
python3 build.py
```

Do not maintain a separate copy of the header/footer inside `index.html`. The generated file is committed so normal previewing needs no build step. There are no runtime template fetches: the result also opens directly from disk. `python3 build.py --check` detects an outdated generated page.

Future pages should use this shared shell, partials, assets, and components, with their own page template, stylesheet, and optional page script. Their build entries and routes can be added when each page is migrated. Styles are scoped under `.lsh-site`; page-only composition uses `.page-home`. Figma node IDs and export class names are absent from the refined source.

## Responsive decisions

The 390px mobile and 1920px desktop references drive the layout. Content reflows between them; the page is never a scaled fixed-width canvas.

- Below 640px: stacked hero and one restaurant column.
- 640–1023px: two restaurant columns.
- 1024–1439px: three restaurant columns.
- From 1440px: five restaurant columns.
- From 768px: a two-column hero and three editorial cards, with fluid type and spacing.
- Below 1024px: an accessible collapsible navigation menu. With JavaScript disabled, navigation stays visible.
- Above 1920px: the composition is centered at its original maximum width.

The mobile reference deliberately uses blue for the hero and nearby invitation, different brand SVGs/flags, and a stacked footer. `<picture>` supplies the matching original SVGs without duplicating page content. The desktop contact panel appears from 1024px; mobile retains the footer contact details supplied in its design.

## Static scope

Content is static. The existing browser-only restaurant search, category filters, map/restaurant dialogs, email links, and an accessible menu work locally. No database, authentication, submission service, tracking, storage, or new backend feature is included. Legal/social destinations and connections to the other restaurant pages remain for a later task. This is not a WordPress theme.

## Verification

Run the build and downloader checks with Python alone:

```bash
python3 build.py --check
python3 -m unittest discover -s tests -p 'test_*.py' -v
```

Optional browser checks use `playwright-core` and an existing Chromium installation. With the site server running at port 8000 and Playwright available to Node:

```bash
SITE_URL=http://127.0.0.1:8000/ node tests/responsive.cjs
```

For an existing installation outside this folder, set `PLAYWRIGHT_MODULE` to its module path. `CHROMIUM_PATH` overrides `/usr/bin/chromium`. Screenshots and detailed results are written to ignored `tests/results/`.

Checks cover 20 widths from 320px to 2560px, overflow, grid transitions, typography, keyboard menu behavior, focus return, navigation without JavaScript, and the local homepage interactions. Reference viewport heights are 6167px at 390px and approximately 6031px at 1920px. Original asset bytes are not available in the execution checkout, so loaded-image visual verification remains pending the local import/download. The Figma palette is preserved, including its existing low-contrast orange/white combinations. See `validation.json` for the recorded results and limitations.
