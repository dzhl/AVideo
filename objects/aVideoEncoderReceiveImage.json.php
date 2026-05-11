<?php
header('Content-Type: application/json');
$obj = new stdClass();
$obj->error = true;

global $global, $config;
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}
allowOrigin();

function receiveImageDebugLog($message)
{
    _error_log($message);
}

$global['bypassSameDomainCheck'] = 1;
inputToRequest();

if (empty($_REQUEST)) {
    $obj->msg = ("Your REQUEST data is empty, maybe your video file is too big for the host");
    receiveImageDebugLog("ReceiveImage: " . $obj->msg);
    die(json_encode($obj));
}

useVideoHashOrLogin();
if (!User::canUpload()) {
    $obj->msg = __("Permission denied to receive a image: " . json_encode($_REQUEST));
    receiveImageDebugLog("ReceiveImage: " . $obj->msg);
    die(json_encode($obj));
}

if (!Video::canEdit($_REQUEST['videos_id'])) {
    $obj->msg = __("Permission denied to edit a video: " . json_encode($_REQUEST));
    receiveImageDebugLog("ReceiveImage: " . $obj->msg);
    die(json_encode($obj));
}

$securityChecks = array(
    'downloadURL_gifimage',
    'downloadURL_webpimage',
    'downloadURL_image',
    'downloadURL_spectrumimage',
);

foreach ($securityChecks as $key => $value) {
    if (!empty($_REQUEST[$value])) {
        // Block directory traversal in URL paths AND query strings.
        // The previous check only inspected parse_url(..., PHP_URL_PATH), so a payload
        // like http://host/x?a=/videos/../../etc/passwd bypassed it entirely because
        // the '..' appears only in the query string component, not the path.
        // Decode the full URL string (handles %2e%2e and similar encodings) and reject
        // any URL that contains '..' anywhere.
        $decodedFull = urldecode((string)$_REQUEST[$value]);
        if (strpos($decodedFull, '..') !== false) {
            unset($_REQUEST[$value]);
        }
    }
}

// check if there is en video id if yes update if is not create a new one
$video = new Video("", "", $_REQUEST['videos_id'], true);
$obj->video_id = $_REQUEST['videos_id'];

$videoFileName = $video->getFilename();
$paths = Video::getPaths($videoFileName, true);
$destination_local = "{$paths['path']}{$videoFileName}";

make_path($destination_local);

$obj->jpgDest = "{$destination_local}.jpg";
$_jpgExistedBefore = file_exists($obj->jpgDest) && fileIsAnValidImage($obj->jpgDest);
if (!file_exists($obj->jpgDest) || !fileIsAnValidImage($obj->jpgDest)) {

    $resolvedIP_image = null;
    if (isValidURL($_REQUEST['downloadURL_image']) && isSSRFSafeURL($_REQUEST['downloadURL_image'], $resolvedIP_image)) {
        $content = ssrfPinnedFetch($_REQUEST['downloadURL_image'], $resolvedIP_image);
        $obj->jpgDestSize = _file_put_contents($obj->jpgDest, $content);
        _error_log("ReceiveImage: download {$_REQUEST['downloadURL_image']} to {$obj->jpgDest} " . humanFileSize($obj->jpgDestSize));
    } elseif (!empty($_FILES['image']['tmp_name']) && (!empty($_REQUEST['update_video_id']) || !fileIsAnValidImage($obj->jpgDest))) {
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $obj->jpgDest)) {
            if (!rename($_FILES['image']['tmp_name'], $obj->jpgDest)) {
                if (!copy($_FILES['image']['tmp_name'], $obj->jpgDest)) {
                    if (!file_exists($_FILES['image']['tmp_name'])) {
                        $obj->msg = print_r(sprintf(__("Could not move image file because it does not exits %s => [%s]"), $_FILES['image']['tmp_name'], $obj->jpgDest), true);
                    } else {
                        $obj->msg = print_r(sprintf(__("Could not move image file %s => [%s]"), $_FILES['image']['tmp_name'], $obj->jpgDest), true);
                    }
                    receiveImageDebugLog("ReceiveImage: " . $obj->msg);
                    die(json_encode($obj));
                }
            }
        } else {
            $obj->jpgDestSize = humanFileSize(filesize($obj->jpgDest));
        }
    } else {
        if (empty($_FILES['image']['tmp_name'])) {
            receiveImageDebugLog("ReceiveImage: empty \$_FILES['image']['tmp_name'] for {$obj->jpgDest}");
        }
        if (file_exists($obj->jpgDest)) {
            receiveImageDebugLog("ReceiveImage: File already exists " . $obj->jpgDest);
            if (fileIsAnValidImage($obj->jpgDest)) {
                receiveImageDebugLog("ReceiveImage: file is valid image " . filesize($obj->jpgDest));
            }
        }
    }
}

