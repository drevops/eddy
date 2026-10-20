import { expect, test } from '@playwright/test';
import { COLOR_MODES, open, scan } from './helpers';

const MOBILE = { width: 390, height: 844 };
const DEMO_URL = '/development/building';
const DEMO_NAME = 'ahoy build assembling the codebase, starting the webserver and installing Drupal in a terminal';

test.describe('search', () => {
  for (const colorMode of COLOR_MODES) {
    test(`dropdown in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/', colorMode);

      await page.locator('.navbar__search-input').click();
      await page.keyboard.type('webserver');
      await expect(page.getByRole('option').first()).toBeVisible();

      expect(await scan(page, '.navbar')).toEqual([]);
    });

    test(`dropdown without results in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/', colorMode);

      await page.locator('.navbar__search-input').click();
      await page.keyboard.type('zzqxv');
      await expect(page.getByText('No results')).toBeVisible();

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

  test('moves keyboard focus inside, keeps it there and closes on Escape', async ({ page }) => {
    await open(page, '/', 'light');

    const toggle = page.getByRole('button', { name: 'Toggle navigation bar' });
    const sidebar = page.locator('.navbar-sidebar');
    const focusIn = () => page.evaluate(() => {
      if (document.activeElement === document.body) {
        return 'body';
      }

      return document.activeElement.closest('.navbar-sidebar') ? 'menu' : 'outside';
    });

    await toggle.focus();
    await page.keyboard.press('Enter');

    await expect.poll(focusIn).toBe('menu');

    const controls = await sidebar.evaluate((element) => Array.from(element.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')).filter((control) => !control.closest('[inert]') && control.checkVisibility({ visibilityProperty: true })).length);
    const stops = [];

    // 2 presses more than the menu has controls, so focus wraps around.
    for (let press = 0; press < controls + 2; press++) {
      await page.keyboard.press('Tab');
      stops.push(await focusIn());
    }

    // Tab past the last control leaves the page for 1 press before it wraps.
    expect(stops).not.toContain('outside');
    expect(stops.filter((stop) => stop === 'body').length).toBeLessThanOrEqual(1);
    expect(stops.slice(-2)).not.toContain('body');

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

  // The home page tints the navbar, and the menu opens inside it.
  for (const url of ['/', DEMO_URL]) {
    for (const colorMode of COLOR_MODES) {
      test(`open on ${url} in ${colorMode} mode`, async ({ page }) => {
        await open(page, url, colorMode);

        await page.getByRole('button', { name: 'Toggle navigation bar' }).click();

        // The menu fades in, and a half-transparent menu fails on contrast.
        await expect(page.locator('.navbar-sidebar')).toHaveCSS('opacity', '1');

        expect(await scan(page, '.navbar-sidebar')).toEqual([]);
      });
    }
  }
});

test.describe('animated demo', () => {
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

test.describe('home page', () => {
  const INSTALL_COMMAND = 'curl -fsSL https://eddy.drevops.com/install > install.php && php install.php';

  test('pauses each demo on its last frame and plays it again', async ({ page }) => {
    await open(page, '/', 'light');

    const demos = page.locator('main img');

    await expect(demos).toHaveCount(4);

    for (const demo of await demos.all()) {
      const wrapper = demo.locator('xpath=..');

      await wrapper.getByRole('button', { name: 'Pause animation' }).click();
      await expect(demo).toHaveAttribute('src', /^blob:/);
      await expect.poll(() => demo.evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);

      await wrapper.getByRole('button', { name: 'Play animation' }).click();
      await expect(demo).toHaveAttribute('src', /\/img\/demos\/\w+\.svg$/);
    }
  });

  test('copies the install command', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await open(page, '/', 'light');

    await page.getByRole('button', { name: 'Copy the install command' }).first().click();

    await expect(page.getByRole('status').filter({ hasText: 'Copied' })).toHaveText('Copied the install command to the clipboard.');
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(INSTALL_COMMAND);
  });

  for (const colorMode of COLOR_MODES) {
    test(`passes once every block has played its entrance in ${colorMode} mode`, async ({ page }) => {
      await open(page, '/', colorMode);

      // Scrolls down in steps, so every block crosses the viewport.
      await page.evaluate(async () => {
        for (let top = 0; top < document.body.scrollHeight; top += 300) {
          window.scrollTo(0, top);
          await new Promise((resolve) => {
            setTimeout(resolve, 50);
          });
        }
      });

      await expect(page.locator('[data-revealed="false"]')).toHaveCount(0);

      // A looping animation never finishes, so only the animations that end
      // are waited for.
      await page.evaluate(() => Promise.all(document.getAnimations().filter((animation) => animation.effect.getComputedTiming().endTime !== Infinity).map((animation) => animation.finished)));

      expect(await scan(page)).toEqual([]);
    });
  }
});
