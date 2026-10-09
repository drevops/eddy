---
name: create-eddy-tooling-release-notes
description: Use when preparing release notes for the 'drevops/eddy-tooling' Composer package, published as a read-only mirror of '.eddy/tooling/' from the Eddy repository. Builds a tooling-only changelog from the previous release tag (or a commit) up to the release being prepared, enriches each entry from its 'drevops/eddy' pull request and issue, and writes consumer-facing notes to paste into the GitHub release. Releases are created by hand in the GitHub UI and rarely align with Eddy releases, so the range is driven by tooling tags, not by an Eddy tag. Triggers on phrases like 'tooling release notes', 'eddy-tooling release notes', 'release notes for eddy-tooling'.
user-invocable: true
---

# Create Eddy Tooling Release Notes

Generate consumer-facing release notes for the `drevops/eddy-tooling` Composer package.

`drevops/eddy-tooling` is a **read-only mirror** of the `.eddy/tooling/` directory in the `drevops/eddy` repository. The `scaffold-publish-tooling.yml` workflow copies that directory to the mirror on every push to `1.x`, so the mirror's history is mostly noise: it holds 1 commit per Eddy commit, including empty commits for Eddy commits that didn't touch the tooling. Tooling releases are created by hand in the GitHub UI and rarely align with Eddy releases, so "what changed since the last tooling release" can't be read off an Eddy tag. This skill rebuilds that set from the Eddy history and turns it into release notes.

## When to use

- A `drevops/eddy-tooling` release was created in the GitHub UI, as a draft or published, and needs notes.
- An existing tooling release has empty or missing notes.
- You want to see what tooling changes have accumulated since the last release.

Run it from a `drevops/eddy` clone with `git` and an authenticated GitHub CLI (`gh`). It produces notes only: it doesn't tag, publish or otherwise change the `drevops/eddy-tooling` repository. The maintainer pastes the notes into the release in the GitHub UI.

## How the reconciliation works

Every commit published to `drevops/eddy-tooling` records its origin in the commit body as `Source: drevops/eddy@<SHA>`. Each release tag therefore points back to an exact Eddy commit. That is the anchor: resolve the previous release and the release being prepared to their source SHAs, then list the Eddy commits between them that changed the package.

Everything in `.eddy/tooling/` ships to consumers: `src/`, `composer.json`, `README.md` and `LICENSE`. The package's tests live outside it, in `.eddy/tests/`. Filtering the log to `.eddy/tooling/` removes both the empty mirror commits and every Eddy commit that didn't change the package, in 1 step.

## Inputs

The range runs from a **lower bound**, the previous release, to an **upper bound**, the release being prepared. The user can name either bound as a tooling release tag or an Eddy commit SHA.

| Bound          | Default                                                   |
|----------------|-----------------------------------------------------------|
| Lower (`FROM`) | The highest published release below the upper bound.      |
| Upper (`TO`)   | The newest release on the mirror, if it has no notes yet. |

When the mirror has no releases, or the newest one already has notes, the upper bound is the mirror's `1.x` branch instead, for a release not created yet. When no published release sits below the upper bound, the release is the package's **first release**. It has no default lower bound: the range starts where the package was created.

3 common shapes:

- **A release created in the GitHub UI** (the usual case): give nothing, or the release's version. The maintainer creates the release, as a draft or published, before running this skill, so it's the newest release and its notes are empty.
- **The next release, not created yet**: the range is "previous release to the mirror's `1.x`", and you choose the new version in Step 4.
- **Notes for an older release**: give both bounds as tags, for example `1.1.0` and `1.2.0`. The new version is the upper tag.

## Process

### Step 1: Resolve the range bounds to Eddy source SHAs

List the mirror's releases, newest first:

```bash
gh release list -R drevops/eddy-tooling --json tagName,isDraft,isPrerelease,publishedAt
```

**Upper bound.** Use the bound the user named. A version that isn't on the mirror yet is a release not created yet. Without a named bound, view the newest release:

```bash
gh release view <TAG> -R drevops/eddy-tooling --json tagName,isDraft,targetCommitish,body,url
```

