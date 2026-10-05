SHELL=/bin/bash

# Load variables from .env if present and export them to recipe shells. The
# leading '-' on -include suppresses errors when the file does not exist.
-include .env
export

# Resolve a relative TMPDIR to an absolute path so build/-dir tools (which
# chdir into build) can still find it. No-op when unset or already absolute.
ifdef TMPDIR
export TMPDIR := $(abspath $(TMPDIR))
endif

WEBSERVER_HOST ?= localhost
WEBSERVER_PORT ?= 8000

# Resolve the site URL through the shared `eddy-info` resolver so `drush`
# and `login` report the same tunnel-aware URL as start, provision, and info
# (see resolve_site_url()). Lazy `=` so the probe runs only when the recipes
# that expand it are invoked.
DRUSH = build/vendor/bin/drush -l "$(shell vendor/bin/eddy-info site-url)"
# Recipes expand $(DRUSH) themselves. Exported, it would run the probe for
# every recipe, including the one that installs the tooling.
unexport DRUSH

# Test environment exported to every recipe (see the blanket `export` above),
# matching what the ahoy entrypoint exports for every command, so `make
# test*` runs against the same base URL, database, and browser-output
# directory as `ahoy test*`.
EXTENSION_NAME = $(shell basename -s .info.yml -- ./*.info.yml)
SIMPLETEST_BASE_URL = http://$(WEBSERVER_HOST):$(WEBSERVER_PORT)
SIMPLETEST_DB = sqlite://localhost/drupal_test_$(EXTENSION_NAME).sqlite
BROWSERTEST_OUTPUT_DIRECTORY = $(CURDIR)/.logs/browser_output

define title
	@echo -e "\n\033[36m$(1)\033[0m"
endef

.PHONY: assemble build debug debug-off debug-on delete describe destroy drush help info lint lint-fix login provision reset start stop test tooling xdebug xdebug-off xdebug-on
#;< DEV_PHPUNIT
.PHONY: test-unit test-kernel test-functional
#;> DEV_PHPUNIT
#;< DEV_FUNCTIONAL_JAVASCRIPT
.PHONY: test-functional-javascript browser-start browser-stop
#;> DEV_FUNCTIONAL_JAVASCRIPT
#;< DEV_JEST
.PHONY: test-javascript test-js
#;> DEV_JEST

help:
	@echo "COMMANDS"
	@echo "========"
	@echo "build                      - Build or rebuild the project."
	@echo "assemble                   - Assemble a codebase using project code and all required dependencies."
	@echo "debug                      - Enable PHP XDebug step-debugging for the development server (aliases: debug-on, xdebug, xdebug-on)."
	@echo "drush                      - Run Drush command."
	@echo "info                       - Print a read-only summary of the current environment (alias: describe)."
	@echo "lint                       - Check coding standards for violations."
	@echo "lint-fix                   - Fix violations in coding standards."
	@echo "login                      - Login to a website."
	@echo "provision                  - Provision application within assembled codebase."
	@echo "reset                      - Reset project to the default state (aliases: delete, destroy)."
	@echo "start                      - Start development environment (aliases: debug-off, xdebug-off)."
	@echo "stop                       - Stop development environment."
	@echo "test                       - Run all tests."
	@#;< DEV_PHPUNIT
	@echo "test-functional            - Run functional tests."
	@#;> DEV_PHPUNIT
	@#;< DEV_FUNCTIONAL_JAVASCRIPT
	@echo "test-functional-javascript - Run FunctionalJavascript tests."
	@#;> DEV_FUNCTIONAL_JAVASCRIPT
	@#;< DEV_PHPUNIT
	@echo "test-kernel                - Run kernel tests."
	@echo "test-unit                  - Run unit tests."
	@#;> DEV_PHPUNIT
	@#;< DEV_FUNCTIONAL_JAVASCRIPT
	@echo "browser-start              - Start the browser for FunctionalJavascript tests."
	@echo "browser-stop               - Stop the browser."
	@#;> DEV_FUNCTIONAL_JAVASCRIPT
	@#;< DEV_JEST
	@echo "test-javascript            - Run JavaScript unit tests (alias: test-js)."
	@#;> DEV_JEST

# Every target that runs a tooling command installs the tooling first. Progress
# goes to stderr, so a target's own output stays capturable.
assemble build debug drush info login provision start stop: tooling
#;< DEV_FUNCTIONAL_JAVASCRIPT
browser-start browser-stop test-functional-javascript: tooling
#;> DEV_FUNCTIONAL_JAVASCRIPT

tooling:
	@./scripts/eddy-tooling >&2

build:
	@$(MAKE) stop >/dev/null 2>&1 || true
	$(MAKE) assemble
	$(MAKE) start
	$(MAKE) provision

assemble:
	vendor/bin/eddy-assemble

start:
	vendor/bin/eddy-start

stop:
	vendor/bin/eddy-stop

info:
	@vendor/bin/eddy-info

# Enable PHP XDebug step-debugging by restarting the PHP server with
# `-d xdebug.mode=debug -d xdebug.start_with_request=yes`. State is
# probed by `info xdebug`, which inspects the running server's command
# line. Run `make start` to disable.
debug:
	@[ "$$(vendor/bin/eddy-info xdebug)" = "enabled" ] && echo "XDebug is already enabled. Run 'make start' to disable." || \
		(XDEBUG=1 vendor/bin/eddy-start && sleep 1 && [ "$$(vendor/bin/eddy-info xdebug)" = "enabled" ] && echo "Enabled XDebug. Run 'make start' to disable." || (echo "Failed to enable XDebug." && exit 1))

# Make has no native command aliases - the alias targets declare `debug` as
# their sole prerequisite, so running e.g. `make xdebug` executes the `debug`
# recipe via the prerequisite chain.
debug-on xdebug xdebug-on: debug

# Mirror the ahoy `start` aliases. `make debug-off` runs the `start` recipe
# via the prerequisite chain, which restarts without XDebug.
debug-off xdebug-off: start

# DDEV uses `describe` for the equivalent of our `info`. Add the alias so
# developers coming from DDEV find a familiar verb.
describe: info

# Lando uses `destroy` and DDEV uses `delete` for what our `reset` does.
delete destroy: reset

# Allow running Drush commands with `make drush <command>`
ifeq (drush,$(firstword $(MAKECMDGOALS)))
  DRUSH_RUN_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
  $(eval $(DRUSH_RUN_ARGS):;@:)
endif

drush:
	$(DRUSH) $(DRUSH_RUN_ARGS)

login:
	@url="$$($(DRUSH) uli)"; printf '%s\n' "$$url"; if [ -n "$$LOGIN_QRCODE" ]; then vendor/bin/eddy-qrcode "$$url"; fi

provision:
	vendor/bin/eddy-provision

lint:
	@#;< DEV_PHPCS
	$(call title,Running PHPCS)
	pushd "build" >/dev/null || exit 1 && vendor/bin/phpcs && popd >/dev/null || exit 1
	@#;> DEV_PHPCS
	@#;< DEV_PHPSTAN
	$(call title,Running PHPStan)
	pushd "build" >/dev/null || exit 1 && vendor/bin/phpstan && popd >/dev/null || exit 1
	@#;> DEV_PHPSTAN
	@#;< DEV_RECTOR
	$(call title,Running Rector)
	pushd "build" >/dev/null || exit 1 && vendor/bin/rector --clear-cache --dry-run && popd >/dev/null || exit 1
	@#;> DEV_RECTOR
	@#;< DEV_TWIGCS
	$(call title,Running Twig CS Fixer)
	pushd "build" >/dev/null || exit 1 && vendor/bin/twig-cs-fixer && popd >/dev/null || exit 1
	@#;> DEV_TWIGCS
	@#;< DEV_NODEJS_LINT
	$(call title,Running ESLint)
	pushd "build" >/dev/null || exit 1 && ([ ! -d node_modules ] || npm run lint) && popd >/dev/null || exit 1
	@#;> DEV_NODEJS_LINT
	@#;< DEV_CSPELL
	$(call title,Running CSpell)
	[ -d node_modules ] || npm install --no-audit --no-fund
	npm run lint-spell
	@#;> DEV_CSPELL

lint-fix:
	@#;< DEV_RECTOR
	$(call title,Running Rector)
	pushd "build" >/dev/null || exit 1 && vendor/bin/rector --clear-cache && popd >/dev/null || exit 1
	@#;> DEV_RECTOR
	@#;< DEV_PHPCS
	$(call title,Running PHPCBF)
	pushd "build" >/dev/null || exit 1 && vendor/bin/phpcbf && popd >/dev/null || exit 1
	@#;> DEV_PHPCS
	@#;< DEV_TWIGCS
	$(call title,Running Twig CS Fixer)
	pushd "build" >/dev/null || exit 1 && vendor/bin/twig-cs-fixer --no-cache --fix && popd >/dev/null || exit 1
	@#;> DEV_TWIGCS
	@#;< DEV_NODEJS_LINT
	$(call title,Running ESLint)
	pushd "build" >/dev/null || exit 1 && ([ ! -d node_modules ] || npm run lint-fix) && popd >/dev/null || exit 1
	@#;> DEV_NODEJS_LINT

# Allow passing extra args to phpunit test targets, mirroring the `drush`
# arg-capture above. The target list is split across the same DEV_* markers
# as the `.PHONY` declarations so a stripped feature leaves no dangling entry.
TEST_TARGETS := test
#;< DEV_PHPUNIT
TEST_TARGETS += test-unit test-kernel test-functional
#;> DEV_PHPUNIT
#;< DEV_FUNCTIONAL_JAVASCRIPT
TEST_TARGETS += test-functional-javascript
#;> DEV_FUNCTIONAL_JAVASCRIPT
ifneq (,$(filter $(firstword $(MAKECMDGOALS)),$(TEST_TARGETS)))
  TEST_RUN_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
  $(eval $(TEST_RUN_ARGS):;@:)
endif

test:
	@#;< DEV_PHPUNIT
	$(call title,Running PHPUnit)
	pushd "build" >/dev/null || exit 1 && php -d pcov.directory=.. vendor/bin/phpunit $(TEST_RUN_ARGS) && popd >/dev/null || exit 1
	@#;> DEV_PHPUNIT
	@#;< DEV_JEST
	$(call title,Running Jest)
	pushd "build" >/dev/null || exit 1 && ([ ! -d node_modules ] || npm test) && popd >/dev/null || exit 1
	@#;> DEV_JEST

#;< DEV_PHPUNIT
test-unit:
	pushd "build" >/dev/null || exit 1 && \
	php -d pcov.directory=.. vendor/bin/phpunit --testsuite unit $(TEST_RUN_ARGS) && \
	popd >/dev/null || exit 1

test-kernel:
	pushd "build" >/dev/null || exit 1 && \
	php -d pcov.directory=.. vendor/bin/phpunit --testsuite kernel $(TEST_RUN_ARGS) && \
	popd >/dev/null || exit 1

test-functional:
	pushd "build" >/dev/null || exit 1 && \
	php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional $(TEST_RUN_ARGS) && \
	popd >/dev/null || exit 1
#;> DEV_PHPUNIT

#;< DEV_FUNCTIONAL_JAVASCRIPT
test-functional-javascript:
	$(MAKE) browser-start
	export WEBDRIVER_PORT="$$(vendor/bin/eddy-info webdriver-port)" && \
	pushd "build" >/dev/null || exit 1 && \
	php -d pcov.directory=.. vendor/bin/phpunit --testsuite functional-javascript $(TEST_RUN_ARGS) && \
	popd >/dev/null || exit 1
#;> DEV_FUNCTIONAL_JAVASCRIPT

#;< DEV_FUNCTIONAL_JAVASCRIPT
browser-start:
	vendor/bin/eddy-browser start

browser-stop:
	vendor/bin/eddy-browser stop
#;> DEV_FUNCTIONAL_JAVASCRIPT

#;< DEV_JEST
test-javascript:
	pushd "build" >/dev/null || exit 1 && \
	([ ! -d node_modules ] || npm test) && \
	popd >/dev/null || exit 1

# Keep `make test-js` working for projects that already script that name.
test-js: test-javascript
#;> DEV_JEST

reset:
	killall -9 php >/dev/null 2>&1 || true
	chmod -Rf 777 build .logs > /dev/null 2>&1 || true
	rm -Rf build > /dev/null 2>&1 || true
	rm -Rf .logs > /dev/null 2>&1 || true
	rm -Rf vendor > /dev/null 2>&1 || true

.DEFAULT_GOAL := build
