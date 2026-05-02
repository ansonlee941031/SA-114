<?php
session_start();
require_once 'config/db.php'; // 引用您的資料庫連線[cite: 4]
require_once 'config/google_config.php'; // 引用 Google 設定[cite: 4]

if (isset($_GET['code'])) {
    // --- 1. 用 Code 交換 Access Token ---
    $token_url = "https://oauth2.googleapis.com/token";
    $post_data = [
        'code'          => $_GET['code'],
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        $access_token = $data['access_token'];

        // --- 2. 用 Access Token 取得使用者資訊 ---
        $info_url = "https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . $access_token;
        $user_info_json = file_get_contents($info_url);
        $user_info = json_decode($user_info_json, true);

        // 取得 Google 真正的資料
        $google_id = $user_info['id'];
        $name      = $user_info['name'];
        $email     = $user_info['email'];
        $picture   = $user_info['picture'];

        // --- 3. 檢查資料庫並登入 ---
        $stmt = $conn->prepare("SELECT id FROM users WHERE google_id = ?");
        $stmt->bind_param("s", $google_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            // 新使用者：執行註冊[cite: 4]
            $insert = $conn->prepare("INSERT INTO users (google_id, name, email, picture) VALUES (?, ?, ?, ?)");
            $insert->bind_param("ssss", $google_id, $name, $email, $picture);
            $insert->execute();
            $_SESSION['user_id'] = $insert->insert_id;
        } else {
            // 舊使用者：取得 ID[cite: 4]
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
        }

        // 存入 Session 供導覽列使用[cite: 4, 5]
        $_SESSION['user_name'] = $name;
        $_SESSION['user_pic'] = $picture;

        // 成功！跳轉回地圖[cite: 4]
        header("Location: cafe_map.php");
        exit();
    } else {
        die("無法取得 Google Token，請檢查 Client ID/Secret 設定。");
    }
} else {
    die("未收到 Google 回傳的授權碼。");
}
?>