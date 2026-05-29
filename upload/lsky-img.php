<?php
/**
 * 兰空图床上传功能
 * 
 * @package LskyPro for WordPress
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 检查用户是否有上传权限
 * 
 * @return bool 是否有上传权限
 */
function lskypro_user_can_upload() {
    if (!is_user_logged_in()) {
        return false;
    }
    
    $current_user = wp_get_current_user();
    $allowed_roles = get_option('lskypro_roles', array('administrator'));
    
    // 如果没有设置任何角色权限，默认只允许管理员
    if (empty($allowed_roles)) {
        $allowed_roles = array('administrator');
    }
    
    // 检查用户是否有允许的角色
    $has_role = false;
    foreach ($allowed_roles as $role) {
        if (in_array($role, $current_user->roles)) {
            $has_role = true;
            break;
        }
    }
    
    return $has_role;
}

/**
 * 检查兰空图床配置是否有效
 * 
 * @return array 状态和消息
 */
function lskypro_check_config() {
    $domain = get_option('domain');
    $tokens = get_option('tokens');
    $errors = array();
    
    if (empty($domain)) {
        $errors[] = '未配置API网址';
    } elseif (!filter_var($domain, FILTER_VALIDATE_URL)) {
        $errors[] = 'API网址格式无效';
    }
    
    if (empty($tokens)) {
        $errors[] = '未配置Tokens';
    }
    
    if (!empty($errors)) {
        return array(
            'valid' => false,
            'errors' => $errors
        );
    }
    
    return array(
        'valid' => true
    );
}

/**
 * 获取允许的文件类型
 * 
 * @return array 允许的文件类型
 */
if (!function_exists('lskypro_get_allowed_types')) {
    function lskypro_get_allowed_types() {
        $allowed_types = get_option('lskypro_allowed_types', array('jpg', 'jpeg', 'png', 'gif', 'webp'));
        return array_map('strtolower', $allowed_types);
    }
}

/**
 * 添加编辑器上传按钮
 */
add_action('media_buttons', 'lskypro_add_upload_button');
function lskypro_add_upload_button() {
    try {
        // 检查用户权限
        if (!lskypro_user_can_upload()) {
            return;
        }
        
        // 检查配置是否有效
        $config_check = lskypro_check_config();
        if (!$config_check['valid']) {
            echo '<a href="' . admin_url('admin.php?page=lskyupload_options') . '" class="button" style="color: #a00;">';
            echo '配置兰空图床';
            echo '<span class="dashicons dashicons-warning" style="margin: 3px 0 0 5px;"></span>';
            echo '</a>';
            return;
        }
        
        ?>
        <a href="javascript:;" class="button lsky-upload">
            <span class="dashicons dashicons-upload" style="margin: 3px 5px 0 0;"></span>
            上传至兰空图床
            <input type="file" id="input-lsky-upload" multiple accept="image/*" />
        </a>
        <span id="lskypro-upload-tip">上传图片过程中请耐心等待！请勿刷新页面</span>
        <?php
    } catch (Exception $e) {
        // 记录错误
        if (function_exists('lskypro_log_error')) {
            lskypro_log_error('添加上传按钮时出错: ' . $e->getMessage());
        }
    }
}

/**
 * 添加侧边栏上传框
 */
add_action('add_meta_boxes', 'lskypro_add_upload_box');
function lskypro_add_upload_box() {
    try {
        // 检查用户权限
        if (!lskypro_user_can_upload()) {
            return;
        }
        
        // 获取允许使用的文章类型
        $post_types = apply_filters('lskypro_post_types', array('post', 'page'));
        
        // 为每个文章类型添加元框
        foreach ($post_types as $post_type) {
            add_meta_box(
                'lsky_upload_box_tmp',
                '兰空图床(LskyPro)上传',
                'lskypro_upload_box_callback',
                $post_type,
                'side',
                'high'
            );
        }
    } catch (Exception $e) {
        // 记录错误
        if (function_exists('lskypro_log_error')) {
            lskypro_log_error('添加侧边栏上传框时出错: ' . $e->getMessage());
        }
    }
}

/**
 * 侧边栏上传框回调函数
 * 
 * @param WP_Post $post 当前编辑的文章对象
 */
