<?php
// ─── Cloudinary configuration from .env ────────────────────────────
require_once __DIR__ . '/env.php';

define('CLOUDINARY_CLOUD_NAME', env('CLOUDINARY_CLOUD_NAME', ''));
define('CLOUDINARY_API_KEY', env('CLOUDINARY_API_KEY', ''));
define('CLOUDINARY_API_SECRET', env('CLOUDINARY_API_SECRET', ''));
define('CLOUDINARY_FOLDER', 'crs_app');

// ─── Upload a file to Cloudinary, return the secure URL ────────────
function cloudinary_upload($file_path, $public_id = null) {
    if (empty(CLOUDINARY_CLOUD_NAME) || empty(CLOUDINARY_API_KEY) || empty(CLOUDINARY_API_SECRET)) {
        return null;
    }

    $timestamp = time();
    $params = [
        'timestamp' => $timestamp,
        'folder'    => CLOUDINARY_FOLDER,
    ];
    if ($public_id) {
        $params['public_id'] = $public_id;
    }

    ksort($params);
    $signature_parts = [];
    foreach ($params as $key => $value) {
        $signature_parts[] = "$key=$value";
    }
    $signature_parts[] = CLOUDINARY_API_SECRET;
    $params['signature'] = sha1(implode('&', $signature_parts));
    $params['api_key'] = CLOUDINARY_API_KEY;

    if (is_string($file_path) && file_exists($file_path)) {
        $params['file'] = new CURLFile($file_path);
    } elseif (filter_var($file_path, FILTER_VALIDATE_URL)) {
        $params['file'] = $file_path;
    } else {
        return null;
    }

    $ch = curl_init('https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/upload');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['secure_url'] ?? null;
    }
    return null;
}

// ─── Delete an image from Cloudinary by URL ────────────────────────
function cloudinary_delete($url) {
    if (empty(CLOUDINARY_CLOUD_NAME) || empty(CLOUDINARY_API_KEY) || empty(CLOUDINARY_API_SECRET) || empty($url)) {
        return false;
    }

    $parts = explode('/', parse_url($url, PHP_URL_PATH));
    $filename = end($parts);
    $public_id = CLOUDINARY_FOLDER . '/' . pathinfo($filename, PATHINFO_FILENAME);

    $timestamp = time();
    $params = [
        'timestamp' => $timestamp,
        'public_id' => $public_id,
    ];

    ksort($params);
    $signature_parts = [];
    foreach ($params as $key => $value) {
        $signature_parts[] = "$key=$value";
    }
    $signature_parts[] = CLOUDINARY_API_SECRET;
    $params['signature'] = sha1(implode('&', $signature_parts));
    $params['api_key'] = CLOUDINARY_API_KEY;

    $ch = curl_init('https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/destroy');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_exec($ch);
    curl_close($ch);

    return true;
}
