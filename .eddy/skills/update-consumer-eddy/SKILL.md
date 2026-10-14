---
name: update-consumer-eddy
description: Update a Drupal extension project to the latest version of Eddy
user-invocable: true
---

# Update Eddy

When this skill is triggered, follow the steps below to update the current project's infrastructure to the latest version of [Eddy](https://github.com/drevops/eddy).

The update is a 3-way merge. It renders the release the project is on and the new release with the project's own answers, then applies the difference between the 2 renders to the project. What the project changed on top of the template stays - its own files, the versions Renovate bumped, notes in its docs - and only lines that both sides changed come up as conflicts. Files git ignores, such as `.env` and `build/`, are never touched.

## Step 0: Ensure required permissions

This skill requires several Bash commands to run without prompts. Before doing anything else, read `.claude/settings.local.json` (create it if it does not exist) and ensure the following entries are present in `permissions.allow`. Add only the missing ones:

```json
[
  "Bash(ahoy:*)",
  "Bash(cat:*)",
  "Bash(cd:*)",
  "Bash(chmod:*)",
  "Bash(composer:*)",
  "Bash(cp:*)",
  "Bash(curl:*)",
  "Bash(find:*)",
  "Bash(gh:*)",
  "Bash(git:*)",
  "Bash(grep:*)",
  "Bash(ls:*)",
  "Bash(make:*)",
  "Bash(mkdir:*)",
  "Bash(mv:*)",
  "Bash(npm:*)",
  "Bash(php:*)",
  "Bash(rm -rf .artifacts/tmp/eddy-update)",
  "Bash(rm -rf .artifacts/tmp/eddy-update/base)",
  "Bash(rm -rf .claude/skills/update-consumer-drupal-extension-scaffold)",
  "Bash(tar:*)"
]
```

Each `rm` entry is 1 exact command the skill runs, with no wildcard, so none of them approves removing anything else: the scratch directory in Steps 4 and 12, the base render in Step 5, and the 4.x skill directory in Step 9. When `permissions.allow` also holds `Bash(rm:*)`, tell the user that the skill doesn't need it, so they can remove it if nothing else does.

If any entries were added, tell the user what was added and ask them to restart the session. Permissions are loaded at startup, so changes made mid-session do not take effect. **STOP here and do not continue** - the user must restart Claude Code and re-invoke the skill for the new permissions to apply.

Claude Code can refuse the write: its auto mode blocks an agent from granting itself permissions. In that case, print the missing entries as a JSON list for the user to add to `permissions.allow` themselves. If the session runs commands without asking for approval anyway, as auto mode does, proceed to Step 1 without them. Otherwise stop, and ask the user to add the entries and restart Claude Code.

If all entries are already present, proceed to Step 1.

## Step 1: Detect current project settings

The update applies to the default branch as it is on the remote, so read every answer from there, not from whatever happens to be checked out. A pull can bring in new settings, or a newer scaffold update that changes the base of the merge.

Detect the **default branch** of the repository (not the current checkout):

```bash
git symbolic-ref --short refs/remotes/origin/HEAD
```

Strip the `origin/` prefix from the result. If `origin/HEAD` is not set, fall back to the branch configured in `.github/workflows/test.yml`.

Check that nothing is pending:

```bash
git status --porcelain
```

The only lines allowed are untracked paths (`??`) under `.claude/` or `.idea/`. Anything else - a change to a tracked file anywhere, or an untracked file elsewhere - would block the merge in Step 7 or end up in the update commit, so stop and ask the user to commit or stash it first. Files git ignores, such as `.env` and `build/`, do not show up here, and the update never touches them.

Switch to the default branch and pull the latest changes:

```bash
git checkout <main_branch>
```

```bash
git pull
```

Then read the project to determine the answers for the installation script:

