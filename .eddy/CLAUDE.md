# Eddy Maintenance

Maintenance guide for the Eddy template itself.

This file documents how `init.php` prunes the template, how to regenerate the scaffold's own artifacts (animated README SVGs, the social preview card, snapshot fixtures) and how to run its self-tests. It does **not** apply to consumer projects produced by running `init.php`.

## Layout

- `.eddy/assets/` - Source files for animated SVG demos used in the root `README.md` (`init.svg`, `build.svg`, `lint.svg`, `test.svg`) plus the `update-assets.php` generator and a small `svg-term` Node wrapper. It also holds the repository's social preview card, `social-preview.png`, and the `social-preview.html` page it's rendered from.
- `.eddy/tests/` - PHPUnit suite that validates the scaffold itself: the `init.php` interactive flow, the tooling commands in `.eddy/tooling/src/` with their installer `scripts/eddy-tooling`, and the resulting project structure. Snapshots live under `.eddy/tests/fixtures/init/`.
- `.eddy/tooling/` - Source of the `drevops/eddy-tooling` Composer package (the `eddy-*` commands). `scripts/eddy-tooling` installs it into `vendor/` as a symlink in this repository, and `scaffold-publish-tooling.yml` mirrors it to the read-only `drevops/eddy-tooling` repository on every push to `1.x` and to a branch whose name contains `eddy-tooling`. Releases are created on the mirror by hand, with notes from the `create-eddy-tooling-release-notes` skill - see `CONTRIBUTING.md`.
- `.eddy/skills/update-consumer-eddy/` - the update skill that consumer projects fetch through the "Updating the scaffold" section of their `AGENTS.md`.
- `.claude/skills/` - Claude Code skills for maintaining the scaffold, such as `create-eddy-tooling-release-notes`. `init.php` removes the directory, so generated projects never get them.

## Template markers

Besides renaming files and replacing placeholders, `init.php` deletes whatever the answers to its prompts rule out: a Drupal major you didn't select, a tool you unchecked, a command wrapper you dropped. Whole files go by name, such as `phpstan.neon` when PHPStan goes. Lines inside the files that stay go through marker blocks.

A block opens with a line holding `#;< TOKEN` and closes with a line holding `#;> TOKEN`, and each marker line uses whatever comment syntax keeps its file valid:

| File                                                                         | Marker line          |
|------------------------------------------------------------------------------|----------------------|
| YAML, NEON, `.gitattributes`, `.editorconfig`, shell code blocks in Markdown | `#;< TOKEN`          |
| Makefile recipe                                                              | `@#;< TOKEN`         |
| Markdown and XML                                                             | `<!-- #;< TOKEN -->` |
| PHP                                                                          | `// #;< TOKEN`       |

In a Makefile recipe the `@` stops `make` from echoing the line, and the shell then reads it as a comment.

For each token it strips, `remove_tokens_with_content()` deletes every block of that token, markers included, from every text file in the project. It walks the whole tree apart from `.git`, `.idea`, `vendor` and `node_modules`, so a block works in any file you put it in. Once the pruning is done, `remove_special_comments()` deletes every remaining line that contains `#;`, which clears the markers of the blocks that stayed.

| Token                                               | Stripped when                                |
|-----------------------------------------------------|----------------------------------------------|
| `DRUPAL_<major>`, such as `DRUPAL_11`               | The major isn't selected                     |
| `DEV_<TOOL>`, the `token` of a `tool_specs()` entry | The tool isn't selected                      |
| `DEV_NODEJS_LINT`                                   | Neither ESLint nor Stylelint is selected     |
| `DEV_AHOY`, `DEV_MAKEFILE`                          | That command wrapper isn't selected          |
| `DEV_COMMAND_WRAPPER`                               | No command wrapper is selected               |
| `DEV_NO_COMMAND_WRAPPER`                            | At least 1 command wrapper is selected       |
| `META`                                              | Always, since it wraps scaffold-only content |

Dropping PHPUnit drops FunctionalJavascript with it, since those tests run on PHPUnit.

Blocks of different tokens can nest, because `init.php` strips 1 token at a time: a `DEV_FUNCTIONAL_JAVASCRIPT` block inside a `DEV_MAKEFILE` block goes when either token is stripped.

### Rules for a block

A broken block fails quietly. Every file stays valid, and `composer update-snapshots` records the broken output as the new baseline, so these rules matter more than they look:

- **Give each marker a line of its own.** `remove_special_comments()` deletes any line containing `#;`, so content that shares a line with a marker never reaches a generated project, and neither does a `#;` in ordinary content.
- **Close a block before you open the same token again.** `remove_tokens_with_content()` skips a file that has no `#;> TOKEN` at all, so an unclosed block there ships in full. In a file that does close the token somewhere, the unclosed block runs on to the next `#;> TOKEN` or the end of the file and takes everything in between with it.
- **Use a token `init.php` strips.** A misspelled or unknown token is never stripped, so its block ships in every generated project, minus the marker lines.
- **Don't start a token with another token's name.** Markers match as substrings, so stripping `DEV_PHPCS` would also strip a `DEV_PHPCS_FIXER` block.
- **Wrap a new Drupal major everywhere the other majors are wrapped.** A selected major keeps only the blocks that exist for it, so a missing one leaves a gap, such as a project with Drupal 13 CI jobs but no Drupal 13 badge.

`TemplateMarkersTest` checks all 5 rules across the template.

### Adding a Drupal major

