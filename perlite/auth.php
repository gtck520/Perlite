<?php
// Configuration
$api_url = 'https://auto.kanglan.vip/cozeapi/user/checkPlanetMember';

// Session Configuration for No-Cookie Auth (iframe support)
ini_set('session.use_cookies', 0);
ini_set('session.use_only_cookies', 0);
ini_set('session.use_trans_sid', 1);
ini_set('session.name', 'PERLITE_SID');
// Set long session life
ini_set('session.gc_maxlifetime', 86400 * 7);

// Manually handle Session ID from URL
if (isset($_GET['PERLITE_SID'])) {
    session_id($_GET['PERLITE_SID']);
}

session_start();

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
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['code']) && $data['code'] === 1 && 
            isset($data['data']['is_planet_member']) && 
            $data['data']['is_planet_member'] === true) {
            return true;
        }
    }
    return false;
}

// 1. Check if already logged in via Session
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    // Inject JS to persist SID and configure AJAX
    $sid = session_id();
    $auth_script = "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var perlite_sid = '$sid';
            
            // 1. Sync to LocalStorage
            if (typeof localStorage !== 'undefined') {
                if (localStorage.getItem('perlite_sid') !== perlite_sid) {
                    localStorage.setItem('perlite_sid', perlite_sid);
                }
            }
            
            // 2. Setup jQuery AJAX to include SID
            if (typeof $ !== 'undefined') {
                $.ajaxSetup({
                    data: { 'PERLITE_SID': perlite_sid }
                });
            }
        });
    </script>";
    return; // Auth success, continue
}

// 2. Handle Token Login (URL)
if (isset($_GET['token'])) {
    if (check_auth_api(['token' => $_GET['token']])) {
        $_SESSION['is_logged_in'] = true;
        $sid = session_id();
        // Redirect to URL with SID only (clean URL)
        header("Location: ?PERLITE_SID=$sid");
        exit;
    }
}

// 3. Handle POST Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        if (check_auth_api(['username' => $username, 'password' => $password])) {
            $_SESSION['is_logged_in'] = true;
            $sid = session_id();
            // Redirect to URL with SID only (clean URL, no password)
            header("Location: ?PERLITE_SID=$sid");
            exit;
        } else {
            $error = '鉴权失败：账号或密码错误';
        }
    } else {
        $error = '请输入用户名和密码';
    }
}

// 4. Render Login Page (Bridge)
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登录 - Perlite 知识库</title>
    <script>
        // Bridge Logic: Check localStorage for SID and Redirect
        var sid = localStorage.getItem('perlite_sid');
        if (sid) {
            var url = new URL(window.location.href);
            // Only redirect if SID is missing from URL
            if (!url.searchParams.has('PERLITE_SID')) {
                url.searchParams.set('PERLITE_SID', sid);
                window.location.replace(url.toString());
            }
        }
    </script>
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
        <?php if (isset($error) && $error): ?>
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
?>
