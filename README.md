301域名转发系统/无需数据库/json存储  账号密码：config.php文件  推荐：PHP7.4

展示图

登录页面 默认账号密码 账号：admin 密码：123456

<img width="331" height="376" alt="image" src="https://github.com/user-attachments/assets/855ef763-15fb-4f39-852f-18d4b5447042" />

后台页面  删除和导出TXT放在底部

<img width="702" height="935" alt="image" src="https://github.com/user-attachments/assets/9aee44b1-d9f1-448a-aa1c-3c5afc61d78a" />

跳转显示页面

<img width="775" height="494" alt="image" src="https://github.com/user-attachments/assets/18fcb82e-e6a5-43f7-b012-f62357d993fb" />

跳转倒计时 3秒

🚀 301 域名智能重定向与访客日志追踪系统一个基于 PHP + JSON + SQLite3 开发的轻量级、高性能多域名 301/302 跳转管理系统。无需繁琐的 MySQL 数据库支持，上传即可使用，完美适配宝塔面板及各类 Linux/Windows 主机环境。推荐运行环境：PHP 7.4。账号与密码统一配置在 config.php 文件中。🎨 界面展示与效果图1. 登录页面2. 后台管理页面（删除和导出 TXT 统一放置在表格底部）3. 安全跳转显示页面（3 秒倒计时与合规提示）📂 核心文件结构说明config.php：系统配置文件（包含 Base64 加密的管理员账号、密码及统一的跳转延迟时间）。index.php：前台重定向核心及安全认证引导页（负责访客流量无感探针采集与倒计时跳转）。check.php：域名跳转状态在线检测工具（实时自测各规则源域名与目标地址的响应状态）。export.php：后台规则一键导出为纯文本 TXT 工具。admin/index.php（或 index2.php）：多功能后台管理控制面板（集成数据大屏、日志追踪、规则增删改查）。🌟 系统核心功能特性1. 🛡️ 前台：安全认证与温馨提示页绿色合规引导：访客访问时展示“安全认证已通过”与“目标链接安全合规，请放心浏览”的绿色打钩提示，大幅提升访客信任度。平滑进度条动画：默认 3秒 动态倒计时跳转，支持中英文双语对照与“立即访问”手动直达按钮。   无感流量探针：在访客等待跳转的同时，自动记录其 IP、访问设备、系统及受访通道。2. 📊 后台：大屏流量统计多维核心指标：顶部直观展示 今日、昨日、前日 以及 近 3天、5天、7天 的总访问量（PV）与独立访客（UV）汇总。ECharts 统计图表：内置 近 7 天流量走势折线图 与 今日 24 小时访问分布柱状图，流量波动一眼掌握。   3. 🔍 后台：访客访问日志追踪多时间维度筛选：支持一键快速切换查看：今天、昨天、前天、近 3天、5天、7天、10天、15天 的访问明细。高级全量检索弹窗：支持按受访域名通道过滤、按 IP 地址模糊搜索，且拥有独立的无条数限制全屏弹窗面板。   智能设备识别：自动解析访客的操作系统（iOS、Android、Windows 等）及设备型号（iPhone、iPad、PC）。   全球 IP 地理定位：自动将访客 IP 转换为具体的省市区，并完美挂载对应的国家小国旗。   灵活日志清理：支持自由选择保留最近 1天 到 30 天的日志，一键手动清理冗余数据。   4. ⚙️ 后台：规则配置与安全加固Base64 双重加密：后台管理员账号与密码均采用 Base64 方式加密配置在 config.php 中，杜绝明文泄露风险。   6 位数字防刷验证码：登录后台需输入动态生成的 6 位纯数字干扰验证码，有效抵御自动化暴力破解。PRG 防重复提交机制：所有后台写操作（添加、删除、清理）执行后自动安全重定向，并伴随 3 秒自动消失的绿色成功提示条（Toast），彻底解决按 F5 刷新导致数据重复提交的 Bug。便捷管理：支持单条添加、空格格式批量导入、表格底部一键批量删除选中项以及一键导出为 TXT 文件。   🌐 宝塔面板 Nginx 服务器配置指南为了让系统能够完美处理所有泛解析域名和默认访问请求，请在宝塔面板对应网站的 配置文件 中使用以下优化配置：Nginxserver {
    listen 80 default_server; # 监听 80 端口，并将其设为默认服务器，用于处理所有未明确匹配的域名请求

    # server_name 定义了哪些域名或 IP 地址将由该服务器块处理。
    # 这里使用下划线 "_" 作为通配符，表示该服务器块会获取所有连接其他 server_name 显式匹配的请求。
    # 您只需在此处列出所有域名，只需确保它们都解析到您的 IP 即可。
    server_name 0.0.0.0 _; # 0.0.0.0 用于直接IP访问，"_" 用于接管所有其他泛解析域名

    root /www/wwwroot/你的网站目录; # 请修改为你的实际网站根目录
    index index.php index.html index.htm;

    # 引入宝塔默认配置
    include enable-php-74.conf; # 推荐 PHP 7.4

    # 伪静态及防重定向死循环基础设置
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 文件处理
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/tmp/php-cgi-74.sock; # 对应 PHP 7.4 套接字
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 禁止访问敏感数据目录（保护 json 规则与 sqlite 数据库安全）
    location ~ ^/data/ {
        deny all;
        return 403;
    }

    access_log /www/wwwlogs/domain_redirect.log;
    error_log /www/wwwlogs/domain_redirect_error.log;
}
