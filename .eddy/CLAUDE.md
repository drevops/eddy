# Eddy Maintenance

Maintenance guide for the Eddy template itself.

This file documents how `install.php` prunes the template, how to regenerate the scaffold's own artifacts (animated demo SVGs, the social preview card, snapshot fixtures), how to run its self-tests and how to work on its documentation site. It does **not** apply to consumer projects produced by running `install.php`.

## Layout

- `.eddy/assets/` - Source files for animated SVG demos used in the root `README.md` and the documentation site (`install.svg`, `build.svg`, `lint.svg`, `test.svg`) plus the `update-assets.php` generator and a small `svg-term` Node wrapper. It also holds the repository's social preview card, `social-preview.png`, and the `social-preview.html` page it's rendered from.
- `.eddy/docs/` - The Docusaurus documentation site, with its pages in `.eddy/docs/content/`. It's the home of the user-facing docs: `README.md`, `MIGRATION.md` and `CONTRIBUTING.md` only point into it. See [Documentation site](#documentation-site).
- `.eddy/tests/` - PHPUnit suite that validates the scaffold itself: the `install.php` interactive flow, the tooling commands in `.eddy/tooling/src/` with their installer `scripts/eddy-tooling`, and the resulting project structure. Snapshots live under `.eddy/tests/fixtures/install/`.
- `.eddy/tooling/` - Source of the `drevops/eddy-tooling` Composer package (the `eddy-*` commands). `scripts/eddy-tooling` installs it into `vendor/` as a symlink in this repository, and `eddy-publish-tooling.yml` mirrors it to the read-only `drevops/eddy-tooling` repository on every push to `main` and to a branch whose name contains `eddy-tooling`. Releases are created on the mirror by hand, with notes from the `create-eddy-tooling-release-notes` skill - see `.eddy/docs/content/contributing/tooling-releases.mdx`.
- `.eddy/skills/update-consumer-eddy/` - the update skill that consumer projects fetch through the "Updating the scaffold" section of their `AGENTS.md`.
- `.claude/skills/` - Claude Code skills for maintaining the scaffold, such as `create-eddy-tooling-release-notes`. `install.php` removes each one by name, as listed in `eddy_skills()`, so generated projects never get them, and keeps any other skill, such as a project's own when `install.php` runs again in the root of an existing project. A new skill goes into `eddy_skills()` as well: `InstallHelpersTest` fails until every tracked skill is listed.

## Template markers

Besides renaming files and replacing placeholders, `install.php` deletes whatever the answers to its prompts rule out: a Drupal major you didn't select, a tool you unchecked, a command wrapper you dropped, the example module's own files in a theme. Whole files go by name, such as `phpstan.neon` when PHPStan goes. Lines inside the files that stay go through marker blocks.

A block opens with a line holding `#;< TOKEN` and closes with a line holding `#;> TOKEN`, and each marker line uses whatever comment syntax keeps its file valid:

| File                                                                         | Marker line          |
|------------------------------------------------------------------------------|----------------------|
| YAML, NEON, `.gitattributes`, `.editorconfig`, shell code blocks in Markdown | `#;< TOKEN`          |
| Makefile recipe                                                              | `@#;< TOKEN`         |
| Markdown and XML                                                             | `<!-- #;< TOKEN -->` |
| PHP                                                                          | `// #;< TOKEN`       |

In a Makefile recipe the `@` stops `make` from echoing the line, and the shell then reads it as a comment.

For each token it strips, `remove_tokens_with_content()` deletes every block of that token, markers included, from every text file in the project. It walks the whole tree apart from `.git`, `.idea`, `.chromedriver`, `vendor` and `node_modules`, the paths the root `.gitignore` ignores, everything in `.claude` except `settings.json`, and the root `install.php`, so a block works in any other file you put it in. The marker-line cleanup walks the same files, and the replacements walk them plus `install.php`. Once the pruning is done, `remove_special_comments()` deletes every remaining line that contains `#;`, which clears the markers of the blocks that stayed.

Both marker passes leave `install.php` out, because its code spells out the marker syntax: deleting its `#;` lines would break the copy that stays when "Remove this script" is answered no. `get_marker_files()` holds that exclusion, and `TemplateMarkersTest` skips `install.php` for the same reason.

Neither leaves a run of blank lines behind. When the lines they delete sit between 2 blank lines, 1 of the 2 goes with them, and at the start or the end of a file, the blank line next to them goes. A block can keep a blank line on each side, or hold 1 of them the way the `DRUPAL_<major>` blocks in `test.yml` do. `remove_string_content()`, which deletes the template-only lines of `.gitattributes`, works the same way. `InstallProcessTest` checks every generated file for a run of blank lines, and for a blank line at the start or the end, across the command wrapper, Drupal major and tool selections.

