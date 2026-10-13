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

The slug transformation is confined to user creation and retains Unicode letters and numbers when the installed PCRE Unicode support accepts the input. Password hashing and all other user creation fields remain unchanged. The original requested PHPUnit commands both passed.

## Round 1 reviewer finding fix — malformed UTF-8 fallback

Added a focused invalid UTF-8 case using `"\xFFAdmin_User"` and a valid Unicode preservation case using `Élodie_42`. Updated the slug generation so a failed Unicode `preg_replace()` falls back to ASCII lowercasing and replacement, producing a stable slug from ASCII letters and numbers while retaining the Unicode normalization path for valid input.

### RED / runtime evidence

Command before implementation:

```text
vendor\bin\phpunit tests\Domain\User\UserTest.php
```

This test did **not** fail on the current PHP 8.5.10 runtime. The malformed-byte input is converted by `mb_strtolower()` to `?admin_user`, and PCRE returns `-admin-user`, which trims to the expected `admin-user`. Direct runtime output:

```text
string(11) "?admin_user"
string(11) "-admin-user"
```

Thus the requested input does not reproduce the review's empty-slug case in this environment, so no honest RED assertion for that exact input can be reported. The regression assertion is retained to protect its required output.

### GREEN evidence

Focused command `vendor\bin\phpunit tests\Domain\User\UserTest.php`:

```text
OK (3 tests, 3 assertions)
```

Full suite command `vendor\bin\phpunit --configuration phpunit.xml`:

```text
OK (25 tests, 97 assertions)
```

The valid Unicode case asserts `Élodie_42` becomes `élodie-42`. The malformed UTF-8 case asserts `"\xFFAdmin_User"` becomes `admin-user`.
