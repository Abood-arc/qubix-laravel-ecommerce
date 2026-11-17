import { devices, type Locator, type Page } from "@playwright/test";
import { test, expect } from "../../setup";
import {
    expectCompareLimitMessage,
    findMultiPhotoProduct,
    findSaleableProducts,
    openSearchFor,
    setCardStyle,
    setCustomerCompare,
    setGuestCompare,
    signInSeededCustomer,
    styledCard,
    transformAnimationCount,
    withAdmin,
    type CardProduct,
} from "../../utils/card-styles";

const { defaultBrowserType, ...pixel7 } = devices["Pixel 7"];

test.describe.configure({ mode: "serial" });

test.beforeAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Swipe, named and sized"));
});

test.afterAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
});

const tray = (page: Page) => page.getByRole("region", { name: "Compare tray" });

/**
 * Starts writing down every CSS transition and animation that starts inside the tray (property or
 * animation name), whatever its length, so a check made after the tray has opened still sees them.
 */
async function recordTrayMotion(page: Page): Promise<void> {
    await page.evaluate(() => {
        const seen: string[] = ((window as any).trayMotion = []);

        const inTray = (event: Event) => (event.target as Element | null)?.closest?.(".pc-tray");

        document.addEventListener("transitionrun", (event) => inTray(event) && seen.push((event as TransitionEvent).propertyName), true);
        document.addEventListener("animationstart", (event) => inTray(event) && seen.push((event as AnimationEvent).animationName), true);
    });
}

const trayMotion = (page: Page): Promise<string[]> => page.evaluate(() => (window as any).trayMotion);

/**
 * The focused element, if it is a new card's Compare control: whether it sits in a card, and its state.
 * (The control carries data-compare-product; checked in the page, as focus has no locator.)
 */
const focusedCompareControl = (page: Page) =>
    page.evaluate(() => {
        const control = document.activeElement?.closest("[data-compare-product]");

        return control ? { inCard: Boolean(control.closest("article")), pressed: control.getAttribute("aria-pressed") } : null;
    });

/**
 * No local product has related products, so the product page's related call answers with these
 * products from the listing API. Call before page.goto().
 */
async function answerRelatedWith(page: Page, products: CardProduct[]): Promise<void> {
    const { data } = await (await page.request.get("api/products?limit=48")).json();

    const items = data.filter((item: any) => products.some((product) => product.id === item.id));

    await page.route(/\/api\/products\/\d+\/related/, (route) => route.fulfill({ json: { data: items } }));
}

/**
 * The related products load once their row scrolls into view.
 */
async function showRelatedProducts(page: Page, product: CardProduct): Promise<void> {
    await page.keyboard.press("End");
    await expect(styledCard(page, product)).toBeVisible();
}

/**
 * The product page's own "Compare". Its accessible name starts with the icon font's glyph, so it is
 * matched as "Compare" after any non-letters (as in compare-limit.spec).
 */
const productPageCompare = (page: Page) => page.getByRole("button", { name: /^[^A-Za-z]*Compare$/ });

/**
 * How many points of a 5 × 3 grid over the element (clear of its rounded corners) have something else on top.
 */
const coveredPoints = (target: Locator): Promise<number> =>
    target.evaluate((element) => {
        const box = element.getBoundingClientRect();

        let covered = 0;

        for (let column = 0; column < 5; column++) {
            for (let row = 0; row < 3; row++) {
                // Inside the rounded corners: from 15% to 85% across, 25% to 75% down.
                const x = box.left + box.width * (0.15 + (0.7 * column) / 4);
                const y = box.top + box.height * (0.25 + (0.5 * row) / 2);

                covered += element.contains(document.elementFromPoint(x, y)) ? 0 : 1;
            }
        }

        return covered;
    });

