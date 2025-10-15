<?php

/**
 * 主重定向脚本。
 * 根据 data/domains.json 中的域名映射处理自动重定向。
 */

require_once __DIR__ . '/config.php'; // 引入配置文件，确保 REDIRECT_DELAY 常量可用

// 为域名数据文件路径定义常量
define('DOMAINS_FILE', __DIR__ . '/data/domains.json');

// --- 函数以更好地组织代码 ---

/**
 * 从 JSON 文件中获取域名数据。
 * @return array 解码后的域名数据，失败时返回空数组。
 */
function getDomainData(): array // PHP 7.0+ 支持的返回类型声明
{
    $filePath = DOMAINS_FILE;
    // 检查文件是否存在
    if (!file_exists($filePath)) {
        error_log("错误：未找到域名文件：" . $filePath); // 将错误记录到 PHP 错误日志
        return [];
    }
    // 读取文件内容
    $content = file_get_contents($filePath);
    if ($content === false) {
        error_log("错误：无法读取域名文件：" . $filePath);
        return [];
    }
    // 解码 JSON 内容
    $data = json_decode($content, true);
    // 检查 JSON 解码是否成功
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("从 " . $filePath . " 解码 JSON 错误：" . json_last_error_msg());
        return [];
    }
    return $data;
}

// --- 主执行逻辑 ---

// 获取域名映射数据
$data = getDomainData();
// 获取当前访问的域名
$host = $_SERVER['HTTP_HOST'];

// 查找当前域名对应的目标 URL
// 使用 Null 合并运算符 (??) 确保 $target 在找不到时为 null，这是 PHP 7.0+ 的语法
$target = $data[$host] ?? null; 

if ($target) {
    // 从 config.php 获取重定向延迟时间
    // 如果 REDIRECT_DELAY 未定义（不应该发生，但为了健壮性），则默认为 3 秒
    $delay = defined('REDIRECT_DELAY') ? REDIRECT_DELAY : 3;

    // 输出包含自动跳转的 HTML 页面
    echo "<!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='utf-8'>
        <title>正在跳转...</title>
        <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>
        <meta http-equiv='refresh' content='{$delay};url={$target}'>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #333;
                line-height: 1.6;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 20px;
                padding: 40px 30px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                max-width: 500px;
                width: 100%;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .redirect-icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 20px;
                background: linear-gradient(135deg, #4CAF50, #45a049);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: pulse 2s infinite;
            }
            
            .redirect-icon::before {
                content: '→';
                font-size: 2.5em;
                color: white;
                font-weight: bold;
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            h1 {
                color: #2c3e50;
                font-size: 1.5em;
                margin-bottom: 15px;
                font-weight: 600;
            }
            
            .target-url {
                background: #f8f9fa;
                border: 2px solid #e9ecef;
                border-radius: 12px;
                padding: 15px;
                margin: 20px 0;
                word-break: break-all;
                font-size: 0.95em;
            }
            
            .target-url a {
                color: #3498db;
                text-decoration: none;
                font-weight: 500;
                display: block;
            }
            
            .target-url a:hover {
                color: #2980b9;
                text-decoration: underline;
            }
            
            .countdown-section {
                margin: 25px 0;
            }
            
            .countdown-number {
                font-size: 3em;
                font-weight: bold;
                color: #e74c3c;
                display: block;
                line-height: 1;
                margin-bottom: 10px;
            }
            
            .countdown-text {
                color: #7f8c8d;
                font-size: 1em;
                margin-bottom: 5px;
            }
            
            .manual-redirect {
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid #ecf0f1;
            }
            
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #3498db, #2980b9);
                color: white;
                padding: 12px 30px;
                border-radius: 25px;
                text-decoration: none;
                font-weight: 500;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
                font-size: 1em;
            }
            
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
                text-decoration: none;
                color: white;
            }
            
            .language-switch {
                margin-top: 20px;
                font-size: 0.9em;
                color: #95a5a6;
            }
            
            /* 移动端优化 */
            @media (max-width: 768px) {
                body {
                    padding: 15px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                }
                
                .container {
                    padding: 30px 20px;
                    margin: 0;
                    border-radius: 16px;
                }
                
                .redirect-icon {
                    width: 60px;
                    height: 60px;
                    margin-bottom: 15px;
                }
                
                .redirect-icon::before {
                    font-size: 2em;
                }
                
                h1 {
                    font-size: 1.3em;
                }
                
                .countdown-number {
                    font-size: 2.5em;
                }
                
                .target-url {
                    padding: 12px;
                    font-size: 0.9em;
                }
                
                .btn {
                    padding: 10px 25px;
                    font-size: 0.95em;
                    width: 100%;
                    max-width: 200px;
                }
            }
            
            @media (max-width: 480px) {
                .container {
                    padding: 25px 15px;
                }
                
                h1 {
                    font-size: 1.2em;
                }
                
                .countdown-number {
                    font-size: 2em;
                }
                
                .countdown-text {
                    font-size: 0.9em;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='redirect-icon'></div>
            <h1>正在为您跳转</h1>
            
            <div class='target-url'>
                <a href='{$target}'>{$target}</a>
            </div>
            
            <div class='countdown-section'>
                <span class='countdown-number' id='countdown'>{$delay}</span>
                <div class='countdown-text'>秒后自动跳转</div>
                <div class='countdown-text'>Automatic redirect in <span id='countdown-en'>{$delay}</span> seconds</div>
            </div>
            
            <div class='manual-redirect'>
                <a href='{$target}' class='btn'>立即访问</a>
                <div class='language-switch'>If not redirected, please click the button</div>
            </div>
        </div>
        
        <script>
            // 获取倒计时显示元素
            var countdownElement = document.getElementById('countdown');
            var countdownElementEn = document.getElementById('countdown-en');
            
            // 获取初始倒计时秒数
            var timeLeft = {$delay};

            // 定义倒计时函数
            function updateCountdown() {
                if (countdownElement) {
                    countdownElement.textContent = timeLeft;
                }
                if (countdownElementEn) {
                    countdownElementEn.textContent = timeLeft;
                }
                timeLeft--;

                // 如果时间到，清除定时器
                if (timeLeft < 0) {
                    clearInterval(countdownInterval);
                }
            }

            // 每秒调用一次 updateCountdown 函数
            var countdownInterval = setInterval(updateCountdown, 1000);

            // 第一次调用，立即显示初始值
            updateCountdown();
        </script>
    </body>
    </html>";
    exit(); // 重定向页面输出后，终止脚本执行
}

// 如果当前域名未找到匹配的跳转规则，则显示提示页面
echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='utf-8'>
    <title>未设置跳转规则</title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            color: white;
            font-weight: bold;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 1.5em;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .domain-display {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 1.1em;
            color: #e74c3c;
            font-weight: 500;
        }
        
        .message {
            color: #7f8c8d;
            margin-bottom: 10px;
            font-size: 1em;
        }
        
        /* 移动端优化 */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .container {
                padding: 30px 20px;
                margin: 0;
                border-radius: 16px;
            }
            
            .error-icon {
                width: 60px;
                height: 60px;
                font-size: 2em;
                margin-bottom: 15px;
            }
            
            h1 {
                font-size: 1.3em;
            }
            
            .domain-display {
                padding: 12px;
                font-size: 1em;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 25px 15px;
            }
            
            h1 {
                font-size: 1.2em;
            }
            
            .message {
                font-size: 0.9em;
            }
        }
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
