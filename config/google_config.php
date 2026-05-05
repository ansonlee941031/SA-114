<?php
// config/google_config.php
$clientId = '46527709737-7vc9erkb1vbskpbr35acsakm4reogd9k.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/sa_project/login_callback.php';
// 確保 http_build_query 裡面有包含 'client_id' => $clientId
$googleLoginUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online'
]);