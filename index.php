<?php
// 【备注：开启会话，用于记住用户的登录状态】
session_start();

// 【备注：引入上一层目录的 config.php，里面存了 Base64 加密过的账号和密码】
require_once "../config.php"; 

// 【备注：定义域名规则文件存放的位置】
define('DOMAINS_FILE', __DIR__ . '/../data/domains.json');

// 【备注：设置时间为中国标准时间，防止日志时间出错】
date_default_timezone_set('Asia/Shanghai');

// ==========================================
// 【备注：第一部分 - 登录校验系统】
// ==========================================
function handleLogin(): bool {
    // 【备注：如果之前已经登录过，直接放行（返回 true）】
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) return true;
    
    // 【备注：当用户点击了登录按钮（POST 请求）】
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // 【备注：检查验证码是否输入正确】
        if (!isset($_POST['captcha']) || !isset($_SESSION['captcha']) || $_POST['captcha'] !== $_SESSION['captcha']) {
            $_SESSION['login_error'] = "验证码错误，请重新输入";
        } 
        // 【备注：验证码正确后，将输入的账号密码与 config.php 中 Base64 解密后的真实账号密码进行对比】
        elseif (isset($_POST['username'], $_POST['password'])) {
            if ($_POST['username'] === base64_decode(ADMIN_USER) && $_POST['password'] === base64_decode(ADMIN_PASS)) {
                // 【备注：密码正确，记录登录状态并刷新页面】
                $_SESSION['logged_in'] = true;
                unset($_SESSION['captcha']); 
                session_regenerate_id(true);
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $_SESSION['login_error'] = "账号或密码错误";
            }
        }
    }
    return false;
}

