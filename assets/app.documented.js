/**
 * 密码保管箱 + 记事本（整合版）前端交互
 * 
 * 功能模块：
 * - 通用工具函数（弹窗、Toast、删除）
 * - 设置弹窗管理
 * - 分类管理（增删改查）
 * - 密码模块（添加、编辑、复制、生成）
 * - 记事本模块（添加、编辑、阅读视图、置顶）
 * - 富文本编辑器（格式化、图片上传、表格、Emoji）
 * 
 * @package    PasswordVault
 * @version    2.0
 * @author     六斤Libra <528.Libra@gmail.com>
 * @license    MIT
 */

/* ==================== 通用函数 ==================== */

/**
 * 关闭弹窗
 * 
 * 通过移除 'active' 类来隐藏弹窗。
 * 
 * @param {string} id - 弹窗元素的 ID
 * @returns {void}
 * 
 * @example
 * closeModal('pwdModal');
 * closeModal('settingsModal');
 */
function closeModal(id) { 
    document.getElementById(id).classList.remove('active'); 
}

/**
 * 显示 Toast 提示
 * 
 * 在页面底部中央显示临时提示消息，2.5 秒后自动消失。
 * 
 * @param {string} msg - 提示消息内容
 * @param {string} [type] - 提示类型，"error" 显示红色背景，其他显示普通背景
 * @returns {void}
 * 
 * @example
 * showToast('✅ 保存成功');
 * showToast('❌ 操作失败', 'error');
 */
function showToast(msg, type) {
    const t = document.createElement('div'); 
    t.textContent = msg;
    const bg = type==='error' ? 'var(--danger)' : 'var(--bg-card)';
    const fg = type==='error' ? '#fff' : 'var(--text-primary)';
    t.style.cssText = `position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:${bg};color:${fg};padding:12px 28px;border-radius:10px;border:1px solid var(--border);z-index:9999;animation:fadeIn 0.3s ease;font-size:0.9rem;box-shadow:0 8px 30px rgba(0,0,0,0.4)`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity 0.3s'; setTimeout(()=>t.remove(),300); }, 2500);
}

/**
 * 删除密码或记事
 * 
 * 显示确认对话框，确认后创建隐藏表单提交删除请求。
 * 
 * @param {number} id - 记录 ID
 * @param {string} module - 模块类型，"pwd" 或 "note"
 * @returns {void}
 * 
 * @example
 * deleteItem(123, 'pwd');  // 删除密码
 * deleteItem(456, 'note'); // 删除记事
 */
function deleteItem(id, module) {
    if (!confirm('确定删除？不可恢复！')) return;
    const f = document.createElement('form'); f.method='POST'; f.style.display='none';
    const csrf = document.querySelector('input[name=csrf_token]');
    f.innerHTML = '<input name="action" value="delete"><input name="id" value="'+id+'"><input name="module" value="'+module+'">' + (csrf ? '<input name="csrf_token" value="'+csrf.value+'">' : '');
    document.body.appendChild(f); f.submit();
}

// 弹窗点击外部关闭
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('active'); }));

// ESC 键关闭所有弹窗
document.addEventListener('keydown', e => { 
    if(e.key==='Escape') { 
        document.querySelectorAll('.modal-overlay.active').forEach(m=>m.classList.remove('active')); 
        closeFullscreenEditor(); 
    } 
});

/* ==================== 设置弹窗 ==================== */

/**
 * 显示设置弹窗
 * 
 * @returns {void}
 */
function showSettingsModal() {
    document.getElementById('settingsModal').classList.add('active');
}

/**
 * 切换设置标签页
 * 
 * @param {string} tab - 标签页 ID，"password" | "theme" | "about"
 * @returns {void}
 * 
 * @example
 * switchSettingsTab('theme'); // 切换到主题设置
 */
function switchSettingsTab(tab) {
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    document.querySelector(`.settings-tab[onclick*="${tab}"]`).classList.add('active');
    document.getElementById('panel-' + tab).classList.add('active');
}

/* ==================== 分类管理 ==================== */

/**
 * 显示分类管理弹窗
 * 
 * @param {string} type - 分类类型，"pwd" 或 "note"
 * @returns {void}
 * 
 * @example
 * showCategoryModal('pwd');  // 密码分类
 * showCategoryModal('note'); // 记事分类
 */
