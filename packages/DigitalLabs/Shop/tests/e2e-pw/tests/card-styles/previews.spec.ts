import { test, expect } from "../../setup";
import type { Browser, BrowserContextOptions, FrameLocator, Page } from "@playwright/test";
import { loginAsAdmin } from "../../utils/admin";
import {
    STORE_LOOKS,
    currentStoreLook,
    designOption,
    setCardStyle,
    setStoreLook,
    type StoreLookCode,
} from "../../utils/card-styles";

/**
 * Admin > Configuration > General > Design: the Product card and Store look pickers are live previews of
 * the real storefront (shop.card-preview), chosen like radios, playing a demo that never reaches the cart,
 * the compare list or the wishlist.
 */

test.describe.configure({ mode: "serial" });

let originalLook: StoreLookCode;

/*
 * One admin sign-in for the whole file (the admin login is rate limited), reused by every page.
 */
let adminState: BrowserContextOptions["storageState"];

const openPages: Page[] = [];

async function adminPage(browser: Browser, options: BrowserContextOptions = {}): Promise<Page> {
    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, storageState: adminState, ...options });

    const page = await context.newPage();

    openPages.push(page);

    return page;
}

test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL });
    const page = await context.newPage();

    originalLook = await currentStoreLook(page);

    await loginAsAdmin(page);

    adminState = await context.storageState();

    await context.close();
});

test.afterEach(async () => {
    for (const page of openPages.splice(0)) {
        await page.context().close();
    }
});

test.afterAll(async ({ browser }) => {
    const admin = await adminPage(browser);

    await setCardStyle(admin, "Classic");
    await setStoreLook(admin, originalLook);

    await admin.context().close();
});

/**
 * Every request for the shopper's own cart, compare list or wishlist that the page (or a preview in it) sends.
 */
function privateRequests(page: Page): string[] {
    const seen: string[] = [];

    page.on("request", (request) => {
        if (/\/api\/(checkout\/cart|compare-items)|wishlist/.test(request.url())) {
            seen.push(`${request.method()} ${request.url()}`);
        }
    });

    return seen;
}

async function openDesign(page: Page): Promise<void> {
    await page.goto("admin/configuration/general/design");
}

/**
 * A preview window by the option it shows. The window is inert (decorative), so it has no role: select it by title.
 */
async function preview(page: Page, name: string): Promise<FrameLocator> {
    const frame = page.locator(`iframe[title="Live preview: ${name}"]`);

    await frame.scrollIntoViewIfNeeded();

    return frame.contentFrame();
}

test("each picker is a group of radios with a live preview per option, and no dropdown", async ({ browser }) => {
    const page = await adminPage(browser);

    await openDesign(page);

    const cardStyle = page.getByRole("group", { name: "Card style" });
    const look = page.getByRole("group", { name: "Look" });

    await expect(cardStyle.getByRole("radio")).toHaveCount(3);
    await expect(look.getByRole("radio")).toHaveCount(4);
    await expect(page.getByRole("combobox", { name: "Card style" })).toHaveCount(0);
    await expect(page.getByRole("combobox", { name: "Look" })).toHaveCount(0);

    for (const name of ["Classic", "Swipe, named and sized", "Photo deck", ...Object.values(STORE_LOOKS)]) {
        // The windows are decorative and inert: they have no role, so select them by title.
        const previewWindow = page.locator(`iframe[title="Live preview: ${name}"]`);

        await expect(previewWindow).toHaveAttribute("tabindex", "-1");
        await expect(previewWindow).toHaveAttribute("sandbox", "allow-scripts allow-same-origin");
        await expect(previewWindow).toHaveAttribute("loading", "lazy");
    }

    await expect(cardStyle.getByRole("radio", { name: "Classic", exact: true })).toBeChecked();
});

