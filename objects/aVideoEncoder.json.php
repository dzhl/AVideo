<?php
// Early diagnostic log — fires before configuration.php loads.
// Captures the raw Content-Type header and whether PHP parsed any POST or FILES.
// This is the first thing to check when response_raw is empty: if post_count=0 and
// files_count=0 despite a POST being sent, the Content-Type header is malformed
// (e.g. missing multipart boundary) and PHP silently discarded the entire body.
error_log("aVideoEncoder.json: EARLY content_type=" . ($_SERVER['CONTENT_TYPE'] ?? 'not-set')
    . " method=" . ($_SERVER['REQUEST_METHOD'] ?? 'not-set')
    . " post_count=" . count($_POST)
    . " files_count=" . count($_FILES)
    . " request_count=" . count($_REQUEST));
/*
error_log("avideoencoder REQUEST 1: " . json_encode($_REQUEST));
error_log("avideoencoder POST 1: " . json_encode($_REQUEST));
error_log("avideoencoder GET 1: " . json_encode($_GET));
*/
if (empty($global)) {
    $global = [];
}
$obj = new stdClass();
$obj->error = true;
$obj->lines = array();
$obj->errorMSG = array();

global $global, $config;
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}

inputToRequest();
_error_log("aVideoEncoder.json: after inputToRequest post_keys=[" . implode(',', array_keys($_POST)) . "] files_keys=[" . implode(',', array_keys($_FILES)) . "] request_count=" . count($_REQUEST));
/*
_error_log("REQUEST: " . json_encode($_REQUEST));
_error_log("POST: " . json_encode($_REQUEST));
_error_log("GET: " . json_encode($_GET));
*/
header('Content-Type: application/json');
allowOrigin();

function dieJsonResponse($obj, $context = '')
{
    $json = _json_encode($obj);
    if ($json === false || $json === '') {
        $jsonError = json_last_error_msg();
        $objectDump = print_r($obj, true);
        if (strlen($objectDump) > 4000) {
            $objectDump = substr($objectDump, 0, 4000) . '... [truncated]';
        }
        _error_log("aVideoEncoder.json: JSON encode failed context={$context} error={$jsonError}");
        _error_log("aVideoEncoder.json: JSON encode object dump context={$context} {$objectDump}");

        $fallback = [
            'error' => true,
            'msg' => "json_encode failed: {$jsonError}",
            'context' => $context,
        ];
        $json = json_encode($fallback);

        if ($json === false || $json === '') {
            $json = '{"error":true,"msg":"json_encode failed with unrecoverable error"}';
        }
    }
    die($json);
}

$global['bypassSameDomainCheck'] = 1;
if (empty($_REQUEST)) {
    $obj->msg = ("Your POST data is empty, maybe your video file is too big for the host");
    //$obj->SERVER_ADDR = $_SERVER['SERVER_ADDR'];
    //$obj->dir = __DIR__;
    _error_log($obj->msg);
    dieJsonResponse($obj, 'empty-request');
}
//_error_log("aVideoEncoder.json: start");
_error_log("aVideoEncoder.json: start");
if (empty($global['allowedExtension'])) {
    $global['allowedExtension'] = array();
}
if (empty($_REQUEST['format']) || !in_array($_REQUEST['format'], $global['allowedExtension'])) {
    $obj->msg = "aVideoEncoder.json: ERROR Extension not allowed File {$_REQUEST['format']}";
    _error_log($obj->msg . ": " . json_encode($_REQUEST));
    dieJsonResponse($obj, 'invalid-format-extension');
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $_REQUEST['format'])) {
    $obj->msg = "aVideoEncoder.json: ERROR Invalid format characters: {$_REQUEST['format']}";
    _error_log($obj->msg);
    dieJsonResponse($obj, 'invalid-format-characters');
}

if (!isset($_REQUEST['encodedPass'])) {
    $_REQUEST['encodedPass'] = 1;
}
useVideoHashOrLogin();
_error_log("aVideoEncoder.json: after useVideoHashOrLogin - User::getId()=" . User::getId() . " isLogged=" . (User::isLogged() ? 'true' : 'false') . " videos_id=" . @$_REQUEST['videos_id'] . " video_id_hash=" . @$_REQUEST['video_id_hash']);
if (!User::canUpload()) {
    $obj->msg = __("Permission denied to receive a file") . ': ' . json_encode($_REQUEST);
    _error_log("aVideoEncoder.json: {$obj->msg}  canNotUploadReason=" . json_encode(User::canNotUploadReason()));
    _error_log($obj->msg);
    dieJsonResponse($obj, 'permission-denied-upload');
}

