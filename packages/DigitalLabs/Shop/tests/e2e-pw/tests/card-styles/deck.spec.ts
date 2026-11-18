import { test, expect } from "../../setup";
import { devices, type Locator, type Page } from "@playwright/test";
import {
    findMultiPhotoProduct,
    openSearchFor,
    photoLink,
    setCardStyle,
    styledCard,
    transformAnimationCount,
    withAdmin,
    type CardProduct,
} from "../../utils/card-styles";

test.describe.configure({ mode: "serial" });

let product: CardProduct;

test.beforeAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));
});

test.afterAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
});

test.beforeEach(async ({ request }) => {
    product ??= await findMultiPhotoProduct(request);
});

const nextButton = (page: Page, current: number) =>
    styledCard(page, product).getByRole("button", {
        name: `Next photo of ${product.name}, showing ${current} of ${product.photoCount}`,
    });

/**
 * Animations still running on the card's photo layers (a throw or its landing). The loading shimmer runs
 * on a ::before and can run for as long as a photo downloads, so it does not count.
 */
const runningPhotoAnimations = (page: Page) =>
    photoLink(page, product).evaluate((link) =>
        link.getAnimations({ subtree: true }).filter((animation) => !(animation.effect as KeyframeEffect).pseudoElement).length);

test("the counter brings the next photo to the top with a throw", async ({ page }) => {
    await openSearchFor(page, product);

    await nextButton(page, 1).click();

    expect(await transformAnimationCount(page)).toBeGreaterThan(0);
    await expect(nextButton(page, 2)).toBeVisible();
    await expect(styledCard(page, product).getByText(`2 / ${product.photoCount}`)).toBeVisible();
});

test("dragging the top photo sideways throws it to the back without opening the product", async ({ page }) => {
    await openSearchFor(page, product);

    const before = page.url();
    const box = (await photoLink(page, product).boundingBox())!;

    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 - 120, box.y + box.height / 2, { steps: 6 });
    await page.mouse.up();

    await expect(nextButton(page, 2)).toBeVisible();
    expect(page.url()).toBe(before);
});

test("arrow keys on the photo move through the pile and announce the photo", async ({ page }) => {
    await openSearchFor(page, product);

    const card = styledCard(page, product);

    await photoLink(page, product).focus();
    await page.keyboard.press("ArrowRight");

    await expect(card.getByRole("status")).toHaveText(`Photo 2 of ${product.photoCount}`);

    await expect(card.getByText(`2 / ${product.photoCount}`)).toBeVisible();

    await page.keyboard.press("ArrowLeft");

    await expect(card.getByText(`1 / ${product.photoCount}`)).toBeVisible();
    await expect(card.getByRole("status")).toHaveText(`Photo 1 of ${product.photoCount}`);
});

test("keys pressed while a photo is still in the air all count", async ({ page }) => {
    await openSearchFor(page, product);

    const card = styledCard(page, product);

    await photoLink(page, product).focus();

    // A throw and its landing take about 650ms; these presses land well inside it.
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("ArrowRight");

    const shown = (2 % product.photoCount) + 1;

    await expect(card.getByText(`${shown} / ${product.photoCount}`)).toBeVisible();
    await expect(card.getByRole("status")).toHaveText(`Photo ${shown} of ${product.photoCount}`);

    await page.keyboard.press("ArrowLeft");

    await expect(card.getByText(`${shown === 1 ? product.photoCount : shown - 1} / ${product.photoCount}`)).toBeVisible();

    // Every photo settles back into the pile.
    await expect.poll(() => runningPhotoAnimations(page)).toBe(0);
});

test("the round bag button adds the product to the cart", async ({ page }) => {
    await openSearchFor(page, product);

    await styledCard(page, product).getByRole("button", { name: `Add ${product.name} to cart` }).click();

    await expect(page.getByText("Item Added Successfully").first()).toBeVisible();
});

