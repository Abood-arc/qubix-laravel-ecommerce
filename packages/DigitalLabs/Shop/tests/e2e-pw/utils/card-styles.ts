import { expect, test, type APIRequestContext, type Browser, type Locator, type Page } from "@playwright/test";
import { loginAsAdmin } from "./admin";

/**
 * Helpers for the product card style specs (tests/card-styles/).
 * Settings are changed through real admin saves, so the page cache is cleared the way it is in production.
 */

export type CardStyle = "Classic" | "Swipe, named and sized" | "Photo deck";

export const STORE_LOOKS = {
    "forest-green": "Forest Green",
    "marigold-indigo": "Marigold & Indigo",
    "sand-charcoal": "Sand & Charcoal",
    "minimal-white": "Minimal White",
} as const;

export type StoreLookCode = keyof typeof STORE_LOOKS;

export interface CardProduct {
    id: number;
    name: string;
    urlKey: string;
    photoCount: number;
}

/**
 * Runs `action` with a signed-in admin page. For beforeAll/afterAll hooks, where the adminPage fixture is not available.
 */
export async function withAdmin(browser: Browser, action: (admin: Page) => Promise<void>): Promise<void> {
    const admin = await browser.newPage({ baseURL: test.info().project.use.baseURL });

    try {
        await loginAsAdmin(admin);
        await action(admin);
    } finally {
        await admin.close();
    }
}

async function saveDesign(admin: Page, choose: () => Promise<void>, kept: () => Promise<void>): Promise<void> {
    await admin.goto("admin/configuration/general/design");
    await choose();

    // The save answers with a redirect back to the page. (Its flash message can be gone before the page,
    // which now loads the live previews, has finished loading, so the answer itself is what is checked.)
    const [saved] = await Promise.all([
        admin.waitForResponse((response) => response.request().method() === "POST" && response.url().includes("admin/configuration")),
        admin.getByRole("button", { name: "Save Configuration" }).click(),
    ]);

    expect(saved.status()).toBe(302);
    expect(saved.headers().location ?? "").not.toContain("login");

    await admin.waitForLoadState("domcontentloaded");

    await kept();
}

/**
 * Picks one of the design pickers' options (Card style, Look): a radio named after the option.
 */
export const designOption = (admin: Page, picker: "Card style" | "Look", option: string) =>
    admin.getByRole("group", { name: picker }).getByRole("radio", { name: option, exact: true });

export const setCardStyle = (admin: Page, style: CardStyle) =>
    saveDesign(
        admin,
        () => designOption(admin, "Card style", style).check(),
        () => expect(designOption(admin, "Card style", style)).toBeChecked(),
    );

export const setSizeUnit = (admin: Page, unit: "Inches" | "Centimetres") =>
    saveDesign(
        admin,
        async () => {
            await admin.getByLabel("Size unit").selectOption({ label: unit });
        },
        () => expect(admin.getByLabel("Size unit")).toHaveValue(unit === "Inches" ? "in" : "cm"),
    );

export const setStoreLook = (admin: Page, look: StoreLookCode) =>
    saveDesign(
        admin,
        () => designOption(admin, "Look", STORE_LOOKS[look]).check(),
        () => expect(designOption(admin, "Look", STORE_LOOKS[look])).toBeChecked(),
    );

/**
 * The store look the storefront shows now (data-look on <html>), to restore it after a spec.
 */
export async function currentStoreLook(page: Page): Promise<StoreLookCode> {
    await page.goto("");

    return (await page.locator("html").getAttribute("data-look")) as StoreLookCode;
}

/**
 * A saleable simple product (it has a final price) with at least two photos, from the listing API.
 * Passing a locale switches the session's locale too.
 */
export async function findMultiPhotoProduct(request: APIRequestContext, locale = "en"): Promise<CardProduct> {
    const response = await request.get(`api/products?limit=48&locale=${locale}`);

    expect(response.ok()).toBeTruthy();

    const { data } = await response.json();

    const product = data.find((item: any) => item.images.length >= 2 && item.is_saleable && item.prices?.final);

    expect(product, `No saleable simple product with 2+ photos in "${locale}"; add photos to one in the admin`).toBeTruthy();

    return { id: product.id, name: product.name, urlKey: product.url_key, photoCount: product.images.length };
}

