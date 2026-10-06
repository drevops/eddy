# Eddy Maintenance

Maintenance guide for the Eddy template itself.

This file documents how to regenerate the scaffold's own artefacts (animated README SVGs, the social preview card, snapshot fixtures) and how to run its self-tests. It does **not** apply to consumer projects produced by running `init.php`.

## Layout

- `.eddy/assets/` - Source files for animated SVG demos used in the root `README.md` (`init.svg`, `build.svg`, `lint.svg`, `test.svg`) plus the `update-assets.php` generator and a small `svg-term` Node wrapper. It also holds the repository's social preview card, `social-preview.png`, and the `social-preview.html` page it's rendered from.
- `.eddy/tests/` - PHPUnit suite that validates the scaffold itself: the `init.php` interactive flow, the `.devtools/*` PHP helpers, and the resulting project structure. Snapshots live under `.eddy/tests/fixtures/init/`.
- `.eddy/skills/update-consumer-eddy/` - the update skill that consumer projects fetch through the "Updating the scaffold" section of their `AGENTS.md`.

## Test groups

PHPUnit tests are tagged with `#[Group('p0'..'p5')]` so CI can shard them across parallel jobs:

- `p0` - Unit tests in `src/Unit/` (no I/O bound dependencies).
- `p1` - `InitTest` (snapshot comparison of `init.php` output).
- `p2` - `AssembleTest` (Drupal codebase assembly).
- `p3` - `AhoyTest`, `AutoPortDiscoveryTest` (Ahoy command wrapper).
- `p4` - `MakeTest` (Makefile command wrapper).
- `p5` - `XdebugTest` (XDebug step-debugging toggle). This is the only group whose CI runner installs the xdebug PHP extension via `coverage: xdebug` instead of pcov - the test exercises the real extension end to end, so coverage is not collected for this group.

**Do not DRY the per-wrapper tests.** `AhoyTest` (p3) and `MakeTest` (p4) are intentionally kept as separate, near-parallel test classes - one per command wrapper. Never merge or parameterize them into a single shared class: each wrapper (`ahoy`, `make`) must be exercised by its own independent test so a regression in one runner can never be masked by the other. `XdebugTest`'s single parameterized `assertToggle()` is a deliberate exception for one feature toggled through both wrappers, not a pattern to extend across the whole command surface.

## Running the tests

All commands run from the repository root. Install dependencies once with `composer --working-dir=.eddy/tests install`.

| Action                        | Command                                                          |
|-------------------------------|------------------------------------------------------------------|
| All tests                     | `composer --working-dir=.eddy/tests test`                        |
| Single group                  | `composer --working-dir=.eddy/tests test -- --group=p0`          |
| Single test class             | `composer --working-dir=.eddy/tests test -- --filter=InitTest`   |
| With coverage                 | `composer --working-dir=.eddy/tests test-coverage -- --group=p0` |
| Lint (phpcs, phpstan, rector) | `composer --working-dir=.eddy/tests lint`                        |
| Lint autofix                  | `composer --working-dir=.eddy/tests lint-fix`                    |

`p2`-`p5` exercise the full build pipeline and need a Drupal-friendly PHP setup; `p3` and `p4` also need a WebDriver backend: a Selenium container or a local Chrome driven by chromedriver. These are the same jobs the GitHub Actions matrix runs (`.github/workflows/scaffold-test.yml`).

## Regenerating snapshot fixtures

**HARD RULE - never edit fixtures directly.** Files under `.eddy/tests/fixtures/init/` are generated artefacts. They must always be regenerated with the `update-snapshots` Composer script - run from inside `.eddy/tests` (see below) - after any source change that affects `init.php` output. Hand-editing a fixture risks drift between what the generator would produce and what is checked in - subsequent regenerations would then overwrite the manual edit and the failure mode would only surface in CI.

`InitTest` runs `init.php` end-to-end and diffs the output against `fixtures/init/_baseline/` plus one fixture directory per dataset (`gha_makefile/`, `theme/`, etc. - see `InitTest::dataProviderInit()`).

When source files change (workflows, `.devtools/`, `init.php`, Claude settings, etc.), the fixtures fall out of date. Regenerate them.

**HARD RULE - regenerate snapshots in this exact order. Never manipulate `TMPDIR` and never pass `--jobs`.**

1. Commit your current source changes first (as their own commit).
2. `cd` into `.eddy/tests`.
3. Run `composer update-snapshots`.