function showCategoryModal(type) {
    const title = type === 'pwd' ? '🔑 密码分类管理' : '📝 记事分类管理';
    document.getElementById('catModalTitle').textContent = title;
    document.getElementById('catType').value = type;
    document.getElementById('categoryModal').classList.add('active');
}

/**
 * 显示重命名分类弹窗
 * 
 * @param {number} id - 分类 ID
 * @param {string} name - 当前分类名称
 * @returns {void}
 */
function renameCategoryPrompt(id, name) {
    document.getElementById('renameCatId').value = id;
    document.getElementById('renameCatName').value = name;
    document.getElementById('renameCatModal').classList.add('active');
}

/**
 * 显示删除分类确认弹窗
 * 
 * @param {number} id - 分类 ID
 * @param {string} name - 分类名称（用于显示）
 * @returns {void}
 */
function deleteCategoryConfirm(id, name) {
    document.getElementById('deleteCatId').value = id;
    document.getElementById('deleteCatName').textContent = name;
    document.getElementById('deleteCatModal').classList.add('active');
}

/* ==================== 密码模块 ==================== */

/**
 * 显示添加密码弹窗
 * 
 * 重置表单，设置必填项，显示弹窗。
 * 
 * @returns {void}
 */
function showAddPwdModal() {
    document.getElementById('pwdModalTitle').textContent = '➕ 添加密码';
    document.getElementById('pwdFormAction').value = 'add';
    document.getElementById('pwdFormId').value = '';
    document.getElementById('pwdForm').reset();
    document.getElementById('pwdColor').value = '#8b5cf6';
    document.getElementById('pwdPassword').required = true;
    document.getElementById('pwdPassword').placeholder = '输入密码';
    document.getElementById('pwdLabel').textContent = '密码 *';
    document.getElementById('pwdModal').classList.add('active');
}

/**
 * 显示编辑密码弹窗
 * 
 * 填充现有数据，密码字段设为可选（留空不变）。
 * 
 * @param {Object} data - 密码数据对象
 * @param {number} data.id - 记录 ID
 * @param {string} data.title - 名称
 * @param {string} data.category - 分类
 * @param {string} data.username - 用户名
 * @param {string} data.url - 网址
 * @param {string} data.notes - 备注
 * @param {string} data.icon - 图标
 * @param {string} data.color - 颜色
 * @returns {void}
 * 
 * @example
 * showEditPwdModal({
 *     id: 1,
 *     title: '微信',
 *     category: '社交',
 *     username: 'myemail@example.com',
 *     url: 'https://weixin.qq.com',
 *     notes: '备注信息',
 *     icon: '💬',
 *     color: '#06b6d4'
 * });
 */
function showEditPwdModal(data) {
    document.getElementById('pwdModalTitle').textContent = '✏️ 编辑密码';
    document.getElementById('pwdFormAction').value = 'edit';
    document.getElementById('pwdFormId').value = data.id;
    document.getElementById('pwdTitle').value = data.title||'';
    document.getElementById('pwdUsername').value = data.username||'';
    document.getElementById('pwdCategory').value = data.category||'默认';
    document.getElementById('pwdUrl').value = data.url||'';
    document.getElementById('pwdIcon').value = data.icon||'🔑';
    document.getElementById('pwdColor').value = data.color||'#8b5cf6';
    document.getElementById('pwdNotes').value = data.notes||'';
    document.getElementById('pwdPassword').value = '';
    document.getElementById('pwdPassword').required = false;
    document.getElementById('pwdPassword').placeholder = '留空保持原密码不变';
    document.getElementById('pwdLabel').textContent = '密码（留空不变）';
    document.getElementById('pwdModal').classList.add('active');
}

/**
 * 复制密码到剪贴板
 * 
 * 通过 AJAX 获取解密后的密码，复制到剪贴板。
 * 
 * @param {number} id - 密码记录 ID
 * @returns {void}
 * 
 * @example
 * copyPassword(123);
 */
function copyPassword(id) {
    fetch('index.php?reveal=1&id='+id).then(r=>r.json()).then(d => {
        if(d.success && d.password) {
            navigator.clipboard.writeText(d.password).then(()=>showToast('✅ 已复制')).catch(()=>{
                // 降级方案：使用 textarea
                const i=document.createElement('textarea'); i.value=d.password; i.style.cssText='position:fixed;left:-9999px';
                document.body.appendChild(i); i.select(); document.execCommand('copy'); document.body.removeChild(i);
                showToast('✅ 已复制');
            });
        } else showToast('❌ 获取失败','error');
    }).catch(()=>showToast('❌ 网络错误','error'));
}

