#!/usr/bin/env bash
##
# Example post-assemble script.
#
# Runs at the end of `eddy-assemble` (after dependencies are
# installed and the extension is symlinked into `build/`). The current
# working directory is the project root. Any non-zero exit aborts the
# parent assemble run.
#
# Drop your own logic in any file matching `scripts/assemble-*.sh` -
# all matching files run in lexicographic order.

set -eu

echo "[example] post-assemble script ran."
