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
  const url = new URL("restaurants.html", base).href;
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
      document.querySelectorAll("img").forEach((image) => {
        image.loading = "eager";
      });
      await Promise.allSettled(
        [...document.images].map((image) => image.decode()),
      );
      await document.fonts.ready;
    });
    const metrics = await page.evaluate(() => {
      const ids = [...document.querySelectorAll("[id]")].map(
        (element) => element.id,
      );
      return {
        width: innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        height: document.documentElement.scrollHeight,
        cards: document.querySelectorAll("[data-listing-card]").length,
        headings: document.querySelectorAll("h1").length,
        fontLoaded: document.fonts.check("16px Parkinsans"),
        columns: getComputedStyle(
          document.querySelector(".listing-results__cards"),
        ).gridTemplateColumns.split(" ").length,
        cardDirection: getComputedStyle(document.querySelector(".listing-card"))
          .display,
        duplicateIds: ids.length - new Set(ids).size,
        missingImages: [...document.images].filter(
          (image) => !image.complete || !image.naturalWidth,
        ).length,
        overflow: [
          ...document.querySelectorAll(
            "h1,h2,.button,.category,.listing-card,.listing-sort,.listing-filters__reset",
          ),
        ]
          .filter((element) => {
            const box = element.getBoundingClientRect();
            return box.width && (box.left < -1 || box.right > innerWidth + 1);
          })
          .map((element) => element.className),
      };
    });
    assert.equal(metrics.documentWidth, width, `Page overflow at ${width}px`);
    assert.deepEqual(metrics.overflow, [], `Controls overflow at ${width}px`);
    assert.equal(metrics.cards, 5);
    assert.equal(metrics.headings, 1);
    assert.equal(metrics.duplicateIds, 0);
    assert(metrics.fontLoaded);
    assert.equal(metrics.columns, width >= 640 && width < 1024 ? 2 : 1);
    if (width === 390) assert(Math.abs(metrics.height - 2949) <= 1);
    if (width === 1920) assert(Math.abs(metrics.height - 2835) <= 1);
    if ([390, 768, 1024, 1366, 1920].includes(width)) {
      await page.screenshot({
        path: path.join(output, `restaurants-${width}.png`),
        fullPage: true,
      });
    }
    sizes.push(metrics);
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(url);
  assert.equal(
    (await page.locator("#result-count").textContent()).trim(),
    "27 spots trouvés.",
  );
  await page.locator('[data-cuisine="Burgers"]').click();
  assert.equal(await page.locator("[data-listing-card]:visible").count(), 1);
  assert.equal(
    await page.locator("#result-count").textContent(),
    "1 spot trouvé.",
  );
  await page.locator('[data-cuisine="Café"]').click();
  assert.equal(await page.locator("[data-listing-card]:visible").count(), 2);
  await page.locator("[data-reset-listing]").click();
  assert.equal(await page.locator("[data-listing-card]:visible").count(), 5);
  assert.equal(
    await page.locator("#result-count").textContent(),
    "5 spots trouvés.",
  );
  await page.locator('[data-cuisine="Street food"]').click();
  assert(await page.locator("#empty-results").isVisible());
  assert.equal(
    await page.locator("#result-count").textContent(),
    "0 spots trouvés.",
  );
  await page.locator("[data-reset-listing]").click();
  await page.locator("#listing-sort").selectOption("category");
  assert.deepEqual(
    await page
      .locator("[data-listing-card]")
      .evaluateAll((cards) => cards.map((card) => card.dataset.category)),
    ["Burgers", "Café", "Healthy", "Marocain", "Pizza"],
  );
  await page.locator("[data-reset-listing]").click();
  assert.equal(
    await page
      .locator("[data-listing-card]")
      .first()
      .getAttribute("data-order"),
    "0",
  );

  await page.locator("[data-menu-toggle]").click();
  await page.locator(".site-header__search").click();
  assert(!(await page.locator("[data-navigation]").isVisible()));
  assert(
    await page.locator("#listing-search").evaluate((dialog) => dialog.open),
  );
  await page.locator("#listing-query").fill("cafe");
  await page.locator("#listing-query").press("Enter");
  assert.equal(
    await page
      .locator("[data-listing-card]:visible")
      .getAttribute("data-category"),
    "Café",
  );
  await page.locator("[data-reset-listing]").click();
  await page.locator(".site-header__search").click();
  await page.locator("#listing-query").fill("Eaux-Vives");
  await page.locator("#listing-query").press("Enter");
  assert.equal(
    await page
      .locator("[data-listing-card]:visible")
      .getAttribute("data-category"),
    "Marocain",
  );
  await page.locator("[data-reset-listing]").click();
  await page.locator("[data-menu-toggle]").click();
  await page.locator('[data-navigation] [data-action="map"]').click();
  assert(await page.locator("#info-dialog").evaluate((dialog) => dialog.open));
  await page.keyboard.press("Escape");
  assert(
    !(await page.locator("#info-dialog").evaluate((dialog) => dialog.open)),
  );
  await page.locator(".site-header__brand").click();
  assert(page.url().endsWith("/index.html"));
  await page.locator("[data-menu-toggle]").click();
  await page.locator('[data-navigation] a[href="restaurants.html"]').click();
  assert(page.url().endsWith("/restaurants.html"));
  assert.equal(
    await page.locator('[data-navigation] [aria-current="page"]').count(),
    1,
  );

  const noJs = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const staticPage = await noJs.newPage();
  await staticPage.goto(url);
  assert(await staticPage.locator("[data-navigation]").isVisible());
  assert.equal(await staticPage.locator("[data-listing-card]").count(), 5);
  await noJs.close();
  assert.deepEqual(errors, []);
  const report = {
    sizes,
    checks: [
      "20 responsive widths",
      "one and two column mobile/tablet grid",
      "horizontal desktop cards and sidebar",
      "mobile/desktop reference heights",
      "font loading and unique IDs",
      "multi-cuisine filtering",
      "reset and empty results",
      "category sorting and restored original order",
      "accent-insensitive search and neighborhood query",
      "shared menu and search dialogs",
      "shared map dialog and Escape dismissal",
      "homepage/listing navigation",
      "navigation and content without JavaScript",
    ],
    errors,
    visualLimit: sizes.some((size) => size.missingImages)
      ? "Original assets remain on the user’s machine; complete loaded-image verification is pending."
      : null,
  };
  fs.writeFileSync(
    path.join(output, "validation-restaurants.json"),
    JSON.stringify(report, null, 2) + "\n",
  );
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
