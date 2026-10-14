import { expect, test } from '@playwright/test';
import { COLOR_MODES, open, scan } from './helpers';

const MOBILE = { width: 390, height: 844 };

test.describe('search', () => {
  for (const colorMode of COLOR_MODES) {
    test(`dropdown in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/', colorMode);

      await page.locator('.navbar__search-input').click();
      await page.keyboard.type('webserver');
      await expect(page.getByRole('option').first()).toBeVisible();

      expect(await scan(page, '.navbar')).toEqual([]);
    });

    test(`highlighted result in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/development/webserver?_highlight=webserver', colorMode);
      await expect(page.locator('article mark').first()).toBeVisible();

      expect(await scan(page)).toEqual([]);
    });
  }
});

test.describe('mobile menu', () => {
  test.use({ viewport: MOBILE });

  test('keeps keyboard focus inside and closes on Escape', async ({ page }) => {
    await open(page, '/', 'light');

    const toggle = page.getByRole('button', { name: 'Toggle navigation bar' });
    const sidebar = page.locator('.navbar-sidebar');

    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(sidebar).toBeVisible();

    // More presses than the menu has controls, so focus wraps around at
    // least once.
    for (let press = 0; press < 20; press++) {
      const outside = await page.evaluate(() => document.activeElement !== document.body && !document.activeElement.closest('.navbar-sidebar'));

      expect(outside, `Tab press ${press} left focus outside the menu`).toBe(false);

      await page.keyboard.press('Tab');
    }

    await page.keyboard.press('Escape');

    await expect(sidebar).toBeHidden();
    await expect(toggle).toBeFocused();
  });

  test('closes on a click outside', async ({ page }) => {
    await open(page, '/', 'light');

    await page.getByRole('button', { name: 'Toggle navigation bar' }).click();
    await expect(page.locator('.navbar-sidebar')).toBeVisible();

    await page.mouse.click(MOBILE.width - 10, MOBILE.height / 2);

    await expect(page.locator('.navbar-sidebar')).toBeHidden();
  });

  for (const colorMode of COLOR_MODES) {
    test(`open in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/development/building', colorMode);

      await page.getByRole('button', { name: 'Toggle navigation bar' }).click();

      // The menu fades in, and a half-transparent menu fails on contrast.
      await expect(page.locator('.navbar-sidebar')).toHaveCSS('opacity', '1');

      expect(await scan(page, '.navbar-sidebar')).toEqual([]);
    });
  }
});

test.describe('animated demo', () => {
  const DEMO_URL = '/development/building';
  const DEMO_NAME = 'Building the site';

  test('pauses on its last frame and plays again', async ({ page }) => {
    await open(page, DEMO_URL, 'light');

    const demo = page.getByRole('img', { name: DEMO_NAME });

    await page.getByRole('button', { name: 'Pause animation' }).click();
    await expect(demo).toHaveAttribute('src', /^blob:/);
    await expect.poll(() => demo.evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);

    await page.getByRole('button', { name: 'Play animation' }).click();
    await expect(demo).toHaveAttribute('src', /\.svg$/);
  });

  test.describe('with reduced motion', () => {
    test.use({ reducedMotion: 'reduce' });

    test('starts paused', async ({ page }) => {
      await open(page, DEMO_URL, 'light');

      await expect(page.getByRole('button', { name: 'Play animation' })).toBeVisible();
      await expect(page.getByRole('img', { name: DEMO_NAME })).toHaveAttribute('src', /^blob:/);
    });
  });
});
