# Upgrading a 4.x project to 5.x

This guide upgrades a Drupal module or theme created from a 4.x release of this template to 5.x.

5.x is a bigger step than a usual update. Since `4.19.0`, the last 4.x release, the template became Eddy and moved to [`drevops/eddy`](https://github.com/drevops/eddy), the scripts in `.devtools/` turned into the `drevops/eddy-tooling` Composer package, and CircleCI support went away. The upgrade rewrites most of your build and CI files in one go, and a few of the changes need decisions only you can make.

There's a more immediate catch, too: a 4.x project can't update itself anymore. Its `AGENTS.md` fetches the update skill from the `.scaffold/` directory of the old repository, and that URL returns 404 now that the skill lives in `.eddy/`. The steps below get you past it.

- [Before you start](#before-you-start)
- [Upgrade with Claude Code](#upgrade-with-claude-code)
- [Upgrade by hand](#upgrade-by-hand)
- [What changed](#what-changed)
- [After the upgrade](#after-the-upgrade)

## Before you start

- Commit or stash your work in progress. The upgrade replaces most of the files in the project root, and the update skill won't start while the working tree has changes outside `.claude/` and `.idea/`.
- Find the edits you made to `.devtools/` and, in a project created from 4.18.0 or 4.19.0, to the `scripts/*-cloudflared.sh` hooks. The upgrade deletes both, so each edit needs a new home (see [`.devtools/` is a package now](#devtools-is-a-package-now)). `git log -p -- .devtools 'scripts/*-cloudflared.sh'` shows every change to those files, template updates included, so look for the commits that are yours.
- If your project runs on CircleCI, keep its project settings at hand. Its deployment settings move to GitHub Actions (see [CircleCI is gone](#circleci-is-gone)).

## Upgrade with Claude Code

The `update-consumer-eddy` skill does most of the work. It downloads the latest release, re-runs the installer with your project's answers, restores your own code, removes the template's example files and opens a pull request. It already knows about 4.x projects: it drops `.devtools/` and the Cloudflare hooks instead of restoring them, and it reads your Drupal versions from `.circleci/config.yml` when your project has no GitHub Actions workflow.

What it can't do is fetch itself into a 4.x project, so you do that part once, from the project root:

1. Delete the old skill directory. The 4.x `AGENTS.md` downloads the skill with `curl -sSL`, which doesn't fail on an HTTP error, so the directory holds the 404 page instead of a skill.

   ```bash
   rm -rf .claude/skills/update-consumer-drupal-extension-scaffold
   ```

2. Download the new skill:

   ```bash
   mkdir -p .claude/skills/update-consumer-eddy
   ```

   ```bash
   curl -fsSL -o .claude/skills/update-consumer-eddy/SKILL.md \
     https://raw.githubusercontent.com/drevops/eddy/main/.eddy/skills/update-consumer-eddy/SKILL.md
   ```

3. Start Claude Code in the project and run `/update-consumer-eddy`, or ask Claude to run the `update-consumer-eddy` skill. On its first run, the skill may add a few command permissions to `.claude/settings.local.json` and ask you to restart Claude Code. Run it again after the restart.

4. Go through [What changed](#what-changed) while you review the pull request it opens, and make the changes the skill leaves to you: moving your `.devtools/` edits, moving the CircleCI settings and, if you want it, adding Drupal 12.

The upgrade rewrites `AGENTS.md`, so from then on "update Eddy" works the way it does in any 5.x project.

## Upgrade by hand

Follow the manual steps in [Updating your extension](README.md#updating-your-extension), with these differences for a 4.x project:

- The installer is `install.php`, not `init.php`. It no longer asks for a CI provider or about the Cloudflare hooks, and it reads pre-filled answers from `INSTALL_*` variables instead of `DEX_*` (4.19.0) or `PROMPTY_*` (earlier releases). `php install.php --help` lists them.
- Before you copy the new files in, delete whichever of these your project has: `.devtools/`, `.circleci/`, `scripts/provision-cloudflared.sh`, `scripts/start-cloudflared.sh`, `scripts/stop-cloudflared.sh` and `.claude/skills/update-consumer-drupal-extension-scaffold/`.
- `scripts/` holds your own hooks, but take `scripts/eddy-tooling` from the release. It's the installer of the tooling package, and it's new in 5.x.
- Merge `composer.dev.json` rather than overwriting it, so your own dependencies and patches survive. [`.devtools/` is a package now](#devtools-is-a-package-now) lists the entries 5.x adds.
- `install.php` runs outside your repository here, so it writes `1.x` into the branch filters of `.github/workflows/test.yml` and `.github/workflows/draft-release-notes.yml`. If your project builds from other branches, put them back.

Then work through [What changed](#what-changed) for the decisions the merge leaves to you.

## What changed

Each change below covers what it was in 4.x, what it is in 5.x, and what your project needs.

### Name and repository

In 4.x, the template was the Drupal Extension Scaffold at `AlexSkrypnyk/drupal_extension_scaffold`. It kept its own files in `.scaffold/`, and your `AGENTS.md` fetched the `update-consumer-drupal-extension-scaffold` skill from there, on the `1.x` branch.

In 5.x, it's Eddy at [`drevops/eddy`](https://github.com/drevops/eddy), on the `main` branch. Its own files live in `.eddy/`, which never reaches your project. The update skill is `update-consumer-eddy`, and the installer is `install.php`.

What to do:

- Delete `.claude/skills/update-consumer-drupal-extension-scaffold/`.
- In `AGENTS.md`, replace the "Updating the scaffold" section with [the one Eddy ships](AGENTS.md#updating-the-scaffold). It downloads the skill with `curl -fsSL`, which fails on an HTTP error instead of saving the error page.
- In `.gitignore`, delete the `.claude/skills/update-consumer-drupal-extension-scaffold/` line. The `.claude/*` rule above it already ignores everything in `.claude/` except `settings.json`, the new skill's directory included.
- In `README.md`, credit [Eddy](https://github.com/drevops/eddy) in the closing line instead of the Drupal Extension Scaffold.

The upgrade makes the last 3 changes for you.

### `.devtools/` is a package now

In 4.x, every project carried its own copy of the build, run and deploy scripts in `.devtools/`: `assemble`, `start`, `stop`, `provision`, `deploy`, `browser`, `info` and `qrcode`, with `helpers.php` behind them. The command wrappers and CI ran them as `.devtools/<script>`, so a fix reached your project only when you merged the next release by hand.

In 5.x, the scripts ship as the [`drevops/eddy-tooling`](https://github.com/drevops/eddy-tooling) Composer package, so fixes arrive through its version constraint instead. `scripts/eddy-tooling` installs the package into `vendor/`, and each script becomes a command in `vendor/bin/`: `.devtools/assemble` is `vendor/bin/eddy-assemble`, and `.devtools/browser start` and `stop` are `eddy-browser-start` and `eddy-browser-stop`.

`ahoy` and `make` run the installer before their commands, and CI runs it as a step of its own. The installer skips Composer while nothing it depends on has changed, so running it every time costs next to nothing. [The tooling package](README.md#the-tooling-package) has the details.

What to do:

- Delete `.devtools/` and add `scripts/eddy-tooling` from the release.
- Add the 5.x entries to `composer.dev.json`, next to your own:
  - `drevops/eddy-tooling` and `cweagans/composer-patches` in `require-dev`, with the constraints from the release's `composer.dev.json`.
  - `cweagans/composer-patches` in `config.allow-plugins`.
  - `extra.eddy.drupal-version`, set to the highest Drupal major you build, such as `"11"`. A local build uses it when `DRUPAL_VERSION` isn't set, and Drupal 11 when neither is.
  - The release's `drush/drush` constraint, which adds Drush 14 for Drupal 12.
- Move each edit you made to `.devtools/`:
  - A project-specific step, such as importing extra configuration once the site is installed, becomes a [custom lifecycle script](README.md#custom-lifecycle-scripts): `scripts/assemble-*.sh`, `scripts/provision-*.sh`, `scripts/start-*.sh` or `scripts/stop-*.sh`.
  - A change to what a command itself does becomes a patch for `drevops/eddy-tooling` in `composer.dev.json`, as the [package README](.eddy/tooling/README.md#patching-the-package) describes. The commands live in the package's `src/` directory, such as `src/eddy-assemble`, and they've changed a lot since 4.x, so write the patch against the current source instead of reusing an old diff. This is the trickiest part of the upgrade.
- Replace every `.devtools/<script>` call in your own scripts, docs and CI steps with `vendor/bin/eddy-<script>`. Where no wrapper or CI step runs the installer first, run `scripts/eddy-tooling` before it.

The build changed in 2 ways you might notice:

- It applies the patches you declare. From 4.14.0 to 4.19.0, the build didn't install `cweagans/composer-patches`. Unless your project required the plugin itself, Composer skipped every patch in `composer.json` and `composer.dev.json` without a word. In 5.x they apply, and a patch that no longer applies fails the build (see [Patching dependencies](README.md#patching-dependencies)).
- It pins Drupal core to 1 exact release, and resolves every other dependency with `minimum-stability: dev` and `prefer-stable: true` (see [How versions are resolved](README.md#how-versions-are-resolved)). `DRUPAL_VERSION` takes values such as `11`, `11.1.0` or `11@beta`, but no longer `@dev`, because a development branch isn't a release.

### Cloudflare tunnel hooks

In 4.18.0 and 4.19.0, `init.php` could keep 3 hooks that ran a Cloudflare quick tunnel: `scripts/provision-cloudflared.sh`, `scripts/start-cloudflared.sh` and `scripts/stop-cloudflared.sh`.

In 5.x, `eddy-start`, `eddy-provision` and `eddy-stop` run the tunnel themselves when `CLOUDFLARE_TUNNEL=1` is set, in the shell or in `.env` (see [Public HTTPS tunnel](README.md#public-https-tunnel-cloudflare)). They also fix a bug in the hooks: the hooks made `*.trycloudflare.com` the only trusted host, so after a tunnel provision the site answered 400 on `http://localhost:<port>`.

What to do:

- Delete the 3 hooks. Left in place, they act on the same tunnel as the commands, and the start hook can restart it.
- Carry any edit you made to them as a patch for `drevops/eddy-tooling`, the same way as an edit to `.devtools/`.

A project created before 4.18.0 never had these hooks.

### CircleCI is gone

In 4.x, `init.php` asked which CI provider to use. A CircleCI project got `.circleci/config.yml` instead of the GitHub Actions workflows, kept its deployment key in CircleCI with the key's fingerprint in the config, and deployed every branch that passed its tests.

In 5.x, GitHub Actions is the only CI, and every project gets `.github/workflows/test.yml`, `deploy.yml`, `draft-release-notes.yml` and `assign-author.yml`.

What to do, if your project ran on CircleCI:

- Delete `.circleci/`. The upgrade adds the GitHub Actions workflows.
- Move the deployment settings to the repository's **Settings** -> **Secrets and variables** -> **Actions**, as [Setting up SSH key for deployment](README.md#setting-up-ssh-key-for-deployment) describes:
  - The private deployment key becomes the `DEPLOY_SSH_KEY` secret. GitHub Actions doesn't need its fingerprint.
  - `DEPLOY_REMOTE`, `DEPLOY_USER_NAME`, `DEPLOY_USER_EMAIL` and `DEPLOY_PROCEED` become secrets.
  - `DEPLOY_BRANCH`, if you set it, becomes a repository variable.
  - `CODECOV_TOKEN`, if you upload coverage to Codecov, becomes a secret.
  - Each `CI_*_IGNORE_FAILURE` toggle you set becomes a repository variable (see [Allowing a CI step to fail](README.md#allowing-a-ci-step-to-fail)).
- Check which branches deploy. GitHub Actions deploys release tags and pushes to the branches in the `push` filter of `test.yml`, which is `1.x` unless you change it, so a branch outside that list stops deploying.
- In your branch protection rules, swap each `ci/circleci: <job>` check for the GitHub Actions job of the same name. [Branch protection](README.md#branch-protection) lists them.
- Stop building the project in CircleCI. Otherwise every push gets a failing "CircleCI Pipeline" check that can't find a configuration.

A project on GitHub Actions has nothing to do here.

### Deployment

In 4.x, a release tag also force-pushed the tagged commit to a branch of your Drupal.org repository, so a tag behind that branch's tip rewound it. A test run that finished late, or that you re-ran, could rewind a branch the same way. The first deployment of a new branch failed, and the `Deploy` workflow failed on every run until you set up deployment.

In 5.x:

- A release tag deploys only the tag and leaves your branches alone. `DEPLOY_BRANCH` applies to branch pushes only.
- A branch push deploys only while its commit is still the tip of the branch. An older commit is skipped with a notice, so it never replaces a newer one.
- Deployments of the same branch wait for each other instead of running side by side, so 2 of them can't push out of order.
- The first deployment of a branch creates it.
- Until the `DEPLOY_REMOTE` secret is set, the `Deploy` workflow skips its steps and reports a notice instead of failing.

What to do: nothing, unless your release process relied on a tag push to update a branch. Push the branch as well in that case. [Deployment](README.md#deployment) describes the new behavior in full.

### Drupal 12

5.x can test your extension on Drupal 12 next to Drupal 10 and 11. It's opt-in: the upgrade keeps the Drupal majors your CI tests today. To add Drupal 12, select `12` when `install.php` asks for the Drupal versions, or ask Claude to include it when it runs the update skill.

Drupal 12 brings 5 CI jobs (`lint-d12`, `test-php-min-d12-stable`, `test-php-max-d12-stable`, `test-php-min-d12-legacy` and `test-php-max-d12-canary`) and a README badge. It also needs 3 changes in your own code, which no template merge makes:

- Add `^12` to `core_version_requirement` in your `*.info.yml`.
- If your FunctionalJavascript tests extend the base class the template generated, declare `string|false` as the return type of its `getMinkDriverArgs()`. Drupal 12 declares that type on the parent method, so an override without it is a fatal error, and Drupal 10 and 11 accept the typed override too.
- Keep both forms of test metadata: doc-comment annotations, such as `@group` and `@dataProvider`, and the matching PHP attributes. Drupal 10 runs PHPUnit 9.6, which reads only the annotations, and Drupal 12 runs PHPUnit 12, which ignores them.

Check that the dependencies in your `composer.json` support Drupal 12 too, and add the new jobs to your [branch protection](README.md#branch-protection) rules. [Drupal 12](README.md#drupal-12) explains how its jobs follow Drupal releases.

### Everything else

The rest of the template moved on as well, and an upgrade merges these files like any template update:

- `.ahoy.yml` and `Makefile` call `vendor/bin/eddy-*` and install the tooling first. When you keep FunctionalJavascript tests, `test` now starts the browser before PHPUnit, so it needs Chrome, or Docker with `WEBDRIVER_BACKEND=selenium`. `test-unit`, `test-kernel` and `test-functional` don't. `reset` also removes `vendor/`, which is how a local checkout picks up a newer patch release of the tooling.
- `.github/workflows/test.yml` installs the tooling and calls `vendor/bin/eddy-*`. Its Composer cache works now: the 4.x cache step pointed at a directory Composer never wrote to, so it never stored anything.
- `.github/workflows/deploy.yml` installs the tooling and calls `vendor/bin/eddy-deploy` (see [Deployment](#deployment)).
- `phpunit.xml`, `phpunit.d10.xml`, `phpcs.xml`, `phpstan.neon`, `rector.php` and `jest.config.js` changed only in their comments, so keep any settings you added to them.
- `.cspell.json` adds the word `cweagans`, which `composer.dev.json` now contains. Merge it with your own words.
- `AGENTS.md` describes the tooling commands, `vendor/` and `scripts/eddy-tooling` in place of `.devtools/`, drops CircleCI, and carries the new "Updating the scaffold" section.
- `CONTRIBUTING.md` documents only the command wrappers you keep, or the `vendor/bin/eddy-*` commands when you keep neither. Carry your own notes over from the old copy.
- `README.md` drops the CircleCI badge, shows Drupal badges only for the majors you test, and credits Eddy.
- `.gitattributes` drops its `.circleci` and `.devtools` lines.

## After the upgrade

Before you merge the upgrade:

1. Build, lint and test the project with `ahoy build`, `ahoy lint` and `ahoy test`, or the same `make` commands. The build installs the tooling into `vendor/` first.
2. Check that every CI job passes on the pull request.
3. Check that the branch filters in `.github/workflows/test.yml` and `.github/workflows/draft-release-notes.yml` list the branches you build from.
4. Check that your branch protection rules require the jobs in [Branch protection](README.md#branch-protection) for the Drupal majors you test, and no `ci/circleci: <job>` checks.

After you merge it, check that the next `Deploy` run reaches your Drupal.org repository, or reports a notice that says why it didn't.
