<?php

/**
 * 域名重定向状态检查工具。
 * 从 data/domains.json 读取域名映射并检查其重定向状态。
 */

// 为文件路径定义常量，以提高可维护性
define('DOMAINS_FILE', __DIR__ . '/data/domains.json');

// --- 函数以更好地组织代码 ---

/**
 * 从 JSON 文件中获取域名数据。
 * @return array 解码后的域名数据，失败时返回空数组。
 */
function getDomainData(): array // PHP 7.0+ 支持的返回类型声明
{
    $filePath = DOMAINS_FILE;
    if (!file_exists($filePath)) {
        error_log("错误：未找到域名文件：" . $filePath); // 记录错误到日志
        return [];
    }
    $content = file_get_contents($filePath);
    if ($content === false) {
        error_log("错误：无法读取域名文件：" . $filePath);
        return [];
    }
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("从 " . $filePath . " 解码 JSON 错误：" . json_last_error_msg());
        return [];
    }
    return $data;
}

/**
 * 检查给定 URL 的重定向状态。
 * @param string $url 要检查的 URL。
 * @return string 状态消息。
 */
function checkRedirectStatus(string $url): string // PHP 7.0+ 支持的参数类型声明和返回类型声明
{
    // 使用 stream_context_create 进行非阻塞请求和更好的控制
    $context = stream_context_create([
        "http" => [
            "method"  => "HEAD",
            "timeout" => 5, // 5 秒超时
            "ignore_errors" => true // 即使是 4xx/5xx 响应也获取头部信息
        ]
    ]);

    // 抑制 get_headers 的警告（例如，目标不可达时），并优雅地处理错误
    $headers = @get_headers($url, 1, $context);
    $status = "❌ 无响应 (No Response)"; // 默认状态

    if ($headers && is_array($headers)) {
        $status_line = $headers[0]; // 第一个头部是状态行 (例如：HTTP/1.1 200 OK)

        // 包含 301 (永久移动), 302 (临时移动), 307 (临时重定向), 308 (永久重定向)
        if (strpos($status_line, "301") !== false || strpos($status_line, "302") !== false || strpos($status_line, "307") !== false || strpos($status_line, "308") !== false) {
            $status = "✅ 跳转正常 (Redirect OK)";
        } elseif (strpos($status_line, "200") !== false) {
            $status = "⚠ 未跳转（状态200）(No Redirect - Status 200)";
        } else {
            // 显示其他状态码，并进行 HTML 编码以确保安全显示
            $status = "⚠ 状态码: " . htmlspecialchars($status_line);
        }
    }
    return $status;
}

// --- 主执行 ---
$data = getDomainData(); // 获取域名规则数据

echo "<h2>跳转状态检测工具 (Redirect Status Check Tool)</h2>";
echo "<table border='1' cellpadding='10' cellspacing='0' style='width:100%; border-collapse: collapse;'>";
echo "<tr>
        <th style='background-color:#f2f2f2; padding: 10px;'>原域名 (Original Domain)</th>
        <th style='background-color:#f2f2f2; padding: 10px;'>目标地址 (Target URL)</th>
        <th style='background-color:#f2f2f2; padding: 10px;'>检测状态 (Check Status)</th>
      </tr>";

if (empty($data)) {
    echo "<tr><td colspan='3' style='text-align:center; padding: 10px;'>未找到域名跳转规则 (No domain redirect rules found)</td></tr>";
} else {
    foreach ($data as $host => $rule) {
        if (is_array($rule) && isset($rule['target'])) {
            $target = $rule['target'];
            // 始终检查 HTTP，因为重定向可能从 HTTP 到 HTTPS 发生
            $url = "http://" . $host; 
            $status = checkRedirectStatus($url);
            echo "<tr>
                    <td style='padding: 10px;'>{$host}</td>
                    <td style='padding: 10px;'><a href='{$target}' target='_blank'>{$target}</a></td>
                    <td style='padding: 10px;'>{$status}</td>
                  </tr>";
        }
    }
}

echo "</table>";

?>