1. **Name**: Read from `*.info.yml` - the `name` field.
2. **Machine name**: The `*.info.yml` filename without extension.
3. **Type**: `module` or `theme` - from the `type` field in `*.info.yml`.
4. **CI provider** (4.x releases only): `gha` if `.github/workflows/` exists, `circleci` if `.circleci/` exists. Later releases support only GitHub Actions and do not ask.
5. **Command wrapper**: `ahoy` if `.ahoy.yml` exists and `makefile` if `Makefile` exists - `ahoy,makefile` when both exist, and an empty string when neither does.
6. **Drupal versions**: the Drupal majors in the CI matrix - the `drupal-version` values in `.github/workflows/test.yml`, or the `DRUPAL_VERSION` values in `.circleci/config.yml` for a project that still uses CircleCI - as a comma-separated list (e.g. `10,11`).
7. **Tools**: every tool whose file still exists - `phpcs` (`phpcs.xml`), `phpstan` (`phpstan.neon`), `rector` (`rector.php`), `twigcs` (`.twig-cs-fixer.php`), `eslint` (`.eslintrc.json`), `stylelint` (`.stylelintrc.js`), `cspell` (`.cspell.json`), `jest` (`jest.config.js`), `phpunit` (`phpunit.xml`), `functional_javascript` (a `tests/src/FunctionalJavascript/` directory) and `renovate` (`renovate.json`). The `functional-javascript` test suite in `phpunit.xml` doesn't tell whether the project has FunctionalJavascript tests: releases before 4.16.3 ship it with or without them, and a project can delete its tests and keep the suite.
8. **Cloudflare** (4.18.0 and 4.19.0 only): `true` if `scripts/start-cloudflared.sh` exists, `false` otherwise. Later releases run the tunnel from the tooling package and do not ask.
9. **GitHub repository**: the `owner/name` of the project's GitHub repository, such as `AlexSkrypnyk/drupal_helpers`, which the README badges point to:

```bash
gh repo view --json nameWithOwner --jq .nameWithOwner
```

These answers describe the project as it is now, and Step 5 renders the installed version with them unchanged. When the user asks to add a Drupal major, such as "include Drupal 12", or to drop one, change the Drupal versions for Step 4 only. Step 7 applies the difference between the 2 renders, so the project gains or loses the major's CI jobs, badges and docs only when the 2 renders disagree on it. For Drupal 10 that includes `phpunit.d10.xml`, so dropping Drupal 10 removes the file.

Also detect the **installed version** - the Eddy release the project was created from or last updated to, which Step 5 renders as the base of the merge. Take `extra.eddy.version` from `composer.dev.json` when it is set. Otherwise list the commits that updated the scaffold, newest first:

```bash
git log -i --grep=scaffold --format='%h %s'
```

Take the version from the newest subject that names one, such as `Updated scaffold to 4.18.0.`, with or without a pull request number after it. When neither source names a version, the installed version is unknown.

A version that ends in `-<count>-g<hash>`, such as `5.0.0-3-g1a2b3c4`, comes from a branch download rather than a release. `git describe` printed it for the commit the project was created from: the last tag before that commit (`5.0.0`), the number of commits after the tag (`3`) and, after the `g`, the abbreviated hash of the commit (`1a2b3c4`). Wherever a later step compares the installed version with a release, compare the tag: the project already has the release with that tag, and a later release is newer. No release carries such a version, so Steps 4 and 5 download the archive of the commit instead.

## Step 2: Get the latest published scaffold version

```bash
gh release list --repo drevops/eddy --exclude-drafts --exclude-pre-releases --limit 10
```

`--exclude-drafts` is mandatory. Draft releases are unpublished work in progress and must never be selected, even when they carry the highest version number. `--exclude-pre-releases` skips the releases marked as pre-releases on GitHub, so a project moves to one only when its maintainer asks for it. It goes by that mark, not by the tag: the list still includes a release published without the mark, even when its tag carries a pre-release suffix such as `-alpha2` or `-rc1`.

Take the release with the highest version from that list. It is usually the topmost, but the list is ordered by publication date, and a patch for an older line can be published after a newer release. Use its tag **verbatim** - exactly as printed, including any prefix - for the branch name, the download and the commit message. Never reformat, shorten or normalise it.

Do not ask the user to confirm the version. If the user named a version when invoking the skill, use that version verbatim and skip the lookup, whether or not it is a pre-release.

Compare the version with the installed version from Step 1, or with its tag when it comes from a branch download. When the project is already on that release or a newer one - such as a pre-release it moved to on purpose - tell the user there is nothing newer to update to and stop: a merge from a newer base would apply the difference backwards. The exception is a user who asked to add or drop a Drupal major: take the installed version as the release instead, so the update changes the majors and nothing else.

## Step 3: Create the feature branch

Create and switch to a new feature branch off the default branch that Step 1 checked out and pulled. Use the release tag verbatim, as printed in Step 2 (e.g., `5.0.0`):

```bash
git checkout -b feature/update-eddy-<version>
```

This step is **mandatory** - never apply scaffold changes directly to the default branch.

## Step 4: Render the release

The installer rewrites every file under the directory it runs in, so it never runs in the project root. It runs on a copy of the release in a scratch directory instead, and Step 7 merges the result into the project.

1. Remove any scratch directory an earlier run left behind, then create the scratch directories. Renders extracted over old ones would keep files that the releases never shipped:

