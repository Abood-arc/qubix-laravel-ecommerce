import { test, expect } from "../setup";
import { findSaleableProducts, openSearchFor, setGuestCompare } from "../utils/card-styles";

test("should add product to compare page", async ({ page }) => {
    await page.goto("");

    await page
        .locator("div:nth-child(2) > .-mt-9 > .action-items > .icon-compare")
        .first()
        .click();
    await page.locator(".action-items > .icon-compare").first().click();
    await page
        .locator("div:nth-child(3) > .-mt-9 > .action-items > .icon-compare")
        .first()
        .click();

    await expect(
        page.getByText("Item added successfully to compare list").first()
    ).toBeVisible();
});

test("should remove product from the compare page", async ({ page }) => {
    await page.goto("");

    await page
        .locator("div:nth-child(2) > .-mt-9 > .action-items > .icon-compare")
        .first()
        .click();
    await page.locator(".action-items > .icon-compare").first().click();
    await page.locator("div:nth-child(3) > .-mt-9 > div").first().click();

    await page.getByRole("link", { name: "Compare" }).click();
    await page.locator(".relative > .icon-cancel").first().click();
    await page.getByRole("button", { name: "Agree", exact: true }).click();
});

test("should remove all products from the compare page", async ({ page }) => {
    await page.goto("");
    
    await page
        .locator("div:nth-child(2) > .-mt-9 > .action-items > .icon-compare")
        .first()
        .click();
    await page.locator(".action-items > .icon-compare").first().click();
    await page.locator("div:nth-child(3) > .-mt-9 > div").first().click();

    await page.getByRole("link", { name: "Compare" }).click();
    await page.getByText("Delete All", { exact: true }).click();
    await page.getByRole("button", { name: "Agree", exact: true }).click();

    await expect(
        page.getByText("All items removed successfully.").first()
    ).toBeVisible();
});


test("refused compare adds show the limit message once, not once per click", async ({ page }) => {
    const products = await findSaleableProducts(page.request, 5);

    // A guest with 4 products already in compare, on the Classic card.
    await openSearchFor(page, products[4]);
    await setGuestCompare(page, products.slice(0, 4).map((product) => product.id));
    await page.reload();

    // The Classic card has no landmark role: the nearest card container around the product's name.
    const card = page.getByText(products[4].name, { exact: true }).first().locator("xpath=ancestor::div[contains(@class, 'group')][1]");
    const limitMessage = page.getByText("You can compare up to 4").filter({ visible: true });

    for (let attempt = 0; attempt < 2; attempt++) {
        await card.hover();
        await card.getByRole("button", { name: "Add To Compare" }).click();

        await expect(limitMessage.first()).toBeVisible();
    }

    // The message with its link stays until closed, so a second refusal must not add a second one.
    await expect(limitMessage).toHaveCount(1);
    await expect(page.getByRole("link", { name: "Open compare" }).filter({ visible: true })).toHaveCount(1);
});