| Token                                               | Stripped when                                |
|-----------------------------------------------------|----------------------------------------------|
| `DRUPAL_<major>`, such as `DRUPAL_11`               | The major isn't selected                     |
| `EXTENSION_MODULE`, `EXTENSION_THEME`               | The extension is of the other type           |
| `DEV_<TOOL>`, the `token` of a `tool_specs()` entry | The tool isn't selected                      |
| `DEV_NODEJS_LINT`                                   | Neither ESLint nor Stylelint is selected     |
| `DEV_NPM`                                           | No tool from `npm_specs()` is selected       |
| `DEV_AHOY`, `DEV_MAKEFILE`                          | That command wrapper isn't selected          |
| `DEV_COMMAND_WRAPPER`                               | No command wrapper is selected               |
| `DEV_NO_COMMAND_WRAPPER`                            | At least 1 command wrapper is selected       |
| `META`                                              | Always, since it wraps scaffold-only content |

Dropping PHPUnit drops FunctionalJavascript with it, since those tests run on PHPUnit. Functional tests run on Mink and its BrowserKit driver too, so those packages belong to PHPUnit, and dropping FunctionalJavascript removes only the Selenium driver. Dropping every tool in `npm_specs()` (ESLint, Stylelint, CSpell and Jest) also deletes `package.json` and `package-lock.json`, since nothing runs npm any more.

A file with a `DRUPAL_<major>` block holds one for every major, so a file that only 1 major has goes in the `files` of that major's entry in `drupal_version_specs()` instead, and `remove_drupal_versions()` deletes it when the major isn't selected. That's how a project without Drupal 10 loses `phpunit.d10.xml`, which `eddy-assemble` uses in place of `phpunit.xml` only in a Drupal 10 build.

The example extension is a module, and a theme keeps only what isn't specific to a module. A line that only one type has sits in an `EXTENSION_MODULE` or `EXTENSION_THEME` block: the `dependencies` of `your_extension.info.yml` are the module's, its `base theme` and `libraries` keys are the theme's, and so are their notes in `AGENTS.md`. A file that only one type uses goes in the `files` of that type's entry in `extension_type_specs()`, and `remove_extension_types()` deletes it from the other type by its template name, before `process_internal()` renames the files, along with each directory that leaves empty:

- The module's are the `.module`, `.install`, routing, services and menu links files, the service class, the settings form, its configuration schema and the Unit, Kernel and Functional tests that cover them. Its `cspell_words` drop `noscript`, which only those files use. `phpunit.xml` and `phpunit.d10.xml` include the `.module` and `.install` files of `web/modules/custom` in the coverage source, and the Kernel tests call the hooks in them, since the Functional test runs them in the webserver's process, where pcov collects nothing.
- The theme's are `your_extension.theme`, which adds the load time element that the module adds from `hook_page_bottom()`, and the Unit test that calls its hook. The `.theme` file is also what gives the PHPUnit coverage source a file in a theme: PHPUnit writes no coverage report when the source matches nothing, so `phpunit.xml` and `phpunit.d10.xml` include the `.theme` files of `web/themes/custom`.

