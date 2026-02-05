<?php

/**
 * 主重定向脚本。
 * 根据 data/domains.json 中的域名映射处理自动重定向。
 */

require_once __DIR__ . '/config.php'; // 引入配置文件

// 为域名数据文件路径定义常量
define('DOMAINS_FILE', __DIR__ . '/data/domains.json');

// --- 函数 ---

function getDomainData(): array 
{
    $filePath = DOMAINS_FILE;
    if (!file_exists($filePath)) {
        error_log("错误：未找到域名文件：" . $filePath);
        return [];
    }
    $content = file_get_contents($filePath);
    if ($content === false) {
        error_log("错误：无法读取域名文件：" . $filePath);
        return [];
    }
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON 解码错误：" . json_last_error_msg());
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
    // 获取延迟时间，默认为 3 秒
    $delay = defined('REDIRECT_DELAY') ? REDIRECT_DELAY : 3;

    echo "<!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='utf-8'>
        <title>正在跳转...</title>
        <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>
        <meta http-equiv='refresh' content='{$delay};url={$target}'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background-color: #f8f9fa; /* 浅灰白背景 */
                color: #333;
                line-height: 1.6;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            /* 白色卡片容器 */
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
            
            h1 {
                color: #202124;
                font-size: 22px;
                margin-bottom: 25px;
                font-weight: 500;
            }

            /* --- 新版：文件传输式进度条 --- */
            .progress-wrapper {
                width: 100%;
                height: 8px; /* 稍微厚一点 */
                background-color: #f1f3f4; /* 灰色底槽 */
                border-radius: 4px;
                overflow: hidden;
                margin: 25px 0;
                box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            }

            .progress-bar {
                height: 100%;
                width: 0%; /* 初始宽度为0 */
                background-color: #1a73e8; /* 谷歌蓝 */
                border-radius: 4px;
                /* 增加斜纹纹理，更有文件传输的感觉 */
                background-image: linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);
                background-size: 1rem 1rem;
                /* 核心动画：时间等于PHP变量delay */
                animation: fillProgress {$delay}s linear forwards;
            }

            @keyframes fillProgress {
                0% { width: 0%; }
                100% { width: 100%; }
            }
            /* --------------------------- */
            
            .target-url {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 12px;
                margin: 20px 0;
                word-break: break-all;
                font-size: 14px;
                color: #5f6368;
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
                padding: 10px 24px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: 500;
                transition: background-color 0.2s;
                border: none;
                cursor: pointer;
                font-size: 14px;
            }
            
            .btn:hover {
                background-color: #1557b0;
                box-shadow: 0 1px 2px rgba(60,64,67,0.3);
            }
            
            .language-switch { margin-top: 15px; font-size: 12px; color: #9aa0a6; }
            
            @media (max-width: 480px) {
                body { padding: 15px; }
                .container { padding: 30px 20px; }
                h1 { font-size: 18px; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>正在为您跳转</h1>
            
            <div class='progress-wrapper'>
                <div class='progress-bar'></div>
            </div>
            
            <div class='target-url'>
                <a href='{$target}'>{$target}</a>
            </div>
            
            <div class='countdown-section'>
                <div class='countdown-text'>
                    <span class='countdown-number' id='countdown'>{$delay}</span> 秒后自动跳转
                </div>
                <div class='countdown-text' style='font-size: 12px;'>
                    Automatic redirect in <span id='countdown-en'>{$delay}</span> seconds
                </div>
            </div>
            
            <div class='manual-redirect'>
                <a href='{$target}' class='btn'>立即访问</a>
                <div class='language-switch'>If not redirected, please click the button</div>
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

// 错误页面（保持白底风格）
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
        <p class='message'>The current domain is not configured for redirection</p>
    </div>
</body>
</html>";
?>