When its body is empty, it's the release being prepared. When it already has notes, or the mirror has no releases at all, the release being prepared doesn't exist yet: the upper bound is the mirror's `1.x` branch.

**Lower bound.** Use the bound the user named. Otherwise take the highest published release below the upper bound from the list. Drafts don't count, since a draft has no tag until it's published. When there's no such release, this is the package's **first release**: without a named bound, leave the lower bound empty, so Step 2 covers the whole history of `.eddy/tooling/`.

Resolve each bound to an Eddy commit SHA:

- **A published release**: its tag exists on the mirror. Read the source SHA from the mirror commit body:

  ```bash
  gh api repos/drevops/eddy-tooling/commits/<TAG> --jq '.commit.message'
  ```

  Parse the `Source: drevops/eddy@<SHA>` line from the output; that `<SHA>` is the bound.
- **A draft, or a release not created yet**: there's no tag yet, so resolve the release's target with the same command instead: the target the draft shows, or `1.x` for a release not created yet.
- **A commit SHA**: use it directly.

Don't use the local `HEAD` as the upper bound. The release is cut from the mirror's `1.x`, and the local checkout may be a feature branch or behind `origin/1.x`.

Confirm each resolved SHA is a commit in the local clone, which makes this command print `commit`:

```bash
git cat-file -t <SHA>
```

If `git cat-file` fails, the local clone is behind; run `git fetch origin` and retry, and stop with a clear message if it still can't be found. If it prints anything other than `commit`, the bound isn't a commit: stop and tell the user.

Then confirm the range runs forward, unless this is a first release without a lower bound:

```bash
git merge-base --is-ancestor <FROM_SHA> <TO_SHA>
```

A non-zero exit means the lower bound isn't an ancestor of the upper bound, so the bounds are reversed or on different branches. Stop and tell the user, rather than reporting the empty range as "no changes" in Step 2.

Tell the user the resolved range before going on, for example "Preparing `1.1.0` (draft) since `1.0.0`", so a wrong guess is caught early.

### Step 2: Build the tooling-only changelog

List the Eddy commits between `FROM_SHA` and `TO_SHA` that changed the package, newest first:

```bash
git log <FROM_SHA>..<TO_SHA> --no-merges --pretty=format:'%H%x09%s' -- .eddy/tooling/
```

The path filter is the heart of the skill: it keeps only commits that touched files consumers actually receive, discarding the empty mirror commits and every Eddy commit that didn't change the package.

For a first release without a lower bound, drop it from the range. The log then starts at the commit that created the package:

```bash
git log <TO_SHA> --no-merges --pretty=format:'%H%x09%s' -- .eddy/tooling/
```

If the command returns nothing, there's nothing to release. Tell the user "No tooling changes shipped since `<lower bound>`; no release needed" and stop. Don't write an empty notes file.

Each output line is a commit: a full SHA, a tab, then the subject. The subject already carries the entry shape these notes use: an optional `[#NNN]` issue prefix and a trailing `(#MMM)` pull request reference, for example `[#NNN] Added a public tunnel to 'eddy-start'. (#MMM)`.

### Step 3: Enrich each entry from its pull request

For each commit, gather enough context to write an accurate paragraph. **The pull requests and issues live in `drevops/eddy`, never in `drevops/eddy-tooling`** (the mirror has none). Follow this order and stop as soon as you have enough:

1. **Use the subject** if it's already specific enough. Skip the fetch entirely in that case.
2. **Fetch the pull request** named by the trailing `(#MMM)`:

   ```bash
   gh pr view <MMM> --repo drevops/eddy --json title,body,labels,author
   ```

   Use `author.login` for the `@author` attribution.
3. **Fetch the linked issue** named by a `[#NNN]` prefix when the pull request body is still ambiguous:

   ```bash
   gh issue view <NNN> --repo drevops/eddy --json title,body
   ```

4. **Inspect the change itself** only when the descriptions don't explain it. The diff is in the Eddy history, so read it locally rather than over the API:

   ```bash
   git show <SHA> -- .eddy/tooling/
   ```

   Read only enough to understand intent. The signals that matter most are added, renamed or removed commands (the files in `src/` and the `bin` list in `composer.json`), the environment variables the commands read (such as `WEBSERVER_PORT` or `WEBDRIVER_BACKEND`), changed defaults, and the PHP requirement in `composer.json`.

