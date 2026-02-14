# Release Checklist

Shortcut command (from project root):

```bash
./packages/mrzeroc/ovo-api/scripts/release.sh v1.0.3
```

## 1) Prepare local repository

- [ ] Ensure package files are committed
- [ ] Ensure `composer.json` metadata is correct
- [ ] Ensure README and CHANGELOG are up-to-date
- [ ] Run syntax checks:
  - `php -l src/Ovo.php`
  - `php -l src/Laravel/OvoidTesterServiceProvider.php`
  - `php -l src/Laravel/Http/Controllers/OvoidTesterController.php`

## 2) Push to GitHub

- [ ] Create repository: `mrzeroc/ovo-api`
- [ ] Add remote:
  - `git remote add origin git@github.com:mrzeroc/ovo-api.git`
- [ ] Push default branch:
  - `git push -u origin main`
- [ ] Push tags:
  - `git push origin --tags`

## 3) Publish to Packagist

- [ ] Login to https://packagist.org
- [ ] Submit repository URL
- [ ] Enable auto-update via GitHub hook (recommended)
- [ ] Verify package appears as `mrzeroc/ovo-api`

## 4) Post-publish verification

- [ ] In a clean Laravel project, run:
  - `composer require mrzeroc/ovo-api:^1.0`
- [ ] Verify route exists:
  - `php artisan route:list --path=ovoid`
- [ ] Open tester:
  - `http://127.0.0.1:8000/ovoid`

## 5) Migration from local path setup

- [ ] Remove local `path` repository from project composer.json
- [ ] Set requirement to stable tag (`^1.0`)
- [ ] Run `./scripts/switch-to-packagist-ovoid.sh --apply`
