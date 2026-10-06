#!/usr/bin/env bash
set -euo pipefail
echo 'Partial deployment is disabled: it could mix incompatible code and configuration.' >&2
echo 'Use the reviewed release workflow in DEPLOYMENT.md.' >&2
exit 2
