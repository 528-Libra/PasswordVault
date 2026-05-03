<?php
/**
 * 图片上传接口
 * 支持：点击上传、拖拽上传、粘贴上传
 */
require_once 'config.php';
session_start();
if (empty($_SESSION['pwd_logged_in'])) { http_response_code(403); echo json_encode(['error'=>'未登录']); exit; }

header('Content-Type: application/json; charset=utf-8');

$uploadDir = ROOT_PATH . '/uploads/';
$uploadUrl = 'uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// 允许的类型
$allowedTypes = ['image/jpeg','image/png','image/gif','image/webp','image/bmp','image/svg+xml'];
$maxSize = 10 * 1024 * 1024; // 10MB

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('仅支持POST');

    $file = $_FILES['image'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $err = $file ? $file['error'] : 'no file';
        throw new Exception('上传失败: ' . $err);
    }
    if ($file['size'] > $maxSize) throw new Exception('文件过大（最大10MB）');
    
    // 验证MIME
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedTypes)) throw new Exception('不支持的格式: ' . $mime);

    // 生成文件名 - 扩展名白名单
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extMap = ['jpg'=>'jpg','jpeg'=>'jpg','png'=>'png','gif'=>'gif','webp'=>'webp','bmp'=>'bmp'];
    // SVG允许但会强制改名，因为可能含JS
    if ($mime === 'image/svg+xml') {
        $ext = 'svg.txt'; // SVG重命名为.txt防止浏览器直接执行JS
    } elseif (!isset($extMap[$ext])) {
        $ext = 'jpg'; // 未知扩展名默认jpg
    }
    $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('保存失败');
    }

    echo json_encode([
        'success' => true,
        'url' => $uploadUrl . $safeName,
        'name' => $file['name'],
        'size' => $file['size']
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