test("ticking Compare on two cards fills the tray, which opens the compare page", async ({ page }) => {
    const [first, second] = await findSaleableProducts(page.request, 2);

    await openSearchFor(page, first);
    await setGuestCompare(page, []);
    await page.reload();

    await expect(tray(page)).toBeHidden();

    await styledCard(page, first).getByRole("button", { name: `Compare ${first.name}` }).click();

    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();
    await expect(tray(page).getByText("Pick 1 more")).toBeVisible();

    await openSearchFor(page, second);
    await styledCard(page, second).getByRole("button", { name: `Compare ${second.name}` }).click();

    await expect(tray(page).getByText("2 of 4 chosen")).toBeVisible();

    await tray(page).getByRole("link", { name: "Compare (2)" }).click();

    await expect(page).toHaveURL(/\/compare/);
    await expect(page.getByText(first.name, { exact: true }).first()).toBeVisible();
    await expect(page.getByText(second.name, { exact: true }).first()).toBeVisible();
    // The compare page keeps the Classic card, so no new-style card and no tray.
    await expect(page.locator("[data-card-style]")).toHaveCount(0);
    await expect(tray(page)).toHaveCount(0);
});

test("a pick made while the saved list is still loading is kept", async ({ page }) => {
    const [first, second] = await findSaleableProducts(page.request, 2);

    await openSearchFor(page, second);
    await setGuestCompare(page, [first.id]);

    // Hold back the page's one read of the compare list until after the click.
    let release = () => {};
    const held = new Promise<void>((resolve) => (release = resolve));

    await page.route(/\/api\/compare-items(\?|$)/, async (route) => {
        await held;
        await route.continue();
    });

    await page.reload();

    await styledCard(page, second).getByRole("button", { name: `Compare ${second.name}` }).click();

    release();

    await expect(tray(page).getByText("2 of 4 chosen")).toBeVisible();
    await expect(styledCard(page, second).getByRole("button", { name: `Compare ${second.name}` })).toHaveAttribute("aria-pressed", "true");
    expect(await page.evaluate(() => JSON.parse(localStorage.getItem("compare_items") ?? "[]"))).toEqual([first.id, second.id]);
});

test("a tray photo removes that pick, and Clear empties the tray", async ({ page }) => {
    const [first, second] = await findSaleableProducts(page.request, 2);

    await openSearchFor(page, first);
    await setGuestCompare(page, [first.id, second.id]);
    await page.reload();

    await expect(tray(page).getByText("2 of 4 chosen")).toBeVisible();

    await tray(page).getByRole("button", { name: `Remove ${first.name} from compare` }).click();

    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();
    await expect(styledCard(page, first).getByRole("button", { name: `Compare ${first.name}` })).toHaveAttribute("aria-pressed", "false");

    await tray(page).getByRole("button", { name: "Clear" }).click();

    await expect(tray(page)).toBeHidden();
    expect(await page.evaluate(() => localStorage.getItem("compare_items"))).toBeNull();
});

test("the tray works from the keyboard and says what its buttons did", async ({ page }) => {
    const [first, second] = await findSaleableProducts(page.request, 2);

    await openSearchFor(page, first);
    await setGuestCompare(page, [first.id, second.id]);
    await page.reload();

    const removeSecond = tray(page).getByRole("button", { name: `Remove ${second.name} from compare` });

    await removeSecond.focus();
    await page.keyboard.press("Enter");

    // The pressed photo has gone; focus stays in the tray, on the photo left in its place.
    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();
    await expect(tray(page).getByRole("button", { name: `Remove ${first.name} from compare` })).toBeFocused();
    await expect(page.getByRole("status").filter({ hasText: "Removed from compare" })).toContainText("1 of 4 chosen");

    // Not a dialog: Tab from its last control leaves the tray instead of going round inside it.
    await tray(page).getByRole("button", { name: "Clear" }).focus();
    await page.keyboard.press("Tab");
    expect(await page.evaluate(() => Boolean(document.activeElement?.closest('[aria-label="Compare tray"]')))).toBe(false);

    // Removing the last photo closes the tray: focus goes to that product's Compare control on its card.
    await tray(page).getByRole("button", { name: `Remove ${first.name} from compare` }).focus();
    await page.keyboard.press("Enter");

    await expect(tray(page)).toBeHidden();
    await expect(styledCard(page, first).getByRole("button", { name: `Compare ${first.name}` })).toBeFocused();
    await expect(page.getByRole("status").filter({ hasText: "Removed from compare" })).toHaveCount(1);

    await page.keyboard.press("Enter");
    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();

    await tray(page).getByRole("button", { name: "Clear" }).focus();
    await page.keyboard.press("Enter");

    // Clear closes the tray too: its live region sits outside the closed part and still reads out the
    // change, and focus goes to a card's Compare control (the first on screen).
    await expect(tray(page)).toBeHidden();
    await expect(page.getByRole("status").filter({ hasText: "Removed from compare" })).toHaveCount(1);
    expect(await focusedCompareControl(page)).toEqual({ inCard: true, pressed: "false" });
});