1. In `init.php`, add the major to `drupal_version_options()` and to the `INIT_DRUPAL_VERSION` entry of `print_help()`.
2. Add a `DRUPAL_<major>` block wherever the existing majors have one. `git grep -n '#;< DRUPAL_'` lists them: today that's the `lint` and `test` matrices in `.github/workflows/test.yml` and the badges in `README.dist.md`.
3. To have the major start checked, add it to `drupal_version_default()` and update the defaults that `print_help()` and `README.md` name. Then set `extra.eddy.drupal-version` in `composer.dev.json` to the highest major `drupal_version_default()` returns: `InitHelpersTest` fails until they agree.
4. Review `.eddy/tooling/src/eddy-assemble`. It picks the PHPUnit, `symfony/phpunit-bridge` and Rector constraints by major, and it uses `phpunit.d<major>.xml` in place of `phpunit.xml` when that file exists.
5. Update `README.md` where it lists the majors (the badges, the CI job table and the branch protection table) and the matrix line in `AGENTS.md`.
6. Update the expected majors in `InitHelpersTest` and the Drupal data providers in `InitProcessTest`, add a `d<major>_only` dataset to `InitTest`, then regenerate the snapshot fixtures.

### Adding a development tool

1. In `init.php`, add the tool to `$tool_options` in `main()` and to the `INIT_TOOLS` entry of `print_help()`.
2. Give it a `tool_specs()` entry: its `DEV_<TOOL>` token plus the files, directories and `composer.dev.json` entries that go with it. Its npm packages and scripts, if it has any, go in `npm_specs()`.
3. Wrap every line you add for the tool in a `DEV_<TOOL>` block, wherever it lands: the command wrappers, `.github/workflows/test.yml`, configuration files such as `.gitattributes` and `.editorconfig`, and the docs in `AGENTS.md`, `README.dist.md` and `CONTRIBUTING.dist.md`. A `CI_IS_<TOOL>_RUNNER` flag in `test.yml` belongs inside the block too, and `CiRunnerVariablesTest` checks it's there.
4. If the tool runs through the shared `npm run lint` step, add it to the condition in `remove_tools()` that strips `DEV_NODEJS_LINT`, and add its sub-scripts to the `rebuild_npm_chain()` calls in `remove_npm()`.
5. Document it in `README.md`: the feature list, and the ignore-failure table when its CI step has a `CI_<TOOL>_IGNORE_FAILURE` variable.
6. Add the tool to `dataProviderProcessRemovesTools()` and `dataProviderProcessRemovesGitattributes()` in `InitProcessTest`. In `InitTest`, add it to every `tools` answer and add a `no_<tool>` dataset, then regenerate the snapshot fixtures.

## Test groups

PHPUnit tests are tagged with `#[Group('p0'..'p5')]` so CI can shard them across parallel jobs:

- `p0` - Unit tests in `src/Unit/` (no I/O bound dependencies).
- `p1` - `InitTest` (snapshot comparison of `init.php` output).
- `p2` - `AssembleTest` (Drupal codebase assembly) and `ToolingInstallerTest` (the tooling installer driving a real Composer).
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

**HARD RULE - never edit fixtures directly.** Files under `.eddy/tests/fixtures/init/` are generated artifacts. They must always be regenerated with the `update-snapshots` Composer script - run from inside `.eddy/tests` (see below) - after any source change that affects `init.php` output. Hand-editing a fixture risks drift between what the generator would produce and what is checked in - subsequent regenerations would then overwrite the manual edit and the failure mode would only surface in CI.

`InitTest` runs `init.php` end-to-end and diffs the output against `fixtures/init/_baseline/` plus one fixture directory per dataset (`gha_makefile/`, `theme/`, etc. - see `InitTest::dataProviderInit()`).

When source files change (workflows, `scripts/`, `composer.dev.json`, `init.php`, Claude settings, etc.), the fixtures fall out of date. Regenerate them. `.eddy/tooling/` is not part of a generated project, so a change confined to it needs no regeneration.

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

The trait that drives the diff-and-update behavior is `SnapshotTrait` (see `tearDown()` in `InitTest`); it calls `snapshotUpdateOnFailure()` so a normal `test` run will also rewrite fixtures if you have not used the dedicated `update-snapshots` command.

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

`UpdateAssetsTest` replays 2 real recordings of each session from `.eddy/tests/fixtures/assets/first/` and `.../second/` and expects them to render identically. They're plain copies of `.artifacts/tmp/asciinema/*.cast` from 2 back-to-back runs with `SCRIPT_KEEP_CASTS=1`, with the header reduced to `{"version":3,"term":{"cols":80,"rows":24}}`, the workspace path replaced with `/var/folders/aa/T/eddy-assets-111111111111` (first) or `/var/folders/aa/T/eddy-assets-222222222222` (second), and `$HOME` replaced with `/Users/maintainer`. Refresh them the same way when a change to the recordings makes the old ones unrepresentative.

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

`.github/workflows/scaffold-test.yml` runs the suite across the `p0`-`p5` groups on Ubuntu and macOS, runs `p3` and `p4` once per WebDriver backend (Selenium on Ubuntu only, chromedriver on both), and validates `composer.json` and the tooling package's `composer.json` (validate + normalize) plus the PHP lint step in `p0`. A second job (`scaffold-test-actions`) lints the workflow YAML with `yamllint` and `actionlint` and checks it for security issues with Zizmor.

`.github/workflows/scaffold-publish-tooling.yml` publishes `.eddy/tooling/` to the same-named branch of `drevops/eddy-tooling` on every push to `1.x` and to a branch whose name contains `eddy-tooling`.
