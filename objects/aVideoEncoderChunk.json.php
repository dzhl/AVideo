<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Security: clean up orphaned chunk files older than 4 hours.
// 4 hours instead of 1 hour because a multi-chunk upload of a large file
// (e.g. 12 GB) can legitimately span several hours on a slow link.
$tmpDir = sys_get_temp_dir();
foreach (glob($tmpDir . DIRECTORY_SEPARATOR . 'YTPChunk_*') as $staleFile) {
    if (is_file($staleFile) && filemtime($staleFile) < time() - 14400) {
        @unlink($staleFile);
    }
}

// Security: enforce a per-request size cap (mirrors PHP's post_max_size; falls back to 4 GB).
// For multi-chunk uploads each request is at most 500 MB, so this limit applies per chunk.
function _parseIniSize(string $val): int
{
    $val  = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $num  = (int) $val;
    switch ($last) {
        case 'g': $num *= 1024;
        // fall through
        case 'm': $num *= 1024;
        // fall through
        case 'k': $num *= 1024;
    }
    return $num;
}
$rawLimit  = ini_get('post_max_size');
$floorBytes = 4 * 1024 * 1024 * 1024; // 4 GB floor
$maxBytes  = $rawLimit ? max(_parseIniSize($rawLimit), $floorBytes) : $floorBytes;

// Reject obviously oversized requests using the Content-Length hint.
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > $maxBytes) {
    http_response_code(413);
    error_log("aVideoEncoderChunk.json.php: rejected oversized request ({$contentLength} bytes)");
    die(json_encode(['error' => true, 'msg' => 'Payload too large']));
}

// -----------------------------------------------------------------------
// Multi-chunk assembly mode
//
// The encoder splits large files into 500 MB PUT requests and passes:
//   ?file_id=<16 hex chars>   — unique per upload session
//   &chunk=<0-based index>    — which piece this is
//   &total=<total pieces>     — how many pieces in total
//
// chunk=0 creates/truncates the destination file.
// chunk>0 appends to it.
// After the last chunk the response includes complete=true so the encoder
// knows the assembled file is ready to be registered via sendFile().
// -----------------------------------------------------------------------
$fileId = isset($_GET['file_id']) ? $_GET['file_id'] : '';
if (!empty($fileId)) {
    // Validate file_id to prevent path traversal (only hex chars allowed).
    if (!preg_match('/^[0-9a-f]{1,64}$/i', $fileId)) {
        http_response_code(400);
        error_log("aVideoEncoderChunk.json.php: invalid file_id rejected");
        die(json_encode(['error' => true, 'msg' => 'Invalid file_id']));
    }

    $chunkIndex  = isset($_GET['chunk']) ? (int) $_GET['chunk'] : 0;
    $totalChunks = isset($_GET['total']) ? max(1, (int) $_GET['total']) : 1;
    $destFile    = $tmpDir . DIRECTORY_SEPARATOR . 'YTPChunk_' . $fileId;

    // chunk 0 → create/truncate; subsequent chunks → append.
    $mode    = ($chunkIndex === 0) ? 'w' : 'a';
    $putdata = fopen('php://input', 'r');
    $fp      = fopen($destFile, $mode);

    $written = 0;
    while (($data = fread($putdata, 1024 * 1024)) !== false && $data !== '') {
        $written += strlen($data);
        if ($written > $maxBytes) {
            fclose($fp);
            fclose($putdata);
            http_response_code(413);
            error_log("aVideoEncoderChunk.json.php: stream exceeded limit at {$written} bytes, aborting");
            die(json_encode(['error' => true, 'msg' => 'Payload too large']));
        }
        fwrite($fp, $data);
    }
    fclose($fp);
    fclose($putdata);

    $obj           = new stdClass();
    $obj->file     = $destFile;
    $obj->filesize = filesize($destFile);
    $obj->chunk    = $chunkIndex;
    $obj->total    = $totalChunks;
    $obj->complete = ($chunkIndex + 1 >= $totalChunks);

    error_log("aVideoEncoderChunk.json.php: chunk " . ($chunkIndex + 1) . "/{$totalChunks} written={$written} total_so_far={$obj->filesize} file={$destFile} complete=" . ($obj->complete ? 'yes' : 'no'));
    die(json_encode($obj));
}

// -----------------------------------------------------------------------
// Legacy single-PUT mode (backward compatibility for older encoder builds)
// -----------------------------------------------------------------------
$obj       = new stdClass();
$obj->file = tempnam(sys_get_temp_dir(), 'YTPChunk_');

$putdata = fopen("php://input", "r");
$fp      = fopen($obj->file, "w");

error_log("aVideoEncoderChunk.json.php: start {$obj->file} ");

$written = 0;
while ($data = fread($putdata, 1024 * 1024)) {
    $written += strlen($data);
    if ($written > $maxBytes) {
        fclose($fp);
        fclose($putdata);
        @unlink($obj->file);
        http_response_code(413);
        error_log("aVideoEncoderChunk.json.php: stream exceeded limit at {$written} bytes, aborting");
        die(json_encode(['error' => true, 'msg' => 'Payload too large']));
    }
    fwrite($fp, $data);
}

fclose($fp);
fclose($putdata);
sleep(1);
$obj->filesize = filesize($obj->file);

$json = json_encode($obj);

error_log("aVideoEncoderChunk.json.php: {$json} ");

die($json);


// Security: enforce a per-request size cap (mirrors PHP's post_max_size; falls back to 4 GB).
// Legitimate encoder uploads of individual video files fit within this bound.
function _parseIniSize(string $val): int
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $num = (int) $val;
    switch ($last) {
        case 'g': $num *= 1024;
        // fall through
        case 'm': $num *= 1024;
        // fall through
        case 'k': $num *= 1024;
    }
    return $num;
}
$rawLimit = ini_get('post_max_size');
$floorBytes = 4 * 1024 * 1024 * 1024; // 4 GB floor — encoder uploads can be large
$maxBytes = $rawLimit ? max(_parseIniSize($rawLimit), $floorBytes) : $floorBytes;

// Reject obviously oversized requests using the Content-Length hint.
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > $maxBytes) {
    http_response_code(413);
    error_log("aVideoEncoderChunk.json.php: rejected oversized request ({$contentLength} bytes)");
    die(json_encode(['error' => true, 'msg' => 'Payload too large']));
}

$obj = new stdClass();
$obj->file = tempnam(sys_get_temp_dir(), 'YTPChunk_');

$putdata = fopen("php://input", "r");
$fp = fopen($obj->file, "w");

error_log("aVideoEncoderChunk.json.php: start {$obj->file} ");

$written = 0;
while ($data = fread($putdata, 1024 * 1024)) {
    $written += strlen($data);
    if ($written > $maxBytes) {
        fclose($fp);
        fclose($putdata);
        @unlink($obj->file);
        http_response_code(413);
        error_log("aVideoEncoderChunk.json.php: stream exceeded limit at {$written} bytes, aborting");
        die(json_encode(['error' => true, 'msg' => 'Payload too large']));
    }
    fwrite($fp, $data);
}

fclose($fp);
fclose($putdata);
sleep(1);
$obj->filesize = filesize($obj->file);

$json = json_encode($obj);

error_log("aVideoEncoderChunk.json.php: {$json} ");

die($json);
