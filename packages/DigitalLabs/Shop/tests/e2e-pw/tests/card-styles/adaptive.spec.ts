import { devices, type Locator, type Page } from "@playwright/test";
import { test, expect } from "../../setup";
import {
    STORE_LOOKS,
    brandColour,
    contrastRatio,
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
     * Fades started on the card's photo layers from now on: animations whose keyframes change opacity and
     * never move anything (a throw also fades, but it has a transform). They are short, so they are
     * recorded as they start rather than looked up afterwards.
     */
    const recordPhotoFades = (page: Page) =>
        photoLink(page, product).evaluate((link) => {
            const fades: number[] = ((window as any).photoFades = []);
            const animate = Element.prototype.animate;

            Element.prototype.animate = function (keyframes, options) {
                const frames = Array.isArray(keyframes) ? keyframes : [keyframes];

                const fade = frames.some((frame) => frame && "opacity" in frame) && ! frames.some((frame) => frame && "transform" in frame);

                if (link.contains(this) && fade) {
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

            // An unticked box is shown by its outline alone, so the outline needs 3:1 against the box (WCAG 1.4.11).
            // The box is an aria-hidden part of the Compare button, with no role of its own.
            const box = styledCard(page, product).getByRole("button", { name: `Compare ${product.name}` }).locator(".pc-compare-box");
            const [outline, fill] = await box.evaluate((element) => [getComputedStyle(element).borderTopColor, getComputedStyle(element).backgroundColor]);

            expect(contrastRatio(outline, fill), `tick box outline ${outline} on ${fill}`).toBeGreaterThanOrEqual(3);

            await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));

            await openSearchFor(page, product);

            await expect(styledCard(page, product).getByRole("button", { name: `Add ${product.name} to cart` })).toHaveCSS("background-color", await brandColour(page));
            await expect(styledCard(page, product).getByRole("button", { name: `Compare ${product.name}` })).toHaveCSS("border-top-color", await lookColour(page, "--c-line"));
        });
    }
});

/**
 * Clicks Add to cart and reads the flying photo's path: the animations are paused on their first and last
 * frame to measure the photo's centre there. Also returns the centre of the visible header cart icon. The
 * photo (.pc-fly) and the icon ([data-cart-target]) have no role, so they are found by CSS.
 */
async function flightToCart(page: Page, add: Locator) {
    await page.evaluate(() => {
        new MutationObserver((records, observer) =>
            records.forEach((record) =>
                record.addedNodes.forEach((node) => {
                    if (! (node instanceof HTMLElement) || ! node.classList.contains("pc-fly")) {
                        return;
                    }

                    observer.disconnect();

                    const animations = node.getAnimations({ subtree: true });
                    const centre = () => {
                        const box = node.querySelector(".pc-fly-dot")!.getBoundingClientRect();

                        return { x: box.left + box.width / 2, y: box.top + box.height / 2 };
                    };

                    animations.forEach((animation) => animation.pause());
                    animations.forEach((animation) => (animation.currentTime = 0));

                    const start = centre();

                    animations.forEach((animation) => (animation.currentTime = Number(animation.effect!.getComputedTiming().endTime) - 1));

                    const end = centre();

                    animations.forEach((animation) => animation.play());

                    (window as any).cardFlight = { start, end };
                })
            )
        ).observe(document.body, { childList: true });
    });

    const button = (await add.boundingBox())!;

    await add.click();
    await page.waitForFunction(() => (window as any).cardFlight);

    const target = await page.evaluate(() => {
        const icon = [...document.querySelectorAll("[data-cart-target]")].find((element) => element.getBoundingClientRect().width > 0)!;
        const box = icon.getBoundingClientRect();

        return { x: box.left + box.width / 2, y: box.top + box.height / 2 };
    });

    return {
        button: { x: button.x + button.width / 2, y: button.y + button.height / 2 },
        ...((await page.evaluate(() => (window as any).cardFlight)) as { start: { x: number; y: number }; end: { x: number; y: number } }),
        target,
    };
}

for (const device of ["desktop", "phone"] as const) {
    test.describe(`Arabic on a ${device}: the photo flies to the cart icon`, () => {
        test.use(device === "phone" ? pixel7 : { viewport: { width: 1366, height: 900 } });

        test.beforeAll(async ({ browser }) => {
            await withAdmin(browser, (admin) => setCardStyle(admin, "Swipe, named and sized"));
        });

        test("from the Add to cart button to the header cart, right to left", async ({ page }) => {
            await page.goto("?locale=ar");

            const product = await findMultiPhotoProduct(page.request, "ar");

            await openSearchFor(page, product, "&locale=ar");

            await expect(page.locator("html")).toHaveAttribute("dir", "rtl");

            const flight = await flightToCart(page, styledCard(page, product).getByRole("button", { name: /^أضف إلى السلة/ }));

            // It leaves from the button and lands on the icon (the photo shrinks as it lands, so allow a few pixels).
            expect(Math.hypot(flight.start.x - flight.button.x, flight.start.y - flight.button.y)).toBeLessThan(4);
            expect(Math.hypot(flight.end.x - flight.target.x, flight.end.y - flight.target.y)).toBeLessThan(12);
        });
    });
}

const DECK_TEXT = {
    en: {
        add: (name: string) => `Add ${name} to cart`,
        next: (name: string, current: number, total: number) => `Next photo of ${name}, showing ${current} of ${total}`,
        inCart: "1 in cart",
    },
    ar: {
        add: (name: string) => `أضف ${name} إلى السلة`,
        next: (name: string, current: number, total: number) => `الصورة التالية من ${name}، المعروضة ${current} من ${total}`,
        inCart: "1 في السلة",
    },
};

for (const device of ["desktop", "phone"] as const) {
    test.describe(`Photo deck on a ${device}: the "1 / 4" counter while the product is in the cart`, () => {
        test.use(device === "phone" ? pixel7 : { viewport: { width: 1366, height: 900 } });

        test.beforeAll(async ({ browser }) => {
            await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));
        });

        for (const locale of ["en", "ar"] as const) {
            test(`${locale}: it moves above the − 1 + control and still answers a ${device === "phone" ? "tap" : "click"}`, async ({ page }) => {
                const text = DECK_TEXT[locale];

                await page.goto(`?locale=${locale}`);

                const product = await findMultiPhotoProduct(page.request, locale);

                await openSearchFor(page, product, `&locale=${locale}`);

                const card = styledCard(page, product);

                await card.getByRole("button", { name: text.add(product.name) }).click();

                const control = card.getByRole("group", { name: text.inCart });
                const counter = card.getByRole("button", { name: text.next(product.name, 1, product.photoCount) });

                await expect(control).toBeVisible();
                await expect(counter).toBeVisible();

                // Clear of the control once it has moved up.
                await expect
                    .poll(async () => (await counter.boundingBox())!.y + (await counter.boundingBox())!.height <= (await control.boundingBox())!.y)
                    .toBe(true);

                // Both actions check that nothing else would take the pointer.
                device === "phone" ? await counter.tap() : await counter.click();

                await expect(card.getByText(`2 / ${product.photoCount}`)).toBeVisible();
                await expect(control).toBeVisible();
            });
        }
    });
}
