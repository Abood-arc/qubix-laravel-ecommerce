import { expect, test, type APIRequestContext, type Browser, type Page } from "@playwright/test";
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

async function saveDesignSelect(admin: Page, label: string, option: string): Promise<void> {
    await admin.goto("admin/configuration/general/design");
    await admin.getByLabel(label).selectOption({ label: option });
    await admin.getByRole("button", { name: "Save Configuration" }).click();
    await expect(admin.getByText("Configuration saved successfully").first()).toBeVisible();
}

export const setCardStyle = (admin: Page, style: CardStyle) => saveDesignSelect(admin, "Card style", style);

export const setSizeUnit = (admin: Page, unit: "Inches" | "Centimetres") => saveDesignSelect(admin, "Size unit", unit);

export const setStoreLook = (admin: Page, look: StoreLookCode) => saveDesignSelect(admin, "Look", STORE_LOOKS[look]);

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