```bash
rm -rf .artifacts/tmp/eddy-update
```

```bash
mkdir -p .artifacts/tmp/eddy-update/base .artifacts/tmp/eddy-update/new
```

2. Write `.artifacts/tmp/eddy-update/.gitignore` with the single line `*`. Git then ignores the scratch directory, so `git status` never lists it and `git add -A` never stages it.

3. Download the release archive and extract it into `new` (not a git clone - the archive expands the version that `install.php` records as `extra.eddy.version`):

```bash
gh release download <version> --repo drevops/eddy --archive tar.gz --output .artifacts/tmp/eddy-update/new.tar.gz
```

Step 2 takes a version from a branch download as the release only when the user asks to add or drop a Drupal major. That version has no release, so download the archive of its commit instead, with `<commit>` the hash after the `g` (see Step 1):

```bash
curl -fsSL -o .artifacts/tmp/eddy-update/new.tar.gz https://github.com/drevops/eddy/archive/<commit>.tar.gz
```

Then extract the archive:

```bash
tar -xzf .artifacts/tmp/eddy-update/new.tar.gz --strip-components=1 -C .artifacts/tmp/eddy-update/new
```

4. Run `install.php` from inside the render, with `INSTALL_REMOVE_SELF=true` and `INSTALL_PROCEED=true` to auto-accept the confirmations, then return to the project root. Run these 3 commands one after another, never in parallel, so the installer starts only once the shell is inside the render:

```bash
cd .artifacts/tmp/eddy-update/new
```

```bash
INSTALL_NAME='<Name>' \
INSTALL_MACHINE_NAME='<machine_name>' \
INSTALL_TYPE='<type>' \
INSTALL_DRUPAL_VERSION='<drupal_version>' \
INSTALL_COMMAND_WRAPPER='<command_wrapper>' \
INSTALL_TOOLS='<tools>' \
INSTALL_EXAMPLES=false \
INSTALL_REMOVE_SELF=true \
INSTALL_PROCEED=true \
INSTALL_GITHUB_REPOSITORY='<repository>' \
php install.php
```

```bash
cd ../../../..
```

If the installer fails, still `cd` back to the project root, then stop and report the error: there is nothing to merge without the render.

Wrap every value in single quotes, never double quotes. The values come from the project's own files, and double quotes still expand `$(...)`, backticks and `$VAR`. Write a single quote inside a value as `'\''`, so `O'Brien` becomes `'O'\''Brien'`.

**Every one of these variables is mandatory.** A prompt with no matching variable is not silently defaulted - it falls through to the interactive input loop and reads `STDIN`, which never returns under automation. Derive each value from the project's detected settings, and run `php install.php --help` for the full list and accepted values if the prompts change in a future release.

The script is `install.php` with the `INSTALL_` prefix in releases after 4.x. A 4.x release ships it as `init.php` instead: run `php init.php --help` first, then run `php init.php` with the prefix it lists (`DEX_` in 4.19.0, `PROMPTY_` before it). 4.x releases also ask for the CI provider, and 4.18.0 and 4.19.0 whether to keep the Cloudflare tunnel scripts, so pass those variables too, with the values from Step 1.

`INSTALL_EXAMPLES=false` drops the scaffold's example lifecycle scripts. The project's own hooks in `scripts/` never take part in the merge, so they stay as they are.

`<command_wrapper>` accepts a comma-separated list (`ahoy`, `makefile`, or `ahoy,makefile`), or an empty string for neither.

`<drupal_version>` is a comma-separated list of Drupal majors to target (e.g. `11`, `10,11` or `11,12`): the list from Step 1, with any major the user asked to add or drop. `<tools>` is a comma-separated list of the tools to keep - list every tool the project still uses, since anything omitted is removed.

`<repository>` is the GitHub repository from Step 1. Without it, the badges in the render name the machine name as both the owner and the repository, and every release that changes a badge conflicts with the project's real values. A release that doesn't read `INSTALL_GITHUB_REPOSITORY` ignores it and renders the machine name.

## Step 5: Render the installed version

Step 7 merges against a render of the version the project is on, which is how it tells the project's own changes to the template's files apart from the new release's.

Skip this step and leave `base` empty when Step 1 found no installed version, or found one older than 4.14.0: earlier releases don't read their answers from environment variables. Step 7 then merges without a base.

Otherwise render the installed version into `base` the way Step 4 renders the new release:

```bash
gh release download <installed_version> --repo drevops/eddy --archive tar.gz --output .artifacts/tmp/eddy-update/base.tar.gz
```

