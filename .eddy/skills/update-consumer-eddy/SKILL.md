---
name: update-consumer-eddy
description: Update a Drupal extension project to the latest version of Eddy
user-invocable: true
---

# Update Eddy

When this skill is triggered, follow the steps below to update the current project's infrastructure to the latest version of [Eddy](https://github.com/drevops/eddy).

## Step 0: Ensure required permissions

This skill requires several Bash commands to run without prompts. Before doing anything else, read `.claude/settings.local.json` (create it if it does not exist) and ensure the following entries are present in `permissions.allow`. Add only the missing ones:

```json
[
  "Bash(ahoy:*)",
  "Bash(cat:*)",
  "Bash(chmod:*)",
  "Bash(composer:*)",
  "Bash(cp:*)",
  "Bash(find:*)",
  "Bash(gh:*)",
  "Bash(git:*)",
  "Bash(grep:*)",
  "Bash(ls:*)",
  "Bash(make:*)",
  "Bash(mkdir:*)",
  "Bash(mv:*)",
  "Bash(php:*)",
  "Bash(rm:*)",
  "Bash(tar:*)"
]
```

If any entries were added, tell the user what was added and ask them to restart the session. Permissions are loaded at startup, so changes made mid-session do not take effect. **STOP here and do not continue** - the user must restart Claude Code and re-invoke the skill for the new permissions to apply.

If all entries are already present, proceed to Step 1.

## Step 1: Detect current project settings

Read the project to determine the init.php answers:

1. **Name**: Read from `*.info.yml` - the `name` field.
2. **Machine name**: The `*.info.yml` filename without extension.
3. **Type**: `module` or `theme` - from the `type` field in `*.info.yml`.
4. **CI provider** (4.x releases only): `gha` if `.github/workflows/` exists, `circleci` if `.circleci/` exists. Later releases support only GitHub Actions and do not ask.
5. **Command wrapper**: `ahoy` if `.ahoy.yml` exists and `makefile` if `Makefile` exists - `ahoy,makefile` when both exist, and an empty string when neither does.
6. **Drupal versions**: the Drupal majors in the CI matrix - the `drupal-version` values in `.github/workflows/test.yml`, or the `DRUPAL_VERSION` values in `.circleci/config.yml` for a project that still uses CircleCI - as a comma-separated list (e.g. `10,11`).
7. **Tools**: every tool whose file still exists - `phpcs` (`phpcs.xml`), `phpstan` (`phpstan.neon`), `rector` (`rector.php`), `twigcs` (`.twig-cs-fixer.php`), `eslint` (`.eslintrc.json`), `stylelint` (`.stylelintrc.js`), `cspell` (`.cspell.json`), `jest` (`jest.config.js`), `phpunit` (`phpunit.xml`), `functional_javascript` (`.devtools/browser`) and `renovate` (`renovate.json`).
8. **Cloudflare**: `true` if `scripts/start-cloudflared.sh` exists, `false` otherwise.

Also detect the **default branch** of the repository (not the current checkout):

```bash
git symbolic-ref --short refs/remotes/origin/HEAD
```

Strip the `origin/` prefix from the result. If `origin/HEAD` is not set, fall back to the branch configured in `.github/workflows/test.yml`.

## Step 2: Get the latest published scaffold version

```bash
gh release list --repo drevops/eddy --exclude-drafts --limit 5
```

`--exclude-drafts` is mandatory. Draft releases are unpublished work in progress and must never be selected, even when they carry the highest version number.

Take the topmost (newest) release from that list and use its tag **verbatim** - exactly as printed, including any prefix - for the branch name, the download and the commit message. Never reformat, shorten or normalise it.

Do not ask the user to confirm the version. If the user named a version when invoking the skill, use that version verbatim and skip the lookup.

## Step 3: Prepare the feature branch

Before making any changes, ensure the working tree is on the default branch and up to date, then create a feature branch.

1. Check that the update can't destroy uncommitted work:

```bash
git status --porcelain
```

