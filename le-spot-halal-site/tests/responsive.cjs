/* Optional browser checks: PLAYWRIGHT_MODULE may point to a local playwright-core install. */
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
    await page.goto(base);
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
      const site = document.querySelector(".site-shell");
      const grid = document.querySelector(".restaurant-grid");
      const heading = document.querySelector("h1").getBoundingClientRect();
      const hero = document
        .querySelector(".hero__copy")
        .getBoundingClientRect();
      const ids = [...document.querySelectorAll("[id]")].map(
        (element) => element.id,
      );
      return {
        width: innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        height: document.documentElement.scrollHeight,
        siteWidth: site.getBoundingClientRect().width,
        columns: getComputedStyle(grid).gridTemplateColumns.split(" ").length,
        cards: document.querySelectorAll("[data-restaurant]").length,
        headings: document.querySelectorAll("h1").length,
        fontLoaded: document.fonts.check("16px Parkinsans"),
        duplicateIds: ids.length - new Set(ids).size,
        heroTextFits:
          heading.left >= hero.left &&
          heading.right <= hero.right &&
          heading.bottom <= hero.bottom,
        heroColor: getComputedStyle(document.querySelector(".hero__copy"))
          .backgroundColor,
        missingImages: [...document.images].filter(
          (image) => !image.complete || !image.naturalWidth,
        ).length,
        overflow: [
          ...document.querySelectorAll(
            "h1,h2,h3,.button,.category,.restaurant-card,.search-form,.city-filter,.contact-row,.site-footer__contact",
          ),
        ]
          .filter((element) => {
            const box = element.getBoundingClientRect();
            return box.width && (box.left < -1 || box.right > innerWidth + 1);
          })
          .map((element) => element.className),
      };
    });
    assert.equal(
      metrics.documentWidth,
      width,
      `Horizontal overflow at ${width}px`,
    );
    assert.equal(metrics.cards, 5);
    assert.equal(metrics.headings, 1);
    assert.equal(metrics.duplicateIds, 0);
    assert(
      metrics.fontLoaded && metrics.heroTextFits,
      `Typography at ${width}px`,
    );
    assert.deepEqual(metrics.overflow, [], `Controls overflow at ${width}px`);
    assert.equal(
      metrics.columns,
      width < 640 ? 1 : width < 1024 ? 2 : width < 1440 ? 3 : 5,
    );
    assert.equal(
      metrics.heroColor,
      width < 768 ? "rgb(49, 23, 245)" : "rgb(47, 3, 17)",
    );
    if ([390, 768, 1024, 1366, 1920].includes(width)) {
      await page.screenshot({
        path: path.join(output, `homepage-${width}.png`),
        fullPage: true,
      });
    }
    sizes.push(metrics);
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(base);
  const menu = page.locator("[data-menu-toggle]");
  const navigation = page.locator("[data-navigation]");
  assert(!(await navigation.isVisible()));
  await menu.focus();
  await page.keyboard.press("Enter");
  assert(await navigation.isVisible());
  assert.equal(await menu.getAttribute("aria-expanded"), "true");
  await page.keyboard.press("Escape");
  assert(!(await navigation.isVisible()));
  assert(await menu.evaluate((element) => element === document.activeElement));
  await menu.click();
  await navigation.locator('a[href="#about"]').click();
  assert(!(await navigation.isVisible()));
  await page.setViewportSize({ width: 1366, height: 900 });
  assert(await navigation.isVisible());
  assert(!(await menu.isVisible()));
  await page.setViewportSize({ width: 390, height: 844 });
  assert(!(await navigation.isVisible()));

  await page.locator("#restaurant-query").fill("Maison Yemma");
  await page.locator("#restaurant-query").press("Enter");
  assert.equal(await page.locator("[data-restaurant]:visible").count(), 1);
  assert.equal(
    await page.locator("#results-status").textContent(),
    "1 restaurant trouvé",
  );
  await page.locator('[data-action="reset"]').click();
  assert.equal(await page.locator("[data-restaurant]:visible").count(), 5);
  await page.locator('[data-filter="burger"]').click();
  assert.equal(
    await page.locator("[data-restaurant]:visible").getAttribute("data-name"),
    "Smash Club",
  );
  await page.locator('[data-action="reset"]').click();
  await page.locator("#restaurant-query").fill("cafe");
  await page.locator(".search-form__submit").click();
  assert.equal(
    await page.locator("[data-restaurant]:visible").getAttribute("data-name"),
    "Nomad Coffee",
  );
  await page.locator("#restaurant-query").fill("no-such-place");
  await page.locator(".search-form__submit").click();
  assert.equal(
    await page.locator("#results-status").textContent(),
    "0 restaurants trouvés",
  );
  await page.locator('[data-action="reset"]').click();
  await page.locator('[data-action="restaurant"]').first().focus();
  await page.keyboard.press("Enter");
  assert(
    await page.locator("#info-dialog").evaluate((element) => element.open),
  );
  assert.equal(
    await page.locator("#dialog-title").textContent(),
    "Maison Yemma",
  );
  await page.keyboard.press("Escape");
  assert(
    !(await page.locator("#info-dialog").evaluate((element) => element.open)),
  );
  await page.locator('[data-city="Annemasse"]').click();
  assert.equal(await page.locator("#dialog-title").textContent(), "Annemasse");
  await page.locator("#info-dialog button").click();

  // Shared navigation remains available if scripting is disabled.
  const noJs = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const staticPage = await noJs.newPage();
  await staticPage.goto(base);
  assert(await staticPage.locator("[data-navigation]").isVisible());
  assert.equal(await staticPage.locator("[data-restaurant]").count(), 5);
  await noJs.close();

  assert.deepEqual(errors, []);
  const report = {
    sizes,
    checks: [
      "responsive grid",
      "no horizontal overflow",
      "hero text containment",
      "desktop/mobile colors",
      "unique IDs",
      "local font",
      "keyboard menu",
      "Escape and focus return",
      "menu closes on navigation and resize",
      "search, categories, accents, empty results, reset",
      "restaurant and city dialogs",
      "no-JavaScript navigation",
    ],
    errors,
    visualLimit: sizes.some((size) => size.missingImages)
      ? "Original assets are not present; loaded-image visual verification is pending."
      : null,
  };
  fs.writeFileSync(
    path.join(output, "validation.json"),
    JSON.stringify(report, null, 2) + "\n",
  );
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
