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
  const base =
    process.env.SITE_URL || "http://127.0.0.1:8011/le-spot-halal-site/";
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    reducedMotion: "no-preference",
  });
  const page = await context.newPage();
  const errors = [];
  const remoteRequests = [];
  page.on("pageerror", (error) => errors.push(error.message));
  page.on("request", (request) => {
    if (!request.url().startsWith(base) && request.url().startsWith("http"))
      remoteRequests.push(request.url());
  });
  await page.goto(base);
  await page.waitForFunction(
    () => document.body.dataset.homeMotion === "active",
  );
  await page.waitForFunction(() =>
    [...document.querySelectorAll("#hero-title .home-motion-line")].every(
      (line) => Number(getComputedStyle(line).opacity) > 0.99,
    ),
  );
  assert(await page.evaluate(() => !!ScrollSmoother.get()));
  assert.equal(
    await page.locator("#hero-title").getAttribute("aria-label"),
    "Le meilleur guide des spots halal",
  );
  const sizes = [];
  for (const width of [
    320, 360, 375, 390, 430, 540, 639, 640, 767, 768, 820, 912, 1023, 1024,
    1280, 1366, 1440, 1600, 1920, 2560,
  ]) {
    await page.setViewportSize({ width, height: 900 });
    await page.waitForTimeout(350); // SplitText's ResizeObserver debounces reflow.
    const metrics = await page.evaluate(() => ({
      width: innerWidth,
      documentWidth: document.documentElement.scrollWidth,
      height: document.documentElement.scrollHeight,
      smoother: !!ScrollSmoother.get(),
      heroLines: document.querySelectorAll("#hero-title .home-motion-line")
        .length,
      lineOverflow: [
        ...document.querySelectorAll("#hero-title .home-motion-line"),
      ].some((line) => line.scrollWidth > line.clientWidth + 1),
      wrappers: document.querySelectorAll("#home-scroll-wrapper").length,
      triggers: ScrollTrigger.getAll().length,
    }));
    assert.equal(metrics.documentWidth, width, `Overflow at ${width}`);
    assert.equal(metrics.smoother, width >= 1024);
    assert.equal(metrics.wrappers, width >= 1024 ? 1 : 0);
    assert(metrics.heroLines > 0);
    assert(!metrics.lineOverflow);
    assert(
      metrics.triggers < 60,
      "Duplicate triggers accumulated during responsive reflow",
    );
    sizes.push(metrics);
  }
  assert(
    new Set(sizes.map((size) => size.heroLines)).size > 1,
    "Line splits must respond to changing text widths",
  );
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(base);
  await page.waitForFunction(
    () => document.body.dataset.homeMotion === "active",
  );
  await page.locator(".hero__button").click();
  await page.waitForFunction(
    () =>
      Math.abs(
        document.getElementById("search").getBoundingClientRect().top - 100,
      ) < 3,
  );
  assert.equal(new URL(page.url()).hash, "#search");
  assert.equal(await page.evaluate(() => document.activeElement.id), "search");
  await page.locator("#restaurant-query").fill("Pizza");
  await page.locator("#restaurant-query").press("Enter");
  await page.waitForFunction(
    () =>
      Math.abs(
        document.getElementById("restaurants").getBoundingClientRect().top -
          100,
      ) < 3,
  );
  assert.equal(
    await page.locator("[data-restaurant]:not(.is-filtered-out)").count(),
    1,
  );
  assert(
    await page
      .locator("[data-restaurant]:not(.is-filtered-out)")
      .evaluate((el) => Number(getComputedStyle(el).opacity) > 0.99),
  );
  await page.locator('[data-action="reset"]').click();
  const before = await page
    .locator(".featured__all")
    .evaluate((el) => el.getBoundingClientRect().y);
  await page.locator(".featured__all").hover();
  await page.waitForTimeout(260);
  assert(
    (
      await page
        .locator(".featured__all")
        .evaluate((el) => getComputedStyle(el).translate)
    ).includes("-3px"),
  );
  await page.mouse.move(0, 0);
  // Every major section is revealed as it enters the viewport; later content stays readable.
  for (const selector of [
    ".featured",
    ".submit-banner",
    ".about",
    ".nearby-banner",
    ".contact",
    ".site-footer",
  ]) {
    await page.evaluate(
      (selector) => ScrollSmoother.get().scrollTo(selector, false, "top 100px"),
      selector,
    );
    await page.waitForTimeout(1100);
  }
  assert(
    await page
      .locator(".site-footer")
      .evaluate((el) => Number(getComputedStyle(el).opacity) > 0.99),
  );
  await page.evaluate(() =>
    ScrollSmoother.get().scrollTo(".nearby-banner", false, "top 100px"),
  );
  await page.waitForTimeout(1100);
  const floatStart = await page
    .locator(".nearby-sticker > span")
    .first()
    .evaluate((el) => getComputedStyle(el).transform);
  await page.waitForTimeout(250);
  assert.notEqual(
    await page
      .locator(".nearby-sticker > span")
      .first()
      .evaluate((el) => getComputedStyle(el).transform),
    floatStart,
  );
  // An OS preference change tears down splits, transforms, listeners and smoothing live.
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.waitForTimeout(300);
  assert.equal(await page.locator(".home-motion-line").count(), 0);
  assert.equal(await page.locator("#home-scroll-wrapper").count(), 0);
  assert.equal(await page.evaluate(() => ScrollTrigger.getAll().length), 0);
  assert(!(await page.evaluate(() => !!ScrollSmoother.get())));
  await page.emulateMedia({ reducedMotion: "no-preference" });
  await page.waitForFunction(
    () => document.body.dataset.homeMotion === "active",
  );
  assert.equal(await page.locator("#home-scroll-wrapper").count(), 1);
  // Native top-layer dialogs still work while content is transformed by ScrollSmoother.
  await page.evaluate(() =>
    ScrollSmoother.get().scrollTo(".featured", false, "top 100px"),
  );
  await page.locator('[data-action="restaurant"]').first().click();
  assert(await page.locator("#info-dialog").evaluate((el) => el.open));
  await page.keyboard.press("Escape");
  assert(!(await page.locator("#info-dialog").evaluate((el) => el.open)));
  const output = path.join(__dirname, "results");
  fs.mkdirSync(output, { recursive: true });
  await page.evaluate(() => ScrollSmoother.get().scrollTo(0, false));
  await page.waitForTimeout(1100);
  await page.screenshot({ path: path.join(output, "home-motion-desktop.png") });
  const touch = await browser.newContext({
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
    reducedMotion: "no-preference",
  });
  const phone = await touch.newPage();
  await phone.goto(base);
  await phone.waitForFunction(
    () => document.body.dataset.homeMotion === "active",
  );
  assert(!(await phone.evaluate(() => !!ScrollSmoother.get())));
  assert.equal(await phone.locator("#home-scroll-wrapper").count(), 0);
  await phone.waitForTimeout(1100);
  await phone.screenshot({ path: path.join(output, "home-motion-mobile.png") });
  await touch.close();
  // A missing library, an initialization error, and disabled JavaScript each retain the static site.
  const missing = await browser.newContext();
  await missing.route("**/vendor/gsap/**", (route) => route.abort());
  const fallback = await missing.newPage();
  await fallback.goto(base);
  assert.equal(await fallback.locator(".home-motion-line").count(), 0);
  assert.equal(await fallback.locator("#home-scroll-wrapper").count(), 0);
  assert(await fallback.locator("#hero-title").isVisible());
  await missing.close();
  const broken = await browser.newContext();
  await broken.route("**/SplitText.min.js", async (route) => {
    const response = await route.fetch();
    await route.fulfill({
      response,
      body:
        (await response.text()) +
        '\nwindow.SplitText.create = function(){throw new Error("test initialization failure")};',
    });
  });
  const brokenPage = await broken.newPage();
  await brokenPage.goto(base);
  await brokenPage.waitForFunction(
    () => document.body.dataset.homeMotion === "fallback",
  );
  assert.equal(await brokenPage.locator("#home-scroll-wrapper").count(), 0);
  assert.equal(await brokenPage.locator(".home-motion-line").count(), 0);
  assert(await brokenPage.locator("#hero-title").isVisible());
  await broken.close();
  const staticContext = await browser.newContext({ javaScriptEnabled: false });
  const staticPage = await staticContext.newPage();
  await staticPage.goto(base);
  assert(await staticPage.locator("#hero-title").isVisible());
  assert.equal(await staticPage.locator(".home-motion-line").count(), 0);
  assert.equal(
    await staticPage.locator(".nearby-banner__map").getAttribute("href"),
    "map.html",
  );
  assert.equal(
    await staticPage
      .locator(".submit-banner__actions .button--add")
      .getAttribute("href"),
    "add-restaurant.html",
  );
  await staticContext.close();
  await page.goto(new URL("restaurants.html", base).href);
  assert.equal(await page.locator('script[src*="gsap"]').count(), 0);
  assert.equal(await page.locator("#home-scroll-wrapper").count(), 0);
  assert.deepEqual(errors, []);
  assert.deepEqual(remoteRequests, []);
  const report = {
    sizes,
    checks: [
      "20 responsive widths with live line resplitting and bounded trigger counts",
      "local GSAP dependencies; desktop smoothing and native phone scrolling",
      "text, card and section reveals; decorative float and hover effects",
      "anchors, keyboard focus, filters, dialogs and reset",
      "live reduced-motion teardown/restart",
      "missing dependency and initialization-failure static fallback",
      "no-JavaScript content and real homepage CTA links",
      "other pages load no motion dependencies",
    ],
    errors,
    remoteRequests,
    visualLimit:
      "Original photos/SVGs are absent in this checkout; final appearance requires the local asset download.",
  };
  fs.writeFileSync(
    path.join(output, "validation-home-motion.json"),
    JSON.stringify(report, null, 2) + "\n",
  );
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
