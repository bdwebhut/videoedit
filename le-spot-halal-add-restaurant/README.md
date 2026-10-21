# Le spot halal — add-restaurant form

Desktop-only HTML, CSS and JavaScript built from Figma frame `2:1649`:
https://www.figma.com/design/vKBLU6mx3eUbyaZgU4FLRZ/Untitled?node-id=2-1649

The large design was read in separate high-fidelity sections: navigation, introduction, all six form sections, and footer. The original French content, visibility notices, empty initial fields, and six-part layout are preserved. No mobile breakpoints or runtime dependencies.

## Run and download assets

1. Run `python3 download-assets.py` in this folder.
2. Open `index.html`, or serve the folder with `python3 -m http.server 8000` and visit `http://localhost:8000`.

The script and `asset-manifest.json` include all 15 unique original SVG assets. This page has per-section Figma export URLs, so each manifest asset has its own URL and `sourceNodes` for refreshing it. The URLs were retrieved on October 21, 2026 and expire after seven days. Runtime HTML references only local filenames under `assets/`. Downloads are left to the owner. Parkinsans and its license are bundled locally.

## Form behavior

The page contains 98 editable inputs, textareas, choices, selects and file controls across six sections: restaurant information, professional contact, halal practices, alcohol practices, documents, and confirmations.

- Section cards navigate to the corresponding form section.
- All answers start empty; the yellow first navigation card is a section marker, not a preselected form answer.
- Cuisine and country fields are native selects; these option lists are editable in `index.html`.
- Weekly schedule fields accept `HH:MM – HH:MM` or `HH:MM - HH:MM`. First slots are required unless the corresponding closed-day checkbox is selected; second slots are optional.
- Services permit multiple selections; declaration questions use exclusive radio groups, including the five shared-equipment rows.
- File inputs support choosing or dropping multiple PDF/JPG/PNG files, maximum 10 MiB each. Selected names and validation errors appear locally. Photo-publication confirmation is required when photos are attached.
- Required fields and confirmations are validated before preparing a request. Invalid fields are focused with browser validation messages.
- “Enregistrer un brouillon” explicitly saves answers in localStorage on the current browser/device and restores them on reopening. Private contact answers are part of that local draft; file contents are never saved. Files must be selected again after reopening. No automatic saving or external upload occurs.

## Submission integration

No submission endpoint was supplied. In the default configuration, a valid submission downloads `demande-referencement-restaurant.json` and clearly says that nothing was sent. That file includes labeled answers and attachment metadata, not attachment contents. Original documents must be handled separately.

To connect a real backend, set `SUBMISSION_ENDPOINT` at the top of `script.js`. The form then POSTs multipart FormData including the selected files and shows a sent message only after an HTTP success response. Implement server-side validation, document handling and actual request processing in that backend. The front end does not automatically publish a restaurant.

The Figma field IDs are retained as stable control names (`field-2-...`, `choice-2-...`, `hours-...`, and `upload-2-...`). Labels and public/private visibility text remain visible beside the fields. The JSON export includes field labels and section identifiers for mapping to a backend schema.

Homepage, listing, and map navigation points to the existing sibling folders. Legal page text is retained without inventing a destination. The design's footer email remains visible; no email is sent automatically.

## Validation

Chromium checks cover the original 1920px desktop width and approximately 10641px page height, all six sections without clipped content, 98 controls, bundled font loading, required-field rejection, closed-day behavior, rejection/acceptance of file types, draft restoration, exclusion of file contents from stored drafts, valid local request preparation, and explicit no-send messaging. No JavaScript errors. Exact results are in `validation.json`.

Visual spacing and control slots were checked against the Figma screenshots; final appearance of the original SVG files is left to the owner's download step.
