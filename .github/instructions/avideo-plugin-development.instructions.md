---
name: "AVideo Plugin Development"
description: "Use when creating, editing, reviewing, or debugging AVideo plugins. Covers plugin structure, PluginAbstract hooks, install/update/uninstall patterns, plugin settings, DB migrations, UI integration, and plugin compatibility rules."
applyTo: "plugin/**/*.php"
---

# AVideo Plugin Development Guidelines

## Plugin Architecture Overview

AVideo uses ~130+ plugins. Every plugin:
- Lives in `plugin/PluginName/` (PascalCase directory name)
- Has a main class `plugin/PluginName/PluginName.php` that extends `PluginAbstract`
- Is loaded via `AVideoPlugin::loadPluginIfEnabled($name)` or `AVideoPlugin::loadPlugin($name)`
- Registers behavior through hook methods (not event listeners)

**Before creating a new plugin**, search the existing `plugin/` directory — the needed functionality may already exist.

---

## Plugin Directory Structure

```
plugin/PluginName/
  ├── PluginName.php            # Main plugin class (REQUIRED)
  ├── version.json              # Version metadata
  ├── index.php                 # Admin settings page (if needed)
  ├── pluginMenu.html           # Admin left-menu entry (if needed)
  ├── Objects/                  # Plugin-specific data classes
  │   └── PluginNameTable.php
  ├── install/                  # Database migration SQL files
  │   ├── updateV1.0.sql
  │   └── updateV2.0.sql
  ├── page/                     # Frontend page files
  ├── view/                     # JS/templates used by frontend
  └── img/                      # Plugin images/icons
```

---

## Main Plugin Class

```php
<?php
class PluginName extends PluginAbstract {

    public function getUUID() {
        return 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'; // unique UUID v4
    }

    public function getName() {
        return 'PluginName';
    }

    public function getDescription() {
        return 'Short description of what this plugin does.';
    }

    public function getVersion() {
        return '1.0';
    }

    // Override only the hooks you need:

    public function getHeadCode() {
        // Return HTML to inject inside <head>
        return '';
    }

    public function getFooterCode() {
        // Return HTML to inject before </body>
        return '';
    }

    public function getHTMLBody() {
        // Return HTML to inject in page body
        return '';
    }

    public function getHTMLMenuLeft() {
        // Return HTML for left navigation menu
        return '';
    }
}
```

**Required methods** (must implement):
- `getUUID()` — globally unique plugin identifier (UUID v4 string)
- `getName()` — plugin name (must match directory and class name)
- `getDescription()` — short description

**Do not** override methods you do not use. Empty overrides waste context.

---

## Available Hook Methods

Override only what the plugin needs. All return `''` (empty string) by default in `PluginAbstract`:

| Method | Injected Into | Notes |
|---|---|---|
| `getHeadCode()` | `<head>` | CSS, meta tags, early JS |
| `getFooterCode()` | Before `</body>` | JS initialization, late scripts |
| `getHTMLBody()` | Page body | Modal markup, hidden elements |
| `getHTMLMenuLeft()` | Left sidebar | Navigation entries |
| `getHTMLMenuRight()` | Right sidebar | Navigation entries |
| `getChartContent()` | Analytics page | Chart.js charts for plugin data |
| `getPluginMenu()` | Admin plugin page | Admin settings link |
| `getHelp()` | Help modal | Plugin documentation |
| `updateScript()` | Plugin enable/upgrade | Run DB migrations and version updates |

Hook aggregation is handled by `AVideoPlugin` static methods:
- `AVideoPlugin::getHeadCode()` — calls all enabled plugins' `getHeadCode()`
- `AVideoPlugin::getFooterCode()` — calls all enabled plugins' `getFooterCode()`
- Do not call individual plugin hooks from outside plugin code

---

## Plugin Settings (Admin UI)

Store plugin settings in the `plugins` table `object_data` JSON column.

Use `admin/functions.php` helpers to render settings forms:

```php
// In index.php (plugin admin page)
$obj = $pluginObject->getObjectData(); // returns decoded JSON object

// Render form using admin helper
echo createTable($obj, 'PluginName');
```

Define fields in the plugin's `object_data` as a JSON structure:
```json
{
  "apiKey": {
    "type": "text",
    "value": "",
    "label": "API Key"
  },
  "isEnabled": {
    "type": "checkbox",
    "value": "1",
    "label": "Enable Feature"
  }
}
```

