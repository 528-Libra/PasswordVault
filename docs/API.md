# 📖 API 文档 - 密码保管箱

> 版本: 2.0 | 作者: 六斤Libra | 生成时间: 2026-05-03

---

## 📌 目录

- [PHP 后端 API](#php-后端-api)
  - [config.php - 核心配置](#configphp---核心配置)
  - [index.php - 主入口](#indexphp---主入口)
  - [upload.php - 图片上传](#uploadphp---图片上传)
  - [router.php - 路由安全](#routerphp---路由安全)
- [JavaScript 前端 API](#javascript-前端-api)
  - [app.js - 前端交互](#appjs---前端交互)
- [数据库 API](#数据库-api)

---

## PHP 后端 API

### config.php - 核心配置

核心配置文件，包含加密、数据库、安全防护等基础功能。

#### 常量定义

| 常量 | 值 | 说明 |
|------|-----|------|
| `SITE_NAME` | '密码保管箱' | 站点名称 |
| `APP_VERSION` | '2.0' | 版本号 |
| `APP_AUTHOR` | '六斤Libra' | 作者 |
| `APP_EMAIL` | '528.Libra@gmail.com' | 联系邮箱 |
| `ROOT_PATH` | `__DIR__` | 根目录路径 |
| `DATA_DIR` | `ROOT_PATH . '/data/'` | 数据目录 |
| `DB_PATH` | `DATA_DIR . 'passwords.db'` | 数据库路径 |
| `KEY_FILE` | `DATA_DIR . '.master_key'` | 加密密钥文件路径 |

---

#### 安全函数

##### `csrfToken(): string`

生成或获取 CSRF Token。

**返回值:**
- `string` - 32字节随机十六进制字符串

**示例:**
```php
$token = csrfToken();
// 输出类似: "a1b2c3d4e5f6..."
```

---

##### `csrfField(): string`

生成 CSRF 隐藏字段 HTML。

**返回值:**
- `string` - HTML input 元素

**示例:**
```php
echo csrfField();
// 输出: <input type="hidden" name="csrf_token" value="...">
```

---

##### `csrfCheck(): bool`

验证 CSRF Token。

**返回值:**
- `bool` - 验证结果

**注意:**
- 使用 `hash_equals()` 防止时序攻击

---

##### `checkLoginAttempts(): int`

检查登录尝试次数，返回需等待秒数。

**返回值:**
- `int` - 0 表示可以登录，>0 表示需等待秒数

**逻辑:**
- 5 次错误 → 锁定 60 秒
- 锁定期满自动解锁

---

##### `recordLoginAttempt(): void`

记录一次登录失败。

**存储位置:** `DATA_DIR . '.login_attempts'`

---

##### `clearLoginAttempts(): void`

清除登录失败记录（登录成功后调用）。

---

#### 加密函数

##### `getMasterKey(): string`

获取或生成主密钥。

**返回值:**
- `string` - 64字符十六进制密钥

**逻辑:**
1. 密钥文件存在 → 读取
2. 不存在 → 生成并保存，Windows 下设为隐藏

---

##### `encryptData(string $data): string`

使用 AES-256-CBC 加密数据。

**参数:**
| 名称 | 类型 | 说明 |
|------|------|------|
| $data | string | 待加密明文 |

**返回值:**
- `string` - Base64 编码密文（HMAC + IV + 密文）

**加密流程:**
```
key = SHA256(master_key)
iv = random_bytes(16)
encrypted = AES-256-CBC(data, key, iv)
hmac = HMAC-SHA256(iv + encrypted, key)
result = Base64(hmac + iv + encrypted)
```

---

##### `decryptData(string $data): string|false`

解密数据。

**参数:**
| 名称 | 类型 | 说明 |
|------|------|------|
| $data | string | Base64 编码密文 |

**返回值:**
- `string` - 解密后明文
- `false` - 解密失败（HMAC 不匹配或数据损坏）

---

#### 数据库函数

##### `getDB(): PDO`

获取数据库连接单例。

**返回值:**
- `PDO` - SQLite 数据库连接

---

##### `initDB(): void`

初始化数据库表结构。

**创建表:**
- `passwords` - 密码记录
- `posts` - 记事本
- `categories` - 分类
- `settings` - 系统设置

---

##### `getSetting(string $key, string $default = ''): string`

获取系统设置。

**参数:**
| 名称 | 类型 | 说明 |
|------|------|------|
| $key | string | 设置键名 |
| $default | string | 默认值 |

**返回值:**
- `string` - 设置值或默认值

---

##### `setSetting(string $key, string $value): void`

保存系统设置。

---

##### `getCurrentTheme(): string`

获取当前主题 ID。

**返回值:**
- `string` - 主题 ID，默认 'deep-space'

---

#### 分类管理函数

##### `addCategory(string $name, string $icon, string $color, string $type): array`

添加分类。

**参数:**
| 名称 | 类型 | 说明 |
|------|------|------|
| $name | string | 分类名 |
| $icon | string | 图标 Emoji |
| $color | string | 颜色值 |
| $type | string | 类型: 'pwd' 或 'note' |

**返回值:**
```php
['success' => bool, 'msg' => string]
```

---

##### `renameCategory(int $id, string $newName): array`

重命名分类，同步更新关联记录。

---

##### `deleteCategory(int $id): array`

删除分类，关联记录移至默认分类。

---

##### `updateCategoryIcon(int $id, string $icon, string $color): array`

更新分类图标和颜色。

---

##### `getCategories(string $type = 'pwd'): array`

获取指定类型的分类列表。

---

##### `getCatStats(string $type = 'pwd'): array`

获取分类统计（各分类下记录数）。

---

#### 工具函数

##### `e(string $s): string`

HTML 转义输出。

```php
echo e($userInput); // 安全输出
```

---

##### `sanitizeHTML(string $html): string`

清理 HTML，移除危险标签和属性。

**允许标签:**
`<p><br><b><i><u><s><strong><em><del><h1-h3><ul><ol><li><blockquote><pre><code><a><img><hr><table><tr><th><td><span><div><sup><sub><thead><tbody>`

**移除:**
- `on*` 事件属性
- `javascript:` / `vbscript:` / `data:` 协议
- `style` 属性

---

##### `timeAgo(string $dt): string`

将日期时间转换为相对时间描述。

**返回示例:** "刚刚"、"5分钟前"、"2天前"、"3月前"

---

##### `getThemes(): array`

获取所有主题定义。

---

##### `checkSessionTimeout(): bool`

检查 Session 超时（30分钟），更新最后活动时间。

---

##### `changeMasterPassword(string $oldPwd, string $newPwd): array`

修改主密码。

---

---

### index.php - 主入口

主页面，处理所有 POST 请求和 AJAX 接口。

#### POST 接口

所有 POST 请求需携带 `csrf_token` 字段。

---

##### `action=login`

用户登录。

**参数:**
| 名称 | 类型 | 必填 | 说明 |
|------|------|------|------|
| action | string | 是 | 固定值 "login" |
| password | string | 是 | 主密码 |

**响应:**
- 成功 → 重定向到 `index.php`
- 失败 → 显示错误消息

---

##### `action=add`

添加密码记录（需登录）。

**参数:**
| 名称 | 类型 | 必填 | 说明 |
|------|------|------|------|
| action | string | 是 | 固定值 "add" |
| title | string | 是 | 名称 |
| pwd | string | 是 | 密码明文 |
| category | string | 否 | 分类，默认"默认" |
| username | string | 否 | 用户名 |
| url | string | 否 | 网址 |
| notes | string | 否 | 备注 |
| icon | string | 否 | 图标，默认🔑 |
| color | string | 否 | 颜色，默认#8b5cf6 |
| csrf_token | string | 是 | CSRF Token |

---

##### `action=edit`

编辑密码记录。

**参数:**
| 名称 | 类型 | 必填 | 说明 |
|------|------|------|------|
| action | string | 是 | 固定值 "edit" |
| id | int | 是 | 记录 ID |
| title | string | 是 | 名称 |
| pwd | string | 否 | 新密码（留空不变） |
| ... | | | 其他同 add |
| csrf_token | string | 是 | CSRF Token |

---

##### `action=delete`

删除密码或记事。

**参数:**
| 名称 | 类型 | 必填 | 说明 |
|------|------|------|------|
| action | string | 是 | 固定值 "delete" |
| id | int | 是 | 记录 ID |
| module | string | 否 | "pwd" 或 "note"，默认 "pwd" |
| csrf_token | string | 是 | CSRF Token |

---

##### `action=add_note`

添加记事。

**参数:**
| 名称 | 类型 | 必填 | 说明 |
|------|------|------|------|
| action | string | 是 | 固定值 "add_note" |
| title | string | 是 | 标题 |
| content | string | 否 | 富文本内容（HTML） |
| category | string | 否 | 分类，默认"日常" |
| mood | string | 否 | 心情，默认📝 |
| color | string | 否 | 颜色 |
| csrf_token | string | 是 | CSRF Token |

---

##### `action=edit_note`

编辑记事。

---

##### `action=pin_note`

切换记事置顶状态。

---

##### `action=add_category`

添加分类。

---

##### `action=rename_category`

重命名分类。

---

##### `action=delete_category`

删除分类。

---

##### `action=update_category`

更新分类图标/颜色。

---

##### `action=change_password`

修改主密码。

**参数:**
| 名称 | 类型 | 必填 | 说明 |
|------|------|------|------|
| action | string | 是 | 固定值 "change_password" |
| old_password | string | 是 | 原密码 |
| new_password | string | 是 | 新密码（至少4位） |
| confirm_password | string | 是 | 确认新密码 |
| csrf_token | string | 是 | CSRF Token |

---

##### `action=change_theme`

切换主题。

**参数:**
| 名称 | 类型 | 必填 | 说明 |
|------|------|------|------|
| action | string | 是 | 固定值 "change_theme" |
| theme | string | 是 | 主题 ID |
| csrf_token | string | 是 | CSRF Token |

---

#### GET 接口（AJAX）

##### `?reveal=1&id=X`

获取密码明文。

**参数:**
| 名称 | 类型 | 说明 |
|------|------|------|
| reveal | int | 固定值 1 |
| id | int | 密码记录 ID |

**响应:**
```json
{
  "success": true,
  "password": "解密后的明文密码"
}
```

---

##### `?ajax=view_note&id=X`

增加记事浏览次数。

**响应:**
```json
{
  "success": true,
  "views": 123
}
```

---

##### `?ajax=categories&type=pwd|note`

获取分类列表。

**响应:**
```json
[
  {"id": 1, "name": "默认", "icon": "📁", "color": "#8b5cf6", ...},
  ...
]
```

---

### upload.php - 图片上传

图片上传接口，支持点击、拖拽、粘贴上传。

**端点:** `POST upload.php`

**请求:**
- Content-Type: `multipart/form-data`
- 字段: `image` (文件)

**限制:**
- 最大大小: 10MB
- 允许类型: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/bmp`, `image/svg+xml`

**成功响应:**
```json
{
  "success": true,
  "url": "uploads/20260503_1155_a1b2c3d4.jpg",
  "name": "原始文件名.jpg",
  "size": 123456
}
```

**失败响应:**
```json
{
  "success": false,
  "error": "错误信息"
}
```

**安全措施:**
- MIME 类型验证
- 扩展名白名单
- SVG 重命名为 `.svg.txt` 防止 XSS

---

### router.php - 路由安全

PHP 内置服务器路由文件，阻止敏感文件访问。

**拦截规则:**

| 正则 | 说明 | 响应 |
|------|------|------|
| `#/data/.*\.db$#i` | 数据库文件 | HTTP 403 |
| `#/config\.php$#i` | 配置文件 | HTTP 403 |
| `#/(check_db\|test_debug\|migrate)\.php$#i` | 调试脚本 | HTTP 403 |

**其他文件:** 正常返回（`return false`）

---

## JavaScript 前端 API

### app.js - 前端交互

前端交互脚本，包含 UI 交互和富文本编辑器。

#### 通用函数

##### `closeModal(id: string): void`

关闭弹窗。

```javascript
closeModal('pwdModal');
```

---

##### `showToast(msg: string, type?: string): void`

显示 Toast 提示。

**参数:**
| 名称 | 类型 | 说明 |
|------|------|------|
| msg | string | 提示消息 |
| type | string | "error" 或省略 |

---

##### `deleteItem(id: number, module: string): void`

删除密码或记事（带确认）。

---

#### 设置弹窗

##### `showSettingsModal(): void`

显示设置弹窗。

---

##### `switchSettingsTab(tab: string): void`

切换设置标签页。

**参数:** `tab` = "password" | "theme" | "about"

---

#### 分类管理

##### `showCategoryModal(type: string): void`

显示分类管理弹窗。

---

##### `renameCategoryPrompt(id: number, name: string): void`

显示重命名分类弹窗。

---

##### `deleteCategoryConfirm(id: number, name: string): void`

显示删除分类确认弹窗。

---

#### 密码模块

##### `showAddPwdModal(): void`

显示添加密码弹窗。

---

##### `showEditPwdModal(data: object): void`

显示编辑密码弹窗。

**参数:**
```javascript
{
  id: number,
  title: string,
  category: string,
  username: string,
  url: string,
  notes: string,
  icon: string,
  color: string
}
```

---

##### `copyPassword(id: number): void`

复制密码到剪贴板。

---

##### `togglePwdField(): void`

切换密码显示/隐藏。

---

##### `generatePwd(): void`

生成随机密码（16位，含大小写、数字、符号）。

---

#### 记事本模块

##### `showAddNoteModal(): void`

显示添加记事弹窗。

---

##### `showEditNoteModal(data: object): void`

显示编辑记事弹窗。

---

##### `syncEditorContent(): void`

同步编辑器内容到隐藏字段（提交前调用）。

---

##### `showViewNoteModal(data: object): void`

显示记事阅读视图。

---

##### `openNoteReader(): void`

打开阅读视图（隐藏列表）。

---

##### `closeNoteReader(): void`

关闭阅读视图（显示列表）。

---

##### `togglePin(id: number): void`

切换置顶状态。

---

#### 富文本编辑器

##### `editorCmd(cmd: string, val?: string): void`

执行编辑器命令。

```javascript
editorCmd('bold');          // 加粗
editorCmd('italic');        // 斜体
editorCmd('underline');     // 下划线
editorCmd('formatBlock', 'h1'); // 设为标题
```

---

##### `editorHeading(val: string): void`

设置标题级别。

**参数:** `val` = "H1" | "H2" | "H3" | "P"

---

##### `editorFontSize(val: string): void`

设置字号。

**参数:** `val` = "1"~"7"

---

##### `editorForeColor(color: string): void`

设置文字颜色。

---

##### `editorHighlight(color: string): void`

设置背景高亮色。

---

##### `editorInsertHR(): void`

插入分割线。

---

##### `editorInsertLink(): void`

插入链接（弹窗输入 URL）。

---

##### `editorInsertImageByUrl(): void`

插入网络图片（弹窗输入 URL）。

---

##### `editorUploadImage(): void`

触发本地图片上传。

---

##### `uploadImageFile(file: File): void`

上传图片文件。

**限制:**
- 类型必须为 `image/*`
- 大小 ≤ 10MB

---

##### `editorInsertTable(): void`

插入表格（弹窗输入行列数）。

---

##### `editorInsertDate(): void`

插入当前日期时间。

---

##### `toggleEmojiPicker(): void`

切换 Emoji 选择器。

---

##### `insertEmoji(emoji: string): void`

插入 Emoji。

---

##### `updateWordCount(): void`

更新字数统计。

---

##### `toggleFullscreenEditor(): void`

切换编辑器全屏模式。

---

## 数据库 API

### SQLite 表结构

详见 [README.md](../README.md) 数据库结构章节。

### 常用查询示例

```sql
-- 获取所有密码
SELECT id, title, category, username, url, notes, icon, color
FROM passwords
ORDER BY updated_at DESC;

-- 搜索密码
SELECT * FROM passwords
WHERE title LIKE '%关键词%' OR username LIKE '%关键词%';

-- 获取分类统计
SELECT category, COUNT(*) as cnt
FROM passwords
GROUP BY category;

-- 获取置顶记事
SELECT * FROM posts
WHERE is_pinned = 1
ORDER BY updated_at DESC;
```

---

## 错误码

| HTTP 状态码 | 说明 |
|-------------|------|
| 200 | 成功 |
| 400 | 请求参数错误 |
| 403 | 未登录或禁止访问 |
| 500 | 服务器内部错误 |

---

## 安全最佳实践

1. **所有 POST 请求必须携带 CSRF Token**
2. **密码明文仅在内存中短暂存在**，存储前立即加密
3. **主密钥文件 `.master_key` 应设为隐藏**（Windows: `attrib +H`）
4. **生产环境建议启用 HTTPS**
5. **定期备份数据库和密钥文件**

---

*文档生成时间: 2026-05-03 | 代码文学家 📖*