test("compare and wishlist sit beside the price", async ({ page }) => {
    await openSearchFor(page, product);
    await page.evaluate(() => localStorage.removeItem("compare_items"));
    await page.reload();

    const card = styledCard(page, product);
    const compare = card.getByRole("button", { name: `Compare ${product.name}` });

    await compare.click();

    await expect(compare).toHaveAttribute("aria-pressed", "true");

    await compare.click();

    await expect(compare).toHaveAttribute("aria-pressed", "false");
    await expect(card.getByRole("button", { name: `Save ${product.name} to wishlist` })).toBeVisible();
});

test("photos under the top one load only once the shopper shows interest", async ({ page }) => {
    await openSearchFor(page, product);

    // Photos under the top one are decorative (alt="", aria-hidden layers): select the <img> elements directly.
    const photos = photoLink(page, product).locator("img");

    await expect(photos.nth(1)).not.toHaveAttribute("src", /.+/);

    await photoLink(page, product).hover();

    await expect(photos.nth(1)).toHaveAttribute("src", /.+/);
});

const { defaultBrowserType, ...pixel7 } = devices["Pixel 7"];

/**
 * Whether two boxes share any area (touching edges do not count).
 */
async function overlap(first: Locator, second: Locator): Promise<boolean> {
    const a = (await first.boundingBox())!;
    const b = (await second.boundingBox())!;

    return a.x < b.x + b.width - 0.5 && b.x < a.x + a.width - 0.5 && a.y < b.y + b.height - 0.5 && b.y < a.y + a.height - 0.5;
}

const LAYOUTS = {
    "a 390px phone": { ...pixel7, viewport: { width: 390, height: 844 } },
    "a computer": { viewport: { width: 1366, height: 900 } },
};

const TEXT = {
    en: { add: (name: string) => `Add ${name} to cart`, inCart: "1 in cart", removeOne: (name: string) => `Remove one ${name} from cart` },
    ar: { add: (name: string) => `أضف ${name} إلى السلة`, inCart: "1 في السلة", removeOne: (name: string) => `أزل قطعة من ${name}` },
};

for (const [device, use] of Object.entries(LAYOUTS)) {
    for (const locale of ["en", "ar"] as const) {
        test.describe(`The counter, the round Add to cart and the photo on ${device} (${locale})`, () => {
            test.use(use);

            test("never cover each other, before and while the product is in the cart", async ({ page }) => {
                if (locale === "ar") {
                    await page.goto("?locale=ar");
                }

                const shown = await findMultiPhotoProduct(page.request, locale);

                await openSearchFor(page, shown, locale === "ar" ? "&locale=ar" : "");

                const card = styledCard(page, shown);
                const photo = card.getByRole("img", { name: shown.name, exact: true });
                const counter = card.getByRole("button").filter({ hasText: `1 / ${shown.photoCount}` });
                const add = card.getByRole("button", { name: TEXT[locale].add(shown.name) });

                await card.scrollIntoViewIfNeeded();
                await expect(photo).toBeVisible();
                await expect(counter).toBeVisible();

                expect(await overlap(counter, add), "counter and button").toBe(false);
                expect(await overlap(counter, photo), "counter and photo").toBe(false);
                expect(await overlap(add, photo), "button and photo").toBe(false);

                // Both sit on one line: their middles line up.
                const middle = async (target: Locator) => {
                    const box = (await target.boundingBox())!;

                    return box.y + box.height / 2;
                };

                expect(Math.abs((await middle(counter)) - (await middle(add)))).toBeLessThan(1);

                // The counter at the start, the button at the end.
                const counterBox = (await counter.boundingBox())!;
                const addBox = (await add.boundingBox())!;

                expect(locale === "ar" ? counterBox.x > addBox.x : counterBox.x < addBox.x).toBe(true);

                await add.click();

                const stepper = card.getByRole("group", { name: TEXT[locale].inCart });

                await expect(stepper).toBeVisible();
                // The control widens over 320ms: measure once it has.
                await expect.poll(async () => (await stepper.boundingBox())!.width).toBeGreaterThan(3 * 44);

                expect(await overlap(stepper, photo), "− 1 + control and photo").toBe(false);
                // The counter steps aside while the control fills its line.
                await expect(counter).toBeHidden();

                await card.getByRole("button", { name: TEXT[locale].removeOne(shown.name) }).click();

                await expect(add).toBeVisible();
                await expect(counter).toBeVisible();
            });
        });
    }
}
