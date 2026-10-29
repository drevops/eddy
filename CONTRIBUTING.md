# Contributing

Thank you for your interest in improving Eddy. To report a bug or suggest a change, open an issue in the [issue queue](https://github.com/drevops/eddy/issues). Send changes as pull requests against `main`.

➡️ [Contributing](https://eddy.drevops.com/contributing) in the Eddy documentation covers how the repository is laid out and how to build and test the demo extension, with these pages:

- [Scaffold tests](https://eddy.drevops.com/contributing/scaffold-tests) - the self-tests, their groups and the snapshot fixtures.
- [Tooling releases](https://eddy.drevops.com/contributing/tooling-releases) - publishing and releasing the `drevops/eddy-tooling` package.
- [Documentation](https://eddy.drevops.com/contributing/documentation) - working on the docs and publishing them.

Their source lives in [`.eddy/docs/content/contributing/`](.eddy/docs/content/contributing), and [`.eddy/CLAUDE.md`](.eddy/CLAUDE.md) is the full maintenance reference.

`php install.php` replaces this file with the generated project's own `CONTRIBUTING.md`, which it renders from `CONTRIBUTING.dist.md`. Keep notes about the scaffold in the docs, and notes for generated projects in `CONTRIBUTING.dist.md`.
