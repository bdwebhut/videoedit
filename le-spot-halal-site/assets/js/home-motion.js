/* Homepage motion only. Settings, selectors and lifecycle are kept out of content templates. */
(() => {
  "use strict";
  const home = document.querySelector(".lsh-site.page-home");
  const { gsap, ScrollTrigger, ScrollSmoother, SplitText } = window;
  if (!home || !gsap || !ScrollTrigger || !ScrollSmoother || !SplitText) return;

  const settings = {
    smooth: 0.8,
    revealDuration: 0.75,
    lineStagger: 0.08,
    revealStart: "top 92%",
  };
  const textSelectors =
    "#hero-title, #mood-title, #search-title, #submit-title, #about-title, #nearby-title, #contact-title, .about__intro, .contact__intro, .about-card h3";
  // The search intro has an art-directed <br>; reveal it whole to preserve its responsive wrapping.
  const revealSelectors =
    ".hero__eyebrow, .hero__button, .hero__visual, .mood__categories, .search-form, .restaurant-search__intro, .city-filter, #featured-title, .featured__all, .restaurant-card, .submit-banner__actions, .about-card, .about__logo, .nearby-banner__actions, .contact__details, .contact__button, .contact__visual, .site-footer";
  let smoother;
  let ready = false;
  let media;
  const completedText = new WeakSet();
  const completedReveals = new WeakSet();

  async function start() {
    if (ready) return;
    ready = true;
    await document.fonts.ready;
    gsap.registerPlugin(ScrollTrigger, ScrollSmoother, SplitText);
    media = gsap.matchMedia();
    media.add(
      {
        motion: "(prefers-reduced-motion:no-preference)",
        desktop: "(min-width:64rem) and (hover:hover) and (pointer:fine)",
      },
      (context) => {
        if (!context.conditions.motion) {
          home.dataset.homeMotion = "reduced";
          return;
        }
        const splits = [];
        const revealAnimations = new Map();
        const removers = [];
        const shell = home.querySelector(".site-shell");
        let wrapper;
        const originalShellId = shell.id;
        home.dataset.homeMotion = "active";
        let cleaned = false;
        const cleanup = () => {
          if (cleaned) return;
          cleaned = true;
          removers.forEach((remove) => remove());
          splits.forEach((split) => split.revert());
          smoother?.kill();
          smoother = undefined;
          if (wrapper) {
            wrapper.before(shell);
            wrapper.remove();
            shell.id = originalShellId;
            if (!originalShellId) shell.removeAttribute("id");
          }
          delete home.dataset.homeMotion;
        };
        try {
          if (context.conditions.desktop) {
            wrapper = document.createElement("div");
            wrapper.id = "home-scroll-wrapper";
            wrapper.className = "home-scroll-wrapper";
            shell.before(wrapper);
            wrapper.append(shell);
            shell.id = "home-scroll-content";
            smoother = ScrollSmoother.create({
              wrapper,
              content: shell,
              smooth: settings.smooth,
              smoothTouch: 0,
              effects: false,
              normalizeScroll: false,
              onFocusIn: (_instance, event) =>
                event.target.matches("main, section") ? false : undefined,
            });
          }
          home.querySelectorAll(textSelectors).forEach((element) => {
            if (!element.getClientRects().length) return;
            const split = SplitText.create(element, {
              type: "lines",
              tag: "span",
              linesClass: "home-motion-line",
              autoSplit: true,
              aria: "auto",
              onSplit(self) {
                if (completedText.has(element))
                  return gsap.set(self.lines, { opacity: 1, y: 0 });
                self.lines.forEach((line) => (line.dataset.motionReveal = ""));
                return gsap.from(self.lines, {
                  y: 28,
                  opacity: 0,
                  duration: settings.revealDuration,
                  stagger: settings.lineStagger,
                  ease: "power3.out",
                  scrollTrigger: {
                    trigger: element,
                    start: settings.revealStart,
                    once: true,
                  },
                  onComplete: () => completedText.add(element),
                });
              },
            });
            splits.push(split);
          });
          home.querySelectorAll(revealSelectors).forEach((element, index) => {
            if (
              !element.getClientRects().length ||
              completedReveals.has(element)
            )
              return;
            element.dataset.motionReveal = "";
            const card = element.matches(".restaurant-card,.about-card");
            const animation = gsap.from(element, {
              y: element.matches(".site-footer") ? 0 : card ? 32 : 20,
              opacity: 0,
              duration: settings.revealDuration,
              ease: "power3.out",
              delay: card ? (index % 3) * 0.07 : 0,
              scrollTrigger: {
                trigger: element,
                start: settings.revealStart,
                once: true,
              },
              clearProps: "opacity,transform",
              onComplete: () => completedReveals.add(element),
            });
            revealAnimations.set(element, animation);
          });
          // Animate decorative children, preserving every sticker's original rotation.
          home
            .querySelectorAll(
              ".nearby-sticker > span, .submit-banner__flag, .contact__badge",
            )
            .forEach((element, index) => {
              if (!element.getClientRects().length) return;
              const floating = gsap.to(element, {
                y: index % 2 ? 7 : -7,
                duration: 2.6 + index * 0.25,
                repeat: -1,
                yoyo: true,
                ease: "sine.inOut",
                paused: true,
              });
              ScrollTrigger.create({
                trigger: element.closest("section"),
                start: "top bottom",
                end: "bottom top",
                onToggle: (trigger) =>
                  trigger.isActive ? floating.play() : floating.pause(),
              });
            });
          // Art-only parallax stays inside the hero's existing crop.
          gsap.to(home.querySelector(".hero__background"), {
            y: -28,
            ease: "none",
            scrollTrigger: {
              trigger: ".hero",
              start: "top top",
              end: "bottom top",
              scrub: 0.8,
            },
          });
          const listen = (type, handler) => {
            home.addEventListener(type, handler);
            removers.push(() => home.removeEventListener(type, handler));
          };
          listen("lsh:scroll-to", (event) => {
            if (smoother) {
              event.preventDefault();
              smoother.scrollTo(event.detail.target, true, "top 100px");
            }
          });
          listen("lsh:results-updated", () => {
            // A newly filtered card must never retain its pre-reveal opacity.
            revealAnimations.forEach((animation, element) => {
              if (
                element.matches(".restaurant-card") &&
                !element.classList.contains("is-filtered-out")
              )
                animation.progress(1);
            });
            requestAnimationFrame(() => ScrollTrigger.refresh());
          });
          listen("click", (event) => {
            const link = event.target.closest('a[href^="#"]');
            if (
              !smoother ||
              !link ||
              event.defaultPrevented ||
              event.button > 0 ||
              event.metaKey ||
              event.ctrlKey ||
              event.shiftKey ||
              event.altKey
            )
              return;
            const target = document.getElementById(
              decodeURIComponent(link.hash.slice(1)),
            );
            if (!target) return;
            event.preventDefault();
            history.pushState(null, "", link.hash);
            smoother.scrollTo(target, true, "top 100px");
            if (!target.hasAttribute("tabindex"))
              target.setAttribute("tabindex", "-1");
            target.focus({ preventScroll: true });
          });
          // Keyboard focus exposes a pending reveal immediately.
          listen("focusin", (event) => {
            revealAnimations.forEach((animation, element) => {
              if (element.contains(event.target)) animation.progress(1);
            });
          });
          const revealForPrint = () => {
            revealAnimations.forEach((animation) => animation.progress(1));
            splits.forEach((split) => split.revert());
          };
          window.addEventListener("beforeprint", revealForPrint);
          removers.push(() =>
            window.removeEventListener("beforeprint", revealForPrint),
          );
          ScrollTrigger.refresh();
          if (location.hash && smoother) {
            const target = document.getElementById(
              decodeURIComponent(location.hash.slice(1)),
            );
            if (target) smoother.scrollTo(target, false, "top 100px");
          }
        } catch (error) {
          // Enhancement failure leaves the original static page usable.
          context.revert();
          cleanup();
          home.dataset.homeMotion = "fallback";
          console.warn("Homepage motion was skipped.", error);
        }
        return cleanup;
      },
    );
  }
  start();
})();
