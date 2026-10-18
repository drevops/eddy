import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

// 'git archive' expands this placeholder in the 'install.php' of a release
// download, and the build replaces it in the copy it serves.
export const VERSION_PLACEHOLDER = '$Format:%(describe:tags=true)$';

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

// Returns the source of 'install.php' with a version in place of the
// placeholder. An empty version leaves the source as it is.
export const stampInstaller = (source, version) => {
  if (version === '') {
    return source;
  }

  // The version becomes a PHP string literal, so it holds no quote.
  if (!/^[\w.+-]+$/.test(version)) {
    throw new Error(`Invalid installer version "${version}": use letters, digits, ".", "+", "-" and "_".`);
  }

  if (!source.includes(VERSION_PLACEHOLDER)) {
    throw new Error(`install.php holds no "${VERSION_PLACEHOLDER}" to replace with the version.`);
  }

  return source.replace(VERSION_PLACEHOLDER, version);
};

// Serves the root 'install.php' at '/install' with the version of the build:
// the 'version' option, or the 'git describe' output of the checkout.
export default function installerPlugin(context, options = {}) {
  const root = path.resolve(context.siteDir, '..', '..');

  return {
    name: 'eddy-installer',
    async postBuild({ outDir }) {
      const source = fs.readFileSync(path.join(root, 'install.php'), 'utf8');
      const version = options.version || describeCheckout(root);

      fs.writeFileSync(path.join(outDir, 'install'), stampInstaller(source, version));
    },
  };
}
