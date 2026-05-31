<?php
/**
 * 兰空图床配置文件
 * 
 * @package LskyPro for WordPress
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册设置菜单
 */
add_action('admin_menu', 'lskyupload_menu_page');
function lskyupload_menu_page() {
    add_menu_page(
        '兰空图床设置',
        '兰空图床设置',
        'manage_options',
        'lskyupload_options',
        'lskyupload_options_page',
        'dashicons-format-image',
        99
    );
    
    // 注册设置
    register_setting('lskypro_settings_group', 'domain', 'sanitize_url');
    register_setting('lskypro_settings_group', 'tokens', 'sanitize_text_field');
    register_setting('lskypro_settings_group', 'permission', 'absint');
    register_setting('lskypro_settings_group', 'lskypro_max_size', 'absint');
    register_setting('lskypro_settings_group', 'lskypro_roles', 'lskypro_sanitize_roles');
    register_setting('lskypro_settings_group', 'lskypro_allowed_types', 'lskypro_sanitize_types');
    register_setting('lskypro_settings_group', 'lskypro_api_version', 'sanitize_text_field');
    register_setting('lskypro_settings_group', 'lskypro_storage_id', 'absint');
    register_setting('lskypro_settings_group', 'lskypro_album_id', 'absint');
}

/**
 * 角色数组清理函数
 * 
 * @param array $roles 角色数组
 * @return array 清理后的角色数组
 */
function lskypro_sanitize_roles($roles) {
    if (!is_array($roles)) {
        return array('administrator');
    }
    
    $valid_roles = array_keys(wp_roles()->roles);
    return array_intersect($roles, $valid_roles);
}

/**
 * 图片类型数组清理函数
 * 
 * @param array $types 图片类型数组
 * @return array 清理后的图片类型数组
 */
function lskypro_sanitize_types($types) {
    if (!is_array($types)) {
        return array('jpg', 'jpeg', 'png', 'gif');
    }
    
    $valid_types = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
    return array_intersect($types, $valid_types);
}

/**
 * 设置页面内容
 */
