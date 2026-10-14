# Contributing

Thank you for your interest in improving Eddy. Bug reports, fixes and better docs are all welcome: report a bug or suggest a change in the [issue queue](https://github.com/drevops/eddy/issues), and send changes as pull requests against `main`.

➡️ [Contributing](https://eddy.drevops.com/contributing) in the Eddy documentation covers how the repository is laid out and how to build and test the demo extension, with these pages:

- [Scaffold tests](https://eddy.drevops.com/contributing/scaffold-tests) - the self-tests, their groups and the snapshot fixtures.
- [Tooling releases](https://eddy.drevops.com/contributing/tooling-releases) - publishing and releasing the `drevops/eddy-tooling` package.
- [Documentation](https://eddy.drevops.com/contributing/documentation) - working on the docs and publishing them.

Their source lives in [`.eddy/docs/content/contributing/`](.eddy/docs/content/contributing), and [`.eddy/CLAUDE.md`](.eddy/CLAUDE.md) is the full maintenance reference.

When someone runs `php install.php` to create a project from this template, this file is replaced by the generated project's own `CONTRIBUTING.md`, produced from `CONTRIBUTING.dist.md`. Keep scaffold-specific notes in the docs and consumer-facing notes in `CONTRIBUTING.dist.md`.