A theme keeps the CSS, JavaScript and Jest test, and the FunctionalJavascript base class and smoke test, which `process_extension_type_text()` points at the theme (see [Text rendered from the answers](#text-rendered-from-the-answers)).

`process_internal()` renames the files named after the example extension, `your_extension` or `YourExtension`, from a list, so a new file of that kind needs a `rename()` line there. `InstallProcessTest` fails while any path of a generated project still holds either name.

Every tool configuration names only the custom directory of the extension's type. Each line that names `web/modules/custom` or `web/themes/custom` sits in the matching `EXTENSION_<TYPE>` block, in `phpcs.xml`, `phpstan.neon`, `rector.php`, `.twig-cs-fixer.php`, `phpunit.xml` and `phpunit.d10.xml`, and `process_extension_type_text()` renders the lines of `package.json` and `jest.config.js` that list both. So the build's other custom directory, which `eddy-assemble` still creates, never reaches a linter or a test suite, and a custom extension of the other type that `suggest` installs there is neither linted nor tested. An extension that a project bundles inside its own, such as a module in a theme's `modules/`, is linted with it, since it sits under the extension's directory. Running its tests and enabling it on the site are manual steps, which `.eddy/docs/content/development/bundled-extensions.mdx` documents and `GeneratedProjectTest` exercises.

JSON holds no markers, so the words a tool adds to the `words` of `.cspell.json` are listed in its `cspell_words` in `tool_specs()`. Once every other change is done, `remove_unused_cspell_words()` removes the listed words of each dropped tool that no project file uses. A word that the extension name or a kept `install.php` uses stays, and so do generic words such as `webroot`, which no tool lists.

Blocks of different tokens can nest, because `install.php` strips 1 token at a time: a `DEV_FUNCTIONAL_JAVASCRIPT` block inside a `DEV_MAKEFILE` block goes when either token is stripped.

### Rules for a block

A broken block fails quietly. Every file stays valid, and `composer update-snapshots` records the broken output as the new baseline, so these rules matter more than they look:

- **Give each marker a line of its own.** `remove_special_comments()` deletes any line containing `#;`, so content that shares a line with a marker never reaches a generated project, and neither does a `#;` in ordinary content.
- **Close a block before you open the same token again.** `remove_tokens_with_content()` skips a file that has no `#;> TOKEN` at all, so an unclosed block there ships in full. In a file that does close the token somewhere, the unclosed block runs on to the next `#;> TOKEN` or the end of the file and takes everything in between with it.
- **Use a token `install.php` strips.** A misspelled or unknown token is never stripped, so its block ships in every generated project, minus the marker lines.
- **Don't start a token with another token's name.** Markers match as substrings, so stripping `DEV_PHPCS` would also strip a `DEV_PHPCS_FIXER` block.
- **Wrap a new Drupal major everywhere the other majors are wrapped.** A selected major keeps only the blocks that exist for it, so a missing one leaves a gap, such as a project with Drupal 13 CI jobs but no Drupal 13 badge. What only 1 major has goes in `drupal_version_specs()` instead of a block.

`TemplateMarkersTest` checks all 5 rules across the template.

### Text rendered from the answers

A block removes whole lines, so it can't trim a line that names several options. It can't keep a line that any of several options needs either, because a line inside nested blocks goes as soon as 1 of their tokens is stripped. `install.php` renders those lines instead. The template holds the text for every option, and `install.php` replaces it with the same function's text for the selected options:

- `core_version_requirement()` renders `core_version_requirement` in `your_extension.info.yml`, such as `^10 || ^11` for Drupal 10 and 11.
- `phpunit_config_comment()` renders the comment that opens `phpunit.xml`. It leaves out a major with a `phpunit.d<major>.xml` of its own, which `eddy-assemble` uses for that major instead, so with Drupal 10 and 11 selected it names Drupal 11 only.
- `php_badges()` renders the PHP badges in `README.dist.md`, 1 for each PHP version that a selected major supports in `drupal_version_php_versions()`. Drupal 10 alone drops the PHP 8.5 badge, and Drupal 12 alone keeps only that one.
- `command_wrapper_text()` renders "`make` or `ahoy`", and "`make build` or `ahoy build`" for the `build`, `info`, `lint` and `test` commands. Ahoy alone turns them into "`ahoy`" and "`ahoy build`". Without a wrapper, these phrases sit in `DEV_COMMAND_WRAPPER` blocks, and "The command wrappers and CI run it" in `AGENTS.md` becomes "CI runs it".
- `process_extension_type_text()` renders where the extension sits in the build. A module's `CONTRIBUTING.md` drops the "(`web/themes/custom/` for a theme)" note, a theme's shows its example test paths under `web/themes/custom/`, and the symlink note in `AGENTS.md` names only the directory of the extension's type. So do the ESLint and Stylelint scripts of `package.json` and the `dirs` of `jest.config.js`, which list both directories on 1 line. A theme can't be enabled as a module, so in its FunctionalJavascript base class the `$defaultTheme = 'stark'` and `$modules` declarations become a `$defaultTheme` that names the theme.
- A tool's `replacements` in `tool_specs()` map text that a block can't remove to the same text without the tool, and `remove_tools()` applies them when the tool goes. Without FunctionalJavascript, the `info` lines in `AGENTS.md` stop naming the WebDriver port and a theme's test notes there name only its Unit test, and the `test` recipe in the `Makefile` stops exporting the port. The export opens a backslash-continued recipe line there, so a closing marker after it would become part of the shell command.
- A major's `replacements` in `drupal_version_specs()` do the same for text that only that major has, and `remove_drupal_versions()` applies them when the major goes. Without Drupal 10, `.gitattributes` loses its `phpunit.d10.xml` line and `CONTRIBUTING.md` stops naming the file.

A replacement finds nothing once the template text drifts from the every-option rendering, and the text then ships unrendered. `InstallProcessTest` renders each one for every selection, so it fails when a template edit breaks a replacement. A new phrase that names both wrappers needs its command added to `process_command_wrapper_text()`.

### Adding a Drupal major

1. In `install.php`, add the major to `drupal_version_options()` and to the `INSTALL_DRUPAL_VERSION` entry of `print_help()`, and list the PHP versions it supports in `drupal_version_php_versions()`.
2. Add a `DRUPAL_<major>` block wherever the existing majors have one. `git grep -n '#;< DRUPAL_'` lists them: today that's the `lint` and `test` matrices in `.github/workflows/test.yml`, the badges in `README.dist.md` and the matrix list in `AGENTS.md`. The `test` jobs run the lowest and the highest of the major's PHP versions, and its `AGENTS.md` line states that range. `InstallHelpersTest` fails until all 3 agree.
3. Add the major to `core_version_requirement` in `your_extension.info.yml`, and to the comment that opens `phpunit.xml` unless the major gets a `phpunit.d<major>.xml` of its own (see [Text rendered from the answers](#text-rendered-from-the-answers)). A PHP version that no other major supports also needs its badge among the PHP badges in `README.dist.md`, in ascending order. A new `phpunit.d<major>.xml` needs the same wiring as `phpunit.d10.xml`: an entry in the PHPUnit `files` of `tool_specs()`, a `.gitattributes` line with its `uncomment_line()` call in `process_internal()`, an entry for the major in `drupal_version_specs()` that lists the file and removes its `.gitattributes` line and any text that names it, and an entry in the PHPUnit config lists of `InstallProcessTest`, `PhpunitWebdriverEndpointTest` and `VariableNamesTest`.
4. To have the major start checked, add it to `drupal_version_default()` and update the defaults that `print_help()` and the documentation name: the questions table in `.eddy/docs/content/installation.mdx` and the default in `.eddy/docs/content/development/drupal-versions.mdx`. Then set `extra.eddy.drupal-version` in `composer.dev.json` to the highest major `drupal_version_default()` returns: `InstallHelpersTest` fails until they agree. That major also caps the build default of a generated project, because `drupal_version_build_default()` picks the highest selected major up to it. Add a major to `drupal_version_default()` only once it has a stable release.
5. Review `.eddy/tooling/src/eddy-assemble`. It picks the PHPUnit, `symfony/phpunit-bridge` and Rector constraints by major, and it uses `phpunit.d<major>.xml` in place of `phpunit.xml` when that file exists.
6. Update the documentation where it lists the majors: the badges and the feature list in `README.md`, the feature list in `.eddy/docs/content/README.mdx`, the job tables and the job count above them in `.eddy/docs/content/continuous-integration/README.mdx`, the table in `.eddy/docs/content/continuous-integration/branch-protection.mdx`, and `DRUPAL_MAJORS` in `.eddy/docs/src/pages/index.js`, with `PHP_VERSIONS` there too when the major brings a new PHP version. `DocumentationTest` fails until those tables list every job of `test.yml` and the 2 home page lists match `install.php`. `git grep -n 'Drupal 12' -- .eddy/docs/content` finds the other pages that name the majors.
7. Update the expected majors in `InstallHelpersTest` and the Drupal data providers in `InstallProcessTest`, add a `d<major>_only` dataset to `InstallTest`, then regenerate the snapshot fixtures.

### Adding a development tool

1. In `install.php`, add the tool to `$tool_options` in `main()` and to the `INSTALL_TOOLS` entry of `print_help()`.
2. Give it a `tool_specs()` entry: its `DEV_<TOOL>` token plus the files, directories and `composer.dev.json` entries that go with it. List only the packages nothing else needs: Mink, which Functional tests use too, sits with PHPUnit rather than FunctionalJavascript. Text that names the tool among other items goes in its `replacements`. The words it adds to `.cspell.json`, such as the vendors of its packages, go in its `cspell_words` when no other file uses them. Its npm packages and scripts, if it has any, go in `npm_specs()`.
3. Wrap every line you add for the tool in a `DEV_<TOOL>` block, wherever it lands: the command wrappers, `.github/workflows/test.yml`, configuration files such as `.gitattributes` and `.editorconfig`, and the docs in `AGENTS.md`, `README.dist.md` and `CONTRIBUTING.dist.md`. A `CI_IS_<TOOL>_RUNNER` flag in `test.yml` belongs inside the block too, and `CiRunnerVariablesTest` checks it's there. A commented-out `export-ignore` line in `.gitattributes` also needs an `uncomment_line()` call in `process_internal()`, or it ships commented out.
4. If the tool runs through the shared `npm run lint` step, add it to the condition in `remove_tools()` that strips `DEV_NODEJS_LINT`, and add its sub-scripts to the `rebuild_npm_chain()` calls in `remove_npm()`.
5. Document it: the feature lists in `README.md` and `.eddy/docs/content/README.mdx`, the `TOOLS` and `FEATURES` lists of the home page in `.eddy/docs/src/pages/index.js`, the tools table in `.eddy/docs/content/development/coding-standards.mdx`, the Tools row in `.eddy/docs/content/installation.mdx`, and the ignore-failure table in `.eddy/docs/content/continuous-integration/configuration.mdx` when its CI step has a `CI_<TOOL>_IGNORE_FAILURE` variable. `DocumentationTest` fails until that table lists every toggle of `test.yml`.
6. Add the tool to the Tools list in `.eddy/skills/update-consumer-eddy/SKILL.md`, followed by the file that marks it, such as `` `phpcs` (`phpcs.xml`) ``. `DocumentationTest` fails until it's there, because the update skill would otherwise remove the tool from every project it updates.
7. Add the tool to `dataProviderProcessRemovesTools()` and `dataProviderProcessRemovesGitattributes()` in `InstallProcessTest`. In `InstallTest`, add it to `defaultAnswers()` and to every `tools` answer other than the empty one of `no_tools`, and add a `no_<tool>` dataset, then regenerate the snapshot fixtures.

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

The license badge names no repository: it states GPL-2.0-or-later. GitHub detects a license only from a license file, and a generated project has none, because Drupal.org adds `LICENSE.txt` to every release it packages and asks projects not to commit one. The deployment pushes the project's commits as they are, so it can't leave a committed license file out either.

## Template version

`install.php` records the Eddy release a project came from as `extra.eddy.version` in its `composer.dev.json`, so the update skill can render that release as the base of its 3-way merge.

The value comes from the `TEMPLATE_VERSION` constant. `.gitattributes` marks `install.php` as `export-subst`, so `git archive` expands the constant's `$Format:%(describe:tags=true)$` placeholder to `git describe` output for the archived commit. GitHub builds every download that way: a release archive, as the update skill fetches it, gets the tag, such as `5.0.0`, and **Download ZIP** of a branch gets the commit after the last tag, such as `5.0.0-3-g1a2b3c4`. No release carries the second form, so the update skill renders its base from the archive of the commit, `https://github.com/drevops/eddy/archive/<commit>.tar.gz`, which GitHub expands to the same version. A clone, or a repository created with **Use this template**, keeps the placeholder, and `install.php` records nothing.

`git archive` expands every `$Format:` in the file up to the next `$`, which in PHP is usually the next variable. Keep the constant the only place `install.php` holds that text, in comments too. `InstallHelpersTest` archives the real `install.php` and fails when anything other than the constant changes, and the docs test in `.eddy/docs/tests/unit/installer.test.js` fails when the placeholder isn't there exactly once.

The documentation site serves `install.php` at `/install`, so `curl -fsSL https://eddy.drevops.com/install > install.php && php install.php` installs Eddy. `.eddy/docs/src/installer.js` writes the copy after each docs build and replaces the placeholder with the version of the build: the release version that `eddy-release-docs.yml` passes as `RELEASE_VERSION`, or the `git describe` output of the checkout in any other build, such as the Netlify development site.

In a directory that holds nothing but itself and hidden entries such as `.git`, `install.php` downloads the GitHub archive of its own version, or of `main` when it has none, and extracts it in place. Then it runs the extracted `install.php` with its own terminal, environment and arguments, so the release's own installer rewrites the release's files. `INSTALL_TEMPLATE_ARCHIVE` replaces the archive, which `InstallTest` points at an archive of the SUT. In a directory that holds other files and no `your_extension.info.yml`, `install.php` stops with an error, because its prompts would find no template files to rewrite there.

The `export-subst` line sits under "Remove the lines below in your project." in `.gitattributes`, and `install.php` removes it from generated projects.

## Test groups

PHPUnit tests are tagged with `#[Group('p0'..'p5')]` so CI can shard them across parallel jobs:

- `p0` - Unit tests in `src/Unit/` (no I/O bound dependencies).
- `p1` - `InstallTest` (snapshot comparison of `install.php` output, and the download into an empty directory).
- `p2` - `AssembleTest` (Drupal codebase assembly) and `ToolingInstallerTest` (the tooling installer driving a real Composer).
- `p3` - `AhoyTest` (Ahoy command wrapper), plus `AutoPortDiscoveryTest` and `WaitForPortTest`, which run real webservers and sockets without a wrapper.
- `p4` - `MakeTest` (Makefile command wrapper), plus `GeneratedProjectTest`, which generates a theme and a module with `install.php`, each bundling an extension of the other type, then builds, provisions, lints and tests them through `make`.
- `p5` - `XdebugTest` (XDebug step-debugging toggle). This is the only group whose CI runner installs the xdebug PHP extension via `coverage: xdebug` instead of pcov - the test exercises the real extension end to end.

Only `p0` collects coverage in CI. The other groups run the tooling and `install.php` as subprocesses, where pcov records nothing, so their reports would hold only the static analysis of files they never load. That analysis counts lines such as the shebang and top-level `const` declarations, which pcov never reports, so Codecov would show them as missed.

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

`test-coverage` writes an HTML report and a Clover file to `.eddy/tests/.logs/coverage/phpunit/`, and CI uploads the Clover file of `p0` to Codecov. The suite writes Clover rather than the template's Cobertura, because Cobertura leaves out code outside functions, which is most of every tooling command.

`p2`-`p5` exercise the full build pipeline and need a Drupal-friendly PHP setup; `p3` and `p4` also need a WebDriver backend: a Selenium container or a local Chrome driven by chromedriver. `p3` and `p5` run Ahoy, `p4` and `p5` run `make`, and `p5` needs the xdebug extension loaded with `xdebug.mode=off`. These are the same jobs the GitHub Actions matrix runs (`.github/workflows/eddy-test.yml`).

`phpunit.xml` stops the run at the first error, failure, warning or risky test, so a run that ends early can still print an "OK, but there were issues!" footer. Compare the test count with the expected total.

## Writing tests

Tests in `.eddy/tests/src` read, write, copy and remove files through `AlexSkrypnyk\File\File` from `alexskrypnyk/file`, and take the rest of their plumbing from the traits of `alexskrypnyk/phpunit-helpers`. `UnitTestCase` already uses `LocationsTrait`, `EnvTrait`, `ReflectionTrait` and `SerializableClosureTrait` from the helpers, and `FileAssertionsTrait` and `DirectoryAssertionsTrait` from `alexskrypnyk/file`. `FunctionalTestCase` adds `ProcessTrait` and `TuiTrait`.

| Instead of                                                               | Use                                                                 |
|--------------------------------------------------------------------------|---------------------------------------------------------------------|
| `file_get_contents()`                                                    | `File::read()`                                                      |
| `file()`, `parse_ini_file()`, `Yaml::parseFile()`, `DOMDocument::load()` | `File::read()`, then split or parse the string                      |
| `file_put_contents()`, `touch()` of a new file                           | `File::dump()`                                                      |
| `file_put_contents()` with `FILE_APPEND`                                 | `File::append()`, when the file exists                              |
| `mkdir()`                                                                | `File::mkdir()`                                                     |
| `copy()`, Symfony `Filesystem::mirror()`                                 | `File::copy()`                                                      |
| `unlink()`, `rmdir()`                                                    | `File::remove()`, `File::rmdir()`, `File::rmdirIfEmpty()`           |
| `file_exists()`, `is_file()`, `is_dir()`                                 | `File::exists()`, when the test only asks whether the path is there |
| `scandir()`, `RecursiveDirectoryIterator`                                | `File::scandir()`, `File::dirIsEmpty()`                             |
| `getcwd()`                                                               | `File::cwd()`                                                       |
| Any other Symfony `Filesystem` method                                    | Its `File` counterpart                                              |
| `sys_get_temp_dir()`, `tempnam()`                                        | The `LocationsTrait` directories, such as `self::$tmp`              |
| `realpath()`                                                             | `self::locationsRealpath()`                                         |
| `putenv()` of a variable the test introduces                             | `self::envSet()`                                                    |
| `getenv()`                                                               | `self::envGet()`                                                    |
| A Symfony `Process` that runs the command a test asserts on              | `processRun()` and the `assertProcess*()` methods of `ProcessTrait` |

`File::dump()` and `File::copy()` create the parent directories, so a test calls `File::mkdir()` only for a directory it needs on its own, such as an empty one or one it changes into. `File::read()` returns a string and throws for a missing file, so it needs no `(string)` cast or `FALSE` check, and a file that may be missing is read with `File::exists($path) ? File::read($path) : ''`. `File::rmdirIfEmpty()` keeps the behavior of an `rmdir()` that only removes an empty directory.

`assertFileContainsString()` and the other assertions of the 2 traits read a needle that parses as a regular expression, such as `/home/us`, as one, and their "not contains" forms pass for a missing file. Where either matters, assert on `File::read()` with `assertStringContainsString()`.

Plain PHP stays where `File` has no method, or where the native behavior is what the test needs:

- `chmod()`, `fileperms()`, `is_executable()`, `symlink()`, `readlink()`, `is_link()` and `rename()`.
- `glob()`. `File::scandir()` walks every directory below the one it lists, which a pattern such as `phpunit*.xml` in the repository root doesn't need.
- The `RecursiveDirectoryIterator` in `TemplateMarkersTest` and in `DocumentationTest::markdownFiles()`, which skip directories by name. `File::scandir()` skips paths by substring, so skipping `/build/` would also skip a checkout that sits under a `build` directory.
- `chdir()`, streams such as `php://memory` and sockets, and `file_get_contents()` of a URL.
- `putenv()` and `getenv()` that save and restore a variable the environment already holds, such as `HOME` in `UpdateAssetsTest`. `envReset()` unsets every variable set through `EnvTrait` instead of restoring its earlier value.
- Symfony `Process` for a command run through `PHP_BINARY`, a process started in the background, and helper commands such as `git` and `tar`. `processRun()` accepts only letters, digits, `.`, `-`, `_` and `/` in the command name, and a Homebrew `PHP_BINARY` such as `/opt/homebrew/Cellar/php@8.4/8.4.17/bin/php` holds an `@`.
- PHP that a test writes into a file as a string, such as the stub commands of `MakefileArgumentsTest`, since it runs without the packages.
- The functions that a mock stands in for: the names passed to `mockFunction()` and `getFunctionMock()`, and the native call that a mock falls back to, such as `\file_get_contents()` in `ProvisionTest`. The code under test expects the native return values, such as `FALSE` for a missing file. A mock intercepts only the calls from its namespace, such as `DrevOps\Eddy\Tooling`, so the `File` calls of a test never reach it.

The tooling reads a file through `file_read()` or `file_read_or_fail()`, which check `is_file()` before they call `file_get_contents()`. So a test that mocks the content of a file mocks `is_file()` for it too. `mockIsFile()` answers for the paths it lists and checks every other path on disk.

## Regenerating snapshot fixtures

**HARD RULE - never edit fixtures directly.** Files under `.eddy/tests/fixtures/install/` are generated artifacts, apart from the `.ignorecontent` rule files, which are edited by hand and kept as they are when the fixtures are regenerated. They must always be regenerated with the `update-snapshots` Composer script - run from inside `.eddy/tests` (see below) - after any source change that affects `install.php` output. Hand-editing a fixture risks drift between what the generator would produce and what is checked in - subsequent regenerations would then overwrite the manual edit and the failure mode would only surface in CI.

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
2. Runs the remaining datasets, then commits all their fixture diffs at once: into the baseline commit when step 1 made one, or as a commit of its own otherwise.
3. Exits 0 when every dataset passed or had its fixtures rewritten. A non-zero exit means a dataset failed for another reason or timed out.

After it finishes, `git show --stat` the resulting commit to confirm it touches only the files your change should have affected, then run `composer test -- --filter=InstallTest` from inside `.eddy/tests` to confirm everything is green before pushing.

The trait that drives the diff-and-update behavior is `SnapshotTrait` (see `tearDown()` in `InstallTest`). Its `snapshotUpdateOnFailure()` rewrites the fixtures of a failed dataset only when `UPDATE_SNAPSHOTS` is set, which `update-snapshots` does for each dataset, so a normal `test` run never rewrites fixtures.

## Regenerating animated SVG assets

The demo SVGs of `README.md` and the documentation site are produced from real terminal recordings:

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

Required tools: `asciinema` 3, `expect`, `node`, `npm` and `ahoy`, plus what `ahoy build` and `ahoy test` need, such as Composer and a local Chrome. The script checks for the first 4 and aborts if any are missing.

Set `SCRIPT_QUIET=1` to suppress verbose progress messages. The recordings go to `.artifacts/tmp/asciinema`: they're removed after a successful run unless `SCRIPT_KEEP_CASTS=1` is set, and kept after a failed one.

`UpdateAssetsTest` replays 2 real recordings of each session from `.eddy/tests/fixtures/assets/first/` and `.../second/` and expects them to render identically. They're plain copies of the raw `<asset>.cast` recordings in `.artifacts/tmp/asciinema/`, not the `.canonical.cast` files, from 2 back-to-back runs with `SCRIPT_KEEP_CASTS=1`, with the header reduced to `{"version":3,"term":{"cols":80,"rows":24}}`, the workspace path replaced with `/var/folders/aa/T/eddy-assets-111111111111` (first) or `/var/folders/aa/T/eddy-assets-222222222222` (second), and `$HOME` replaced with `/Users/maintainer`. Refresh them the same way when a change to the recordings makes the old ones unrepresentative.

## Regenerating the social preview

`social-preview.png` is the card GitHub shows when someone shares a link to the repository. It's a 1280x640 screenshot of `social-preview.html`, a self-contained page that draws the logo inline and takes its fonts from the local system, so install the 2 fonts its `@font-face` rules name before rendering it.

To change the card, edit the HTML, then render it at exactly 1280x640 with any headless browser, for example `agent-browser`:

```bash
agent-browser set viewport 1280 640
agent-browser open "file://$PWD/.eddy/assets/social-preview.html"
agent-browser screenshot "$PWD/.eddy/assets/social-preview.png"
```

GitHub has no API for the social preview, so upload the new PNG by hand in the repository's **Settings > General > Social preview**.

## Documentation site

`.eddy/docs/` is a Docusaurus site set up like the getscaffold.dev one from `AlexSkrypnyk/scaffold`. Its pages live in `.eddy/docs/content/`, 1 topic per page, and the sidebar follows the folder tree in the order of each page's `sidebar_position`. `.eddy/docs/content/contributing/documentation.mdx` is the guide for humans; these points matter most when editing:

- Run `npm --prefix .eddy/docs ci` once, then `npm --prefix .eddy/docs run build` and `npm --prefix .eddy/docs test`, which runs Jest with coverage for `src/` and CSpell for the pages. CI uploads the Jest coverage to Codecov. The build fails on a broken link, anchor or Markdown link.
- The home page is `.eddy/docs/src/pages/index.js`, a React page with its words in lists at the top of the file, and the introduction sits at `/introduction` through the `slug` of `content/README.mdx`. `npm start` and `npm run build` copy the 4 demo SVGs of `.eddy/assets` into the git-ignored `static/img/demos/` for its terminal, since a JavaScript import of an SVG yields a React component rather than a URL. Its type is Lexend from the `@fontsource-variable/lexend` package, and its colors are the `--eddy-*` tokens of `src/css/custom.css`.
- `npm --prefix .eddy/docs run test:accessibility` runs axe through Playwright against the WCAG 2.2 A and AA rules: every built page in both color modes at desktop and phone widths, plus the search dropdown, the mobile menu and an animated demo. The page scans run with reduced motion, so the home page shows every block at once, and separate checks cover the home page's terminal, tool list and copy button, and scan it again after a scroll-through with motion on. Install its Chromium once with `npm --prefix .eddy/docs exec -- playwright install chromium`. The fixes it guards live in `src/css/custom.css` (colors and underlined body links), `src/codeThemes.js` (code colors) and `src/theme/` (search markup, mobile menu focus, pausable demos and focusable wide tables), and `content/contributing/documentation.mdx` describes them.
- Link pages by their relative `.mdx` path, and show commands with the `CommandTabs` component (`make`, `ahoy` and `none` props), so every page covers the 3 wrapper setups.
- `.eddy/docs/cspell.json` checks the pages in US English and skips fenced code and `CommandTabs` blocks. The root `.cspell.json` ignores `.eddy/docs/`, and `remove_cspell_eddy_paths()` drops every `.eddy/` ignore path from a generated project.
- `DocumentationTest` checks the pages against the code: the tooling commands in `development/tooling.mdx`, the CI jobs in `continuous-integration/README.mdx` and `continuous-integration/branch-protection.mdx`, the ignore-failure toggles in `continuous-integration/configuration.mdx`, the variables the tooling reads in `development/environment-variables.mdx`, the update skill commands and the tooling PHP floor in `updating/upgrading-from-4x.mdx`, and the Drupal majors and PHP versions of the home page.
- The `eddy-test-docs` job deploys every build to Netlify when the `NETLIFY_SITE_ID` and `NETLIFY_AUTH_TOKEN` secrets are set: a push to `main` updates the development site at https://drevops-eddy-docs.netlify.app, and a pull request gets a preview linked in a comment.
- `eddy-release-docs.yml` publishes to GitHub Pages at https://eddy.drevops.com on a release tag, or on a manual run with an optional version. Without one, the version is the tag of the run, or the `git describe` output of its branch. It passes `DOCS_URL` and `DOCS_BASE_URL` from the Pages settings, so a custom domain needs no code change, but the published site links to the old address until the workflow runs again. It also sets `RELEASE_VERSION`, which turns on Google Analytics, search engine indexing and the version in the footer, and becomes the version of the installer at `/install`. Before the deployment, it installs an extension with that installer, which downloads the release archive from GitHub. The `github-pages` environment must allow the tag or branch that deploys.

## CI

`.github/workflows/eddy-test.yml` runs the suite across the `p0`-`p5` groups on Ubuntu and macOS, runs `p3` and `p4` once per WebDriver backend (Selenium on Ubuntu only, chromedriver on both), and validates `.eddy/tests/composer.json` and the tooling package's `composer.json` (validate + normalize) plus the PHP lint step in `p0`. A second job (`eddy-test-docs`) builds and tests the documentation site, uploads the coverage of its components to Codecov and deploys the site to Netlify, and a third (`eddy-test-actions`) lints the workflow YAML with `yamllint` and `actionlint` and checks it for security issues with Zizmor.

`.github/workflows/eddy-publish-tooling.yml` publishes `.eddy/tooling/` to the same-named branch of `drevops/eddy-tooling` on every push to `main` and to a branch whose name contains `eddy-tooling`.

`.github/workflows/eddy-release-docs.yml` publishes the documentation site to GitHub Pages on a release tag or a manual run (see [Documentation site](#documentation-site)).

`.github/workflows/deploy.yml` deploys `main` and release tags to [`drevops/eddy_destination`](https://github.com/drevops/eddy_destination) the way it deploys a generated project to Drupal.org. The `DEPLOY_BRANCH` repository variable sends `main` to `5.x` there, and the `DEPLOY_PROCEED` secret set to `1` lets the push through.

`install.php` deletes every `.github/workflows/eddy-*.yml` from a generated project, so a maintenance-only workflow takes the `eddy-` prefix and a workflow that ships to projects never does.

<!-- cspell:ignore ignorecontent -->