```bash
cd .eddy/tests
composer update-snapshots
```

Committing the source first matters because `update-snapshots` stages, commits, and amends the fixture diffs via `git`: with the source already in its own commit, the regenerated fixtures land in a separate, clean commit on top instead of mixing the two changesets.

This wraps `vendor/bin/update-snapshots` from `alexskrypnyk/snapshot`. It:

1. Runs the baseline dataset first and commits any baseline diff as its own commit.
2. Runs the remaining datasets and amends the baseline commit with each fixture diff.
3. Exits non-zero on the first run because the original tests failed against the stale snapshots - that is expected; the snapshots are now correct.

After it finishes, `git show --stat` the resulting commit to confirm it touches only the files your change should have affected, then run `composer test -- --filter=InitTest` from inside `.eddy/tests` to confirm everything is green before pushing.

The trait that drives the diff-and-update behaviour is `SnapshotTrait` (see `tearDown()` in `InitTest`); it calls `snapshotUpdateOnFailure()` so a normal `test` run will also rewrite fixtures if you have not used the dedicated `update-snapshots` command.

## Regenerating animated SVG assets

The README demo SVGs are produced from real terminal recordings:

```bash
composer --working-dir=.eddy/tests update-assets
```

This invokes `php .eddy/assets/update-assets.php`, which:

1. Exports the committed `HEAD` into a clean workspace in the system temp directory (so commit your changes first), and installs `svg-term` via `npm install --prefix .eddy/assets`.
2. Records `php init.php`, `ahoy build`, `ahoy lint` and `ahoy test` one after another with `asciinema` + `expect`, each typed at a shell prompt in the workspace the previous recordings left behind.
3. Rewrites each recording onto a canonical timeline, converts it to an animated SVG via `node .eddy/assets/svg-term-render.js`, and writes `init.svg`, `build.svg`, `lint.svg` and `test.svg` into `.eddy/assets/`.

Regeneration is reproducible: recording the same session twice produces the same bytes, so `git status` after a run is the check. A clean tree means the rendering didn't change, and any diff is a real change worth reading. 3 things make that hold:

- Frames are cut where the session's own output defines them (1 per typed character, 1 per widget redraw in `init`, 1 per line of command output) rather than wherever the terminal happened to split a write.
- Every gap becomes 1 of 2 fixed durations, a step or a frame within a step, so the recording machine's timing never reaches the SVG.
- Values that change on every run, such as timings, random IDs, the one-time login link and the workspace path, are replaced with fixed ones.

The recordings still show what the recording machine has installed (PHP, PHPUnit, the resolved Drupal release) and what its environment changes, such as whether `GITHUB_TOKEN` is set.

Pass asset names to render only those, for example `php .eddy/assets/update-assets.php lint`. Every recording up to the last named one still runs, because each one prepares the workspace for the next.

Required tools: `asciinema` 3, `expect`, `node`, `npm`. The script checks for these and aborts if any are missing.

Set `SCRIPT_QUIET=1` to suppress verbose progress messages. The recordings go to `.artifacts/tmp/asciinema`: they're removed after a successful run unless `SCRIPT_KEEP_CASTS=1` is set, and kept after a failed one.

## Regenerating the social preview

`social-preview.png` is the card GitHub shows when someone shares a link to the repository. It's a 1280x640 screenshot of `social-preview.html`, a self-contained page that draws the logo inline and takes its fonts from the local system, so install the 2 fonts its `@font-face` rules name before rendering it.

To change the card, edit the HTML, then render it at exactly 1280x640 with any headless browser, for example `agent-browser`:

```bash
agent-browser set viewport 1280 640
agent-browser open "file://$PWD/.eddy/assets/social-preview.html"
agent-browser screenshot "$PWD/.eddy/assets/social-preview.png"
```

GitHub has no API for the social preview, so upload the new PNG by hand in the repository's **Settings > General > Social preview**.

## CI

`.github/workflows/scaffold-test.yml` runs the suite across the `p0`-`p5` groups on Ubuntu and macOS, runs `p3` and `p4` once per WebDriver backend (Selenium on Ubuntu only, chromedriver on both), and validates `composer.json` (validate + normalize) plus the PHP lint step in `p0`. A second job (`scaffold-test-actions`) lints the workflow YAML with `yamllint` and `actionlint` and checks it for security issues with Zizmor.
