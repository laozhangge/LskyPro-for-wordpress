/**
 * LskyPro 粘贴上传功能
 * 支持在WordPress编辑器中直接粘贴图片自动上传到兰空图床
 */
(function() {
    'use strict';

    // 全局配置检查
    if (typeof lskyproData === 'undefined') {
        console.log('[LskyPro] 配置未加载，跳过初始化');
        return;
    }

    console.log('[LskyPro] 粘贴上传模块已加载');

    /**
     * 上传图片到兰空图床
     */
    function uploadImageToLsky(file, callback) {
        if (!lskyproData.domain || !lskyproData.tokens) {
            showNotification('请先配置兰空图床API网址和Tokens！', 'error');
            return;
        }

        var maxSize = parseInt(lskyproData.max_size) || 10;
        if (file.size > maxSize * 1024 * 1024) {
            showNotification('文件过大，最大支持 ' + maxSize + 'MB', 'error');
            return;
        }

        showNotification('正在上传图片到兰空图床...', 'info');
        console.log('[LskyPro] 开始上传:', file.name);

        var formData = new FormData();
        formData.append('file', file);

        var apiVersion = lskyproData.api_version || 'v1';
        if (apiVersion === 'v2') {
            if (lskyproData.storage_id) {
                formData.append('storage_id', lskyproData.storage_id);
            }
            if (lskyproData.album_id) {
                formData.append('album_id', lskyproData.album_id);
            }
            formData.append('is_public', lskyproData.permission === '1' ? 1 : 0);
        } else {
            formData.append('permission', lskyproData.permission || '1');
            if (lskyproData.album_id) {
                formData.append('album_id', lskyproData.album_id);
            }
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', lskyproData.domain + '/api/' + apiVersion + '/upload', true);
        xhr.setRequestHeader('Authorization', 'Bearer ' + lskyproData.tokens);
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.onload = function() {
            console.log('[LskyPro] 响应状态:', xhr.status);
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    console.log('[LskyPro] 响应:', data);
                    
                    var url = '';
                    if (apiVersion === 'v2') {
                        if (data.status === 'success') {
                            url = data.data.public_url || data.data.url || '';
                        }
                    } else {
                        if (data.status) {
                            url = data.data.links.url;
                        }
                    }
                    
                    if (url) {
                        showNotification('图片上传成功！', 'success');
                        callback(url);
                    } else {
                        showNotification('上传失败：' + (data.message || '未知错误'), 'error');
                    }
                } catch(e) {
                    showNotification('上传失败：响应格式错误', 'error');
                }
            } else {
                showNotification('上传失败：HTTP ' + xhr.status, 'error');
            }
        };

        xhr.onerror = function() {
            showNotification('网络错误，请检查连接', 'error');
        };

        xhr.send(formData);
    }

    /**
     * 插入图片到编辑器
     */
    function insertImageToEditor(url) {
        var safeUrl = encodeURI(url);
        var imgHtml = '<img src="' + safeUrl + '" alt="" />';
        
        // 尝试TinyMCE编辑器
        if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
            var editor = tinymce.activeEditor;
            if (!editor.isHidden()) {
                editor.insertContent(imgHtml);
                console.log('[LskyPro] 已插入到TinyMCE');
                return;
            }
        }
        
        // 尝试textarea
        var textarea = document.getElementById('content');
        if (textarea) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            textarea.value = textarea.value.substring(0, start) + imgHtml + textarea.value.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + imgHtml.length;
            textarea.focus();
            console.log('[LskyPro] 已插入到textarea');
            return;
        }
        
        // Gutenberg
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            try {
                var block = wp.blocks.createBlock('core/image', { url: url, alt: '' });
                wp.data.dispatch('core/editor').insertBlock(block);
                console.log('[LskyPro] 已插入到Gutenberg');
                return;
            } catch(e) {
                console.error('[LskyPro] Gutenberg插入失败:', e);
            }
        }
    }

    /**
     * 检测并处理图片粘贴
     */
    function detectImagePaste(clipboardData, callback) {
        if (!clipboardData) return false;
        
        var items = clipboardData.items;
        if (!items) return false;

        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                var file = items[i].getAsFile();
                console.log('[LskyPro] 检测到图片:', file.name, file.size);
                callback(file);
                return true;
            }
        }
        return false;
    }

    /**
     * 显示通知
     */
    function showNotification(message, type) {
        var existing = document.querySelector('.lskypro-paste-notification');
        if (existing) existing.remove();

        var el = document.createElement('div');
        el.className = 'lskypro-paste-notification';
        el.textContent = message;
        el.style.cssText = 'position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:4px;color:#fff;font-size:14px;z-index:999999;box-shadow:0 2px 10px rgba(0,0,0,0.2);';
        
        if (type === 'success') el.style.backgroundColor = '#46b450';
        else if (type === 'error') el.style.backgroundColor = '#dc3232';
        else el.style.backgroundColor = '#0073aa';

        document.body.appendChild(el);
        setTimeout(function() {
            el.style.opacity = '0';
            setTimeout(function() { if (el.parentNode) el.remove(); }, 300);
        }, 3000);
    }

    /**
     * 初始化
     */
    function init() {
        console.log('[LskyPro] 初始化粘贴上传');
        
        // 方法1: 监听TinyMCE编辑器
        function setupTinyMCE() {
            if (typeof tinymce === 'undefined') {
                console.log('[LskyPro] TinyMCE未加载');
                return;
            }
            
            // 获取所有TinyMCE编辑器
            tinymce.editors.forEach(function(editor) {
                if (editor._lskyproBound) return;
                editor._lskyproBound = true;
                
                console.log('[LskyPro] 绑定TinyMCE编辑器:', editor.id);
                
                editor.on('paste', function(e) {
                    console.log('[LskyPro] TinyMCE paste事件');
                    
                    // 获取剪贴板数据
                    var cd = e.clipboardData || (e.originalEvent && e.originalEvent.clipboardData) || window.clipboardData;
                    
                    if (cd && cd.items) {
                        for (var i = 0; i < cd.items.length; i++) {
                            if (cd.items[i].type.indexOf('image') !== -1) {
                                e.preventDefault();
                                var file = cd.items[i].getAsFile();
                                console.log('[LskyPro] TinyMCE检测到图片:', file.name);
                                uploadImageToLsky(file, function(url) {
                                    var safeUrl = encodeURI(url);
                                    editor.insertContent('<img src="' + safeUrl + '" alt="" />');
                                });
                                return;
                            }
                        }
                    }
                });
            });
        }
        
        // 方法2: 监听textarea (HTML模式)
        function setupTextarea() {
            var textarea = document.getElementById('content');
            if (!textarea || textarea._lskyproBound) return;
            textarea._lskyproBound = true;
            
            console.log('[LskyPro] 绑定textarea');
            textarea.addEventListener('paste', function(e) {
                console.log('[LskyPro] textarea paste事件');
                detectImagePaste(e.clipboardData || window.clipboardData, function(file) {
                    e.preventDefault();
                    uploadImageToLsky(file, function(url) {
                        var safeUrl = encodeURI(url);
                        var imgHtml = '<img src="' + safeUrl + '" alt="" />';
                        var start = textarea.selectionStart;
                        textarea.value = textarea.value.substring(0, start) + imgHtml + textarea.value.substring(start);
                        textarea.selectionStart = textarea.selectionEnd = start + imgHtml.length;
                    });
                });
            });
        }
        
        // 方法3: 监听Gutenberg
        function setupGutenberg() {
            var gutenberg = document.querySelector('.block-editor-writing-flow');
            if (!gutenberg || gutenberg._lskyproBound) return;
            gutenberg._lskyproBound = true;
            
            console.log('[LskyPro] 绑定Gutenberg');
            document.addEventListener('paste', function(e) {
                if (!e.target.closest('.block-editor-writing-flow')) return;
                
                console.log('[LskyPro] Gutenberg paste事件');
                detectImagePaste(e.clipboardData || window.clipboardData, function(file) {
                    e.preventDefault();
                    e.stopPropagation();
                    uploadImageToLsky(file, function(url) {
                        insertImageToEditor(url);
                    });
                });
            }, true);
        }
        
        // 监听TinyMCE编辑器创建事件
        if (typeof tinymce !== 'undefined') {
            tinymce.on('AddEditor', function(e) {
                console.log('[LskyPro] TinyMCE编辑器创建:', e.editor.id);
                setTimeout(function() {
                    setupTinyMCE();
                }, 500);
            });
        }
        
        // 初始绑定
        setupTinyMCE();
        setupTextarea();
        setupGutenberg();
        
        // 定期检查新编辑器（防止TinyMCE延迟加载）
        var checkCount = 0;
        var checkInterval = setInterval(function() {
            checkCount++;
            if (checkCount > 20) { // 10秒后停止
                clearInterval(checkInterval);
                return;
            }
            setupTinyMCE();
            setupTextarea();
        }, 500);
    }

    // 启动
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