test("a double press on a tray photo removes one product, with one request", async ({ page }) => {
    // The suite's marked e2e customer (seeded by the global setup, removed by the teardown).
    await signInSeededCustomer(page);

    const [first, second] = await findSaleableProducts(page.request, 2);

    await setCustomerCompare(page, [first.id, second.id]);
    await openSearchFor(page, first);

    await expect(tray(page).getByText("2 of 4 chosen")).toBeVisible();

    let deletes = 0;

    page.on("request", (request) => {
        if (request.url().includes("/api/compare-items") && request.method() === "POST" && request.postDataJSON()?._method === "DELETE") {
            deletes++;
        }
    });

    await tray(page).getByRole("button", { name: `Remove ${first.name} from compare` }).dblclick();

    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();
    expect(deletes).toBe(1);

    await setCustomerCompare(page, []);
});

test("the tray follows the product page's own Add to compare", async ({ page }) => {
    const [main, related] = await findSaleableProducts(page.request, 2);

    await answerRelatedWith(page, [related]);
    await page.goto(main.urlKey);
    await setGuestCompare(page, [related.id]);
    await page.reload();

    await showRelatedProducts(page, related);
    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();

    await productPageCompare(page).click();

    await expect(tray(page).getByText("2 of 4 chosen")).toBeVisible();
});

test("the tray follows a signed-in customer's Add to compare on the product page", async ({ page }) => {
    // The suite's marked e2e customer (seeded by the global setup, removed by the teardown).
    await signInSeededCustomer(page);

    const [main, related] = await findSaleableProducts(page.request, 2);

    await setCustomerCompare(page, [related.id]);
    await answerRelatedWith(page, [related]);
    await page.goto(main.urlKey);

    await showRelatedProducts(page, related);
    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();

    await productPageCompare(page).click();

    await expect(tray(page).getByText("2 of 4 chosen")).toBeVisible();

    await setCustomerCompare(page, []);
});

test("the tray follows the compare list changed in another tab, and on Back", async ({ page }) => {
    const [first, second] = await findSaleableProducts(page.request, 2);

    await openSearchFor(page, first);
    await setGuestCompare(page, [first.id]);
    await page.reload();

    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();

    const otherTab = await page.context().newPage();

    await otherTab.goto("");
    await setGuestCompare(otherTab, [first.id, second.id]);

    await expect(tray(page).getByText("2 of 4 chosen")).toBeVisible();

    await otherTab.evaluate(() => localStorage.removeItem("compare_items"));

    await expect(tray(page)).toBeHidden();

    await otherTab.close();

    // A page the browser kept for Back shows again with pageshow (persisted): the list is read again.
    // Same-page storage writes fire no storage event, so only that pageshow can update the tray here.
    await setGuestCompare(page, [second.id]);
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent("pageshow", { persisted: true })));

    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();
});

test("saved picks the shop no longer shows leave the list, so the count and the limit agree", async ({ page }) => {
    const [first] = await findSaleableProducts(page.request, 1);

    await openSearchFor(page, first);
    await setGuestCompare(page, [first.id, 987654321]);
    await page.reload();

    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();
    await expect.poll(() => page.evaluate(() => JSON.parse(localStorage.getItem("compare_items") ?? "[]"))).toEqual([first.id]);
});

test("the tray holds four, and a fifth pick is refused", async ({ page }) => {
    const products = await findSaleableProducts(page.request, 5);

    await openSearchFor(page, products[4]);
    await setGuestCompare(page, products.slice(0, 4).map((product) => product.id));
    await page.reload();

    await expect(tray(page).getByText("4 of 4 chosen")).toBeVisible();

    await styledCard(page, products[4]).getByRole("button", { name: `Compare ${products[4].name}` }).click();

    await expectCompareLimitMessage(page);
    await expect(tray(page).getByText("4 of 4 chosen")).toBeVisible();
});

