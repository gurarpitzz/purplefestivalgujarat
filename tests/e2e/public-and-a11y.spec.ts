import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";

async function assertNoCriticalOrSeriousA11yViolations(
  page: import("@playwright/test").Page,
  routeLabel: string
) {
  const results = await new AxeBuilder({ page })
    .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
    .analyze();

  const blocking = results.violations.filter(
    (v) => v.impact === "critical" || v.impact === "serious"
  );

  if (blocking.length > 0) {
    const summary = blocking
      .map(
        (v) =>
          `[${v.impact?.toUpperCase()}] ${v.id}: ${v.help} (${v.nodes.length} node(s): ${v.nodes
            .slice(0, 2)
            .map((n) => n.target.join(" "))
            .join(", ")})`
      )
      .join("\n");
    throw new Error(`WCAG 2.2 AA violations on ${routeLabel}:\n${summary}`);
  }
}

test.describe("Purple Festival Gujarat 2027 — Public E2E & WCAG 2.2 AA Audits", () => {
  test("Home page preloader, Skip Intro, hero branding, CTAs, and zero a11y violations", async ({
    page,
  }) => {
    const response = await page.goto("/", { waitUntil: "domcontentloaded" });
    expect(response?.status()).toBe(200);

    // Preloader renders PURPLE FESTIVAL & GUJARAT 2027 with visible Skip Intro button
    const preloader = page.getByRole("region", {
      name: /Purple Festival Gujarat 2027 Introductory Animation/i,
    });
    await expect(preloader).toBeVisible({ timeout: 5000 });
    await expect(preloader.locator('[data-draw-text="title"]')).toHaveText("PURPLE FESTIVAL");
    await expect(preloader.locator('[data-draw-text="subtitle"]')).toHaveText("GUJARAT 2027");

    const skipIntroBtn = page.getByRole("button", { name: /Skip Intro/i });
    await expect(skipIntroBtn).toBeVisible();
    await skipIntroBtn.click();

    // Preloader dismisses and Hero is visible
    await expect(preloader).toBeHidden({ timeout: 5000 });

    const mainContent = page.locator("main");
    await expect(mainContent).toBeVisible();
    await expect(page.getByRole("heading", { level: 1 })).toContainText(
      /PURPLE FESTIVAL GUJARAT 2027/i
    );

    // Verify zero leftover Battalion / real-estate copy and zero Book Stall CTA
    const bodyText = await page.locator("body").innerText();
    expect(bodyText).not.toMatch(/\bBattalion\b/i);
    expect(bodyText).not.toMatch(/real[- ]?estate|luxury apartments|sq\.?\s*ft/i);
    expect(bodyText).not.toMatch(/Book Stall/i);

    // Verify header uses Login + Apply Now
    const header = page.locator("header");
    await expect(header.getByRole("link", { name: /Login/i })).toBeVisible();
    await expect(header.getByRole("link", { name: /Apply Now/i })).toBeVisible();

    await assertNoCriticalOrSeriousA11yViolations(page, "/");
  });

  for (const route of [
    { path: "/programme", heading: /Programme/i },
    { path: "/accessibility", heading: /Accessibility/i },
    { path: "/apply", heading: /Apply|Register/i },
    { path: "/login", heading: /Sign In|Login|Portal/i },
  ]) {
    test(`${route.path} returns HTTP 200 and passes WCAG 2.2 AA axe-core audit`, async ({
      page,
    }) => {
      // Bypass preloader session if navigating directly
      await page.addInitScript(() => {
        window.sessionStorage.setItem("pfg27_intro_seen", "1");
      });

      const response = await page.goto(route.path, { waitUntil: "networkidle" });
      expect(response?.status()).toBe(200);
      await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
      await expect(page.getByRole("heading", { level: 1 })).toContainText(route.heading);

      await assertNoCriticalOrSeriousA11yViolations(page, route.path);
    });
  }

  test("Keyboard navigation & Accessibility Preferences Panel open/close via Escape", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      window.sessionStorage.setItem("pfg27_intro_seen", "1");
    });

    await page.goto("/", { waitUntil: "networkidle" });

    // Tab into the page and verify keyboard focus lands on an interactive element
    await page.keyboard.press("Tab");
    const activeTag = await page.evaluate(() => document.activeElement?.tagName ?? "");
    expect(["A", "BUTTON", "INPUT"]).toContain(activeTag);

    // Open Accessibility Preferences Panel via button
    const a11yTrigger = page.locator('[data-a11y-trigger="true"]');
    await expect(a11yTrigger).toBeVisible();
    await a11yTrigger.click();

    const dialog = page.getByRole("dialog", { name: /Accessibility Preferences/i });
    await expect(dialog).toBeVisible();
    await expect(a11yTrigger).toHaveAttribute("aria-expanded", "true");

    // Verify Escape closes the dialog and restores focus
    await page.keyboard.press("Escape");
    await expect(dialog).toBeHidden();
    await expect(a11yTrigger).toHaveAttribute("aria-expanded", "false");
  });
});
