/**
 * 密码保管箱 + 记事本（整合版）前端交互
 * 含富文本编辑器 - 增强版
 * @author 六斤Libra
 * @email 528.Libra@gmail.com
 */

// ===== 通用 =====
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function showToast(msg, type) {
  const t = document.createElement('div'); t.textContent = msg;
  const bg = type==='error' ? 'var(--danger)' : 'var(--bg-card)';
  const fg = type==='error' ? '#fff' : 'var(--text-primary)';
  t.style.cssText = `position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:${bg};color:${fg};padding:12px 28px;border-radius:10px;border:1px solid var(--border);z-index:9999;animation:fadeIn 0.3s ease;font-size:0.9rem;box-shadow:0 8px 30px rgba(0,0,0,0.4)`;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity 0.3s'; setTimeout(()=>t.remove(),300); }, 2500);
}
function deleteItem(id, module) {
  if (!confirm('确定删除？不可恢复！')) return;
  const f = document.createElement('form'); f.method='POST'; f.style.display='none';
  const csrf = document.querySelector('input[name=csrf_token]');
  f.innerHTML = '<input name="action" value="delete"><input name="id" value="'+id+'"><input name="module" value="'+module+'">' + (csrf ? '<input name="csrf_token" value="'+csrf.value+'">' : '');
  document.body.appendChild(f); f.submit();
}
// 弹窗关闭
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('active'); }));
document.addEventListener('keydown', e => { if(e.key==='Escape') { document.querySelectorAll('.modal-overlay.active').forEach(m=>m.classList.remove('active')); closeFullscreenEditor(); } });

// ===== 设置弹窗 =====
function showSettingsModal() {
  document.getElementById('settingsModal').classList.add('active');
}
function switchSettingsTab(tab) {
  document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
  document.querySelector(`.settings-tab[onclick*="${tab}"]`).classList.add('active');
  document.getElementById('panel-' + tab).classList.add('active');
}

// ===== 分类管理 =====
function showCategoryModal(type) {
  const title = type === 'pwd' ? '🔑 密码分类管理' : '📝 记事分类管理';
  document.getElementById('catModalTitle').textContent = title;
  document.getElementById('catType').value = type;
  document.getElementById('categoryModal').classList.add('active');
}
function renameCategoryPrompt(id, name) {
  document.getElementById('renameCatId').value = id;
  document.getElementById('renameCatName').value = name;
  document.getElementById('renameCatModal').classList.add('active');
}
function deleteCategoryConfirm(id, name) {
  document.getElementById('deleteCatId').value = id;
  document.getElementById('deleteCatName').textContent = name;
  document.getElementById('deleteCatModal').classList.add('active');
}

// ===== 密码模块 =====
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
function copyPassword(id) {
  fetch('index.php?reveal=1&id='+id).then(r=>r.json()).then(d => {
    if(d.success && d.password) {
      navigator.clipboard.writeText(d.password).then(()=>showToast('✅ 已复制')).catch(()=>{
        const i=document.createElement('textarea'); i.value=d.password; i.style.cssText='position:fixed;left:-9999px';
        document.body.appendChild(i); i.select(); document.execCommand('copy'); document.body.removeChild(i);
        showToast('✅ 已复制');
      });
    } else showToast('❌ 获取失败','error');
  }).catch(()=>showToast('❌ 网络错误','error'));
}
function togglePwdField() {
  const i = document.getElementById('pwdPassword'), b = document.querySelector('.toggle-pwd');
  if(i.type==='password'){i.type='text';b.textContent='🙈';}else{i.type='password';b.textContent='👁️';}
}
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

// ===== 记事本模块 =====
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
function syncEditorContent() {
  document.getElementById('noteContent').value = document.getElementById('noteEditor').innerHTML;
}
function showViewNoteModal(data) {
  // 向后端发送浏览计数请求
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
function togglePin(id) {
  const f=document.createElement('form'); f.method='POST'; f.style.display='none';
  const csrf = document.querySelector('input[name=csrf_token]');
  f.innerHTML='<input name="action" value="pin_note"><input name="id" value="'+id+'">' + (csrf ? '<input name="csrf_token" value="'+csrf.value+'">' : '');
  document.body.appendChild(f); f.submit();
}

// ===== 富文本编辑器 =====
const editor = () => document.getElementById('noteEditor');

function editorCmd(cmd, val) {
  document.execCommand(cmd, false, val || null);
  editor().focus();
}

function editorHeading(val) {
  if (!val) return;
  document.execCommand('formatBlock', false, val);
  editor().focus();
}

function editorFontSize(val) {
  if (!val) return;
  document.execCommand('fontSize', false, val);
  editor().focus();
}

function editorForeColor(color) {
  document.execCommand('foreColor', false, color);
  editor().focus();
}

function editorHighlight(color) {
  document.execCommand('hiliteColor', false, color);
  editor().focus();
}

function editorInsertHR() {
  document.execCommand('insertHorizontalRule');
  editor().focus();
}

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

function editorInsertImageByUrl() {
  const url = prompt('请输入图片地址:', 'https://');
  if (url) {
    document.execCommand('insertImage', false, url);
    editor().focus();
  }
}

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

function removeUploadingPlaceholder() {
  const el = editor().querySelector('.img-uploading');
  if (el) el.remove();
}

document.addEventListener('DOMContentLoaded', () => {
  const ed = editor();
  if (!ed) return;

  ed.addEventListener('dragover', e => {
    e.preventDefault();
    e.stopPropagation();
    ed.style.borderColor = 'var(--accent)';
    ed.style.background = 'var(--bg-hover)';
  });
  ed.addEventListener('dragleave', e => {
    e.preventDefault();
    ed.style.borderColor = '';
    ed.style.background = '';
  });
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

  ed.addEventListener('input', updateWordCount);
});

function updateWordCount() {
  const ed = editor();
  const counter = document.getElementById('wordCount');
  if (!ed || !counter) return;
  const text = ed.innerText || '';
  const chars = text.replace(/\s/g, '').length;
  const words = text.trim() ? text.trim().split(/\s+/).length : 0;
  counter.textContent = `${chars} 字 / ${words} 词`;
}

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
function closeFullscreenEditor() {
  const modal = document.getElementById('noteModal');
  if (modal && modal.classList.contains('fullscreen')) {
    modal.classList.remove('fullscreen');
    const btn = document.getElementById('fullscreenBtn');
    if (btn) { btn.textContent = '⛶'; btn.title = '全屏编辑'; }
  }
}

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

function editorInsertDate() {
  const now = new Date();
  const str = now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0')+' '+String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');
  document.execCommand('insertText', false, str);
  editor().focus();
}

function toggleEmojiPicker() {
  const panel = document.getElementById('emojiPicker');
  panel.classList.toggle('active');
}
function insertEmoji(emoji) {
  document.execCommand('insertText', false, emoji);
  document.getElementById('emojiPicker').classList.remove('active');
  editor().focus();
}