// If the .jpg was just written (didn't exist before), delete stale derived thumbnails
// so they regenerate from the new .jpg image
if (!$_jpgExistedBefore && file_exists($obj->jpgDest) && fileIsAnValidImage($obj->jpgDest)) {
    _error_log("ReceiveImage: new .jpg written, deleting derived thumbnails for {$videoFileName}");
    Video::deleteThumbs($videoFileName);
}

$resolvedIP_spectrum = null;
if (!empty($_REQUEST['downloadURL_spectrumimage']) && isSSRFSafeURL($_REQUEST['downloadURL_spectrumimage'], $resolvedIP_spectrum)) {
    $content = ssrfPinnedFetch($_REQUEST['downloadURL_spectrumimage'], $resolvedIP_spectrum);
    $obj->jpgSpectrumDestSize = _file_put_contents($obj->jpgSpectrumDest, $content);
    _error_log("ReceiveImage: download {$_REQUEST['downloadURL_spectrumimage']} {$obj->jpgDestSize}");
} elseif (!empty($_FILES['spectrumimage']['tmp_name'])) {
    $obj->jpgSpectrumDest = "{$destination_local}_spectrum.jpg";
    if ((!empty($_REQUEST['update_video_id']) || !fileIsAnValidImage($obj->jpgSpectrumDest))) {
        if (!move_uploaded_file($_FILES['spectrumimage']['tmp_name'], $obj->jpgSpectrumDest)) {
            $obj->msg = print_r(sprintf(__("Could not move image file [%s.jpg]"), $destination_local), true);
            receiveImageDebugLog("ReceiveImage: " . $obj->msg);
            die(json_encode($obj));
        } else {
            $obj->jpgSpectrumDestSize = humanFileSize(filesize($obj->jpgSpectrumDest));
        }
    } else {
        if (empty($_FILES['spectrumimage']['tmp_name'])) {
            receiveImageDebugLog("ReceiveImage: empty \$_FILES['spectrumimage']['tmp_name'] for {$destination_local}");
        }
        if (file_exists($obj->jpgSpectrumDest)) {
            receiveImageDebugLog("ReceiveImage: File already exists " . $obj->jpgSpectrumDest);
            if (fileIsAnValidImage($obj->jpgSpectrumDestSize)) {
                receiveImageDebugLog("ReceiveImage: file is valid image " . filesize($obj->jpgSpectrumDest));
            }
        }
    }
}

$obj->gifDest = "{$destination_local}.gif";
$resolvedIP_gif = null;
if (!empty($_REQUEST['downloadURL_gifimage']) && isSSRFSafeURL($_REQUEST['downloadURL_gifimage'], $resolvedIP_gif)) {
    $content = ssrfPinnedFetch($_REQUEST['downloadURL_gifimage'], $resolvedIP_gif);
    $obj->gifDestSize = file_put_contents($obj->gifDest, $content);
    _error_log("ReceiveImage: download {$_REQUEST['downloadURL_gifimage']} {$obj->gifDestSize}");
} elseif (!empty($_FILES['gifimage']['tmp_name']) && (!empty($_REQUEST['update_video_id']) || !file_exists($obj->gifDest) || filesize($obj->gifDest) === 2095341)) {
    if (!move_uploaded_file($_FILES['gifimage']['tmp_name'], $obj->gifDest)) {
        $obj->msg = print_r(sprintf(__("Could not move gif image file [%s.gif]"), $destination_local), true);
        _error_log("ReceiveImage: " . $obj->msg);
        die(json_encode($obj));
    } else {
        $obj->gifDestSize = humanFileSize(filesize($obj->gifDest));
    }
} else {
    if (empty($_FILES['gifimage']['tmp_name'])) {
        receiveImageDebugLog("ReceiveImage: empty \$_FILES['gifimage']['tmp_name'] for {$obj->gifDest}");
    }
    if (file_exists($obj->gifDest)) {
        receiveImageDebugLog("ReceiveImage: File already exists " . $obj->gifDest);
        if (fileIsAnValidImage($obj->gifDest)) {
            receiveImageDebugLog("ReceiveImage: file is valid image " . filesize($obj->gifDest));
        }
    }
}

