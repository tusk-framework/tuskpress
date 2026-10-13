# Task 4d implementation report

## Change

Updated `src/Domain/User/User.php` so `User::create()` derives `nicename` locally rather than calling WordPress's unavailable `sanitize_title()` helper. It lowercases the login with UTF-8-aware `mb_strtolower`, replaces each run of characters other than Unicode letters and numbers with one hyphen, and trims leading and trailing hyphens. Added `tests/Domain/User/UserTest.php` with a focused assertion that `Smoke_User 42` becomes `smoke-user-42`.

No installer/controller, schema, auth, or smoke assertion files were changed.

## RED evidence

Command:

```text
vendor\bin\phpunit tests\Domain\User\UserTest.php
```

Output before the implementation:

```text
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.5.10
Configuration: C:\Users\wende\Projects\tusk\.worktrees\tuskpress-roadrunner\phpunit.xml

E                                                                   1 / 1 (100%)

There was 1 error:

1) TuskPress\Tests\Domain\User\UserTest::testCreateGeneratesUrlFriendlyNicenameFromLogin
Error: Call to undefined function TuskPress\Domain\User\sanitize_title()

...\src\Domain\User\User.php:223
...\tests\Domain\User\UserTest.php:16

ERRORS!
Tests: 1, Assertions: 0, Errors: 1.
```

## GREEN evidence

Focused command `vendor\bin\phpunit tests\Domain\User\UserTest.php`:

```text
OK (1 test, 1 assertion)
```

Full suite command `vendor\bin\phpunit --configuration phpunit.xml`:

```text
OK (23 tests, 95 assertions)
```

`git diff --check` completed without errors.

## Self-review and concerns

The slug transformation is confined to user creation and retains Unicode letters and numbers when the installed PCRE Unicode support accepts the input. If the Unicode regex unexpectedly fails, the implementation currently falls back to an empty slug. Password hashing and all other user creation fields remain unchanged. The requested PHPUnit commands both pass.
