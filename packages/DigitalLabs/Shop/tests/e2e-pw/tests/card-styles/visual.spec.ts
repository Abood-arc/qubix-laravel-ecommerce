import { devices } from "@playwright/test";
import fs from "fs";
import path from "path";
import { test, expect } from "../../setup";
import {
    STORE_LOOKS,
    currentStoreLook,
    findMultiPhotoProduct,
    openSearchFor,
    setCardStyle,
    setStoreLook,
    styledCard,
    withAdmin,
    type CardStyle,
    type StoreLookCode,
} from "../../utils/card-styles";

const { defaultBrowserType, ...pixel7 } = devices["Pixel 7"];

const STYLE_SLUGS: Record<string, string> = { "Swipe, named and sized": "swipe", "Photo deck": "deck" };

/**
 * Screenshots for the user's review: 2 styles × 4 looks × phone/desktop × English/Arabic, each as a plain
 * card and again with the product in the cart and picked for compare (the − 1 + control and the tray).
 * Run on its own: --grep @visual. Open them with: npx playwright show-report tests/e2e-pw/playwright-report
 * With CARD_SCREENSHOTS_DIR set, each one is also saved there as style-look-direction-device[-in-cart].png.
 */
test("@visual card styles in every look, device and direction", async ({ browser }, testInfo) => {
    test.setTimeout(15 * 60 * 1000);

    const baseURL = testInfo.project.use.baseURL;
    const opener = await browser.newPage({ baseURL });
    const originalLook = await currentStoreLook(opener);
    const folder = process.env.CARD_SCREENSHOTS_DIR;

    await opener.close();

    if (folder) {
        fs.mkdirSync(folder, { recursive: true });
    }

    const keep = async (name: string, body: Buffer) => {
        await testInfo.attach(name, { body, contentType: "image/png" });

        if (folder) {
            fs.writeFileSync(path.join(folder, `${name}.png`), body);
        }
    };

    try {
        for (const style of ["Swipe, named and sized", "Photo deck"] as CardStyle[]) {
            for (const look of Object.keys(STORE_LOOKS) as StoreLookCode[]) {
                await withAdmin(browser, async (admin) => {
                    await setCardStyle(admin, style);
                    await setStoreLook(admin, look);
                });

                for (const device of ["desktop", "phone"] as const) {
                    for (const locale of ["en", "ar"]) {
                        const context = await browser.newContext(
                            device === "phone" ? { ...pixel7, baseURL } : { baseURL, viewport: { width: 1366, height: 900 } }
                        );
                        const page = await context.newPage();
                        const name = `${STYLE_SLUGS[style]}-${look}-${locale === "ar" ? "rtl" : "ltr"}-${device}`;

                        await page.goto(`?locale=${locale}`);

                        const product = await findMultiPhotoProduct(page.request, locale);

                        await openSearchFor(page, product, `&locale=${locale}`);

                        const card = styledCard(page, product);

                        await expect(card).toBeVisible();

                        await keep(name, await card.screenshot({ animations: "disabled" }));

                        // Language-neutral clicks (the accessible names are translated in Arabic).
                        await page.evaluate(() => localStorage.removeItem("compare_items"));
                        await card.locator(".pc-compare").click();
                        await card.locator(".pc-add").click();
                        await expect(card.locator('.pc-buy[data-state="qty"]')).toBeVisible();
                        await card.scrollIntoViewIfNeeded();

                        await keep(`${name}-in-cart`, await page.screenshot({ animations: "disabled" }));

                        await context.close();
                    }
                }
            }
        }
    } finally {
        await withAdmin(browser, async (admin) => {
            await setCardStyle(admin, "Classic");
            await setStoreLook(admin, originalLook);
        });
    }
});
