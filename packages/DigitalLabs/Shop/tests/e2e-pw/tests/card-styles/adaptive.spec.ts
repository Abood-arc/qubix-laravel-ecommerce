import { devices, type Page } from "@playwright/test";
import { test, expect } from "../../setup";
import {
    STORE_LOOKS,
    brandColour,
    currentStoreLook,
    findMultiPhotoProduct,
    lookColour,
    openSearchFor,
    photoLink,
    setCardStyle,
    setStoreLook,
    styledCard,
    transformAnimationCount,
    withAdmin,
    type CardProduct,
    type StoreLookCode,
} from "../../utils/card-styles";

const { defaultBrowserType, ...pixel7 } = devices["Pixel 7"];

test.describe.configure({ mode: "serial" });

test.afterAll(async ({ browser }) => {
    await withAdmin(browser, (admin) => setCardStyle(admin, "Classic"));
});

test.describe("Swipe card on a phone", () => {
    test.use(pixel7);

    test.beforeAll(async ({ browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Swipe, named and sized"));
    });

    test("the photo row swipes natively and names the photo", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await openSearchFor(page, product);

        const card = styledCard(page, product);
        // The scrolling row has no role of its own.
        const row = card.locator(".pc-swipe-track");

        await expect(row).toHaveCSS("scroll-snap-type", "x mandatory");

        await row.evaluate((element) => element.scrollTo({ left: element.clientWidth, behavior: "instant" }));

        await expect(card.getByText(`Photo 2 of ${product.photoCount}`)).toBeVisible();
    });

    test("Add to cart is at least 44px tall", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await openSearchFor(page, product);

        const box = (await styledCard(page, product).getByRole("button", { name: "Add To Cart" }).boundingBox())!;

        expect(box.height).toBeGreaterThanOrEqual(44);
    });
});

test.describe("Photo deck on a phone", () => {
    test.use(pixel7);

    test.beforeAll(async ({ browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));
    });

    test("the round bag button is a 44px target and the counter answers a tap", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await openSearchFor(page, product);

        const card = styledCard(page, product);
        const add = (await card.getByRole("button", { name: `Add ${product.name} to cart` }).boundingBox())!;

        expect(add.width).toBeGreaterThanOrEqual(44);
        expect(add.height).toBeGreaterThanOrEqual(44);

        await card.getByRole("button", { name: `Next photo of ${product.name}, showing 1 of ${product.photoCount}` }).tap();

        await expect(card.getByText(`2 / ${product.photoCount}`)).toBeVisible();
    });
});

test.describe("Arabic, right to left", () => {
    test("Swipe: pointing from the right edge to the left edge goes through the photos", async ({ page, browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Swipe, named and sized"));

        await page.goto("?locale=ar");

        const product = await findMultiPhotoProduct(page.request, "ar");

        await openSearchFor(page, product, "&locale=ar");

        await expect(page.locator("html")).toHaveAttribute("dir", "rtl");

        const card = styledCard(page, product);
        const box = (await photoLink(page, product).boundingBox())!;

        await page.mouse.move(box.x + box.width - 4, box.y + box.height / 2);
        await page.mouse.move(box.x + 4, box.y + box.height / 2, { steps: 8 });

        await expect(card.getByText(`الصورة ${product.photoCount} من ${product.photoCount}`)).toBeVisible();

        await photoLink(page, product).focus();
        await page.keyboard.press("ArrowLeft");

        await expect(card.getByRole("status")).toHaveText(/^الصورة \d+ من \d+$/);
    });

    test("Photo deck: the left arrow brings up the next photo", async ({ page, browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));

        await page.goto("?locale=ar");

        const product = await findMultiPhotoProduct(page.request, "ar");

        await openSearchFor(page, product, "&locale=ar");

        const card = styledCard(page, product);

        await photoLink(page, product).focus();
        await page.keyboard.press("ArrowLeft");

        await expect(card.getByRole("status")).toHaveText(`الصورة 2 من ${product.photoCount}`);
        await expect(card.getByText(`2 / ${product.photoCount}`)).toBeVisible();
    });

    test("Photo deck: the − 1 + control's name reads its count in the order the control shows it", async ({ page, browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));

        await page.goto("?locale=ar");

        const product = await findMultiPhotoProduct(page.request, "ar");

        await openSearchFor(page, product, "&locale=ar");

        const card = styledCard(page, product);

        await card.getByRole("button", { name: `أضف ${product.name} إلى السلة` }).click();

        // The control shows "1 في السلة": the number first, then the words.
        await expect(card.getByRole("group", { name: "1 في السلة" })).toBeVisible();
    });
});