Every line must be under `.claude/` or `.idea/`, the only paths Step 4 keeps. Anything else - a modified tracked file or an untracked one - would be deleted in Step 4, so stop and ask the user to commit or stash it first.

2. Switch to the default (main) branch detected in Step 1:

```bash
git checkout <main_branch>
```

3. Pull the latest changes:

```bash
git pull
```

4. Create and switch to a new feature branch. Use the release tag verbatim, as printed in Step 2 (e.g., `5.0.0`):

```bash
git checkout -b feature/update-eddy-<version>
```

This step is **mandatory** - never apply scaffold changes directly to the default branch.

## Step 4: Clean project root

Delete everything in the project root **except** `.claude/`, `.git/` and `.idea`.

**IMPORTANT:** This command MUST use a relative path (`.`) and be run from the project root. Using an absolute path causes `-name '.'` to fail to match the root directory, which results in the root directory itself (including `.git/`) being deleted. Ensure the shell working directory is the project root before running this command.

```bash
find . -maxdepth 1 ! -name '.' ! -name '.git' ! -name '.claude' ! -name '.idea' -exec rm -rf {} +
```

## Step 5: Download and extract scaffold

Download the release archive directly into the project root (not a git clone - the release archive has scaffold-only files removed):

```bash
gh release download <version> --repo drevops/eddy --archive tar.gz --output eddy.tar.gz
```

```bash
tar -xzf eddy.tar.gz --strip-components=1
```

```bash
rm eddy.tar.gz
```

## Step 6: Run init.php

Run init.php from the project root. Set `EDDY_REMOVE_SELF=true` and `EDDY_PROCEED=true` to auto-accept the confirmations:

```bash
EDDY_NAME='<Name>' \
EDDY_MACHINE_NAME='<machine_name>' \
EDDY_TYPE='<type>' \
EDDY_DRUPAL_VERSION='<drupal_version>' \
EDDY_COMMAND_WRAPPER='<command_wrapper>' \
EDDY_TOOLS='<tools>' \
EDDY_CLOUDFLARE=<cloudflare> \
EDDY_EXAMPLES=false \
EDDY_REMOVE_SELF=true \
EDDY_PROCEED=true \
php init.php
```

Wrap every value in single quotes, never double quotes. The values come from the project's own files, and double quotes still expand `$(...)`, backticks and `$VAR`. Write a single quote inside a value as `'\''`, so `O'Brien` becomes `'O'\''Brien'`.

**Every one of these variables is mandatory.** A prompt with no matching variable is not silently defaulted - it falls through to the interactive input loop and reads `STDIN`, which never returns under automation. Derive each value from the project's detected settings, and run `php init.php --help` for the full list and accepted values if the prompts change in a future release.

The `EDDY_` prefix applies to releases after 4.x. When the tag from Step 2 is a 4.x release, run `php init.php --help` first and use the prefix it lists instead (`DEX_` in 4.19.0, `PROMPTY_` before it). 4.x releases also ask for the CI provider, so pass that variable too, with the value from Step 1.

`EDDY_EXAMPLES=false` drops the scaffold's example lifecycle scripts. Step 7 restores `scripts/` from git straight after, so any hook the project actually tracks comes back untouched.

`<command_wrapper>` accepts a comma-separated list (`ahoy`, `makefile`, or `ahoy,makefile`), or an empty string for neither.

`<drupal_version>` is a comma-separated list of Drupal majors to target (e.g. `11`, `10,11` or `11,12`). `<tools>` is a comma-separated list of the tools to keep - list every tool the project still uses, since anything omitted is removed. `<cloudflare>` is `true` or `false`, and keeps or drops the Cloudflare tunnel scripts.

## Step 7: Restore project-specific files from git

The scaffold extraction overwrote project-specific files. Restore them from the previous commit:

```bash
git checkout HEAD -- \
  src/ \
  tests/ \
  config/ \
  scripts/ \
  composer.json \
  LICENSE \
  *.module \
  *.install \
  *.info.yml \
  *.services.yml \
  *.routing.yml \
  *.links.menu.yml \
  *.permissions.yml \
  *.libraries.yml
```