Most pull requests change the scaffold and the package together. List every file a commit changed:

```bash
git show --stat --format= <SHA>
```

A tooling change that landed with changes to the command wrappers (`Makefile`, `.ahoy.yml`), the CI workflow (`.github/workflows/test.yml`) or `composer.dev.json` usually needs the matching scaffold update. Describe the package side of the change, and say so when projects need that update.

Batch independent `gh` calls in parallel - issue several Bash tool calls in a single message, targeting 8-10 at a time, so a release with many entries resolves in a few waves rather than 1 call at a time. Pull request bodies often contain auto-generated review-bot sections; skim past them and rely on the human-written summary.

If `gh` is unavailable or auth fails, fall back to writing conservative paragraphs from the commit subjects alone, and tell the user which entries lack deep context so they can review them.

### Step 4: Determine the version

`PREVIOUS_VERSION` is the lower-bound tag, or the highest published release below the upper bound when a bare commit was given. A first release has no `PREVIOUS_VERSION`.

- **The release exists**, as a draft or published: `NEW_VERSION` is its tag, which the maintainer chose in the GitHub UI. Check it against the rules below and warn the user before writing if it looks wrong. Don't change the release.
- **The release isn't created yet**: suggest `NEW_VERSION` from the nature of the changes, then **confirm it with the user** before writing. For a first release, suggest the lowest version the constraint in `composer.dev.json` accepts, such as `1.0.0` for `~1.0.0`. The version only labels the notes; no tag is created.

Projects require `~1.<minor>.0` in `composer.dev.json`, so they install a new patch release on their next fresh install, without a scaffold update. A minor or major release reaches them only when the scaffold raises that constraint. A patch release must therefore hold nothing that needs the matching scaffold update: never suggest a patch for such a change, and warn when the maintainer chose one.

- **Major** (e.g. `2.0.0`): a command was removed or renamed; an environment variable projects set was removed or renamed; a default changed in a way that breaks existing usage; the PHP requirement in `composer.json` was raised.
- **Minor** (e.g. `1.1.0`): a new command or a new opt-in capability, flag or environment variable; a change that needs the matching scaffold update; new behaviour that existing projects are unaffected by.
- **Patch** (e.g. `1.0.1`): a bug fix, a hardening change, an internal refactor with no contract change, or documentation fixes, all of them safe for projects on their current scaffold.

For a minor or major release, remind the user to raise the constraint in `composer.dev.json` in `drevops/eddy`, as `CONTRIBUTING.md` describes.

### Step 5: Write and display the notes

Write the file to `.artifacts/release-notes-tooling-<NEW_VERSION>.md` (for example `release-notes-tooling-1.0.1.md`) using the structure and rules below. Then display its full contents to the user in a fenced `markdown` code block, ready to paste into the release description, together with the release's URL from Step 1 when the release exists.

## Output structure

```markdown
## What's new since PREVIOUS_VERSION

### Breaking changes

- **[#NNN](https://github.com/drevops/eddy/issues/NNN) Original subject. @author (https://github.com/drevops/eddy/pull/MMM)**<br>Paragraph: what used to work, the new behaviour, and the exact migration step projects must take.

### Highlights

- **[#NNN](https://github.com/drevops/eddy/issues/NNN) Original subject. @author (https://github.com/drevops/eddy/pull/MMM)**<br>Paragraph: why this matters to someone running the commands - the capability it unlocks or the pain it removes.

### All changes

- **[#NNN](https://github.com/drevops/eddy/issues/NNN) Original subject. @author (https://github.com/drevops/eddy/pull/MMM)**<br>Paragraph (1-3 sentences) explaining what the change does and why it is valuable.

**Full Changelog**: https://github.com/drevops/eddy-tooling/compare/PREVIOUS_VERSION...NEW_VERSION
```

A first release starts with `## Initial release` instead and ends without the `**Full Changelog**` line, since the mirror has no earlier tag to compare with. It has no `### Breaking changes` section either, since there's no earlier release to break.

## Formatting rules

### Sections

