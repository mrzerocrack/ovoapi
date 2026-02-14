# Changelog

All notable changes to this project will be documented in this file.

## [1.0.1] - 2026-02-14

### Added
- Package-local release helper script: `scripts/release.sh`.

### Changed
- Release checklist now points to direct package script and project migration script.

## [1.0.0] - 2026-02-14

### Added
- Community-maintained fork package `mrzeroc/ovo-api` with compatibility namespace `Namdevel\\Ovo`.
- Laravel auto-discovery service provider for OVOID web tester.
- Built-in browser tester route set (`/ovoid`) with controller, view, and config.
- Updated QRIS flow handling and fallback behavior for better compatibility with latest OVO app behavior.
- Documentation for installation, config, tester usage, and Packagist release flow.

### Changed
- `QrisPay` flow now aligns with current checkout schema (`campaign_id`, `metadata.product_name`, modern payment type mapping).
- Request fallback now prioritizes successful JSON responses across multiple host candidates.
- Package declares `replace` for `namdevel/ovoid-api` to ease migration.
