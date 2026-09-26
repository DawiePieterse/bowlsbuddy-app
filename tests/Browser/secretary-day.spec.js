// The Secretary's whole day (PLAN.md Phase 4 done-when): close a green, add an event,
// print the day sheet, reset a member's password.
import { test, expect } from '@playwright/test';

function isoDate(daysAhead) {
    const date = new Date(Date.now() + daysAhead * 86_400_000);
    return date.toISOString().slice(0, 10);
}

async function logInAsSecretary(page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('secretary@example.com');
    await page.getByLabel('Password', { exact: true }).fill('e2e-admin-password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page.getByRole('link', { name: 'My bookings' })).toBeVisible();
}

test('the Secretary closes a green, prints the day sheet and reopens it', async ({ page }) => {
    await logInAsSecretary(page);

    // The overview shows the WhatsApp invite for the Secretary
    await expect(page.getByRole('link', { name: 'Invite members via WhatsApp' })).toBeVisible();

    // Close green B four days out (leaving green A free for the booking flows)
    const day = isoDate(4);
    await page.goto(`/greens/B/${day}`);
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Close green B on this day' }).click();
    await expect(page.getByText(/is now closed/)).toBeVisible();
    await expect(page.getByText('Green B is closed on this day.')).toBeVisible();

    // The overview shows it red (closed), and the day sheet prints the closure
    await page.goto('/');
    await expect(page.locator('.green-pill.closed').first()).toBeVisible();

    await page.goto(`/greens/B/${day}/sheet`);
    await expect(page.getByText('day sheet')).toBeVisible();
    await expect(page.getByText('Green B is closed on this day.')).toBeVisible();
    await expect(page.locator('svg').first()).toBeVisible(); // the QR code to live bookings
    await expect(page.getByRole('button', { name: 'Print this sheet' })).toBeVisible();

    // Reopen it
    await page.goto(`/greens/B/${day}`);
    await page.getByRole('button', { name: 'Open green B on this day' }).click();
    await expect(page.getByText(/open again/)).toBeVisible();
});

test('the Secretary adds an event and resets a password in the admin panel', async ({ page }, testInfo) => {
    await logInAsSecretary(page);

    // Add an event blocking green B five days out
    const day = isoDate(5);
    const eventName = `Green B maintenance ${testInfo.project.name}`;
    await page.goto('/admin/events/create');
    await page.getByRole('textbox', { name: 'Name*' }).fill(eventName);
    await page.getByRole('textbox', { name: 'From*' }).fill(`${day}T12:00`);
    await page.getByRole('textbox', { name: 'To*' }).fill(`${day}T17:00`);
    await page.getByLabel('One green').check();
    await page.getByLabel('Green', { exact: true }).selectOption('B');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await page.waitForURL(/admin\/events\/\d+\/edit/);
    await page.goto('/admin/events');
    await expect(page.getByText(eventName).first()).toBeVisible();

    // The member calendar shows the event purple
    await page.goto(`/greens/B/${day}`);
    await expect(page.locator('.slot-event').first()).toContainText('Green B maintenance');

    // Reset a member's password from the members list (the booking-flow tests registered members)
    await page.goto('/admin/members');
    await expect(page.getByRole('heading', { name: 'Members' })).toBeVisible();
    await page.getByRole('searchbox', { name: 'Search', exact: true }).fill('Playwright');
    await expect(page.getByRole('button', { name: 'Set temporary password' }).first()).toBeVisible();
    await page.getByRole('button', { name: 'Set temporary password' }).first().click();
    await page.getByRole('button', { name: 'Confirm' }).click();
    await expect(page.getByText('Temporary password set')).toBeVisible();
    await expect(page.getByText('Give the member this password:')).toBeVisible();
});
