<?php
/**
 * 密码保管箱 + 记事本 - 主页面
 * @author 六斤Libra
 * @email 528.Libra@gmail.com
 */
require_once 'config.php';
session_start();

// Session超时检查
if (!empty($_SESSION['pwd_logged_in'])) {
    if (!checkSessionTimeout()) {
        header('Location: index.php'); exit;
    }
}

$isLoggedIn = isset($_SESSION['pwd_logged_in']) && $_SESSION['pwd_logged_in'] === true;
$message = '';
$messageType = '';

// ===== POST处理 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $db = getDB();
    
    // 登录
    if ($action === 'login') {
        $wait = checkLoginAttempts();
        if ($wait > 0) {
            $message = "尝试过多，请{$wait}秒后再试"; $messageType = 'error';
        } else {
        $pwd = $_POST['password'] ?? '';
        $stmt = $db->query("SELECT value FROM settings WHERE key='master_hash'");
        $hash = $stmt->fetchColumn();
        if ($hash === false) {
            if (strlen($pwd) >= 4) {
                $db->prepare("INSERT INTO settings (key,value) VALUES ('master_hash',?)")->execute([password_hash($pwd, PASSWORD_DEFAULT)]);
                clearLoginAttempts();
                $_SESSION['pwd_logged_in'] = true;
                $_SESSION['pwd_last_activity'] = time();
                header('Location: index.php'); exit;
            }
            $message = '密码至少4位'; $messageType = 'error';
        } else {
            if (password_verify($pwd, $hash)) {
                session_regenerate_id(true);
                clearLoginAttempts();
                $_SESSION['pwd_logged_in'] = true;
                $_SESSION['pwd_last_activity'] = time();
                header('Location: index.php'); exit;
            }
            recordLoginAttempt();
            $message = '密码错误'; $messageType = 'error';
        }
        }
    }
    
    if ($isLoggedIn) {
        // CSRF验证
        if (!csrfCheck()) {
            $message = '安全验证失败，请重试'; $messageType = 'error';
        } else {
        // --- 密码模块 ---
        if ($action === 'add') {
            $title = trim($_POST['title'] ?? '');
            $rawPwd = $_POST['pwd'] ?? '';
            if (empty($title) || empty($rawPwd)) {
                $message = '名称和密码必填'; $messageType = 'error';
            } else {
                $enc = encryptData($rawPwd);
                $db->prepare("INSERT INTO passwords (title,category,username,password_enc,url,notes,icon,color) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$title, $_POST['category']??'默认', trim($_POST['username']??''), $enc, trim($_POST['url']??''), trim($_POST['notes']??''), $_POST['icon']??'🔑', $_POST['color']??'#8b5cf6']);
                $message = '密码已保存'; $messageType = 'success';
            }
        }
        if ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            if (empty($title)) { $message = '名称必填'; $messageType = 'error'; }
            else {
                $newPwd = $_POST['pwd'] ?? '';
                if ($newPwd) {
                    $enc = encryptData($newPwd);
                    $db->prepare("UPDATE passwords SET title=?,category=?,username=?,password_enc=?,url=?,notes=?,icon=?,color=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                       ->execute([$title, $_POST['category']??'默认', trim($_POST['username']??''), $enc, trim($_POST['url']??''), trim($_POST['notes']??''), $_POST['icon']??'🔑', $_POST['color']??'#8b5cf6', $id]);
                } else {
                    $db->prepare("UPDATE passwords SET title=?,category=?,username=?,url=?,notes=?,icon=?,color=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                       ->execute([$title, $_POST['category']??'默认', trim($_POST['username']??''), trim($_POST['url']??''), trim($_POST['notes']??''), $_POST['icon']??'🔑', $_POST['color']??'#8b5cf6', $id]);
                }
                $message = '已更新'; $messageType = 'success';
            }
        }
        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            $module = $_POST['module'] ?? 'pwd';
            $table = $module === 'note' ? 'posts' : 'passwords';
            $db->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
            $message = '已删除'; $messageType = 'success';
        }
        
        // --- 记事本模块 ---
        if ($action === 'add_note') {
            $title = trim($_POST['title'] ?? '');
            if (empty($title)) { $message = '标题必填'; $messageType = 'error'; }
            else {
                $content = sanitizeHTML($_POST['content'] ?? '');
                $db->prepare("INSERT INTO posts (title,content,category,mood,color) VALUES (?,?,?,?,?)")
                   ->execute([$title, $content, $_POST['category']??'日常', $_POST['mood']??'📝', $_POST['color']??'#8b5cf6']);
                $message = '记事已保存'; $messageType = 'success';
            }
        }
        if ($action === 'edit_note') {
            $id = intval($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            if (empty($title)) { $message = '标题必填'; $messageType = 'error'; }
            else {
                $content = sanitizeHTML($_POST['content'] ?? '');
                $db->prepare("UPDATE posts SET title=?,content=?,category=?,mood=?,color=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                   ->execute([$title, $content, $_POST['category']??'日常', $_POST['mood']??'📝', $_POST['color']??'#8b5cf6', $id]);
                $message = '已更新'; $messageType = 'success';
            }
        }
        if ($action === 'pin_note') {
            $id = intval($_POST['id'] ?? 0);
            $db->prepare("UPDATE posts SET is_pinned = CASE WHEN is_pinned=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
            $message = '已更新'; $messageType = 'success';
        }
        
        // --- 分类管理 ---
        if ($action === 'add_category') {
            $name = trim($_POST['cat_name'] ?? '');
            $type = $_POST['cat_type'] ?? 'pwd';
            if ($name) {
                $result = addCategory($name, $_POST['cat_icon']??'📁', $_POST['cat_color']??'#8b5cf6', $type);
                $message = $result['msg']; $messageType = $result['success'] ? 'success' : 'error';
            }
        }
        if ($action === 'rename_category') {
            $id = intval($_POST['cat_id'] ?? 0);
            $newName = trim($_POST['cat_new_name'] ?? '');
            if ($id && $newName) {
                $result = renameCategory($id, $newName);
                $message = $result['msg']; $messageType = $result['success'] ? 'success' : 'error';
            }
        }
        if ($action === 'delete_category') {
            $id = intval($_POST['cat_id'] ?? 0);
            if ($id) {
                $result = deleteCategory($id);
                $message = $result['msg']; $messageType = $result['success'] ? 'success' : 'error';
            }
        }
        if ($action === 'update_category') {
            $id = intval($_POST['cat_id'] ?? 0);
            if ($id) {
                updateCategoryIcon($id, $_POST['cat_icon']??'📁', $_POST['cat_color']??'#8b5cf6');
                $message = '分类已更新'; $messageType = 'success';
            }
        }
        
        // --- 修改主密码 ---
        if ($action === 'change_password') {
            $oldPwd = $_POST['old_password'] ?? '';
            $newPwd = $_POST['new_password'] ?? '';
            $confirmPwd = $_POST['confirm_password'] ?? '';
            if (empty($oldPwd) || empty($newPwd) || empty($confirmPwd)) {
                $message = '所有字段必填'; $messageType = 'error';
            } elseif ($newPwd !== $confirmPwd) {
                $message = '两次新密码不一致'; $messageType = 'error';
            } else {
                $result = changeMasterPassword($oldPwd, $newPwd);
                $message = $result['msg']; $messageType = $result['success'] ? 'success' : 'error';
            }
        }

        // --- 主题切换 ---
        if ($action === 'change_theme') {
            $theme = $_POST['theme'] ?? 'deep-space';
            $themes = getThemes();
            if (isset($themes[$theme])) {
                setSetting('theme', $theme);
                $message = '主题已切换'; $messageType = 'success';
            } else {
                $message = '无效主题'; $messageType = 'error';
            }
        }
        } // end CSRF else
    }
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

// AJAX：获取密码明文
if ($isLoggedIn && isset($_GET['reveal']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $stmt = getDB()->prepare("SELECT password_enc FROM passwords WHERE id=?");
    $stmt->execute([intval($_GET['id'])]);
    $enc = $stmt->fetchColumn();
    echo json_encode(['success' => (bool)$enc, 'password' => $enc ? decryptData($enc) : '']);
    exit;
}

// AJAX：增加记事浏览次数
if ($isLoggedIn && isset($_GET['ajax']) && $_GET['ajax'] === 'view_note' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);
    getDB()->exec("UPDATE posts SET views = views + 1 WHERE id = $id");
    $stmt = getDB()->prepare("SELECT views FROM posts WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'views' => (int)$stmt->fetchColumn()]);
    exit;
}

// AJAX：获取分类列表
if ($isLoggedIn && isset($_GET['ajax']) && $_GET['ajax'] === 'categories') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? 'pwd';
    echo json_encode(getCategories($type));
    exit;
}

// 当前模块
$module = $_GET['m'] ?? 'pwd';
if (!in_array($module, ['pwd', 'note'])) $module = 'pwd';

// 当前主题
$currentTheme = getCurrentTheme();
$themes = getThemes();
$themeVars = $themes[$currentTheme]['vars'] ?? $themes['deep-space']['vars'];

// ===== 登录页 =====
if (!$isLoggedIn):
    $hasPwd = getDB()->query("SELECT value FROM settings WHERE key='master_hash'")->fetchColumn() !== false;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
:root {
<?php foreach ($themeVars as $k => $v): echo "$k: $v;\n"; endforeach; ?>
}
</style>
</head>
<body class="login-body">
<div class="bg-orb"></div><div class="bg-orb"></div><div class="bg-orb"></div>
<div class="login-container"><div class="login-card">
<div class="login-icon">🔐</div>
<h1><?= e(SITE_NAME) ?></h1>
<p class="login-subtitle"><?= $hasPwd ? '请输入主密码解锁' : '首次使用，请设置主密码' ?></p>
<?php if ($message): ?><div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div><?php endif; ?>
<form method="POST">
<input type="hidden" name="action" value="login">
<input type="password" name="password" class="login-input" placeholder="<?= $hasPwd ? '输入主密码' : '设置主密码（至少4位）' ?>" required autofocus>
<button type="submit" class="btn btn-primary btn-block"><?= $hasPwd ? '🔓 解锁' : '🔐 设置主密码' ?></button>
</form>
<div class="login-footer">v<?= APP_VERSION ?> · by <?= APP_AUTHOR ?></div>
</div></div>
</body></html>
<?php exit; endif;

// ===== 主界面 =====
$db = getDB();
$search = trim($_GET['s'] ?? '');
$cat = trim($_GET['c'] ?? '');

// 密码数据
$pwdCategories = getCategories('pwd');
$pwdCatStats = getCatStats('pwd');
$pwdTotal = $db->query("SELECT COUNT(*) FROM passwords")->fetchColumn();
$pwdWhere = "1=1"; $pwdParams = [];
if ($search && $module === 'pwd') { $pwdWhere .= " AND (title LIKE ? OR username LIKE ? OR url LIKE ?)"; $p = "%$search%"; $pwdParams = [$p,$p,$p]; }
if ($cat && $module === 'pwd') { $pwdWhere .= " AND category=?"; $pwdParams[] = $cat; }
$pwdStmt = $db->prepare("SELECT id,title,category,username,url,notes,icon,color,created_at,updated_at FROM passwords WHERE $pwdWhere ORDER BY updated_at DESC");
$pwdStmt->execute($pwdParams);
$passwords = $pwdStmt->fetchAll();

// 记事本数据
$noteCategories = getCategories('note');
$noteTotal = $db->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$noteWhere = "1=1"; $noteParams = [];
if ($search && $module === 'note') { $noteWhere .= " AND (title LIKE ? OR content LIKE ?)"; $p = "%$search%"; $noteParams = [$p,$p]; }
if ($cat && $module === 'note') { $noteWhere .= " AND category=?"; $noteParams[] = $cat; }
$noteStmt = $db->prepare("SELECT * FROM posts WHERE $noteWhere ORDER BY is_pinned DESC, updated_at DESC");
$noteStmt->execute($noteParams);
$posts = $noteStmt->fetchAll();

$catColorPwd = []; foreach ($pwdCategories as $c) $catColorPwd[$c['name']] = $c['color'];
$catColorNote = []; foreach ($noteCategories as $c) $catColorNote[$c['name']] = $c['color'];

// 分类管理数据
$manageCats = $module === 'pwd' ? $pwdCategories : $noteCategories;
$catType = $module === 'pwd' ? 'pwd' : 'note';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
:root {
<?php foreach ($themeVars as $k => $v): echo "$k: $v;\n"; endforeach; ?>
}
</style>
</head>
<body>
<div class="bg-orb"></div><div class="bg-orb"></div><div class="bg-orb"></div>

<header class="header">
<div class="header-left">
<span class="logo-icon">🔐</span><span class="logo-text"><?= e(SITE_NAME) ?></span>
<div class="module-tabs">
<a href="?m=pwd" class="module-tab <?= $module==='pwd'?'active':'' ?>">🔑 密码</a>
<a href="?m=note" class="module-tab <?= $module==='note'?'active':'' ?>">📝 记事本</a>
</div>
</div>
<div class="header-center">
<form class="search-form" method="GET">
<input type="hidden" name="m" value="<?= e($module) ?>">
<input type="text" name="s" class="search-input" placeholder="搜索..." value="<?= e($search) ?>">
<?php if ($cat): ?><input type="hidden" name="c" value="<?= e($cat) ?>"><?php endif; ?>
<button type="submit" class="search-btn">🔍</button>
</form>
</div>
<div class="header-right">
<?php if ($module === 'pwd'): ?>
<button class="btn btn-primary" onclick="showAddPwdModal()">➕ 添加密码</button>
<?php else: ?>
<button class="btn btn-primary" onclick="showAddNoteModal()">✏️ 写记事</button>
<?php endif; ?>
<button class="btn btn-ghost" onclick="showSettingsModal()" title="设置">⚙️</button>
<a href="?logout=1" class="btn btn-ghost">🚪 退出</a>
</div>
</header>

<aside class="sidebar">
<?php if ($module === 'pwd'): ?>
<div class="sidebar-section">
<div class="sidebar-title">概览</div>
<a href="?m=pwd" class="sidebar-item <?= !$cat?'active':'' ?>">
<span class="item-icon">📋</span><span class="item-text">全部密码</span><span class="item-badge"><?= $pwdTotal ?></span>
</a>
</div>
<div class="sidebar-section">
<div class="sidebar-title">分类 <button class="sidebar-action-btn" onclick="showCategoryModal('pwd')" title="管理分类">⚙️</button></div>
<?php foreach ($pwdCategories as $c): ?>
<a href="?m=pwd&c=<?= e($c['name']) ?>" class="sidebar-item <?= $cat===$c['name']?'active':'' ?>">
<span class="item-icon" style="background:<?= e($c['color']) ?>20;color:<?= e($c['color']) ?>"><?= e($c['icon']) ?></span>
<span class="item-text"><?= e($c['name']) ?></span>
<?php if (isset($pwdCatStats[$c['name']])): ?><span class="item-badge"><?= $pwdCatStats[$c['name']] ?></span><?php endif; ?>
</a>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="sidebar-section">
<div class="sidebar-title">概览</div>
<a href="?m=note" class="sidebar-item <?= !$cat?'active':'' ?>">
<span class="item-icon">📋</span><span class="item-text">全部记事</span><span class="item-badge"><?= $noteTotal ?></span>
</a>
</div>
<div class="sidebar-section">
<div class="sidebar-title">分类 <button class="sidebar-action-btn" onclick="showCategoryModal('note')" title="管理分类">⚙️</button></div>
<?php foreach ($noteCategories as $c): ?>
<a href="?m=note&c=<?= e($c['name']) ?>" class="sidebar-item <?= $cat===$c['name']?'active':'' ?>">
<span class="item-icon" style="background:<?= e($c['color']) ?>20;color:<?= e($c['color']) ?>"><?= e($c['icon']) ?></span>
<span class="item-text"><?= e($c['name']) ?></span>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</aside>

<main class="main-content">
<?php if ($message): ?><div class="alert alert-<?= $messageType ?> fade-in"><?= $messageType==='success'?'✅':'❌' ?> <?= e($message) ?></div><?php endif; ?>
<?php if ($search || $cat): ?>
<div class="filter-bar">
<span>找到 <?= $module==='pwd'?count($passwords):count($posts) ?> 条</span>
<a href="?m=<?= e($module) ?>" class="btn btn-sm">清除</a>
</div>
<?php endif; ?>

<?php if ($module === 'pwd'): ?>
<!-- ====== 密码模块 ====== -->
<?php if (empty($passwords)): ?>
<div class="empty-state"><div class="empty-icon">🔐</div><h3>暂无密码记录</h3><p>点击"添加密码"开始记录</p></div>
<?php else: ?>
<div class="password-grid fade-in">
<?php foreach ($passwords as $p): ?>
<?php $cc = $catColorPwd[$p['category']] ?? '#8b5cf6';
   $editJson = htmlspecialchars(json_encode(['id'=>$p['id'],'title'=>$p['title'],'category'=>$p['category'],'username'=>$p['username'],'url'=>$p['url'],'notes'=>$p['notes'],'icon'=>$p['icon'],'color'=>$p['color']], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<div class="password-card" data-id="<?= $p['id'] ?>">
<div class="card-header">
<div class="card-icon" style="background:<?= e($p['color']) ?>20;color:<?= e($p['color']) ?>"><?= e($p['icon']) ?></div>
<div class="card-info">
<div class="card-title"><?= e($p['title']) ?></div>
<div class="card-meta">
<span class="card-category" style="background:<?= e($cc) ?>20;color:<?= e($cc) ?>"><?= e($p['category']) ?></span>
<?php if ($p['username']): ?><span class="card-username">👤 <?= e($p['username']) ?></span><?php endif; ?>
</div>
</div>
</div>
<div class="card-actions">
<button class="action-btn copy-btn" onclick="copyPassword(<?= $p['id'] ?>)">📋 复制</button>
<?php if ($p['url']): ?><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener" class="action-btn">🔗 打开</a><?php endif; ?>
<button class="action-btn" onclick='showEditPwdModal(<?= $editJson ?>)'>✏️</button>
<button class="action-btn action-danger" onclick="deleteItem(<?= $p['id'] ?>,'pwd')">🗑️</button>
</div>
<div class="card-time">更新于 <?= timeAgo($p['updated_at']) ?></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ====== 记事本模块 ====== -->
<?php if (empty($posts)): ?>
<div class="empty-state"><div class="empty-icon">📝</div><h3>暂无记事</h3><p>点击"写记事"开始记录日常</p></div>
<?php else: ?>
<div class="post-list fade-in">
<?php foreach ($posts as $p): ?>
<?php $cc = $catColorNote[$p['category']] ?? '#8b5cf6';
   $noteJson = htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<a href="javascript:void(0)" class="post-card" style="border-left:4px solid <?= e($p['color']) ?>" onclick='showViewNoteModal(<?= $noteJson ?>)'>
<?php if ($p['is_pinned']): ?><span class="pin-badge">📌 置顶</span><?php endif; ?>
<div class="post-header">
<span class="post-mood"><?= e($p['mood']) ?></span>
<div class="post-info">
<div class="post-title"><?= e($p['title']) ?></div>
<div class="post-meta">
<span style="color:<?= e($cc) ?>"><?= e($p['category']) ?></span>
<span><?= timeAgo($p['updated_at']) ?></span>
<span>👁 <?= $p['views'] ?></span>
</div>
</div>
</div>
<?php if ($p['content']): ?>
<div class="post-preview"><?= e(mb_substr(strip_tags($p['content']), 0, 120)) ?><?= mb_strlen(strip_tags($p['content'])) > 120 ? '...' : '' ?></div>
<?php endif; ?>
<div class="post-actions" onclick="event.preventDefault();event.stopPropagation()">
<button class="action-btn" onclick="togglePin(<?= $p['id'] ?>)" title="<?= $p['is_pinned']?'取消置顶':'置顶' ?>"><?= $p['is_pinned']?'📌':'📍' ?></button>
<button class="action-btn" onclick='showEditNoteModal(<?= $noteJson ?>)'>✏️</button>
<button class="action-btn action-danger" onclick="deleteItem(<?= $p['id'] ?>,'note')">🗑️</button>
</div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- 页脚开发者信息 -->
<div class="app-footer" id="mainFooter">
    <span>🔐 <?= e(SITE_NAME) ?> v<?= APP_VERSION ?></span>
    <span>·</span>
    <span>作者: <a href="mailto:<?= APP_EMAIL ?>"><?= APP_AUTHOR ?></a></span>
</div>

<!-- ====== 记事阅读视图（内嵌，默认隐藏） ====== -->
<div class="note-reader" id="noteReader" style="display:none">
  <div class="note-reader-toolbar">
    <button class="note-reader-back" onclick="closeNoteReader()">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      返回列表
    </button>
    <div class="note-reader-actions" id="noteReaderActions"></div>
  </div>
  <div class="note-reader-accent" id="noteReaderAccent"></div>
  <div class="note-reader-content">
    <div class="note-reader-header">
      <div class="note-reader-mood" id="noteReaderMood"></div>
      <h1 class="note-reader-title" id="noteReaderTitle"></h1>
      <div class="note-reader-meta" id="noteReaderMeta"></div>
    </div>
    <div class="note-reader-divider"></div>
    <div class="note-reader-body" id="noteReaderBody"></div>
  </div>
  <div class="note-reader-footer">
    <div class="note-reader-stats" id="noteReaderStats"></div>
  </div>
</div>
</main>

<!-- ====== 密码弹窗 ====== -->
<div class="modal-overlay" id="pwdModal">
<div class="modal">
<div class="modal-header">
<h3 id="pwdModalTitle">➕ 添加密码</h3>
<button class="modal-close" onclick="closeModal('pwdModal')">✕</button>
</div>
<form method="POST" id="pwdForm">
<input type="hidden" name="action" id="pwdFormAction" value="add">
<?= csrfField() ?>
<input type="hidden" name="id" id="pwdFormId">
<div class="form-row">
<div class="form-group flex-2"><label>名称 *</label><input type="text" name="title" id="pwdTitle" required placeholder="如：微信"></div>
<div class="form-group flex-1"><label>图标</label>
<select name="icon" id="pwdIcon">
<option value="🔑">🔑</option><option value="💬">💬</option><option value="📧">📧</option>
<option value="💰">💰</option><option value="🛒">🛒</option><option value="🎮">🎮</option>
<option value="📱">📱</option><option value="🌐">🌐</option><option value="💼">💼</option>
<option value="🏠">🏠</option><option value="🔐">🔐</option><option value="📦">📦</option>
</select></div>
</div>
<div class="form-row">
<div class="form-group flex-1"><label>用户名</label><input type="text" name="username" id="pwdUsername" placeholder="账号或邮箱"></div>
<div class="form-group flex-1"><label>分类</label>
<select name="category" id="pwdCategory"><?php foreach ($pwdCategories as $c): ?><option value="<?= e($c['name']) ?>"><?= e($c['icon']) ?> <?= e($c['name']) ?></option><?php endforeach; ?></select></div>
</div>
<div class="form-group"><label id="pwdLabel">密码 *</label>
<div class="password-input-wrap">
<input type="password" name="pwd" id="pwdPassword" placeholder="输入密码">
<button type="button" class="toggle-pwd" onclick="togglePwdField()">👁️</button>
<button type="button" class="gen-pwd" onclick="generatePwd()">🎲</button>
</div></div>
<div class="form-group"><label>网址</label><input type="text" name="url" id="pwdUrl" placeholder="https://"></div>
<div class="form-row">
<div class="form-group flex-1"><label>颜色</label><input type="color" name="color" id="pwdColor" value="#8b5cf6" class="color-input"></div>
</div>
<div class="form-group"><label>备注</label><textarea name="notes" id="pwdNotes" rows="2" placeholder="其他信息..."></textarea></div>
<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="closeModal('pwdModal')">取消</button><button type="submit" class="btn btn-primary">💾 保存</button></div>
</form>
</div>
</div>

<!-- ====== 记事本弹窗 ====== -->
<div class="modal-overlay" id="noteModal">
<div class="modal modal-lg">
<div class="modal-header">
<h3 id="noteModalTitle">✏️ 写记事</h3>
<button class="modal-close" onclick="closeModal('noteModal')">✕</button>
</div>
<form method="POST" id="noteForm">
<input type="hidden" name="action" id="noteFormAction" value="add_note">
<?= csrfField() ?>
<input type="hidden" name="id" id="noteFormId">
<div class="form-group"><label>标题 *</label><input type="text" name="title" id="noteTitle" required placeholder="记个什么事..."></div>
<div class="form-row">
<div class="form-group flex-1"><label>分类</label>
<select name="category" id="noteCategory"><?php foreach ($noteCategories as $c): ?><option value="<?= e($c['name']) ?>"><?= e($c['icon']) ?> <?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="form-group flex-1"><label>心情</label>
<select name="mood" id="noteMood">
<option value="📝">📝 记录</option><option value="💭">💭 感悟</option><option value="😊">😊 开心</option>
<option value="😢">😢 难过</option><option value="😤">😤 生气</option><option value="😴">😴 疲惫</option>
<option value="🎉">🎉 庆祝</option><option value="💡">💡 想法</option><option value="⭐">⭐ 精华</option>
</select></div>
<div class="form-group flex-1"><label>颜色</label><input type="color" name="color" id="noteColor" value="#8b5cf6" class="color-input"></div>
</div>
<div class="form-group"><label>内容</label>
<div class="editor-toolbar" id="editorToolbar">
  <button type="button" onclick="editorCmd('undo')" title="撤销 Ctrl+Z">↩️</button>
  <button type="button" onclick="editorCmd('redo')" title="重做 Ctrl+Y">↪️</button>
  <div class="tb-sep"></div>
  <button type="button" onclick="editorCmd('bold')" title="加粗 Ctrl+B"><b>B</b></button>
  <button type="button" onclick="editorCmd('italic')" title="斜体 Ctrl+I"><i>I</i></button>
  <button type="button" onclick="editorCmd('underline')" title="下划线 Ctrl+U"><u>U</u></button>
  <button type="button" onclick="editorCmd('strikeThrough')" title="删除线"><s>S</s></button>
  <button type="button" onclick="editorCmd('superscript')" title="上标">X²</button>
  <button type="button" onclick="editorCmd('subscript')" title="下标">X₂</button>
  <div class="tb-sep"></div>
  <select onchange="editorHeading(this.value);this.value=''" title="标题">
    <option value="">标题</option><option value="H1">标题1</option><option value="H2">标题2</option><option value="H3">标题3</option>
    <option value="P">正文</option>
  </select>
  <select onchange="editorFontSize(this.value);this.value=''" title="字号">
    <option value="">字号</option><option value="1">极小</option><option value="2">小</option><option value="3">中</option><option value="4">偏大</option><option value="5">大</option><option value="6">很大</option><option value="7">特大</option>
  </select>
  <div class="tb-sep"></div>
  <button type="button" onclick="editorCmd('justifyLeft')" title="左对齐">⫷</button>
  <button type="button" onclick="editorCmd('justifyCenter')" title="居中">☰</button>
  <button type="button" onclick="editorCmd('justifyRight')" title="右对齐">⫸</button>
  <button type="button" onclick="editorCmd('justifyFull')" title="两端对齐">≡</button>
  <div class="tb-sep"></div>
  <button type="button" onclick="editorCmd('insertUnorderedList')" title="无序列表">• 列表</button>
  <button type="button" onclick="editorCmd('insertOrderedList')" title="有序列表">1. 列表</button>
  <button type="button" onclick="editorCmd('indent')" title="增加缩进">→⊂</button>
  <button type="button" onclick="editorCmd('outdent')" title="减少缩进">⊃←</button>
  <div class="tb-sep"></div>
  <button type="button" onclick="editorCmd('formatBlock','blockquote')" title="引用">❝</button>
  <button type="button" onclick="editorCmd('formatBlock','pre')" title="代码块">⚙</button>
  <button type="button" onclick="editorInsertHR()" title="分割线">—</button>
  <div class="tb-sep"></div>
  <button type="button" onclick="editorInsertLink()" title="插入链接">🔗</button>
  <button type="button" onclick="editorUploadImage()" title="上传图片">📤</button>
  <button type="button" onclick="editorInsertImageByUrl()" title="网络图片">🖼️</button>
  <button type="button" onclick="editorInsertTable()" title="插入表格">⊞</button>
  <button type="button" onclick="editorInsertDate()" title="插入日期时间">📅</button>
  <div class="tb-sep"></div>
  <span class="tb-color-wrap" title="文字颜色">
    <input type="color" value="#ffffff" onchange="editorForeColor(this.value)" id="editorColorPicker">
    <span class="tb-color-label">A</span>
  </span>
  <span class="tb-color-wrap" title="背景高亮">
    <input type="color" value="#fbbf24" onchange="editorHighlight(this.value)" id="editorBgColorPicker">
    <span class="tb-color-label" style="background:#fbbf24;">🖍</span>
  </span>
  <div class="tb-sep"></div>
  <button type="button" onclick="toggleEmojiPicker()" title="Emoji">😀</button>
  <button type="button" id="fullscreenBtn" onclick="toggleFullscreenEditor()" title="全屏编辑">⛶</button>
  <button type="button" onclick="editorCmd('removeFormat')" title="清除格式">🧹</button>
  <span class="word-count" id="wordCount">0 字 / 0 词</span>
</div>
<div class="emoji-picker" id="emojiPicker">
  <div class="emoji-grid">
    <button type="button" onclick="insertEmoji('😀')">😀</button><button type="button" onclick="insertEmoji('😁')">😁</button><button type="button" onclick="insertEmoji('😂')">😂</button><button type="button" onclick="insertEmoji('🤣')">🤣</button><button type="button" onclick="insertEmoji('😊')">😊</button><button type="button" onclick="insertEmoji('😇')">😇</button><button type="button" onclick="insertEmoji('🙂')">🙂</button><button type="button" onclick="insertEmoji('😉')">😉</button>
    <button type="button" onclick="insertEmoji('😍')">😍</button><button type="button" onclick="insertEmoji('🥰')">🥰</button><button type="button" onclick="insertEmoji('😘')">😘</button><button type="button" onclick="insertEmoji('😜')">😜</button><button type="button" onclick="insertEmoji('🤔')">🤔</button><button type="button" onclick="insertEmoji('🤗')">🤗</button><button type="button" onclick="insertEmoji('😏')">😏</button><button type="button" onclick="insertEmoji('😴')">😴</button>
    <button type="button" onclick="insertEmoji('😢')">😢</button><button type="button" onclick="insertEmoji('😭')">😭</button><button type="button" onclick="insertEmoji('😤')">😤</button><button type="button" onclick="insertEmoji('😡')">😡</button><button type="button" onclick="insertEmoji('🥺')">🥺</button><button type="button" onclick="insertEmoji('😱')">😱</button><button type="button" onclick="insertEmoji('🤯')">🤯</button><button type="button" onclick="insertEmoji('💀')">💀</button>
    <button type="button" onclick="insertEmoji('👍')">👍</button><button type="button" onclick="insertEmoji('👎')">👎</button><button type="button" onclick="insertEmoji('👏')">👏</button><button type="button" onclick="insertEmoji('🙏')">🙏</button><button type="button" onclick="insertEmoji('🤝')">🤝</button><button type="button" onclick="insertEmoji('✌️')">✌️</button><button type="button" onclick="insertEmoji('🤟')">🤟</button><button type="button" onclick="insertEmoji('💪')">💪</button>
    <button type="button" onclick="insertEmoji('❤️')">❤️</button><button type="button" onclick="insertEmoji('🧡')">🧡</button><button type="button" onclick="insertEmoji('💛')">💛</button><button type="button" onclick="insertEmoji('💚')">💚</button><button type="button" onclick="insertEmoji('💙')">💙</button><button type="button" onclick="insertEmoji('💜')">💜</button><button type="button" onclick="insertEmoji('🖤')">🖤</button><button type="button" onclick="insertEmoji('🤍')">🤍</button>
    <button type="button" onclick="insertEmoji('⭐')">⭐</button><button type="button" onclick="insertEmoji('🌟')">🌟</button><button type="button" onclick="insertEmoji('🔥')">🔥</button><button type="button" onclick="insertEmoji('💯')">💯</button><button type="button" onclick="insertEmoji('✅')">✅</button><button type="button" onclick="insertEmoji('❌')">❌</button><button type="button" onclick="insertEmoji('⚠️')">⚠️</button><button type="button" onclick="insertEmoji('💡')">💡</button>
    <button type="button" onclick="insertEmoji('🎉')">🎉</button><button type="button" onclick="insertEmoji('🎊')">🎊</button><button type="button" onclick="insertEmoji('🎁')">🎁</button><button type="button" onclick="insertEmoji('🏆')">🏆</button><button type="button" onclick="insertEmoji('🎵')">🎵</button><button type="button" onclick="insertEmoji('🎶')">🎶</button><button type="button" onclick="insertEmoji('☕')">☕</button><button type="button" onclick="insertEmoji('🍕')">🍕</button>
  </div>
</div>
<div class="editor-content" id="noteEditor" contenteditable="true" data-placeholder="开始写点什么...支持拖拽/粘贴上传图片"></div>
<input type="hidden" name="content" id="noteContent">
</div>
<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="closeModal('noteModal')">取消</button><button type="submit" class="btn btn-primary" onclick="syncEditorContent()">💾 保存</button></div>
</form>
</div>
</div>

<!-- ====== 查看记事弹窗已改为内嵌阅读视图，见main区域 ====== -->

<!-- ====== 设置弹窗 ====== -->
<div class="modal-overlay" id="settingsModal">
<div class="modal modal-lg">
<div class="modal-header">
<h3>⚙️ 系统设置</h3>
<button class="modal-close" onclick="closeModal('settingsModal')">✕</button>
</div>
<div class="settings-content">
<!-- 标签页切换 -->
<div class="settings-tabs">
<button class="settings-tab active" onclick="switchSettingsTab('password')">🔐 修改密码</button>
<button class="settings-tab" onclick="switchSettingsTab('theme')">🎨 主题切换</button>
<button class="settings-tab" onclick="switchSettingsTab('about')">ℹ️ 关于</button>
</div>

<!-- 修改密码 -->
<div class="settings-panel active" id="panel-password">
<form method="POST">
<input type="hidden" name="action" value="change_password">
<?= csrfField() ?>
<div class="form-group"><label>原密码</label><input type="password" name="old_password" required placeholder="输入当前密码"></div>
<div class="form-group"><label>新密码</label><input type="password" name="new_password" required placeholder="输入新密码（至少4位）"></div>
<div class="form-group"><label>确认新密码</label><input type="password" name="confirm_password" required placeholder="再次输入新密码"></div>
<div class="modal-actions"><button type="submit" class="btn btn-primary">🔐 修改密码</button></div>
</form>
</div>

<!-- 主题切换 -->
<div class="settings-panel" id="panel-theme">
<form method="POST">
<input type="hidden" name="action" value="change_theme">
<?= csrfField() ?>
<div class="theme-grid">
<?php foreach ($themes as $tid => $t): ?>
<label class="theme-card <?= $currentTheme === $tid ? 'active' : '' ?>">
<input type="radio" name="theme" value="<?= e($tid) ?>" <?= $currentTheme === $tid ? 'checked' : '' ?>>
<div class="theme-preview" data-theme="<?= e($tid) ?>">
<?php
    $tv = $t['vars'];
    echo '<div class="theme-color-bar">';
    echo '<span style="background:'.$tv['--bg-primary'].'"></span>';
    echo '<span style="background:'.$tv['--bg-card'].'"></span>';
    echo '<span style="background:'.$tv['--accent'].'"></span>';
    echo '<span style="background:'.$tv['--accent-light'].'"></span>';
    echo '<span style="background:'.$tv['--bg-elevated'].'"></span>';
    echo '</div>';
?>
</div>
<div class="theme-info">
<div class="theme-name"><?= e($t['name']) ?></div>
<div class="theme-desc"><?= e($t['desc']) ?></div>
</div>
<?php if ($currentTheme === $tid): ?><span class="theme-current">✅ 当前</span><?php endif; ?>
</label>
<?php endforeach; ?>
</div>
<div class="modal-actions"><button type="submit" class="btn btn-primary">🎨 切换主题</button></div>
</form>
</div>

<!-- 关于 -->
<div class="settings-panel" id="panel-about">
<div class="about-card">
<div class="about-icon">🔐</div>
<h2><?= e(SITE_NAME) ?></h2>
<div class="about-version">v<?= APP_VERSION ?></div>
<div class="about-desc">安全、优雅的本地密码与记事管理工具，数据全部本地加密存储。</div>
<div class="about-divider"></div>
<div class="about-row"><span class="about-label">作者</span><span class="about-value"><?= APP_AUTHOR ?></span></div>
<div class="about-row"><span class="about-label">邮箱</span><span class="about-value"><a href="mailto:<?= APP_EMAIL ?>"><?= APP_EMAIL ?></a></span></div>
<div class="about-row"><span class="about-label">技术栈</span><span class="about-value">PHP + SQLite + AES-256</span></div>
<div class="about-row"><span class="about-label">数据存储</span><span class="about-value">本地加密，零云端依赖</span></div>
<div class="about-divider"></div>
<div class="about-footer">Made with ❤️ by <?= APP_AUTHOR ?></div>
</div>
</div>
</div>
</div>
</div>

<!-- ====== 分类管理弹窗 ====== -->
<div class="modal-overlay" id="categoryModal">
<div class="modal">
<div class="modal-header">
<h3 id="catModalTitle">📁 分类管理</h3>
<button class="modal-close" onclick="closeModal('categoryModal')">✕</button>
</div>
<div class="cat-list" id="catList">
<?php foreach ($manageCats as $c): ?>
<div class="cat-item-row" data-id="<?= $c['id'] ?>">
<div class="cat-item-info">
<span class="cat-item-icon" style="color:<?= e($c['color']) ?>"><?= e($c['icon']) ?></span>
<span class="cat-item-name"><?= e($c['name']) ?></span>
</div>
<div class="cat-item-actions">
<button class="action-btn" onclick="renameCategoryPrompt(<?= $c['id'] ?>, '<?= e($c['name']) ?>')" title="重命名">✏️</button>
<button class="action-btn action-danger" onclick="deleteCategoryConfirm(<?= $c['id'] ?>, '<?= e($c['name']) ?>')" title="删除">🗑️</button>
</div>
</div>
<?php endforeach; ?>
</div>
<div class="cat-form">
<form method="POST" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1;">
<input type="hidden" name="action" value="add_category">
<input type="hidden" name="cat_type" id="catType" value="<?= e($catType) ?>">
<?= csrfField() ?>
<select name="cat_icon" style="width:50px;font-size:1rem;padding:8px;">
<option value="📁">📁</option><option value="💬">💬</option><option value="📧">📧</option>
<option value="💰">💰</option><option value="🛒">🛒</option><option value="🎮">🎮</option>
<option value="📱">📱</option><option value="🌐">🌐</option><option value="💼">💼</option>
<option value="🏠">🏠</option><option value="📝">📝</option><option value="📚">📚</option>
<option value="✈️">✈️</option><option value="🍜">🍜</option><option value="📖">📖</option>
<option value="💭">💭</option><option value="📦">📦</option>
</select>
<input type="text" name="cat_name" placeholder="新分类名..." required style="flex:1;min-width:100px;">
<input type="color" name="cat_color" value="#8b5cf6" style="width:40px;height:36px;padding:2px;cursor:pointer;">
<button type="submit" class="btn btn-primary btn-sm">➕ 添加</button>
</form>
</div>
</div>
</div>

<!-- 重命名分类弹窗 -->
<div class="modal-overlay" id="renameCatModal">
<div class="modal modal-sm">
<div class="modal-header">
<h3>✏️ 重命名分类</h3>
<button class="modal-close" onclick="closeModal('renameCatModal')">✕</button>
</div>
<form method="POST">
<input type="hidden" name="action" value="rename_category">
<input type="hidden" name="cat_id" id="renameCatId">
<?= csrfField() ?>
<div class="form-group"><label>新名称</label><input type="text" name="cat_new_name" id="renameCatName" required></div>
<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="closeModal('renameCatModal')">取消</button><button type="submit" class="btn btn-primary">✅ 确认</button></div>
</form>
</div>
</div>

<!-- 删除分类确认弹窗 -->
<div class="modal-overlay" id="deleteCatModal">
<div class="modal modal-sm">
<div class="modal-header">
<h3>🗑️ 删除分类</h3>
<button class="modal-close" onclick="closeModal('deleteCatModal')">✕</button>
</div>
<p style="padding:1rem;color:var(--text-secondary);">确定删除分类 "<span id="deleteCatName"></span>"？该分类下的记录将移至默认分类。</p>
<form method="POST" style="padding:0 1rem 1rem;">
<input type="hidden" name="action" value="delete_category">
<input type="hidden" name="cat_id" id="deleteCatId">
<?= csrfField() ?>
<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="closeModal('deleteCatModal')">取消</button><button type="submit" class="btn btn-primary" style="background:var(--danger);">🗑️ 删除</button></div>
</form>
</div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
