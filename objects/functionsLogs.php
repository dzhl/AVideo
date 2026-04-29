<?php

function TimeLogStart($name)
{
    global $global;
    if (!empty($global['noDebug'])) {
        return false;
    }
    $time = microtime();
    $time = explode(' ', $time);
    $time = $time[1] + $time[0];
    if (empty($global['start']) || !is_array($global['start'])) {
        $global['start'] = [];
    }
    $global['start'][$name] = $time;
    return $name;
}

function TimeLogEnd($name, $line, $TimeLogLimit = 0.7)
{
    global $global;
    if (!empty($global['noDebug']) || empty($global['start'][$name])) {
        return false;
    }
    if (!empty($global['TimeLogLimit'])) {
        $TimeLogLimit = $global['TimeLogLimit'];
    }
    $time = microtime();
    $time = explode(' ', $time);
    $time = $time[1] + $time[0];
    $finish = $time;
    $total_time = round(($finish - $global['start'][$name]), 4);
    $type = AVideoLog::$PERFORMANCE;
    $backtrace = '';
    $ua = ' IP=' . getRealIpAddr();

    if (empty($global['noDebugSlowProcess']) && $total_time > $TimeLogLimit) {
        if ($total_time > 1) {
            $type = AVideoLog::$WARNING;
        }
        if ($total_time > 2) {
            $type = AVideoLog::$ERROR;
            $backtrace = ' backtrace=' . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20));
        }

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            if (isBot()) {
                $ua .= " BOT ";
                // Strip newlines to prevent log injection (CWE-117); security.php does not cover $_SERVER
                $ua .= " USER_AGENT=" . preg_replace('/[\r\n]+/', ' ', $_SERVER['HTTP_USER_AGENT']);
            }
        } else {
            $ua .= " USER_AGENT=Undefined server=" . json_encode($_SERVER);
        }

        _error_log("Time: " . str_pad(number_format($total_time, 3) . "s", 8) . " | Limit: {$TimeLogLimit}s | Location: {$_SERVER["SCRIPT_FILENAME"]} Line {$line} [{$name}]{$ua}{$backtrace}", $type);
    }
    TimeLogStart($name);
}


class AVideoLog
{

    public static $DEBUG = 0;
    public static $WARNING = 1;
    public static $ERROR = 2;
    public static $SECURITY = 3;
    public static $SOCKET = 4;
    public static $PERFORMANCE = 5;
    public static $MONITORE = 6;
}

function _error_log_debug($message, $show_args = false)
{
    $array = debug_backtrace();
    $message .= PHP_EOL;
    foreach ($array as $value) {
        $message .= "function: {$value['function']} Line: {{$value['line']}} File: {{$value['file']}}" . PHP_EOL;
        if ($show_args) {
            $message .= print_r($value['args'], true) . PHP_EOL;
        }
    }
    _error_log(PHP_EOL . '***' . PHP_EOL . $message . '***');
}

function _error_log_stringify_message($message)
{
    if (!is_string($message)) {
        $originalMessage = $message;
        $message = json_encode($message);
        if (!is_string($message)) {
            $message = print_r($originalMessage, true);
        }
    }
    return $message;
}

function _error_log_build_message($message, $type = 0)
{
    $message = _error_log_stringify_message($message);
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $prefix = "AVideoLog::";
    switch ($type) {
        case AVideoLog::$DEBUG:
            $prefix .= "DEBUG:      ";
            break;
        case AVideoLog::$WARNING:
            $prefix .= "WARNING:    ";
            break;
        case AVideoLog::$ERROR:
            $prefix .= "ERROR:      ";
            break;
        case AVideoLog::$SECURITY:
            $prefix .= "SECURITY:   ";
            break;
        case AVideoLog::$SOCKET:
            $prefix .= "SOCKET:      ";
            break;
        case AVideoLog::$PERFORMANCE:
            $prefix .= "PERFORMANCE: ";
            break;
        case AVideoLog::$MONITORE:
            $prefix .= "MONITORE:    ";
            break;
    }
    return $prefix . $message . " SCRIPT_NAME: {$scriptName}";
}

function _error_log_truncate_message($message)
{
    global $global;

    $message = _error_log_stringify_message($message);

    $maxLength = isset($global['maxLogLineLength']) ? intval($global['maxLogLineLength']) : 8192;
    if ($maxLength <= 0 || strlen($message) <= $maxLength) {
        return $message;
    }

    $suffix = '... [log truncated, original length=' . strlen($message) . ' bytes]';
    $maxMessageLength = $maxLength - strlen($suffix);
    if ($maxMessageLength < 1) {
        return substr($message, 0, $maxLength);
    }

    return substr($message, 0, $maxMessageLength) . $suffix;
}