Access settings in plugin code:
```php
$data = $this->getObjectData();
$apiKey = $data->apiKey->value ?? '';
```

---

## Database Migrations

All plugin schema changes go in versioned SQL files:

```
plugin/PluginName/install/updateV1.0.sql
plugin/PluginName/install/updateV2.0.sql
```

Migration file conventions:
```sql
-- plugin/PluginName/install/updateV2.0.sql
CREATE TABLE IF NOT EXISTS `PluginName_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `users_id` INT(11) NOT NULL,
  `videos_id` INT(11) NOT NULL,
  `created` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_users_id` (`users_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Migrations are run automatically when the plugin is enabled/updated via `updateScript()` in `PluginAbstract`.

**Rules:**
- Never modify existing migration files — add a new versioned file instead
- Never drop or rename columns without ensuring backward compatibility
- Use `IF NOT EXISTS` / `IF EXISTS` guards
- Add only justified indexes — document the reason
- Do not guess table names from other plugins — check their schema files first

---

## Plugin Data Classes (Objects/)

For plugins with complex data, create classes in `plugin/PluginName/Objects/`:

```php
<?php
// plugin/PluginName/Objects/PluginNameItem.php
class PluginNameItem {

    public static function getByUserId($userId) {
        global $global;
        $userId = intval($userId);
        $sql = "SELECT * FROM PluginName_items WHERE users_id = ?";
        $res = sqlDAL::readSql($sql, 'i', [$userId]);
        $rows = [];
        while ($row = sqlDAL::fetchAssoc($res)) {
            $rows[] = $row;
        }
        sqlDAL::close($res);
        return $rows;
    }

    public static function save($userId, $videosId) {
        $sql = "INSERT INTO PluginName_items (users_id, videos_id) VALUES (?, ?)";
        sqlDAL::writeSql($sql, 'ii', [$userId, $videosId]);
    }
}
```

---

## Plugin AJAX Endpoints

AJAX endpoints inside a plugin follow the same pattern as core AJAX:

```
plugin/PluginName/objects/pluginNameAction.json.php
```

```php
<?php
require_once '../../../videos/configuration.php';
header('Content-Type: application/json');

// Auth checks BEFORE the try/catch — forbiddenPage() sets HTTP 403 and exits
// Do NOT use die(json_encode([...])) — it bypasses HTTP status codes, logging, and login redirect
if (!User::isLogged()) {
    forbiddenPage('Login required');
}

$response = ['error' => false, 'msg' => ''];

try {
    // ...
    $response['msg'] = 'Done';
} catch (\Throwable $th) {
    $response['error'] = true;
    $response['msg'] = $th->getMessage();
    _error_log($th->getMessage());
}

echo json_encode($response);
```

---

## Loading Other Plugins

To interact with another plugin, use `AVideoPlugin::loadPluginIfEnabled()`:

```php
$subscription = AVideoPlugin::loadPluginIfEnabled('Subscription');
if (!empty($subscription)) {
    // use $subscription->someMethod()
}
```

Do not instantiate other plugin classes directly — always go through `AVideoPlugin`.

---

## Plugin Priority & Order

Plugin loading order respects the priority system in `cmpPlugin()` (`objects/functions.php`).
High-priority plugins (e.g., `SecureVideosDirectory`, `Subscription`, `PayPerView`) load before others.
`PlayerSkins` loads last.

Do not hardcode plugin load order — rely on the existing priority system.

---

## Do

- Use a UUID v4 for `getUUID()`
- Keep all plugin logic inside `plugin/PluginName/`
- Use `sqlDAL` for all DB access inside plugins
- Use `createTable()` / `jsonToFormElements()` for admin settings forms
- Create versioned SQL migration files for all schema changes
- Check if another plugin already provides needed functionality before creating new hooks
- Use `AVideoPlugin::loadPluginIfEnabled()` to safely reference other plugins

## Do Not

- Modify `plugin/Plugin.abstract.php` or `plugin/AVideoPlugin.php` without explicit request
- Access another plugin's DB tables directly — use that plugin's public API
- Register hooks in more than one plugin for the same concern
- Drop or rename DB columns without a guarded migration
- Hardcode site URLs or system paths — use `$global['webSiteRootURL']`
- Store sensitive data (passwords, tokens) in plaintext in `object_data`
- Create plugin files outside `plugin/PluginName/`