/**
 * `count` saleable simple products (photos not required), for specs that need several cards.
 */
export async function findSaleableProducts(request: APIRequestContext, count: number, locale = "en"): Promise<CardProduct[]> {
    const response = await request.get(`api/products?limit=48&locale=${locale}`);

    expect(response.ok()).toBeTruthy();

    const { data } = await response.json();

    const products = data.filter((item: any) => item.is_saleable && item.prices?.final).slice(0, count);

    expect(products.length, `Need ${count} saleable simple products in "${locale}"`).toBe(count);

    return products.map((item: any) => ({ id: item.id, name: item.name, urlKey: item.url_key, photoCount: item.images.length }));
}

export async function openSearchFor(page: Page, product: CardProduct, extraQuery = ""): Promise<void> {
    await page.goto(`search?query=${encodeURIComponent(product.name)}&suggest=0${extraQuery}`);
}

/**
 * A Swipe or Photo deck card (an <article>) by its product name.
 */
export const styledCard = (page: Page, product: CardProduct) =>
    page.getByRole("article").filter({ hasText: product.name }).first();

/**
 * The card's photo link: the first link named after the product (the name link comes after it).
 */
export const photoLink = (page: Page, product: CardProduct) =>
    styledCard(page, product).getByRole("link", { name: product.name, exact: true }).first();

/**
 * Overrides fields of one product in the listing API's answers, to show facts the catalogue does not have yet.
 * Call before page.goto().
 */
export async function withCardData(page: Page, productId: number, patch: Record<string, unknown>): Promise<void> {
    await page.route(/\/api\/products(\?|$)/, async (route) => {
        const response = await route.fetch();

        const body = await response.json();

        body.data = body.data.map((item: any) => (item.id === productId ? { ...item, ...patch } : item));

        await route.fulfill({ response, json: body });
    });
}

/**
 * Running animations or transitions that move something (keyframes with a transform).
 */
export function transformAnimationCount(page: Page, within = "body"): Promise<number> {
    return page.evaluate((selector) =>
        document
            .getAnimations()
            .filter((animation) => {
                const effect = animation.effect as KeyframeEffect | null;

                return (effect?.target as Element | null)?.closest(selector)
                    && effect?.getKeyframes().some((frame) => "transform" in frame);
            })
            .length,
    within);
}

/**
 * The store look's brand colour as computed CSS ("rgb(47, 93, 72)").
 */
export const brandColour = (page: Page): Promise<string> => lookColour(page, "--c-brand");

/**
 * The throwaway customer that the suite's global setup seeds (CardStylesE2eSeeder) and its teardown removes.
 */
export const SEEDED_CUSTOMER = {
    email: "e2e-cards-customer@example.test",
    password: process.env.E2E_CUSTOMER_PASSWORD ?? "e2e-customer-password",
};

export async function signInSeededCustomer(page: Page): Promise<void> {
    await page.goto("customer/login");
    await page.getByPlaceholder("email@example.com").fill(SEEDED_CUSTOMER.email);
    await page.getByPlaceholder("Password").fill(SEEDED_CUSTOMER.password);

    const agreement = page.getByText("I agree with this statement.");

    if (await agreement.isVisible()) {
        await agreement.click();
    }

    await page.getByRole("button", { name: "Sign In" }).click();

    await expect(page).not.toHaveURL(/customer\/login/);
}

/**
 * A guest's compare list (localStorage, as the shop keeps it). Reload the page afterwards.
 */
export const setGuestCompare = (page: Page, ids: number[]) =>
    page.evaluate((list) => localStorage.setItem("compare_items", JSON.stringify(list)), ids);

/**
 * A signed-in customer's compare list, set through the compare API with the page's session.
 */
export async function setCustomerCompare(page: Page, ids: number[]): Promise<void> {
    const token = (await page.context().cookies()).find((cookie) => cookie.name === "XSRF-TOKEN")?.value ?? "";

    const headers = { Accept: "application/json", "X-XSRF-TOKEN": decodeURIComponent(token) };

    await page.request.post("api/compare-items/all", { headers, data: { _method: "DELETE" } });

    for (const id of ids) {
        const response = await page.request.post("api/compare-items", { headers, data: { product_id: id } });

        expect(response.ok(), `compare add for product ${id}`).toBeTruthy();
    }
}

