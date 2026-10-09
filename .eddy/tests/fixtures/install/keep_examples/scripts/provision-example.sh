#!/usr/bin/env bash
##
# Example post-provision script.
#
# Runs at the end of `eddy-provision` (after the site is
# installed, the extension is enabled, and caches are pre-warmed). The
# current working directory is the project root. Any non-zero exit
# aborts the parent provision run.
#
# Drop your own logic in any file matching `scripts/provision-*.sh` -
# all matching files run in lexicographic order.

set -eu

echo "[example] post-provision script ran."
