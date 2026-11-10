import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

// 'git archive' expands this placeholder in the 'install.php' of a release
// download, and the build replaces it in the copy it serves.
export const VERSION_PLACEHOLDER = '$Format:%(describe:tags=true)$';

// The release workflow replaces these with the template archive of the
// release and its checksum, which the installer verifies before it extracts
// the archive.
export const ARCHIVE_URL_PLACEHOLDER = '__EDDY_TEMPLATE_ARCHIVE_URL__';

export const ARCHIVE_SHA256_PLACEHOLDER = '__EDDY_TEMPLATE_ARCHIVE_SHA256__';

// Returns the 'git describe' output of the checkout that holds a directory,
// or '' outside a checkout.
export const describeCheckout = (dir) => {
  try {
    return execFileSync('git', ['describe', '--tags', '--always'], { cwd: dir, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  }
  catch {
    return '';
  }
};

const replacePlaceholder = (source, placeholder, value, name) => {
  if (!source.includes(placeholder)) {
    throw new Error(`install.php holds no "${placeholder}" to replace with the ${name}.`);
  }

  return source.replace(placeholder, () => value);
};

// Returns the source of 'install.php' with a version, an archive URL and the
// archive checksum in place of their placeholders. An empty version leaves
// its placeholder, and so does an empty archive.
export const stampInstaller = (source, version, archiveUrl = '', archiveSha256 = '') => {
  let stamped = source;

  if (version !== '') {
    // The version becomes a PHP string literal, so it holds no quote.
    if (!/^[\w.+-]+$/.test(version)) {
      throw new Error(`Invalid installer version "${version}": use letters, digits, ".", "+", "-" and "_".`);
    }

    stamped = replacePlaceholder(stamped, VERSION_PLACEHOLDER, version, 'version');
  }

  if (archiveUrl === '' && archiveSha256 === '') {
    return stamped;
  }

  // Both become PHP string literals, so neither holds a quote, a backslash or
  // a dollar sign.
  if (!/^https:\/\/[\w.~:/?#@!&+,;=%-]+$/.test(archiveUrl)) {
    throw new Error(`Invalid template archive URL "${archiveUrl}": use an https URL without quotes, backslashes or dollar signs.`);
  }

  if (!/^[0-9a-f]{64}$/.test(archiveSha256)) {
    throw new Error(`Invalid template archive checksum "${archiveSha256}": use the SHA-256 checksum as 64 lowercase hexadecimal digits.`);
  }

  stamped = replacePlaceholder(stamped, ARCHIVE_URL_PLACEHOLDER, archiveUrl, 'archive URL');

  return replacePlaceholder(stamped, ARCHIVE_SHA256_PLACEHOLDER, archiveSha256, 'archive checksum');
};

// Writes a stamped copy of the installer at 'source' to 'destination'. A
// release version is required, so the copy never keeps the placeholder.
export const stampInstallerFile = (source, destination, version, archiveUrl, archiveSha256) => {
  if (!version) {
    throw new Error('Pass the release version to stamp into the installer.');
  }

  fs.writeFileSync(destination, stampInstaller(fs.readFileSync(source, 'utf8'), version, archiveUrl, archiveSha256));
};

// Serves the root 'install.php' at '/install' with the 'git describe' output
// of the checkout as its version. With the 'release' option, the build serves
// no installer.
export default function installerPlugin(context, options = {}) {
  const root = path.resolve(context.siteDir, '..', '..');

  return {
    name: 'eddy-installer',
    async postBuild({ outDir }) {
      if (options.release) {
        return;
      }

      const source = fs.readFileSync(path.join(root, 'install.php'), 'utf8');

      fs.writeFileSync(path.join(outDir, 'install'), stampInstaller(source, describeCheckout(root)));
    },
  };
}
