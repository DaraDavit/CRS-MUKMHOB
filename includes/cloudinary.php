<?php
require_once __DIR__ . '/env.php';

define('CLOUDINARY_CLOUD_NAME', env('CLOUDINARY_CLOUD_NAME', ''));
define('CLOUDINARY_API_KEY', env('CLOUDINARY_API_KEY', ''));
define('CLOUDINARY_API_SECRET', env('CLOUDINARY_API_SECRET', ''));
define('CLOUDINARY_FOLDER', 'crs_app');

function cloudinary_upload($file_path, $public_id = null, $folder = null) {
    if (empty(CLOUDINARY_CLOUD_NAME) || empty(CLOUDINARY_API_KEY) || empty(CLOUDINARY_API_SECRET)) {
        return null;
    }

    $params = [
        'timestamp' => time(),
        'folder'    => $folder ?? CLOUDINARY_FOLDER,
    ];
    if ($public_id) {
        $params['public_id'] = $public_id;
    }

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
    curl_setopt($ch, CURLOPT_USERPWD, CLOUDINARY_API_KEY . ':' . CLOUDINARY_API_SECRET);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['secure_url'] ?? null;
    }
    return null;
}

function cloudinary_delete($url) {
    if (empty(CLOUDINARY_CLOUD_NAME) || empty(CLOUDINARY_API_KEY) || empty(CLOUDINARY_API_SECRET) || empty($url)) {
        return false;
    }

    if (!preg_match('#/upload/v\d+/(.+)\.\w+$#', parse_url($url, PHP_URL_PATH), $m)) {
        return false;
    }
    $public_id = $m[1];

    $params = [
        'timestamp' => time(),
        'public_id' => $public_id,
    ];

    $ch = curl_init('https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/destroy');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERPWD, CLOUDINARY_API_KEY . ':' . CLOUDINARY_API_SECRET);
    curl_exec($ch);
    curl_close($ch);

    return true;
}
