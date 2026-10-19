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

// Maps the URL of each page to its source. A page lives at the 'slug' of its
// front matter, or else at 'content/<path>.mdx', or at
// 'content/<path>/README.mdx' when it indexes a folder.
const pageSources = () => {
  const files = fs.readdirSync(CONTENT_DIR, { recursive: true }).filter((file) => file.endsWith('.mdx'));

  return Object.fromEntries(files.map((file) => {
    const source = fs.readFileSync(path.join(CONTENT_DIR, file), 'utf8');
    const frontMatter = source.match(/^---\n([\s\S]*?)\n---/)?.[1] ?? '';
    const slug = frontMatter.match(/^slug:\s*(\S+)\s*$/m)?.[1];
    const route = file.split(path.sep).join('/').replace(/(^|\/)README\.mdx$/, '').replace(/\.mdx$/, '');

    return [slug ?? `/${route}`, source];
  }));
};

// Mermaid draws a diagram after hydration and leaves no placeholder before
// it, so the page source gives the number of diagrams to wait for.
const diagramCount = (url) => {
  const source = pageSources()[url.split(/[?#]/)[0]] ?? '';

  return (source.match(/^\s*```mermaid\b/gm) || []).length;
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
