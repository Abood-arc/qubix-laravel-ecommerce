/**
 * DevTools Performance & Stability Audit
 * ----------------------------------------------------------------------
 * Combines functional E2E coverage with a direct Chrome DevTools Protocol
 * (CDP) session so that regressions which don't break a locator — a slow
 * render, a leaking Vue watcher, a 500 from a Laravel controller that the
 * storefront silently swallows — still fail the build.
 *
 * Two journeys are covered:
 *   1. Storefront: search -> add to cart -> open the mini-cart drawer.
 *   2. Admin:      login -> create a category -> create a simple product.
 *
 * Run (from packages/DigitalLabs/Shop):
 *   APP_URL=http://localhost:8080 npx playwright test \
 *     tests/performance/devtools-performance-audit.spec.ts \
 *     --config=tests/e2e-pw/playwright.config.ts
 *
 * A JSON metrics log is written to
 *   packages/DigitalLabs/Shop/tests/e2e-pw/test-results/performance/performance-metrics.json
 * and every snapshot is also attached to the HTML report via testInfo.attach().
 */

import { test, expect } from "../../setup";
import type { CDPSession, ConsoleMessage, Page, Response, TestInfo } from "@playwright/test";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/* ------------------------------------------------------------------ *
 * Performance budgets.
 * Tune these to your infrastructure — they are intentionally strict
 * defaults for a local/staging box, not a production CDN-fronted site.
 * ------------------------------------------------------------------ */
const THRESHOLDS = {
    pageLoadMs: 2000,
    domContentLoadedMs: 1500,
    /** Acceptable JS heap growth (MB) across an entire journey. */
    jsHeapGrowthMb: 25,
    /** Acceptable main-thread busy time (ms) for a single SPA transition (e.g. opening the cart drawer). */
    transitionTaskDurationMs: 500,
};

type MetricSnapshot = {
    step: string;
    timestamp: string;
    /** Browser Navigation Timing API — only populated right after a full page load. */
    navigationTiming: {
        domContentLoadedMs: number;
        loadEventMs: number;
        ttfbMs: number;
    } | null;
    /** Chrome DevTools Protocol Performance.getMetrics() snapshot. */
    cdp: {
        jsHeapUsedMb: number;
        jsHeapTotalMb: number;
        /** Cumulative main-thread task time (ms) since the CDP session was enabled. */
        taskDurationMs: number;
        scriptDurationMs: number;
        layoutDurationMs: number;
        domNodes: number;
    };
};

/** Accumulates every journey's snapshots so a single JSON log covers the whole run. */
const runMetricsLog: { journey: string; snapshots: MetricSnapshot[] }[] = [];

function persistMetricsLog() {
    const outDir = path.resolve(__dirname, "../../test-results/performance");
    fs.mkdirSync(outDir, { recursive: true });
    fs.writeFileSync(
        path.join(outDir, "performance-metrics.json"),
        JSON.stringify(runMetricsLog, null, 2),
    );
}

/**
 * DevToolsMonitor
 * ----------------------------------------------------------------------
 * Wraps a raw CDP session (Performance + Network domains) together with
 * Playwright's native console/pageerror events.
 *
 * Why the mix instead of pure CDP end-to-end? `Performance.getMetrics`
 * (heap size, cumulative task/script/layout duration) has no Playwright-level
 * equivalent, so it's read straight off the CDP session. Console errors and
 * uncaught exceptions, on the other hand, are exposed by Playwright's
 * `console` / `pageerror` events, which are themselves backed by the CDP
 * Runtime/Log domains but normalised across navigations — using the raw
 * `Runtime.exceptionThrown` event directly would mean re-deriving that
 * normalisation for no functional benefit. Network 5xx detection uses the
 * raw `Network.responseReceived` CDP event directly, per the brief.
 */
class DevToolsMonitor {
    private readonly page: Page;
    private cdp!: CDPSession;
    private readonly snapshots: MetricSnapshot[] = [];

    readonly consoleErrors: string[] = [];
    readonly pageExceptions: string[] = [];
    readonly criticalApiErrors: { url: string; status: number }[] = [];

    constructor(page: Page) {
        this.page = page;
    }

    /** Opens the CDP session and wires up every listener. Call before the first navigation. */
    async attach() {
        this.cdp = await this.page.context().newCDPSession(this.page);

        await this.cdp.send("Performance.enable");
        await this.cdp.send("Network.enable");

        // Raw CDP network tracing: flag any 5xx response (Laravel controller
        // exceptions, failed AJAX cart/checkout calls, etc.) the instant it
        // is received on the wire, before Vue has a chance to swallow it.
        this.cdp.on("Network.responseReceived", (event: any) => {
            const status: number = event?.response?.status ?? 0;
            const url: string = event?.response?.url ?? "unknown";

            if (status >= 500) {
                this.criticalApiErrors.push({ url, status });
            }
        });

        // Uncaught exceptions inside the page — this is where an unhandled
        // Vue render/watcher/lifecycle error surfaces.
        this.page.on("pageerror", (error: Error) => {
            this.pageExceptions.push(error.message);
        });

        // console.error(...) calls (including Vue's own dev-mode warnings
        // that indicate a broken render).
        this.page.on("console", (msg: ConsoleMessage) => {
            if (msg.type() === "error") {
                this.consoleErrors.push(msg.text());
            }
        });
    }

