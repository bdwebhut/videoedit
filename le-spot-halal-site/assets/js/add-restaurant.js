/* Local form validation, explicit browser draft, and JSON export. No network submission. */
(() => {
  "use strict";
  const DRAFT_KEY = "le-spot-halal-shared-restaurant-draft-v1";
  const form = document.querySelector("#restaurant-form");
  if (!form) return;
  form.noValidate = true;
  form
    .querySelectorAll("[data-requires-script]")
    .forEach((button) => (button.disabled = false));
  const status = document.querySelector("#form-status");
  const byName = (name) =>
    [...form.elements].filter((control) => control.name === name);
  function updateHours(row) {
    const closed = row.querySelector("input[type=checkbox]").checked;
    row.querySelectorAll(".add-control").forEach((input, index) => {
      input.disabled = closed;
      input.required = !closed && index === 0;
    });
  }
  form.querySelectorAll("[data-hours-row]").forEach((row) => {
    row
      .querySelector("input[type=checkbox]")
      .addEventListener("change", () => updateHours(row));
  });
  function validateServices() {
    const group = form.querySelector("[data-services]");
    if (!group) return;
    const checkboxes = [...group.querySelectorAll("input[type=checkbox]")];
    if (checkboxes.length)
      checkboxes[0].setCustomValidity(
        checkboxes.some((input) => input.checked)
          ? ""
          : "Choisissez au moins un service.",
      );
  }
  form
    .querySelectorAll("input[type=checkbox]")
    .forEach((input) => input.addEventListener("change", validateServices));
  function validateFiles(input) {
    const files = [...input.files];
    const invalid = files.find(
      (file) =>
        !/\.(pdf|jpe?g|png)$/i.test(file.name) || file.size > 10 * 1024 * 1024,
    );
    input.setCustomValidity(
      invalid
        ? "Choisissez des fichiers PDF, JPG ou PNG de 10 Mo maximum chacun."
        : "",
    );
    input.closest(".add-upload-zone").querySelector("output").textContent =
      invalid
        ? "Format ou taille non accepté."
        : files.map((file) => file.name).join(", ");
    updatePhotoConsent();
  }
  form.querySelectorAll("input[type=file]").forEach((input) => {
    input.addEventListener("change", () => validateFiles(input));
    const zone = input.closest(".add-upload-zone");
    zone.addEventListener("dragover", (event) => {
      event.preventDefault();
      zone.classList.add("drag-active");
    });
    zone.addEventListener("dragleave", () =>
      zone.classList.remove("drag-active"),
    );
    zone.addEventListener("drop", (event) => {
      event.preventDefault();
      zone.classList.remove("drag-active");
      input.files = event.dataTransfer.files;
      validateFiles(input);
    });
  });
  function draftData() {
    const values = {};
    for (const control of form.elements) {
      if (!control.name || control.type === "file") continue;
      if (control.type === "radio" || control.type === "checkbox") {
        if (!values[control.name]) values[control.name] = [];
        if (control.checked) values[control.name].push(control.value);
      } else values[control.name] = control.value;
    }
    return { version: 1, savedAt: new Date().toISOString(), values };
  }
  document.querySelector("#save-draft").addEventListener("click", () => {
    try {
      localStorage.setItem(DRAFT_KEY, JSON.stringify(draftData()));
      status.textContent =
        "Brouillon enregistré sur cet appareil. Les fichiers devront être sélectionnés à nouveau à la prochaine ouverture.";
    } catch {
      status.textContent =
        "Le brouillon ne peut pas être enregistré dans ce navigateur.";
    }
  });
  try {
    const draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || "null");
    if (draft?.version === 1) {
      Object.entries(draft.values).forEach(([name, value]) =>
        byName(name).forEach((control) => {
          if (control.type === "file") return;
          if (control.type === "checkbox" || control.type === "radio")
            control.checked =
              Array.isArray(value) && value.includes(control.value);
          else control.value = value;
        }),
      );
      form.querySelectorAll("[data-hours-row]").forEach(updateHours);
      status.textContent =
        "Votre brouillon a été restauré sur cet appareil. Sélectionnez à nouveau vos fichiers si nécessaire.";
    }
  } catch {
    /* A corrupt or unavailable local draft never prevents editing. */
  }
  function handoffData() {
    const answers = [];
    for (const control of form.elements) {
      if (
        !control.name ||
        control.disabled ||
        control.type === "file" ||
        ["submit", "button"].includes(control.type)
      )
        continue;
      if (["checkbox", "radio"].includes(control.type) && !control.checked)
        continue;
      answers.push({
        name: control.name,
        label: control.getAttribute("aria-label") || control.name,
        value: control.value,
        section: control.closest("section")?.id,
      });
    }
    const attachments = [...form.querySelectorAll("input[type=file]")].flatMap(
      (input) =>
        [...input.files].map((file) => ({
          field: input.name,
          label: input.getAttribute("aria-label"),
          name: file.name,
          type: file.type,
          size: file.size,
        })),
    );
    return {
      restaurantSubmissionVersion: 1,
      preparedAt: new Date().toISOString(),
      answers,
      attachments,
      attachmentContentsIncluded: false,
    };
  }
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    validateServices();
    form.querySelectorAll("input[type=file]").forEach(validateFiles);
    form.classList.add("was-validated");
    if (!form.checkValidity()) {
      const first = form.querySelector(":invalid");
      first
        ?.closest("section")
        ?.scrollIntoView({
          behavior: matchMedia("(prefers-reduced-motion: reduce)").matches
            ? "auto"
            : "smooth",
          block: "start",
        });
      first?.focus({ preventScroll: true });
      first?.reportValidity();
      status.textContent =
        "Vérifiez les champs obligatoires, les horaires et les confirmations avant de continuer.";
      return;
    }
    const button = document.querySelector("#submit-request");
    const blob = new Blob([JSON.stringify(handoffData(), null, 2)], {
      type: "application/json",
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "demande-referencement-restaurant.json";
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    document.querySelector("#submission-message").textContent =
      "Vos réponses ont été préparées dans un fichier à télécharger. Aucun envoi n’a été effectué. Les justificatifs ne sont pas inclus dans ce fichier et doivent être joints séparément.";
    document.querySelector("#submission-dialog").showModal();
  });
  // Photo-publication consent applies when photos are attached.
  const photoUpload = document.querySelector("[data-photo-upload]");
  const photoConsent = document.querySelector("[data-photo-consent]");
  function updatePhotoConsent() {
    if (photoConsent)
      photoConsent.required = Boolean(photoUpload?.files.length);
  }
  photoUpload?.addEventListener("change", updatePhotoConsent);
  updatePhotoConsent();
})();
