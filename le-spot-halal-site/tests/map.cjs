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
  const context = await browser.newContext({ reducedMotion: "reduce" });
  const page = await context.newPage();
  const base =
    process.env.SITE_URL || "http://127.0.0.1:8010/le-spot-halal-site/";
  const url = new URL("map.html", base).href;
  const errors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  const sizes = [];
  const output = path.join(__dirname, "results");
  fs.mkdirSync(output, { recursive: true });
  for (const width of [
    320, 360, 375, 390, 430, 540, 639, 640, 767, 768, 820, 912, 1023, 1024,
    1280, 1366, 1440, 1600, 1920, 2560,
  ]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(url);
    await page.evaluate(async () => {
      document
        .querySelectorAll("img")
        .forEach((img) => (img.loading = "eager"));
      await Promise.allSettled([...document.images].map((img) => img.decode()));
      await document.fonts.ready;
    });
    const metrics = await page.evaluate(() => {
      const ids = [...document.querySelectorAll("[id]")].map((el) => el.id);
      const cards = document.querySelector(".map-cards");
      const map = document.querySelector(".map-canvas").getBoundingClientRect();
      const results = document
        .querySelector(".map-results")
        .getBoundingClientRect();
      return {
        width: innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        height: document.documentElement.scrollHeight,
        cards: document.querySelectorAll("[data-map-card]").length,
        markers: document.querySelectorAll("[data-marker-target]").length,
        headings: document.querySelectorAll("h1").length,
        duplicateIds: ids.length - new Set(ids).size,
        fontLoaded: document.fonts.check("16px Parkinsans"),
        columns: getComputedStyle(cards).gridTemplateColumns.split(" ").length,
        mapBeforeResults: map.bottom <= results.top,
        mapBesideResults: map.left > results.right,
        map: { x: map.x, y: map.y, width: map.width, height: map.height },
        missingImages: [...document.images].filter(
          (img) => !img.complete || !img.naturalWidth,
        ).length,
        overflow: [
          ...document.querySelectorAll(
            "h1,h2,.map-search,.map-city,.map-filter,.map-marker,.map-card__tags,.map-card__quote",
          ),
        ]
          .filter((el) => {
            const r = el.getBoundingClientRect();
            return (
              r.width &&
              (r.left < -1 ||
                r.right > innerWidth + 1 ||
                el.scrollWidth > el.clientWidth + 1)
            );
          })
          .map((el) => el.className),
      };
    });
    assert.equal(metrics.documentWidth, width, `Page overflow at ${width}`);
    assert.deepEqual(metrics.overflow, [], `Content overflow at ${width}`);
    assert.equal(metrics.cards, 6);
    assert.equal(metrics.markers, 7);
    assert.equal(metrics.headings, 1);
    assert.equal(metrics.duplicateIds, 0);
    assert(metrics.fontLoaded);
    assert.equal(
      metrics.columns,
      width < 640 || (width >= 1024 && width < 1440) ? 1 : 2,
    );
    assert(width < 1024 ? metrics.mapBeforeResults : metrics.mapBesideResults);
    if (width === 390) {
      assert(Math.abs(metrics.height - 2440) <= 1);
      assert(Math.abs(metrics.map.y - 399) <= 1);
      assert.equal(metrics.map.height, 430);
    }
    if (width === 1920) {
      assert(Math.abs(metrics.height - 2306) <= 1);
      assert.equal(metrics.map.x, 678);
      assert.equal(metrics.map.width, 1155);
      assert.equal(metrics.map.height, 1153);
    }
    if ([390, 768, 1024, 1366, 1920].includes(width))
      await page.screenshot({
        path: path.join(output, `map-${width}.png`),
        fullPage: true,
      });
    sizes.push(metrics);
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(url);
  await page.locator("#map-query").fill("Pizza");
  await page.locator("#map-query").press("Enter");
  assert.equal(await page.locator("[data-map-card]:visible").count(), 2);
  assert.equal(await page.locator("[data-marker-target]:visible").count(), 3);
  await page.locator("[data-map-filter]").click();
  await page.locator("[data-map-reset]").click();
  await page.locator('input[value="Healthy"]').check();
  await page.locator("#map-filter-form button[type=submit]").click();
  assert.equal(await page.locator("[data-map-card]:visible").count(), 1);
  assert.equal(await page.locator("[data-marker-target]:visible").count(), 1);
  assert.equal(
    await page.locator("[data-filter-label]").textContent(),
    "Filtres (1)",
  );
  await page.locator("[data-map-filter]").click();
  await page.locator('input[value="Pizza"]').check();
  await page.locator("#map-filter-form button[type=submit]").click();
  assert.equal(await page.locator("[data-map-card]:visible").count(), 3);
  await page.locator("[data-map-filter]").click();
  await page.locator("[data-map-reset]").click();
  await page.keyboard.press("Escape");
  assert.equal(await page.locator("[data-map-card]:visible").count(), 6);
  assert.equal(
    await page.locator("[data-filter-label]").textContent(),
    "Filtres",
  );
  await page.locator("#map-query").fill("cafe");
  await page.locator("#map-query").press("Enter");
  assert.equal(
    await page.locator("[data-map-card]:visible").getAttribute("data-category"),
    "Café",
  );
  await page.locator("#map-query").fill("not-present");
  await page.locator("#map-query").press("Enter");
  assert(await page.locator("#map-empty").isVisible());
  assert.equal(await page.locator("[data-marker-target]:visible").count(), 0);
  await page.locator("#map-query").fill("");
  await page.locator("#map-query").press("Enter");
  await page.locator("[data-map-city]").click();
  assert(await page.locator("#map-city-dialog").evaluate((el) => el.open));
  await page.keyboard.press("Escape");
  assert(
    await page
      .locator("[data-map-city]")
      .evaluate((el) => el === document.activeElement),
  );
  await page.locator('[data-select-card="1"]').focus();
  await page.keyboard.press("Space");
  assert.equal(
    await page.locator('[data-marker-target="1"]').getAttribute("aria-pressed"),
    "true",
  );
  await page.setViewportSize({ width: 1024, height: 900 });
  await page.goto(url);
  await page.locator('[data-marker-target="4"]').first().click();
  assert.equal(
    await page.locator('[data-select-card="4"]').getAttribute("aria-pressed"),
    "true",
  );
  assert.equal(
    await page.locator('[data-marker-target="4"][aria-pressed="true"]').count(),
    2,
  );
  assert(
    (await page.locator(".map-results").evaluate((el) => el.scrollTop)) > 0,
  );
  await page.locator(".map-card__link").click();
  assert(page.url().endsWith("/details.html"));
  await page.locator('[data-navigation] [data-action="map"]').click();
  assert(page.url().endsWith("/map.html"));
  await page.locator(".site-header__search").click();
  assert(
    await page
      .locator("#map-query")
      .evaluate((el) => el === document.activeElement),
  );
  const noJs = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const staticPage = await noJs.newPage();
  await staticPage.goto(url);
  assert(await staticPage.locator("[data-navigation]").isVisible());
  assert.equal(await staticPage.locator("[data-map-card]").count(), 6);
  assert.equal(await staticPage.locator("[data-marker-target]").count(), 7);
  await staticPage.locator(".map-card__link").click();
  assert(staticPage.url().endsWith("/details.html"));
  await noJs.close();
  assert.deepEqual(errors, []);
  const report = {
    sizes,
    checks: [
      "20 responsive widths without overflow",
      "mobile map before cards and desktop map beside results",
      "reference dimensions and map placement",
      "six cards including repeated Pizza Time and seven markers",
      "accent-insensitive search, multi-cuisine filters, reset and empty state",
      "marker/card selection, linked duplicate markers and panel scrolling",
      "keyboard activation and dialog Escape/focus return",
      "shared map navigation and restaurant details link",
      "static content and links without JavaScript",
    ],
    errors,
    visualLimit: sizes.some((size) => size.missingImages)
      ? "Original photos and SVGs are not present in this checkout; final image verification requires the user’s local download."
      : null,
  };
  fs.writeFileSync(
    path.join(output, "validation-map.json"),
    JSON.stringify(report, null, 2) + "\n",
  );
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
