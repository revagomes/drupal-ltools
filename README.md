# Drupal ltools

`ltools` is a lightweight locale helper module.

## Drupal compatibility

This repository is set up for Drupal 11 module metadata (`ltools.info.yml` with `core_version_requirement: ^11`).

## CI checks

GitHub Actions workflow: `.github/workflows/ci.yml`

The CI pipeline runs fast, high-value checks for review and publication readiness:

- Composer manifest validation
- Dependency installation
- PHP syntax linting
- YAML parsing validation
- PHPCS (Drupal/DrupalPractice when available, with PSR-12 fallback)
- PHPUnit only when a PHPUnit config file is present