if (!empty($_REQUEST['videos_id'])) {
    if (!Video::canEncoderEdit($_REQUEST['videos_id'])) {
        _error_log("aVideoEncoder.json: Permission denied to edit videos_id=" . intval($_REQUEST['videos_id']) . " isLogged=" . (User::isLogged() ? 'true' : 'false') . " userId=" . User::getId());
        $obj->msg = __("Permission denied to edit a video: ") . json_encode($_REQUEST);
        _error_log($obj->msg);
        dieJsonResponse($obj, 'permission-denied-edit');
    }
}

_error_log("aVideoEncoder.json: start to receive: " . json_encode($_REQUEST));

// check if there is en video id if yes update if is not create a new one
$video = new Video("", "", @$_REQUEST['videos_id'], true);

if (!empty($video->getId()) && !empty($_REQUEST['first_request']) && !empty($_REQUEST['downloadURL'])) {
    $obj->lines[] = __LINE__;
    _error_log("aVideoEncoder.json: There is a new video to replace the existing one, we will delete the current files videos_id = " . $video->getId());
    $video->removeVideoFiles();
}

$obj->lines[] = __LINE__;
$obj->video_id = @$_REQUEST['videos_id'];
$title = $video->getTitle();
$description = $video->getDescription();
if (empty($title) && !empty($_REQUEST['title'])) {
    $obj->lines[] = __LINE__;
    _error_log("aVideoEncoder.json: Title updated {$_REQUEST['title']} ");
    $title = $video->setTitle($_REQUEST['title']);
} elseif (empty($title)) {
    $obj->lines[] = __LINE__;
    $video->setTitle("Automatic Title");
} else {
    $obj->lines[] = __LINE__;
    _error_log("aVideoEncoder.json: Title not updated {$_REQUEST['title']} ");
}

if (empty($description)) {
    $obj->lines[] = __LINE__;
    $video->setDescription($_REQUEST['description']);
}


if (!empty($_REQUEST['duration'])) {
    $obj->lines[] = __LINE__;
    $duration = $video->getDuration();
    if (empty($duration) || $duration === 'EE:EE:EE') {
        $obj->lines[] = __LINE__;
        $video->setDuration($_REQUEST['duration']);
    }
}

$status = $video->setAutoStatus();

$video->setVideoDownloadedLink($_REQUEST['videoDownloadedLink']);
_error_log("aVideoEncoder.json: Encoder receiving post " . json_encode($_REQUEST));
//_error_log(print_r($_REQUEST, true));
if (preg_match("/(mp3|wav|ogg)$/i", $_REQUEST['format'])) {
    $obj->lines[] = __LINE__;
    // Only set type to audio if it is not already set to video
    // This prevents an audio file (e.g. mp3) arriving after an mp4 from overwriting the type
    $currentType = $video->getType();
    if ($currentType !== Video::$videoTypeVideo) {
        $type = Video::$videoTypeAudio;
        $video->setType($type);
        _error_log("aVideoEncoder.json: Setting type to audio for format {$_REQUEST['format']} currentType={$currentType}");
    }else{
        _error_log("aVideoEncoder.json: Keeping type as video for format {$_REQUEST['format']} currentType={$currentType}");
    }
} elseif (preg_match("/(mp4|webm|zip)$/i", $_REQUEST['format'])) {
    $obj->lines[] = __LINE__;
    $type = Video::$videoTypeVideo;
    $video->setType($type);
}

$videoFileName = $video->getFilename();
if (empty($videoFileName)) {
    $obj->lines[] = __LINE__;
    $paths = Video::getNewVideoFilename();
    $filename = $paths['filename'];
    $videoFileName = $video->setFilename($videoFileName);
}

$paths = Video::getPaths($videoFileName, true);
$destination_local = "{$paths['path']}{$videoFileName}";