/**
 * The fifth compare add, wherever it happens: the limit message and its link to the compare page.
 */
export async function expectCompareLimitMessage(page: Page): Promise<void> {
    await expect(page.getByText("You can compare up to 4").first()).toBeVisible();
    await expect(page.getByRole("link", { name: "Open compare" }).first()).toBeVisible();
}

/**
 * The card's Add to cart button: the full button on the Swipe card, the round bag button on the Photo deck card.
 */
export const addToCartButton = (page: Page, product: CardProduct, style: CardStyle) =>
    styledCard(page, product).getByRole("button", {
        name: style === "Photo deck" ? `Add ${product.name} to cart` : "Add To Cart",
    });

/**
 * How many of the product the real cart holds (the page's session, through the cart API).
 */
export async function cartQuantityOf(page: Page, product: CardProduct): Promise<number> {
    const { data } = await (await page.request.get("api/checkout/cart")).json();

    return Number(data?.items?.find((item: any) => item.product_id === product.id)?.quantity ?? 0);
}

/**
 * One of the store look's colour tokens (`--c-ink`, `--c-line`, …) as computed CSS ("rgb(29, 34, 31)").
 */
export async function lookColour(page: Page, token: string): Promise<string> {
    const channels = await page.evaluate((name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim(), token);

    expect(channels, `The store look has no ${token}`).not.toBe("");

    return `rgb(${channels.split(/\s+/).join(", ")})`;
}

/**
 * The WCAG contrast ratio of two computed CSS colours ("rgb(138, 142, 136)"), from 1 to 21.
 */
export function contrastRatio(first: string, second: string): number {
    const luminance = (colour: string) => {
        const [red, green, blue] = (colour.match(/\d+(\.\d+)?/g) ?? []).slice(0, 3).map((value) => {
            const channel = Number(value) / 255;

            return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
        });

        return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
    };

    const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a);

    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Waits until every card photo on screen that has been asked to load (it has a src) has loaded, so a
 * screenshot never catches a blank photo tile. The photo wrapper (.pc-photo, no role) carries is-loaded
 * once its image is in.
 */
export async function cardPhotosOnScreenLoaded(page: Page): Promise<void> {
    await expect
        .poll(() =>
            page.evaluate(() =>
                [...document.querySelectorAll(".pc-photo:has(> img[src])")]
                    .filter((photo) => {
                        const box = photo.getBoundingClientRect();

                        return box.width > 0 && box.bottom > 0 && box.top < innerHeight && box.right > 0 && box.left < innerWidth;
                    })
                    .every((photo) => photo.classList.contains("is-loaded"))
            )
        )
        .toBe(true);
}

/**
 * A real finger swipe across `target` (a touch screen page: hasTouch), sent through Chrome's input
 * pipeline as touch events, so the browser scrolls natively the way a phone does. `direction` is the
 * way the finger moves: "left" drags the content towards the start in English (next photo).
 */
export async function fingerSwipe(page: Page, target: Locator, direction: "left" | "right"): Promise<void> {
    const box = (await target.boundingBox())!;
    const y = box.y + box.height / 2;
    const [from, to] = direction === "left"
        ? [box.x + box.width * 0.85, box.x + box.width * 0.1]
        : [box.x + box.width * 0.15, box.x + box.width * 0.9];
    const steps = 12;

    const cdp = await page.context().newCDPSession(page);

    try {
        await cdp.send("Input.dispatchTouchEvent", { type: "touchStart", touchPoints: [{ x: from, y }] });

        for (let step = 1; step <= steps; step++) {
            await cdp.send("Input.dispatchTouchEvent", {
                type: "touchMove",
                touchPoints: [{ x: from + ((to - from) * step) / steps, y }],
            });
        }

        await cdp.send("Input.dispatchTouchEvent", { type: "touchEnd", touchPoints: [] });
    } finally {
        await cdp.detach();
    }
}