function _error_log($message, $type = 0, $doNotRepeat = null)
{
    global $global;
    $message = _error_log_stringify_message($message);
    if (!empty($global['printLogs'])) {
        echo _error_log_truncate_message($message) . PHP_EOL;
        return false;
    }

    if ($doNotRepeat === null) {
        // HLS requests can repeat the same error many times per playback session.
        $doNotRepeat = preg_match("/hls.php$/", $_SERVER['SCRIPT_NAME'] ?? '');
    }

    if (isCommandLineInterface() && empty($global['doNotPrintLogs'])) {
        //echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    }
    if (empty($global['noDebug'])) {
        $global['noDebug'] = array();
    }
    if (!empty($global['noDebug']) && ($type == AVideoLog::$DEBUG || $type == AVideoLog::$PERFORMANCE)) {
        if (is_array($global['noDebug'])) {
            if (in_array($type, $global['noDebug'])) {
                return false;
            }
        } else if (($type == AVideoLog::$DEBUG || $type == AVideoLog::$PERFORMANCE)) {
            return false;
        }
    }
    $str = _error_log_build_message($message, $type);
    $logMessage = _error_log_truncate_message($str);
    if ($doNotRepeat) {
        return rateLimitedLog(
            '_error_log_' . md5($str),
            $logMessage
        );
    }
    error_log($logMessage);
}

function rateLimitedLog($key, $message, $ttl = 300, $type = null)
{
    global $global;

    $message = _error_log_stringify_message($message);

    if (!empty($global['printLogs'])) {
        if ($type === null) {
            echo _error_log_truncate_message($message) . PHP_EOL;
        } else {
            echo _error_log_truncate_message(_error_log_build_message($message, $type)) . PHP_EOL;
        }
        return false;
    }

    if (!empty($global['noDebug']) && ($type == AVideoLog::$DEBUG || $type == AVideoLog::$PERFORMANCE)) {
        if (is_array($global['noDebug'])) {
            if (in_array($type, $global['noDebug'])) {
                return false;
            }
        } else if (($type == AVideoLog::$DEBUG || $type == AVideoLog::$PERFORMANCE)) {
            return false;
        }
    }

    if (class_exists('ObjectYPT')) {
        $tmpDir = ObjectYPT::getTmpCacheDir() . 'rateLimitedLogs' . DIRECTORY_SEPARATOR;
    } else {
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'YPTObjectCache' . DIRECTORY_SEPARATOR . 'rateLimitedLogs' . DIRECTORY_SEPARATOR;
    }
    make_path($tmpDir);
    $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $key);
    $rateFile = $tmpDir . 'rate_limited_log_' . $safeKey . '.cache';
    $now = time();

    if (is_file($rateFile)) {
        $lastLogged = intval(@file_get_contents($rateFile));
        if (!empty($lastLogged) && ($lastLogged + $ttl) > $now) {
            return false;
        }
    }

    @_file_put_contents($rateFile, $now);

    if ($type === null) {
        error_log(_error_log_truncate_message($message));
    } else {
        error_log(_error_log_truncate_message(_error_log_build_message($message, $type)));
    }

    return true;
}

function getAVideoLogPermissionCheckFile($logfile)
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'avideo_log_permission_check_' . md5($logfile) . '.cache';
}

function getAVideoWebServerUser()
{
    global $global;

    if (!empty($global['webserverUser'])) {
        return $global['webserverUser'];
    }

    if (!empty($_SERVER['APACHE_RUN_USER'])) {
        return $_SERVER['APACHE_RUN_USER'];
    }

    if (function_exists('posix_getpwnam')) {
        foreach (['www-data', 'apache', 'nginx'] as $user) {
            if (posix_getpwnam($user) !== false) {
                return $user;
            }
        }
    }

    return 'www-data';
}

function canCurrentProcessChown()
{
    return isCommandLineInterface() && function_exists('posix_geteuid') && posix_geteuid() === 0;
}