if (!empty($_FILES)) {
    $obj->lines[] = __LINE__;
    _error_log("aVideoEncoder.json: Files " . json_encode($_FILES));
} else {
    $obj->lines[] = __LINE__;
    _error_log("aVideoEncoder.json: Files EMPTY");
    if (!empty($_REQUEST['downloadURL'])) {
        // Validate resolution before downloading: forbiddenPage() calls die(), so if resolution
        // is checked only after the download the temp file is orphaned permanently on disk.
        if (!empty($_REQUEST['resolution']) && !in_array($_REQUEST['resolution'], $global['avideo_possible_resolutions'])) {
            $msg = "This resolution is not possible {$_REQUEST['resolution']}";
            _error_log($msg);
            forbiddenPage($msg);
        }
        $obj->lines[] = __LINE__;
        $_FILES['video']['tmp_name'] = downloadVideoFromDownloadURL($_REQUEST['downloadURL']);
        if (empty($_FILES['video']['tmp_name'])) {
            $obj->lines[] = __LINE__;
            _error_log("aVideoEncoder.json: ********  Download ERROR " . $_REQUEST['downloadURL']);
        } else {
            $obj->lines[] = __LINE__;
        }
    } else {
        $obj->lines[] = __LINE__;
    }
}

if (!empty($_FILES['video']['error'])) {
    $obj->lines[] = __LINE__;
    $phpFileUploadErrors = [
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
    ];
    _error_log("aVideoEncoder.json: ********  Files ERROR " . $phpFileUploadErrors[$_FILES['video']['error']]);
    if (!empty($_REQUEST['downloadURL'])) {
        $obj->lines[] = __LINE__;
        $_FILES['video']['tmp_name'] = downloadVideoFromDownloadURL($_REQUEST['downloadURL']);
    } else {
        $obj->lines[] = __LINE__;
    }
} else {
    $obj->lines[] = __LINE__;
}
// SECURITY: Validate chunkFile against a strict allowlist of temp directories only.
// The old str_replace('../', '') path-traversal guard was bypassable (e.g. '....//'),
// and isValidURLOrPath() was too broad — it allowed any path under /var/www/, the
// application root, and the videos directory, enabling arbitrary local file read for
// any authenticated uploader. We now use realpath() to canonicalise the path and
// verify it is rooted inside getTmpDir() or sys_get_temp_dir() only.
if (empty($_FILES['video']['tmp_name']) && !empty($_REQUEST['chunkFile'])) {
    $resolvedChunkFile = realpath($_REQUEST['chunkFile']);
    if ($resolvedChunkFile !== false) {
        $allowedChunkDirs = array_filter(array_unique([
            realpath(getTmpDir()),
            realpath(sys_get_temp_dir()),
        ]));
        $chunkAllowed = false;
        foreach ($allowedChunkDirs as $allowedDir) {
            // Use DIRECTORY_SEPARATOR so '/tmp' cannot match '/tmpfoo'
            if (str_starts_with($resolvedChunkFile, $allowedDir . DIRECTORY_SEPARATOR) ||
                $resolvedChunkFile === $allowedDir) {
                $chunkAllowed = true;
                break;
            }
        }
        if ($chunkAllowed) {
            $obj->lines[] = __LINE__;
            $_FILES['video']['tmp_name'] = $resolvedChunkFile;
        } else {
            _error_log("aVideoEncoder.json: chunkFile rejected (outside allowed temp dirs): " . $_REQUEST['chunkFile']);
        }
    } else {
        _error_log("aVideoEncoder.json: chunkFile rejected (realpath failed / file not found): " . $_REQUEST['chunkFile']);
    }
}

if (empty($_FILES['video']['tmp_name'])) {
    if (isMetadataOnlyEncoderRequest()) {
        logIncomingMediaState("aVideoEncoder.json: metadata-only request received without direct media file; the streamer will create or update the video record only");
    } else {
        $obj->errorMSG[] = "No incoming media file was provided";
        logIncomingMediaState("aVideoEncoder.json: missing incoming media file after upload/download/chunk resolution", AVideoLog::$ERROR);
    }
}

