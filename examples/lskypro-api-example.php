<?php
/**
 * 兰空图床上传插件API使用示例
 * 
 * 此文件演示如何在主题或其他插件中使用LskyPro上传功能
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 判断兰空图床上传插件是否已激活
 * 
 * @return bool
 */
function is_lskypro_active() {
    return function_exists('lskypro_user_can_upload');
}

/**
 * 上传图片到兰空图床（示例）
 * 
 * @param string $file_path 本地图片路径
 * @return array|WP_Error 成功返回图片信息数组，失败返回WP_Error
 */
function upload_to_lskypro($file_path) {
    // 检查插件是否激活
    if (!is_lskypro_active()) {
        return new WP_Error('plugin_inactive', '兰空图床上传插件未激活');
    }
    
    // 检查用户权限
    if (!function_exists('lskypro_user_can_upload') || !lskypro_user_can_upload()) {
        return new WP_Error('permission_denied', '当前用户无权使用兰空图床上传功能');
    }
    
    // 检查文件是否存在
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', '文件不存在');
    }
    
    // 获取兰空图床设置
    $domain = get_option('domain');
    $tokens = get_option('tokens');
    $permission = get_option('permission', '1');
    $api_version = get_option('lskypro_api_version', 'v1');
    
    if (empty($domain) || empty($tokens)) {
        return new WP_Error('config_missing', '兰空图床配置不完整，请先设置API网址和Tokens');
    }
    
    // 准备文件数据
    $file_data = file_get_contents($file_path);
    $file_name = basename($file_path);
    $boundary = wp_generate_password(24, false);
    
    // 构建请求体
    $body = "--$boundary\r\n";
    $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$file_name\"\r\n";
    $body .= "Content-Type: " . wp_check_filetype($file_name)['type'] . "\r\n\r\n";
    $body .= $file_data . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Disposition: form-data; name=\"permission\"\r\n\r\n";
    $body .= $permission . "\r\n";
    $body .= "--$boundary--\r\n";
    
    // 发送请求
    $response = wp_remote_post($domain . '/api/' . $api_version . '/upload', array(
        'headers' => array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            'Authorization' => 'Bearer ' . $tokens,
            'Accept' => 'application/json'
        ),
        'body' => $body,
        'timeout' => 60
    ));
    
    // 处理响应
    if (is_wp_error($response)) {
        return $response;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    // 根据API版本处理不同的返回格式
    if ($api_version === 'v2') {
        // V2版本API
        if (isset($data['status']) && $data['status'] === 'success') {
            $url = isset($data['data']['public_url']) ? $data['data']['public_url'] : 
                (isset($data['data']['pathname']) ? $data['data']['pathname'] : '');
            
            $name = isset($data['data']['name']) ? $data['data']['name'] : 
                (isset($data['data']['filename']) ? $data['data']['filename'] : '');
            
            $size = isset($data['data']['mimetype']) ? $data['data']['mimetype'] : 0;
            
            return array(
                'url' => $url,
                'name' => $name,
                'width' => isset($data['data']['width']) ? $data['data']['width'] : 0,
                'height' => isset($data['data']['height']) ? $data['data']['height'] : 0,
                'size' => $size,
                'extension' => isset($data['data']['extension']) ? $data['data']['extension'] : '',
                'md5' => isset($data['data']['md5']) ? $data['data']['md5'] : '',
                'sha1' => isset($data['data']['sha1']) ? $data['data']['sha1'] : ''
            );
        } else {
            $message = isset($data['message']) ? $data['message'] : '上传失败';
            return new WP_Error('upload_failed', $message);
        }
    } else {
        // V1版本API
        if (isset($data['status']) && $data['status']) {
            return array(
                'url' => $data['data']['links']['url'],
                'name' => $data['data']['origin_name'],
                'width' => $data['data']['width'],
                'height' => $data['data']['height'],
                'size' => $data['data']['size']
            );
        } else {
            $message = isset($data['message']) ? $data['message'] : '上传失败';
            return new WP_Error('upload_failed', $message);
        }
    }
}

/**
 * 示例：使用方法
 */
function lskypro_usage_example() {
    // 上传图片
    $result = upload_to_lskypro(ABSPATH . 'wp-content/uploads/2023/07/example.jpg');
    
    // 检查结果
    if (is_wp_error($result)) {
        echo '上传失败: ' . $result->get_error_message();
    } else {
        echo '上传成功! 图片链接: ' . $result['url'];
        
        // 插入到文章中
        $image_html = '<a href="' . esc_url($result['url']) . '"><img src="' . esc_url($result['url']) . '" alt="' . esc_attr($result['name']) . '" /></a>';
        
        // 可以将$image_html添加到文章内容中
    }
}

/**
 * 示例：自定义上传按钮
 */
function lskypro_custom_upload_button() {
    // 检查插件是否激活和用户权限
    if (!is_lskypro_active() || !lskypro_user_can_upload()) {
        return;
    }
    
    ?>
    <button type="button" class="button" id="custom-lskypro-button">
        <span class="dashicons dashicons-upload"></span> 
        上传到兰空图床
    </button>
    
    <script>
    jQuery(document).ready(function($) {
        $('#custom-lskypro-button').on('click', function() {
            // 创建隐藏的文件输入
            var fileInput = $('<input type="file" multiple accept="image/*" style="display:none">');
            $('body').append(fileInput);
            
            // 触发文件选择
            fileInput.trigger('click');
            
            // 处理文件选择
            fileInput.on('change', function() {
                if (typeof lskyproData !== 'undefined' && 
                    typeof handleFileUpload === 'function') {
                    
                    handleFileUpload(this.files, {
                        onSuccess: function(url, name) {
                            console.log('上传成功:', url);
                            // 这里处理上传成功后的操作
                        },
                        onError: function(error) {
                            console.error('上传失败:', error);
                            // 这里处理上传失败的情况
                        }
                    });
                } else {
                    alert('兰空图床上传功能未正确加载，请检查插件是否正常工作');
                }
                
                // 使用后移除文件输入
                fileInput.remove();
            });
        });
    });
    </script>
    <?php
}
?> 