/**
 * 切换密码显示/隐藏
 * 
 * @returns {void}
 */
function togglePwdField() {
    const i = document.getElementById('pwdPassword'), b = document.querySelector('.toggle-pwd');
    if(i.type==='password'){i.type='text';b.textContent='🙈';}else{i.type='password';b.textContent='👁️';}
}

/**
 * 生成随机密码
 * 
 * 生成 16 位密码，包含：
 * - 大写字母（排除 I, O）
 * - 小写字母（排除 i, l, o）
 * - 数字（排除 0, 1）
 * - 特殊符号（!@#$%^&*）
 * 
 * @returns {void}
 * 
 * @example
 * generatePwd(); // 填充到密码输入框
 */
function generatePwd() {
    const u='ABCDEFGHJKLMNPQRSTUVWXYZ',l='abcdefghjkmnpqrstuvwxyz',d='23456789',s='!@#$%^&*',a=u+l+d+s;
    let p=u[Math.random()*u.length|0]+l[Math.random()*l.length|0]+d[Math.random()*d.length|0]+s[Math.random()*s.length|0];
    for(let i=4;i<16;i++) p+=a[Math.random()*a.length|0];
    p=p.split('').sort(()=>Math.random()-0.5).join('');
    document.getElementById('pwdPassword').value=p;
    document.getElementById('pwdPassword').type='text';
    document.querySelector('.toggle-pwd').textContent='🙈';
    showToast('🎲 已生成密码');
}

/* ==================== 记事本模块 ==================== */

/**
 * 显示添加记事弹窗
 * 
 * 重置表单和编辑器，显示弹窗。
 * 
 * @returns {void}
 */
function showAddNoteModal() {
    document.getElementById('noteModalTitle').textContent = '✏️ 写记事';
    document.getElementById('noteFormAction').value = 'add_note';
    document.getElementById('noteFormId').value = '';
    document.getElementById('noteForm').reset();
    document.getElementById('noteColor').value = '#8b5cf6';
    document.getElementById('noteEditor').innerHTML = '';
    document.getElementById('noteModal').classList.add('active');
    updateWordCount();
    setTimeout(() => document.getElementById('noteEditor').focus(), 100);
}

/**
 * 显示编辑记事弹窗
 * 
 * 填充现有数据到表单和编辑器。
 * 
 * @param {Object} data - 记事数据对象
 * @param {number} data.id - 记录 ID
 * @param {string} data.title - 标题
 * @param {string} data.content - 富文本内容（HTML）
 * @param {string} data.category - 分类
 * @param {string} data.mood - 心情
 * @param {string} data.color - 颜色
 * @returns {void}
 */
function showEditNoteModal(data) {
    document.getElementById('noteModalTitle').textContent = '✏️ 编辑记事';
    document.getElementById('noteFormAction').value = 'edit_note';
    document.getElementById('noteFormId').value = data.id;
    document.getElementById('noteTitle').value = data.title||'';
    document.getElementById('noteEditor').innerHTML = data.content||'';
    document.getElementById('noteCategory').value = data.category||'日常';
    document.getElementById('noteMood').value = data.mood||'📝';
    document.getElementById('noteColor').value = data.color||'#8b5cf6';
    document.getElementById('noteModal').classList.add('active');
    updateWordCount();
}

/**
 * 同步编辑器内容到隐藏字段
 * 
 * 表单提交前必须调用，将 contenteditable 内容复制到 hidden input。
 * 
 * @returns {void}
 */
function syncEditorContent() {
    document.getElementById('noteContent').value = document.getElementById('noteEditor').innerHTML;
}

/**
 * 显示记事阅读视图
 * 
 * 在主区域内显示完整记事内容，隐藏列表。
 * 同时发送浏览计数请求。
 * 
 * @param {Object} data - 记事数据对象
 * @param {number} data.id - 记录 ID
 * @param {string} data.title - 标题
 * @param {string} data.content - 内容
 * @param {string} data.category - 分类
 * @param {string} data.mood - 心情
 * @param {string} data.color - 颜色
 * @param {string} data.created_at - 创建时间
 * @param {string} data.updated_at - 更新时间
 * @param {number} data.views - 浏览次数
 * @returns {void}
 */
