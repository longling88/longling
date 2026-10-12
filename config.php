<?php
// 后台登录配置 (账号与密码均已使用 Base64 编码)

// 账号已使用 Base64 编码 (原账号: Galaxyadmin)
// 如需修改账号，请使用 base64_encode('新账号') 生成替换
define("ADMIN_USER", "R2FsYXh5YWRtaW4=");

// 密码已使用 Base64 编码 (原密码: admin@123456)
// 如需修改密码，请使用 base64_encode('新密码') 生成替换
define("ADMIN_PASS", "YWRtaW5AMTIzNDU2");

// 跳转延迟秒数（统一）
define("REDIRECT_DELAY", 3);
?>
