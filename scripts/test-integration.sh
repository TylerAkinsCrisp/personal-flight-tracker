#!/usr/bin/env bash
set -euo pipefail

required_vars=(
  TEST_DB_ENABLED
  TEST_DB_HOST
  TEST_DB_NAME
  TEST_DB_USER
  TEST_DB_PASS
)

missing=()
for var_name in "${required_vars[@]}"; do
  if [[ -z "${!var_name:-}" ]]; then
    missing+=("$var_name")
  fi
done

if [[ "${#missing[@]}" -gt 0 ]]; then
  echo "Missing required environment variables for integration tests:"
  printf ' - %s\n' "${missing[@]}"
  echo ""
  echo "Example:"
  echo "TEST_DB_ENABLED=1 TEST_DB_HOST=localhost TEST_DB_NAME=flight_tracker_test TEST_DB_USER=test_user TEST_DB_PASS=test_pass composer test:integration"
  exit 1
fi

if [[ "${TEST_DB_ENABLED}" != "1" ]]; then
  echo "TEST_DB_ENABLED must be set to 1 for integration tests."
  exit 1
fi

if [[ ! -x "vendor/bin/phpunit" ]]; then
  echo "vendor/bin/phpunit not found. Run: composer install"
  exit 1
fi

vendor/bin/phpunit --testsuite Integration
