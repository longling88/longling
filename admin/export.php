<?php

/**
 * 导出域名跳转规则为纯文本文件。
 * 包含登录验证，确保只有管理员才能下载。
 */

session_start();
require_once "../config.php"; // 引入 config.php 获取管理员用户名和密码

// 检查用户是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 如果未登录，重定向到登录页面或显示未授权消息
    header('HTTP/1.1 401 Unauthorized');
    echo 'Unauthorized access. Please log in to the admin panel.';
    exit();
}

// 定义域名数据文件路径
define('DOMAINS_FILE', __DIR__ . '/../data/domains.json');

/**
 * 读取 JSON 文件中的域名数据。
 * @return array 解码后的域名数据，失败时返回空数组。
 */
function readDomainRulesForExport(): array
{
    $filePath = DOMAINS_FILE;
    if (!file_exists($filePath)) {
        return [];
    }
    $content = file_get_contents($filePath);
    if ($content === false) {
        error_log("错误：导出功能无法读取域名文件：" . $filePath);
        return [];
    }
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("导出功能从 " . $filePath . " 解码 JSON 错误：" . json_last_error_msg());
        return [];
    }
    if (!is_array($data)) {
        return [];
    }
    return $data;
}

$domainRules = readDomainRulesForExport();

// 设置 HTTP 头，强制浏览器下载文件
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="domain_redirect_rules_' . date('Ymd_His') . '.txt"');
header('Pragma: no-cache');
header('Expires: 0');

// 遍历域名规则，格式化并输出到文件
if (!empty($domainRules)) {
    foreach ($domainRules as $oldDomain => $newUrl) {
        // 输出格式为：原域名 空格 跳转地址
        echo htmlspecialchars($oldDomain) . " " . htmlspecialchars($newUrl) . "\n";
    }
} else {
    echo "No domain redirect rules found.";
}

exit();

?>