test("a signed-in customer's picks come from their account", async ({ page }) => {
    // The suite's marked e2e customer (seeded by the global setup, removed by the teardown).
    await signInSeededCustomer(page);
    await setCustomerCompare(page, []);

    const product = await findMultiPhotoProduct(page.request);

    await openSearchFor(page, product);
    await styledCard(page, product).getByRole("button", { name: `Compare ${product.name}` }).click();

    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();

    await page.reload();

    await expect(tray(page).getByText("1 of 4 chosen")).toBeVisible();

    await tray(page).getByRole("button", { name: "Clear" }).click();

    await expect(tray(page)).toBeHidden();

    await page.reload();

    await expect(styledCard(page, product).getByRole("button", { name: `Compare ${product.name}` })).toHaveAttribute("aria-pressed", "false");
    await expect(tray(page)).toBeHidden();
});

test("the open tray leaves room at the end of the page", async ({ page }) => {
    const product = await findMultiPhotoProduct(page.request);

    await openSearchFor(page, product);
    await setGuestCompare(page, [product.id]);
    await page.reload();

    await expect(tray(page)).toBeVisible();
    await expect.poll(() => transformAnimationCount(page, ".pc-tray")).toBe(0);

    const trayHeight = (await tray(page).boundingBox())!.height;

    await expect.poll(() => page.evaluate(() => parseFloat(getComputedStyle(document.body).paddingBottom))).toBeGreaterThanOrEqual(trayHeight);
});

test("the room at the end of the page follows the tray's height when the window is resized", async ({ page }) => {
    const product = await findMultiPhotoProduct(page.request);

    await openSearchFor(page, product);
    await setGuestCompare(page, [product.id]);
    await page.reload();

    await expect(tray(page)).toBeVisible();

    const reserved = () => page.evaluate(() => parseFloat(document.body.style.getPropertyValue("--pc-tray-space")));
    const trayHeight = async () => (await tray(page).boundingBox())!.height;

    await expect.poll(async () => (await reserved()) - (await trayHeight())).toBeCloseTo(16, 0);

    const wide = await reserved();

    await page.setViewportSize({ width: 390, height: 844 });

    await expect.poll(async () => (await reserved()) - (await trayHeight())).toBeCloseTo(16, 0);
    expect(await reserved()).not.toBe(wide);
});

test.describe("on a phone", () => {
    test.use(pixel7);

    test("the tray runs full width along the bottom, just above the Filter and Sort bar", async ({ page }) => {
        const [first, second] = await findSaleableProducts(page.request, 2);

        await openSearchFor(page, first);
        await setGuestCompare(page, [first.id, second.id]);
        await page.reload();

        await expect(tray(page)).toBeVisible();
        // Measured once the slide up has finished.
        await expect.poll(() => transformAnimationCount(page, ".pc-tray")).toBe(0);

        const box = (await tray(page).boundingBox())!;
        // The search page's fixed Filter/Sort bar has no landmark role.
        const bar = (await page.locator(".category-mobile-bar").boundingBox())!;

        expect(box.width).toBeGreaterThanOrEqual(page.viewportSize()!.width - 1);
        // Resting on the bar: its bottom edge is 3.25rem up, a few pixels above the bar's top.
        const gap = bar.y - (box.y + box.height);

        expect(gap).toBeGreaterThanOrEqual(0);
        expect(gap).toBeLessThanOrEqual(4);

        // Nothing floats over any part of the tray's Compare button (the chat button sits in that corner).
        expect(await coveredPoints(tray(page).getByRole("link", { name: "Compare (2)" }))).toBe(0);
    });
});

test.describe("with reduced motion", () => {
    test.use({ reducedMotion: "reduce" });

    test("the tray fades in without sliding", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await openSearchFor(page, product);
        await setGuestCompare(page, []);
        await page.reload();
        await recordTrayMotion(page);

        await styledCard(page, product).getByRole("button", { name: `Compare ${product.name}` }).click();

        // Measured once the tray has opened, while a slide would still be running.
        await expect(tray(page)).toBeVisible();
        expect(await transformAnimationCount(page, ".pc-tray")).toBe(0);

        // The recorder heard the fade, so it was listening; nothing that moves started.
        await expect.poll(() => trayMotion(page)).toContain("opacity");
        expect(await trayMotion(page)).not.toContain("transform");
        expect(await trayMotion(page)).not.toContain("pc-slot-in");
    });
});