Only restore paths that actually exist in the project - skip any that produce errors.

## Step 8: Remove the scaffold examples

The scaffold ships a complete example extension - sample service, form, tests, assets, config schema and shell scripts - so that the template runs standalone. None of it belongs in a real project. Remove it on every update, without asking the user.

1. List what the extraction left behind:

```bash
git status --porcelain --untracked-files=all
```

2. Delete every file that is **both** untracked (`??` in that listing) **and** one of the example paths below. `init.php` renames the example to the project's machine name, so match by shape rather than by literal name (`<machine_name>` in file names, `<MachineName>` in class names):

- `src/<MachineName>Service.php`
- `src/Form/<MachineName>Form.php`
- `tests/src/Functional/<MachineName>FunctionalTest.php`
- `tests/src/FunctionalJavascript/<MachineName>FunctionalJavascriptTestBase.php`
- `tests/src/FunctionalJavascript/<MachineName>SmokeFunctionalJavascriptTest.php`
- `tests/src/Kernel/<MachineName>ServiceKernelTest.php`
- `tests/src/Unit/<MachineName>ServiceUnitTest.php`
- `config/schema/<machine_name>.schema.yml`
- `css/<machine_name>.css`
- `js/<machine_name>.js` and `js/<machine_name>.test.js`
- `<machine_name>.module`, `<machine_name>.install`, `<machine_name>.libraries.yml`, `<machine_name>.links.menu.yml`, `<machine_name>.routing.yml`, `<machine_name>.services.yml`
- `scripts/assemble-example.sh`, `scripts/provision-example.sh`, `scripts/start-example.sh`, `scripts/stop-example.sh`

**Both conditions are required at every deletion, with no exceptions.** A file at one of these paths that git already tracks is the project's own code restored in Step 7 - it may be a service grown out of the example, or a lifecycle hook adapted from one - so leave it alone. A path outside this list is never deleted here, however example-like it looks.

3. Remove directories left empty by the deletions (e.g. `css/`, `js/`, `src/Form/`). Never leave an empty directory in the tree.

4. Grep `package.json`, `jest.config.js` and the stylelint config for every path deleted above, and remove or repoint each reference you find. Do this unconditionally - a project with JavaScript and CSS of its own can still carry a script or a glob aimed at a deleted example asset, and an empty glob is not an error for most of these tools, so Step 11 will not reliably surface it.

## Step 9: Review changes

Use `git diff` and `git status` to review all changes. Pay attention to:

- **Branch references**: Replace scaffold default branch (`1.x`) with the project's main branch in workflow files and README.
- **README.md**: Rebuild from the scaffold template (see README section below).
- **Removed files**: If `git status` shows deleted files that were old scaffold infrastructure (e.g., `phpmd.xml`), confirm they are intentionally removed in the new scaffold version.
- **New files**: Review any new files from the scaffold to ensure they are infrastructure, not placeholder stubs. Anything the example extension missed in Step 8 - a generic service class, form class or test stub - is removed here, under the same guard: untracked only, and only when the file is demonstrably scaffold boilerplate rather than project code.

### README.md and CONTRIBUTING.md handling

The `README.md` must follow the scaffold template structure exactly. Do NOT simply patch path references - instead, rebuild it from the scaffold's `README.md` template:

1. Start with the scaffold's `README.md` as the base structure.
2. Replace scaffold placeholder values with project-specific values:
   - Badge URLs (GitHub org/repo).
   - Logo URL or image.
   - Project title/description (from `*.info.yml` and existing README).
3. Insert project-specific content sections (e.g., "Use case", "How it works", "Installation") between the header and the "Contributing" section.
4. Keep the scaffold's development sections verbatim. They live in `CONTRIBUTING.md` (Local development, Building website, Drupal versions, Coding standards, Testing), which `init.php` regenerates from the scaffold: carry over any project-specific notes from the previous version (`git show HEAD:CONTRIBUTING.md`).
5. Remove badges for tools the project does not use.
6. Fix the Eddy link at the bottom to point to the Eddy repo, not the project repo.

