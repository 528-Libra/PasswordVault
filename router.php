<?php
/**
 * PHP内置服务器路由文件
 * 阻止敏感文件被直接下载
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 阻止数据库文件
if (preg_match('#/data/.*\.db$#i', $uri)) {
    http_response_code(403);
    exit('Access Forbidden');
}

// 阻止配置文件
if (preg_match('#/config\.php$#i', $uri)) {
    http_response_code(403);
    exit('Access Forbidden');
}

// 阻止调试/临时脚本
if (preg_match('#/(check_db|test_debug|migrate)\.php$#i', $uri)) {
    http_response_code(403);
    exit('Access Forbidden');
}

// 其他文件正常处理
return false;
