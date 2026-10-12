<?php

/**
 * 主重定向脚本。
 * 根据 data/domains.json 中的域名映射处理自动重定向，并记录访问日志。
 */

// 强制清除所有缓存 (防止 Cloudflare 或浏览器缓存旧的蓝色进度条页面)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

require_once __DIR__ . '/config.php'; // 引入配置文件

// 为域名数据文件路径定义常量
define('DOMAINS_FILE', __DIR__ . '/data/domains.json');

// --- 函数 ---

function getDomainData(): array 
{
    $filePath = DOMAINS_FILE;
    if (!file_exists($filePath)) {
        return [];
    }
    $content = file_get_contents($filePath);
    if ($content === false) {
        return [];
    }
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    return $data;
}

// --- 主执行逻辑 ---

$data = getDomainData();
$host = $_SERVER['HTTP_HOST'];
$rule = $data[$host] ?? null; 
$target = null;

if (is_array($rule) && isset($rule['target'])) {
    $target = $rule['target'];
}

if ($target) {
    // --- 访客访问日志追踪 (SQLite 记录) ---
    try {
        date_default_timezone_set('Asia/Shanghai');
        $dbPath = __DIR__ . '/data/logs.db';
        // 检查类是否存在，防止部分环境未安装 sqlite 扩展导致致命错误(Fatal Error)无法显示页面
        if (class_exists('SQLite3')) {
            $db = new SQLite3($dbPath);
            
            $db->exec("CREATE TABLE IF NOT EXISTS visit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                host TEXT,
                ip TEXT,
                user_agent TEXT,
                visit_time DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt = $db->prepare("INSERT INTO visit_logs (host, ip, user_agent, visit_time) VALUES (:host, :ip, :ua, datetime('now', 'localtime'))");
            $stmt->bindValue(':host', $host);
            $stmt->bindValue(':ip', trim($ip));
            $stmt->bindValue(':ua', $ua);
            $stmt->execute();
            $db->close();
        }
    } catch (Throwable $e) {
        // 捕获所有异常和错误，确保日志写入失败绝对不会影响前端的绿色跳转页面显示
        error_log("写入访问日志失败：" . $e->getMessage());
    }
    // --- 日志记录结束 ---

    // 获取延迟时间，默认为 3 秒
    $delay = defined('REDIRECT_DELAY') ? REDIRECT_DELAY : 3;

    echo "<!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='utf-8'>
        <title>安全跳转认证...</title>
        <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>
        <meta http-equiv='refresh' content='{$delay};url={$target}'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background-color: #f8f9fa;
                color: #333;
                line-height: 1.6;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .container {
                background: #ffffff;
                border-radius: 12px;
                padding: 40px 30px;
                text-align: center;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06); 
                max-width: 500px;
                width: 100%;
                border: 1px solid #ebebeb;
            }
            
            /* 标题：安全盾牌+文字 */
            h1 {
                color: #202124;
                font-size: 24px;
                margin-bottom: 25px;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            
            /* 副标题：绿色打钩+文字 */
            .safe-subtitle {
                color: #34a853; 
                font-size: 16px;
                font-weight: 600; 
                margin-top: -15px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }

            /* 绿色进度条 */
            .progress-wrapper {
                width: 100%;
                height: 8px; 
                background-color: #f1f3f4; 
                border-radius: 4px;
                overflow: hidden;
                margin: 20px 0 25px 0;
                box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            }

            .progress-bar {
                height: 100%;
                width: 0%; 
                background-color: #34a853;
                border-radius: 4px;
                background-image: linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);
                background-size: 1rem 1rem;
                animation: fillProgress {$delay}s linear forwards;
            }

            @keyframes fillProgress {
                0% { width: 0%; }
                100% { width: 100%; }
            }
            
            .target-url {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 12px;
                margin: 20px 0;
                word-break: break-all;
                font-size: 14px;
                color: #5f6368;
                border: 1px dashed #dadce0;
            }
            
            .target-url a { color: #1a73e8; text-decoration: none; }
            .target-url a:hover { text-decoration: underline; }
            
            .countdown-section { margin: 25px 0; color: #5f6368; }
            .countdown-number {
                font-size: 1.5em;
                font-weight: bold;
                color: #d93025;
                display: inline-block;
                margin: 0 5px;
            }
            .countdown-text { font-size: 14px; margin-bottom: 5px; }
            
            .manual-redirect {
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid #f1f3f4;
            }
            
            .btn {
                display: inline-block;
                background-color: #1a73e8;
                color: white;
                padding: 10px 30px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 500;
                transition: background-color 0.2s, box-shadow 0.2s;
                border: none;
                cursor: pointer;
                font-size: 15px;
            }
            
            .btn:hover {
                background-color: #1557b0;
                box-shadow: 0 2px 6px rgba(26,115,232,0.3);
            }
            
            .language-switch { margin-top: 15px; font-size: 12px; color: #9aa0a6; }
            
            @media (max-width: 480px) {
                body { padding: 15px; }
                .container { padding: 30px 20px; }
                h1 { font-size: 20px; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>
                <!-- 绿色安全盾牌 -->
                <svg xmlns='http://www.w3.org/2000/svg' width='26' height='26' viewBox='0 0 24 24' fill='none' stroke='#34a853' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'>
                    <path d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'></path>
                    <path d='M9 12l2 2 4-4'></path>
                </svg>
                安全认证已通过
            </h1>
            
            <!-- 绿色打钩与提示语 -->
            <div class='safe-subtitle'>
                <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='#34a853' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'>
                    <polyline points='20 6 9 17 4 12'></polyline>
                </svg>
                目标链接安全合规，请放心浏览
            </div>
            
            <div class='progress-wrapper'>
                <div class='progress-bar'></div>
            </div>
            
            <div class='target-url'>
                <a href='{$target}'>{$target}</a>
            </div>
            
            <div class='countdown-section'>
                <div class='countdown-text'>
                    即将为您前往安全网页，<span class='countdown-number' id='countdown'>{$delay}</span> 秒后跳转
                </div>
                <div class='countdown-text' style='font-size: 12px;'>
                    Redirecting to secure page in <span id='countdown-en'>{$delay}</span> seconds
                </div>
            </div>
            
            <div class='manual-redirect'>
                <a href='{$target}' class='btn'>立即访问</a>
                <div class='language-switch'>If not redirected automatically, please click the button</div>
            </div>
        </div>
        
        <script>
            var countdownElement = document.getElementById('countdown');
            var countdownElementEn = document.getElementById('countdown-en');
            var timeLeft = {$delay};

            function updateCountdown() {
                if (countdownElement) countdownElement.textContent = timeLeft;
                if (countdownElementEn) countdownElementEn.textContent = timeLeft;
                timeLeft--;
                if (timeLeft < 0) clearInterval(countdownInterval);
            }

            var countdownInterval = setInterval(updateCountdown, 1000);
            updateCountdown();
        </script>
    </body>
    </html>";
    exit(); 
}

// 错误页面
echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='utf-8'>
    <title>未设置跳转规则</title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: #ffffff;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06); 
            max-width: 500px;
            width: 100%;
            border: 1px solid #ebebeb;
        }
        .error-icon { font-size: 48px; color: #d93025; margin-bottom: 20px; }
        h1 { font-size: 20px; margin-bottom: 10px; color: #202124; }
        .domain-display {
            background: #f1f3f4;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
            font-family: monospace;
            color: #d93025;
        }
        .message { color: #5f6368; font-size: 14px; margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='error-icon'>!</div>
        <h1>未设置跳转规则</h1>
        <div class='domain-display'>{$host}</div>
        <p class='message'>当前域名未在系统中配置跳转规则</p>
    </div>
</body>
</html>";
?>