// get video file from encoder
if (!empty($_FILES['video']['tmp_name'])) {
    $obj->lines[] = __LINE__;
    $resolution = '';
    if (!empty($_REQUEST['resolution'])) {
        $obj->lines[] = __LINE__;
        if (!in_array($_REQUEST['resolution'], $global['avideo_possible_resolutions'])) {
            $obj->lines[] = __LINE__;
            $msg = "This resolution is not possible {$_REQUEST['resolution']}";
            _error_log($msg);
            forbiddenPage($msg);
        }
        $resolution = "_{$_REQUEST['resolution']}";
    }
    $obj->lines[] = __LINE__;
    $filename = "{$videoFileName}{$resolution}.{$_REQUEST['format']}";

    $fsize = filesize($_FILES['video']['tmp_name']);

    _error_log("aVideoEncoder.json: receiving video upload to {$filename} filesize=" . ($fsize) . " (" . humanFileSize($fsize) . ")" . json_encode($_FILES));
    $destinationFile = decideMoveUploadedToVideos($_FILES['video']['tmp_name'], $filename);
} else {
    $obj->lines[] = __LINE__;
    // set encoding
    $video->setStatus(Video::STATUS_ENCODING);
    //$video->setAutoStatus(Video::STATUS_ACTIVE);
}
if (!empty($_FILES['image']['tmp_name']) && !file_exists("{$destination_local}.jpg")) {
    $obj->lines[] = __LINE__;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], "{$destination_local}.jpg")) {
        $obj->lines[] = __LINE__;
        $obj->msg = print_r(sprintf(__("Could not move image file [%s.jpg]"), $destination_local), true);
        _error_log("aVideoEncoder.json: " . $obj->msg);
        dieJsonResponse($obj, 'move-image-failed');
    }
}
if (!empty($_FILES['gifimage']['tmp_name']) && !file_exists("{$destination_local}.gif")) {
    $obj->lines[] = __LINE__;
    if (!move_uploaded_file($_FILES['gifimage']['tmp_name'], "{$destination_local}.gif")) {
        $obj->lines[] = __LINE__;
        $obj->msg = print_r(sprintf(__("Could not move gif image file [%s.gif]"), $destination_local), true);
        _error_log("aVideoEncoder.json: " . $obj->msg);
        dieJsonResponse($obj, 'move-gif-failed');
    }
}

if (!empty($_REQUEST['encoderURL'])) {
    $obj->lines[] = __LINE__;
    $video->setEncoderURL($_REQUEST['encoderURL']);
}

if (!empty($_REQUEST['categories_id'])) {
    $obj->lines[] = __LINE__;
    $video->setCategories_id($_REQUEST['categories_id']);
}

if(!empty($video->getId())){
    $obj->lines[] = __LINE__;
    _error_log("Editing video ID {$video->getId()} ".$video->getExternalOptions());
}

deduplicateByEncoderQueueId($video, $obj);

$video_id = $video->save();

if (empty($video_id)) {
    $obj->msg = __("Your video has NOT been saved!") . ' ' . $global['lastBeforeSaveVideoMessage'];
    _error_log("aVideoEncoder.json: " . $obj->msg);
    dieJsonResponse($obj, 'video-save-failed');
}

$video->updateDurationIfNeed();
$video->updateHLSDurationIfNeed();

if (!empty($_REQUEST['usergroups_id'])) {
    $obj->lines[] = __LINE__;
    if (!is_array($_REQUEST['usergroups_id'])) {
        $obj->lines[] = __LINE__;
        $_REQUEST['usergroups_id'] = [$_REQUEST['usergroups_id']];
    }
    UserGroups::updateVideoGroups($video_id, $_REQUEST['usergroups_id']);
}

$obj->error = false;
$obj->video_id = $video_id;

$v = new Video('', '', $video_id, true);
$obj->video_id_hash = $v->getVideoIdHash();
$obj->releaseDate = @$_REQUEST['releaseDate'];
$obj->releaseTime = @$_REQUEST['releaseTime'];
$obj->lines[] = __LINE__;

_error_log("aVideoEncoder.json: Files Received for video {$video_id}: " . $video->getTitle());
if (!empty($destinationFile)) {
    $obj->lines[] = __LINE__;
    if (file_exists($destinationFile)) {
        $obj->lines[] = __LINE__;
        _error_log("aVideoEncoder.json: Success $destinationFile ");
    } else {
        $obj->lines[] = __LINE__;
        _error_log("aVideoEncoder.json: ERROR $destinationFile ");
    }
}
dieJsonResponse($obj, 'success');

