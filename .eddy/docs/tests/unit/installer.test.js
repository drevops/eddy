import fs from 'node:fs';
import path from 'node:path';
import installer, { VERSION_PLACEHOLDER, describeCheckout, stampInstaller } from '../../src/installer';

const SITE_DIR = path.join(__dirname, '..', '..');

const ROOT_DIR = path.join(SITE_DIR, '..', '..');

const SOURCE = `<?php\nconst TEMPLATE_VERSION = '${VERSION_PLACEHOLDER}';\n`;

const install = () => fs.readFileSync(path.join(ROOT_DIR, 'install.php'), 'utf8');

describe('stampInstaller', () => {
  test.each(['5.0.0', '5.0.0-alpha4', '5.0.0-3-g1a2b3c4', '1a2b3c4', '5.0.0+build_1'])('writes the version %p into the source', (version) => {
    expect(stampInstaller(SOURCE, version)).toBe(`<?php\nconst TEMPLATE_VERSION = '${version}';\n`);
  });

  test('keeps the source without a version', () => {
    expect(stampInstaller(SOURCE, '')).toBe(SOURCE);
  });

  test.each(["5.0.0'", '5.0.0/1', ' 5.0.0', '5.0.0\n', '$5'])('rejects the version %p', (version) => {
    expect(() => stampInstaller(SOURCE, version)).toThrow(`Invalid installer version "${version}"`);
  });

  test('fails on a source without the placeholder', () => {
    expect(() => stampInstaller("<?php\nconst TEMPLATE_VERSION = '5.0.0';\n", '5.0.1')).toThrow(`install.php holds no "${VERSION_PLACEHOLDER}"`);
  });
});

describe('describeCheckout', () => {
  test('reads the checkout', () => {
    expect(describeCheckout(ROOT_DIR)).toMatch(/^[\w.+-]+$/);
  });

  test('returns nothing outside a checkout', () => {
    expect(describeCheckout(path.parse(ROOT_DIR).root)).toBe('');
  });
});

describe('installer plugin', () => {
  let write;

  beforeEach(() => {
    write = jest.spyOn(fs, 'writeFileSync').mockImplementation(() => {});
  });

  afterEach(() => {
    write.mockRestore();
  });

  test.each([
    ['the release version', { version: '5.0.0' }, () => '5.0.0'],
    ['the version of the checkout', {}, () => describeCheckout(ROOT_DIR)],
  ])('serves install.php at /install with %s', async (name, options, version) => {
    await installer({ siteDir: SITE_DIR }, options).postBuild({ outDir: '/site/build' });

    expect(write).toHaveBeenCalledWith(path.join('/site/build', 'install'), install().replace(VERSION_PLACEHOLDER, version()));
  });
});

test('install.php holds the version placeholder once', () => {
  expect(install().split(VERSION_PLACEHOLDER)).toHaveLength(2);
});
