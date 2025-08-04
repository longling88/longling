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
    <html lang='en'>
    <head>
        <meta charset='utf-8'>
        <title>正在跳转... (Redirecting...)</title>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <meta http-equiv='refresh' content='{$delay};url={$target}'>
        <style>
            body {
                text-align: center;
                padding-top: 100px;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                color: #333;
                background: #f9f9f9;
                margin: 0;
            }
            .container {
                background: #ffffff;
                border-radius: 8px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                display: inline-block;
                padding: 40px 60px;
                max-width: 600px;
                box-sizing: border-box;
            }
            h2 {
                color: #0056b3;
                margin-bottom: 20px;
            }
            a {
                color: #007bff;
                text-decoration: none;
                font-weight: bold;
            }
            a:hover {
                text-decoration: underline;
            }
            #countdown {
                font-size: 2.5em;
                font-weight: bold;
                color: #e44d26; /* 倒计时数字的颜色 */
                display: block;
                margin-top: 15px;
            }
            p {
                font-size: 1.1em;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>正在跳转到：<br><a href='{$target}'>{$target}</a></h2>
            <p><span id='countdown'>{$delay}</span> 秒后自动跳转，如果没有跳转，请点击上面的链接。</p>
            <p>(Automatic redirect in <span id='countdown-en'>{$delay}</span> seconds. If not redirected, please click the link above.)</p>
        </div>
        
        <script>
            // 获取倒计时显示元素
            var countdownElement = document.getElementById('countdown');
            var countdownElementEn = document.getElementById('countdown-en'); // 英文版倒计时元素
            
            // 获取初始倒计时秒数
            var timeLeft = {$delay};

            // 定义倒计时函数
            function updateCountdown() {
                if (countdownElement) {
                    countdownElement.textContent = timeLeft; // 更新中文显示
                }
                if (countdownElementEn) {
                    countdownElementEn.textContent = timeLeft; // 更新英文显示
                }
                timeLeft--; // 减少剩余时间

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
<html lang='en'>
<head>
    <meta charset='utf-8'>
    <title>未设置跳转规则 (No Redirect Rule Set)</title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { text-align: center; padding-top: 100px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #f9f9f9; }
        .container {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: inline-block;
            padding: 40px 60px;
            max-width: 600px;
            box-sizing: border-box;
        }
        h2 { color: #dc3545; margin-bottom: 20px; }
        p { font-size: 1.1em; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>未设置跳转规则 (No Redirect Rule Set)</h2>
        <p>当前域名 <code>{$host}</code> 未在系统中配置跳转规则。</p>
        <p>(The current domain <code>{$host}</code> is not configured for redirection in the system.)</p>
    </div>
</body>
</html>";
?>