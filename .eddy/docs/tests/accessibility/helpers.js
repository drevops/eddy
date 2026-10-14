import fs from 'node:fs';
import path from 'node:path';
import { expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const BUILD_DIR = path.join(__dirname, '..', '..', 'build');

const CONTENT_DIR = path.join(__dirname, '..', '..', 'content');

// WCAG 2.0, 2.1 and 2.2 at levels A and AA.
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

export const COLOR_MODES = ['light', 'dark'];

// Development builds have no sitemap, so the pages come from the build
// directory: 'development/building.html' is served at '/development/building'.
export const builtPages = () => {
  if (!fs.existsSync(BUILD_DIR)) {
    throw new Error('No build to check: run "npm run build" first.');
  }

  const files = fs.readdirSync(BUILD_DIR, { recursive: true }).map((file) => file.split(path.sep).join('/'));

  return files
    .filter((file) => file.endsWith('.html') && !file.startsWith('assets/'))
    .map((file) => `/${file.replace(/(^|\/)index\.html$/, '$1').replace(/\.html$/, '')}`)
    .sort();
};

// Mermaid draws a diagram after hydration and leaves no placeholder before
// it, so the page source gives the number of diagrams to wait for. A page
// lives at 'content/<path>.mdx', or at 'content/<path>/README.mdx' when it
// indexes a folder.
const diagramCount = (url) => {
  const base = path.join(CONTENT_DIR, url.split(/[?#]/)[0]);
  const source = [`${base}.mdx`, path.join(base, 'README.mdx')].find((file) => fs.existsSync(file));

  return source ? (fs.readFileSync(source, 'utf8').match(/^\s*```mermaid\b/gm) || []).length : 0;
};

// Opens a page in a color mode and waits until it is fully drawn.
export const open = async (page, url, colorMode) => {
  const separator = url.includes('?') ? '&' : '?';

  await page.goto(`${url}${separator}docusaurus-theme=${colorMode}`);

  await expect(page.locator('html')).toHaveAttribute('data-has-hydrated', 'true');
  await expect(page.locator('html')).toHaveAttribute('data-theme', colorMode);
  await expect(page.locator('.docusaurus-mermaid-container > svg')).toHaveCount(diagramCount(url));
};

// Returns 1 line per failing element, so a failed assertion shows the rule,
// the element and the fix.
export const scan = async (page, selector) => {
  const builder = new AxeBuilder({ page }).withTags(WCAG_TAGS);

  if (selector) {
    builder.include(selector);
  }

  const { violations } = await builder.analyze();

  return violations.flatMap((violation) => violation.nodes.map((node) => `${violation.id} at ${node.target.join(' ')}: ${node.failureSummary.replace(/\s+/g, ' ')}`));
};
