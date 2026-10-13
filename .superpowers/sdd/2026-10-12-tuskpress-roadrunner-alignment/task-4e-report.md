# Task 4e report

## RED

Command:

```text
vendor/bin/phpunit --configuration phpunit.xml tests/Architecture/DockerContractTest.php --filter test_smoke_logs_in_after_install_and_verifies_redirect_and_admin_session
```

Output: expected failure, `1 test, 4 assertions, 1 failure`. The contract expected `--data-urlencode 'redirect_to=wp-admin'` while the smoke script still contained `redirect_to=/wp-admin`.

## GREEN

Focused command:

```text
vendor/bin/phpunit --configuration phpunit.xml tests/Architecture/DockerContractTest.php --filter test_smoke_logs_in_after_install_and_verifies_redirect_and_admin_session
```

Output: `OK (1 test, 8 assertions)`.

Full suite command:

```text
vendor/bin/phpunit --configuration phpunit.xml
```

Output: `OK (25 tests, 97 assertions)`.

Docker and the smoke script were not run.
