# Le spot halal — Le Comptoir restaurant detail

Desktop-only HTML, CSS, and JavaScript for Figma frame `2:2267`:
https://www.figma.com/design/vKBLU6mx3eUbyaZgU4FLRZ/Untitled?node-id=2-2267

## Open and download the original assets

Run `python3 download-assets.py` in this folder, then open `index.html`. Alternatively run `python3 -m http.server 8000` and visit `http://localhost:8000`.

`asset-manifest.json` and `download-assets.py` cover all 19 original Figma images and SVGs. Runtime markup uses local paths under `assets/`. Downloads are left to the owner as requested. The script uses curl, skips existing non-empty files, writes temporary files before completing each download, and reports failures. Source URLs were retrieved on October 21, 2026 and expire after seven days; refresh them through Figma MCP when necessary.

Parkinsans and a Latin subset of Inter are bundled locally with their SIL Open Font License texts. Inter covers this page's French text, punctuation and euro prices.

## Desktop layout

1920 × 3272 pixel reference, no mobile breakpoints or build dependencies. The page reuses the prior header and footer and uses semantic sections with CSS Grid and Flexbox for the restaurant hero and two-column details.

Included sections: hero, photo thumbnails, practical information, restaurant description, five menu entries and prices, three reviews, dietary/alcohol information, map preview, and delivery contact panel. Photo 2 and Photo 3 remain the design's labeled placeholders. The design supplies one actual restaurant photo, repeated in the hero and two visible thumbnail positions. A duplicate overlapping thumbnail layer in the Figma source is represented by its single visible slot.

## Controls

- The thumbnail and “Voir plus de photos” buttons open the photo gallery dialog.
- “Voir la carte” in the hero scrolls to the restaurant menu.
- “Livraison” scrolls to the delivery section.
- “Commander en livraison” opens a dialog with the supplied telephone and email contact links; no order API or external delivery provider was supplied.
- Practical telephone/email links use `tel:` and `mailto:`. The supplied phone number is preserved from the design.
- The map preview links to `../le-spot-halal-map/index.html`.
- Header search links to the sibling restaurant listing. Homepage navigation uses `../le-spot-halal-homepage/`.
- Unknown Instagram, TikTok, and legal destinations are not invented.

## Verification

Chromium checks cover 1920px desktop dimensions, hero at x=91 / y=360 / size 1738 × 600, details at y=1000, footer at y=2682, five menu entries, three reviews, local fonts, gallery and delivery dialogs, menu shortcut, and contact links. No JavaScript errors. Exact results are in `validation.json`.

Photo/icon downloading and final image inspection are left to the owner. Their geometry and local filenames are prepared from the original Figma design context.
