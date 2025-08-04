<?php

/**
 * 域名重定向管理后台。
 * 提供登录功能和域名重定向规则的 CRUD 操作。
 *
 * !!! 重要安全提示：此脚本存在严重的安全漏洞，特别是明文密码存储和比较。
 * 在任何生产环境中使用前，必须进行重大安全增强。
 * 建议使用 password_hash() 存储密码，并使用 password_verify() 来验证。
 */

session_start(); // 启动会话，用于登录状态管理
require_once "../config.php"; // 引入配置文件

// 为域名数据文件路径定义常量
define('DOMAINS_FILE', __DIR__ . '/../data/domains.json');

// --- 函数以更好地组织和重用代码 ---

/**
 * 处理用户登录逻辑。
 * 如果用户已登录或成功登录，返回 true；否则处理登录尝试并返回 false。
 * @return bool 如果用户已登录，则返回 true；否则返回 false。
 */
function handleLogin(): bool // PHP 7.0+ 支持的返回类型声明
{
    // 检查是否已经登录
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        return true;
    }

    // 处理 POST 请求中的登录尝试
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['username'], $_POST['password'])) {
        // !!! 安全警告：此处的密码是明文比较，极不安全。
        // 在实际生产环境中，请务必使用：
        // 1. password_hash() 将用户密码进行哈希处理并存储。
        // 2. password_verify() 来验证用户输入的密码和存储的哈希密码。
        if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
            $_SESSION['logged_in'] = true;
            // 重新生成会话 ID 以防止会话固定攻击
            session_regenerate_id(true);
            header("Location: " . $_SERVER['PHP_SELF']); // 登录成功后重定向到自身页面，避免表单重复提交
            exit();
        } else {
            $_SESSION['login_error'] = "登录失败，请重试 (Login failed, please try again)";
        }
    }
    return false;
}

/**
 * 显示登录表单的 HTML。
 */
function displayLoginForm(): void // PHP 7.0+ 支持的返回类型声明
{
    // 获取并清除会话中存储的登录错误信息
    $error = $_SESSION['login_error'] ?? ''; // PHP 7.0+ Null 合并运算符
    unset($_SESSION['login_error']);

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>后台登录 (Admin Login)</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                background-color: #eef4f7;
                margin: 0;
            }
            form {
                background: #fff;
                padding: 45px 50px;
                border-radius: 10px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 380px;
                box-sizing: border-box;
            }
            h2 {
                text-align: center;
                color: #0056b3;
                margin-bottom: 30px;
                font-size: 1.8em;
            }
            label {
                display: block;
                margin-bottom: 8px;
                font-weight: bold;
                color: #555;
            }
            input[type=text],
            input[type=password]{
                width: calc(100% - 22px); /* 减去内边距和边框 */
                padding: 12px;
                margin-bottom: 20px;
                border: 1px solid #cce0f0;
                border-radius: 6px;
                box-sizing: border-box;
                font-size: 1em;
                transition: border-color 0.3s;
            }
            input[type=text]:focus,
            input[type=password]:focus {
                border-color: #007bff;
                outline: none;
            }
            input[type=submit]{
                width: 100%;
                background-color: #007bff;
                color: white;
                padding: 14px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 1.1em;
                font-weight: bold;
                transition: background-color 0.3s ease;
            }
            input[type=submit]:hover{
                background-color: #0056b3;
            }
            .error{
                color: #dc3545;
                text-align: center;
                margin-top: 15px;
                font-size: 0.95em;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <form method="post">
            <h2>后台登录 (Login)</h2>
            <label for="username">用户名 (Username):</label>
            <input type="text" id="username" name="username" required autocomplete="username">
            <label for="password">密码 (Password):</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <input type="submit" value="登录 (Login)">
            ' . (!empty($error) ? "<p class='error'>$error</p>" : '') . '
        </form>
    </body>
    </html>';
    exit(); // 登录页面显示后终止脚本执行
}