## Step 10: Commit

Stage all changes and commit:

```text
Updated scaffold to <version>.
```

## Step 11: Build, lint, and test

Run the full build pipeline through the command wrapper detected in Step 1 to verify nothing is broken. With Ahoy, alone or next to a Makefile:

```bash
ahoy build
```

For a Makefile-only project:

```bash
make build
```

With neither wrapper, run the devtools scripts as separate commands:

```bash
.devtools/assemble
```

```bash
.devtools/start
```

```bash
.devtools/provision
```

Then run linting and tests through the same wrapper - `ahoy lint` and `ahoy test`, or `make lint` and `make test` for a Makefile-only project:

```bash
ahoy lint
```

```bash
ahoy test
```

A project with neither wrapper has no local lint or test command, so its lint and tests run only in the pull request's CI (Step 12).

Both lint **warnings** and **errors** must be fixed - warnings are not accepted. Same applies to tests: all warnings and failures must be resolved before proceeding. Fix the issues and create additional commits.

### PHPUnit doc-comment deprecations

Drupal 10 runs PHPUnit 9.6, which reads only doc-comment annotations such as `@covers`, `@group` and `@dataProvider`. PHPUnit 11 (used by Drupal 11) reports those annotations as deprecated, and PHPUnit 12 (used by Drupal 12) ignores them entirely. **Keep both forms**, the way the scaffold's example tests do: the doc-comment annotation for Drupal 10 and the matching PHP attribute (`#[Group]`, `#[DataProvider]`, and so on) for Drupal 11 and 12. Do not drop the attributes: a test that relies on a doc-comment `@dataProvider` alone loses its data provider on Drupal 12. The `phpstan.neon` ignore for missing `PHPUnit\Framework\Attributes` classes keeps the Drupal 10 lint passing.

## Step 12: Open PR

Push the branch and open a pull request. Use the `/open-pr` skill or create the PR manually with a summary of all changes.

## Important notes

- When the user names a version, use that tag verbatim. Otherwise take the newest published release - never a draft. Either way, use the tag verbatim and start without asking the user to confirm it.
- Always remove the scaffold's example extension and example scripts - a real project never ships them.
- Never overwrite project-specific code (src/, tests/, config/, *.module, etc.).
- After copying workflow files, always verify branch references match the project.
- If the devtools scripts changed format (e.g., Bash to PHP), remove the old files and copy the new ones - do not try to merge them.
- Run the full test suite before opening the PR to catch regressions.
- Prefer `ahoy` or `Makefile` commands over running tools directly. For example, use `ahoy lint` instead of `composer lint`, `ahoy test` instead of `composer test`, `ahoy build` instead of running `.devtools/*` scripts manually. Only fall back to direct commands when no `ahoy` or `Makefile` equivalent exists.

## Working directory rules

- **Never `cd` into the `build/` directory** to run commands. All commands (`ahoy lint`, `ahoy test`, `ahoy build`, etc.) must be run from the project root directory.
- **Never use absolute paths** to run commands. Use relative paths or let `ahoy`/`make` handle path resolution.

## Command pattern rules

Commands must start with a simple keyword that matches the allowed permission prefixes (e.g., `git`, `php`, `rm`, `cp`, `ahoy`). Avoid patterns that trigger CLI approval prompts:

NEVER use compound or composite commands in a single Bash tool call. Every Bash call must contain exactly ONE simple command. No exceptions.

**NEVER use:** `&&`, `||`, `;`, `|`, `<<<`, `$(...)`, heredocs.

**ALWAYS:**
- Use multiple separate Bash tool calls, one command per call
- Use non-interactive flags or env vars for scripts that support them (e.g. `composer --no-interaction`, `EDDY_*` for `init.php`)
- For git commits, use: `git commit -m "Message here."`
