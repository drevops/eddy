import { expect, test } from '@playwright/test';
import { COLOR_MODES, open, scan } from './helpers';

const MOBILE = { width: 390, height: 844 };
const DEMO_URL = '/development/building';
const DEMO_NAME = 'Building the site';

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

  for (const colorMode of COLOR_MODES) {
    test(`open in ${colorMode} mode`, async ({ page }) => {
      await open(page, DEMO_URL, colorMode);

      await page.getByRole('button', { name: 'Toggle navigation bar' }).click();

      // The menu fades in, and a half-transparent menu fails on contrast.
      await expect(page.locator('.navbar-sidebar')).toHaveCSS('opacity', '1');

      expect(await scan(page, '.navbar-sidebar')).toEqual([]);
    });
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

  test('switches the demo from the keyboard', async ({ page }) => {
    await open(page, '/', 'light');

    const tabs = page.getByRole('tablist', { name: 'Demos' });

    await tabs.getByRole('tab', { name: 'install' }).focus();
    await page.keyboard.press('ArrowRight');

    await expect(tabs.getByRole('tab', { name: 'build' })).toBeFocused();
    await expect(tabs.getByRole('tab', { name: 'build' })).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByRole('tabpanel').locator('img')).toHaveAttribute('src', /\/img\/demos\/build\.svg$/);
  });

  test('pauses the demo on its last frame and plays it again', async ({ page }) => {
    await open(page, '/', 'light');

    const demo = page.getByRole('tabpanel').locator('img');

    await page.getByRole('button', { name: 'Pause the demo' }).click();
    await expect(demo).toHaveAttribute('src', /^blob:/);
    await expect.poll(() => demo.evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);

    await page.getByRole('button', { name: 'Play the demo' }).click();
    await expect(demo).toHaveAttribute('src', /\/img\/demos\/install\.svg$/);
  });

  test('pauses the tool list from the keyboard and plays it again', async ({ page }) => {
    await open(page, '/', 'light');

    const row = page.getByRole('group', { name: 'Tools and services that Eddy sets up' }).getByRole('list').first().locator('..');

    // A pointer over the list pauses it too, so the keyboard drives the button.
    await page.getByRole('button', { name: 'Pause the tool list' }).focus();
    await page.keyboard.press('Enter');
    await expect(row).toHaveCSS('animation-play-state', 'paused');

    await page.keyboard.press('Enter');
    await expect(page.getByRole('button', { name: 'Play the tool list' })).toHaveCount(0);
    await expect(row).toHaveCSS('animation-play-state', 'running');
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

      // The tool list loops forever, so only the animations that end are
      // waited for.
      await page.evaluate(() => Promise.all(document.getAnimations().filter((animation) => animation.effect.getComputedTiming().endTime !== Infinity).map((animation) => animation.finished)));

      expect(await scan(page)).toEqual([]);
    });
  }
});