function showViewNoteModal(data) {
    // 发送浏览计数请求
    fetch('index.php?ajax=view_note&id=' + data.id)
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const sv = document.getElementById('noteReaderStatViews');
                if (sv) sv.textContent = res.views;
            }
        })
        .catch(() => {});

    const catColor = data.color || '#8b5cf6';

    // 装饰条
    document.getElementById('noteReaderAccent').style.background =
        'linear-gradient(90deg, ' + catColor + ', ' + catColor + 'aa, ' + catColor + ')';

    // 心情
    document.getElementById('noteReaderMood').textContent = data.mood || '📝';

    // 标题
    document.getElementById('noteReaderTitle').textContent = data.title || '无标题';

    // 元信息
    let metaHtml = '';
    if (data.category) {
        metaHtml += '<span class="meta-tag category-tag" style="background:' + catColor + '22;color:' + catColor + ';border-color:' + catColor + '44">📁 ' + data.category + '</span>';
    }
    if (data.created_at) metaHtml += '<span class="meta-tag">📅 ' + data.created_at + '</span>';
    if (data.updated_at && data.updated_at !== data.created_at) metaHtml += '<span class="meta-tag">✏️ 编辑于 ' + data.updated_at + '</span>';
    document.getElementById('noteReaderMeta').innerHTML = metaHtml;

    // 正文
    const bodyEl = document.getElementById('noteReaderBody');
    if (data.content && data.content.trim()) {
        bodyEl.innerHTML = data.content;
    } else {
        bodyEl.innerHTML = '<div class="empty-content"><div class="empty-icon">🍃</div><p>这篇记事还没有内容</p></div>';
    }

    // 底部统计
    document.getElementById('noteReaderStats').innerHTML =
        '<span class="stat-item">👁 <span id="noteReaderStatViews">' + (data.views || 0) + '</span> 次浏览</span>';

    // 顶部操作按钮
    const actions = document.getElementById('noteReaderActions');
    actions.innerHTML = '';
    const editBtn = document.createElement('button');
    editBtn.className = 'btn btn-edit';
    editBtn.innerHTML = '✏️ 编辑';
    editBtn.onclick = function() { closeNoteReader(); showEditNoteModal(data); };
    const delBtn = document.createElement('button');
    delBtn.className = 'btn btn-delete';
    delBtn.innerHTML = '🗑 删除';
    delBtn.onclick = function() { deleteItem(data.id, 'note'); };
    actions.appendChild(editBtn);
    actions.appendChild(delBtn);

    // 隐藏列表，显示阅读视图
    openNoteReader();
}

/**
 * 打开阅读视图
 * 
 * 隐藏主区域内除阅读视图外的所有元素。
 * 
 * @returns {void}
 */
