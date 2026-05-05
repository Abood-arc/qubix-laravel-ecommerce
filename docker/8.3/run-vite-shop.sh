#!/usr/bin/env bash
# Starts Shop Vite when SAIL_VITE_SHOP is enabled (default: on for local Sail).
# When off, idles so Supervisor stays healthy without running a dev server (CI/production-like runs).
set -euo pipefail
cd /var/www/html
case "${SAIL_VITE_SHOP:-true}" in
    0|false|False|FALSE|no|No|NO|off|Off|OFF)
        exec sleep infinity
        ;;
    *)
        exec npm run dev:shop
        ;;
esac
