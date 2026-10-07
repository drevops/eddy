#!/usr/bin/env bash
##
# Example post-start script.
#
# Runs at the end of `eddy-start` (after the PHP webserver is up and
# serving). The current working directory is the project root. Any non-zero
# exit aborts the parent start run.
#
# Drop your own logic in any file matching `scripts/start-*.sh` - all
# matching files run in lexicographic order. Use this for server-lifecycle
# tasks such as launching an access tunnel or a file watcher.

set -eu

echo "[example] post-start script ran."