    /**
     * Fails the test as soon as it's called if any Vue exception, console
     * error, or 5xx API response has been observed since `attach()`. Call
     * this after every meaningful interaction so a regression is caught at
     * the step where it happened rather than at the end of the whole test.
     */
    assertNoCriticalIssues(step: string) {
        expect(this.pageExceptions, `Unhandled Vue.js exception detected after "${step}"`).toEqual([]);
        expect(this.criticalApiErrors, `Critical 5xx API error detected after "${step}"`).toEqual([]);
        expect(this.consoleErrors, `Console error detected after "${step}"`).toEqual([]);
    }

    /** Captures CDP performance metrics + (if applicable) Navigation Timing at a checkpoint. */
    async snapshot(step: string, testInfo?: TestInfo): Promise<MetricSnapshot> {
        const { metrics } = await this.cdp.send("Performance.getMetrics");
        const byName = Object.fromEntries(metrics.map((m) => [m.name, m.value]));

        const navigationTiming = await this.page.evaluate(() => {
            const [nav] = performance.getEntriesByType("navigation") as PerformanceNavigationTiming[];
            if (!nav) return null;

            return {
                domContentLoadedMs: nav.domContentLoadedEventEnd - nav.startTime,
                loadEventMs: nav.loadEventEnd - nav.startTime,
                ttfbMs: nav.responseStart - nav.requestStart,
            };
        });

        const snap: MetricSnapshot = {
            step,
            timestamp: new Date().toISOString(),
            navigationTiming,
            cdp: {
                jsHeapUsedMb: +(byName["JSHeapUsedSize"] / 1_048_576).toFixed(2),
                jsHeapTotalMb: +(byName["JSHeapTotalSize"] / 1_048_576).toFixed(2),
                taskDurationMs: +(byName["TaskDuration"] * 1000).toFixed(2),
                scriptDurationMs: +(byName["ScriptDuration"] * 1000).toFixed(2),
                layoutDurationMs: +(byName["LayoutDuration"] * 1000).toFixed(2),
                domNodes: byName["Nodes"] ?? 0,
            },
        };

        this.snapshots.push(snap);
        console.log(`[perf] ${step}:`, JSON.stringify(snap));

        if (testInfo) {
            await testInfo.attach(`perf-${step}`, {
                body: JSON.stringify(snap, null, 2),
                contentType: "application/json",
            });
        }

        return snap;
    }

    getSnapshots() {
        return this.snapshots;
    }

    async detach() {
        await this.cdp?.detach().catch(() => {});
    }
}

/* ==================================================================== *
 * JOURNEY 1 — Storefront: search -> add to cart -> open cart drawer
 * ==================================================================== */
test.describe("Storefront — search -> cart performance & functional audit", () => {
    test("search for a product, add it to cart, and measure the cart-drawer transition", async ({
        page,
        browserName,
    }, testInfo) => {
        test.skip(browserName !== "chromium", "CDP performance audit requires Chromium.");

        const monitor = new DevToolsMonitor(page);
        await monitor.attach();

        /* Step 1 — Homepage load. */
        await page.goto("");
        const homepageSnapshot = await monitor.snapshot("homepage_load", testInfo);
        monitor.assertNoCriticalIssues("homepage_load");

        expect(
            homepageSnapshot.navigationTiming?.domContentLoadedMs,
            "DOMContentLoaded should be fast on the homepage",
        ).toBeLessThan(THRESHOLDS.domContentLoadedMs);
        expect(
            homepageSnapshot.navigationTiming?.loadEventMs,
            "Full homepage load should be under budget",
        ).toBeLessThan(THRESHOLDS.pageLoadMs);

        /* Step 2 — Search for a product. Resilient, label-based locator so
         * it survives Vue re-rendering the search bar's markup. */
        const searchBox = page.getByLabel("Search products here");
        await searchBox.click();
        await searchBox.fill("arct");
        await searchBox.press("Enter");

        await expect(page.getByText("These are results for : arct").first()).toBeVisible();
        await monitor.snapshot("search_results", testInfo);
        monitor.assertNoCriticalIssues("search_results");

        /* Step 3 — Add the first result to the cart. */
        await page.getByRole("button", { name: "Add To Cart" }).first().click();
        await expect(page.getByText("Item Added Successfully").first()).toBeVisible();
        const addToCartSnapshot = await monitor.snapshot("add_to_cart", testInfo);
        monitor.assertNoCriticalIssues("add_to_cart");

        /* Step 4 — Open the mini-cart drawer: the specific transition we
         * want to measure for performance degradation. */
        await page.getByRole("button", { name: "Shopping Cart" }).click();
        await expect(page.getByRole("button", { name: "Remove" }).first()).toBeVisible();
        const cartOpenSnapshot = await monitor.snapshot("cart_drawer_open", testInfo);
        monitor.assertNoCriticalIssues("cart_drawer_open");

        /* Step 5 — Dual-purpose assertions: functional success is already
         * proven by the toasts/locators above; here we bound the
         * performance cost of the transition itself. */
        const heapGrowthMb = cartOpenSnapshot.cdp.jsHeapUsedMb - homepageSnapshot.cdp.jsHeapUsedMb;
        expect(
            heapGrowthMb,
            `Heap grew ${heapGrowthMb.toFixed(2)}MB across the search->cart journey`,
        ).toBeLessThan(THRESHOLDS.jsHeapGrowthMb);

        const cartTransitionTaskMs = cartOpenSnapshot.cdp.taskDurationMs - addToCartSnapshot.cdp.taskDurationMs;
        expect(
            cartTransitionTaskMs,
            `Opening the cart drawer cost ${cartTransitionTaskMs.toFixed(2)}ms of main-thread work`,
        ).toBeLessThan(THRESHOLDS.transitionTaskDurationMs);

        runMetricsLog.push({ journey: "storefront_search_to_cart", snapshots: monitor.getSnapshots() });
        persistMetricsLog();
        await monitor.detach();
    });
});

