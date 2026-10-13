# Eddy Maintenance

Maintenance guide for the Eddy template itself.

This file documents how `install.php` prunes the template, how to regenerate the scaffold's own artifacts (animated README SVGs, the social preview card, snapshot fixtures) and how to run its self-tests. It does **not** apply to consumer projects produced by running `install.php`.

## Layout

- `.eddy/assets/` - Source files for animated SVG demos used in the root `README.md` (`install.svg`, `build.svg`, `lint.svg`, `test.svg`) plus the `update-assets.php` generator and a small `svg-term` Node wrapper. It also holds the repository's social preview card, `social-preview.png`, and the `social-preview.html` page it's rendered from.
- `.eddy/tests/` - PHPUnit suite that validates the scaffold itself: the `install.php` interactive flow, the tooling commands in `.eddy/tooling/src/` with their installer `scripts/eddy-tooling`, and the resulting project structure. Snapshots live under `.eddy/tests/fixtures/install/`.
- `.eddy/tooling/` - Source of the `drevops/eddy-tooling` Composer package (the `eddy-*` commands). `scripts/eddy-tooling` installs it into `vendor/` as a symlink in this repository, and `eddy-publish-tooling.yml` mirrors it to the read-only `drevops/eddy-tooling` repository on every push to `main` and to a branch whose name contains `eddy-tooling`. Releases are created on the mirror by hand, with notes from the `create-eddy-tooling-release-notes` skill - see `CONTRIBUTING.md`.
- `.eddy/skills/update-consumer-eddy/` - the update skill that consumer projects fetch through the "Updating the scaffold" section of their `AGENTS.md`.
- `.claude/skills/` - Claude Code skills for maintaining the scaffold, such as `create-eddy-tooling-release-notes`. `install.php` removes each one by name, as listed in `eddy_skills()`, so generated projects never get them, and keeps any other skill, such as a project's own when `install.php` runs again in the root of an existing project. A new skill goes into `eddy_skills()` as well: `InstallHelpersTest` fails until every tracked skill is listed.

## Template markers

Besides renaming files and replacing placeholders, `install.php` deletes whatever the answers to its prompts rule out: a Drupal major you didn't select, a tool you unchecked, a command wrapper you dropped. Whole files go by name, such as `phpstan.neon` when PHPStan goes. Lines inside the files that stay go through marker blocks.

A block opens with a line holding `#;< TOKEN` and closes with a line holding `#;> TOKEN`, and each marker line uses whatever comment syntax keeps its file valid:

| File                                                                         | Marker line          |
|------------------------------------------------------------------------------|----------------------|
| YAML, NEON, `.gitattributes`, `.editorconfig`, shell code blocks in Markdown | `#;< TOKEN`          |
| Makefile recipe                                                              | `@#;< TOKEN`         |
| Markdown and XML                                                             | `<!-- #;< TOKEN -->` |
| PHP                                                                          | `// #;< TOKEN`       |

In a Makefile recipe the `@` stops `make` from echoing the line, and the shell then reads it as a comment.

For each token it strips, `remove_tokens_with_content()` deletes every block of that token, markers included, from every text file in the project. It walks the whole tree apart from `.git`, `.idea`, `.chromedriver`, `vendor` and `node_modules`, the paths the root `.gitignore` ignores, and everything in `.claude` except `settings.json`, so a block works in any other file you put it in. The replacements and the marker-line cleanup walk the same files. Once the pruning is done, `remove_special_comments()` deletes every remaining line that contains `#;`, which clears the markers of the blocks that stayed.

| Token                                               | Stripped when                                |
|-----------------------------------------------------|----------------------------------------------|
| `DRUPAL_<major>`, such as `DRUPAL_11`               | The major isn't selected                     |
| `DEV_<TOOL>`, the `token` of a `tool_specs()` entry | The tool isn't selected                      |
| `DEV_NODEJS_LINT`                                   | Neither ESLint nor Stylelint is selected     |
| `DEV_NPM`                                           | No tool from `npm_specs()` is selected       |
| `DEV_AHOY`, `DEV_MAKEFILE`                          | That command wrapper isn't selected          |
| `DEV_COMMAND_WRAPPER`                               | No command wrapper is selected               |
| `DEV_NO_COMMAND_WRAPPER`                            | At least 1 command wrapper is selected       |
| `META`                                              | Always, since it wraps scaffold-only content |

