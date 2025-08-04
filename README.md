301域名转发系统/无需数据库/json存储  推荐：PHP7.4

展示图

登录页面

<img width="331" height="376" alt="image" src="https://github.com/user-attachments/assets/855ef763-15fb-4f39-852f-18d4b5447042" />

后台页面

<img width="702" height="935" alt="image" src="https://github.com/user-attachments/assets/9aee44b1-d9f1-448a-aa1c-3c5afc61d78a" />

跳转显示页面

<img width="775" height="494" alt="image" src="https://github.com/user-attachments/assets/18fcb82e-e6a5-43f7-b012-f62357d993fb" />

跳转倒计时 3秒

宝塔 nginx配置

Nginx 服务器配置示例
此配置允许通过 IP 地址和任何解析到此 IP 的域名访问您的网站
服务器 {
监听 80 default_server; # 监听 80 端口，将其默认设为服务器，用于处理所有未明确匹配的域名请求
#server_name 定义了哪些域名或 IP 地址将由该服务器块处理。
#这里使用下划线 "_" 作为通配符，表示该服务器块会获取所有连接其他 server_name 显式匹配的请求。
#您只需在此处列出所有域名，只需确保它们都解析到您的 IP 即可。
server_name 0.0.0.0 ; # 45.204.23.87用于直接IP访问，" "用于夺取所有其他域名
