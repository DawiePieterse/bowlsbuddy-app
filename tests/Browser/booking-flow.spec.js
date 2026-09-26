// The member booking flow, end to end: register, pick a slot from the greens overview,
// book it for two players, share, see it under My bookings and cancel it.
// Runs at 390px (phone) and 1200px (desktop) via the projects in playwright.config.js.
import { test, expect } from '@playwright/test';

test('a member registers, books a rink for two and cancels it', async ({ page }, testInfo) => {
    const email = `member-${testInfo.project.name}-${Date.now()}@example.com`;

    // Greens overview is the home page
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Greens' })).toBeVisible();
    await expect(page.locator('.green-pill').first()).toBeVisible();

    // Register (the form has an anti-bot delay, so wait before submitting)
    await page.getByRole('link', { name: 'Register' }).first().click();
    await page.getByLabel('First name').fill('Playwright');
    await page.getByLabel('Surname').fill('Tester');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('a-good-password');
    await page.getByLabel('Password again').fill('a-good-password');
    await page.getByRole('checkbox', { name: /I accept the/ }).check();
    await page.waitForTimeout(3500);
    await page.getByRole('button', { name: 'Create my account' }).click();
    await expect(page.locator('.flash')).toContainText('Welcome');

    // Open Green A two days from now (safely outside the cancel cut-off)
    const targetDay = page.locator('.overview-day').nth(2);
    await targetDay.locator('a.green-pill').first().click();
    await expect(page.getByRole('heading', { name: /Green A/ })).toBeVisible();

    // Book the first free slot for two players
    await page.locator('a.slot-free').first().click();
    await expect(page.getByRole('heading', { name: /Book rink/ })).toBeVisible();
    await page.getByLabel('Players').selectOption('2');
    await page.getByLabel(/partner's full name/).fill('Pat Partner');
    await page.getByRole('button', { name: 'Book this rink' }).click();

    // Confirmation with the WhatsApp share
    await expect(page.getByRole('heading', { name: /is yours/ })).toBeVisible();
    await expect(page.getByText('Playing with Pat Partner.')).toBeVisible();
    const share = page.getByRole('link', { name: 'Share on WhatsApp' });
    await expect(share).toBeVisible();
    expect(await share.getAttribute('href')).toContain('https://wa.me/?text=');

    // The slot shows as our own booking on the calendar
    await page.getByRole('link', { name: /Back to Green A/ }).click();
    await expect(page.locator('.slot-own').first()).toContainText('Playwright Tester');

    // My bookings lists it; cancel it
    await page.getByRole('link', { name: 'My bookings' }).first().click();
    await expect(page.getByText('Rink A-')).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Cancel' }).click();
    await expect(page.locator('.flash')).toContainText('cancelled');
    await expect(page.getByText('· cancelled')).toBeVisible();
});

test('a second booking on the same day is refused', async ({ page }, testInfo) => {
    const email = `second-${testInfo.project.name}-${Date.now()}@example.com`;

    await page.goto('/register');
    await page.getByLabel('First name').fill('Second');
    await page.getByLabel('Surname').fill('Member');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('a-good-password');
    await page.getByLabel('Password again').fill('a-good-password');
    await page.getByRole('checkbox', { name: /I accept the/ }).check();
    await page.waitForTimeout(3500);
    await page.getByRole('button', { name: 'Create my account' }).click();
    await expect(page.locator('.flash')).toContainText('Welcome');

    // Book the first free slot on the last overview day
    await page.goto('/');
    const day = page.locator('.overview-day').nth(13);
    await day.locator('a.green-pill').first().click();
    const slot = page.locator('a.slot-free').first();
    const slotUrl = await slot.getAttribute('href');
    await slot.click();
    await page.getByLabel('Players').selectOption('1');
    await page.getByRole('button', { name: 'Book this rink' }).click();
    await expect(page.getByRole('heading', { name: /is yours/ })).toBeVisible();

    // The same slot straight away again: the form refuses it (occupied comes before one-per-day)
    await page.goto(slotUrl);
    await expect(page.getByText('This rink is already booked for this time.')).toBeVisible();
});
