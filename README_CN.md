# 🔐 密码保管箱（PasswordVault）

> 安全、优雅的本地密码与记事管理工具 · 零云端依赖

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![SQLite](https://img.shields.io/badge/SQLite-3-green.svg)](https://sqlite.org)

---

## ✨ 功能特性

- **密码管理** — 加密存储密码，支持分类、搜索、一键复制
- **记事本** — 富文本笔记，附带心情追踪、置顶、浏览统计
- **本地存储** — 所有数据保存在本地，完全不依赖云端
- **6 套主题** — 深空紫 · 深海蓝 · 翡翠绿 · 落日橘 · 玫瑰粉 · 晨光白
- **分类系统** — 密码与笔记均可自定义分类
- **响应式布局** — 支持桌面端和移动端

---

## 🛡️ 安全机制

| 功能 | 实现方式 |
|------|----------|
| 加密算法 | AES-256-CBC + HMAC-SHA256 认证 |
| 主密码 | `password_hash()` / `password_verify()` 存储与验证 |
| 会话超时 | 30 分钟无操作自动登出 |
| CSRF 防护 | 会话级 Token，`hash_equals()` 校验 |
| 暴力破解防护 | 5 次登录失败 → 锁定 60 秒 |
| 路由守卫 | `router.php` 禁止直接访问敏感文件 |

---

## 📂 项目结构

```
PasswordVault/
├── index.php              # 主入口：路由、登录、主界面、AJAX
├── config.php             # 核心配置：加密、数据库、安全函数
├── router.php             # PHP 内置服务器路由安全拦截
├── upload.php             # 图片上传接口
├── assets/
│   ├── app.js             # 前端交互：富文本编辑器、UI 交互
│   └── style.css          # 样式：主题系统、响应式布局
├── data/
│   ├── passwords.db       # SQLite 数据库
│   ├── .master_key         # AES-256 密钥（隐藏）
│   └── index.php           # 目录保护
├── uploads/               # 用户上传图片目录
│   └── index.php           # 目录保护
└── docs/
    └── API.md             # API 文档
```

---

## 🚀 快速开始

### 环境要求
- PHP 7.4+
- PHP 扩展：`pdo_sqlite`、`openssl`

### 运行

```bash
# 进入项目目录
cd PasswordVault

# 启动 PHP 内置服务器
php -S localhost:8080 router.php

# 浏览器打开
http://localhost:8080
```

> **首次使用：** 设置主密码（至少 4 位字符）。

### 生产环境部署（推荐）

```nginx
# Nginx 配置示例
server {
    listen 443 ssl;
    server_name your-domain.com;
    root /var/www/PasswordVault;
    index index.php;

    # 禁止访问敏感文件
    location ~ /\.(db|master_key|migrated) { deny all; }
    location = /config.php { deny all; }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 🗄️ 数据库结构

### `passwords` — 密码记录表

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INTEGER | 主键自增 |
| `title` | TEXT | 名称（必填） |
| `category` | TEXT | 分类，默认"默认" |
| `username` | TEXT | 账号/用户名 |
| `password_enc` | TEXT | AES-256 加密后的密码密文 |
| `url` | TEXT | 关联网址 |
| `notes` | TEXT | 备注信息 |
| `icon` | TEXT | 图标 Emoji，默认🔑 |
| `color` | TEXT | 卡片颜色，默认 #8b5cf6 |
| `created_at` | DATETIME | 创建时间 |
| `updated_at` | DATETIME | 更新时间 |

### `posts` — 记事表

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INTEGER | 主键自增 |
| `title` | TEXT | 标题（必填） |
| `content` | TEXT | 富文本内容（HTML） |
| `category` | TEXT | 分类，默认"日常" |
| `mood` | TEXT | 心情 Emoji，默认📝 |
| `is_pinned` | INTEGER | 是否置顶，0/1 |
| `views` | INTEGER | 浏览次数 |
| `created_at` | DATETIME | 创建时间 |
| `updated_at` | DATETIME | 更新时间 |

### `categories` — 分类管理表

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INTEGER | 主键自增 |
| `name` | TEXT | 分类名（唯一） |
| `icon` | TEXT | 图标 Emoji |
| `color` | TEXT | 颜色 |
| `type` | TEXT | 类型：`pwd` 或 `note` |
| `sort_order` | INTEGER | 排序序号 |

### `settings` — 系统设置表

| 字段 | 类型 | 说明 |
|------|------|------|
| `key` | TEXT | 设置键（主键） |
| `value` | TEXT | 设置值 |

已知设置项：`master_hash`（主密码哈希）、`theme`（当前主题）

---

## 📡 API 接口

| 接口 | 方法 | 参数 | 说明 |
|------|------|------|------|
| `?reveal=1&id=X` | GET | 需登录 | 解密并返回密码明文 |
| `?ajax=view_note&id=X` | GET | 需登录 | 增加记事浏览次数 |
| `?ajax=categories&type=pwd\|note` | GET | 需登录 | 获取分类列表 |

---

## 🗂️ 默认分类

**密码分类（type=pwd）：**
默认 · 社交💬 · 工作💼 · 金融💰 · 购物🛒 · 邮箱📧 · 游戏🎮 · 其他📦

**记事分类（type=note）：**
日常📝 · 心情💭 · 读书📚 · 旅行✈️ · 美食🍜 · 学习📖 · 其他📦

---

## 📝 版本历史

| 版本 | 日期 | 更新内容 |
|------|------|----------|
| 2.0 | 2026 | 整合密码保管箱与记事本、主题系统、AES-256 加密迁移 |
| 1.x | 早期 | 早期版本，老密钥加密 |

---

## ⚠️ 安全建议

1. **不要直接访问** `config.php` 或 `data/` 目录下的文件
2. 首次部署时可根据需要删除 `data/.migrated_v2` 以触发完整重新迁移
3. 生产环境请启用 **HTTPS** 并使用 Nginx/Apache 替代 PHP 内置服务器
4. **定期备份** `data/passwords.db` 和 `data/.master_key`

---

## 📄 许可证

MIT License — 可免费用于个人和商业项目。

---

## 👤 作者

**六斤Libra**
- 邮箱：528.Libra@gmail.com

---

*你的数据，你做主。无云端，无追踪，只有安全。*