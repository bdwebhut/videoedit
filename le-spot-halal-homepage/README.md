# Le spot halal — desktop homepage

Plain HTML, CSS, and JavaScript converted from Figma file `vKBLU6mx3eUbyaZgU4FLRZ`, frame `2:122`.

## Open

1. Run `python3 download-assets.py` from a network that can access `www.figma.com`.
2. Open `index.html` in a browser, or run `python3 -m http.server 8000` in this directory and visit `http://localhost:8000`.

The design is a fixed **1920 × 6031 px desktop canvas**. Browser zoom can be used for inspection on smaller monitors. No mobile styles, breakpoints, frameworks, or build tools are included. The original positioned composition is preserved for this desktop-only handoff, including Figma node IDs for inspection.

Files: `index.html`, `styles.css`, `script.js`, `assets/`, and `asset-manifest.json`.

## Asset status

Parkinsans variable font is bundled locally. Original Figma photographs and vector icons are referenced locally, but their downloads were blocked by this workspace's network policy. They are not replaced with stock imagery or screenshot exports. The included download script fetches the exact original files with curl. The manifest's temporary download URLs were retrieved on October 21, 2026 and expire after seven days; refresh them through Figma MCP if needed. There are no temporary Figma URLs in the page's runtime sources.

The page cannot be considered visually verified until those assets are downloaded. Do not ship with missing assets.

## Interactions

Search and category filters work against the five restaurant cards in the design. Navigation scrolls to homepage sections. Contact and add-restaurant actions use email. Restaurant details open local dialogs with links to Google Maps. City selections outside Genève and map actions open map searches because no restaurant database, map API, or secondary page was supplied. Legal and social destinations have no invented URLs. Replace these with production routes when integrating.

## Validation

Desktop DOM and CSS are checked using Chromium at 1920px; image completeness remains blocked as described above. No mobile behavior was added or checked.