function openNoteReader() {
    const main = document.querySelector('.main-content');
    const children = main.children;
    for (let i = 0; i < children.length; i++) {
        if (children[i].id !== 'noteReader') children[i].style.display = 'none';
    }
    const reader = document.getElementById('noteReader');
    reader.style.display = '';
    reader.classList.add('note-reader-active');
    main.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * 关闭阅读视图
 * 
 * 显示主区域内所有元素，隐藏阅读视图。
 * 
 * @returns {void}
 */
function closeNoteReader() {
    const main = document.querySelector('.main-content');
    const children = main.children;
    for (let i = 0; i < children.length; i++) {
        if (children[i].id !== 'noteReader') children[i].style.display = '';
    }
    const reader = document.getElementById('noteReader');
    reader.style.display = 'none';
    reader.classList.remove('note-reader-active');
}

/**
 * 切换置顶状态
 * 
 * 通过创建隐藏表单提交请求。
 * 
 * @param {number} id - 记事 ID
 * @returns {void}
 */
function togglePin(id) {
    const f=document.createElement('form'); f.method='POST'; f.style.display='none';
    const csrf = document.querySelector('input[name=csrf_token]');
    f.innerHTML='<input name="action" value="pin_note"><input name="id" value="'+id+'">' + (csrf ? '<input name="csrf_token" value="'+csrf.value+'">' : '');
    document.body.appendChild(f); f.submit();
}

/* ==================== 富文本编辑器 ==================== */

/**
 * 获取编辑器元素
 * 
 * @returns {HTMLElement} 编辑器 DOM 元素
 */
const editor = () => document.getElementById('noteEditor');

/**
 * 执行编辑器命令
 * 
 * 封装 document.execCommand，执行后聚焦编辑器。
 * 
 * @param {string} cmd - 命令名称
 * @param {string} [val] - 命令值（可选）
 * @returns {void}
 * 
 * @example
 * editorCmd('bold');           // 加粗
 * editorCmd('italic');         // 斜体
 * editorCmd('underline');      // 下划线
 * editorCmd('formatBlock', 'h1'); // 设为标题
 * editorCmd('fontSize', '5');  // 设字号
 */
function editorCmd(cmd, val) {
    document.execCommand(cmd, false, val || null);
    editor().focus();
}

/**
 * 设置标题级别
 * 
 * @param {string} val - 标题级别，"H1" | "H2" | "H3" | "P"
 * @returns {void}
 * 
 * @example
 * editorHeading('H1'); // 一级标题
 * editorHeading('P');  // 正文
 */
function editorHeading(val) {
    if (!val) return;
    document.execCommand('formatBlock', false, val);
    editor().focus();
}

/**
 * 设置字号
 * 
 * @param {string} val - 字号，"1"（最小）到 "7"（最大）
 * @returns {void}
 */
function editorFontSize(val) {
    if (!val) return;
    document.execCommand('fontSize', false, val);
    editor().focus();
}

/**
 * 设置文字颜色
 * 
 * @param {string} color - 颜色值（十六进制或颜色名）
 * @returns {void}
 * 
 * @example
 * editorForeColor('#ff0000'); // 红色
 */
function editorForeColor(color) {
    document.execCommand('foreColor', false, color);
    editor().focus();
}

/**
 * 设置背景高亮色
 * 
 * @param {string} color - 颜色值
 * @returns {void}
 */
function editorHighlight(color) {
    document.execCommand('hiliteColor', false, color);
    editor().focus();
}

/**
 * 插入分割线
 * 
 * @returns {void}
 */
function editorInsertHR() {
    document.execCommand('insertHorizontalRule');
    editor().focus();
}

/**
 * 插入链接
 * 
 * 弹窗输入 URL，选中文字创建超链接，否则插入 URL 文本。
 * 
 * @returns {void}
 */
function editorInsertLink() {
    const sel = window.getSelection();
    const selectedText = sel.toString();
    const url = prompt('请输入链接地址:', 'https://');
    if (url) {
        if (selectedText) {
            document.execCommand('createLink', false, url);
        } else {
            document.execCommand('insertHTML', false, '<a href="'+url+'" target="_blank">'+url+'</a>');
        }
        editor().focus();
    }
}

/**
 * 插入网络图片
 * 
 * 弹窗输入图片 URL。
 * 
 * @returns {void}
 */
function editorInsertImageByUrl() {
    const url = prompt('请输入图片地址:', 'https://');
    if (url) {
        document.execCommand('insertImage', false, url);
        editor().focus();
    }
}

/**
 * 触发本地图片上传
 * 
 * 创建 file input 并触发点击。
 * 
 * @returns {void}
 */
function editorUploadImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/gif,image/webp,image/bmp';
    input.multiple = true;
    input.onchange = function() {
        for (const file of input.files) {
            uploadImageFile(file);
        }
    };
    input.click();
}

/**
 * 上传图片文件
 * 
 * 显示上传中占位符，上传成功后替换为图片标签。
 * 
 * @param {File} file - 图片文件对象
 * @returns {void}
 * 
 * @throws 图片类型错误或大小超限显示 Toast 错误
 */
function uploadImageFile(file) {
    if (!file.type.startsWith('image/')) { showToast('❌ 仅支持图片文件', 'error'); return; }
    if (file.size > 10*1024*1024) { showToast('❌ 图片不能超过10MB', 'error'); return; }

    const ed = editor();
    document.execCommand('insertHTML', false, '<div class="img-uploading" contenteditable="false">📤 上传中...</div>');

    const fd = new FormData();
    fd.append('image', file);

    fetch('upload.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const uploading = ed.querySelector('.img-uploading');
                if (uploading) {
                    uploading.outerHTML = '<img src="'+data.url+'" alt="'+file.name+'" style="max-width:100%;border-radius:8px;margin:8px 0">';
                } else {
                    document.execCommand('insertHTML', false, '<img src="'+data.url+'" alt="'+file.name+'" style="max-width:100%;border-radius:8px;margin:8px 0">');
                }
                showToast('✅ 图片上传成功');
            } else {
                removeUploadingPlaceholder();
                showToast('❌ ' + (data.error || '上传失败'), 'error');
            }
        })
        .catch(() => {
            removeUploadingPlaceholder();
            showToast('❌ 网络错误', 'error');
        });
}

/**
 * 移除上传中占位符
 * 
 * @returns {void}
 */
