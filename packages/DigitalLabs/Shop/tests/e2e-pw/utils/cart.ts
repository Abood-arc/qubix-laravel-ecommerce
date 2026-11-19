import { expect, type Page, type Route } from "@playwright/test";

/**
 * Cart helpers for specs that time what the shopper sees against what the server answers.
 */

export interface HeldRequest {
    /** The product the add was for (adds only). */
    productId?: number;
    /** When the browser sent it (Date.now()). */
    start: number;
    /** When its answer was handed back to the browser, once it has been. */
    end?: number;
}

const CART_API = /\/api\/checkout\/cart(\?|$)/;

const pause = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Hold every cart request of `method` for `delay` ms, then pass on the server's answer, or `answer`'s
 * instead (to stand in for a refusal). Returns the log of the held requests, in the order they were sent.
 */
export async function holdCartRequests(
    page: Page,
    method: "GET" | "POST" | "PUT",
    delay: number,
    answer?: (route: Route) => Promise<void>,
): Promise<HeldRequest[]> {
    const log: HeldRequest[] = [];

    await page.route(CART_API, async (route) => {
        if (route.request().method() !== method) {
            return route.fallback();
        }

        const held: HeldRequest = { start: Date.now() };

        if (method === "POST") {
            held.productId = route.request().postDataJSON()?.product_id;
        }

        log.push(held);

        await pause(delay);

        if (answer) {
            await answer(route);
        } else {
            const response = await route.fetch();

            await route.fulfill({ response });
        }

        held.end = Date.now();
    });

    return log;
}

/**
 * Adds a product to the page's cart through the cart API, with the page's session (open a page first).
 */
export async function addToCartByApi(page: Page, productId: number, quantity = 1): Promise<void> {
    const token = (await page.context().cookies()).find((cookie) => cookie.name === "XSRF-TOKEN")?.value ?? "";

    const response = await page.request.post("api/checkout/cart", {
        headers: { Accept: "application/json", "X-XSRF-TOKEN": decodeURIComponent(token) },
        data: { product_id: productId, quantity },
    });

    expect(response.ok(), `add product ${productId} to the cart`).toBeTruthy();
}

/**
 * The number on the header cart icon that is showing ("" when it shows none).
 */
export async function headerCartCount(page: Page): Promise<string> {
    const badge = page.locator("[data-cart-target]").filter({ visible: true }).locator(".header-badge");

    return (await badge.count()) ? (await badge.first().innerText()).trim() : "";
}

/**
 * The chat widget is a third party's: keep it out, so it covers nothing and costs nothing in a spec.
 */
export const blockChatWidget = (page: Page) => page.route(/zanderio/, (route) => route.abort());