function lskyupload_options_page() {
    // 处理设置提交（settings_fields已生成nonce，由WordPress Settings API处理验证）
    $updated = false;
    if (isset($_POST['option_page']) && $_POST['option_page'] === 'lskypro_settings_group') {
        $updated = true;
    }
    ?>
    <div class="wrap">
        <h1>兰空图床(LskyPro)上传设置</h1>
        
        <?php if ($updated): ?>
            <div class="notice notice-success is-dismissible">
                <p>设置已保存成功！</p>
            </div>
        <?php endif; ?>
        
        <div id="lskypro-connection-result" style="display:none;margin:15px 0;padding:10px 15px;border-left:4px solid;background:#fff;"></div>
        
        <form method="post" action="options.php">
            <?php 
            settings_fields('lskypro_settings_group');
            ?>
            
            <div class="lskypro-settings-container">
                <div class="lskypro-settings-section">
                    <h2>基本设置</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">API网址设置</th>
                            <td>
                                <input type="url" class="regular-text" name="domain" id="lskypro-domain" value="<?php echo esc_attr(get_option('domain', '')); ?>" required />
                                <p class="description">填写要对接图床的域名，必须带有http://或https://，例如：https://www.lsky.pro</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">图床Tokens</th>
                            <td>
                                <input type="text" class="regular-text" name="tokens" id="lskypro-tokens" value="<?php echo esc_attr(get_option('tokens', '')); ?>" required />
                                <p class="description">填写图床后台获取的Tokens，例如：1|1bJbwlqBfnggmOMEZqXT5XusaIwqiZjCDs7r1Ob5</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">API版本</th>
                            <td>
                                <select name="lskypro_api_version" id="lskypro-api-version">
                                    <option value="v1" <?php selected(get_option('lskypro_api_version', LSKYPRO_API_V1), LSKYPRO_API_V1); ?>>V1 (旧版本兰空图床)</option>
                                    <option value="v2" <?php selected(get_option('lskypro_api_version', LSKYPRO_API_V1), LSKYPRO_API_V2); ?>>V2 (新版本兰空图床)</option>
                                </select>
                                <p class="description">选择兰空图床的API版本，根据您使用的兰空图床版本选择</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">图片权限</th>
                            <td>
                                <select name="permission">
                                    <option value="1" <?php selected(get_option('permission', '1'), '1'); ?>>公开</option>
                                    <option value="0" <?php selected(get_option('permission', '1'), '0'); ?>>私有</option>
                                </select>
                                <p class="description">设置上传图片的权限状态</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">存储策略 (V2版本必填)</th>
                            <td>
                                <select name="lskypro_storage_id" id="lskypro-storage-id">
                                    <option value="">请先测试连接</option>
                                </select>
                                <button type="button" id="lskypro-load-strategies" class="button" style="margin-left:10px;" disabled>刷新列表</button>
                                <span id="lskypro-strategies-loading" style="display:none;margin-left:10px;">加载中...</span>
                                <p class="description">兰空图床的存储策略，测试连接后自动加载</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">上传相册</th>
                            <td>
                                <select name="lskypro_album_id" id="lskypro-album-id">
                                    <option value="">不指定相册</option>
                                </select>
                                <button type="button" id="lskypro-load-albums" class="button" style="margin-left:10px;" disabled>刷新列表</button>
                                <span id="lskypro-albums-loading" style="display:none;margin-left:10px;">加载中...</span>
                                <p class="description">选择上传到指定相册，测试连接后自动加载</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">测试连接</th>
                            <td>
                                <button type="button" id="lskypro-test-connection" class="button">测试连接</button>
                                <span id="lskypro-test-loading" style="display:none;margin-left:10px;">正在测试...</span>
                                <p class="description">测试API连接是否正常，请先填写API网址、Tokens和选择API版本</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="lskypro-settings-section">
                    <h2>高级设置</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">允许使用的角色</th>
                            <td>
                                <?php
                                $allowed_roles = get_option('lskypro_roles', array('administrator'));
                                foreach (wp_roles()->roles as $role_key => $role):
                                    $checked = in_array($role_key, $allowed_roles) ? 'checked' : '';
                                ?>
                                <label>
                                    <input type="checkbox" name="lskypro_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php echo $checked; ?> />
                                    <?php echo translate_user_role($role['name']); ?>
                                </label><br/>
                                <?php endforeach; ?>
                                <p class="description">选择哪些用户角色可以使用兰空图床上传功能</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">最大上传大小(MB)</th>
                            <td>
                                <input type="number" class="small-text" name="lskypro_max_size" min="1" max="100" value="<?php echo intval(get_option('lskypro_max_size', 10)); ?>" />
                                <p class="description">设置允许上传的最大文件大小（单位：MB）</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">允许的图片类型</th>
                            <td>
                                <?php
                                $image_types = array(
                                    'jpg' => 'JPG/JPEG',
                                    'png' => 'PNG',
                                    'gif' => 'GIF',
                                    'webp' => 'WEBP',
                                    'bmp' => 'BMP'
                                );
                                $allowed_types = get_option('lskypro_allowed_types', array('jpg', 'jpeg', 'png', 'gif'));
                                
                                foreach ($image_types as $type => $label):
                                    $checked = in_array($type, $allowed_types) ? 'checked' : '';
                                ?>
                                <label>
                                    <input type="checkbox" name="lskypro_allowed_types[]" value="<?php echo esc_attr($type); ?>" <?php echo $checked; ?> />
                                    <?php echo esc_html($label); ?>
                                </label><br/>
                                <?php endforeach; ?>
                                <p class="description">选择允许上传的图片类型</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="lskypro-settings-section">
                    <h2>关于</h2>
                    <p>兰空图床上传 v<?php echo LSKYPRO_VERSION; ?></p>
                    <p>作者：<a href="https://laozhang.org" target="_blank">老张博客</a></p>
                    <p>插件主页：<a href="https://github.com/laozhangge/LskyPro-for-wordpress" target="_blank">GitHub</a></p>
                    <p>兰空图床官网：<a href="https://www.lsky.pro/" target="_blank">https://www.lsky.pro/</a></p>
                </div>
                
                <p class="submit">
                    <?php submit_button('保存设置', 'primary', 'submit', false); ?>
                    <span id="lskypro-saving" style="display: none; margin-left: 10px;">正在保存...</span>
                </p>
            </div>
        </form>
        
        <style>
            .lskypro-settings-container {
                max-width: 800px;
            }
            .lskypro-settings-section {
                background: #fff;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .lskypro-settings-section h2 {
                margin-top: 0;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                var nonce = '<?php echo wp_create_nonce('lskypro-upload-nonce'); ?>';
                var currentStorageId = '<?php echo esc_attr(get_option('lskypro_storage_id', '')); ?>';
                var currentAlbumId = '<?php echo esc_attr(get_option('lskypro_album_id', '')); ?>';
                
                // 表单提交处理
                $('form').on('submit', function() {
                    $('#submit').prop('disabled', true);
                    $('#lskypro-saving').show();
                });
                
                // 加载存储策略列表
                function loadStrategies() {
                    $('#lskypro-strategies-loading').show();
                    $('#lskypro-load-strategies').prop('disabled', true);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'lskypro_get_strategies',
                            nonce: nonce
                        },
                        success: function(response) {
                            var select = $('#lskypro-storage-id');
                            select.empty().append('<option value="">请选择存储策略</option>');
                            
                            if (response.success && response.data.strategies) {
                                $.each(response.data.strategies, function(i, item) {
                                    var selected = (item.id == currentStorageId) ? 'selected' : '';
                                    var option = $('<option>').val(item.id).text(item.name);
                                    if (selected) option.prop('selected', true);
                                    select.append(option);
                                });
                                $('#lskypro-load-strategies').prop('disabled', false);
                            } else {
                                select.append('<option value="">加载失败</option>');
                            }
                        },
                        error: function() {
                            var select = $('#lskypro-storage-id');
                            select.empty().append('<option value="">加载失败</option>');
                        },
                        complete: function() {
                            $('#lskypro-strategies-loading').hide();
                        }
                    });
                }
                
                // 加载相册列表
                function loadAlbums() {
                    $('#lskypro-albums-loading').show();
                    $('#lskypro-load-albums').prop('disabled', true);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'lskypro_get_albums',
                            nonce: nonce
                        },
                        success: function(response) {
                            var select = $('#lskypro-album-id');
                            select.empty().append('<option value="">不指定相册</option>');
                            
                            if (response.success && response.data.albums) {
                                $.each(response.data.albums, function(i, item) {
                                    var selected = (item.id == currentAlbumId) ? 'selected' : '';
                                    var option = $('<option>').val(item.id).text(item.name);
                                    if (selected) option.prop('selected', true);
                                    select.append(option);
                                });
                                $('#lskypro-load-albums').prop('disabled', false);
                            } else {
                                select.append('<option value="">加载失败</option>');
                            }
                        },
                        error: function() {
                            var select = $('#lskypro-album-id');
                            select.empty().append('<option value="">加载失败</option>');
                        },
                        complete: function() {
                            $('#lskypro-albums-loading').hide();
                        }
                    });
                }
                
                // 刷新按钮点击事件
                $('#lskypro-load-strategies').on('click', loadStrategies);
                $('#lskypro-load-albums').on('click', loadAlbums);
                
                // 测试连接
                $('#lskypro-test-connection').on('click', function() {
                    var resultBox = $('#lskypro-connection-result');
                    
                    // 显示加载中
                    $('#lskypro-test-loading').show();
                    $('#lskypro-test-connection').prop('disabled', true);
                    resultBox.hide();
                    
                    // 发送AJAX请求（后端从数据库读取配置，防止SSRF）
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'lskypro_test_connection',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                resultBox.removeClass('notice-error').addClass('notice-success')
                                    .html('<p><strong>成功：</strong>' + response.data.message + 
                                    (response.data.user ? '，欢迎 ' + response.data.user : '') + '</p>')
                                    .show();
                                
                                // 连接成功后自动加载策略和相册列表
                                loadStrategies();
                                loadAlbums();
                            } else {
                                resultBox.removeClass('notice-success').addClass('notice-error')
                                    .html('<p><strong>错误：</strong>' + (response.data ? response.data.message : '连接失败') + '</p>')
                                    .show();
                            }
                        },
                        error: function() {
                            resultBox.removeClass('notice-success').addClass('notice-error')
                                .html('<p><strong>错误：</strong>请先保存设置后再测试连接</p>')
                                .show();
                        },
                        complete: function() {
                            $('#lskypro-test-loading').hide();
                            $('#lskypro-test-connection').prop('disabled', false);
                        }
                    });
                });
                
                // 确保至少选择一个图片类型
                $('form').on('submit', function(e) {
                    var checkedTypes = $('input[name="lskypro_allowed_types[]"]:checked').length;
                    if (checkedTypes === 0) {
                        e.preventDefault();
                        alert('请至少选择一种允许上传的图片类型');
                        return false;
                    }
                });
                
                // 确保至少选择一个角色
                $('form').on('submit', function(e) {
                    var checkedRoles = $('input[name="lskypro_roles[]"]:checked').length;
                    if (checkedRoles === 0) {
                        e.preventDefault();
                        alert('请至少选择一个允许使用的用户角色');
                        return false;
                    }
                });
                
                // 页面加载时，如果已保存设置，自动加载策略和相册列表
                if (currentStorageId || currentAlbumId || ($('#lskypro-domain').val() && $('#lskypro-tokens').val())) {
                    loadStrategies();
                    loadAlbums();
                }
            });
        </script>
    </div>
    <?php
}
?>