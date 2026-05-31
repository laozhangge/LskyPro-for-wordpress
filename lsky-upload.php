<?php
/*
Plugin Name: 兰空图床上传
Plugin URI: https://github.com/laozhangge/LskyPro-for-wordpress
Description: 通过WordPress兰空图床插件复刻而来，支持粘贴上传和存储策略/相册选择。安装完成后先在插件设置中填写对应参数后再使用，若在使用过程中出现问题或者Bug请截图保存反馈至作者邮箱
Version: 1.3.7
Author: 老张博客
Author URI: https://laozhang.org
License: GPL v2 or later
Text Domain: lskypro-upload
*/

//-------------  还请各位大佬手下留情，不要改作者署名和作者链接，蟹蟹啦~~~ --------------

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 定义插件常量
define('LSKYPRO_VERSION', '1.3.7');
define('LSKYPRO_FILE', __FILE__);
define('LSKYPRO_PATH', plugin_dir_path(__FILE__));
define('LSKYPRO_URL', plugins_url('', __FILE__));
define('LSKYPRO_BASENAME', plugin_basename(__FILE__));
define('LSKYPRO_API_V1', 'v1');
define('LSKYPRO_API_V2', 'v2');

// 加载必要的文件
require_once LSKYPRO_PATH . 'includes/lsky-config.php';
require_once LSKYPRO_PATH . 'upload/lsky-img.php';

/**
 * 插件激活时的操作
 */
register_activation_hook(LSKYPRO_FILE, 'lskypro_activate');
function lskypro_activate() {
    // 设置默认选项
    add_option('lskypro_permission', '1');
    add_option('lskypro_roles', array('administrator'));
    add_option('lskypro_api_version', LSKYPRO_API_V1); // 默认使用V1版本API
    add_option('lskypro_max_size', 10); // 默认最大上传大小为10MB
    add_option('lskypro_allowed_types', array('jpg', 'jpeg', 'png', 'gif')); // 默认允许的文件类型
    add_option('lskypro_storage_id', ''); // 存储策略ID
    add_option('lskypro_album_id', ''); // 相册ID
}

/**
 * 插件卸载时的清理操作
 */
register_uninstall_hook(LSKYPRO_FILE, 'lskypro_uninstall');
function lskypro_uninstall() {
    // 删除所有插件相关选项（统一使用lskypro_前缀）
    delete_option('domain');
    delete_option('tokens');
    delete_option('permission');
    delete_option('lskypro_permission');
    delete_option('lskypro_roles');
    delete_option('lskypro_max_size');
    delete_option('lskypro_allowed_types');
    delete_option('lskypro_api_version');
    delete_option('lskypro_storage_id');
    delete_option('lskypro_album_id');
}

/**
 * 在插件列表页添加设置链接
 */
add_filter('plugin_action_links_' . LSKYPRO_BASENAME, 'lskypro_add_action_links');
function lskypro_add_action_links($actions) {
    $settings_link = '<a href="admin.php?page=lskyupload_options">' . __('Settings') . '</a>';
    
    // 添加设置链接和其他链接
    $actions = array_merge(
        array('settings' => $settings_link),
        array(
            'blog' => '<a href="https://laozhang.org" target="_blank">老张博客</a>',
            'lsky' => '<a href="https://www.lsky.pro/" target="_blank">兰空官网</a>'
        ),
        $actions
    );
    
    return $actions;
}

/**
 * 加载文本域用于翻译
 */
add_action('plugins_loaded', 'lskypro_load_textdomain');
function lskypro_load_textdomain() {
    load_plugin_textdomain('lskypro-upload', false, dirname(LSKYPRO_BASENAME) . '/languages');
}

/**
 * 获取允许的文件类型列表
 * 
 * @return array 允许的文件类型列表
 */
function lskypro_get_allowed_types() {
    $allowed_types = get_option('lskypro_allowed_types', array('jpg', 'jpeg', 'png', 'gif'));
    
    // 确保至少有一种允许的类型
    if (empty($allowed_types)) {
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
    }
    
    return $allowed_types;
}

/**
 * 加载插件所需的JavaScript和CSS文件
 */
add_action('admin_enqueue_scripts', 'lskypro_enqueue_scripts');
function lskypro_enqueue_scripts($hook) {
    // 只在文章编辑页和插件设置页加载资源
    if (!in_array($hook, array('post.php', 'post-new.php', 'admin_page_lskyupload_options'))) {
        return;
    }
    
    // 注册并加载样式
    wp_register_style('lskypro-admin-css', LSKYPRO_URL . '/includes/assets/style.css', array(), LSKYPRO_VERSION);
    wp_register_style('lskypro-upload-css', LSKYPRO_URL . '/upload/assets/style.css', array(), LSKYPRO_VERSION);
    wp_enqueue_style('lskypro-admin-css');
    wp_enqueue_style('lskypro-upload-css');
    
    // 注册并加载脚本
    wp_register_script('axios', LSKYPRO_URL . '/assets/axios.min.js', array(), '0.27.2', true);
    wp_register_script('lskypro-upload-js', LSKYPRO_URL . '/assets/lskypro-upload.js', array('axios'), LSKYPRO_VERSION, true);
    wp_register_script('lskypro-paste-upload-js', LSKYPRO_URL . '/assets/lskypro-paste-upload.js', array(), LSKYPRO_VERSION, true);
    
    wp_enqueue_script('axios');
    wp_enqueue_script('lskypro-upload-js');
    wp_enqueue_script('lskypro-paste-upload-js');
    
    // 传递PHP变量到JavaScript
    $lskypro_localize_data = array(
        'domain' => get_option('domain', ''),
        'tokens' => get_option('tokens', ''),
        'permission' => get_option('permission', '1'),
        'api_version' => get_option('lskypro_api_version', LSKYPRO_API_V1),
        'storage_id' => get_option('lskypro_storage_id', ''),
        'album_id' => get_option('lskypro_album_id', ''),
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('lskypro-upload-nonce'),
        'max_size' => get_option('lskypro_max_size', 10),
        'allowed_types' => lskypro_get_allowed_types()
    );
    wp_localize_script('lskypro-upload-js', 'lskyproData', $lskypro_localize_data);
    wp_localize_script('lskypro-paste-upload-js', 'lskyproData', $lskypro_localize_data);
}

/**
 * 记录错误日志
 * 
 * @param string $message 错误信息
 * @param mixed $data 相关数据
 */
function lskypro_log_error($message, $data = array()) {
    if (WP_DEBUG && WP_DEBUG_LOG) {
        error_log('[LskyPro Upload] ' . $message . (empty($data) ? '' : ' - ' . json_encode($data)));
    }
}
?>
