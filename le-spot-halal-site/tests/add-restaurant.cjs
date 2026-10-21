const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const { chromium } = require(
  process.env.PLAYWRIGHT_MODULE || "playwright-core",
);
(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || "/usr/bin/chromium",
    headless: true,
    args: process.env.CI ? ["--no-sandbox"] : [],
  });
  const context = await browser.newContext({
    reducedMotion: "reduce",
    acceptDownloads: true,
  });
  const page = await context.newPage();
  const base =
    process.env.SITE_URL || "http://127.0.0.1:8010/le-spot-halal-site/";
  const url = new URL("add-restaurant.html", base).href;
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  const posts = [];
  page.on("request", (request) => {
    if (request.method() === "POST") posts.push(request.url());
  });
  const output = path.join(__dirname, "results");
  fs.mkdirSync(output, { recursive: true });
  const sizes = [];
  for (const width of [
    320, 360, 375, 390, 430, 540, 639, 640, 767, 768, 820, 912, 1023, 1024,
    1280, 1366, 1440, 1600, 1920, 2560,
  ]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(url);
    await page.evaluate(async () => {
      await document.fonts.ready;
      await Promise.allSettled([...document.images].map((img) => img.decode()));
    });
    const metrics = await page.evaluate(() => {
      const ids = [...document.querySelectorAll("[id]")].map((el) => el.id);
      return {
        width: innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        height: document.documentElement.scrollHeight,
        sections: document.querySelectorAll(".add-section").length,
        controls: document.querySelectorAll(
          "#restaurant-form input,#restaurant-form select,#restaurant-form textarea",
        ).length,
        headings: document.querySelectorAll("h1").length,
        duplicateIds: ids.length - new Set(ids).size,
        fontLoaded: document.fonts.check("16px Parkinsans"),
        missingImages: [...document.images].filter(
          (img) => !img.complete || !img.naturalWidth,
        ).length,
        overflow: [...document.querySelectorAll("main *")]
          .filter((el) => {
            const r = el.getBoundingClientRect();
            return r.width && (r.right > innerWidth + 1 || r.left < -1);
          })
          .map((el) => el.className),
        unlabeled: [
          ...document.querySelectorAll(
            "#restaurant-form input,#restaurant-form select,#restaurant-form textarea",
          ),
        ]
          .filter((el) => !el.getAttribute("aria-label") && !el.labels?.length)
          .map((el) => el.name),
      };
    });
    assert.equal(metrics.documentWidth, width);
    assert.deepEqual(metrics.overflow, [], `Overflow at ${width}`);
    assert.deepEqual(metrics.unlabeled, []);
    assert.equal(metrics.sections, 6);
    assert.equal(metrics.controls, 98);
    assert.equal(metrics.headings, 1);
    assert.equal(metrics.duplicateIds, 0);
    assert(metrics.fontLoaded);
    if (width === 1920)
      assert(
        Math.abs(metrics.height - 10641) < 40,
        `Desktop reference height changed: ${metrics.height}`,
      );
    if ([390, 768, 1024, 1920].includes(width))
      await page.screenshot({
        path: path.join(output, `add-restaurant-${width}.png`),
        fullPage: true,
      });
    sizes.push(metrics);
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(url);
  assert.equal(await page.locator("#restaurant-form input:checked").count(), 0);
  await page.locator('[href="#part-3"]').click();
  assert.equal(new URL(page.url()).hash, "#part-3");
  await page.locator("#submit-request").click();
  assert(
    await page
      .locator("#form-status")
      .textContent()
      .then((s) => s.includes("Vérifiez")),
  );
  assert((await page.locator("#restaurant-form :invalid").count()) > 0);
  const firstDay = page.locator("[data-hours-row]").first();
  await firstDay.locator("input[type=checkbox]").check();
  assert.equal(await firstDay.locator(".add-control:disabled").count(), 2);
  await firstDay.locator("input[type=checkbox]").uncheck();
  assert(
    await firstDay
      .locator(".add-control")
      .first()
      .evaluate((el) => el.required),
  );
  await firstDay.locator(".add-control").first().fill("25:90");
  assert(
    !(await firstDay
      .locator(".add-control")
      .first()
      .evaluate((el) => el.checkValidity())),
  );
  await firstDay.locator(".add-control").first().fill("12:00 – 14:00");
  assert(
    await firstDay
      .locator(".add-control")
      .first()
      .evaluate((el) => el.checkValidity()),
  );
  const photo = page.locator("[data-photo-upload]");
  await photo.setInputFiles({
    name: "invalid.txt",
    mimeType: "text/plain",
    buffer: Buffer.from("text"),
  });
  assert(!(await photo.evaluate((el) => el.checkValidity())));
  assert(
    await photo
      .locator("..")
      .locator("output")
      .textContent()
      .then((s) => s.includes("accepté")),
  );
  await photo.setInputFiles({
    name: "large.png",
    mimeType: "image/png",
    buffer: Buffer.alloc(10 * 1024 * 1024 + 1),
  });
  assert(!(await photo.evaluate((el) => el.checkValidity())));
  await photo.setInputFiles({
    name: "restaurant.png",
    mimeType: "image/png",
    buffer: Buffer.from("local file fixture"),
  });
  assert(await photo.evaluate((el) => el.checkValidity()));
  assert(
    await page.locator("[data-photo-consent]").evaluate((el) => el.required),
  );
  const name = page.locator("#restaurant-form input[type=text]").first();
  await name.fill("Restaurant test");
  await page.locator("#save-draft").click();
  await page.reload();
  assert.equal(await name.inputValue(), "Restaurant test");
  assert.equal(await photo.evaluate((el) => el.files.length), 0);
  assert(
    !(await page.locator("[data-photo-consent]").evaluate((el) => el.required)),
  );
  const stored = await page.evaluate(() =>
    JSON.parse(
      localStorage.getItem("le-spot-halal-shared-restaurant-draft-v1"),
    ),
  );
  assert(!JSON.stringify(stored).includes("restaurant.png"));
  // Fill a complete local request, including every exclusive radio group and required confirmation.
  await page.evaluate(() => {
    const form = document.querySelector("#restaurant-form");
    for (const control of form.elements) {
      if (control.type === "file" || control.disabled) continue;
      if (control.type === "radio") {
        if (!form.querySelector(`input[name="${control.name}"]:checked`))
          control.checked = true;
      } else if (control.type === "checkbox") {
        control.checked = true;
        control.dispatchEvent(new Event("change", { bubbles: true }));
      } else if (control.tagName === "SELECT") {
        control.value =
          [...control.options].find((option) => option.value)?.value || "";
      } else if (control.required) {
        control.value =
          control.type === "email"
            ? "test@example.com"
            : control.type === "url"
              ? "https://example.com"
              : control.type === "tel"
                ? "+33450000000"
                : "Restaurant test";
      }
    }
  });
  await photo.setInputFiles({
    name: "restaurant.png",
    mimeType: "image/png",
    buffer: Buffer.from("local file fixture"),
  });
  assert(
    await page.locator("#restaurant-form").evaluate((el) => el.checkValidity()),
  );
  const downloadPromise = page.waitForEvent("download");
  await page.locator("#submit-request").click();
  const download = await downloadPromise;
  assert.equal(
    download.suggestedFilename(),
    "demande-referencement-restaurant.json",
  );
  const result = JSON.parse(fs.readFileSync(await download.path(), "utf8"));
  assert.equal(result.attachmentContentsIncluded, false);
  assert(result.answers.length > 30);
  assert.equal(result.attachments[0].name, "restaurant.png");
  assert.equal(result.attachments[0].size, 18);
  assert(await page.locator("#submission-dialog").evaluate((el) => el.open));
  assert(
    (await page.locator("#submission-message").textContent()).includes(
      "Aucun envoi",
    ),
  );
  await page.keyboard.press("Escape");
  assert(
    await page
      .locator("#submit-request")
      .evaluate((el) => el === document.activeElement),
  );
  await page.locator(".site-header__brand").click();
  assert(page.url().endsWith("/index.html"));
  await page.locator(".site-footer__add").click();
  assert(page.url().endsWith("/add-restaurant.html"));
  const noJs = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const staticPage = await noJs.newPage();
  await staticPage.goto(url);
  assert.equal(await staticPage.locator(".add-section").count(), 6);
  assert(await staticPage.locator("#submit-request").isDisabled());
  assert(await staticPage.locator("[data-navigation]").isVisible());
  await staticPage
    .locator("#restaurant-form input[type=text]")
    .first()
    .fill("Private answer");
  await staticPage
    .locator("#restaurant-form input[type=text]")
    .first()
    .press("Enter");
  assert.equal(staticPage.url(), url);
  await noJs.close();
  assert.deepEqual(errors, []);
  assert.deepEqual(posts, []);
  const report = {
    sizes,
    checks: [
      "20 responsive widths; all 98 labeled controls and six sections without overflow",
      "desktop geometry compared with original export; mobile/tablet layout inferred",
      "empty initial choices and section anchors",
      "required fields, schedule format and closed-day state",
      "file type/size validation and conditional photo consent",
      "explicit local draft restoration without attachment contents",
      "valid local JSON export; contact data never posted",
      "keyboard dialog dismissal and focus return",
      "shared add-page navigation and no-JavaScript submit prevention",
    ],
    errors,
    posts,
    visualLimit: sizes.some((s) => s.missingImages)
      ? "Original SVGs require the user’s local asset download for final visual verification."
      : null,
  };
  fs.writeFileSync(
    path.join(output, "validation-add-restaurant.json"),
    JSON.stringify(report, null, 2) + "\n",
  );
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
