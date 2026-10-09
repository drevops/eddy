#!/usr/bin/env bash
##
# Example pre-stop script.
#
# Runs during `eddy-stop` before the PHP webserver is stopped, while it
# is still reachable. The current working directory is the project root. Any
# non-zero exit aborts the parent stop run.
#
# Drop your own logic in any file matching `scripts/stop-*.sh` - all matching
# files run in lexicographic order. Use this to tear down whatever a matching
# `start-*.sh` script launched.

set -eu

echo "[example] pre-stop script ran."