- **Breaking changes**: include only if at least 1 entry is breaking; omit the heading entirely otherwise. An entry is breaking when projects upgrading may have to change their own setup, or when observable behaviour changes automatically on upgrade. The package's public surface is the **command contract**: the `eddy-*` commands in `src/` and their `bin` entries in `composer.json`, the environment variables they read, their arguments, their observable side effects such as the values they write to `.env`, and the PHP requirement in `composer.json`. The migration step names what projects change themselves, such as a renamed variable in `.env` or in CI, on top of the scaffold update. An opt-out flag makes a breaking change recoverable, not non-breaking - still list it and explain the escape hatch in the migration step.
- **Highlights**: pick the 5-7 most consumer-relevant entries, or fewer in a small release, preferring new commands and capabilities over internal hardening, refactors and doc fixes. Omit the heading if the release is too small to have meaningful highlights. Every highlight must also appear under All changes - it's a curated view, not a separate set.
- **All changes**: list every entry from Step 2, newest first, preserving the commit subject text verbatim. Don't reword, reorder, merge or drop entries.

### Entries

- **No hand-wrapping.** Every bullet, including its paragraph, is a **single line** in the file regardless of length. GitHub renders the markdown - let the browser wrap.
- Format exactly: wrap the original subject in `**...**`, immediately follow it with `<br>`, then the paragraph on the same line - no blank line between them, no indentation.
- **Render every issue and pull request reference as a full `drevops/eddy` URL**, never a bare `#NNN` - the notes are published to the `drevops/eddy-tooling` mirror, where a bare `#NNN` links to that repository's own numbering. Transform the commit subject so the leading `[#NNN]` issue reference becomes the markdown link `[#NNN](https://github.com/drevops/eddy/issues/NNN)`, and the trailing `(#MMM)` pull request reference becomes the bare URL `(https://github.com/drevops/eddy/pull/MMM)`. When a commit has no `[#NNN]` issue prefix, start with the subject text; when it has no `(#MMM)` pull request reference (a direct push), omit the trailing reference and enrich from the commit and its diff. Keep the `@author` handle (a global GitHub mention resolves correctly anywhere) and the subject's punctuation and letter case.
- Paragraphs are 1-3 sentences of plain prose. For fixes: name what was broken, the user-visible symptom, and the corrected behaviour. For features: name what projects can now do that they couldn't before, and the environment variable or flag that controls it with its default. Never invent details the source doesn't support.
- Entries authored by dependency bots (`@renovate[bot]`) or whose subject begins with `Update dependency`, `Update all dependencies` or `Bump ` stay as bare list items with no bold and no paragraph. These are rare here, since the package only requires PHP.
- Don't insert blank lines between consecutive list items.

### Footer

The `**Full Changelog**` line points at the **mirror** repository, `drevops/eddy-tooling`, using `PREVIOUS_VERSION...NEW_VERSION`. The link resolves once the `NEW_VERSION` tag exists on that repository, which happens when the release is published. A first release has no `**Full Changelog**` line.

## Validation checklist

Before displaying the output, verify:

- File saved to `.artifacts/release-notes-tooling-<NEW_VERSION>.md`.
- First line is `## What's new since <PREVIOUS_VERSION>`, or `## Initial release` for a first release.
- `### Breaking changes` appears only if there is at least 1 breaking entry.
- `### All changes` lists every commit from Step 2, verbatim, newest first.
- Every non-bot entry is `**subject**<br>paragraph` on a single line, no blank line, no indentation.
- Every reference is a full `https://github.com/drevops/eddy/...` URL - the leading issue as `[#NNN](.../issues/NNN)`, the trailing pull request as `(.../pull/MMM)`. No bare `#NNN` remains.
- Pull request and issue lookups used `--repo drevops/eddy`.
- The `**Full Changelog**` URL targets `drevops/eddy-tooling`, and a first release has no such line.
- No invented details beyond the commit subject, linked issue, pull request body, or diff.

## Command rules - CRITICAL

Every Bash tool call must contain exactly 1 simple command. No `&&`, `||`, `;`, `|`, command substitution `$(...)`, here-strings, or heredocs. When you need several commands, make several separate Bash calls. This applies to every `git` and `gh` invocation above.