A version from a branch download renders from its commit, not its tag: a render of the tag lacks the commits after it, so the merge would count their changes as the project's own. Download the archive of the commit instead, as Step 4 does:

```bash
curl -fsSL -o .artifacts/tmp/eddy-update/base.tar.gz https://github.com/drevops/eddy/archive/<commit>.tar.gz
```

Then extract the archive and enter the render:

```bash
tar -xzf .artifacts/tmp/eddy-update/base.tar.gz --strip-components=1 -C .artifacts/tmp/eddy-update/base
```

```bash
cd .artifacts/tmp/eddy-update/base
```

Run the installer of that version here with the answers from Step 1 as they are, `INSTALL_GITHUB_REPOSITORY` included, under the script name and prefix that version uses (see Step 4), then return to the project root. As in Step 4, run each command only after the previous one finishes:

```bash
cd ../../../..
```

The Drupal versions stay the ones the project tests now, even when Step 4 adds or drops a major. With the change in both renders, the merge would apply none of it. A 4.x installer also mishandles `12`: 4.19.0 rejects it, which leaves Step 7 without a base, 4.17.0 and 4.18.0 write it into the assemble default, and earlier releases ignore it.

If the download or the installer fails, return to the project root, empty `base` and carry on without a base:

```bash
rm -rf .artifacts/tmp/eddy-update/base
```

```bash
mkdir .artifacts/tmp/eddy-update/base
```

## Step 6: Keep the extension's own files out of the merge

The scaffold ships a complete example extension - sample service, form, tests, assets, config schema and lifecycle scripts - so that the template runs standalone. Those paths hold the project's own code, so Step 7 leaves them out of both renders' commits with these exclude pathspecs, `<machine_name>` replaced:

```text
':!src' ':!tests' ':!config' ':!css' ':!js' ':!composer.json' ':!logo.png' ':!<machine_name>.*' ':!scripts/*-example.sh'
```

The merge then never touches the project's code, none of the examples reach the project, and nothing has to be deleted from the renders. `<machine_name>.*` covers the renamed `.info.yml`, `.module`, `.install`, `.libraries.yml`, `.links.menu.yml`, `.routing.yml` and `.services.yml` files. `composer.json` holds the project's own dependencies, and `logo.png` is a placeholder the installer downloads.

## Step 7: Merge the release into the project

Commit the 2 renders to a throwaway repository - the installed version first, the new release on top - and cherry-pick the second commit into the project. Git merges every file 3 ways: it takes the lines only the release changed, keeps the lines only the project changed, and marks the lines both changed as conflicts. Files the release added appear, files it removed go, and files only the project has are never touched.

```bash
git -C .artifacts/tmp/eddy-update init -q
```

```bash
git -C .artifacts/tmp/eddy-update/base --git-dir=../.git --work-tree=. -c core.excludesFile=/dev/null add -A -- . ':!src' ':!tests' ':!config' ':!css' ':!js' ':!composer.json' ':!logo.png' ':!<machine_name>.*' ':!scripts/*-example.sh'
```

```bash
git -C .artifacts/tmp/eddy-update -c user.name=Eddy -c user.email=eddy@example.com commit -q --allow-empty --no-verify --no-gpg-sign -m 'Base'
```

```bash
git -C .artifacts/tmp/eddy-update/new --git-dir=../.git --work-tree=. -c core.excludesFile=/dev/null add -A -- . ':!src' ':!tests' ':!config' ':!css' ':!js' ':!composer.json' ':!logo.png' ':!<machine_name>.*' ':!scripts/*-example.sh'
```

```bash
git -C .artifacts/tmp/eddy-update -c user.name=Eddy -c user.email=eddy@example.com commit -q --no-verify --no-gpg-sign -m 'Eddy <version>'
```

```bash
git fetch -q .artifacts/tmp/eddy-update HEAD
```

```bash
git -c merge.conflictStyle=zdiff3 cherry-pick --no-commit -X no-renames FETCH_HEAD
```

The identity, the flags that skip commit hooks and signing, and the empty `core.excludesFile` keep the throwaway commits independent of the user's git setup. Without the last one, a pattern in the user's global excludes file that matches a template file, such as `CLAUDE.md`, keeps that file out of both commits, and the release's changes to it never reach the project. Each `add` runs inside its render, with the throwaway repository's `.git` 1 level up, so the pathspecs after `--` resolve against the render's root: `.` stages the whole render, and each `:!` entry leaves out a path from Step 6. Neither commit reaches the project's history: `--no-commit` stages the merged result instead. `-X no-renames` stops git from pairing a file the release removed with an unrelated file it added. The conflict style shows the base between the 2 sides of every conflict. It needs git 2.35 or newer - with an older git, set `merge.conflictStyle=diff3` instead, which shows the same base around larger regions. Without a base, run the cherry-pick without the `-c` option: there is no base to show, and the default style splits the differences in a file into separate conflicts instead of 1 conflict per file.