test("clicking a preview chooses it, saving keeps it, and arrow keys move between the options", async ({ browser }) => {
    const page = await adminPage(browser);

    await openDesign(page);

    // A click anywhere on the option, here in the middle of its preview window, picks it. The window itself
    // takes no clicks (inert), so click the spot with the mouse rather than the element.
    const deckPreview = page.locator('iframe[title="Live preview: Photo deck"]');

    await deckPreview.scrollIntoViewIfNeeded();

    const box = (await deckPreview.boundingBox())!;

    await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);

    await expect(designOption(page, "Card style", "Photo deck")).toBeChecked();

    await Promise.all([
        page.waitForResponse((response) => response.request().method() === "POST" && response.status() === 302),
        page.getByRole("button", { name: "Save Configuration" }).click(),
    ]);

    await openDesign(page);

    await expect(designOption(page, "Card style", "Photo deck")).toBeChecked();

    // As native radios do: the arrow key moves on to the next option and picks it (Photo deck → Classic).
    await designOption(page, "Card style", "Photo deck").focus();
    await page.keyboard.press("ArrowRight");

    await expect(designOption(page, "Card style", "Classic")).toBeChecked();
    await expect(designOption(page, "Card style", "Classic")).toBeFocused();
});

test("the Swipe preview plays: the bars move, Add to cart becomes − 1 +, Compare ticks, and nothing reaches the cart or compare", async ({ browser }) => {
    const page = await adminPage(browser);

    const sent = privateRequests(page);

    await openDesign(page);

    const swipe = await preview(page, "Swipe, named and sized");
    // The demo plays on the first card; its progress bars and buy wrapper have no role.
    const card = swipe.locator(".pc-card").first();

    await expect(swipe.locator("html")).toHaveAttribute("data-demo", "playing");
    await expect(card.locator(".pc-tick").nth(1)).toHaveClass(/\bis-on\b/, { timeout: 8000 });
    await expect(card.locator('.pc-buy[data-state="qty"]')).toBeVisible({ timeout: 20000 });
    await expect(card.getByRole("button", { name: /^Compare / })).toHaveAttribute("aria-pressed", "true", { timeout: 20000 });

    expect(sent).toEqual([]);
});

test("the Photo deck preview throws the top photo to the back", async ({ browser }) => {
    const page = await adminPage(browser);

    await openDesign(page);

    const deck = await preview(page, "Photo deck");

    await expect(deck.getByText(/^2 \/ \d+$/).first()).toBeVisible({ timeout: 8000 });
});

test("the Classic preview stays still: Classic has no motion of its own", async ({ browser }) => {
    const page = await adminPage(browser);

    await openDesign(page);

    const classic = await preview(page, "Classic");

    await expect(classic.locator("html")).toHaveAttribute("data-demo", "still");
});

test("a store look preview shows the card style picked above it, before saving", async ({ browser }) => {
    const page = await adminPage(browser);

    await openDesign(page);

    await page.getByText("Swipe, named and sized", { exact: true }).click();

    const look = await preview(page, "Sand & Charcoal");

    // [data-card-style] names the card style the page renders.
    await expect(look.locator('[data-card-style="swipe"]').first()).toBeVisible();
    await expect(look.locator("html")).toHaveAttribute("data-look", "sand-charcoal");
});

test("the demo waits while its preview is off screen", async ({ browser }) => {
    const page = await adminPage(browser);

    await openDesign(page);

    const swipe = await preview(page, "Swipe, named and sized");

    await expect(swipe.locator("html")).toHaveAttribute("data-demo", "playing");

    await page.evaluate(() => window.scrollTo(0, 0));

    await expect(swipe.locator("html")).toHaveAttribute("data-demo", "paused");
});

test.describe("Reduced motion", () => {
    test("the previews show the cards still, with no loop", async ({ browser }) => {
        const page = await adminPage(browser, { reducedMotion: "reduce" });

        await openDesign(page);

        const swipe = await preview(page, "Swipe, named and sized");

        await expect(swipe.locator("html")).toHaveAttribute("data-demo", "still");

        await page.waitForTimeout(3000);

        // The first photo's bar is still the one lit.
        await expect(swipe.locator(".pc-card").first().locator(".pc-tick").first()).toHaveClass(/\bis-on\b/);
    });
});
