/**
 * @file
 * Write a copy of 'install.php' stamped with a release version and the URL
 * and SHA-256 checksum of its template archive.
 *
 * Usage:
 *   node stamp-installer.mjs <source> <destination> <version> <archive-url> <archive-sha256>
 */

import { stampInstallerFile } from '../src/installer.js';

const args = process.argv.slice(2);

if (args.length !== 5) {
  console.error('Usage: node stamp-installer.mjs <source> <destination> <version> <archive-url> <archive-sha256>');
  process.exit(1);
}

stampInstallerFile(...args);