Dropping PHPUnit drops FunctionalJavascript with it, since those tests run on PHPUnit. Functional tests run on Mink and its BrowserKit driver too, so those packages belong to PHPUnit, and dropping FunctionalJavascript removes only the Selenium driver. Dropping every tool in `npm_specs()` (ESLint, Stylelint, CSpell and Jest) also deletes `package.json` and `package-lock.json`, since nothing runs npm any more.

Blocks of different tokens can nest, because `install.php` strips 1 token at a time: a `DEV_FUNCTIONAL_JAVASCRIPT` block inside a `DEV_MAKEFILE` block goes when either token is stripped.

### Rules for a block

A broken block fails quietly. Every file stays valid, and `composer update-snapshots` records the broken output as the new baseline, so these rules matter more than they look:

- **Give each marker a line of its own.** `remove_special_comments()` deletes any line containing `#;`, so content that shares a line with a marker never reaches a generated project, and neither does a `#;` in ordinary content.
- **Close a block before you open the same token again.** `remove_tokens_with_content()` skips a file that has no `#;> TOKEN` at all, so an unclosed block there ships in full. In a file that does close the token somewhere, the unclosed block runs on to the next `#;> TOKEN` or the end of the file and takes everything in between with it.
- **Use a token `install.php` strips.** A misspelled or unknown token is never stripped, so its block ships in every generated project, minus the marker lines.
- **Don't start a token with another token's name.** Markers match as substrings, so stripping `DEV_PHPCS` would also strip a `DEV_PHPCS_FIXER` block.
- **Wrap a new Drupal major everywhere the other majors are wrapped.** A selected major keeps only the blocks that exist for it, so a missing one leaves a gap, such as a project with Drupal 13 CI jobs but no Drupal 13 badge.

`TemplateMarkersTest` checks all 5 rules across the template.

In Markdown, open a block with the blank line that separates it from the text before it, the way `CONTRIBUTING.dist.md` does. A removed block then takes its blank line with it, where a blank line on each side of it would leave a double blank line behind. `InstallProcessTest` checks the generated `AGENTS.md`, `CONTRIBUTING.md` and `README.md` for that across the command wrapper and Drupal major selections.

### Text rendered from the answers

A block removes whole lines, so it can't trim a line that names several options. `install.php` renders those lines instead. The template holds the text for every option, and `install.php` replaces it with the same function's text for the selected options:

- `core_version_requirement()` renders `core_version_requirement` in `your_extension.info.yml`, such as `^10 || ^11` for Drupal 10 and 11.
- `phpunit_config_comment()` renders the comment that opens `phpunit.xml`. It leaves out a major with a `phpunit.d<major>.xml` of its own, which `eddy-assemble` uses for that major instead, so with Drupal 10 and 11 selected it names Drupal 11 only.
- `command_wrapper_text()` renders "`make` or `ahoy`", and "`make build` or `ahoy build`" for the `build`, `lint` and `test` commands. Ahoy alone turns them into "`ahoy`" and "`ahoy build`". Without a wrapper, these phrases sit in `DEV_COMMAND_WRAPPER` blocks, and "The command wrappers and CI run it" in `AGENTS.md` becomes "CI runs it".
- `process_extension_type_text()` renders where the extension sits in the build. A module's `CONTRIBUTING.md` drops the "(`web/themes/custom/` for a theme)" note, a theme's shows its example test paths under `web/themes/custom/`, and the symlink note in `AGENTS.md` names only the directory of the extension's type.
- A tool's `replacements` in `tool_specs()` map text that names the tool among other items to the same text without it, and `remove_tools()` applies them when the tool goes. Without FunctionalJavascript, the `info` lines in `AGENTS.md` stop naming the WebDriver port.

A replacement finds nothing once the template text drifts from the every-option rendering, and the text then ships unrendered. `InstallProcessTest` renders each one for every selection, so it fails when a template edit breaks a replacement. A new phrase that names both wrappers needs its command added to `process_command_wrapper_text()`.

