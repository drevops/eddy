#!/usr/bin/env bash
##
# Enable the module that the theme bundles.

set -eu

build/vendor/bin/drush -l "$(vendor/bin/eddy-info site-url)" pm:enable force_crystal_companion -y