// 【备注：如果没有登录，就显示这个登录页面】
if (!handleLogin()) {
    $error = $_SESSION['login_error'] ?? ''; 
    unset($_SESSION['login_error']);
    
    // 【备注：随机生成 6 位纯数字验证码】
    $captcha_code = (string)rand(100000, 999999);
    $_SESSION['captcha'] = $captcha_code;
    
    // 【备注：输出登录界面的 HTML 代码】
    echo '<!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="utf-8">
        <title>后台安全登录</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #eef4f7; margin: 0; }
            form { background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); width: 320px; }
            h2 { text-align: center; color: #0056b3; margin-bottom: 20px; }
            input { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 14px; }
            input:focus { border-color: #007bff; outline: none; }
            button { width: 100%; padding: 12px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
            .error { color: #dc3545; text-align: center; margin-top: 15px; font-weight: bold; }
            /* 【备注：验证码防机器刷的干扰样式】 */
            .captcha-box { background: #f4f7f9; border: 1px dashed #adb5bd; border-radius: 5px; padding: 0 15px; display: flex; align-items: center; justify-content: center; font-family: monospace; font-size: 22px; font-weight: 900; letter-spacing: 5px; text-decoration: line-through; cursor: pointer; user-select: none; }
        </style>
    </head>
    <body>
        <form method="post">
            <h2>后台安全登录</h2>
            <input type="text" name="username" placeholder="管理员账号" required>
            <input type="password" name="password" placeholder="管理员密码" required>
            <div style="display:flex; gap:10px; margin-bottom:15px;">
                <input type="text" name="captcha" placeholder="6位验证码" required maxlength="6" autocomplete="off" style="margin-bottom:0; flex:1;">
                <div class="captcha-box" onclick="location.reload()" title="点击刷新">' . $captcha_code . '</div>
            </div>
            <button type="submit">安全进入系统</button>
            '.($error ? "<div class='error'>$error</div>" : '').'
        </form>
    </body>
    </html>';
    exit();
}

// 【备注：如果点击了退出登录，销毁会话并刷新】
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ==========================================
// 【备注：第二部分 - 数据库连接与高级弹窗页面代码】
// ==========================================

// 【备注：连接 SQLite 日志数据库】
$dbPath = __DIR__ . '/../data/logs.db';
try {
    $db = new SQLite3($dbPath);
    $db->exec("CREATE TABLE IF NOT EXISTS visit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, host TEXT, ip TEXT, user_agent TEXT, visit_time DATETIME DEFAULT CURRENT_TIMESTAMP)");
} catch (Exception $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// ======================= 核心新增功能：独立弹窗页面 =======================
// 【备注：当浏览器请求带有 action=modal_logs 时，只渲染高级检索弹窗，不加载外面的大屏框架】
if (isset($_GET['action']) && $_GET['action'] === 'modal_logs') {
    
    // 【备注：处理弹窗内的“清理日志”请求】
    if (isset($_POST['clear_logs']) && isset($_POST['clear_days'])) {
        $days = intval($_POST['clear_days']);
        $db->exec("DELETE FROM visit_logs WHERE visit_time < datetime('now', '-$days days', 'localtime')");
        echo "<script>alert('冗余日志清理成功！已保留最近 {$days} 天。'); window.location.href=window.location.href;</script>";
        exit;
    }

    // 【备注：接收弹窗内的筛选条件（通道筛选、IP搜索、时间筛选）】
    $filter_channel = $_GET['search_channel'] ?? '';
    $filter_ip = trim($_GET['search_ip'] ?? '');
    $filter_time = $_GET['filter'] ?? 'all';

    // 【备注：动态拼接 SQL 查询条件】
    $whereSQL = "WHERE 1=1";
    if ($filter_channel !== '') $whereSQL .= " AND host = '" . SQLite3::escapeString($filter_channel) . "'";
    if ($filter_ip !== '') $whereSQL .= " AND ip LIKE '%" . SQLite3::escapeString($filter_ip) . "%'";
    
    // 【备注：按你要求的时间节点：今天, 昨天, 前天, 3天, 5天, 7天, 10天, 15天】
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $dayBefore = date('Y-m-d', strtotime('-2 days'));
    
    if ($filter_time === 'today') $whereSQL .= " AND date(visit_time) = '$today'";
    elseif ($filter_time === 'yesterday') $whereSQL .= " AND date(visit_time) = '$yesterday'";
    elseif ($filter_time === 'day_before') $whereSQL .= " AND date(visit_time) = '$dayBefore'";
    elseif ($filter_time === '3days') $whereSQL .= " AND visit_time >= datetime('now', '-3 days', 'localtime')";
    elseif ($filter_time === '5days') $whereSQL .= " AND visit_time >= datetime('now', '-5 days', 'localtime')";
    elseif ($filter_time === '7days') $whereSQL .= " AND visit_time >= datetime('now', '-7 days', 'localtime')";
    elseif ($filter_time === '10days') $whereSQL .= " AND visit_time >= datetime('now', '-10 days', 'localtime')";
    elseif ($filter_time === '15days') $whereSQL .= " AND visit_time >= datetime('now', '-15 days', 'localtime')";

    // 【备注：弹窗模式下，获取全部数据，不再限制 500 条】
    $logsQuery = $db->query("SELECT * FROM visit_logs $whereSQL ORDER BY visit_time DESC");

    // 【备注：获取所有存在的“受访通道(域名)”供下拉框筛选使用】
    $channels = [];
    $chQuery = $db->query("SELECT DISTINCT host FROM visit_logs");
    while($c = $chQuery->fetchArray(SQLITE3_ASSOC)) { $channels[] = $c['host']; }

    // 【备注：输出弹窗的纯净 HTML 页面（复刻你第二张图的样式）】
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="utf-8">
        <title>访客高级检索追踪</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
            body { font-family: sans-serif; background: #fff; padding: 20px; color: #333; margin:0; }
            /* 顶部蓝色提示条 */
            .alert-info { background: #e6f0ff; color: #004085; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #0056b3; }
            /* 黄色清理框 */
            .clear-box { background: #fff9e6; border: 1px solid #ffe58f; padding: 15px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            /* 高级筛选工具栏 */
            .search-toolbar { background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; display: flex; gap: 15px; align-items: center; margin-bottom: 20px; flex-wrap: wrap;}
            .search-toolbar select, .search-toolbar input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; outline: none; }
            .btn { padding: 8px 18px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; color: white; text-decoration: none; display: inline-block; }
            .btn-green { background: #28a745; } .btn-green:hover { background: #218838; }
            .btn-orange { background: #fa8c16; } .btn-orange:hover { background: #d46b08; }
            
            table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
            th, td { border-bottom: 1px solid #eee; padding: 15px; text-align: left; }
            th { background: #f8f9fa; color: #555; }
            tr:hover { background-color: #f1f8ff; }
            .tag-channel { background: #e6f0ff; color: #0066cc; padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 12px;}
            .ip-loading { color: #888; font-size: 12px; display: block; margin-top:4px; }
        </style>
    </head>
    <body>
        <h2><i class="fas fa-shoe-prints"></i> 访客访问日志高级追踪 (无条数限制)</h2>
        
        <div class="alert-info">
            <strong>提示：</strong> 此高级面板展示了您所选时间段内的 <b>全部点击日志</b>，不再受 500 条限制，方便您精确定位与溯源。
        </div>

        <!-- 【备注：黄色清理日志模块】 -->
        <div class="clear-box">
            <div><strong>手动清理冗余日志：</strong> <span style="color:#666; font-size:14px;">(系统会自动清除过早记录，您也可以在此手动精简)</span></div>
            <form method="post" style="margin:0; display:flex; gap:10px;">
                <select name="clear_days">
                    <option value="1">仅保留最近 1 天</option>
                    <option value="3">仅保留最近 3 天</option>
                    <option value="7" selected>仅保留最近 7 天</option>
                    <option value="15">仅保留最近 15 天</option>
                    <option value="30">仅保留最近 30 天</option>
                </select>
                <button type="submit" name="clear_logs" class="btn btn-orange" onclick="return confirm('确认清理之前的旧日志吗？');"><i class="fas fa-broom"></i> 确认清理</button>
            </form>
        </div>

        <!-- 【备注：筛选搜索模块 (通道下拉框 + IP输入框)】 -->
        <form method="get" class="search-toolbar">
            <!-- 【必须带上 action=modal_logs 和当前的时间 filter，保证搜索不出错】 -->
            <input type="hidden" name="action" value="modal_logs">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter_time); ?>">
            
            <strong><i class="fas fa-filter"></i> 筛选通道：</strong>
            <select name="search_channel">
                <option value="">-- 全部通道 --</option>
                <?php foreach($channels as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php if($filter_channel==$c) echo 'selected'; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>

            <strong><i class="fas fa-bullseye"></i> 搜索 IP：</strong>
            <input type="text" name="search_ip" value="<?php echo htmlspecialchars($filter_ip); ?>" placeholder="输入完整或部分IP...">
            
            <button type="submit" class="btn btn-green">查询</button>
            <a href="?action=modal_logs&filter=<?php echo htmlspecialchars($filter_time); ?>" class="btn" style="background:#6c757d;">重置筛选</a>
        </form>

        <!-- 【备注：数据显示表格】 -->
        <table>
            <thead>
                <tr>
                    <th>精确访问时间</th>
                    <th>访客 IP 地址 & 地理位置</th>
                    <th>访问设备 & 系统</th>
                    <th>受访通道代号</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hasData = false;
                while($row = $logsQuery->fetchArray(SQLITE3_ASSOC)):
                    $hasData = true;
                    // 解析设备UA (调用外面定义的函数)
                    $ua = $row['user_agent'];
                    $os = '未知系统'; $device = '未知设备'; $icon = 'fa-desktop';
                    if (preg_match('/iPhone/i', $ua)) { $os = 'iOS'; $device = 'iPhone'; $icon = 'fa-apple'; }
                    elseif (preg_match('/iPad/i', $ua)) { $os = 'iOS'; $device = 'iPad'; $icon = 'fa-tablet-alt'; }
                    elseif (preg_match('/Android/i', $ua)) { $os = 'Android'; $device = 'Android Phone'; $icon = 'fa-android'; }
                    elseif (preg_match('/Windows NT/i', $ua)) { $os = 'Windows'; $device = 'PC'; $icon = 'fa-windows'; }
                    elseif (preg_match('/Mac OS X/i', $ua)) { $os = 'macOS'; $device = 'Mac'; $icon = 'fa-apple'; }
                ?>
                <tr>
                    <td style="color:#666;"><i class="far fa-clock"></i> <?php echo $row['visit_time']; ?></td>
                    <td>
                        <strong><?php echo $row['ip']; ?></strong>
                        <span class="ip-loading" data-ip="<?php echo $row['ip']; ?>"><i class="fas fa-spinner fa-spin"></i> 定位中...</span>
                    </td>
                    <td>
                        <i class="fab <?php echo $icon; ?> fa-lg" style="color:#555; margin-right:5px;"></i> 
                        <strong><?php echo $device; ?></strong>
                        <div style="font-size:12px; color:#888; margin-top:2px;"><?php echo $os; ?></div>
                    </td>
                    <td><span class="tag-channel"><?php echo htmlspecialchars($row['host']); ?></span></td>
                </tr>
                <?php endwhile; if(!$hasData) echo "<tr><td colspan='4' style='text-align:center; padding:30px;'>没有搜索到符合条件的数据</td></tr>"; ?>
            </tbody>
        </table>

        <!-- 【备注：弹窗内部的 JS，用于异步获取国旗和 IP 归属地】 -->
        <script>
            document.querySelectorAll('.ip-loading').forEach(el => {
                let ip = el.dataset.ip;
                if(!ip || ip === 'Unknown' || ip === '127.0.0.1' || ip.startsWith('192.168.') || ip.startsWith('10.')) {
                    el.innerHTML = '<i class="fas fa-network-wired"></i> 局域网 / 本机地址'; return;
                }
                fetch(`http://ip-api.com/json/${ip}?lang=zh-CN`)
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 'success') {
                            let flag = `<img src="https://flagcdn.com/16x12/${data.countryCode.toLowerCase()}.png" style="vertical-align:middle; margin-right:5px; box-shadow: 0 0 1px rgba(0,0,0,0.3);">`;
                            let locationText = [data.country, data.regionName, data.city].filter(Boolean).join(' ');
                            el.innerHTML = `${flag} <span style="color:#1a73e8">${locationText}</span>`;
                        } else { el.innerHTML = '归属地未知'; }
                    }).catch(() => el.innerHTML = '定位超时');
            });
        </script>
    </body>
    </html>
    <?php
    exit(); // 【备注：如果是弹窗请求，输出完 HTML 就停止往下执行，不加载后面的大屏】
}
// ======================= 弹窗代码结束 =======================

// ==========================================
// 【备注：第三部分 - 正常后台界面逻辑 (大屏与规则管理)】
// ==========================================

// 【备注：用于接收操作成功的提示语 (Toast)】
$toastMessage = $_SESSION['toast_msg'] ?? '';
unset($_SESSION['toast_msg']);

// 【备注：大屏统计数据获取，定义时间变量】
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$dayBefore = date('Y-m-d', strtotime('-2 days'));

function getStats($db, $date) {
    if (!$db) return ['pv' => 0, 'uv' => 0];
    $res = $db->querySingle("SELECT COUNT(id) as pv, COUNT(DISTINCT ip) as uv FROM visit_logs WHERE date(visit_time) = '$date'", true);
    return ['pv' => $res['pv'] ?? 0, 'uv' => $res['uv'] ?? 0];
}
function getRangeStats($db, $days) {
    if (!$db) return ['pv' => 0, 'uv' => 0];
    $res = $db->querySingle("SELECT COUNT(id) as pv, COUNT(DISTINCT ip) as uv FROM visit_logs WHERE visit_time >= datetime('now', '-$days days', 'localtime')", true);
    return ['pv' => $res['pv'] ?? 0, 'uv' => $res['uv'] ?? 0];
}

// 【备注：大屏卡片的数据】
$stats = [
    'today' => getStats($db, $today),
    'yesterday' => getStats($db, $yesterday),
    'dayBefore' => getStats($db, $dayBefore),
    'last3' => getRangeStats($db, 3),
    'last5' => getRangeStats($db, 5),
    'last7' => getRangeStats($db, 7)
];

// 【备注：获取 7 天趋势 (用于给折线图提供坐标数据)】
$trend = ['dates' => [], 'pv' => [], 'uv' => []];
for($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $s = getStats($db, $d);
    $trend['dates'][] = date('m-d', strtotime($d));
    $trend['pv'][] = $s['pv'];
    $trend['uv'][] = $s['uv'];
}

// 【备注：获取今日 24 小时分布 (用于给柱状图提供数据)】
$hours = array_fill(0, 24, ['pv'=>0, 'uv'=>0]);
if (isset($db)) {
    $hq = $db->query("SELECT strftime('%H', visit_time) as hr, COUNT(id) as pv, COUNT(DISTINCT ip) as uv FROM visit_logs WHERE date(visit_time) = '$today' GROUP BY hr");
    if ($hq) {
        while($row = $hq->fetchArray(SQLITE3_ASSOC)) {
            $hours[(int)$row['hr']] = ['pv' => $row['pv'], 'uv' => $row['uv']];
        }
    }
}

// 【备注：解析 UA(User Agent) 的通用函数】
function parseUA($ua) {
    $os = '未知系统'; $device = '未知设备'; $icon = 'fa-desktop';
    if (preg_match('/iPhone/i', $ua)) { $os = 'iOS'; $device = 'iPhone'; $icon = 'fa-apple'; }
    elseif (preg_match('/iPad/i', $ua)) { $os = 'iOS'; $device = 'iPad'; $icon = 'fa-tablet-alt'; }
    elseif (preg_match('/Android/i', $ua)) { $os = 'Android'; $device = 'Android Phone'; $icon = 'fa-android'; }
    elseif (preg_match('/Windows NT/i', $ua)) { $os = 'Windows'; $device = 'PC'; $icon = 'fa-windows'; }
    elseif (preg_match('/Mac OS X/i', $ua)) { $os = 'macOS'; $device = 'Mac'; $icon = 'fa-apple'; }
    return ['os' => $os, 'device' => $device, 'icon' => $icon];
}

// 【备注：重定向规则读取与写入】
function readDomainRules(){ 
    $f = DOMAINS_FILE; 
    if(!file_exists($f)){ file_put_contents($f, json_encode([])); return []; } 
    return json_decode(file_get_contents($f), true) ?? []; 
}
function writeDomainRules($d){ return file_put_contents(DOMAINS_FILE, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) !== false; }

$rulesData = readDomainRules();

// 【备注：处理添加单条规则】
if(isset($_POST['add'])){
    $rulesData[strtolower(trim($_POST['old']))] = ['target'=>trim($_POST['new']), 'time'=>date('Y-m-d H:i:s')];
    writeDomainRules($rulesData); 
    $_SESSION['toast_msg'] = "成功添加单条跳转规则！";
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=rules"); exit;
}
// 【备注：处理批量添加规则】
if (isset($_POST['batch_add'])) {
    $lines = explode("\n", trim($_POST['batch_input'])); $count = 0;
    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', trim($line), 2);
        if (count($parts) === 2) {
            $old = filter_var(trim($parts[0]), FILTER_SANITIZE_URL); $new = filter_var(trim($parts[1]), FILTER_SANITIZE_URL);
            if (!empty($old) && !empty($new)) { $rulesData[strtolower($old)] = ['target' => $new, 'time' => date('Y-m-d H:i:s')]; $count++; }
        }
    }
    writeDomainRules($rulesData);
    $_SESSION['toast_msg'] = "成功批量添加了 {$count} 条规则！";
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=rules"); exit;
}
// 【备注：处理单条删除规则】
if(isset($_GET['delete'])){
    unset($rulesData[urldecode($_GET['delete'])]); writeDomainRules($rulesData);
    $_SESSION['toast_msg'] = "规则删除成功！"; header("Location: " . $_SERVER['PHP_SELF'] . "?tab=rules"); exit;
}
// 【备注：处理批量删除规则】
if (isset($_POST['batch_delete']) && isset($_POST['selected_domains']) && is_array($_POST['selected_domains'])) {
    $delCount = 0;
    foreach ($_POST['selected_domains'] as $domainToDelete) {
        $domainToDelete = filter_var(trim($domainToDelete), FILTER_SANITIZE_URL);
        if (!empty($domainToDelete) && isset($rulesData[$domainToDelete])) { unset($rulesData[$domainToDelete]); $delCount++; }
    }
    writeDomainRules($rulesData);
    $_SESSION['toast_msg'] = "成功删除了 {$delCount} 条规则！";
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=rules"); exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>域名跳转后台管理系统</title>
    <!-- 【备注：图标库】 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- 【备注：ECharts 图表库】 -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <style>
        body { font-family: -apple-system, sans-serif; background-color: #f4f7f9; margin: 0; color: #333; line-height: 1.6; }
        
        /* 导航栏样式 */
        .header { background: #fff; padding: 0 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10;}
        .tabs { display: flex; gap: 20px; }
        .tab { padding: 20px 10px; cursor: pointer; color: #555; font-weight: bold; border-bottom: 3px solid transparent; font-size: 16px; transition: color 0.3s, border-color 0.3s; }
        .tab:hover { color: #1a73e8; }
        .tab.active { color: #1a73e8; border-color: #1a73e8; }
        .logout-btn { color: #dc3545; text-decoration: none; font-weight: bold; padding: 8px 15px; border-radius: 5px; background: #fff1f0; transition: background 0.3s; }
        
        /* 悬浮提示框 (Toast) */
        .toast-notification { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background-color: #52c41a; color: white; padding: 12px 24px; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; display: flex; align-items: center; gap: 10px; font-weight: bold; animation: slideDown 0.3s ease-out forwards; }
        @keyframes slideDown { from { top: -50px; opacity: 0; } to { top: 20px; opacity: 1; } }
        
        /* 页面内容容器 */
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .panel { display: none; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); animation: fadeIn 0.3s ease-in-out; }
        .panel.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* 大屏指标卡片 */
        .card-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .card { border: 1px solid #eee; border-radius: 8px; padding: 18px; background: linear-gradient(to bottom, #ffffff, #fafafa); box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .card h4 { margin: 0 0 12px 0; color: #666; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .stat-item { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; align-items: center; }
        .stat-val { font-weight: bold; font-size: 18px; } .stat-val.pv { color: #007bff; } .stat-val.uv { color: #28a745; }
        
        /* 大屏图表区 */
        .chart-row { display: flex; gap: 20px; flex-wrap: wrap; }
        .chart-box { flex: 1; min-width: 400px; border: 1px solid #eee; border-radius: 8px; height: 380px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }

        /* 表格样式 */
        table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #eee; border-radius: 8px; overflow: hidden; margin-top: 10px; }
        th, td { border-bottom: 1px solid #eee; padding: 15px; text-align: left; font-size: 14px; }
        th { background: #f8f9fa; color: #555; font-weight: 600; }
        tr:nth-child(even) { background-color: #fdfdfd; } tr:hover { background-color: #f1f8ff; }
        
        /* 标签与按钮 */
        .tag-channel { background: #e6f0ff; color: #0066cc; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; display: inline-block; }
        .ip-loading { color: #888; font-size: 12px; margin-top: 5px; display: block; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; color: #fff; font-weight: bold; font-size: 14px; transition: opacity 0.3s; text-decoration: none; display: inline-block;}
        .btn-primary { background: #1a73e8; } .btn-light { background: #eef4f7; color: #555; border: 1px solid #cce0f0; }
        .btn-danger { background: #ff4d4f; } .btn-success { background: #52c41a; }
        
        /* 筛选按钮组 */
        .filter-group { display: flex; gap: 8px; margin-bottom: 15px; flex-wrap: wrap; align-items: center; }

        /* 弹窗遮罩层 */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; backdrop-filter: blur(3px); }
        /* 弹窗内容容器 */
        .modal-content { position: absolute; top: 5%; left: 5%; width: 90%; height: 90%; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; display: flex; flex-direction: column; }
        .modal-header { background: #f8f9fa; padding: 15px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; color: #333; }
        .close-btn { background: #ff4d4f; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 16px; font-weight: bold; display: flex; justify-content: center; align-items: center; }
        .close-btn:hover { background: #d9363e; }
        /* iframe填满弹窗 */
        #modalIframe { flex: 1; width: 100%; border: none; }
    </style>
</head>
<body>
    <!-- 【备注：弹窗容器 (默认隐藏)】 -->
    <div id="logModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-search-plus"></i> 高级检索面板 (当前时间段全量数据)</h3>
                <button class="close-btn" onclick="closeModal()" title="关闭弹窗">&times;</button>
            </div>
            <!-- 【备注：通过 iframe 嵌套 action=modal_logs 的页面，实现不刷新主界面的全屏弹窗】 -->
            <iframe id="modalIframe" src=""></iframe>
        </div>
    </div>

    <!-- 【备注：操作成功提示】 -->
    <?php if(!empty($toastMessage)): ?>
    <div class="toast-notification" id="toastMsg"><i class="fas fa-check-circle fa-lg"></i><span><?php echo htmlspecialchars($toastMessage); ?></span></div>
    <script>setTimeout(function() { document.getElementById('toastMsg').style.display = 'none'; }, 3000);</script>
    <?php endif; ?>

    <!-- 【备注：头部导航】 -->
    <div class="header">
        <div class="tabs">
            <div class="tab" onclick="switchTab('dashboard')" id="tab-dashboard"><i class="fas fa-chart-line"></i> 数据大屏浏览</div>
            <div class="tab" onclick="switchTab('logs')" id="tab-logs"><i class="fas fa-shoe-prints"></i> 访客访问日志追踪</div>
            <div class="tab" onclick="switchTab('rules')" id="tab-rules"><i class="fas fa-link"></i> 规则配置管理</div>
        </div>
        <a href="?logout=true" class="logout-btn"><i class="fas fa-sign-out-alt"></i> 退出系统</a>
    </div>

    <div class="container">
        <!-- ================= 【备注：面板1：数据大屏】 ================= -->
        <div id="panel-dashboard" class="panel">
            <div class="card-row">
                <div class="card">
                    <h4 style="color:#007bff;"><i class="far fa-calendar-alt"></i> 今日流量 (<?php echo $today; ?>)</h4>
                    <div class="stat-item"><span>总访问量 (PV)</span><span class="stat-val pv"><?php echo $stats['today']['pv']; ?></span></div>
                    <div class="stat-item"><span>独立访客 (UV)</span><span class="stat-val uv"><?php echo $stats['today']['uv']; ?></span></div>
                </div>
                <div class="card">
                    <h4 style="color:#6c757d;"><i class="far fa-calendar-minus"></i> 昨日流量 (<?php echo $yesterday; ?>)</h4>
                    <div class="stat-item"><span>总访问量 (PV)</span><span class="stat-val pv"><?php echo $stats['yesterday']['pv']; ?></span></div>
                    <div class="stat-item"><span>独立访客 (UV)</span><span class="stat-val uv"><?php echo $stats['yesterday']['uv']; ?></span></div>
                </div>
                <div class="card">
                    <h4 style="color:#6c757d;"><i class="far fa-calendar-minus"></i> 前日流量 (<?php echo $dayBefore; ?>)</h4>
                    <div class="stat-item"><span>总访问量 (PV)</span><span class="stat-val pv"><?php echo $stats['dayBefore']['pv']; ?></span></div>
                    <div class="stat-item"><span>独立访客 (UV)</span><span class="stat-val uv"><?php echo $stats['dayBefore']['uv']; ?></span></div>
                </div>
                <div class="card">
                    <h4 style="color:#fa8c16;"><i class="fas fa-chart-pie"></i> 近 3 天汇总</h4>
                    <div class="stat-item"><span>总访问量 (PV)</span><span class="stat-val pv"><?php echo $stats['last3']['pv']; ?></span></div>
                    <div class="stat-item"><span>独立访客 (UV)</span><span class="stat-val uv"><?php echo $stats['last3']['uv']; ?></span></div>
                </div>
                <div class="card">
                    <h4 style="color:#fa8c16;"><i class="fas fa-chart-pie"></i> 近 5 天汇总</h4>
                    <div class="stat-item"><span>总访问量 (PV)</span><span class="stat-val pv"><?php echo $stats['last5']['pv']; ?></span></div>
                    <div class="stat-item"><span>独立访客 (UV)</span><span class="stat-val uv"><?php echo $stats['last5']['uv']; ?></span></div>
                </div>
                <div class="card">
                    <h4 style="color:#fa8c16;"><i class="fas fa-chart-pie"></i> 近 7 天汇总</h4>
                    <div class="stat-item"><span>总访问量 (PV)</span><span class="stat-val pv"><?php echo $stats['last7']['pv']; ?></span></div>
                    <div class="stat-item"><span>独立访客 (UV)</span><span class="stat-val uv"><?php echo $stats['last7']['uv']; ?></span></div>
                </div>
            </div>
            
            <div class="chart-row">
                <div class="chart-box" id="chart-trend"></div>
                <div class="chart-box" id="chart-hour"></div>
            </div>
        </div>

        <!-- ================= 【备注：面板2：访客日志追踪主面板】 ================= -->
        <div id="panel-logs" class="panel">
            
            <?php $logFilter = $_GET['filter'] ?? 'all'; ?>
            
            <!-- 【备注：时间快捷筛选及弹窗呼出按钮】 -->
            <div class="filter-group">
                <strong style="color:#555; margin-right:10px;"><i class="fas fa-clock"></i> 时间范围：</strong>
                <a href="?tab=logs&filter=all" class="btn <?php echo $logFilter=='all'?'btn-primary':'btn-light'; ?>">近期日志</a>
                <!-- 【备注：按照要求加入 今天,昨天,前天,3天,5天,7天,10天,15天】 -->
                <a href="?tab=logs&filter=today" class="btn <?php echo $logFilter=='today'?'btn-primary':'btn-light'; ?>">今天 (<?php echo $today; ?>)</a>
                <a href="?tab=logs&filter=yesterday" class="btn <?php echo $logFilter=='yesterday'?'btn-primary':'btn-light'; ?>">昨天</a>
                <a href="?tab=logs&filter=day_before" class="btn <?php echo $logFilter=='day_before'?'btn-primary':'btn-light'; ?>">前天</a>
                <a href="?tab=logs&filter=3days" class="btn <?php echo $logFilter=='3days'?'btn-primary':'btn-light'; ?>">近 3 天</a>
                <a href="?tab=logs&filter=5days" class="btn <?php echo $logFilter=='5days'?'btn-primary':'btn-light'; ?>">近 5 天</a>
                <a href="?tab=logs&filter=7days" class="btn <?php echo $logFilter=='7days'?'btn-primary':'btn-light'; ?>">近 7 天</a>
                <a href="?tab=logs&filter=10days" class="btn <?php echo $logFilter=='10days'?'btn-primary':'btn-light'; ?>">近 10 天</a>
                <a href="?tab=logs&filter=15days" class="btn <?php echo $logFilter=='15days'?'btn-primary':'btn-light'; ?>">近 15 天</a>
                
                <!-- 【备注：核心功能：点击弹出完整数据页面 (通过 JS 调用 openModal)】 -->
                <button onclick="openModal('<?php echo htmlspecialchars($logFilter); ?>')" class="btn" style="background:#fa8c16; margin-left:auto;">
                    <i class="fas fa-external-link-alt"></i> 查看全部详细数据 (弹窗)
                </button>
            </div>

            <div style="background:#e6f0ff; color:#004085; padding:10px 15px; border-radius:5px; margin-bottom:15px; font-size:13px; border-left:3px solid #0056b3;">
                当前为主面板预览模式，最多展示 <strong>500</strong> 条记录。若需查看此时间段的 <strong>全部数据</strong> 及 <strong>通道/IP高级筛选</strong>，请点击上方橙色的【查看全部详细数据】按钮。
            </div>

            <!-- 【备注：主面板预览表格 (最多显示 500 条)】 -->
            <table>
                <thead>
                    <tr>
                        <th>精确访问时间</th>
                        <th>访客 IP 地址 & 地理位置</th>
                        <th>访问设备 & 系统</th>
                        <th>受访通道代号</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($db)) {
                        // 【备注：主页面判断选中的时间】
                        $whereClause = "";
                        if ($logFilter === 'today') $whereClause = "WHERE date(visit_time) = '$today'";
                        elseif ($logFilter === 'yesterday') $whereClause = "WHERE date(visit_time) = '$yesterday'";
                        elseif ($logFilter === 'day_before') $whereClause = "WHERE date(visit_time) = '$dayBefore'";
                        elseif ($logFilter === '3days') $whereClause = "WHERE visit_time >= datetime('now', '-3 days', 'localtime')";
                        elseif ($logFilter === '5days') $whereClause = "WHERE visit_time >= datetime('now', '-5 days', 'localtime')";
                        elseif ($logFilter === '7days') $whereClause = "WHERE visit_time >= datetime('now', '-7 days', 'localtime')";
                        elseif ($logFilter === '10days') $whereClause = "WHERE visit_time >= datetime('now', '-10 days', 'localtime')";
                        elseif ($logFilter === '15days') $whereClause = "WHERE visit_time >= datetime('now', '-15 days', 'localtime')";

                        // 【备注：主面板强制限制 500 条】
                        $logsQuery = $db->query("SELECT * FROM visit_logs $whereClause ORDER BY visit_time DESC LIMIT 500");
                        if ($logsQuery) {
                            $hasData = false;
                            while($row = $logsQuery->fetchArray(SQLITE3_ASSOC)):
                                $hasData = true;
                                $uaInfo = parseUA($row['user_agent']);
                    ?>
                    <tr>
                        <td style="color:#666; white-space: nowrap;"><i class="far fa-clock"></i> <?php echo $row['visit_time']; ?></td>
                        <td>
                            <strong><?php echo $row['ip']; ?></strong>
                            <span class="ip-loading" data-ip="<?php echo $row['ip']; ?>"><i class="fas fa-spinner fa-spin"></i> 定位中...</span>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap: 8px;">
                                <i class="fab <?php echo $uaInfo['icon']; ?> fa-lg" style="color:#555;"></i> 
                                <div><strong><?php echo $uaInfo['device']; ?></strong><div style="font-size:12px; color:#888;"><?php echo $uaInfo['os']; ?></div></div>
                            </div>
                        </td>
                        <td><span class="tag-channel"><?php echo htmlspecialchars($row['host']); ?></span></td>
                    </tr>
                    <?php endwhile; if(!$hasData) echo "<tr><td colspan='4' style='text-align:center;'>该时间段内暂无访问记录</td></tr>"; } } ?>
                </tbody>
            </table>
        </div>

        <!-- ================= 【备注：面板3：跳转规则管理】 ================= -->
        <div id="panel-rules" class="panel">
            <div style="background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:25px; border: 1px solid #e9ecef;">
                <h4 style="margin-top:0; color:#0056b3;"><i class="fas fa-plus-circle"></i> 单条添加规则</h4>
                <form method='post' style="display:flex; gap:15px;">
                    <input type='text' name='old' placeholder='原域名 (如 example.com)' required style="padding:10px; border:1px solid #ccc; border-radius:5px; flex:1;">
                    <input type='text' name='new' placeholder='跳转目标 (如 https://www.google.com)' required style="padding:10px; border:1px solid #ccc; border-radius:5px; flex:2;">
                    <button type='submit' name='add' class="btn btn-primary">添加规则</button>
                </form>
            </div>

            <div style="background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:25px; border: 1px solid #e9ecef;">
                 <h4 style="margin-top:0; color:#0056b3;"><i class="fas fa-layer-group"></i> 批量添加/修改</h4>
                 <form method='post'>
                    <p style="font-size:14px; color:#666; margin-top:0;">格式：<code>原域名 跳转地址</code> (每行一个，使用空格隔开)</p>
                    <textarea name='batch_input' style="width:100%; height:100px; padding:10px; border:1px solid #ccc; border-radius:5px;" required></textarea>
                    <div style="margin-top:10px;"><button type='submit' name='batch_add' class="btn btn-primary">批量提交</button></div>
                </form>
            </div>
            
            <h4 style="color:#0056b3;"><i class="fas fa-list-alt"></i> 跳转规则列表</h4>
            <form method="post" onsubmit="return confirm('确定要删除选中的规则吗？');">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" onclick="let cb=document.querySelectorAll('input[name=\'selected_domains[]\']'); for(let i=0;i<cb.length;i++) cb[i].checked=this.checked;"></th>
                            <th>原域名</th>
                            <th>跳转地址</th>
                            <th>操作时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rulesData)): ?><tr><td colspan="5" style="text-align: center;">暂无跳转规则</td></tr><?php else: ?>
                            <?php foreach ($rulesData as $k =>$v): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_domains[]" value="<?php echo htmlspecialchars($k); ?>"></td>
                                    <td><strong><?php echo htmlspecialchars($k); ?></strong></td>
                                    <td><a href="<?php echo htmlspecialchars($v['target']); ?>" target="_blank" style="color:#1a73e8;"><?php echo htmlspecialchars($v['target']); ?></a></td>
                                    <td style="color:#888; font-size:13px;"><i class="far fa-clock"></i> <?php echo htmlspecialchars($v['time'] ?? 'N/A'); ?></td>
                                    <td><a href='?delete=<?php echo urlencode($k); ?>' style="color:#ff4d4f; font-weight:bold; text-decoration:none;" onclick="return confirm('确定删除？');"><i class="fas fa-trash-alt"></i> 删除</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <?php if (!empty($rulesData)): ?>
                <div style="margin-top:15px; display:flex; gap:10px;">
                    <button type="submit" name="batch_delete" class="btn btn-danger">批量删除选中</button>
                    <a href="export.php" class="btn btn-success">导出为TXT</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        // 【备注：打开弹窗，并将 iframe 内部地址指向带 action=modal_logs 的独立查询页面】
        function openModal(filterStr) {
            document.getElementById('logModal').style.display = 'block';
            // 【把时间筛选条件传给弹窗，保证弹窗里的数据时间段一致】
            document.getElementById('modalIframe').src = "?action=modal_logs&filter=" + filterStr;
        }

        // 【备注：关闭弹窗】
        function closeModal() {
            document.getElementById('logModal').style.display = 'none';
            document.getElementById('modalIframe').src = ""; // 清空，释放内存
        }

        // 【备注：全局变量，防止图表重复初始化】
        let chartTrendInstance = null;
        let chartHourInstance = null;

        // 【备注：初始化并渲染 ECharts 图表，确保选项卡切换时宽度正常】
        function initCharts() {
            if (!chartTrendInstance) {
                let elTrend = document.getElementById('chart-trend');
                if(elTrend && elTrend.clientWidth > 0) {
                    chartTrendInstance = echarts.init(elTrend);
                    chartTrendInstance.setOption({
                        title: { text: '📈 近 7 天走势 (PV / UV)', textStyle: {fontSize: 16, color: '#333'} },
                        tooltip: { trigger: 'axis' },
                        legend: { data: ['访问量 (PV)', '独立访客 (UV)'], top: 25 },
                        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
                        xAxis: { type: 'category', boundaryGap: false, data: <?php echo json_encode($trend['dates']); ?> },
                        yAxis: { type: 'value' },
                        series: [
                            { name: '访问量 (PV)', type: 'line', smooth: true, areaStyle: { opacity: 0.1, color: '#007bff' }, itemStyle:{ color: '#007bff' }, data: <?php echo json_encode($trend['pv']); ?> },
                            { name: '独立访客 (UV)', type: 'line', smooth: true, areaStyle: { opacity: 0.1, color: '#20c997' }, itemStyle:{ color: '#20c997' }, data: <?php echo json_encode($trend['uv']); ?> }
                        ]
                    });
                }
            } else { chartTrendInstance.resize(); }

            if (!chartHourInstance) {
                let elHour = document.getElementById('chart-hour');
                if(elHour && elHour.clientWidth > 0) {
                    chartHourInstance = echarts.init(elHour);
                    chartHourInstance.setOption({
                        title: { text: '🕒 今日 24 小时分布', textStyle: {fontSize: 16, color: '#333'} },
                        tooltip: { trigger: 'axis', axisPointer: {type: 'shadow'} },
                        legend: { data: ['点击量 (PV)', '访客数 (UV)'], top: 25 },
                        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
                        xAxis: { type: 'category', data: Array.from({length:24}, (_,i) => (i<10?'0'+i:i)+':00'), axisLabel: { interval: 2, rotate: 45 } },
                        yAxis: { type: 'value' },
                        series: [
                            { name: '点击量 (PV)', type: 'bar', itemStyle:{ color: '#007bff', borderRadius: [4, 4, 0, 0] }, data: <?php echo json_encode(array_column($hours, 'pv')); ?> },
                            { name: '访客数 (UV)', type: 'bar', itemStyle:{ color: '#20c997', borderRadius: [4, 4, 0, 0] }, data: <?php echo json_encode(array_column($hours, 'uv')); ?> }
                        ]
                    });
                }
            } else { chartHourInstance.resize(); }
        }

        // 【备注：切换大屏、日志、规则管理的选项卡】
        function switchTab(tabId) {
            document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById('panel-' + tabId).classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');
            
            // 【备注：加入极短时间的延迟（50毫秒），确保包含图表的网页区块先显示出来，然后再画图，解决图表消失的BUG】
            if(tabId === 'dashboard') { setTimeout(() => { initCharts(); }, 50); }
            
            // 改变网址栏的参数记录当前所在页面
            let newUrl = '?tab=' + tabId;
            const urlParams = new URLSearchParams(window.location.search);
            if (tabId === 'logs' && urlParams.has('filter')) { newUrl += '&filter=' + urlParams.get('filter'); }
            history.pushState(null, null, newUrl);
        }

        // 初始化网页加载时显示的标签页
        const urlParams = new URLSearchParams(window.location.search);
        switchTab(urlParams.get('tab') || 'dashboard');

        // 窗口拉伸时自适应图表大小
        window.onresize = function() { if(chartTrendInstance) chartTrendInstance.resize(); if(chartHourInstance) chartHourInstance.resize(); };

        // 【备注：这段代码负责在主面板中拉取 IP 对应的真实归属地(省市区)，并在旁边加个国旗图片】
        document.querySelectorAll('.ip-loading').forEach(el => {
            let ip = el.dataset.ip;
            if(!ip || ip === 'Unknown' || ip === '127.0.0.1' || ip.startsWith('192.168.') || ip.startsWith('10.')) {
                el.innerHTML = '<i class="fas fa-network-wired"></i> 局域网 / 本机地址'; return;
            }
            fetch(`http://ip-api.com/json/${ip}?lang=zh-CN`)
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        let flag = `<img src="https://flagcdn.com/16x12/${data.countryCode.toLowerCase()}.png" style="vertical-align:middle; margin-right:5px; box-shadow: 0 0 1px rgba(0,0,0,0.3);">`;
                        let locationText = [data.country, data.regionName, data.city].filter(Boolean).join(' ');
                        el.innerHTML = `${flag} <span style="color:#1a73e8">${locationText}</span>`;
                    } else { el.innerHTML = '归属地未知'; }
                }).catch(() => el.innerHTML = '定位超时');
        });
    </script>
</body>
</html>
