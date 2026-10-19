# Eddy tooling

The commands that build, run, test and deploy a Drupal module or theme created from [Eddy](https://github.com/drevops/eddy), the Drupal extension scaffold. Eddy's command wrappers (`ahoy` and `make`) and its CI workflows call them, and you can run them directly as `vendor/bin/eddy-<command>` from the extension's root directory.

> [!IMPORTANT]
> This repository is a **read-only mirror** of the [`.eddy/tooling/`](https://github.com/drevops/eddy/tree/main/.eddy/tooling) directory of [`drevops/eddy`](https://github.com/drevops/eddy). Report bugs and propose changes [there](https://github.com/drevops/eddy/issues), not here. Each commit here corresponds to a commit in `drevops/eddy`, and its message body records the source commit.

## Installation

A project created from Eddy doesn't install this package with a plain `composer install`, because its `composer.json` belongs to the extension and the Drupal site is assembled into `build/` by the tooling itself. Instead, `scripts/eddy-tooling` installs the package into the project's `vendor/` directory. The command wrappers run it before the commands that need it, and CI runs it as a step of its own.

A project uses `scripts/eddy-tooling` as-is from Eddy: never modify it, by hand or with AI, since a scaffold update replaces it with the release's copy. To change what a command does, [patch the package](#patching-the-package) instead.

The installer reads the version constraint from `composer.dev.json`:

```json
"require-dev": {
    "drevops/eddy-tooling": "~1.0.0"
}
```

Keep the `~` constraint. It accepts patch releases but holds the minor version, so a new minor release reaches your project with the next scaffold update rather than in the middle of a CI run.

The package requires PHP 8.3 or newer, so every CI job and local build that installs it needs PHP 8.3 or newer too.

The installer runs Composer only when the constraint, the patches for the package or the contents of a local patch file change, or when a command is missing from `vendor/bin`. Otherwise it returns straight away, so running it before every command costs next to nothing. A local install keeps its patch release until `vendor/` is removed, which `ahoy reset` and `make reset` do.

## Commands

| Command              | Purpose                                                                                                                 |
|----------------------|-------------------------------------------------------------------------------------------------------------------------|
| `eddy-assemble`      | Assemble a Drupal codebase in `build/`, install dependencies, and symlink the extension.                                |
| `eddy-start`         | Launch the built-in PHP development server. Auto-discovers a free port in 8000-8099 and writes `.env`.                  |
| `eddy-stop`          | Stop the development server.                                                                                            |
| `eddy-provision`     | Install Drupal on the assembled site and enable the extension.                                                          |
| `eddy-login`         | Print a one-time login link without opening it in a browser, and draw it as a QR code when `LOGIN_QRCODE` is set.       |
| `eddy-deploy`        | Mirror the extension to a remote git repository (e.g. drupal.org). Used in CI.                                          |
| `eddy-browser-start` | Start the WebDriver backend used by FunctionalJavascript tests. Auto-discovers a free port from 4444 and writes `.env`. |
| `eddy-browser-stop`  | Stop the WebDriver backend.                                                                                             |
| `eddy-info`          | Print a summary of the environment, or a single field such as `site-url`, for the wrappers to consume.                  |
| `eddy-qrcode`        | Render a URL as a scannable QR code in the terminal.                                                                    |

`eddy-assemble` builds the Drupal version in `DRUPAL_VERSION`. When the variable isn't set, it builds the version in `extra.eddy.drupal-version` of `composer.dev.json`, and Drupal 11 when that isn't set either.

Before it touches `build/`, `eddy-assemble` reads the PHP requirement of the Drupal core release it resolved and stops when the PHP running it doesn't meet it, or when the `config.platform.php` that the extension's `composer.dev.json` or `composer.json` sets doesn't. It then sets `config.platform.php` in `build/composer.json` to that setting, or to the running PHP when the extension sets none, so a `platform` setting in the global Composer configuration doesn't change what gets installed. With `GITHUB_TOKEN` set, it passes the token to the Composer commands it runs through `COMPOSER_AUTH`, unless that variable is already set, and leaves the global Composer configuration alone.

Once those checks pass, and still before it touches `build/`, `eddy-assemble` stops the project's webserver when it's running, the way `eddy-stop` does: it runs the `stop-*.sh` [custom scripts](#custom-scripts), stops the tunnel, then stops the server. A failed check leaves the server running.

`eddy-start`, `eddy-stop` and `eddy-assemble` only stop a webserver that serves the project's own `build/web`, so they leave another project's server running when 2 projects record the same port. `eddy-start` fails and names the process that holds the port instead of stopping it. Once it has launched the server, it waits up to `WEBSERVER_WAIT_TIMEOUT` seconds, a whole number of 1 or more that defaults to 5, for the server to accept connections.

`eddy-browser-stop` treats the WebDriver backend the same way. A chromedriver belongs to the project whose root it runs from, and a Selenium container to the project that its `eddy.project` label names, so `eddy-browser-stop` leaves the backend of another project running, along with any process on the port that isn't a chromedriver.

`eddy-browser-start` reuses a backend of the selected type that already accepts WebDriver sessions on the port, whichever project started it, since every session is independent. It reads the type from the `/status` response, whose `message` names ChromeDriver or Selenium Grid. It doesn't reuse a backend of the other type, because the browser reaches the webserver at a different address from each one.

When the port holds anything it doesn't reuse, `eddy-browser-start` replaces only the project's own backend, whichever type that is, so switching `WEBDRIVER_BACKEND` doesn't need an `eddy-browser-stop` first. It fails and names anything else that holds the port, such as another project's backend or another process. A stopped Selenium container holds no port, so it never blocks `eddy-browser-start`, and the `selenium` backend replaces it whichever project started it.

## Verbose output

By default the commands print only their own `[TASK]`/`[ OK ]` progress and suppress the output of the tools they run (Composer, npm, Drush). When a tool fails, its captured output is shown so the failure is diagnosable. The output of the project's own commands - `npm run build` and the custom scripts below - is always shown. Set `DEBUG=1` to stream the full output of every tool live, for example `DEBUG=1 make build` or `DEBUG=1 ahoy build`.

The processes that keep running after a command returns - the PHP webserver, chromedriver and the Cloudflare tunnel - write their output to `php.log`, `chromedriver.log` and `cloudflared.log` in the project's `.logs/` directory.

## Public tunnel

`eddy-start`, `eddy-provision` and `eddy-stop` can expose the development server through a [Cloudflare quick tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/do-more-with-tunnels/trycloudflare/), a public `*.trycloudflare.com` HTTPS URL that needs no Cloudflare account, DNS or configuration. Set `CLOUDFLARE_TUNNEL=1` in the environment or in `.env`, and put the [`cloudflared`](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/) binary on `PATH`:

- `eddy-start` starts a tunnel, or reuses one whose URL still answers, and writes the URL to `.env` as `TUNNEL_URL`. Without `cloudflared` it skips the tunnel with a note.
- `eddy-provision` adds the reverse-proxy and trusted-host settings Drupal needs behind the tunnel to `settings.php`.
- `eddy-stop` stops the tunnel and removes its URL from `.env`, even once `CLOUDFLARE_TUNNEL` is unset.

`eddy-start`, `eddy-provision` and `eddy-info` report `TUNNEL_URL` as the site URL whichever tool wrote it, so a [custom script](#custom-scripts) can wire in another tunnel the same way.

> [!WARNING]
> Anyone with the URL can reach the site while the tunnel is up. Use disposable test data only, and stop the tunnel when you're done.

## Custom scripts

`eddy-assemble`, `eddy-provision`, `eddy-start` and `eddy-stop` each look for `scripts/<prefix>-*.sh` in the project root and run any matches: `assemble-*.sh` and `provision-*.sh` at the end of their phase, `start-*.sh` once the webserver is serving, and `stop-*.sh` before the webserver stops, both in `eddy-stop` and when `eddy-assemble` stops a running webserver. Scripts run in lexicographic order from the project root, inherit the parent environment, and a non-zero exit aborts the parent.

Custom scripts are the way to add project-specific steps. Reach for the next section only when you need to change what a command itself does.

## Patching the package

The package is installed into `vendor/`, so an edit made there is lost on the next install. To change a command, declare a patch for the package in `composer.dev.json`, next to its version constraint:

```json
"extra": {
    "patches": {
        "drevops/eddy-tooling": {
            "Describe the change": "patches/eddy-tooling-change.patch"
        }
    }
}
```

The installer applies the patches with [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches) and installs the package again whenever the list changes or a local patch file is edited. Local patch paths are relative to the project root. The patches, like the package, stay out of the site build.

## Testing

The commands are tested in [`drevops/eddy`](https://github.com/drevops/eddy): unit tests in [`.eddy/tests`](https://github.com/drevops/eddy/tree/main/.eddy/tests) cover every command with its external calls mocked, and functional tests build, run and lint a real extension through the same commands.

## License

[GPL-2.0-or-later](LICENSE)
