import { devices, type Browser, type BrowserContext, type Page, type Request } from "@playwright/test";
import { test, expect } from "../setup";
import { loginAsAdmin } from "../utils/admin";
import { findMultiPhotoProduct, STORE_LOOKS, type StoreLookCode } from "../utils/card-styles";

/**
 * Pages load fast on a phone: the app mounts without waiting for images, the chat widget loads after the
 * app, images come at the size they are drawn (and only when needed), skeletons animate on the compositor,
 * and fonts come from this site, only the ones the page uses.
 */

const { defaultBrowserType, ...pixel7 } = devices["Pixel 7"];

/**
 * A 390px phone at 3x, like the iPhones and many Android phones.
 */
const phone = { ...pixel7, viewport: { width: 390, height: 844 }, deviceScaleFactor: 3 };

const CHAT_SCRIPT = "https://cdn.zanderio.ai/widget/loader.js";

/**
 * Stands in for the chat widget's script (no third-party traffic in tests): it adds the widget's host the
 * way the real one does, a shadow root holding a fixed "Open chat" button.
 */
const FAKE_CHAT_SCRIPT = `
    window.fakeChatId = document.currentScript.dataset.id;
    const host = document.createElement('div');
    host.id = 'zanderio-widget-host';
    document.body.appendChild(host);
    const button = document.createElement('button');
    button.setAttribute('aria-label', 'Open chat');
    button.style.cssText = 'position: fixed; right: 16px; bottom: 16px; width: 56px; height: 56px;';
    host.attachShadow({ mode: 'open' }).appendChild(button);
`;

async function newPage(browser: Browser, options = {}): Promise<Page> {
    const context = await browser.newContext({ baseURL: test.info().project.use.baseURL, ...options });

    return context.newPage();
}

/**
 * Every request whose URL matches `pattern`, from now on.
 */
function requestsTo(page: Page | BrowserContext, pattern: RegExp): string[] {
    const urls: string[] = [];

    page.on("request", (request: Request) => {
        if (pattern.test(request.url())) {
            urls.push(request.url());
        }
    });

    return urls;
}

async function fakeChatWidget(page: Page): Promise<string[]> {
    const requests = requestsTo(page, /cdn\.zanderio\.ai/);

    await page.route(CHAT_SCRIPT, (route) => route.fulfill({ contentType: "application/javascript", body: FAKE_CHAT_SCRIPT }));

    return requests;
}

/**
 * Notes (in the page) when the app mounted and when the window load event fired.
 */
async function recordStartup(page: Page): Promise<void> {
    await page.addInitScript(() => {
        document.addEventListener("qubix:app-mounted", () => ((window as any).mountedAt = performance.now()));
        window.addEventListener("load", () => ((window as any).loadedAt = performance.now()));
    });
}

const appMounted = (page: Page) => page.waitForFunction(() => (window as any).mountedAt !== undefined);

test.describe("the app and the chat widget", () => {
    test("the app mounts before the window load event, without waiting for a slow image", async ({ browser }) => {
        const page = await newPage(browser);

        await recordStartup(page);
        await fakeChatWidget(page);

        // Every image takes 4 seconds: window load waits for them, the app must not.
        await page.route(/\.(png|webp|jpe?g|gif|svg)(\?|$)/, async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 4000));
            await route.continue().catch(() => {});
        });

        await page.goto("", { waitUntil: "domcontentloaded" });

        await appMounted(page);

        expect(await page.evaluate(() => (window as any).loadedAt)).toBeUndefined();
        await expect(page.locator("#app")).toBeVisible();
        expect(await page.evaluate(() => Boolean((document.querySelector("#app") as any).__vue_app__))).toBe(true);

        await page.waitForFunction(() => (window as any).loadedAt !== undefined, null, { timeout: 30000 });

        const { mountedAt, loadedAt } = await page.evaluate(() => ({ mountedAt: (window as any).mountedAt, loadedAt: (window as any).loadedAt }));

        expect(loadedAt - mountedAt).toBeGreaterThan(2000);

        await page.context().close();
    });

    test("the chat script is not requested before the shopper does anything, and is on the first touch", async ({ browser }) => {
        const page = await newPage(browser, { ...phone });

        await recordStartup(page);

        const chatRequests = await fakeChatWidget(page);

        await page.goto("");
        await appMounted(page);

        // Not on load: idle for 2.5 s of the 4 s it waits after the app has mounted.
        await page.waitForTimeout(2500);
        expect(chatRequests).toEqual([]);

        // A first touch that lands on nothing clickable: tapping a fixed spot can hit a link and leave the page.
        await page.evaluate(() => document.body.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true, pointerType: "touch" })));

        await expect.poll(() => chatRequests).toEqual([CHAT_SCRIPT]);

        // The widget gets its id from its own script tag, and the drag handling adopts its button.
        await expect.poll(() => page.evaluate(() => (window as any).fakeChatId)).toBe("wdg_bDmt03WxuJCWokGT6wmHrX1O");
        await expect
            .poll(() =>
                page.evaluate(() => {
                    const button = document.getElementById("zanderio-widget-host")?.shadowRoot?.querySelector("button");

                    return button?.style.touchAction;
                })
            )
            .toBe("none");

        await page.context().close();
    });

    test("without any interaction, the chat script loads once the page has been idle a few seconds", async ({ browser }) => {
        const page = await newPage(browser);

        await recordStartup(page);

        const chatRequests = await fakeChatWidget(page);

        await page.goto("");
        await appMounted(page);

        expect(chatRequests).toEqual([]);

        await expect.poll(() => chatRequests, { timeout: 10000 }).toEqual([CHAT_SCRIPT]);
        await expect(page.locator("#zanderio-widget-host")).toBeAttached();

        await page.context().close();
    });
});