$obj->webpDest = "{$destination_local}.webp";
$resolvedIP_webp = null;
if (!empty($_REQUEST['downloadURL_webpimage']) && isSSRFSafeURL($_REQUEST['downloadURL_webpimage'], $resolvedIP_webp)) {
    $content = ssrfPinnedFetch($_REQUEST['downloadURL_webpimage'], $resolvedIP_webp);
    $obj->webpDestSize = file_put_contents($obj->webpDest, $content);
    _error_log("ReceiveImage: download {$_REQUEST['downloadURL_webpimage']} {$obj->webpDestSize}");
} elseif (!empty($_FILES['webpimage']['tmp_name']) && (!empty($_REQUEST['update_video_id']) || !file_exists($obj->webpDest) || filesize($obj->webpDest) === 2095341)) {
    if (!move_uploaded_file($_FILES['webpimage']['tmp_name'], $obj->webpDest)) {
        $obj->msg = print_r(sprintf(__("Could not move webp image file [%s.webp]"), $destination_local), true);
        _error_log("ReceiveImage: " . $obj->msg);
        die(json_encode($obj));
    } else {
        $obj->webpDestSize = humanFileSize(filesize($obj->webpDest));
    }
} else {
    if (empty($_FILES['webpimage']['tmp_name'])) {
        receiveImageDebugLog("ReceiveImage: empty \$_FILES['webpimage']['tmp_name'] for {$obj->webpDest}");
    }
    if (file_exists($obj->webpDest)) {
        receiveImageDebugLog("ReceiveImage: File already exists " . $obj->webpDest);
        if (fileIsAnValidImage($obj->webpDest)) {
            receiveImageDebugLog("ReceiveImage: file is valid image " . filesize($obj->webpDest));
        }
    }
}

if (!empty($obj->jpgDest)) {
    $obj->jpgDest_deleteInvalidImage = deleteInvalidImage(@$obj->jpgDest);
}
if (!empty($obj->jpgSpectrumDest)) {
    $obj->jpgSpectrumDest_deleteInvalidImage = deleteInvalidImage(@$obj->jpgSpectrumDest);
}
if (!empty($obj->gifDest)) {
    $obj->gifDest_deleteInvalidImage = deleteInvalidImage($obj->gifDest);
}
if (!empty($obj->webpDest)) {
    $obj->webpDest_deleteInvalidImage = deleteInvalidImage(@$obj->webpDest);
}

if (!empty($_REQUEST['duration'])) {
    $duration = $video->getDuration();
    if (empty($duration) || $duration === 'EE:EE:EE') {
        $video->setDuration($_REQUEST['duration']);
    } else if ($_REQUEST['duration'] !== 'EE:EE:EE') {
        $video->setDuration($_REQUEST['duration']);
    }
}

$videos_id = $video->save();
Video::clearCache($videos_id, true);
AVideoPlugin::onEncoderReceiveImage($videos_id);

$obj->error = false;
$obj->video_id = $videos_id;
$v = new Video('', '', $videos_id, true);
$obj->video_id_hash = $v->getVideoIdHash();
$obj->releaseDate = @$_REQUEST['releaseDate'];
$obj->releaseTime = @$_REQUEST['releaseTime'];

$json = json_encode($obj);
die($json);

/*
_error_log(json_encode($_REQUEST));
_error_log(json_encode($_FILES));
var_dump($_REQUEST, $_FILES);
*/