/*
  _error_log(print_r($_REQUEST, true));
  _error_log(print_r($_FILES, true));
  var_dump($_REQUEST, $_FILES);
 */

function downloadVideoFromDownloadURL($downloadURL)
{
    global $global, $obj;
    $downloadURL = trim($downloadURL);

    // Validate that the URL's file extension is on the server's allowed-extension list.
    // basename($downloadURL) is used later as the temp filename, so an unvalidated extension
    // (e.g. .php) would be written to the web-accessible cache directory.
    $urlExtension = strtolower(pathinfo(parse_url($downloadURL, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (!in_array($urlExtension, $global['allowedExtension'])) {
        __errlog("aVideoEncoder.json:downloadVideoFromDownloadURL extension not allowed: " . $urlExtension);
        return false;
    }

    // SSRF check is unconditional — no extension-based bypass.
    //
    // Historical note: a previous version skipped isSSRFSafeURL() for common media/archive
    // extensions (mp4, mp3, jpg, gif, zip …) to accommodate the local encoder sending files
    // via loopback. That bypass was NOT necessary: Encoder.php always builds downloadURL as
    //   "{$global['webSiteRootURL']}{$dfile}"
    // so its host always equals the site's own domain, and isSSRFSafeURL() returns true via
    // the same-domain short-circuit regardless of extension.
    //
    // Keeping an extension bypass would allow any authenticated uploader to reach loopback
    // or internal services (e.g. http://127.0.0.1:9998/probe.mp4) and exfiltrate the
    // response body as publicly retrievable media — a confirmed SSRF exfiltration primitive.
    if (!isSSRFSafeURL($downloadURL)) {
        __errlog("aVideoEncoder.json:downloadVideoFromDownloadURL SSRF protection blocked URL: " . $downloadURL);
        return false;
    }

    // Allow up to 2 hours for large video uploads/encoding
    @set_time_limit(7200);
    ini_set('max_execution_time', 7200);

    _error_log("aVideoEncoder.json: Try to download " . $downloadURL);
    $file = url_get_contents($downloadURL);
    $strlen = strlen($file);
    $minLen = 20000;
    if (preg_match('/\.mp3$/', $downloadURL)) {
        $minLen = 5000;
    }
    if ($strlen < $minLen) {
        __errlog("aVideoEncoder.json:downloadVideoFromDownloadURL this is not a video " . $downloadURL . " strlen={$strlen} " . humanFileSize($strlen));
        //it is not a video
        return false;
    }
    _error_log("aVideoEncoder.json:downloadVideoFromDownloadURL Got the download " . $downloadURL . ' ' . humanFileSize($strlen));
    if ($file) {
        $_FILES['video']['name'] = basename($downloadURL);
        //$temp = getTmpDir('zip') . $_FILES['video']['name'];
        $temp = Video::getStoragePath() . "cache/tmpFile/" . $_FILES['video']['name'];
        make_path($temp);
        $bytesSaved = file_put_contents($temp, $file);

        if ($bytesSaved) {
            _error_log("aVideoEncoder.json:downloadVideoFromDownloadURL saved " . $temp  . ' ' . humanFileSize($bytesSaved));
            return $temp;
        } else {
            $dir = dirname($temp);
            if (!is_writable($dir)) {
                __errlog("aVideoEncoder.json:downloadVideoFromDownloadURL ERROR on save file " . $temp . ". Directory is not writable. To make the directory writable and set www-data as owner, use the following commands: sudo chmod -R 775 " . $dir . " && sudo chown -R www-data:www-data " . $dir);
            } else {
                __errlog("aVideoEncoder.json:downloadVideoFromDownloadURL ERROR on save file " . $temp . ". Directory is writable, but the file could not be saved. Possible causes could be disk space issues, file permission issues, or file system errors.");
            }
        }
    }
    return false;
}

function __errlog($txt)
{
    global $global, $obj;
    $obj->errorMSG[] = $txt;
    _error_log($txt, AVideoLog::$ERROR);
}

function getIncomingMediaStateForLog()
{
    return [
        'format' => @$_REQUEST['format'],
        'first_request' => !empty($_REQUEST['first_request']),
        'videos_id' => intval(@$_REQUEST['videos_id']),
        'video_id_hash_present' => !empty($_REQUEST['video_id_hash']),
        'encoderURL' => @$_REQUEST['encoderURL'],
        'downloadURL' => empty($_REQUEST['downloadURL']) ? '' : $_REQUEST['downloadURL'],
        'chunkFile' => empty($_REQUEST['chunkFile']) ? '' : $_REQUEST['chunkFile'],
        'video_tmp_name' => empty($_FILES['video']['tmp_name']) ? '' : $_FILES['video']['tmp_name'],
        'video_error' => isset($_FILES['video']['error']) ? $_FILES['video']['error'] : null,
        'received_files' => array_keys($_FILES),
    ];
}

function isMetadataOnlyEncoderRequest()
{
    return empty($_FILES['video']['tmp_name']) &&
        empty($_REQUEST['downloadURL']) &&
        empty($_REQUEST['chunkFile']) &&
        !empty($_REQUEST['first_request']);
}

/**
 * Deduplication guard using the encoder queue ID.
 *
 * The encoder sends its unique queue row ID (encoder_queue_id) on every
 * first_request=1 call.  We store it inside externalOptions the first time
 * a video is created for that job, so any concurrent or retried call for the
 * same job finds the already-created record instead of inserting a duplicate.
 *
 * - If a video already exists for this encoder_queue_id  → respond immediately
 *   with that video_id and die (no duplicate INSERT).
 * - If no video exists yet                               → stamp encoder_queue_id
 *   onto the Video object's externalOptions before save() so the next
 *   concurrent request will find it.
 *
 * @param Video    $video  The Video object about to be saved.
 * @param stdClass $obj    The response object passed to dieJsonResponse().
 */
function deduplicateByEncoderQueueId(Video &$video, stdClass &$obj)
{
    global $global;

    if (!isMetadataOnlyEncoderRequest()) {
        _error_log("aVideoEncoder.json: deduplicateByEncoderQueueId — skipped: not a metadata-only request (file/downloadURL/chunkFile present or first_request missing)");
        return;
    }

    if (empty($_REQUEST['encoder_queue_id'])) {
        _error_log("aVideoEncoder.json: deduplicateByEncoderQueueId — skipped: encoder_queue_id not present in request");
        return;
    }

    $encoder_queue_id = intval($_REQUEST['encoder_queue_id']);
    if ($encoder_queue_id <= 0) {
        _error_log("aVideoEncoder.json: deduplicateByEncoderQueueId — skipped: encoder_queue_id={$_REQUEST['encoder_queue_id']} is not a positive integer");
        return;
    }

    // Look for a video that was already created for this encoder queue job.
    $dedup_sql = "SELECT id FROM videos"
        . " WHERE JSON_EXTRACT(externalOptions, '$.encoder_queue_id') = {$encoder_queue_id}"
        . " LIMIT 1";
    $dedup_res = $global['mysqli']->query($dedup_sql);
    if ($dedup_res && ($dedup_row = $dedup_res->fetch_assoc())) {
        $existing_id = intval($dedup_row['id']);
        _error_log("aVideoEncoder.json: deduplicateByEncoderQueueId — returning existing video_id={$existing_id} for encoder_queue_id={$encoder_queue_id}; skipping duplicate INSERT");
        $obj->error         = false;
        $obj->video_id      = $existing_id;
        $v                  = new Video('', '', $existing_id, true);
        $obj->video_id_hash = $v->getVideoIdHash();
        $obj->releaseDate   = @$_REQUEST['releaseDate'];
        $obj->releaseTime   = @$_REQUEST['releaseTime'];
        $obj->lines[]       = __LINE__;
        dieJsonResponse($obj, 'deduplicated');
    }

    // No existing video yet — embed encoder_queue_id in externalOptions
    // before the INSERT so the next concurrent request finds it.
    if (empty($video->getId())) {
        $extOpts = _json_decode($video->getExternalOptions());
        if (!is_object($extOpts)) {
            $extOpts = new stdClass();
        }
        $extOpts->encoder_queue_id = $encoder_queue_id;
        $video->setExternalOptions(_json_encode($extOpts));
    }
}

function logIncomingMediaState($message, $type = 0)
{
    _error_log($message . ' ' . json_encode(getIncomingMediaStateForLog()), $type);
}
