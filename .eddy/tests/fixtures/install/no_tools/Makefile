SHELL=/bin/bash

# Load variables from .env if present and export them to recipe shells. The
# leading '-' on -include suppresses errors when the file does not exist.
#
# An .env assignment overrides the environment, so the shell value of each
# variable that .env sets is saved before the include and restored after.
# An empty shell value counts as unset, as in resolve_env_value().
DOTENV_VARS := $(shell sed -n 's/^[[:space:]]*\([A-Za-z_][A-Za-z0-9_]*\)[[:space:]]*=.*/\1/p' .env 2>/dev/null)
DOTENV_SHELL_VARS := $(sort $(foreach var,$(DOTENV_VARS),$(if $(value $(var)),$(var))))
$(foreach var,$(DOTENV_SHELL_VARS),$(eval DOTENV_SHELL_$(var) := $$(value $(var))))
-include .env
$(foreach var,$(DOTENV_SHELL_VARS),$(eval $(var) := $$(DOTENV_SHELL_$(var))))
export
unexport DOTENV_VARS DOTENV_SHELL_VARS $(foreach var,$(DOTENV_SHELL_VARS),DOTENV_SHELL_$(var))

# Resolve a relative TMPDIR to an absolute path so build/-dir tools (which
# chdir into build) can still find it. No-op when unset or already absolute.
ifdef TMPDIR
export TMPDIR := $(abspath $(TMPDIR))
endif

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
# directory as `ahoy test*`. The webserver host and port fall back to their
# defaults only inside the URL: an exported default port would stop `start`
# from discovering a free one.
EXTENSION_NAME = $(shell basename -s .info.yml -- ./*.info.yml)
SIMPLETEST_BASE_URL = http://$(or $(WEBSERVER_HOST),localhost):$(or $(WEBSERVER_PORT),8000)
SIMPLETEST_DB = sqlite://localhost/drupal_test_$(EXTENSION_NAME).sqlite
BROWSERTEST_OUTPUT_DIRECTORY = $(CURDIR)/.logs/browser_output

define title
	@echo -e "\n\033[36m$(1)\033[0m"
endef

.PHONY: assemble build debug debug-off debug-on delete describe destroy drush help info lint lint-fix login provision reset start stop test tooling xdebug xdebug-off xdebug-on

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

# Every target that runs a tooling command installs the tooling first, except
# `reset`, which still cleans up when the install fails. Progress goes to
# stderr, so a captured target output holds only the target's own.
assemble build debug drush info login provision start stop: tooling

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
		(XDEBUG=1 vendor/bin/eddy-start && [ "$$(vendor/bin/eddy-info xdebug)" = "enabled" ] && echo "Enabled XDebug. Run 'make start' to disable." || (echo "Failed to enable XDebug." && exit 1))

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

lint-fix:

# Allow passing extra args to phpunit test targets, mirroring the `drush`
# arg-capture above. The target list is split across the same DEV_* markers
# as the `.PHONY` declarations so a stripped feature leaves no dangling entry.
TEST_TARGETS := test
ifneq (,$(filter $(firstword $(MAKECMDGOALS)),$(TEST_TARGETS)))
  TEST_RUN_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
  $(eval $(TEST_RUN_ARGS):;@:)
endif

test:

reset:
	vendor/bin/eddy-stop > /dev/null 2>&1 || true
	chmod -Rf 777 build .logs > /dev/null 2>&1 || true
	rm -Rf build > /dev/null 2>&1 || true
	rm -Rf .logs > /dev/null 2>&1 || true
	rm -Rf vendor > /dev/null 2>&1 || true

.DEFAULT_GOAL := build
