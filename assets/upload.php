<?php
require_once __DIR__ . '/config.php';

function handle_image_upload($fileInputName) {
    if (!isset($_FILES[$fileInputName])) return null;
    $file = $_FILES[$fileInputName];
    // No file uploaded
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    // Other upload error
    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    // Try multiple ways to detect mime type: finfo, then getimagesize as fallback
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if (!$mime && function_exists('getimagesize')) {
        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? null;
    }
    if (!$mime) {
        log_upload('mime-detect-failed: ' . json_encode($file));
        return null;
    }

    $mime = strtolower($mime);
    // Accept several common variants
    $map = [
        'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/jpg' => 'jpg',
        'image/png' => 'png', 'image/x-png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];
    if (!isset($map[$mime])) {
        log_upload('mime-not-allowed: detected=' . ($mime ?? 'NULL') . ' file=' . json_encode($file));
        return null;
    }
    $ext = $map[$mime];
    $fname = uniqid('img_', true) . '.' . $ext;
    $dest = UPLOADS_DIR . $fname;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return UPLOADS_URL . $fname;
    }
    // move failed
    log_upload('move-failed: dest=' . $dest . ' php_error=' . ($file['error'] ?? ''));
    return null;
}

function ensure_logs_dir() {
    $logdir = BASE_PATH . 'logs/';
    if (!is_dir($logdir)) {
        @mkdir($logdir, 0755, true);
    }
    return $logdir;
}

function log_upload($msg) {
    $logdir = ensure_logs_dir();
    $file = $logdir . 'upload.log';
    $time = date('Y-m-d H:i:s');
    @file_put_contents($file, "[$time] " . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function delete_local_upload($url_or_path) {
    // Accept either a full URL (UPLOADS_URL + filename) or a relative path stored in DB
    if (!$url_or_path) return false;
    // If it already looks like a file path under UPLOADS_DIR, handle it
    $basename = null;
    // If starts with UPLOADS_URL, strip to get filename
    if (strpos($url_or_path, UPLOADS_URL) === 0) {
        $basename = substr($url_or_path, strlen(UPLOADS_URL));
    } else {
        // maybe stored as relative path like assets/uploads/xxx
        $parts = explode('/', $url_or_path);
        $basename = end($parts);
    }
    if (!$basename) return false;
    $file = UPLOADS_DIR . $basename;
    if (is_file($file)) {
        return @unlink($file);
    }
    return false;
}