A cherry-pick with conflicts exits non-zero and lists them, and Step 8 resolves them. If the second commit finds nothing to commit, the 2 renders are identical, so the release changes nothing for this project: tell the user and stop.

Without a base, the first commit is empty. Every file the project shares with the release then conflicts wherever the 2 differ, and files the older release had but the new one dropped stay in the project: Step 9 removes the 4.x ones, and Step 11 lists the rest.

## Step 8: Resolve the conflicts

List the conflicted files:

```bash
git diff --name-only --diff-filter=U
```

In each conflict, the lines after `<<<<<<< HEAD` are the project's and the lines after `=======` are the release's. With a base, the lines after `|||||||` show the base between them. Resolve every conflict in place:

- With a base, compare each side with it to see what each side changed. Keep the project's change and apply the release's change next to it. Where the project's side only holds older template text - wording or formatting from an earlier release that the project never edited - take the release's side.
- Without a base, judge each difference from the 2 sides alone: keep what is specific to the project and take the release's text for the rest.
- When both sides moved the same version pin - an npm range, or the digest of a GitHub Action or a Docker image - keep the newer one. Renovate updates the project after the release is cut, so it is usually the project's. This lists the commits that moved the project past the release's pin, if there are any:

```bash
git log -S'<release_pin>' --oneline -- <file>
```

- Leave `package-lock.json` to the lock file step below.
- A file the release removed and the project changed (`UD` in `git status --porcelain`) goes with the release: remove it with `git rm`, and note it for the pull request as a change that needs a new home (see Step 9).
- A template file the project removed and the release changed (`DU`) stays removed: remove it with `git rm`.

Resolve each conflict by editing it. Restoring a whole file, as the lock file step does or with `git checkout --ours` or `--theirs`, loses the changes that merged cleanly from the other side, so do it for nothing but the lock file and `scripts/eddy-tooling`. While `.gitattributes` holds conflicts, git warns that its conflict markers are not valid attribute names, so resolve it first.

Then settle `package-lock.json`, whether or not it conflicted. A lock file never merges as text: even a merge that found no conflict in it can leave it out of step with `package.json`. When the project has one, restore the project's copy:

```bash
git checkout HEAD -- package-lock.json
```

If the merged `package.json` differs from the project's (`git diff HEAD -- package.json` prints anything), bring the lock file in line with it:

```bash
npm install --package-lock-only
```

Then take `scripts/eddy-tooling` from the release, whether or not it conflicted: projects use it as-is from Eddy and never modify it. This shows what the project changed in it, if anything:

```bash
git diff FETCH_HEAD -- scripts/eddy-tooling
```

List any change it shows for the pull request description, as a change that belongs in a patch for the `drevops/eddy-tooling` package in `composer.dev.json`. Then restore the release's copy:

```bash
git checkout FETCH_HEAD -- scripts/eddy-tooling
```

Stage each file once it is resolved (`git add <file>`), until the listing above prints nothing.

## Step 9: Remove what 5.x dropped

Run this step only for a project that comes from a 4.x release: the installed version from Step 1 is 4.x, or it is unknown and the project still has a `.devtools/` directory. Skip it for a project on 5.0.0 or later. No 5.x release ships these paths, so on such a project they are the project's own files, and the merge leaves them alone.

These paths belong to 4.x releases. With a base, Step 7 already removed the ones the project kept unchanged. Without a base, nothing has removed them:

```bash
git rm -r -q --ignore-unmatch .devtools .circleci 'scripts/*-cloudflared.sh'
```

```bash
rm -rf .claude/skills/update-consumer-drupal-extension-scaffold
```

The last directory holds the 4.x update skill, or the 404 page the 4.x `AGENTS.md` saved in its place. 5.x no longer ignores it by name, so a project that commits its own skills would commit it too.

A change the project made in these files needs a new home: a `.devtools/` or Cloudflare hook change becomes a lifecycle script in `scripts/` or a patch for the `drevops/eddy-tooling` package in `composer.dev.json`, and CircleCI's deployment settings become GitHub Actions secrets. [The upgrade guide](https://eddy.drevops.com/updating/upgrading-from-4x) explains each. Do not move them here - list the changed files in the pull request description. With a base, Step 8's modify/delete conflicts name them. Without one, this lists the commits that touched them, template updates included:

```bash
git log --oneline -- .devtools .circleci 'scripts/*-cloudflared.sh'
```

## Step 10: Repoint references to the example extension

Step 6 kept the example files out of the project, but the release's docs and configs can still name them. The generated `CONTRIBUTING.md`, for one, tells readers to extend `<MachineName>FunctionalJavascriptTestBase`, which a project whose base class has another name does not have. Grep the docs and the configs for every example name:

```bash
grep -s -n -F -e '<MachineName>Service' -e '<MachineName>Form' -e '<MachineName>FunctionalTest' -e '<MachineName>FunctionalJavascriptTestBase' -e '<MachineName>SmokeFunctionalJavascriptTest' -e '<machine_name>.schema.yml' -e '<machine_name>.css' -e '<machine_name>.js' -e '<machine_name>.test.js' -e '-example.sh' CONTRIBUTING.md AGENTS.md README.md package.json jest.config.js .stylelintrc.js
```

For each hit that names a file the project does not have, repoint it to the project's equivalent - such as its own FunctionalJavascript base class - or remove it. Do this on every update: an empty glob is not an error for most of these tools, and no lint or test checks a class name in the docs, so Step 13 will not surface a stale one.

## Step 11: Review changes

Use `git diff HEAD` and `git status` to review all changes. Pay attention to:

- **Conflict markers**: none may be left. This lists any in tracked files:

```bash
git grep -n -e '^<<<<<<< ' -e '^>>>>>>> '
```

- **PHP versions**: the `drevops/eddy-tooling` package requires PHP 8.3 or newer, so a job in `.github/workflows/test.yml` with a lower `php-version`, such as Drupal 10 on PHP 8.2, fails at "Install the tooling". The merge keeps the project's matrix, so list those jobs for the pull request and tell the user about them. Leave them as they are: raising them drops a PHP version the extension tests, and that's the user's call.
- **Branch references**: both renders ran in the scratch directory, where `install.php` finds no committed `test.yml`, so they filter the workflows on `1.x`. The merge keeps the branches the project lists wherever the 2 renders agree, and without a base, Step 8 resolved them as conflicts. Confirm that `.github/workflows/test.yml` and `.github/workflows/draft-release-notes.yml` list the branches the project builds from.
- **README.md and CONTRIBUTING.md**: check them as described below.
- **Added files**: review the files the release added (`A` in `git status --porcelain`). Without a base, they can include template files the project once removed on purpose - remove those again.
- **Leftover template files**: without a base, files that older releases shipped and this one does not, such as `phpmd.xml`, stay in the project. Leave them, and list them in the pull request description so the maintainer can decide.

### README.md and CONTRIBUTING.md handling

The merge keeps the project's own content in both files and applies the release's changes around it. Check that the result still matches the release's structure:

1. `README.md` follows the release's `README.md`, with the project's values: badge URLs (GitHub org/repo), logo, and the project title and description (from `*.info.yml` and the existing README).
2. The project's own sections (e.g., "Use case", "How it works", "Installation") sit between the header and the "Contributing" section.
3. Badges appear only for the tools the project uses, the Drupal majors it tests and the PHP versions those majors support.
4. The Eddy link at the bottom points to the Eddy repo, not the project repo.
5. `CONTRIBUTING.md` holds the release's development sections (Local development, Building website, Drupal versions, Coding standards, Testing) and keeps the project-specific notes from the previous version (`git show HEAD:CONTRIBUTING.md`).

Without a base, the merge cannot tell the project's content from older template text, so rebuild both files from the release's copies in `.artifacts/tmp/eddy-update/new` against the same checklist, carrying the project's content over.

## Step 12: Commit

Remove the scratch directory first. No later step needs the renders or the throwaway repository:

```bash
rm -rf .artifacts/tmp/eddy-update
```

```bash
find .artifacts -type d -empty -delete
```

Then stage the update. The merge already staged the files the release added, so staging the changes to tracked files completes it, and untracked files under `.claude/` and `.idea/` stay out of the commit. Add a file you created yourself by its path:

```bash
git add -u
```

Commit with this message:

```text
Updated scaffold to <version>.
```

Keep this message as it is: when `composer.dev.json` holds no `extra.eddy.version`, the next update reads the installed version from it in Step 1.

### Code changes for a new Drupal major

Skip this when Step 4 added no Drupal major. When it did, the merge brought in the major's CI jobs, badge and docs, but none of the changes the extension's own code needs: Step 6 kept `*.info.yml` and `tests/` out of the merge. Step 13 builds the new major too, so make them now, each in a commit of its own:

- Add `^<major>` to `core_version_requirement` in every `*.info.yml` the project tracks, submodules and test modules included. A submodule the tests enable fails to install without it. This lists them:

```bash
git ls-files '*.info.yml'
```

- For Drupal 12, also make the other changes in the "Drupal 12" section of [the upgrade guide](https://eddy.drevops.com/updating/upgrading-from-4x#drupal-12): `string|false` as the return type of every `getMinkDriverArgs()` override, such as the one in the FunctionalJavascript base class the template generated, and the PHPUnit attributes that the note at the end of Step 13 describes.

A dropped major needs no change for the build, so leave `core_version_requirement` as it is and mention the choice in the pull request.

## Step 13: Build, lint, and test

Run the full build pipeline through the command wrapper detected in Step 1 to verify nothing is broken. With Ahoy, alone or next to a Makefile:

```bash
ahoy build
```

For a Makefile-only project:

```bash
make build
```

With neither wrapper, install the tooling and run its commands separately. They are PHP scripts, so run them through `php` to match the permissions from Step 0:

```bash
php scripts/eddy-tooling
```

```bash
php vendor/bin/eddy-assemble
```

```bash
php vendor/bin/eddy-start
```

```bash
php vendor/bin/eddy-provision
```

Then run linting and tests through the same wrapper - `ahoy lint` and `ahoy test`, or `make lint` and `make test` for a Makefile-only project:

```bash
ahoy lint
```

```bash
ahoy test
```

A project with neither wrapper has no local lint or test command, so its lint and tests run only in the pull request's CI (Step 14).

Both lint **warnings** and **errors** must be fixed - warnings are not accepted. Same applies to tests: all warnings and failures must be resolved before proceeding. Fix the issues and create additional commits.

### A new Drupal major

The build above uses the project's default major, `extra.eddy.drupal-version` in `composer.dev.json`, which stays on the newest major the project tests that has a stable release. When Step 4 added a major, build, lint and test that one as well, the same way, with `DRUPAL_VERSION` set to it:

```bash
DRUPAL_VERSION=<major> ahoy build
```

Then run `ahoy lint` and `ahoy test` again. Without a wrapper, put `DRUPAL_VERSION=<major>` in front of `php vendor/bin/eddy-assemble`.

Drupal 12 requires PHP 8.5. On an older PHP, the build stops before it changes anything and says which PHP the release needs. Don't work around it: tell the user, and leave that major to the pull request's CI. Failures that only the new major shows usually come from the breaks in the "Drupal 12" section of [the upgrade guide](https://eddy.drevops.com/updating/upgrading-from-4x#drupal-12), such as an update path test that names a database dump Drupal 12 no longer ships.

### PHPUnit doc-comment deprecations

Drupal 10 runs PHPUnit 9.6, which reads only doc-comment annotations such as `@covers`, `@group` and `@dataProvider`. PHPUnit 11 (used by Drupal 11) reports those annotations as deprecated, and PHPUnit 12 (used by Drupal 12) ignores them entirely. **Keep both forms** of the 2 annotations that affect which tests run and how, the way the scaffold's example tests do: `@group` with `#[Group]`, and `@dataProvider` with `#[DataProvider]`. Do not drop the attributes: a test that relies on a doc-comment `@dataProvider` alone loses its data provider on Drupal 12. `@covers` and `@coversDefaultClass` only narrow code coverage, so the example tests keep them as annotations without an attribute. Kernel, Functional and FunctionalJavascript test classes also need `#[RunTestsInSeparateProcesses]`, which the example tests carry without an annotation: Drupal 11.3 deprecates a test class without it, and Drupal 12 throws an exception. The `phpstan.neon` ignore for missing `PHPUnit\Framework\Attributes` classes keeps the Drupal 10 lint passing.

## Step 14: Open PR

### Check what the pull request deploys

Run this when the installed version from Step 1 is 4.15.0 to 4.18.0, or when it's unknown and Step 9 ran. On those releases, `deploy.yml` deploys after every successful test run, a pull request's included: it pushes the default branch to a branch of the deploy remote named after the pull request's branch, or to the branch a 4.18.0 `DEPLOY_BRANCH` repository variable names. GitHub runs that workflow from the copy on the default branch, so this update's own pull request deploys the old way until it merges.

List the repository's Actions secrets:

```bash
gh secret list
```

When `DEPLOY_REMOTE` isn't in the list, the project doesn't deploy, so carry on. Otherwise, or when the command fails, stop before pushing. Ask the user to set the `DEPLOY_PROCEED` secret to `0` under **Settings** -> **Secrets and variables** -> **Actions** now, and back to `1` just before merging. The skill can't read a secret's value, so wait until the user confirms it's `0` or tells you to push anyway.

### Open the pull request

Push the branch and open a pull request. Use the `/open-pr` skill or create the PR manually with a summary of all changes. Include the conflicts you resolved by judgment, the changes from Step 9 that need a new home, the leftover template files and the jobs below PHP 8.3 from Step 11, the code changes Step 12 made for a new Drupal major, and a new major that Step 13 left to CI.

When the deployment check above found `DEPLOY_REMOTE`, end the description with what's left around the merge:

- Set `DEPLOY_PROCEED` back to `1` just before merging.
- After merging, delete the branches that earlier pull requests left on the deploy remote. Each is named after a pull request's branch: `gh pr list --state all --limit 1000 --json headRefName --jq '.[].headRefName'` lists those names, and `git ls-remote --heads <deploy remote>` lists the remote's branches. Delete each match with `git push <deploy remote> --delete <branch>`, but keep the branches the project releases from, such as `1.x`, and the one `DEPLOY_BRANCH` names.

## Important notes

- When the user names a version, use that tag verbatim. Otherwise take the published release with the highest version that is neither a draft nor marked as a pre-release. Either way, use the tag verbatim and start without asking the user to confirm it, and stop when the project is already on that release or a newer one, unless the user asked to add or drop a Drupal major.
- Never run the installer in the project root, and never copy the release's files over the project's: the merge keeps what the project changed. The 1 exception is `scripts/eddy-tooling`, which projects use as-is from Eddy, so Step 8 restores the release's copy.
- A Drupal major the user asks to add or drop changes the render of the new release only. The render of the installed version keeps the majors the project tests now.
- Always keep the scaffold's example extension and example scripts out of the merge - a real project never ships them. Step 7 leaves them out of both commits with the exclude pathspecs from Step 6.
- Never overwrite project-specific code (src/, tests/, config/, *.module, etc.). Edit it only for the code changes a new Drupal major needs (Step 12) and the fixes Step 13 calls for.
- After the merge, always verify branch references match the project.
- A project created from a 4.x release keeps its build scripts in `.devtools/`. The release ships them as the `drevops/eddy-tooling` package instead, so Step 9 removes the directory - never restore `.devtools/` or merge changes into it. Customisations a project made there become a lifecycle hook in `scripts/` or a patch for the package in `composer.dev.json`.
- A project created from 4.18.0 or 4.19.0 can carry the Cloudflare tunnel hooks `scripts/provision-cloudflared.sh`, `scripts/start-cloudflared.sh` and `scripts/stop-cloudflared.sh`. Later releases run the tunnel from the tooling package, so Step 9 removes the hooks, and a customisation made in them becomes a patch for the package in `composer.dev.json`.
- A project on 4.15.0 to 4.18.0 that deploys from GitHub Actions deploys this update's pull request until it merges. Never push its branch until the user confirms `DEPLOY_PROCEED` is `0` or says to push anyway (Step 14).
- Run the full test suite before opening the PR to catch regressions.
- Prefer `ahoy` or `Makefile` commands over running tools directly. For example, use `ahoy lint` instead of `composer lint`, `ahoy test` instead of `composer test`, `ahoy build` instead of running `vendor/bin/eddy-*` commands manually. Only fall back to direct commands when no `ahoy` or `Makefile` equivalent exists.

## Working directory rules

- **Never `cd` into the `build/` directory** to run commands. All commands (`ahoy lint`, `ahoy test`, `ahoy build`, etc.) must be run from the project root directory.
- The installer is the only command that runs outside the project root: Steps 4 and 5 run it from inside its render, then `cd` back.
- **Never use absolute paths** to run commands. Use relative paths or let `ahoy`/`make` handle path resolution.

## Command pattern rules

Commands must start with a simple keyword that matches the allowed permission prefixes (e.g., `git`, `php`, `cp`, `ahoy`). The `rm` entries from Step 0 are exact commands, so run each `rm` command exactly as written. Avoid patterns that trigger CLI approval prompts:

NEVER use compound or composite commands in a single Bash tool call. Every Bash call must contain exactly ONE simple command. No exceptions.

**NEVER use:** `&&`, `||`, `;`, `|`, `<<<`, `$(...)`, heredocs.

**ALWAYS:**
- Use multiple separate Bash tool calls, one command per call
- Use non-interactive flags or env vars for scripts that support them (e.g. `composer --no-interaction`, `INSTALL_*` for `install.php`)
- For git commits, use: `git commit -m "Message here."`
