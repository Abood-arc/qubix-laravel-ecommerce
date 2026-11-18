import { devices, type Locator, type Page } from "@playwright/test";
import { test, expect } from "../../setup";
import {
    STORE_LOOKS,
    brandColour,
    contrastRatio,
    currentStoreLook,
    findMultiPhotoProduct,
    fingerSwipe,
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

    test("a finger swipe moves the progress bars, the caption and the announcement with the photo", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await openSearchFor(page, product);

        const card = styledCard(page, product);
        // The scrolling row and the progress bars have no role of their own (the bars are aria-hidden).
        const row = card.locator(".pc-swipe-track");
        const bars = card.locator(".pc-tick");
        const last = Math.min(product.photoCount, 4);
        const photoOnShow = () => row.evaluate((element) => Math.round(Math.abs(element.scrollLeft) / element.clientWidth));

        await row.scrollIntoViewIfNeeded();

        for (let photo = 2; photo <= last; photo++) {
            await fingerSwipe(page, row, "left");

            await expect.poll(photoOnShow).toBe(photo - 1);
            await expect(bars.nth(photo - 1)).toHaveClass(/\bis-on\b/);
            await expect(card.locator(".pc-tick.is-on")).toHaveCount(1);
            await expect(card.getByText(`Photo ${photo} of ${product.photoCount}`, { exact: true }).first()).toBeVisible();
            await expect(card.getByRole("status")).toHaveText(`Photo ${photo} of ${product.photoCount}`);
        }

        await fingerSwipe(page, row, "right");

        await expect.poll(photoOnShow).toBe(last - 2);
        await expect(bars.nth(last - 2)).toHaveClass(/\bis-on\b/);
    });

    test("after an arrow-key move that lands off its mark, the row is still followed", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await openSearchFor(page, product);

        const card = styledCard(page, product);
        // The scrolling row, its inner rail and the progress bars have no role of their own.
        const row = card.locator(".pc-swipe-track");
        const bars = card.locator(".pc-tick");
        const last = Math.min(product.photoCount, 4);

        // A gap between the photos moves every snap point a few pixels past where the card aims the row,
        // as fractional photo widths do on real screens.
        await card.locator(".pc-swipe-rail").evaluate((rail: HTMLElement) => (rail.style.gap = "3px"));

        await photoLink(page, product).focus();

        for (let photo = 2; photo <= last; photo++) {
            await page.keyboard.press("ArrowRight");
            await expect(card.getByRole("status")).toHaveText(`Photo ${photo} of ${product.photoCount}`);
        }

        await expect(bars.nth(last - 1)).toHaveClass(/\bis-on\b/);
        await expect.poll(() => row.evaluate((element) => element.scrollLeft)).toBeGreaterThan(0);

        // Then something other than a finger or the card moves the row back (a scroll bar, a script).
        await page.waitForTimeout(400);
        await row.evaluate((element) => element.scrollTo({ left: 0, behavior: "instant" }));

        await expect(bars.first()).toHaveClass(/\bis-on\b/);
    });

    test("Add to cart is at least 44px tall", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);

        await openSearchFor(page, product);

        const box = (await styledCard(page, product).getByRole("button", { name: "Add To Cart" }).boundingBox())!;

        expect(box.height).toBeGreaterThanOrEqual(44);
    });
});

test.describe("Swipe card on a screen that turns touch after the page loaded", () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test.beforeAll(async ({ browser }) => {
        await withAdmin(browser, (admin) => setCardStyle(admin, "Swipe, named and sized"));
    });

    test("a finger swipe still moves the progress bars", async ({ page }) => {
        const product = await findMultiPhotoProduct(page.request);
        const cdp = await page.context().newCDPSession(page);
        const hasMouse = () => page.evaluate(() => matchMedia("(hover: hover) and (pointer: fine)").matches);

        // The page loads as on a computer with a mouse (an earlier phone test can leave the browser on touch).
        await cdp.send("Emulation.setTouchEmulationEnabled", { enabled: false });
        await openSearchFor(page, product);

        expect(await hasMouse()).toBe(true);

        const card = styledCard(page, product);
        // The scrolling row and the progress bars have no role of their own (the bars are aria-hidden).
        const row = card.locator(".pc-swipe-track");

        await expect(card.locator(".pc-tick").first()).toHaveClass(/\bis-on\b/);

        // Then the screen turns touch: the browser's phone view in its developer tools, a laptop folded into a tablet.
        await cdp.send("Emulation.setTouchEmulationEnabled", { enabled: true, maxTouchPoints: 5 });

        expect(await hasMouse()).toBe(false);

        await expect(row).toHaveCSS("overflow-x", "auto");

        await fingerSwipe(page, row, "left");

        await expect.poll(() => row.evaluate((element) => Math.round(element.scrollLeft / element.clientWidth))).toBe(1);
        await expect(card.locator(".pc-tick").nth(1)).toHaveClass(/\bis-on\b/);
        await expect(card.getByText(`Photo 2 of ${product.photoCount}`, { exact: true }).first()).toBeVisible();
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
        inCart: "1 in cart",
        photo: (current: number, total: number) => `Photo ${current} of ${total}`,
    },
    ar: {
        add: (name: string) => `أضف ${name} إلى السلة`,
        inCart: "1 في السلة",
        photo: (current: number, total: number) => `الصورة ${current} من ${total}`,
    },
};

for (const device of ["desktop", "phone"] as const) {
    test.describe(`Photo deck on a ${device}: the "1 / 4" counter while the product is in the cart`, () => {
        test.use(device === "phone" ? pixel7 : { viewport: { width: 1366, height: 900 } });

        test.beforeAll(async ({ browser }) => {
            await withAdmin(browser, (admin) => setCardStyle(admin, "Photo deck"));
        });

        for (const locale of ["en", "ar"] as const) {
            test(`${locale}: it steps aside for the − 1 + control, and the photos still turn`, async ({ page }) => {
                const text = DECK_TEXT[locale];

                await page.goto(`?locale=${locale}`);

                const product = await findMultiPhotoProduct(page.request, locale);

                await openSearchFor(page, product, `&locale=${locale}`);

                const card = styledCard(page, product);
                const counter = card.getByRole("button").filter({ hasText: `1 / ${product.photoCount}` });

                await expect(counter).toBeVisible();

                await card.getByRole("button", { name: text.add(product.name) }).click();

                const control = card.getByRole("group", { name: text.inCart });

                await expect(control).toBeVisible();
                // Hidden, so out of the tab order and away from screen readers too.
                await expect(counter).toBeHidden();

                await photoLink(page, product).focus();
                await page.keyboard.press(locale === "ar" ? "ArrowLeft" : "ArrowRight");

                await expect(card.getByRole("status")).toHaveText(text.photo(2, product.photoCount));
                await expect(control).toBeVisible();
            });
        }
    });
}
