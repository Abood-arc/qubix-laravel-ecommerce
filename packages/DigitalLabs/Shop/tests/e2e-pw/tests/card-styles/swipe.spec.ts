import { test, expect } from "../../setup";
import type { Locator } from "@playwright/test";
import {
    cartQuantityOf,
    findMultiPhotoProduct,
    openSearchFor,
    photoLink,
    setCardStyle,
    setSizeUnit,
    styledCard,
    withAdmin,
    withCardData,
    type CardProduct,
} from "../../utils/card-styles";

test.describe.configure({ mode: "serial" });

let product: CardProduct;

test.beforeAll(async ({ browser }) => {
    await withAdmin(browser, async (admin) => {
        await setCardStyle(admin, "Swipe, named and sized");
        await setSizeUnit(admin, "Inches");
    });
});

test.afterAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
});

test.beforeEach(async ({ request }) => {
    product ??= await findMultiPhotoProduct(request);
});

test("loads the extra photos only once the shopper points at the card", async ({ page }) => {
    await openSearchFor(page, product);

    // Photos 2+ are decorative (alt=""), so they have no img role: select the <img> elements directly.
    const photos = photoLink(page, product).locator("img");

    await expect(photos.first()).toHaveAttribute("src", /.+/);
    await expect(photos.nth(1)).not.toHaveAttribute("src", /.+/);

    await photoLink(page, product).hover();

    await expect(photos.nth(1)).toHaveAttribute("src", /.+/);
});

test("pointing across the photo shows each photo, and leaving shows the first again", async ({ page }) => {
    await openSearchFor(page, product);

    const card = styledCard(page, product);
    const box = (await photoLink(page, product).boundingBox())!;
    const last = `Photo ${product.photoCount} of ${product.photoCount}`;

    await page.mouse.move(box.x + 4, box.y + box.height / 2);
    await page.mouse.move(box.x + box.width - 4, box.y + box.height / 2, { steps: 8 });

    await expect(card.getByText(last)).toBeVisible();

    await page.mouse.move(box.x + box.width / 2, box.y - 60);

    await expect(card.getByText(last)).toHaveCount(0);
});

test("arrow keys on the photo change the photo and announce it", async ({ page }) => {
    await openSearchFor(page, product);

    const card = styledCard(page, product);

    await photoLink(page, product).focus();
    await page.keyboard.press("ArrowRight");

    await expect(card.getByRole("status")).toHaveText(`Photo 2 of ${product.photoCount}`);

    await page.keyboard.press("ArrowLeft");

    await expect(card.getByRole("status")).toHaveText(`Photo 1 of ${product.photoCount}`);
});

test("shows the size and low stock when the shop has them", async ({ page }) => {
    await withCardData(page, product.id, { dimensions: { width: "16", height: "14", depth: "5" }, stock_left: 3 });
    await openSearchFor(page, product);

    const card = styledCard(page, product);

    await expect(card.getByText("16 × 14 × 5")).toBeVisible();
    await expect(card.getByText("Size: 16 by 14 inches, 5 inches deep")).toBeAttached();
    await expect(card.getByText("Only 3 left")).toBeVisible();
});

test("hides the size and stock lines when the shop has no such facts", async ({ page }) => {
    await withCardData(page, product.id, { dimensions: null, stock_left: null });
    await openSearchFor(page, product);

    const card = styledCard(page, product);

    await expect(card.getByText("Compare", { exact: true })).toBeVisible();
    await expect(card.getByText(/×/)).toHaveCount(0);
    await expect(card.getByText(/^Only \d+ left$/)).toHaveCount(0);
});

/**
 * Where `first` sits before `second` in reading order: earlier in the DOM, and on screen either on an
 * earlier line or further left on the same line (English).
 */
