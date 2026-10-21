# Le spot halal — desktop homepage

Static HTML, CSS, and JavaScript for Figma file `vKBLU6mx3eUbyaZgU4FLRZ`, frame `2:122`. The desktop design uses a **1920 × 6031 px** canvas. No mobile layout or build step is included.

## Preview

From this directory:

```bash
python3 -m http.server 8000 --bind 127.0.0.1
```

Open http://localhost:8000. Use browser zoom to inspect the full desktop canvas on smaller screens.

The original photographs and SVGs must be present in `assets/`. If needed, run `python3 download-assets.py` on a machine with access to Figma. The temporary URLs in `asset-manifest.json` may need refreshing through Figma MCP after seven days. The font and its license are bundled locally. The user confirmed that the original downloaded assets render correctly in the earlier version; those downloaded files were not yet available in this checkout during the refactor.

## Source organization

| File | Responsibility |
| --- | --- |
| `index.html` | Header, hero, discovery/search, restaurant cards, submission banner, about cards, nearby banner, contact, footer, and dialog. |
| `styles.css` | Local reset and design tokens followed by component styles in page order. |
| `script.js` | Search, category filters, section navigation, and restaurant/map dialogs. |
| `assets/` | Original image, SVG, and font files. Paths are preserved from the initial export. |
| `asset-manifest.json`, `download-assets.py` | Download tooling; unused by the page at runtime. |
| `validation.json` | Refactor checks and their limits. |

Page sections follow reading order. Grid and flex layouts replace the export's page-wide positioning and repeated wrappers. Absolute positioning is limited to local composition details such as hero crops, badges, decorations, and the footer layout. Section spacing and image crops retain the original desktop geometry.

Reusable components include `.button`, `.restaurant-card`, `.restaurant-tag`, `.service-icon`, `.about-card`, and `.contact-row`. Variant classes handle differences in color or crop. Semantic headings, native links/buttons, a search form, and a native dialog replace scripted button roles. Export-only Figma node IDs and numbered classes have been removed.

## Behavior and integration contracts

CSS is scoped under the `.lsh-homepage` body class, with tokens defined there. Keep that class when moving the markup into a future integration. The font declaration is shared at stylesheet level. No global element reset is applied outside the homepage.

JavaScript is enclosed in a private function. Its hooks are separate from component styling:

- `[data-restaurant-search]`: search form; its input uses `name="q"`.
- `[data-restaurant]`: each restaurant article, with `data-name` for the full restaurant name.
- `[data-action="restaurant"]`: the card's native title button. Its hit area covers the card.
- `[data-filter]`: normalized category search term.
- `[data-city]`: city name used for the existing city/map interaction.
- `[data-action="reset"]`, `[data-action="map"]`: reset and map controls.
- `#results-status` and `#info-dialog`: live search feedback and shared dialog.

Restaurant cards remain ordinary server-readable HTML; JavaScript does not generate the content. To add a card, repeat the article structure with a unique heading ID, matching `aria-labelledby`, its name, and content. Initialize the script after the page markup; it gathers the initial card list once. Filtering preserves grid slots, matching the original desktop behavior.

The five cards are static demonstration content. Other cities and map controls open Google Maps searches. Email actions use `mailto:` links. Social and legal text remains informational until production destinations are supplied. This project contains no WordPress theme files, PHP templates, backend, or invented routes.

## Validation

Chromium checks cover the 1920 × 6031 canvas, original section/card geometry, preserved image references, search by full name and accents, empty results, category/reset actions, keyboard dialog activation, dialog dismissal, map/city dialogs, and navigation. The page has one primary heading and no duplicate IDs. JavaScript syntax and whitespace checks also pass.

The automated accessibility scan reports four color-contrast findings in the existing Figma palette. Colors were preserved for design fidelity. Complete visual comparison with loaded original images remains pending their arrival in this checkout; the current missing-image count is recorded in `validation.json`. No mobile behavior was added or checked.
