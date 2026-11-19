import { test, expect, type Page } from "@playwright/test";

/**
 * Storefront flash messages (components/flash-group). Each message is shown twice, in the desktop and the
 * phone list, so these tests look at the visible copy only.
 */

async function openStorefront(page: Page): Promise<void> {
    await page.goto("");

    // Messages are added through the shop's event bus once the Vue app has mounted (Vue marks the root element).
    await page.waitForFunction(() => !!(document.querySelector("#app") as any)?.__vue_app__);
}

function flash(page: Page, message: Record<string, unknown>): Promise<void> {
    return page.evaluate((payload) => (window as any).app.config.globalProperties.$emitter.emit("add-flash", payload), message);
}

const visibleText = (page: Page, text: string) => page.getByText(text, { exact: true }).filter({ visible: true });

test("a message closes after about a second", async ({ page }) => {
    await openStorefront(page);

    await flash(page, { type: "success", message: "Flash test: plain" });

    await expect(visibleText(page, "Flash test: plain")).toBeVisible();

    await page.waitForTimeout(500);

    await expect(visibleText(page, "Flash test: plain")).toBeVisible();
    await expect(visibleText(page, "Flash test: plain")).toBeHidden({ timeout: 2000 });
});

test("closing one message never closes the next one", async ({ page }) => {
    await openStorefront(page);

    await flash(page, { type: "success", message: "Flash test: first" });
    await page.waitForTimeout(400);
    await flash(page, { type: "success", message: "Flash test: second" });

    await expect(visibleText(page, "Flash test: first")).toBeHidden({ timeout: 2000 });

    // The second message has its own second; it must outlive the first.
    await expect(visibleText(page, "Flash test: second")).toBeVisible();
    await expect(visibleText(page, "Flash test: second")).toBeHidden({ timeout: 2000 });
});

test("a message with a link stays until the shopper closes it", async ({ page }) => {
    await openStorefront(page);

    await flash(page, {
        type: "warning",
        message: "Flash test: with a link",
        link: { url: "compare", label: "Flash test link" },
    });

    await page.waitForTimeout(3000);

    const message = page.getByText("Flash test: with a link").filter({ visible: true });

    await expect(message).toBeVisible();
    await expect(page.getByRole("link", { name: "Flash test link" }).filter({ visible: true })).toBeVisible();

    await page.getByRole("button", { name: "Close message" }).filter({ visible: true }).click();

    await expect(message).toBeHidden();
});

test("the same success again takes the place of the one showing, with a fresh second", async ({ page }) => {
    await openStorefront(page);

    await flash(page, { type: "success", message: "Flash test: added" });
    await page.waitForTimeout(600);
    await flash(page, { type: "success", message: "Flash test: added" });
    await flash(page, { type: "success", message: "Flash test: added" });

    await expect(visibleText(page, "Flash test: added")).toHaveCount(1);

    // The first one's second is up, but the latest one has its own.
    await page.waitForTimeout(600);
    await expect(visibleText(page, "Flash test: added")).toBeVisible();
    await expect(visibleText(page, "Flash test: added")).toBeHidden({ timeout: 2000 });
});

test("different messages, and the same warning again, still each show", async ({ page }) => {
    await openStorefront(page);

    await flash(page, { type: "success", message: "Flash test: one" });
    await flash(page, { type: "success", message: "Flash test: two" });
    await flash(page, { type: "warning", message: "Flash test: careful" });
    await flash(page, { type: "warning", message: "Flash test: careful" });

    await expect(visibleText(page, "Flash test: one")).toBeVisible();
    await expect(visibleText(page, "Flash test: two")).toBeVisible();
    await expect(visibleText(page, "Flash test: careful")).toHaveCount(2);
});
