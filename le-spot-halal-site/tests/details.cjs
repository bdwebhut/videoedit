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
  const url = new URL("details.html", base).href;
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
      const visible = (selector) =>
        !!document.querySelector(selector).getBoundingClientRect().width;
      return {
        width: innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        height: document.documentElement.scrollHeight,
        headings: document.querySelectorAll("h1").length,
        duplicateIds: ids.length - new Set(ids).size,
        menuItems: document.querySelectorAll(".detail-menu__item").length,
        reviews: document.querySelectorAll(".detail-review").length,
        fontsLoaded:
          document.fonts.check("16px Parkinsans") &&
          document.fonts.check("16px Inter"),
        reservationVisible: visible(".detail-reservation"),
        deliveryVisible: visible(".detail-delivery"),
        missingImages: [...document.images].filter(
          (img) => !img.complete || !img.naturalWidth,
        ).length,
        overflow: [
          ...document.querySelectorAll(
            "h1,h2,h3,.detail-action,.detail-info,.detail-menu__item,.detail-review",
          ),
        ]
          .filter((el) => {
            const box = el.getBoundingClientRect();
            return box.width && (box.left < -1 || box.right > innerWidth + 1);
          })
          .map((el) => el.className),
      };
    });
    assert.equal(metrics.documentWidth, width, `Page overflow at ${width}`);
    assert.deepEqual(metrics.overflow, [], `Content overflow at ${width}`);
    assert.equal(metrics.headings, 1);
    assert.equal(metrics.duplicateIds, 0);
    assert.equal(metrics.menuItems, 5);
    assert.equal(metrics.reviews, 3);
    assert(metrics.fontsLoaded);
    assert.equal(metrics.reservationVisible, width < 1024);
    assert.equal(metrics.deliveryVisible, width >= 1024);
    if (width === 390) assert(Math.abs(metrics.height - 3961) <= 1);
    if (width === 1920) assert(Math.abs(metrics.height - 3272) <= 1);
    if ([390, 768, 1024, 1366, 1920].includes(width))
      await page.screenshot({
        path: path.join(output, `details-${width}.png`),
        fullPage: true,
      });
    sizes.push(metrics);
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(url);
  await page.locator('.detail-hero a[href="#menu"]').click();
  assert.equal(new URL(page.url()).hash, "#menu");
  await page.locator('.detail-hero a[href="#reservation"]').click();
  assert.equal(new URL(page.url()).hash, "#reservation");
  await page.locator("[data-reservation]").click();
  assert(await page.locator("#reservation-dialog").evaluate((el) => el.open));
  assert.equal(
    await page.locator("#reservation-dialog a").getAttribute("href"),
    "tel:+33450000000",
  );
  await page.keyboard.press("Escape");
  assert(
    await page
      .locator("[data-reservation]")
      .evaluate((el) => el === document.activeElement),
  );
  assert(
    (await page.locator(".detail-map").getAttribute("href")).startsWith(
      "https://www.google.com/maps/search/",
    ),
  );
  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.goto(url);
  await page.locator("[data-gallery]").first().focus();
  await page.keyboard.press("Enter");
  assert(await page.locator("#gallery-dialog").evaluate((el) => el.open));
  await page.keyboard.press("Escape");
  assert(
    await page
      .locator("[data-gallery]")
      .first()
      .evaluate((el) => el === document.activeElement),
  );
  await page.locator(".detail-gallery__more").click();
  await page.locator("#gallery-dialog button").click();
  assert(!(await page.locator("#gallery-dialog").evaluate((el) => el.open)));
  await page.locator('.detail-hero a[href="#delivery"]').click();
  await page.locator("[data-delivery]").click();
  assert(await page.locator("#delivery-dialog").evaluate((el) => el.open));
  assert.equal(
    await page
      .locator('#delivery-dialog a[href^="mailto:"]')
      .getAttribute("href"),
    "mailto:bonjour@lecomptoir.fr",
  );
  await page.keyboard.press("Escape");
  await page.locator(".site-header__search").click();
  assert(page.url().endsWith("/restaurants.html"));
  await page.locator(".listing-card__link").click();
  assert(page.url().endsWith("/details.html"));
  const noJs = await browser.newContext({
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const staticPage = await noJs.newPage();
  await staticPage.goto(url);
  assert(await staticPage.locator("[data-navigation]").isVisible());
  assert.equal(await staticPage.locator(".detail-menu__item").count(), 5);
  assert.equal(await staticPage.locator(".detail-review").count(), 3);
  await noJs.close();
  assert.deepEqual(errors, []);
  const report = {
    sizes,
    checks: [
      "20 responsive widths without horizontal overflow",
      "mobile/desktop reference heights",
      "local Parkinsans and Inter fonts",
      "unique IDs and static menu/reviews",
      "mobile reservation and desktop delivery layouts",
      "menu and contact anchors",
      "keyboard gallery, Escape and focus return",
      "contact-only reservation/delivery dialogs",
      "listing/detail navigation",
      "content and navigation without JavaScript",
    ],
    errors,
    visualLimit: sizes.some((size) => size.missingImages)
      ? "Original assets remain on the user’s machine; complete loaded-image verification is pending."
      : null,
  };
  fs.writeFileSync(
    path.join(output, "validation-details.json"),
    JSON.stringify(report, null, 2) + "\n",
  );
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
