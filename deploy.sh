#!/bin/sh
# Deploy GitHub main to the kiosk (runs ~/bin/deploy there over SSH). Push your commits first.
# Same as the button on http://192.168.1.6/deploy.php, but authenticated by your SSH key.
# Usage: ./deploy.sh [user@host]
set -eu
ssh "${1:-kropek@192.168.1.6}" bin/deploy