function lskypro_upload_box_callback($post) {
    try {
        // 检查配置是否有效
        $config_check = lskypro_check_config();
        if (!$config_check['valid']) {
            echo '<p style="color:#a00;">请先<a href="' . admin_url('admin.php?page=lskyupload_options') . '">配置兰空图床</a>后使用</p>';
            if (!empty($config_check['errors'])) {
                echo '<ul style="color:#a00;margin-left:15px;list-style:disc;">';
                foreach ($config_check['errors'] as $error) {
                    echo '<li>' . esc_html($error) . '</li>';
                }
                echo '</ul>';
            }
            return;
        }
        
        // 获取允许的文件类型
        $allowed_types = lskypro_get_allowed_types();
        $allowed_types_str = implode(', ', $allowed_types);
        
        ?>
        <div id="lsky-upload-box">点击此区域上传图片</div>
        <input type="file" id="lsky-upload-box-input" multiple accept="image/*" />
        <div id="result"></div>
        <p class="description" style="margin-top:10px;font-style:italic;color:#666;">
            支持的文件类型: <?php echo esc_html($allowed_types_str); ?><br>
            最大文件大小: <?php echo intval(get_option('lskypro_max_size', 10)); ?>MB
        </p>
        <?php
    } catch (Exception $e) {
        // 记录错误
        if (function_exists('lskypro_log_error')) {
            lskypro_log_error('渲染上传框时出错: ' . $e->getMessage());
        }
        
        echo '<p style="color:#a00;">加载上传功能时出错，请刷新页面重试</p>';
    }
}

/**
 * 创建AJAX处理函数（预留扩展功能）
 */
add_action('wp_ajax_lskypro_check_settings', 'lskypro_ajax_check_settings');
function lskypro_ajax_check_settings() {
    try {
        // 检查安全性
        check_ajax_referer('lskypro-upload-nonce', 'nonce');
        
        // 检查用户权限
        if (!lskypro_user_can_upload()) {
            wp_send_json_error(array(
                'message' => '没有权限',
                'code' => 'permission_denied'
            ));
            return;
        }
        
        // 检查设置
        $config_check = lskypro_check_config();
        if (!$config_check['valid']) {
            wp_send_json_error(array(
                'message' => '兰空图床配置不完整',
                'errors' => $config_check['errors'],
                'code' => 'invalid_config'
            ));
            return;
        }
        
        wp_send_json_success(array(
            'message' => '设置已配置',
            'api_version' => get_option('lskypro_api_version', 'v1'),
            'allowed_types' => lskypro_get_allowed_types(),
            'max_size' => get_option('lskypro_max_size', 10)
        ));
    } catch (Exception $e) {
        // 记录错误
        if (function_exists('lskypro_log_error')) {
            lskypro_log_error('AJAX处理时出错: ' . $e->getMessage());
        }
        
        wp_send_json_error(array(
            'message' => '处理请求时出错',
            'code' => 'server_error'
        ));
    }
    
    wp_die();
}

/**
 * 测试兰空图床配置
 */
add_action('wp_ajax_lskypro_test_connection', 'lskypro_test_connection');
function lskypro_test_connection() {
    try {
        // 检查安全性
        check_ajax_referer('lskypro-upload-nonce', 'nonce');
        
        // 检查用户权限
        if (!lskypro_user_can_upload()) {
            wp_send_json_error(array(
                'message' => '没有权限',
                'code' => 'permission_denied'
            ));
            return;
        }
        
        // 优先从POST获取配置，其次从数据库获取
        $domain = isset($_POST['domain']) ? sanitize_url($_POST['domain']) : get_option('domain');
        $tokens = isset($_POST['tokens']) ? sanitize_text_field($_POST['tokens']) : get_option('tokens');
        $api_version = isset($_POST['api_version']) ? sanitize_text_field($_POST['api_version']) : get_option('lskypro_api_version', 'v1');
        
        if (empty($domain) || empty($tokens)) {
            wp_send_json_error(array(
                'message' => '请先配置API网址和Tokens',
                'code' => 'missing_config'
            ));
            return;
        }
        
        // 测试连接
        $url = ($api_version === 'v2') ? 
            $domain . '/api/v2/user/profile' : 
            $domain . '/api/' . $api_version . '/profile';
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $tokens,
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => '连接失败: ' . $response->get_error_message(),
                'code' => 'connection_error'
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // 检查响应
        if ($api_version === 'v1') {
            if (isset($data['status']) && $data['status']) {
                wp_send_json_success(array(
                    'message' => '连接成功',
                    'user' => isset($data['data']['name']) ? $data['data']['name'] : '未知用户'
                ));
            } else {
                wp_send_json_error(array(
                    'message' => isset($data['message']) ? $data['message'] : '验证失败',
                    'code' => 'api_error'
                ));
            }
        } else {
            // V2版本
            if (isset($data['status']) && $data['status'] === 'success') {
                wp_send_json_success(array(
                    'message' => '连接成功',
                    'user' => isset($data['data']['name']) ? $data['data']['name'] : '未知用户'
                ));
            } else {
                wp_send_json_error(array(
                    'message' => isset($data['message']) ? $data['message'] : '验证失败',
                    'code' => 'api_error'
                ));
            }
        }
    } catch (Exception $e) {
        // 记录错误
        if (function_exists('lskypro_log_error')) {
            lskypro_log_error('测试连接时出错: ' . $e->getMessage());
        }
        
        wp_send_json_error(array(
            'message' => '处理请求时出错: ' . $e->getMessage(),
            'code' => 'server_error'
        ));
    }
    
    wp_die();
}