function removeUploadingPlaceholder() {
    const el = editor().querySelector('.img-uploading');
    if (el) el.remove();
}

// 编辑器拖拽上传和粘贴上传
document.addEventListener('DOMContentLoaded', () => {
    const ed = editor();
    if (!ed) return;

    // 拖拽悬停
    ed.addEventListener('dragover', e => {
        e.preventDefault();
        e.stopPropagation();
        ed.style.borderColor = 'var(--accent)';
        ed.style.background = 'var(--bg-hover)';
    });
    
    // 拖拽离开
    ed.addEventListener('dragleave', e => {
        e.preventDefault();
        ed.style.borderColor = '';
        ed.style.background = '';
    });
    
    // 拖拽放下
    ed.addEventListener('drop', e => {
        e.preventDefault();
        e.stopPropagation();
        ed.style.borderColor = '';
        ed.style.background = '';
        const files = e.dataTransfer.files;
        for (const file of files) {
            if (file.type.startsWith('image/')) uploadImageFile(file);
        }
    });

    // 粘贴上传
    ed.addEventListener('paste', e => {
        const items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (const item of items) {
            if (item.type.startsWith('image/')) {
                e.preventDefault();
                const file = item.getAsFile();
                uploadImageFile(file);
                return;
            }
        }
    });

    // 输入时更新字数统计
    ed.addEventListener('input', updateWordCount);
});

/**
 * 更新字数统计
 * 
 * 统计字符数（不含空格）和词数。
 * 
 * @returns {void}
 */
function updateWordCount() {
    const ed = editor();
    const counter = document.getElementById('wordCount');
    if (!ed || !counter) return;
    const text = ed.innerText || '';
    const chars = text.replace(/\s/g, '').length;
    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
    counter.textContent = `${chars} 字 / ${words} 词`;
}

/**
 * 切换编辑器全屏模式
 * 
 * @returns {void}
 */
function toggleFullscreenEditor() {
    const modal = document.getElementById('noteModal');
    const btn = document.getElementById('fullscreenBtn');
    if (modal.classList.contains('fullscreen')) {
        modal.classList.remove('fullscreen');
        btn.textContent = '⛶';
        btn.title = '全屏编辑';
    } else {
        modal.classList.add('fullscreen');
        btn.textContent = '▭';
        btn.title = '退出全屏';
    }
    editor().focus();
}

/**
 * 关闭全屏编辑模式
 * 
 * @returns {void}
 */
function closeFullscreenEditor() {
    const modal = document.getElementById('noteModal');
    if (modal && modal.classList.contains('fullscreen')) {
        modal.classList.remove('fullscreen');
        const btn = document.getElementById('fullscreenBtn');
        if (btn) { btn.textContent = '⛶'; btn.title = '全屏编辑'; }
    }
}

/**
 * 插入表格
 * 
 * 弹窗输入行列数，生成带 thead 的表格。
 * 
 * @returns {void}
 */
function editorInsertTable() {
    const rows = prompt('行数:', '3');
    const cols = prompt('列数:', '3');
    if (!rows || !cols) return;
    let html = '<table><thead><tr>';
    for (let c = 0; c < parseInt(cols); c++) html += '<th>标题</th>';
    html += '</tr></thead><tbody>';
    for (let r = 1; r < parseInt(rows); r++) {
        html += '<tr>';
        for (let c = 0; c < parseInt(cols); c++) html += '<td>&nbsp;</td>';
        html += '</tr>';
    }
    html += '</tbody></table><p><br></p>';
    editor().focus();
    document.execCommand('insertHTML', false, html);
}

/**
 * 插入当前日期时间
 * 
 * 格式：YYYY-MM-DD HH:MM
 * 
 * @returns {void}
 */
function editorInsertDate() {
    const now = new Date();
    const str = now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0')+' '+String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');
    document.execCommand('insertText', false, str);
    editor().focus();
}

/**
 * 切换 Emoji 选择器
 * 
 * @returns {void}
 */
function toggleEmojiPicker() {
    const panel = document.getElementById('emojiPicker');
    panel.classList.toggle('active');
}

/**
 * 插入 Emoji
 * 
 * @param {string} emoji - Emoji 字符
 * @returns {void}
 * 
 * @example
 * insertEmoji('😀');
 */
function insertEmoji(emoji) {
    document.execCommand('insertText', false, emoji);
    document.getElementById('emojiPicker').classList.remove('active');
    editor().focus();
}
