<?php

// Fetch requested image URL
$imageURL = !empty($_GET['image']) ? $_GET['image'] : '';
if ($imageURL === '') {
    $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if (basename($scriptFilename) === basename(__FILE__)) {
        http_response_code(404);
    }
    return;
}
$rootDir = dirname(__FILE__) . '/../../';
if ($imageURL === 'favicon.ico') {
    $imgLocalFile = realpath($rootDir . 'videos/' . $imageURL);
} else {
    $imgLocalFile = realpath($rootDir . $imageURL);
}

// Containment: resolved path must stay within the AVideo install root.
// realpath() returns false for non-existent files, which also exits here.
$resolvedRoot = realpath($rootDir);
if ($imgLocalFile === false
    || $resolvedRoot === false
    || strpos($imgLocalFile, $resolvedRoot . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit;
}

if (file_exists($imgLocalFile)) {
    $imageInfo = getimagesize($imgLocalFile);
    if (empty($imageInfo)) {
        die('not image');
    }
    // Determine the content type based on the file extension
    $fileExtension = strtolower(pathinfo($imgLocalFile, PATHINFO_EXTENSION));
    switch ($fileExtension) {
        case 'jpg':
        case 'jpeg':
            $type = 'image/jpeg';
            break;
        case 'png':
            $type = 'image/png';
            break;
        case 'webp':
            $type = 'image/webp';
            break;
        case 'gif':
            $type = 'image/gif';
            break;
        default:
            $type = 'image/jpeg'; // Default to jpg if the extension is not recognized
            break;
    }

    // Serve the final image
    header("HTTP/1.0 200 OK"); // The image exists, so it's not a 404
    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($imgLocalFile));
    readfile($imgLocalFile);
    exit;
}