/**
 * 读取 JSON 文件中的域名数据。
 * 如果文件不存在，则创建一个空文件。
 * @return array 解码后的域名数据，失败时返回空数组。
 */
function readDomainRules(): array // PHP 7.0+ 支持的返回类型声明
{
    $filePath = DOMAINS_FILE;
    if (!file_exists($filePath)) {
        // 如果文件不存在，则创建一个空 JSON 文件，避免后续操作错误
        file_put_contents($filePath, json_encode([]));
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
    // 确保 $data 是一个数组，即使 JSON 文件为空对象 {}
    if (!is_array($data)) {
        return [];
    }
    return $data;
}

/**
 * 将域名规则写入 JSON 文件。
 * @param array $data 要写入的数据。
 * @return bool 写入成功则返回 true，否则返回 false。
 */
function writeDomainRules(array $data): bool // PHP 7.0+ 支持的参数类型声明和返回类型声明
{
    $filePath = DOMAINS_FILE;
    // 使用 JSON_PRETTY_PRINT 使 JSON 文件更具可读性
    // JSON_UNESCAPED_SLASHES 防止斜杠被转义，保持 URL 的清晰性
    $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($jsonContent === false) {
        error_log("错误：编码 JSON 数据失败。");
        return false;
    }
    if (file_put_contents($filePath, $jsonContent) === false) {
        error_log("错误：无法写入域名文件：" . $filePath);
        return false;
    }
    return true;
}

// --- 主要流程控制 ---

// 首先检查登录状态，如果未登录则显示登录表单并退出
if (!handleLogin()) {
    displayLoginForm();
}

// 如果已登录，则继续执行管理面板的逻辑
$data = readDomainRules(); // 读取当前的域名规则

// 处理单条添加规则的 POST 请求
if (isset($_POST['add'])) {
    $old = trim($_POST['old']);
    $new = trim($_POST['new']);
    if ($old && $new) {
        // 输入验证和清理：使用 FILTER_SANITIZE_URL 过滤 URL，防止注入
        $old = filter_var($old, FILTER_SANITIZE_URL);
        $new = filter_var($new, FILTER_SANITIZE_URL);

        if (!empty($old) && !empty($new)) {
            // 将旧域名转换为小写，确保一致性和正确的匹配
            $data[strtolower($old)] = $new;
            if (writeDomainRules($data)) {
                $_SESSION['admin_message'] = "规则添加成功。";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['admin_message'] = "错误：规则添加失败，请检查文件权限。";
                $_SESSION['message_type'] = "error";
            }
            header("Location: " . $_SERVER['PHP_SELF']); // 重定向以防止重复提交表单
            exit();
        } else {
            $_SESSION['admin_message'] = "错误：请输入有效的原域名和跳转地址。";
            $_SESSION['message_type'] = "error";
        }
    }
}

// 处理批量添加/修改规则的 POST 请求
if (isset($_POST['batch_add'])) { // 将 'batch' 改为 'batch_add' 以区分批量删除
    $lines = explode("\n", trim($_POST['batch_input']));
    $updated = false;
    foreach ($lines as $line) {
        // 使用 preg_split 以处理由一个或多个空格分隔的域名和 URL
        $parts = preg_split('/\s+/', trim($line), 2);
        if (count($parts) === 2) {
            $old = filter_var(trim($parts[0]), FILTER_SANITIZE_URL);
            $new = filter_var(trim($parts[1]), FILTER_SANITIZE_URL);
            if (!empty($old) && !empty($new)) {
                $data[strtolower($old)] = $new;
                $updated = true;
            }
        }
    }
    if ($updated) {
        if (writeDomainRules($data)) {
            $_SESSION['admin_message'] = "批量添加/修改成功。";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['admin_message'] = "错误：批量添加/修改失败，请检查文件权限。";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['admin_message'] = "错误：批量输入格式不正确或为空。";
        $_SESSION['message_type'] = "error";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 处理单条删除规则的 GET 请求 (保留原功能)
if (isset($_GET['delete'])) {
    $keyToDelete = urldecode($_GET['delete']); // 对 URL 编码的键进行解码
    if (isset($data[$keyToDelete])) {
        unset($data[$keyToDelete]); // 从数组中删除对应的规则
        if (writeDomainRules($data)) {
            $_SESSION['admin_message'] = "规则删除成功。";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['admin_message'] = "错误：规则删除失败，请检查文件权限。";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['admin_message'] = "错误：要删除的规则不存在。";
        $_SESSION['message_type'] = "error";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// **新增：处理批量删除规则的 POST 请求**
if (isset($_POST['batch_delete']) && isset($_POST['selected_domains']) && is_array($_POST['selected_domains'])) {
    $deletedCount = 0;
    foreach ($_POST['selected_domains'] as $domainToDelete) {
        // 对接收到的域名进行清理，确保安全性
        $domainToDelete = filter_var(trim($domainToDelete), FILTER_SANITIZE_URL);
        if (!empty($domainToDelete) && isset($data[$domainToDelete])) {
            unset($data[$domainToDelete]);
            $deletedCount++;
        }
    }

    if ($deletedCount > 0) {
        if (writeDomainRules($data)) {
            $_SESSION['admin_message'] = "成功删除了 {$deletedCount} 条规则。";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['admin_message'] = "错误：批量删除失败，请检查文件权限。";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['admin_message'] = "没有选择任何规则进行删除。";
        $_SESSION['message_type'] = "info"; // 使用 info 类型表示非错误消息
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


// 处理登出请求
if (isset($_GET['logout'])) {
    session_destroy(); // 销毁所有会话数据
    header("Location: " . $_SERVER['PHP_SELF']); // 重定向回登录页面或当前页面
    exit();
}

// 获取并清除会话中存储的消息（用于显示给用户）
$adminMessage = $_SESSION['admin_message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['admin_message'], $_SESSION['message_type']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>域名跳转管理后台 (Domain Redirect Admin Panel)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            background-color: #f4f7f9;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 960px;
            margin: 30px auto;
            padding: 25px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        h2 {
            color: #0056b3;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        h3 {
            color: #0056b3;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.6em;
        }
        form {
            background: #fafbfc;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e0e6ea;
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        input[type=text],
        textarea {
            width: calc(100% - 22px); /* 减去内边距和边框 */
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #cce0f0;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input[type=text]:focus,
        textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }
        textarea {
            height: 120px;
            resize: vertical; /* 允许垂直拖拽调整大小 */
        }
        input[type=submit] {
            background-color: #007bff;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.05em;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        input[type=submit]:hover {
            background-color: #0056b3;
            transform: translateY(-1px); /* 鼠标悬停时上浮效果 */
        }
        table {
            width: 100%;
            border-collapse: separate; /* 使用 separate 和 border-spacing 来实现圆角表格边框 */
            border-spacing: 0;
            margin-top: 20px;
            border-radius: 8px;
            overflow: hidden; /* 隐藏超出圆角的部分 */
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        th, td {
            border: 1px solid #e0e6ea;
            padding: 15px;
            text-align: left;
        }
        th {
            background-color: #eef4f7;
            color: #333;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f8fbfd; /* 隔行换色 */
        }
        tr:hover {
            background-color: #e6f7ff; /* 行悬停效果 */
        }
        td a {
            color: #dc3545; /* 删除链接的颜色 */
            text-decoration: none;
            font-weight: bold;
        }
        td a:hover {
            text-decoration: underline;
        }
        /* 消息提示框样式 */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex; /* 使用 flex 布局对齐图标和文本 */
            align-items: center;
            font-weight: bold;
            border: 1px solid;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .message.info { /* 新增 info 样式 */
            background-color: #cfe2ff;
            color: #052c65;
            border-color: #b9d3ff;
        }
        .message i {
            margin-right: 10px;
            font-size: 1.2em;
        }
        .logout-button {
            background-color: #6c757d;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            float: right; /* 浮动到右侧 */
            margin-top: -50px; /* 根据需要调整位置 */
            transition: background-color 0.3s;
        }
        .logout-button:hover {
            background-color: #5a6268;
        }
        /* 批量删除按钮样式 */
        .batch-delete-btn {
            background-color: #dc3545; /* 红色 */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: 15px;
        }
        .batch-delete-btn:hover {
            background-color: #c82333;
        }
        /* 导出按钮样式 */
        .export-btn {
            background-color: #28a745; /* 绿色 */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: 15px;
            margin-right: 10px; /* 与批量删除按钮间隔 */
        }
        .export-btn:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
<div class="container">
    <button class="logout-button" onclick="location.href='?logout=true'"><i class="fas fa-sign-out-alt"></i> 退出登录 (Logout)</button>
    <h2>域名跳转管理 (Domain Redirect Management)</h2>

    <?php if (!empty($adminMessage)): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <i class="fas <?php echo ($messageType === 'success' ? 'fa-check-circle' : ($messageType === 'error' ? 'fa-times-circle' : 'fa-info-circle')); ?>"></i>
            <?php echo htmlspecialchars($adminMessage); ?>
        </div>
    <?php endif; ?>

    <h3><i class="fas fa-plus-circle"></i> 单条添加 (Add Single Rule)</h3>
    <form method='post'>
        <label for="old_domain">原域名 (Original Domain):</label>
        <input type='text' id="old_domain" name='old' placeholder='example.com' required>
        <label for="new_url">跳转地址 (Target URL):</label>
        <input type='text' id="new_url" name='new' placeholder='https://target-url.com' required>
        <input type='submit' name='add' value='添加规则 (Add Rule)'>
    </form>

    <h3><i class="fas fa-layer-group"></i> 批量添加/修改 (Batch Add/Update)</h3>
    <form method='post'>
        <p>每行一个，格式：<code>old.com https://new.com</code></p>
        <textarea name='batch_input' placeholder='old1.com https://new1.com
old2.com https://new2.com' required></textarea><br>
        <input type='submit' name='batch_add' value='批量提交 (Batch Submit)'>
    </form>

    <h3><i class="fas fa-list-alt"></i> 跳转规则列表 (Redirect Rules List)</h3>
    <form method="post" onsubmit="return confirm('确定要删除所有选中的规则吗？ (Are you sure you want to delete all selected rules?)');">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="select_all" onclick="toggleAllCheckboxes(this)"></th> <th>序号 (No.)</th> <th>原域名 (Original Domain)</th>
                    <th>跳转地址 (Target URL)</th>
                    <th>操作 (Action)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 20px;">暂无跳转规则 (No redirect rules found)</td></tr> <?php else: ?>
                    <?php $row_number = 1; ?> <?php foreach ($data as $k => $v): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_domains[]" value="<?php echo htmlspecialchars($k); ?>"></td>
                            <td><?php echo $row_number++; ?></td> <td><?php echo htmlspecialchars($k); ?></td>
                            <td><a href="<?php echo htmlspecialchars($v); ?>" target="_blank"><?php echo htmlspecialchars($v); ?></a></td>
                            <td><a href='?delete=<?php echo urlencode($k); ?>' onclick="return confirm('确定要删除这条规则吗？ (Are you sure you want to delete this rule?)');"><i class="fas fa-trash-alt"></i> 删除 (Delete)</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if (!empty($data)): ?>
            <a href="export.php" class="export-btn"><i class="fas fa-file-export"></i> 导出为TXT (Export to TXT)</a>
            <input type="submit" name="batch_delete" value="批量删除选中规则 (Batch Delete Selected Rules)" class="batch-delete-btn">
        <?php endif; ?>
    </form>

</div>

<script>
    // JavaScript 函数用于全选/取消全选复选框
    function toggleAllCheckboxes(source) {
        checkboxes = document.querySelectorAll('input[name="selected_domains[]"]');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }
</script>

</body>
</html>
