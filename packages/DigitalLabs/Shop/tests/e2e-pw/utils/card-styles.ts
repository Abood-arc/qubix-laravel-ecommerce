import { expect, type APIRequestContext, type Page } from "@playwright/test";

export interface CardProduct {
    id: number;
    name: string;
    urlKey: string;
    photoCount: number;
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
