/**
 * LskyPro for WordPress 上传功能
 */
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        // 编辑器按钮上传功能
        initEditorUploader();
        
        // 侧边栏上传框功能
        initSidebarUploader();
    });
    
    /**
     * 初始化编辑器按钮上传
     */
    function initEditorUploader() {
        const uploadInput = document.getElementById('input-lsky-upload');
        const tipElement = document.getElementById('lskypro-upload-tip');
        
        if (!uploadInput) return;
        
        uploadInput.addEventListener('change', function() {
            handleFileUpload(uploadInput.files, {
                beforeUpload: function() {
                    uploadInput.disabled = true;
                    if (tipElement) {
                        tipElement.innerHTML = '正在上传中...';
                    }
                },
                onSuccess: function(url, name) {
                    // 安全转义URL和名称，防止XSS
                    var safeUrl = encodeURI(url);
                    var safeName = name.replace(/[&<>"']/g, function(c) {
                        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                    });
                    
                    // 插入图片到编辑器
                    if (typeof wp !== 'undefined') {
                        // Gutenberg编辑器的更可靠方法
                        if (wp.data && wp.data.select('core/editor')) {
                            // 获取当前编辑器选择状态
                            const editorSelection = wp.data.select('core/editor').getEditorSelection();
                            const currentContent = wp.data.select('core/editor').getEditedPostContent();
                            
                            // 创建图片HTML
                            const imageHTML = `<!-- wp:image -->
<figure class="wp-block-image"><img src="${safeUrl}" alt="${safeName}"/></figure>
<!-- /wp:image -->`;
                            
                            // 插入到当前内容
                            const newContent = currentContent + '\n' + imageHTML;
                            wp.data.dispatch('core/editor').resetEditorBlocks(
                                wp.blocks.parse(newContent)
                            );
                        } else if (wp.media && wp.media.editor) {
                            // 经典编辑器
                            wp.media.editor.insert(`<img src="${safeUrl}" alt="${safeName}" />`);
                        }
                    }
                    
                    uploadInput.disabled = false;
                    if (tipElement) {
                        tipElement.innerHTML = '上传成功！';
                        setTimeout(function() {
                            tipElement.innerHTML = '上传图片过程中请耐心等待！请勿刷新页面';
                        }, 3000);
                    }
                    uploadInput.value = '';
                },
                onError: function(error) {
                    console.error('上传失败:', error);
                    uploadInput.disabled = false;
                    if (tipElement) {
                        tipElement.innerHTML = '上传失败，请检查网络或图床设置！';
                    }
                    uploadInput.value = '';
                }
            });
        });
    }
    
    /**
     * 初始化侧边栏上传框
     */
    function initSidebarUploader() {
        const uploadBox = document.getElementById('lsky-upload-box');
        const uploadInput = document.getElementById('lsky-upload-box-input');
        const resultDiv = document.getElementById('result');
        
        if (!uploadBox || !uploadInput) return;
        
        // 点击上传框触发文件选择
        uploadBox.addEventListener('click', function() {
            uploadInput.click();
        });
        
        // 文件选择后处理上传
        uploadInput.addEventListener('change', function() {
            handleFileUpload(uploadInput.files, {
                beforeUpload: function() {
                    uploadInput.disabled = true;
                    uploadBox.innerHTML = '正在上传中...';
                },
                onSuccess: function(url, name) {
                    // 安全转义URL和名称，防止XSS
                    var safeUrl = encodeURI(url);
                    var safeName = name.replace(/[&<>"']/g, function(c) {
                        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                    });
                    
                    // 确保结果区域存在
                    if (resultDiv) {
                        // 清空结果区域
                        resultDiv.innerHTML = '';
                        
                        // 安全创建各格式结果（用DOM API代替innerHTML拼接，防止XSS）
                        var formats = [
                            { label: '图片URL：', value: url },
                            { label: 'HTML代码：', value: '<img src="' + url + '" alt="' + name + '" />' },
                            { label: 'Markdown：', value: '![' + name + '](' + url + ')' },
                            { label: 'BBCode：', value: '[img]' + url + '[/img]' }
                        ];
                        
                        formats.forEach(function(fmt) {
                            // 标签
                            var labelDiv = document.createElement('div');
                            labelDiv.className = 'lskypro-result-label';
                            labelDiv.textContent = fmt.label;
                            resultDiv.appendChild(labelDiv);
                            
                            // 输入框
                            var input = document.createElement('input');
                            input.type = 'text';
                            input.className = 'lskypro-result-input';
                            input.value = fmt.value;
                            input.readOnly = true;
                            resultDiv.appendChild(input);
                            
                            // 点击复制
                            input.addEventListener('click', function() {
                                this.select();
                                
                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText(this.value).then(function() {
                                        showNotification('已复制到剪贴板');
                                    }).catch(function() {
                                        showNotification('复制失败');
                                    });
                                } else {
                                    try {
                                        if (document.execCommand('copy')) {
                                            showNotification('已复制到剪贴板');
                                        } else {
                                            showNotification('复制失败');
                                        }
                                    } catch (err) {
                                        showNotification('复制失败');
                                    }
                                }
                            });
                        });
                    }
                    
                    // 重置上传框状态
                    uploadInput.disabled = false;
                    uploadBox.innerHTML = '点击此区域上传图片';
                    uploadInput.value = '';
                },
                onError: function(error) {
                    console.error('上传失败:', error);
                    uploadInput.disabled = false;
                    uploadBox.innerHTML = '上传失败，点击重试';
                    uploadInput.value = '';
                }
            });
        });
    }
    
    /**
     * 处理文件上传
     * 
     * @param {FileList} files 要上传的文件列表
     * @param {Object} callbacks 回调函数对象
     */
    function handleFileUpload(files, callbacks) {
        if (!files || files.length === 0) return;
        
        // 获取全局变量中的配置
        const domain = lskyproData.domain;
        const tokens = lskyproData.tokens;
        const permission = lskyproData.permission;
        const apiVersion = lskyproData.api_version || 'v1';
        const allowedTypes = lskyproData.allowed_types || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        
        // 检查必要配置
        if (!domain || !tokens) {
            alert('请先在插件设置中配置API网址和Tokens！');
            return;
        }
        
        // 在上传前执行回调
        if (callbacks.beforeUpload) {
            callbacks.beforeUpload();
        }
        
        // 上传每个文件
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // 检查文件类型
            if (!isValidImageType(file, allowedTypes)) {
                if (callbacks.onError) {
                    callbacks.onError('不支持的文件类型，请上传允许的图片格式: ' + allowedTypes.join(', '));
                }
                continue;
            }
            
            // 检查文件大小
            const maxSize = parseInt(lskyproData.max_size) || 10; // 默认10MB
            if (file.size > maxSize * 1024 * 1024) {
                if (callbacks.onError) {
                    callbacks.onError('文件过大，最大支持 ' + maxSize + 'MB');
                }
                continue;
            }
            
            const formData = new FormData();
            formData.append('file', file);

            // 根据API版本添加不同参数
            if (apiVersion === 'v2') {
                // V2版本参数
                formData.append('storage_id', lskyproData.storage_id || '1'); // 存储ID
                
                // 可选参数
                if (lskyproData.album_id) {
                    formData.append('album_id', lskyproData.album_id);
                }
                
                // 设置公开或私有
                if (permission === '1') {
                    formData.append('is_public', 1); // 使用1代替true
                } else {
                    formData.append('is_public', 0); // 使用0代替false
                }
            } else {
                // V1版本参数
                formData.append('permission', permission);
            }
            
            // 发送上传请求
            axios({
                method: 'post',
                url: domain + '/api/' + apiVersion + '/upload',
                data: formData,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'multipart/form-data',
                    'Authorization': 'Bearer ' + tokens
                }
            }).then(function(response) {
                const data = response.data;
                
                // 根据API版本处理不同的返回格式
                if (apiVersion === 'v2') {
                    // V2版本API返回格式处理
                    if (data.status === 'success') {
                        // V2版本使用 public_url 作为图片URL
                        const url = data.data.public_url || '';
                        const name = data.data.name || data.data.filename || '';
                        
                        if (callbacks.onSuccess && url) {
                            callbacks.onSuccess(url, name);
                        } else {
                            callbacks.onError('无法获取上传图片链接');
                        }
                    } else {
                        if (callbacks.onError) {
                            callbacks.onError(data.message || '上传失败');
                        }
                    }
                } else {
                    // V1版本API返回格式处理
                    if (data.status) {
                        const url = data.data.links.url;
                        const name = data.data.origin_name;
                        
                        if (callbacks.onSuccess) {
                            callbacks.onSuccess(url, name);
                        }
                    } else {
                        if (callbacks.onError) {
                            callbacks.onError(data.message || '上传失败');
                        }
                    }
                }
            }).catch(function(error) {
                console.error('上传请求出错:', error);
                let errorMsg = error.message || '网络请求失败';
                
                // 添加这段代码以显示详细错误
                if (error.response && error.response.data) {
                    console.log('错误详情:', error.response.data);
                    // 如果有详细错误信息，使用它
                    if (error.response.data.message) {
                        errorMsg = error.response.data.message;
                    }
                    // 如果有错误字段信息
                    if (error.response.data.data && error.response.data.data.errors) {
                        console.log('字段错误:', error.response.data.data.errors);
                    }
                }
                
                if (callbacks.onError) {
                    callbacks.onError(errorMsg);
                }
            });
        }
    }
    
    /**
     * 检查文件类型是否为有效的图片类型
     * 
     * @param {File} file 文件对象
     * @param {Array} allowedTypes 允许的文件类型扩展名数组
     * @return {boolean} 是否为有效图片类型
     */
    function isValidImageType(file, allowedTypes) {
        // 从文件类型映射到文件扩展名
        const mimeToExt = {
            'image/jpeg': ['jpg', 'jpeg'],
            'image/png': ['png'],
            'image/gif': ['gif'],
            'image/webp': ['webp'],
            'image/bmp': ['bmp']
        };
        
        // 获取文件的MIME类型
        const mimeType = file.type;
        
        // 检查MIME类型是否在映射表中
        if (mimeToExt[mimeType]) {
            // 检查对应的扩展名是否在允许列表中
            for (let i = 0; i < mimeToExt[mimeType].length; i++) {
                const ext = mimeToExt[mimeType][i];
                if (allowedTypes.indexOf(ext) !== -1) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * 显示通知消息
     * 
     * @param {string} message 要显示的消息
     */
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'lskypro-notification';
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // 2秒后自动移除
        setTimeout(function() {
            notification.style.opacity = '0';
            setTimeout(function() {
                document.body.removeChild(notification);
            }, 500);
        }, 2000);
    }

    // 为不支持Clipboard API的浏览器提供回退方法
    function legacyCopyToClipboard(element) {
        try {
            element.select();
            const successful = document.execCommand('copy');
            if (successful) {
                showNotification('已复制到剪贴板');
            } else {
                showNotification('复制失败，请手动复制');
            }
        } catch (err) {
            console.error('复制失败:', err);
            showNotification('复制失败，请手动复制');
        }
    }
})(); 