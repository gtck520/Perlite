<?php
session_start();

// Configuration
$api_url = 'https://auto.kanglan.vip/cozeapi/user/checkPlanetMember';
$cookie_name = 'perlite_auth';
$cookie_time = 60 * 60 * 24 * 7; // 7 days
$auth_hash = md5('perlite_api_auth_v1'); // Simple hash for cookie verification

/**
 * Check authentication via external API
 * @param array $params ['token' => '...'] or ['username' => '...', 'password' => '...']
 * @return boolean
 */
function check_auth_api($params) {
    global $api_url;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Perlite-Auth-Client/1.0',
        'Accept: application/json'
    ]);
    // Enable SSL verification if possible, or disable if facing issues in dev
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        // Check code=1 and is_planet_member=true
        if (isset($data['code']) && $data['code'] === 1 && 
            isset($data['data']['is_planet_member']) && 
            $data['data']['is_planet_member'] === true) {
            return true;
        }
    }
    return false;
}

// 1. Check URL Token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if (check_auth_api(['token' => $token])) {
        $_SESSION['logged_in'] = true;
        setcookie($cookie_name, $auth_hash, time() + $cookie_time, "/");
        // Remove token from URL to keep it clean (optional, but good practice)
        // header("Location: /");
        // exit;
    }
}

// 2. Check Logout
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie($cookie_name, "", time() - 3600, "/");
    header("Location: /");
    exit;
}

// 3. Check Session or Cookie
$is_logged_in = false;

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $is_logged_in = true;
} elseif (isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] === $auth_hash) {
    $_SESSION['logged_in'] = true;
    $is_logged_in = true;
}

// 4. Handle Login POST
$error = '';
if (!$is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        if (check_auth_api(['username' => $username, 'password' => $password])) {
            $_SESSION['logged_in'] = true;
            setcookie($cookie_name, $auth_hash, time() + $cookie_time, "/");
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $error = '鉴权失败：非知识库成员或账号密码错误';
        }
    } else {
        $error = '请输入用户名和密码';
    }
}

// 5. If not logged in, show login page and exit
if (!$is_logged_in) {
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登录 - Perlite 知识库</title>
    <style>
        body {
            background-color: #202020;
            color: #dcddde;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: #2f3136;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 320px;
        }
        h2 { text-align: center; margin-bottom: 1.5rem; color: #fff; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; }
        input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #202225;
            background-color: #40444b;
            color: #fff;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 0.7rem;
            background-color: #5865f2;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 1rem;
        }
        button:hover { background-color: #4752c4; }
        .error { color: #ed4245; font-size: 0.9rem; text-align: center; margin-bottom: 1rem; }
        .tip { font-size: 0.85rem; color: #b9bbbe; text-align: center; margin-top: 1.5rem; line-height: 1.5; }
        .tip a { color: #00b0f4; text-decoration: none; }
        .tip a:hover { text-decoration: underline; }
        .tip p { margin: 0.5rem 0; }
        .tip .sub-text { font-size: 0.75rem; color: #72767d; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>知识库登录</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autofocus placeholder="请输入 auto.kanglan.vip 账号">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required placeholder="请输入 auto.kanglan.vip 密码">
            </div>
            <button type="submit">登录</button>
        </form>
        <div class="tip">
            <p>请使用 <a href="http://auto.kanglan.vip" target="_blank">auto.kanglan.vip</a> 的账号密码登录</p>
            <p>还没有账号？<a href="http://auto.kanglan.vip/index.html#/user/login" target="_blank">点击注册/找回密码</a></p>
            <p class="sub-text">或者在 URL 中使用 ?token=... 访问</p>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}
?>
