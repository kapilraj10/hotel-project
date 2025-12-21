<?php
// Config file for restaurant app
define('DB_HOST','127.0.0.1');
define('DB_PORT',3306);
define('DB_NAME','restaurant_db');
define('DB_USER','hotel_user');
define('DB_PASS','changeme');

// Base paths
define('BASE_PATH', __DIR__ . '/');
define('UPLOADS_DIR', BASE_PATH . 'uploads/');
// NOTE: UPLOADS_URL should match where uploads are served from in your webserver.
// Change this if you move the uploads folder or remove `assets/` from URLs.
// URL path where uploaded files are served from. This should map to UPLOADS_DIR.
// Updated to remove the old `assets/` segment so uploaded images are served from /hotel/uploads/.
define('UPLOADS_URL', '/hotel/uploads/');

// Ensure uploads dir exists. Use @mkdir to suppress warnings and expose a writable flag.
if (!is_dir(UPLOADS_DIR)) {
    $created = @mkdir(UPLOADS_DIR, 0755, true);
    if ($created === false && !is_dir(UPLOADS_DIR)) {
        // Could not create uploads dir. Log and mark as not writable.
        error_log("[config] Failed to create uploads directory: " . UPLOADS_DIR);
        define('UPLOADS_WRITABLE', false);
    } else {
        define('UPLOADS_WRITABLE', is_writable(UPLOADS_DIR));
    }
} else {
    define('UPLOADS_WRITABLE', is_writable(UPLOADS_DIR));
}