test.describe("Reduced motion", () => {
    test.use({ reducedMotion: "reduce" });

    let product: CardProduct;

    test.beforeAll(async ({ browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));
    });

    test.beforeEach(async ({ request }) => {
        product ??= await findMultiPhotoProduct(request);
    });

    /**
     * Opacity animations started on the card's photo layers since the page loaded (they are short, so
     * they are recorded as they start rather than looked up afterwards).
     */
    const recordPhotoFades = (page: Page) =>
        photoLink(page, product).evaluate((link) => {
            const fades: number[] = ((window as any).photoFades = []);
            const animate = Element.prototype.animate;

            Element.prototype.animate = function (keyframes, options) {
                const frames = Array.isArray(keyframes) ? keyframes : [keyframes];

                if (link.contains(this) && frames.some((frame) => frame && "opacity" in frame)) {
                    fades.push(frames.length);
                }

                return animate.call(this, keyframes, options);
            };
        });

    test("the deck swaps photos with a fade, not a throw", async ({ page }) => {
        await openSearchFor(page, product);

        const card = styledCard(page, product);

        await recordPhotoFades(page);

        await card.getByRole("button", { name: `Next photo of ${product.name}, showing 1 of ${product.photoCount}` }).click();

        expect(await transformAnimationCount(page)).toBe(0);
        await expect(card.getByText(`2 / ${product.photoCount}`)).toBeVisible();
        await expect.poll(() => page.evaluate(() => (window as any).photoFades.length)).toBeGreaterThan(0);
    });

    test("the deck does not fan out under the mouse", async ({ page }) => {
        await openSearchFor(page, product);

        // The photo layers are aria-hidden decoration with no role: read the second one's pose directly.
        const secondLayer = photoLink(page, product).locator(".pc-deck-layer").nth(1);
        const resting = await secondLayer.evaluate((layer) => getComputedStyle(layer).transform);

        await photoLink(page, product).hover();

        // Layers have no transition under reduced motion, so a fan would show at once.
        await expect(secondLayer).toHaveCSS("transform", resting);
        expect(await transformAnimationCount(page)).toBe(0);
    });
});

test.describe("Every store look", () => {
    let originalLook: StoreLookCode;

    test.beforeAll(async ({ browser }) => {
        const page = await browser.newPage({ baseURL: test.info().project.use.baseURL });

        originalLook = await currentStoreLook(page);

        await page.close();
    });

    test.afterAll(async ({ browser }) => {
        await withAdmin(browser, (admin) => setStoreLook(admin, originalLook));
    });

    for (const look of Object.keys(STORE_LOOKS) as StoreLookCode[]) {
        test(`${look}: both card styles take the look's brand colour`, async ({ page, browser }) => {
            // Two admin saves and two storefront pages.
            test.slow();

            await withAdmin(browser, async (admin) => {
                await setStoreLook(admin, look);
                await setCardStyle(admin, "Swipe, named and sized");
            });

            const product = await findMultiPhotoProduct(page.request);

            await openSearchFor(page, product);

            await expect(page.locator("html")).toHaveAttribute("data-look", look);
            await expect(styledCard(page, product).getByRole("button", { name: "Add To Cart" })).toHaveCSS("border-top-color", await brandColour(page));

            // Text and lines take the look's own ink and hairline, not one set of greys for every look.
            await expect(styledCard(page, product)).toHaveCSS("color", await lookColour(page, "--c-ink"));
            await expect(styledCard(page, product).getByRole("button", { name: `Compare ${product.name}` })).toHaveCSS("color", await lookColour(page, "--c-muted"));

            await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));

            await openSearchFor(page, product);

            await expect(styledCard(page, product).getByRole("button", { name: `Add ${product.name} to cart` })).toHaveCSS("background-color", await brandColour(page));
            await expect(styledCard(page, product).getByRole("button", { name: `Compare ${product.name}` })).toHaveCSS("border-top-color", await lookColour(page, "--c-line"));
        });
    }
});