/**
 * 获取存储策略列表
 */
add_action('wp_ajax_lskypro_get_strategies', 'lskypro_get_strategies');
function lskypro_get_strategies() {
    try {
        // 检查安全性
        check_ajax_referer('lskypro-upload-nonce', 'nonce');
        
        // 检查用户权限
        if (!lskypro_user_can_upload()) {
            wp_send_json_error(array(
                'message' => '没有权限',
                'code' => 'permission_denied'
            ));
            return;
        }
        
        // 优先从POST获取配置，其次从数据库获取
        $domain = isset($_POST['domain']) ? sanitize_url($_POST['domain']) : get_option('domain');
        $tokens = isset($_POST['tokens']) ? sanitize_text_field($_POST['tokens']) : get_option('tokens');
        // 策略列表API始终使用V1版本
        $api_version = 'v1';
        
        if (empty($domain) || empty($tokens)) {
            wp_send_json_error(array(
                'message' => '请先配置API网址和Tokens',
                'code' => 'missing_config'
            ));
            return;
        }
        
        // 获取策略列表
        $url = $domain . '/api/' . $api_version . '/strategies';
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $tokens,
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => '获取策略列表失败: ' . $response->get_error_message(),
                'code' => 'connection_error'
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // 处理响应
        if (isset($data['status']) && $data['status']) {
            $strategies = array();
            // 策略列表在 data.strategies 中
            $strategyList = isset($data['data']['strategies']) ? $data['data']['strategies'] : array();
            if (is_array($strategyList)) {
                foreach ($strategyList as $item) {
                    $strategies[] = array(
                        'id' => $item['id'],
                        'name' => $item['name']
                    );
                }
            }
            wp_send_json_success(array(
                'strategies' => $strategies
            ));
        } else {
            wp_send_json_error(array(
                'message' => isset($data['message']) ? $data['message'] : '获取策略列表失败',
                'code' => 'api_error'
            ));
        }
    } catch (Exception $e) {
        if (function_exists('lskypro_log_error')) {
            lskypro_log_error('获取策略列表时出错: ' . $e->getMessage());
        }
        
        wp_send_json_error(array(
            'message' => '处理请求时出错: ' . $e->getMessage(),
            'code' => 'server_error'
        ));
    }
    
    wp_die();
}

/**
 * 获取相册列表
 */
add_action('wp_ajax_lskypro_get_albums', 'lskypro_get_albums');
function lskypro_get_albums() {
    try {
        // 检查安全性
        check_ajax_referer('lskypro-upload-nonce', 'nonce');
        
        // 检查用户权限
        if (!lskypro_user_can_upload()) {
            wp_send_json_error(array(
                'message' => '没有权限',
                'code' => 'permission_denied'
            ));
            return;
        }
        
        // 优先从POST获取配置，其次从数据库获取
        $domain = isset($_POST['domain']) ? sanitize_url($_POST['domain']) : get_option('domain');
        $tokens = isset($_POST['tokens']) ? sanitize_text_field($_POST['tokens']) : get_option('tokens');
        // 相册列表API始终使用V1版本
        $api_version = 'v1';
        
        if (empty($domain) || empty($tokens)) {
            wp_send_json_error(array(
                'message' => '请先配置API网址和Tokens',
                'code' => 'missing_config'
            ));
            return;
        }
        
        // 获取相册列表
        $url = $domain . '/api/' . $api_version . '/albums';
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $tokens,
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => '获取相册列表失败: ' . $response->get_error_message(),
                'code' => 'connection_error'
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // 处理响应
        if (isset($data['status']) && $data['status']) {
            $albums = array();
            // 相册列表在 data.data 中（分页格式）
            $albumList = isset($data['data']['data']) ? $data['data']['data'] : array();
            if (is_array($albumList)) {
                foreach ($albumList as $item) {
                    $albums[] = array(
                        'id' => $item['id'],
                        'name' => $item['name']
                    );
                }
            }
            wp_send_json_success(array(
                'albums' => $albums
            ));
        } else {
            wp_send_json_error(array(
                'message' => isset($data['message']) ? $data['message'] : '获取相册列表失败',
                'code' => 'api_error'
            ));
        }
    } catch (Exception $e) {
        if (function_exists('lskypro_log_error')) {
            lskypro_log_error('获取相册列表时出错: ' . $e->getMessage());
        }
        
        wp_send_json_error(array(
            'message' => '处理请求时出错: ' . $e->getMessage(),
            'code' => 'server_error'
        ));
    }
    
    wp_die();
}
?>