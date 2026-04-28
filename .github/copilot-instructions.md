# AVideo Copilot Instructions

## Project Overview

AVideo is an open-source self-hosted video platform built with PHP, MySQL, JavaScript, Bootstrap, and jQuery.
The codebase has a plugin-based architecture with ~130+ plugins. All existing helpers, classes, plugins, and
patterns must be reused before creating anything new.

## Stack

- **Backend**: PHP 7.3+ / PHP 8.x (no namespaces in core; minimal PSR-4 via Composer autoload)
- **Database**: MySQL via `sqlDAL` prepared-statement helpers (`objects/mysql_dal.php`)
- **Frontend**: Bootstrap 5.3, jQuery 4.0, Video.js 8.x, Socket.io 4.x, Chart.js 4.x
- **Architecture**: Plugin-based via `PluginAbstract` (`plugin/Plugin.abstract.php`)

## Critical Rules

### Search Before Creating
Before writing any new code, search the repository for existing logic:
- Global helpers: `objects/functions*.php`
- Database layer: `objects/mysql_dal.php` — use `sqlDAL::readSql()` / `sqlDAL::writeSql()`
- Security filters: `objects/functionsSecurity.php` — use `$securityFilter`, `xss_esc()`, `htmlspecialchars()`
- Auth checks: `User::isLogged()`, `User::isAdmin()`, `User::getId()` (`objects/user.php`)
- Logging: `_error_log($msg, AVideoLog::$ERROR)` — never `echo` errors to normal users
- Config: `$global['systemRootPath']`, `$global['webSiteRootURL']`, `AVideoConf` singleton
- Plugin hooks: `AVideoPlugin::getHeadCode()`, `getFooterCode()`, `getBodyContent()`, etc.
- Admin forms: `createTable()`, `jsonToFormElements()` (`admin/functions.php`)

### Do Not Invent
- Do not invent table names, column names, constants, class names, or function names.
- Do not guess whether a plugin, table, or function exists — search first using the repository tools.
- Never present fabricated function names, method signatures, or class names as if they exist in the codebase.
- If still unsure after searching, add a `// TODO: verify this exists` comment rather than inventing an API.
- When citing a method from `objects/user.php` or any other file, verify it in the source before referencing it.

### Plugin Architecture
- Keep plugin logic inside its own plugin directory (`plugin/PluginName/`).
- Extend `PluginAbstract`; implement `getUUID()`, `getName()`, `getDescription()`.
- Register hooks via the standard hook methods (`getHeadCode`, `getFooterCode`, etc.).
- Database migrations go in `plugin/PluginName/install/updateVX.X.sql`.
- Never modify the core plugin base without explicit request.

### Coding Style
- Match the style of nearby files exactly.
- PHP: no strict modern syntax unless it already appears in nearby files.
- Error handling: `try { ... } catch (\Throwable $th) { _error_log($th->getMessage()); }`.
- AJAX endpoints: name them `*.json.php`; return `['error' => false, 'msg' => '...']`.
- Use `modal.showPleaseWait()` / `modal.hidePleaseWait()` around async operations.
- Use `avideoToastSuccess()`, `avideoToastError()`, `avideoAlertError()` for user feedback.

### Known Security Anti-Patterns — Never Repeat These

These bugs exist throughout the legacy codebase. Do not reproduce them:

```php
// WRONG — the most common mistake in AVideo plugins
// die(json_encode(...)) does NOT set HTTP 403, does NOT log, does NOT redirect to login
if (!User::isAdmin()) {
    die(json_encode(['error' => true, 'msg' => "You can't do this"])); // WRONG
}

// WRONG — http_response_code(403) alone is not enough
if (!User::isAdmin()) {
    http_response_code(403);
    die(json_encode(['error' => true, 'msg' => 'Not authorized'])); // WRONG
}

// WRONG — auth check inside try/catch; forbiddenPage() calls exit() which try/catch swallows
try {
    if (!User::isAdmin()) { forbiddenPage('...'); } // WRONG PLACEMENT
} catch (\Throwable $th) { ... }
```

```php
// CORRECT — forbiddenPage() handles HTTP status, audit logging, and login redirect atomically
// CORRECT — always BEFORE try/catch
if (!User::isAdmin()) {
    forbiddenPage('Permission denied', true); // CORRECT
}
$response = ['error' => false, 'msg' => ''];
try { ... } catch (\Throwable $th) { ... }
```

### Backward Compatibility
- Do not change public APIs unless explicitly requested.
- Do not break existing plugins.
- Do not drop or rename database columns without a migration.
- Keep all schema changes backward compatible.

## Reference Files

- `objects/mysql_dal.php` — DB layer
- `objects/user.php` — Auth and user model
- `objects/configuration.php` — Site config (`AVideoConf`)
- `objects/functionsSecurity.php` — Input sanitization
- `objects/functions.php` — Global utilities
- `plugin/Plugin.abstract.php` — Plugin base class
- `plugin/AVideoPlugin.php` — Plugin loader and hook dispatcher
- `admin/functions.php` — Admin UI helpers
- `view/js/script.js` — Frontend globals and modal/toast utilities

## Build & Test

```bash
# Run PHPUnit tests
composer test
# or
./vendor/bin/phpunit --configuration phpunit.xml
```

See `.github/workflows/tests.yml` for CI test configuration.
