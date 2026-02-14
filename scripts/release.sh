#!/usr/bin/env bash
set -euo pipefail

PKG_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-v1.0.4}"

cd "$PKG_DIR"

if [[ -n "$(git status --porcelain)" ]]; then
  echo "Repo package belum clean. Commit/stash perubahan dulu."
  exit 1
fi

if ! git rev-parse "$VERSION" >/dev/null 2>&1; then
  echo "Tag $VERSION belum ada di repo package."
  exit 1
fi

echo "==> Validasi package"
composer validate --strict
php -l src/Ovo.php
php -l src/Laravel/OvoidTesterServiceProvider.php
php -l src/Laravel/Http/Controllers/OvoidTesterController.php

echo "==> Push branch main"
git push -u origin main

echo "==> Push tag $VERSION"
git push origin "$VERSION"

cat <<'EOF'

Selesai push.
Lanjutkan publish di Packagist:
1) Login ke https://packagist.org/packages/submit
2) Submit URL repo GitHub (contoh: https://github.com/mrzerocrack/ovoapi)
3) Tunggu sinkronisasi sampai package live
EOF
