<?php
/**
 * 密码保管箱 - 配置文件（整合版）
 * @author 六斤Libra
 * @email 528.Libra@gmail.com
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Shanghai');

define('SITE_NAME', '密码保管箱');
define('APP_VERSION', '2.0');
define('APP_AUTHOR', '六斤Libra');
define('APP_EMAIL', '528.Libra@gmail.com');
define('ROOT_PATH', __DIR__);
define('DATA_DIR', ROOT_PATH . '/data/');
define('DB_PATH', DATA_DIR . 'passwords.db');

// CSRF防护
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}
function csrfCheck() {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// 暴力破解防护
function checkLoginAttempts() {
    $f = DATA_DIR . '.login_attempts';
    if (!file_exists($f)) return 0;
    $data = json_decode(file_get_contents($f), true);
    if (!$data) return 0;
    if ($data['count'] >= 5 && (time() - $data['last']) < 60) {
        return 60 - (time() - $data['last']);
    }
    if ($data['count'] >= 5 && (time() - $data['last']) >= 60) {
        @unlink($f); return 0;
    }
    return 0;
}
function recordLoginAttempt() {
    $f = DATA_DIR . '.login_attempts';
    $data = file_exists($f) ? json_decode(file_get_contents($f), true) : ['count'=>0,'last'=>0];
    $data['count']++;
    $data['last'] = time();
    file_put_contents($f, json_encode($data));
}
function clearLoginAttempts() {
    @unlink(DATA_DIR . '.login_attempts');
}

// 主密码密钥 - 从密钥文件读取，首次运行自动生成
define('KEY_FILE', DATA_DIR . '.master_key');
function getMasterKey() {
    static $key = null;
    if ($key !== null) return $key;
    if (file_exists(KEY_FILE)) {
        $key = trim(file_get_contents(KEY_FILE));
    } else {
        $key = bin2hex(random_bytes(32));
        file_put_contents(KEY_FILE, $key);
        if (PHP_OS_FAMILY === 'Windows') {
            exec('attrib +H ' . escapeshellarg(KEY_FILE));
        }
    }
    return $key;
}
// 密钥迁移
function migrateOldEncryptedData() {
    $oldKeyRaw = 'MySecureKey2024!@#';
    $db = getDB();
    $stmt = $db->query("SELECT id, password_enc FROM passwords");
    $migrated = 0;
    while ($row = $stmt->fetch()) {
        $raw = base64_decode($row['password_enc']);
        if (strlen($raw) < 32) continue;
        $iv = substr($raw, 0, 16);
        $encrypted = substr($raw, 16);
        $decrypted = @openssl_decrypt($encrypted, 'AES-256-CBC', $oldKeyRaw, 0, $iv);
        if ($decrypted !== false && $decrypted !== '') {
            $newEnc = encryptData($decrypted);
            $db->prepare("UPDATE passwords SET password_enc=? WHERE id=?")->execute([$newEnc, $row['id']]);
            $migrated++;
        }
    }
    return $migrated;
}
$migrateFlag = DATA_DIR . '.migrated_v2';
if (!file_exists($migrateFlag)) {
    $count = migrateOldEncryptedData();
    if ($count >= 0) {
        file_put_contents($migrateFlag, date('Y-m-d H:i:s') . " migrated $count records");
    }
}

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

// 加密函数
function encryptData($data) {
    $key = hash('sha256', getMasterKey(), true);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $iv . $encrypted, $key, true);
    return base64_encode($hmac . $iv . $encrypted);
}

// 解密函数
function decryptData($data) {
    $key = hash('sha256', getMasterKey(), true);
    $raw = base64_decode($data);
    if (strlen($raw) < 48) return false;
    $hmac = substr($raw, 0, 32);
    $iv = substr($raw, 32, 16);
    $encrypted = substr($raw, 48);
    $expectedHmac = hash_hmac('sha256', $iv . $encrypted, $key, true);
    if (!hash_equals($expectedHmac, $hmac)) return false;
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

// 数据库连接
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $db;
}

// 初始化数据库
function initDB() {
    $db = getDB();
    
    $db->exec("CREATE TABLE IF NOT EXISTS passwords (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        category TEXT DEFAULT '默认',
        username TEXT DEFAULT '',
        password_enc TEXT NOT NULL,
        url TEXT DEFAULT '',
        notes TEXT DEFAULT '',
        icon TEXT DEFAULT '🔑',
        color TEXT DEFAULT '#8b5cf6',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT DEFAULT '',
        category TEXT DEFAULT '日常',
        mood TEXT DEFAULT '📝',
        color TEXT DEFAULT '#8b5cf6',
        is_pinned INTEGER DEFAULT 0,
        views INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        icon TEXT DEFAULT '📁',
        color TEXT DEFAULT '#8b5cf6',
        type TEXT DEFAULT 'pwd',
        sort_order INTEGER DEFAULT 0
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");
    
    $defaultCategories = [
        ['默认', '📁', '#8b5cf6', 'pwd', 0],
        ['社交', '💬', '#06b6d4', 'pwd', 1],
        ['工作', '💼', '#f59e0b', 'pwd', 2],
        ['金融', '💰', '#10b981', 'pwd', 3],
        ['购物', '🛒', '#ec4899', 'pwd', 4],
        ['邮箱', '📧', '#3b82f6', 'pwd', 5],
        ['游戏', '🎮', '#ef4444', 'pwd', 6],
        ['其他', '📦', '#6b7280', 'pwd', 7],
        ['日常', '📝', '#8b5cf6', 'note', 10],
        ['心情', '💭', '#ec4899', 'note', 11],
        ['读书', '📚', '#06b6d4', 'note', 12],
        ['旅行', '✈️', '#10b981', 'note', 13],
        ['美食', '🍜', '#ef4444', 'note', 14],
        ['学习', '📖', '#3b82f6', 'note', 15],
        ['其他', '📦', '#6b7280', 'note', 16],
    ];
    
    $stmt = $db->query("SELECT COUNT(*) FROM categories");
    if ($stmt->fetchColumn() == 0) {
        $ins = $db->prepare("INSERT INTO categories (name, icon, color, type, sort_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($defaultCategories as $cat) $ins->execute($cat);
    }
}

initDB();

// 获取/设置系统设置
function getSetting($key, $default = '') {
    $stmt = getDB()->prepare("SELECT value FROM settings WHERE key=?");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v !== false ? $v : $default;
}
function setSetting($key, $value) {
    getDB()->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)")->execute([$key, $value]);
}

// 获取当前主题
function getCurrentTheme() {
    return getSetting('theme', 'deep-space');
}

// 修改主密码
function changeMasterPassword($oldPwd, $newPwd) {
    $db = getDB();
    $stmt = $db->query("SELECT value FROM settings WHERE key='master_hash'");
    $hash = $stmt->fetchColumn();
    if ($hash === false) return ['success' => false, 'msg' => '未设置主密码'];
    if (!password_verify($oldPwd, $hash)) return ['success' => false, 'msg' => '原密码错误'];
    if (strlen($newPwd) < 4) return ['success' => false, 'msg' => '新密码至少4位'];
    $newHash = password_hash($newPwd, PASSWORD_DEFAULT);
    $db->prepare("UPDATE settings SET value=? WHERE key='master_hash'")->execute([$newHash]);
    return ['success' => true, 'msg' => '密码修改成功'];
}

// 分类管理
function addCategory($name, $icon, $color, $type) {
    $db = getDB();
    $name = trim($name);
    if (empty($name)) return ['success' => false, 'msg' => '分类名不能为空'];
    $maxSort = $db->prepare("SELECT MAX(sort_order) FROM categories WHERE type=?");
    $maxSort->execute([$type]);
    $sort = ($maxSort->fetchColumn() ?: 0) + 1;
    try {
        $db->prepare("INSERT INTO categories (name, icon, color, type, sort_order) VALUES (?,?,?,?,?)")
           ->execute([$name, $icon, $color, $type, $sort]);
        return ['success' => true, 'msg' => '分类已添加'];
    } catch (Exception $e) {
        return ['success' => false, 'msg' => '分类名已存在'];
    }
}
function renameCategory($id, $newName) {
    $newName = trim($newName);
    if (empty($newName)) return ['success' => false, 'msg' => '名称不能为空'];
    $db = getDB();
    $stmt = $db->prepare("SELECT name, type FROM categories WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) return ['success' => false, 'msg' => '分类不存在'];
    try {
        $db->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$newName, $id]);
        // 同步更新关联记录的分类名
        $table = $old['type'] === 'pwd' ? 'passwords' : 'posts';
        $db->prepare("UPDATE $table SET category=? WHERE category=?")->execute([$newName, $old['name']]);
        return ['success' => true, 'msg' => '分类已重命名'];
    } catch (Exception $e) {
        return ['success' => false, 'msg' => '名称已存在'];
    }
}
function deleteCategory($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT name, type FROM categories WHERE id=?");
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    if (!$cat) return ['success' => false, 'msg' => '分类不存在'];
    // 该分类下的记录移到"默认"/"日常"
    $defaultCat = $cat['type'] === 'pwd' ? '默认' : '日常';
    $table = $cat['type'] === 'pwd' ? 'passwords' : 'posts';
    $db->prepare("UPDATE $table SET category=? WHERE category=?")->execute([$defaultCat, $cat['name']]);
    $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
    return ['success' => true, 'msg' => '分类已删除，关联记录已移至"' . $defaultCat . '"'];
}
function updateCategoryIcon($id, $icon, $color) {
    getDB()->prepare("UPDATE categories SET icon=?, color=? WHERE id=?")->execute([$icon, $color, $id]);
    return ['success' => true];
}

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function sanitizeHTML($html) {
    $allowed = '<p><br><b><i><u><s><strong><em><del><h1><h2><h3><ul><ol><li><blockquote><pre><code><a><img><hr><table><tr><th><td><span><div><sup><sub><thead><tbody>';
    $clean = strip_tags($html, $allowed);
    $clean = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $clean);
    $clean = preg_replace('/\s+on\w+\s*=\S+/i', '', $clean);
    $clean = preg_replace('/href\s*=\s*(["\'])(?:javascript|vbscript|data):.*?\1/i', 'href="$1#"', $clean);
    $clean = preg_replace('/src\s*=\s*(["\'])(?:javascript|vbscript|data):.*?\1/i', 'src="$1#"', $clean);
    $clean = preg_replace('/\s+style\s*=\s*(["\']).*?\1/i', '', $clean);
    return $clean;
}

function timeAgo($dt) {
    $d = (new DateTime())->diff(new DateTime($dt));
    if ($d->y > 0) return $d->y . '年前';
    if ($d->m > 0) return $d->m . '月前';
    if ($d->d > 0) return $d->d . '天前';
    if ($d->h > 0) return $d->h . '小时前';
    if ($d->i > 0) return $d->i . '分钟前';
    return '刚刚';
}

function getCategories($type = 'pwd') {
    $stmt = getDB()->prepare("SELECT * FROM categories WHERE type = ? ORDER BY sort_order");
    $stmt->execute([$type]);
    return $stmt->fetchAll();
}

function getCatStats($type = 'pwd') {
    $table = $type === 'pwd' ? 'passwords' : 'posts';
    $s = getDB()->query("SELECT category, COUNT(*) as cnt FROM $table GROUP BY category");
    $r = [];
    while ($row = $s->fetch()) $r[$row['category']] = $row['cnt'];
    return $r;
}

// 主题定义
function getThemes() {
    return [
        'deep-space' => [
            'name' => '深空紫',
            'desc' => '默认深色主题，紫色系',
            'vars' => [
                '--bg-primary' => '#0c0c18',
                '--bg-secondary' => '#13131f',
                '--bg-card' => '#1a1a2e',
                '--bg-elevated' => '#222244',
                '--bg-hover' => '#2a2a4a',
                '--text-primary' => '#f0eef6',
                '--text-secondary' => '#8b87a0',
                '--text-dim' => '#4a4760',
                '--accent' => '#8b5cf6',
                '--accent-light' => '#a78bfa',
                '--accent-glow' => 'rgba(139, 92, 246, 0.3)',
                '--orb1' => 'radial-gradient(circle, #8b5cf6 0%, transparent 70%)',
                '--orb2' => 'radial-gradient(circle, #06b6d4 0%, transparent 70%)',
                '--orb3' => 'radial-gradient(circle, #ec4899 0%, transparent 70%)',
                '--header-bg' => 'rgba(12, 12, 24, 0.85)',
            ]
        ],
        'ocean-blue' => [
            'name' => '深海蓝',
            'desc' => '海洋深蓝色调，宁静沉稳',
            'vars' => [
                '--bg-primary' => '#0a1628',
                '--bg-secondary' => '#0f1d33',
                '--bg-card' => '#132744',
                '--bg-elevated' => '#1a3358',
                '--bg-hover' => '#1f3d66',
                '--text-primary' => '#e8f0fe',
                '--text-secondary' => '#7a9cc6',
                '--text-dim' => '#3d5a80',
                '--accent' => '#2196f3',
                '--accent-light' => '#64b5f6',
                '--accent-glow' => 'rgba(33, 150, 243, 0.3)',
                '--orb1' => 'radial-gradient(circle, #2196f3 0%, transparent 70%)',
                '--orb2' => 'radial-gradient(circle, #00bcd4 0%, transparent 70%)',
                '--orb3' => 'radial-gradient(circle, #1565c0 0%, transparent 70%)',
                '--header-bg' => 'rgba(10, 22, 40, 0.85)',
            ]
        ],
        'emerald-green' => [
            'name' => '翡翠绿',
            'desc' => '自然翡翠绿，清新护眼',
            'vars' => [
                '--bg-primary' => '#0a1a14',
                '--bg-secondary' => '#0f2319',
                '--bg-card' => '#143025',
                '--bg-elevated' => '#1a3d2e',
                '--bg-hover' => '#204a37',
                '--text-primary' => '#e6f5ee',
                '--text-secondary' => '#7ac6a0',
                '--text-dim' => '#3d7a5a',
                '--accent' => '#10b981',
                '--accent-light' => '#34d399',
                '--accent-glow' => 'rgba(16, 185, 129, 0.3)',
                '--orb1' => 'radial-gradient(circle, #10b981 0%, transparent 70%)',
                '--orb2' => 'radial-gradient(circle, #06b6d4 0%, transparent 70%)',
                '--orb3' => 'radial-gradient(circle, #34d399 0%, transparent 70%)',
                '--header-bg' => 'rgba(10, 26, 20, 0.85)',
            ]
        ],
        'sunset-orange' => [
            'name' => '落日橘',
            'desc' => '温暖橘红色调，热情活力',
            'vars' => [
                '--bg-primary' => '#1a0f0a',
                '--bg-secondary' => '#231510',
                '--bg-card' => '#2e1c14',
                '--bg-elevated' => '#3d261c',
                '--bg-hover' => '#4d3024',
                '--text-primary' => '#fef0e6',
                '--text-secondary' => '#c69a78',
                '--text-dim' => '#7a5d45',
                '--accent' => '#f59e0b',
                '--accent-light' => '#fbbf24',
                '--accent-glow' => 'rgba(245, 158, 11, 0.3)',
                '--orb1' => 'radial-gradient(circle, #f59e0b 0%, transparent 70%)',
                '--orb2' => 'radial-gradient(circle, #ef4444 0%, transparent 70%)',
                '--orb3' => 'radial-gradient(circle, #f97316 0%, transparent 70%)',
                '--header-bg' => 'rgba(26, 15, 10, 0.85)',
            ]
        ],
        'rose-pink' => [
            'name' => '玫瑰粉',
            'desc' => '浪漫粉色系，柔美梦幻',
            'vars' => [
                '--bg-primary' => '#1a0a14',
                '--bg-secondary' => '#23101c',
                '--bg-card' => '#2e1628',
                '--bg-elevated' => '#3d1e36',
                '--bg-hover' => '#4d2644',
                '--text-primary' => '#fce8f3',
                '--text-secondary' => '#c67a9e',
                '--text-dim' => '#7a4a66',
                '--accent' => '#ec4899',
                '--accent-light' => '#f472b6',
                '--accent-glow' => 'rgba(236, 72, 153, 0.3)',
                '--orb1' => 'radial-gradient(circle, #ec4899 0%, transparent 70%)',
                '--orb2' => 'radial-gradient(circle, #a855f7 0%, transparent 70%)',
                '--orb3' => 'radial-gradient(circle, #f43f5e 0%, transparent 70%)',
                '--header-bg' => 'rgba(26, 10, 20, 0.85)',
            ]
        ],
        'light-mode' => [
            'name' => '晨光白',
            'desc' => '明亮浅色主题，清晰舒适',
            'vars' => [
                '--bg-primary' => '#f5f7fa',
                '--bg-secondary' => '#ffffff',
                '--bg-card' => '#ffffff',
                '--bg-elevated' => '#f0f2f5',
                '--bg-hover' => '#e8ecf1',
                '--text-primary' => '#1a1a2e',
                '--text-secondary' => '#64748b',
                '--text-dim' => '#94a3b8',
                '--accent' => '#6366f1',
                '--accent-light' => '#818cf8',
                '--accent-glow' => 'rgba(99, 102, 241, 0.2)',
                '--orb1' => 'radial-gradient(circle, rgba(99,102,241,0.3) 0%, transparent 70%)',
                '--orb2' => 'radial-gradient(circle, rgba(6,182,212,0.3) 0%, transparent 70%)',
                '--orb3' => 'radial-gradient(circle, rgba(236,72,153,0.2) 0%, transparent 70%)',
                '--header-bg' => 'rgba(255, 255, 255, 0.9)',
            ]
        ],
    ];
}

// Session超时检查
function checkSessionTimeout() {
    $timeout = 1800; // 30分钟
    if (isset($_SESSION['pwd_last_activity']) && (time() - $_SESSION['pwd_last_activity']) > $timeout) {
        session_destroy();
        return false;
    }
    $_SESSION['pwd_last_activity'] = time();
    return true;
}
