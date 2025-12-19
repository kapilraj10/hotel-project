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
define('UPLOADS_URL', '/hotel/assets/uploads/');

// Ensure uploads dir exists
if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
}
