# Release Eddy

Follow this guide to release a new version of Eddy. It's for maintainers of `drevops/eddy`, and it covers the tag push, the signing key, the docs deploy and the checks on a published installer. To change Eddy itself, follow [`CONTRIBUTING.md`](CONTRIBUTING.md).

`php install.php` deletes this file from generated projects.

## Before you begin

Make sure you have the following:

- The maintain or admin role on `drevops/eddy`. The `tags` ruleset in [Repository settings](#repository-settings) lets only those roles push a tag.
- A `main` with every change of the release merged and every required check passing.
- The signing key in `release`, the GitHub Actions environment that holds the signing secrets, as [Store the signing key](#store-the-signing-key) describes.

## Release a version

You release a version by pushing its tag. The tag starts `Release Eddy installer`, which attaches the installer files to the draft release and then publishes it. The draft comes from the `Draft release notes` workflow, which runs Release Drafter on every push to `main` to list the merged pull requests.

A version tag is 3 numbers with an optional suffix, such as `5.2.1` or `5.3.0-rc.1`, with no `v`. A published release is immutable, so it takes no new assets. Follow 3 rules for every release:

- Release only by pushing the tag. The **Publish release** button publishes the draft without the installer, and the only fix is a new version.
- Push the tag from `main`. The workflows stop on a tag whose commit isn't on `main`.
- Push 1 version tag at a time. Every release starts from the same Release Drafter draft.

To release a version:

1. Optional: On the **Releases** page, click **Edit** on the draft release and edit its notes. The workflow sets the tag and the title to the version you push.
2. On an up-to-date `main`, tag the commit and push the tag:

   ```bash
   git tag 5.2.1
   git push origin 5.2.1
   ```

3. On the **Actions** tab, open the `Release Eddy installer` and `Release Eddy docs` runs for the tag. Confirm that both pass. When the `release` environment has required reviewers, approve the run so its build job can read the signing key.
4. Open the release. Confirm that it's published with these assets, where `TAG` is the version:
   - `install.php`
   - `eddy-TAG.tar.gz`
   - `SHA256SUMS`
   - `SHA256SUMS.asc`
   - `eddy-TAG.intoto.jsonl`

The workflow publishes a tag with a suffix as a pre-release, which never becomes the latest release. A version older than the latest release leaves "latest" on the newer one.

## What the tag starts

`Release Eddy installer`, defined in `.github/workflows/eddy-release-installer.yml`, runs 3 jobs. Only the first job reads the signing key, and only the second one writes to the repository.

`eddy-release-installer-build` runs in the `release` environment with a read-only token:

1. Checks that the tag is a version and that its commit is on `main`.
2. Builds `eddy-TAG.tar.gz` from the tag with `git archive`. GitHub builds its own archives on demand, with no promise that their bytes stay the same, so the release carries an archive of its own.
3. Stamps `install.php` with the version and with the URL and SHA-256 checksum of the archive, through `.eddy/docs/scripts/stamp-installer.mjs`. Then it checks that the installer reports the version and holds the URL and the checksum.
4. Runs the stamped installer in an empty directory against the local archive. The installer verifies the archive against the stamped checksum and installs an extension.
5. Writes `SHA256SUMS` for the installer and the archive. It fetches the key that keys.openpgp.org has confirmed for the `RELEASE_GPG_KEY_EMAIL` address, and stops unless it's the key in `GPG_PRIVATE_KEY`. Then it signs the file as `SHA256SUMS.asc`, with `GPG_PASSPHRASE` when the key has one, and checks the signature with `gpgv` against the keyserver's copy.
6. Checks the signature with `.eddy/installer/verify-release.php`. The script fetches the key from keys.openpgp.org by its pinned fingerprint and accepts only a signature from that key. Then it checks every file that `SHA256SUMS` lists.
7. Attests the installer, the archive and `SHA256SUMS`. It saves the attestation bundle as `eddy-TAG.intoto.jsonl`. OpenSSF Scorecard reads provenance from release assets only.

`eddy-release-installer-publish` holds a token that writes releases, and no secret:

1. Waits in the `release-drafter` concurrency group, which `Draft release notes` runs in too. Release Drafter sets its draft's tag back to its own guess, so the 2 never run at once.
2. Checks the checksums and the attestations of the files from the build job, and that the tag still points to the commit they came from. It checks the tag again right before it publishes.
3. Moves the draft to the pushed tag when Release Drafter guessed another version, or creates a draft when there's none.
4. Uploads the 5 files to the draft, then publishes it.

`eddy-release-installer-verify` reads the published release with a read-only token:

1. Downloads the published files and checks the signature, the checksums and the attestations again.
2. For the latest release, checks that `https://github.com/drevops/eddy/releases/latest/download/install.php` and `https://eddy.drevops.com/install` serve the published installer byte for byte.
3. Runs the published installer in an empty directory, so it downloads the archive from the release and verifies it.

The tag also starts the `Test` workflow. After `Test` passes, `Deploy` pushes the tag to [`drevops/eddy_destination`](https://github.com/drevops/eddy_destination).

## Fix a failed release run

To fix a failed run, re-run the failed job from the **Actions** tab:

- After the build job fails, the draft holds no new file, so a re-run starts over from the same tag.
- A re-run of the publish job passes when the published release already holds the same `install.php`.
- After the verify job fails, the release stays published and takes no new file. Fix the cause on `main` and release a new version.

The publish job stops on a release published without its files, such as one published with the **Publish release** button. Release a new version.

## Docs on a release

`Release Eddy docs`, defined in `.github/workflows/eddy-release-docs.yml`, publishes the documentation site to [eddy.drevops.com](https://eddy.drevops.com) on GitHub Pages:

- On a version tag, it checks that the tag is a version on `main`. Then it deploys the site built from the tag, with the tag as the version in the footer.
- To deploy a docs change between releases, run the workflow on `main` from the **Actions** tab. The version you type stamps the footer, and it must be an existing tag, such as `5.2.1`. Without a version, the footer shows the `git describe` output of the branch, such as `5.2.1-3-g1a2b3c4`.

The `github-pages` environment needs a deployment rule for the `main` branch and 1 of the **Tag** ref type.

## Verify an installer

To check the files of a release by hand, run these commands in a clone of `drevops/eddy`, with the version in place of `5.2.1`:

1. Download the release files:

   ```bash
   gh release download 5.2.1 --repo drevops/eddy --dir release-check
   ```

2. Check the GPG signature and the checksums, with the same script as the workflow:

   ```bash
   php .eddy/installer/verify-release.php release-check
   ```

3. Check the attestation of the installer against the attached bundle:

   ```bash
   gh attestation verify release-check/install.php --bundle release-check/eddy-5.2.1.intoto.jsonl --repo drevops/eddy
   ```

[Verify the installer](https://eddy.drevops.com/installation#verify-the-installer) shows the checks for users.

## Manage the signing key

The `Eddy Release Signing` key has the fingerprint `7B8D 3C97 93E0 FF52 EAAB  FEC8 E532 2FD0 EC15 511E` and expires on 2028-11-09. Its public half is on [keys.openpgp.org](https://keys.openpgp.org/search?q=7B8D3C9793E0FF52EAABFEC8E5322FD0EC15511E), confirmed for `alex+eddy@drevops.com`. The workflow signs with the private half, which it reads from the `GPG_PRIVATE_KEY` secret. `VerifyReleaseTest` fails until `SIGNING_KEY_FINGERPRINT` in `.eddy/installer/verify-release.php` and the fingerprint in `.eddy/CLAUDE.md`, `README.md` and `.eddy/docs/content/installation.mdx` agree.

keys.openpgp.org returns 1 key for an address, so the signing address belongs to Eddy alone. A plus address such as `alex+eddy@drevops.com` reaches the same mailbox.

### Store the signing key

To publish the key and store its secrets in the `release` environment:

1. Add the signing address to the key as a user ID, unless the key already has it:

   ```bash
   gpg --quick-add-uid 7B8D3C9793E0FF52EAABFEC8E5322FD0EC15511E 'Eddy Release Signing <alex+eddy@drevops.com>'
   ```

2. Upload the public half to keys.openpgp.org:

   ```bash
   gpg --export 7B8D3C9793E0FF52EAABFEC8E5322FD0EC15511E | curl -T - https://keys.openpgp.org
   ```

3. Open the link that the upload prints, and request verification of `alex+eddy@drevops.com`. Confirm the address from the email that keys.openpgp.org sends.
4. Create the `release` environment:

   ```bash
   gh api -X PUT repos/drevops/eddy/environments/release
   ```

5. Store the private key as `GPG_PRIVATE_KEY`:

   ```bash
   gpg --armor --export-secret-keys 7B8D3C9793E0FF52EAABFEC8E5322FD0EC15511E | gh secret set GPG_PRIVATE_KEY --env release --repo drevops/eddy
   ```

6. Store the confirmed address as `RELEASE_GPG_KEY_EMAIL`, and the passphrase as `GPG_PASSPHRASE`. Each command prompts for its value:

   ```bash
   gh secret set RELEASE_GPG_KEY_EMAIL --env release --repo drevops/eddy
   gh secret set GPG_PASSPHRASE --env release --repo drevops/eddy
   ```

In **Settings > Environments > release**, limit **Deployment branches and tags** to tags that match `[0-9]*`, so only a version tag reaches the key. To approve every release before it reads the key, add yourself under **Required reviewers**.

### Extend the signing key

Extend the key before it expires. The fingerprint stays the same, so `verify-release.php` needs no change.

To extend the key by 2 years:

1. Set the new expiry date:

   ```bash
   gpg --quick-set-expire 7B8D3C9793E0FF52EAABFEC8E5322FD0EC15511E 2y
   ```

2. Upload the extended public half to keys.openpgp.org:

   ```bash
   gpg --export 7B8D3C9793E0FF52EAABFEC8E5322FD0EC15511E | curl -T - https://keys.openpgp.org
   ```

3. Store the extended private key again. `GPG_PRIVATE_KEY` still holds the copy with the old expiry date:

   ```bash
   gpg --armor --export-secret-keys 7B8D3C9793E0FF52EAABFEC8E5322FD0EC15511E | gh secret set GPG_PRIVATE_KEY --env release --repo drevops/eddy
   ```

4. Update the expiry date in [Manage the signing key](#manage-the-signing-key) and in `.eddy/CLAUDE.md`.

### Replace the signing key

To replace the key with a new one:

1. Create the new key. `verify-release.php` pins the fingerprint of its primary key, and accepts a signature from that key or from one of its subkeys.
2. Publish the new key and store its secrets with the steps in [Store the signing key](#store-the-signing-key), with its fingerprint in place of the old one. keys.openpgp.org links the address to the newly confirmed key, and releases stop until both secrets hold that key.
3. Put the new fingerprint in `verify-release.php`, in this file, in `.eddy/CLAUDE.md`, in `README.md` and in `.eddy/docs/content/installation.mdx`.
4. Add a note about the new key to the release notes of the next version.

## Repository settings

The release flow relies on these settings of `drevops/eddy`:

| Setting                         | Value                                                          |
|---------------------------------|----------------------------------------------------------------|
| Immutable releases              | On                                                             |
| Secret scanning                 | On                                                             |
| Push protection                 | On                                                             |
| Private vulnerability reporting | On                                                             |
| Dependabot alerts               | On                                                             |
| Default workflow token          | Read-only                                                      |
| `main` ruleset                  | Required checks, with no deletion and no force push            |
| `tags` ruleset                  | Only the maintain and admin roles create, move or delete a tag |

Scaffold's `install.sh github` command applies the security settings and both rulesets. It reads a token with **Administration: write** from `SCAFFOLD_GITHUB_TOKEN`. Its labels and general settings are Scaffold's, so leave them out. Preview the changes with `--dry-run`:

```bash
curl -fsSL https://getscaffold.dev/install.sh | bash -s -- github --github-owner=drevops --github-repo=eddy --no-github-labels --no-github-settings --dry-run
```

To apply the changes, run the command again without `--dry-run`.

<!-- cspell:ignore intoto EAAB openpgp subkey subkeys -->