function ensureAVideoLogWritable($force = false, $ttl = 300)
{
    global $global;

    if (empty($global['logfile'])) {
        return false;
    }

    $logfile = $global['logfile'];
    if (strpos($logfile, '/dev/') === 0 || strpos($logfile, 'php://') === 0) {
        return true;
    }

    $checkFile = getAVideoLogPermissionCheckFile($logfile);
    if (
        empty($force) &&
        !empty($ttl) &&
        file_exists($checkFile) &&
        filemtime($checkFile) >= (time() - intval($ttl))
    ) {
        $lastCheck = json_decode(@file_get_contents($checkFile));
        if (!empty($lastCheck) && !empty($lastCheck->result)) {
            return true;
        }
    }

    $result = true;
    $dir = dirname($logfile);
    if (!is_dir($dir) && function_exists('make_path')) {
        $result = make_path($dir) && $result;
    }

    if (!file_exists($logfile)) {
        $created = @file_put_contents($logfile, '');
        if ($created === false) {
            _error_log("ensureAVideoLogWritable: failed to create log file {$logfile}", AVideoLog::$WARNING);
            $result = false;
        }
    }

    if (file_exists($logfile)) {
        $webServerUser = getAVideoWebServerUser();
        if (canCurrentProcessChown() && function_exists('posix_getpwnam')) {
            $userInfo = posix_getpwnam($webServerUser);
            if (!empty($userInfo) && fileowner($logfile) !== intval($userInfo['uid'])) {
                if (!@chown($logfile, $webServerUser)) {
                    _error_log("ensureAVideoLogWritable: failed to chown {$logfile} to {$webServerUser}", AVideoLog::$WARNING);
                    $result = false;
                }
            }
        }

        if (!@chmod($logfile, 0664)) {
            _error_log("ensureAVideoLogWritable: failed to chmod 0664 {$logfile}", AVideoLog::$WARNING);
            $result = false;
        }

        if (!is_writable($logfile)) {
            _error_log("ensureAVideoLogWritable: log file is not writable by current process {$logfile}", AVideoLog::$WARNING);
            $result = false;
        }
    }

    @file_put_contents($checkFile, json_encode([
        'time' => time(),
        'result' => $result,
        'logfile' => $logfile,
    ]));

    return $result;
}

function isSchedulerRun()
{
    return preg_match('/Scheduler\/run\.php$/', $_SERVER['SCRIPT_NAME']);
}

/**
 * Rotates avideo.log daily (similar to Apache logrotate):
 * - Renames avideo.log to avideo.YYYY-MM-DD.log (yesterday's date)
 * - Creates a fresh empty avideo.log
 * - Deletes rotated logs older than $daysToKeep days
 *
 * Set $global['logRotationDays'] in configuration.php to change retention (default: 30).
 */
function rotateAVideoLog()
{
    global $global;

    // Skip rotation when logging to stdout/stderr (Docker) or log is not a regular file
    if (empty($global['logfile']) || !file_exists($global['logfile']) || !is_file($global['logfile'])) {
        return;
    }

    $logfile = $global['logfile'];
    $daysToKeep = isset($global['logRotationDays']) ? (int) $global['logRotationDays'] : 30;
    if ($daysToKeep < 1) {
        $daysToKeep = 1;
    }

    // Rotate: rename current log to avideo.YYYY-MM-DD.log (yesterday)
    // Keeps the .log extension so web server rules blocking *.log also protect archived files
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $logDir = dirname($logfile);
    $logBasename = basename($logfile, '.log'); // e.g. "avideo"
    $rotatedFile = $logDir . DIRECTORY_SEPARATOR . $logBasename . '.' . $yesterday . '.log';

    // Only rotate if there is content and not already rotated today
    if (filesize($logfile) > 0 && !file_exists($rotatedFile)) {
        if (rename($logfile, $rotatedFile)) {
            // Create a fresh empty log
            file_put_contents($logfile, '');
            ini_set('error_log', $logfile);
            ensureAVideoLogWritable(true);
            _error_log("rotateAVideoLog: rotated to {$rotatedFile}, keeping {$daysToKeep} days");
        } else {
            _error_log("rotateAVideoLog: failed to rotate {$logfile}", AVideoLog::$ERROR);
            return;
        }
    }

    // Delete old rotated logs beyond the retention period
    // Pattern: avideo.YYYY-MM-DD.log
    $cutoffTime = strtotime("-{$daysToKeep} days");

    foreach (glob($logDir . DIRECTORY_SEPARATOR . $logBasename . '.????-??-??.log') ?: [] as $oldFile) {
        if (!is_file($oldFile)) {
            continue;
        }
        // Match files like avideo.YYYY-MM-DD.log
        if (!preg_match('/\.(\d{4}-\d{2}-\d{2})\.log$/', $oldFile, $m)) {
            continue;
        }
        $fileTime = strtotime($m[1]);
        if ($fileTime !== false && $fileTime < $cutoffTime) {
            if (unlink($oldFile)) {
                _error_log("rotateAVideoLog: deleted old log {$oldFile}");
            } else {
                _error_log("rotateAVideoLog: failed to delete {$oldFile}", AVideoLog::$WARNING);
            }
        }
    }
}


function _dieAndLogObject($obj, $prefix = "")
{
    $objString = json_encode($obj);
    _error_log($prefix . $objString);
    die($objString);
}
