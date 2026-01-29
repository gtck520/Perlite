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

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    // Clear LocalStorage and reload without params
    echo "<script>
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('perlite_sid');
        }
        window.location.href = window.location.pathname;
    </script>";
    exit;
}

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
require_once __DIR__ . '/vendor/autoload.php';

// Try to load HOME_FILE content if configured in .env
$home_html = '';
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === 'HOME_FILE') {
                // Try to find the file in 'notes' directory
                // First try exact match
                $md_path = __DIR__ . '/notes/' . $value;
                if (!file_exists($md_path)) {
                    // Try with .md extension
                    $md_path = __DIR__ . '/notes/' . $value . '.md';
                }
                
                if (file_exists($md_path)) {
                    $Parsedown = new Parsedown();
                    $home_html = $Parsedown->text(file_get_contents($md_path));
                    
                    // Simple styling fix for Parsedown output
                    $home_html = str_replace('<h1>', '<h1 style="color: #fff; margin-top: 0; font-size: 1.8rem; border-bottom: 2px solid #5865f2; padding-bottom: 15px; margin-bottom: 20px;">', $home_html);
                    $home_html = str_replace('<h2>', '<h2 style="color: #5865f2; margin-top: 25px; margin-bottom: 15px; font-size: 1.4rem;">', $home_html);
                    $home_html = str_replace('<h3>', '<h3 style="color: #fff; margin-top: 20px; margin-bottom: 10px; font-size: 1.2rem;">', $home_html);
                    $home_html = str_replace('<ul>', '<ul style="padding-left: 20px; margin-top: 5px; color: #b9bbbe;">', $home_html);
                    $home_html = str_replace('<li>', '<li style="margin-bottom: 8px; line-height: 1.6;">', $home_html);
                    $home_html = str_replace('<p>', '<p style="color: #b9bbbe; line-height: 1.6; margin-bottom: 15px;">', $home_html);
                    $home_html = str_replace('<strong>', '<strong style="color: #fff;">', $home_html);
                }
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI 自动化基地 - 知识库登录</title>
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
            background-color: #1a1b1e;
            color: #dcddde;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .main-container {
            display: flex;
            background-color: #2f3136;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 1000px;
            overflow: hidden;
            flex-direction: row;
        }
        
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }
        }

        /* Left Side: Course Info */
        .info-panel {
            flex: 1.5;
            background: linear-gradient(135deg, #202225 0%, #2f3136 100%);
            padding: 40px;
            border-right: 1px solid #202225;
            overflow-y: auto;
            max-height: 80vh;
        }

        .info-panel h1 {
            color: #fff;
            margin-top: 0;
            font-size: 1.8rem;
            border-bottom: 2px solid #5865f2;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .course-highlight {
            background-color: rgba(88, 101, 242, 0.1);
            border-left: 4px solid #5865f2;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .course-highlight h3 {
            color: #5865f2;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .outline-section {
            margin-bottom: 25px;
        }
        
        .outline-section h3 {
            color: #fff;
            font-size: 1.2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .outline-section h3::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #5865f2;
            border-radius: 50%;
            margin-right: 10px;
        }

        .outline-section p, .outline-section li {
            color: #b9bbbe;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .outline-section ul {
            padding-left: 20px;
            margin-top: 5px;
        }

        /* Right Side: Login Form */
        .login-panel {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: #36393f;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #fff;
            margin: 0;
            font-size: 1.5rem;
        }
        
        .login-header p {
            color: #b9bbbe;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .form-group { margin-bottom: 1.2rem; }
        
        label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #b9bbbe; }
        
        input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #202225;
            background-color: #202225;
            color: #fff;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        
        input:focus {
            border-color: #5865f2;
            outline: none;
        }
        
        button {
            width: 100%;
            padding: 0.8rem;
            background-color: #5865f2;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            margin-top: 1rem;
            transition: background-color 0.2s;
        }
        
        button:hover { background-color: #4752c4; }
        
        .error { 
            background-color: rgba(237, 66, 69, 0.1);
            border: 1px solid #ed4245;
            color: #ed4245; 
            padding: 10px;
            border-radius: 4px;
            font-size: 0.9rem; 
            text-align: center; 
            margin-bottom: 1.5rem; 
        }
        
        .access-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #4f545c;
            text-align: center;
        }
        
        .access-info p {
            margin: 8px 0;
            color: #b9bbbe;
            font-size: 0.9rem;
        }
        
        .highlight-text {
            color: #fff;
            font-weight: bold;
        }
        
        .wx-id {
            color: #3ba55c;
            font-weight: bold;
            font-family: monospace;
            background: rgba(59, 165, 92, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .price-tag {
            color: #faa61a;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .action-links {
            margin-top: 15px;
            font-size: 0.85rem;
        }
        
        .action-links a {
            color: #00b0f4;
            text-decoration: none;
            margin: 0 5px;
        }
        
        .action-links a:hover { text-decoration: underline; }

        /* Scrollbar styling */
        .info-panel::-webkit-scrollbar {
            width: 8px;
        }
        .info-panel::-webkit-scrollbar-track {
            background: #2f3136; 
        }
        .info-panel::-webkit-scrollbar-thumb {
            background: #202225; 
            border-radius: 4px;
        }
        .info-panel::-webkit-scrollbar-thumb:hover {
            background: #5865f2; 
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Left Side: Course Info -->
        <div class="info-panel">
            <?php if ($home_html): ?>
                <?php echo $home_html; ?>
            <?php else: ?>
            <h1>AI 自动化基地 · 实战交付体系 (V2.0)</h1>
            
            <div class="course-highlight">
                <h3>🚀 拒绝焦虑，打造 AI 超级个体</h3>
                <p>从认知觉醒到工具掌控，再到技能变现。无论你是职场人、创业者还是寻找副业的普通人，这套体系都将是你最低成本的杠杆。</p>
            </div>

            <div class="outline-section">
                <h3>01 基础篇：认知重塑与概念扫盲</h3>
                <p>听懂行话，看清本质，建立正确的 AI 世界观。</p>
                <ul>
                    <li><strong>AI 的大脑与灵魂</strong>：祛魅 LLM，理解 Token/幻觉，掌握 RAG 原理。</li>
                    <li><strong>提示词工程</strong>：BRTR 原则，三步对话节奏，让 AI 主动反问。</li>
                    <li><strong>AI 的手脚与边界</strong>：MCP 协议，AI 驱动思维与任务拆解。</li>
                </ul>
            </div>

            <div class="outline-section">
                <h3>02 实操篇：国内主流工具全攻略</h3>
                <p>熟练掌握国内最强 AI 工具栈。</p>
                <ul>
                    <li><strong>文本助理 (豆包)</strong>：高效阅读，爆款写作，口语陪练。</li>
                    <li><strong>视觉创意 (即梦)</strong>：AI 绘画与视频生成，可控视频流实战。</li>
                    <li><strong>智能体 (Coze)</strong>：0代码搭建 Bot，配置插件与工作流。</li>
                </ul>
            </div>

            <div class="outline-section">
                <h3>03 赋能篇：AI 编程与超级个体</h3>
                <p>打破技术壁垒，实现效率百倍提升。</p>
                <ul>
                    <li><strong>人人都是产品经理+程序员</strong>：Trae/Cursor 实战，自然语言编程。</li>
                    <li><strong>自动化脚本</strong>：批量文件处理，数据抓取与整理。</li>
                </ul>
            </div>

            <div class="outline-section">
                <h3>04 搞钱篇：商业闭环与综合实战</h3>
                <p>技术落地，流量变现。</p>
                <ul>
                    <li><strong>全自动自媒体矩阵</strong>：Coze + 即梦 + 剪映流水线。</li>
                    <li><strong>知识库问答机器人</strong>：企业/个人分身搭建。</li>
                    <li><strong>变现路径</strong>：信息差变现，技能服务变现，流量变现。</li>
                </ul>
            </div>
            
            <div style="margin-top: 30px; border-top: 1px solid #4f545c; padding-top: 20px; color: #b9bbbe; font-style: italic;">
                "工具永远只是工具，使用工具的人才是关键。行动，是缓解焦虑的唯一良药。"
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Side: Login Form -->
        <div class="login-panel">
            <div class="login-header">
                <h2>知识库登录</h2>
                <p>会员专享 · 实战干货 · 持续更新</p>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="username">用户名 / 账号</label>
                    <input type="text" id="username" name="username" required autofocus placeholder="请输入 auto.kanglan.vip 账号">
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required placeholder="请输入 auto.kanglan.vip 密码">
                </div>
                <button type="submit">立即登录</button>
            </form>

            <div class="access-info">
                <p><span class="highlight-text">如何获取权限？</span></p>
                <p>加入知识星球或成为知识库会员</p>
                <p>💰 价格：<span class="price-tag">99 元</span></p>
                <p>👉 联系微信：<span class="wx-id">kan28256</span></p>
                
                <div class="action-links">
                    <a href="http://auto.kanglan.vip/index.html#/user/login" target="_blank">注册/找回密码</a> |
                    <a href="?logout=1" style="color: #72767d;">清除缓存</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    exit;
?>