test.describe("images on a phone", () => {
    test("the hero banner fetches only the slide on show and the next one, at the phone's size", async ({ browser }) => {
        const page = await newPage(browser, { ...phone });

        await fakeChatWidget(page);

        const heroRequests = requestsTo(page, /\/(storage|cache\/\w+)\/theme\//);

        await page.goto("");

        const slides = page.locator(".home-hero-section img");

        await expect(slides.first()).toBeVisible();

        const slideCount = await slides.count();

        test.skip(slideCount < 3, "The home hero needs three or more slides to show the later ones waiting");

        await expect(slides.first()).toHaveAttribute("sizes", "100vw");
        await expect(slides.first()).toHaveAttribute("fetchpriority", "high");

        await expect.poll(() => new Set(heroRequests).size).toBe(2);
        await page.waitForTimeout(1500);

        // 390 css px at 3x needs 1170 px: the 1280px copy, not the original upload.
        expect(new Set(heroRequests).size).toBe(2);

        for (const url of heroRequests) {
            expect(url).toContain("/cache/large/theme/");
        }

        // Showing the second slide brings in the third.
        await page.getByRole("button", { name: "Go to slide 2" }).tap();

        await expect.poll(() => new Set(heroRequests).size).toBe(3);

        await page.context().close();
    });

    test("the category tiles fetch the 600px tile copy, not the original upload", async ({ browser }) => {
        const page = await newPage(browser, { ...phone });

        await fakeChatWidget(page);

        const tileRequests = requestsTo(page, /\/cache\/\w+\/category\//);

        await page.goto("");

        const tiles = page.locator('a[href] span.aspect-square img[srcset*="/cache/tile/category/"]');

        await tiles.first().scrollIntoViewIfNeeded();
        await expect(tiles.first()).toBeVisible();

        await expect.poll(() => tileRequests.length).toBeGreaterThan(0);
        await page.waitForTimeout(1000);

        expect(tileRequests.filter((url) => url.includes("/cache/tile/category/")).length).toBeGreaterThan(0);
        expect(tileRequests.filter((url) => url.includes("/cache/original/category/"))).toEqual([]);

        await page.context().close();
    });
});

test("the product zoomer fetches the original photos only once it is opened", async ({ browser }) => {
    const page = await newPage(browser);

    await fakeChatWidget(page);

    const product = await findMultiPhotoProduct(page.request);

    const originals = requestsTo(page, /\/cache\/original\/product\//);

    await page.goto(product.urlKey);
    await page.waitForLoadState("load");
    await page.waitForTimeout(1000);

    expect(originals).toEqual([]);

    // The desktop gallery's main photo opens the zoomer.
    await page.locator('img[fetchpriority="high"]').and(page.getByAltText(product.name, { exact: true })).filter({ visible: true }).first().click();

    await expect.poll(() => originals.length).toBeGreaterThan(0);
    await expect(page.locator(".icon-cancel.cursor-pointer").filter({ visible: true })).toBeVisible();

    await page.context().close();
});

test.describe("skeletons", () => {
    /**
     * The properties every running skeleton animation changes.
     */
    const skeletonProperties = (page: Page) =>
        page.evaluate(() =>
            document
                .getAnimations()
                .filter((animation) => (animation as CSSAnimation).animationName === "skeleton")
                .flatMap((animation) => (animation.effect as KeyframeEffect).getKeyframes().flatMap((frame) => Object.keys(frame)))
                .filter((key) => !["offset", "computedOffset", "easing", "composite"].includes(key))
        );

    async function pageWithSkeletons(browser: Browser, options = {}): Promise<Page> {
        const page = await newPage(browser, { ...phone, ...options });

        await fakeChatWidget(page);

        // Hold the products back so their skeletons stay up.
        await page.route(/\/api\/products/, () => {});

        await page.goto("");
        await expect(page.locator(".shimmer").first()).toBeAttached();

        return page;
    }

    test("animate only transform, which the compositor runs without repainting", async ({ browser }) => {
        const page = await pageWithSkeletons(browser);

        await expect.poll(async () => (await skeletonProperties(page)).length).toBeGreaterThan(0);

        expect(new Set(await skeletonProperties(page))).toEqual(new Set(["transform"]));

        await page.context().close();
    });

    test("stay still for shoppers who ask for reduced motion", async ({ browser }) => {
        const page = await pageWithSkeletons(browser, { reducedMotion: "reduce" });

        expect(await skeletonProperties(page)).toEqual([]);

        await page.context().close();
    });
});

test.describe("fonts", () => {
    /**
     * Every font file the page fetched, once its fonts are ready.
     */
    async function fontsFetched(page: Page, url: string): Promise<string[]> {
        const fonts: string[] = [];

        page.on("request", (request) => {
            if (request.resourceType() === "font" || /fonts\.(googleapis|gstatic)\.com/.test(request.url())) {
                fonts.push(request.url());
            }
        });

        await page.goto(url);
        await page.waitForLoadState("load");
        await page.evaluate(() => document.fonts.ready);

        return fonts;
    }

    /**
     * The font families of every face the page has loaded.
     */
    const loadedFamilies = (page: Page) =>
        page.evaluate(() => [...new Set([...document.fonts].filter((face) => face.status === "loaded").map((face) => face.family.replace(/"/g, "")))]);

    const LOOK_FONTS: Record<StoreLookCode, string[]> = {
        "forest-green": ["Karla"],
        "marigold-indigo": ["Mukta", "Baloo 2"],
        "sand-charcoal": ["Archivo"],
        "minimal-white": ["Manrope"],
    };

    const LOOK_FOLDERS: Record<StoreLookCode, string[]> = {
        "forest-green": ["karla"],
        "marigold-indigo": ["mukta", "baloo-2"],
        "sand-charcoal": ["archivo"],
        "minimal-white": ["manrope"],
    };

    test("come from this site, only the store look's (and the template's own) families", async ({ browser }) => {
        const page = await newPage(browser);

        await fakeChatWidget(page);

        const fonts = await fontsFetched(page, "");

        const look = (await page.locator("html").getAttribute("data-look")) as StoreLookCode;

        const allowed = [...LOOK_FOLDERS[look], "poppins", "dm-serif-display"];

        expect(fonts.length).toBeGreaterThan(0);

        for (const url of fonts) {
            const { origin, pathname } = new URL(url);

            expect(origin).toBe(new URL(page.url()).origin);

            // The icon font comes from the build; text fonts from the look's folders.
            if (!pathname.includes("/build/")) {
                expect(pathname).toMatch(new RegExp(`^/themes/shop/default/fonts/(${allowed.join("|")})/`));
            }
        }

        expect(await loadedFamilies(page)).toEqual(expect.arrayContaining([LOOK_FONTS[look][0]]));

        // The look's main face is preloaded.
        await expect(page.locator(`link[rel="preload"][as="font"][href*="/fonts/${LOOK_FOLDERS[look][0]}/"]`)).toHaveCount(1);

        await page.context().close();
    });

    test("every store look draws its text in its own fonts", async ({ browser }) => {
        const admin = await newPage(browser);

        await loginAsAdmin(admin);

        for (const look of Object.keys(STORE_LOOKS) as StoreLookCode[]) {
            const fonts = await fontsFetched(admin, `card-preview?style=classic&look=${look}&header=1`);

            expect(fonts.every((url) => !/fonts\.(googleapis|gstatic)\.com/.test(url)), look).toBe(true);
            expect(await loadedFamilies(admin), look).toEqual(expect.arrayContaining([LOOK_FONTS[look][0]]));

            const otherLooks = (Object.keys(LOOK_FOLDERS) as StoreLookCode[]).filter((other) => other !== look).flatMap((other) => LOOK_FOLDERS[other]);

            for (const folder of otherLooks) {
                expect(fonts.filter((url) => url.includes(`/fonts/${folder}/`)), `${look} fetched ${folder}`).toEqual([]);
            }
        }

        await admin.context().close();
    });

    test("an Arabic page sets its text in Noto Sans Arabic, from this site", async ({ browser }) => {
        const page = await newPage(browser);

        await fakeChatWidget(page);

        const fonts = await fontsFetched(page, "?locale=ar");

        test.skip((await page.locator("html").getAttribute("lang")) !== "ar", "No Arabic locale in this store");

        await expect(page.locator("html")).toHaveAttribute("dir", "rtl");

        expect(fonts.some((url) => url.includes("/fonts/noto-sans-arabic/noto-sans-arabic-arabic.woff2"))).toBe(true);
        expect(await loadedFamilies(page)).toContain("Noto Sans Arabic");
        await expect(page.locator('link[rel="preload"][as="font"][href*="/noto-sans-arabic-arabic.woff2"]')).toHaveCount(1);

        await page.context().close();
    });
});