### Adding a Drupal major

1. In `install.php`, add the major to `drupal_version_options()` and to the `INSTALL_DRUPAL_VERSION` entry of `print_help()`.
2. Add a `DRUPAL_<major>` block wherever the existing majors have one. `git grep -n '#;< DRUPAL_'` lists them: today that's the `lint` and `test` matrices in `.github/workflows/test.yml`, the badges in `README.dist.md` and the matrix list in `AGENTS.md`.
3. Add the major to `core_version_requirement` in `your_extension.info.yml`, and to the comment that opens `phpunit.xml` unless the major gets a `phpunit.d<major>.xml` of its own (see [Text rendered from the answers](#text-rendered-from-the-answers)).
4. To have the major start checked, add it to `drupal_version_default()` and update the defaults that `print_help()` and `README.md` name. Then set `extra.eddy.drupal-version` in `composer.dev.json` to the highest major `drupal_version_default()` returns: `InstallHelpersTest` fails until they agree. That major also caps the build default of a generated project, because `drupal_version_build_default()` picks the highest selected major up to it. Add a major to `drupal_version_default()` only once it has a stable release.
5. Review `.eddy/tooling/src/eddy-assemble`. It picks the PHPUnit, `symfony/phpunit-bridge` and Rector constraints by major, and it uses `phpunit.d<major>.xml` in place of `phpunit.xml` when that file exists.
6. Update `README.md` where it lists the majors: the badges, the CI job table and the branch protection table.
7. Update the expected majors in `InstallHelpersTest` and the Drupal data providers in `InstallProcessTest`, add a `d<major>_only` dataset to `InstallTest`, then regenerate the snapshot fixtures.

### Adding a development tool

1. In `install.php`, add the tool to `$tool_options` in `main()` and to the `INSTALL_TOOLS` entry of `print_help()`.
2. Give it a `tool_specs()` entry: its `DEV_<TOOL>` token plus the files, directories and `composer.dev.json` entries that go with it. List only the packages nothing else needs: Mink, which Functional tests use too, sits with PHPUnit rather than FunctionalJavascript. Text that names the tool among other items goes in its `replacements`. Its npm packages and scripts, if it has any, go in `npm_specs()`.
3. Wrap every line you add for the tool in a `DEV_<TOOL>` block, wherever it lands: the command wrappers, `.github/workflows/test.yml`, configuration files such as `.gitattributes` and `.editorconfig`, and the docs in `AGENTS.md`, `README.dist.md` and `CONTRIBUTING.dist.md`. A `CI_IS_<TOOL>_RUNNER` flag in `test.yml` belongs inside the block too, and `CiRunnerVariablesTest` checks it's there.
4. If the tool runs through the shared `npm run lint` step, add it to the condition in `remove_tools()` that strips `DEV_NODEJS_LINT`, and add its sub-scripts to the `rebuild_npm_chain()` calls in `remove_npm()`.
5. Document it in `README.md`: the feature list, and the ignore-failure table when its CI step has a `CI_<TOOL>_IGNORE_FAILURE` variable.
6. Add the tool to `dataProviderProcessRemovesTools()` and `dataProviderProcessRemovesGitattributes()` in `InstallProcessTest`. In `InstallTest`, add it to every `tools` answer and add a `no_<tool>` dataset, then regenerate the snapshot fixtures.

## Workflow branches

The scaffold builds from `main`, so the branch filters of `.github/workflows/test.yml` and `draft-release-notes.yml` list `main`. A generated project builds from the branch it releases from on Drupal.org instead, so `process_workflow_branches()` in `install.php` replaces every `- main` line in those 2 files:

- When `INSTALL_BRANCHES` lists any branches, the project gets those. `install.php` reads the variable directly rather than through a prompt, so an unattended run without it never waits for input.
- Otherwise, when `HEAD` holds a project copy of `test.yml`, as it does when `install.php` runs again in the root of an existing project, the project keeps that copy's `push` branches.
- Otherwise the project gets `1.x`. A committed copy that still holds `#;` markers is the template's own, such as in a repository created with **Use this template**, so it doesn't count.

The update skill runs `install.php` in a scratch directory, where the lookup finds no committed `test.yml`, so both of its renders get `1.x`. Its 3-way merge then keeps the project's branches, because the 2 renders agree on those lines.

Keep `main` the only branch in those filters: a second branch there ships to every generated project.

## GitHub repository

The badges in `README.dist.md` name the GitHub repository as `YourNamespace/your_extension`, and `process_internal()` renders it as:

- `INSTALL_GITHUB_REPOSITORY`, when it's set. `install.php` reads it directly rather than through a prompt, like `INSTALL_BRANCHES`, and accepts only the `owner/name` form.
- Otherwise the repository of the `origin` remote, when the current directory is the root of a clone, as it is when `install.php` runs in a clone of a repository created with **Use this template**.
- Otherwise the machine name for both parts, as the generic replacements render it.

The repository goes in after the generic replacements, which would rewrite an owner or a name spelled like a placeholder, such as `alexskrypnyk`. The update skill renders in a scratch directory inside the project's repository, which the root check rules out, so it passes `INSTALL_GITHUB_REPOSITORY` to both renders.

## Template version

`install.php` records the Eddy release a project came from as `extra.eddy.version` in its `composer.dev.json`, so the update skill can render that release as the base of its 3-way merge.

The value comes from the `TEMPLATE_VERSION` constant. `.gitattributes` marks `install.php` as `export-subst`, so `git archive` expands the constant's `$Format:%(describe:tags=true)$` placeholder to `git describe` output for the archived commit. GitHub builds every download that way: a release archive, as the update skill fetches it, gets the tag, such as `5.0.0`, and **Download ZIP** of a branch gets the commit after the last tag, such as `5.0.0-3-g1a2b3c4`. A clone, or a repository created with **Use this template**, keeps the placeholder, and `install.php` records nothing.

`git archive` expands every `$Format:` in the file up to the next `$`, which in PHP is usually the next variable. Keep the constant the only place `install.php` holds that text, in comments too. `InstallHelpersTest` archives the real `install.php` and fails when anything other than the constant changes.

The `export-subst` line sits under "Remove the lines below in your project." in `.gitattributes`, and `install.php` removes it from generated projects.

## Test groups

PHPUnit tests are tagged with `#[Group('p0'..'p5')]` so CI can shard them across parallel jobs:

- `p0` - Unit tests in `src/Unit/` (no I/O bound dependencies).
- `p1` - `InstallTest` (snapshot comparison of `install.php` output).
- `p2` - `AssembleTest` (Drupal codebase assembly) and `ToolingInstallerTest` (the tooling installer driving a real Composer).
- `p3` - `AhoyTest`, `AutoPortDiscoveryTest` (Ahoy command wrapper).
- `p4` - `MakeTest` (Makefile command wrapper).
- `p5` - `XdebugTest` (XDebug step-debugging toggle). This is the only group whose CI runner installs the xdebug PHP extension via `coverage: xdebug` instead of pcov - the test exercises the real extension end to end, so coverage is not collected for this group.

**Do not DRY the per-wrapper tests.** `AhoyTest` (p3) and `MakeTest` (p4) are intentionally kept as separate, near-parallel test classes - one per command wrapper. Never merge or parameterize them into a single shared class: each wrapper (`ahoy`, `make`) must be exercised by its own independent test so a regression in one runner can never be masked by the other. `XdebugTest`'s single parameterized `assertToggle()` is a deliberate exception for one feature toggled through both wrappers, not a pattern to extend across the whole command surface.

## Running the tests

All commands run from the repository root. Install dependencies once with `composer --working-dir=.eddy/tests install`.

| Action                        | Command                                                           |
|-------------------------------|-------------------------------------------------------------------|
| All tests                     | `composer --working-dir=.eddy/tests test`                         |
| Single group                  | `composer --working-dir=.eddy/tests test -- --group=p0`           |
| Single test class             | `composer --working-dir=.eddy/tests test -- --filter=InstallTest` |
| With coverage                 | `composer --working-dir=.eddy/tests test-coverage -- --group=p0`  |
| Lint (phpcs, phpstan, rector) | `composer --working-dir=.eddy/tests lint`                         |
| Lint autofix                  | `composer --working-dir=.eddy/tests lint-fix`                     |

`p2`-`p5` exercise the full build pipeline and need a Drupal-friendly PHP setup; `p3` and `p4` also need a WebDriver backend: a Selenium container or a local Chrome driven by chromedriver. These are the same jobs the GitHub Actions matrix runs (`.github/workflows/eddy-test.yml`).

## Regenerating snapshot fixtures

**HARD RULE - never edit fixtures directly.** Files under `.eddy/tests/fixtures/install/` are generated artifacts. They must always be regenerated with the `update-snapshots` Composer script - run from inside `.eddy/tests` (see below) - after any source change that affects `install.php` output. Hand-editing a fixture risks drift between what the generator would produce and what is checked in - subsequent regenerations would then overwrite the manual edit and the failure mode would only surface in CI.

`InstallTest` runs `install.php` end-to-end and diffs the output against `fixtures/install/_baseline/` plus one fixture directory per dataset (`gha_makefile/`, `theme/`, etc. - see `InstallTest::dataProviderInstall()`).

When source files change (workflows, `scripts/`, `composer.dev.json`, `install.php`, Claude settings, etc.), the fixtures fall out of date. Regenerate them. `.eddy/tooling/` is not part of a generated project, so a change confined to it needs no regeneration.

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

After it finishes, `git show --stat` the resulting commit to confirm it touches only the files your change should have affected, then run `composer test -- --filter=InstallTest` from inside `.eddy/tests` to confirm everything is green before pushing.

The trait that drives the diff-and-update behavior is `SnapshotTrait` (see `tearDown()` in `InstallTest`); it calls `snapshotUpdateOnFailure()` so a normal `test` run will also rewrite fixtures if you have not used the dedicated `update-snapshots` command.

## Regenerating animated SVG assets

The README demo SVGs are produced from real terminal recordings:

```bash
composer --working-dir=.eddy/tests update-assets
```

This invokes `php .eddy/assets/update-assets.php`, which:

1. Exports the committed `HEAD` into a clean workspace in the system temp directory (so commit your changes first), and installs `svg-term` via `npm install --prefix .eddy/assets`.
2. Records `php install.php`, `ahoy build`, `ahoy lint` and `ahoy test` one after another with `asciinema` + `expect`, each typed at a shell prompt in the workspace the previous recordings left behind.
3. Rewrites each recording onto a canonical timeline, converts it to an animated SVG via `node .eddy/assets/svg-term-render.js`, and writes `install.svg`, `build.svg`, `lint.svg` and `test.svg` into `.eddy/assets/`.

Regeneration is reproducible: recording the same session twice produces the same bytes, so `git status` after a run is the check. A clean tree means the rendering didn't change, and any diff is a real change worth reading. 3 things make that hold:

- Frames are cut where the session's own output defines them (1 per typed character, 1 per widget redraw in `install`, 1 per line of command output) rather than wherever the terminal happened to split a write.
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

`.github/workflows/eddy-test.yml` runs the suite across the `p0`-`p5` groups on Ubuntu and macOS, runs `p3` and `p4` once per WebDriver backend (Selenium on Ubuntu only, chromedriver on both), and validates `composer.json` and the tooling package's `composer.json` (validate + normalize) plus the PHP lint step in `p0`. A second job (`eddy-test-actions`) lints the workflow YAML with `yamllint` and `actionlint` and checks it for security issues with Zizmor.

`.github/workflows/eddy-publish-tooling.yml` publishes `.eddy/tooling/` to the same-named branch of `drevops/eddy-tooling` on every push to `main` and to a branch whose name contains `eddy-tooling`.

`.github/workflows/deploy.yml` deploys `main` and release tags to [`drevops/eddy_destination`](https://github.com/drevops/eddy_destination) the way it deploys a generated project to Drupal.org. The `DEPLOY_BRANCH` repository variable sends `main` to `5.x` there, and the `DEPLOY_PROCEED` secret set to `1` lets the push through.

`install.php` deletes every `.github/workflows/eddy-*.yml` from a generated project, so a maintenance-only workflow takes the `eddy-` prefix and a workflow that ships to projects never does.
