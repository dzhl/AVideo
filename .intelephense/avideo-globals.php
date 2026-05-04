<?php

/**
 * Editor-only type hints for AVideo globals.
 *
 * This file is indexed by Intelephense through .vscode/settings.json
 * (intelephense.environment.includePaths → ".intelephense"), but it is
 * NOT loaded by the application at runtime.
 *
 * All variables declared here are PHP global-scope variables populated
 * by videos/configuration.php and the bootstrap flow before any plugin
 * or view file is included.
 *
 * @var array<string, mixed> $global        Site config array (systemRootPath, webSiteRootURL, …)
 * @var AVideoConf            $config        Site configuration object
 * @var array<string, mixed>  $video         Current video row (assoc array from DB)
 * @var stdClass              $advancedCustom        CustomizeAdvanced plugin settings
 * @var stdClass              $advancedCustomUser    Per-user CustomizeAdvanced settings
 * @var stdClass              $avideoLayout          Layout plugin settings
 * @var stdClass              $avideoPlayerSkins     PlayerSkins plugin settings
 * @var stdClass              $avideoCustomize       Customize plugin settings
 * @var stdClass              $customizePlugin       Generic customize plugin object
 * @var stdClass              $permissionsPlugin     Permissions plugin object
 */

// Declare each variable at global scope so Intelephense resolves them in
// both file-scope (top-level require_once) and function-scope (global $x).

/** @var array<string, mixed> $global */
$global = [];

/** @var AVideoConf $config */
$config = new AVideoConf();

/** @var array<string, mixed> $video */
$video = [];

/** @var stdClass $advancedCustom */
$advancedCustom = new stdClass();

/** @var stdClass $advancedCustomUser */
$advancedCustomUser = new stdClass();

/** @var stdClass $avideoLayout */
$avideoLayout = new stdClass();

/** @var stdClass $avideoPlayerSkins */
$avideoPlayerSkins = new stdClass();

/** @var stdClass $avideoCustomize */
$avideoCustomize = new stdClass();

/** @var stdClass $customizePlugin */
$customizePlugin = new stdClass();

/** @var stdClass $permissionsPlugin */
$permissionsPlugin = new stdClass();