async function expectReadsBefore(first: Locator, second: Locator): Promise<void> {
    expect(await first.evaluate((element, other) => !!(element.compareDocumentPosition(other!) & Node.DOCUMENT_POSITION_FOLLOWING), await second.elementHandle())).toBe(true);

    const a = (await first.boundingBox())!;
    const b = (await second.boundingBox())!;

    expect(a.y + a.height <= b.y + 1 || (Math.abs(a.y - b.y) < a.height && a.x + a.width <= b.x + 1)).toBe(true);
}

test("a price label such as \"As low as\" stays ahead of the price", async ({ page }) => {
    // The listing has no configurable product, so the card gets the price_html a configurable product
    // prints (products/prices/configurable.blade.php): a label, an empty old-price slot, the price.
    await withCardData(page, product.id, {
        price_html: '<p class="price-label text-sm text-zinc-500 max-sm:text-xs">As low as</p>'
            + '<p class="regular-price text-lg font-semibold text-gray-500 line-through"></p>'
            + '<p class="final-price font-semibold">₹999.00</p>',
    });
    await openSearchFor(page, product);

    const card = styledCard(page, product);

    await expectReadsBefore(card.getByText("As low as", { exact: true }), card.getByText("₹999.00", { exact: true }));
});

test("the current price shows before the struck-out old price", async ({ page }) => {
    // A discounted simple product's price_html (products/prices/index.blade.php): the old price first.
    await withCardData(page, product.id, {
        price_html: '<p class="final-price font-medium text-zinc-500 line-through max-sm:leading-4">₹300.00</p>'
            + '<p class="font-semibold max-sm:leading-4">₹250.00</p>',
    });
    await openSearchFor(page, product);

    const card = styledCard(page, product);

    await expect(card.getByText("₹250.00", { exact: true })).toBeVisible();

    const now = (await card.getByText("₹250.00", { exact: true }).boundingBox())!;
    const was = (await card.getByText("₹300.00", { exact: true }).boundingBox())!;

    expect(now.x + now.width).toBeLessThanOrEqual(was.x + 1);
});

test("the Compare tick box adds and removes the product (guest)", async ({ page }) => {
    await openSearchFor(page, product);
    await page.evaluate(() => localStorage.removeItem("compare_items"));
    await page.reload();

    const compare = styledCard(page, product).getByRole("button", { name: `Compare ${product.name}` });

    await expect(compare).toHaveAttribute("aria-pressed", "false");

    await compare.click();

    await expect(compare).toHaveAttribute("aria-pressed", "true");
    await expect(page.getByText("Added to compare (1 of 4)").first()).toBeVisible();
    expect(await page.evaluate(() => JSON.parse(localStorage.getItem("compare_items") ?? "[]"))).toContain(product.id);

    await compare.click();

    await expect(compare).toHaveAttribute("aria-pressed", "false");
    await expect(page.getByText("Removed from compare").first()).toBeVisible();
});

test("Add to cart turns straight into the − 1 + control, with no dots or tick in between", async ({ page }) => {
    await openSearchFor(page, product);

    const card = styledCard(page, product);

    // The state wrapper has no role; its data-state drives the dots, the tick and the − 1 + control.
    // The page records the states the wrapper passes through, from before the click.
    const wrapper = card.locator(".pc-buy");

    await wrapper.evaluate((element: HTMLElement & { seenStates?: string[] }) => {
        element.seenStates = [];

        new MutationObserver(() => element.seenStates!.push(element.dataset.state ?? ""))
            .observe(element, { attributes: true, attributeFilter: ["data-state"] });
    });

    await card.getByRole("button", { name: "Add To Cart" }).click();

    await expect(page.getByText("Item Added Successfully").first()).toBeVisible();
    await expect.poll(() => cartQuantityOf(page, product)).toBe(1);
    expect(await wrapper.evaluate((element: HTMLElement & { seenStates?: string[] }) => element.seenStates)).toEqual(["qty"]);
});

test("the wishlist heart sends a guest to sign in", async ({ page }) => {
    await openSearchFor(page, product);

    await styledCard(page, product).getByRole("button", { name: `Save ${product.name} to wishlist` }).click();

    await expect(page).toHaveURL(/customer\/login/);
});