/* ==================================================================== *
 * JOURNEY 2 — Admin: login -> create category -> create simple product
 * ==================================================================== */
test.describe("Admin — login -> category -> product performance & functional audit", () => {
    test("login, create a category, and create a simple product", async ({ page, browserName }, testInfo) => {
        test.skip(browserName !== "chromium", "CDP performance audit requires Chromium.");

        // Deliberately use the plain `page` fixture (not the pre-authenticated
        // `adminPage` fixture) so the CDP session is attached *before* login,
        // letting us measure the login page load and the auth redirect too.
        const monitor = new DevToolsMonitor(page);
        await monitor.attach();

        /* Step 1 — Admin login page load. */
        await page.goto("admin/login");
        const loginPageSnapshot = await monitor.snapshot("admin_login_page_load", testInfo);
        monitor.assertNoCriticalIssues("admin_login_page_load");

        expect(
            loginPageSnapshot.navigationTiming?.loadEventMs,
            "Admin login page should load fast",
        ).toBeLessThan(THRESHOLDS.pageLoadMs);

        /* Step 2 — Authenticate and land on the dashboard. */
        await page.fill('input[name="email"]', "admin@example.com");
        await page.fill('input[name="password"]', "admin123");
        await page.press('input[name="password"]', "Enter");
        await page.waitForURL("**/admin/dashboard");
        await expect(page.getByPlaceholder("Mega Search").first()).toBeVisible();

        await monitor.snapshot("admin_dashboard_after_login", testInfo);
        monitor.assertNoCriticalIssues("admin_dashboard_after_login");

        /* Step 3 — Create a category. */
        const categoryName = `Perf Audit Category ${Date.now()}`;

        await page.goto("admin/catalog/categories");
        await page.waitForSelector("div.primary-button", { state: "visible" });
        await page.click("div.primary-button:visible");
        await page.waitForSelector('form[action*="/catalog/categories/create"]');

        await page.fill('input[name="name"]', categoryName);
        await page.click('label:has-text("Root")');
        await page.click('button:has-text("Save Category")');

        await expect(
            page.locator("#app p", { hasText: "Category created successfully." }),
        ).toBeVisible();
        await monitor.snapshot("admin_category_created", testInfo);
        monitor.assertNoCriticalIssues("admin_category_created");

        /* Step 4 — Create a simple product (initial creation modal; the
         * follow-up product edit page is a separate, much heavier form and
         * is intentionally out of scope for this transition-focused audit). */
        await page.goto("admin/catalog/products");
        await page.getByRole("button", { name: "Create Product" }).click();
        await page.locator('select[name="type"]').selectOption("simple");
        await page.locator('select[name="attribute_family_id"]').selectOption("1");
        await page.locator('input[name="sku"]').fill(`PERF-${Date.now()}`);
        await page.getByRole("button", { name: "Save Product" }).click();

        await page.waitForSelector('button.primary-button:has-text("Save Product")');
        const productCreatedSnapshot = await monitor.snapshot("admin_product_created", testInfo);
        monitor.assertNoCriticalIssues("admin_product_created");

        /* Step 5 — Performance assertions across the whole admin journey. */
        const heapGrowthMb = productCreatedSnapshot.cdp.jsHeapUsedMb - loginPageSnapshot.cdp.jsHeapUsedMb;
        expect(
            heapGrowthMb,
            `Admin heap grew ${heapGrowthMb.toFixed(2)}MB across login->category->product`,
        ).toBeLessThan(THRESHOLDS.jsHeapGrowthMb * 2);

        runMetricsLog.push({ journey: "admin_login_category_product", snapshots: monitor.getSnapshots() });
        persistMetricsLog();
        await monitor.detach();
    });
});
