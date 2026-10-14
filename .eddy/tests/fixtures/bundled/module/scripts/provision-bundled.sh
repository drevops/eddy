#!/usr/bin/env bash
##
# Enable the theme that the module bundles.

set -eu

build/vendor/bin/drush -l "$(vendor/bin/eddy-info site-url)" theme:enable force_crystal_admin
