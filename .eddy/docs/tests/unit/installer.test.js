import fs from 'node:fs';
import path from 'node:path';
import installer, { ARCHIVE_SHA256_PLACEHOLDER, ARCHIVE_URL_PLACEHOLDER, VERSION_PLACEHOLDER, describeCheckout, stampInstaller, stampInstallerFile } from '../../src/installer';

const SITE_DIR = path.join(__dirname, '..', '..');

const ROOT_DIR = path.join(SITE_DIR, '..', '..');

const URL = 'https://github.com/drevops/eddy/releases/download/5.0.0/eddy-5.0.0.tar.gz';

const SHA256 = 'a'.repeat(64);

const source = (version = VERSION_PLACEHOLDER, url = ARCHIVE_URL_PLACEHOLDER, sha256 = ARCHIVE_SHA256_PLACEHOLDER) => `<?php\nconst TEMPLATE_VERSION = '${version}';\nconst TEMPLATE_ARCHIVE_URL = '${url}';\nconst TEMPLATE_ARCHIVE_SHA256 = '${sha256}';\n`;

const install = () => fs.readFileSync(path.join(ROOT_DIR, 'install.php'), 'utf8');

describe('stampInstaller', () => {
  test.each(['5.0.0', '5.0.0-alpha4', '5.0.0-3-g1a2b3c4', '1a2b3c4', '5.0.0+build_1'])('writes the version %p into the source', (version) => {
    expect(stampInstaller(source(), version)).toBe(source(version));
  });

  test('keeps the source without a version', () => {
    expect(stampInstaller(source(), '')).toBe(source());
  });

  test.each(["5.0.0'", '5.0.0/1', ' 5.0.0', '5.0.0\n', '$5'])('rejects the version %p', (version) => {
    expect(() => stampInstaller(source(), version)).toThrow(`Invalid installer version "${version}"`);
  });

  test('fails on a source without the placeholder', () => {
    expect(() => stampInstaller(source('5.0.0'), '5.0.1')).toThrow(`install.php holds no "${VERSION_PLACEHOLDER}"`);
  });

  test('writes the version, the archive URL and the archive checksum into the source', () => {
    expect(stampInstaller(source(), '5.0.0', URL, SHA256)).toBe(source('5.0.0', URL, SHA256));
  });

  test('writes the archive without a version', () => {
    expect(stampInstaller(source(), '', URL, SHA256)).toBe(source(VERSION_PLACEHOLDER, URL, SHA256));
  });

  test('writes an archive URL that holds replacement patterns as it is', () => {
    expect(stampInstaller(source(), '5.0.0', 'https://example.com/a&b', SHA256)).toBe(source('5.0.0', 'https://example.com/a&b', SHA256));
  });

  test.each([
    ['an http URL', 'http://example.com/eddy.tar.gz'],
    ['a quote', "https://example.com/eddy'.tar.gz"],
    ['a backslash', 'https://example.com/eddy\\.tar.gz'],
    ['a dollar sign', 'https://example.com/$eddy.tar.gz'],
    ['a space', 'https://example.com/eddy .tar.gz'],
    ['no URL', ''],
  ])('rejects an archive URL with %s', (name, url) => {
    expect(() => stampInstaller(source(), '5.0.0', url, SHA256)).toThrow(`Invalid template archive URL "${url}"`);
  });

  test.each([
    ['uppercase digits', 'A'.repeat(64)],
    ['63 digits', 'a'.repeat(63)],
    ['65 digits', 'a'.repeat(65)],
    ['a non-hexadecimal digit', `${'a'.repeat(63)}g`],
    ['a quote', `${'a'.repeat(63)}'`],
    ['no checksum', ''],
  ])('rejects an archive checksum with %s', (name, sha256) => {
    expect(() => stampInstaller(source(), '5.0.0', URL, sha256)).toThrow(`Invalid template archive checksum "${sha256}"`);
  });

  test.each([
    ['URL', ARCHIVE_URL_PLACEHOLDER, source(VERSION_PLACEHOLDER, URL)],
    ['checksum', ARCHIVE_SHA256_PLACEHOLDER, source(VERSION_PLACEHOLDER, ARCHIVE_URL_PLACEHOLDER, SHA256)],
  ])('fails on a source without the archive %s placeholder', (name, placeholder, input) => {
    expect(() => stampInstaller(input, '5.0.0', URL, SHA256)).toThrow(`install.php holds no "${placeholder}"`);
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

describe('stampInstallerFile', () => {
  let write;

  beforeEach(() => {
    write = jest.spyOn(fs, 'writeFileSync').mockImplementation(() => {});
  });

  afterEach(() => {
    write.mockRestore();
  });

  test('writes the stamped installer to the destination', () => {
    stampInstallerFile(path.join(ROOT_DIR, 'install.php'), '/release/install.php', '5.0.0', URL, SHA256);

    expect(write).toHaveBeenCalledWith('/release/install.php', install().replace(VERSION_PLACEHOLDER, '5.0.0').replace(ARCHIVE_URL_PLACEHOLDER, URL).replace(ARCHIVE_SHA256_PLACEHOLDER, SHA256));
  });

  test.each([
    ['an empty version', ''],
    ['no version', undefined],
  ])('rejects %s', (name, version) => {
    expect(() => stampInstallerFile(path.join(ROOT_DIR, 'install.php'), '/release/install.php', version, URL, SHA256)).toThrow('Pass the release version to stamp into the installer.');
    expect(write).not.toHaveBeenCalled();
  });

  test('rejects an invalid archive', () => {
    expect(() => stampInstallerFile(path.join(ROOT_DIR, 'install.php'), '/release/install.php', '5.0.0', URL, '')).toThrow('Invalid template archive checksum ""');
    expect(write).not.toHaveBeenCalled();
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
    ['options', { release: false }],
    ['no options', undefined],
  ])('serves install.php at /install with the version of the checkout and %s', async (name, options) => {
    await installer({ siteDir: SITE_DIR }, options).postBuild({ outDir: '/site/build' });

    expect(write).toHaveBeenCalledWith(path.join('/site/build', 'install'), install().replace(VERSION_PLACEHOLDER, describeCheckout(ROOT_DIR)));
  });

  test('serves no installer in a release build', async () => {
    await installer({ siteDir: SITE_DIR }, { release: true }).postBuild({ outDir: '/site/build' });

    expect(write).not.toHaveBeenCalled();
  });
});

test.each([VERSION_PLACEHOLDER, ARCHIVE_URL_PLACEHOLDER, ARCHIVE_SHA256_PLACEHOLDER])('install.php holds the placeholder %p once', (placeholder) => {
  expect(install().split(placeholder)).toHaveLength(2);
});
