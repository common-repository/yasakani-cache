<?php
/**
* Options page
*/

class Yasakani_option {
    
    static $setting;
    static $option;
    static $blogid;
    static $db;
    static $sqldb;

    public function __construct($option, $blogid) {
        global $yasakani_cache;
        self::$setting = YC_MUSE::get_setting();
        self::$option = $option;
        self::$blogid = $blogid;
        self::$db = $yasakani_cache;
        self::$sqldb = $yasakani_cache->sqldb;
    }
    
    /* ====  Style Sheet  ==== */

    function yasakani_css() {
    ?>
    <style type="text/css">
    .grid-row { display: flex; flex-flow: row wrap;}        
    .yasakani-summary { width: 66%;}
    .summary-hed { width: 192px; padding-right: 24px;}
    .side-info { width: 30%;  padding-left: 3%;}
    .yasakani-option-info { font-size:12px; padding:2px; background-color:#fbf7dc;}
    
    fieldset { border: 1px solid #dddddd; margin: 0 2px 16px; padding: .3em .625em .5em;}
    .form-table fieldset > p{ margin: 8px 0;}
    .wrap_yasakani-log, .wrap_yasakani-fstat-diff { overflow-x:auto;}
    .yasakani-table thead, .yasakani-table tbody { display: block;}
    .yasakani-log-body { overflow-y:scroll; height:420px; } 
    .yasakani-stat-body { overflow-y:scroll; height:420px; }
    .yasakani-log-body td { font-size:12px; } .yasakani-log-body .over-hide, .yasakani-stat-body .over-hide { white-space:nowrap; overflow: hidden; }
    .log-hit {background-color: #ddf7d0;} .log-save {background-color: #faebeb;} .log-exclude {background-color: #fbf7dc;} .log-atack {background-color: red;} .log-autoblock {background-color: #d3d3d3;} .log-botblock {background-color: #dcdcdc;}.log-phperror {background-color: #F9E0B4;} .log-server {background-color: #f0ffff;} .log-protect {background-color: orange;}
    td.server-ip { color:deepskyblue; } td.login-ip { color:green; } td.bot-ip { color:orange; } td.blackbot-ip { color:red; } td .post-files { background-color:wheat; } td .ua-mobile { color:green; }
    .s-size { min-width: 56px; max-width: 56px;} .m-size { min-width: 96px; max-width: 96px;} .ml-size { min-width: 144px; max-width: 144px;} .l-size { min-width: 384px; max-width: 384px;} .ll-size { min-width: 512px; max-width: 512px;}
    .wp-admin .filter-opt { font-size: 13px; margin: 8px;}    
    .wp-admin .filter-opt > span { margin-left: 16px;}
    .wp-admin .filter-opt > span > input { margin-left: 8px;}
    .wp-admin .filter-opt > span > select { margin-left: 8px;}
    .wp-admin .yasakani-filter-nav { text-align: center; margin: 12px auto;}    
    .wp-admin .yasakani-filter-nav input.tiny-text { margin: 0 5px; text-align: center;}
    .metabox-submit {padding: 12px 20px; text-align: right;}
    .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#000; opacity:.5; z-index:98}
    .modal-window { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:66%; height:auto; background:#fff; z-index:99}
    .msg-warning, td.msg-warning { color: red; }
    .msg-success, td.msg-success { color: green; }
    </style>
    <?php }

    function jquery_tab_css() {
    ?>
    <style type="text/css">
    .ui-helper-reset { margin: 0; padding: 0; border: 0; outline: 0; line-height: 1.5; text-decoration: none; font-size: 100%; list-style: none; }
    .ui-helper-clearfix:before, .ui-helper-clearfix:after { content: ""; display: table; }
    .ui-helper-clearfix:after { clear: both; }
    .ui-helper-clearfix { zoom: 1; }
    .ui-tabs { position: relative; padding: .2em; zoom: 1; } /* position: relative prevents IE scroll bug (element with position: relative inside container with overflow: auto appear as "fixed") */
    .ui-tabs .ui-tabs-nav { margin: 1px 8px; padding: .2em .2em; }
    .ui-tabs .ui-tabs-nav li { list-style: none; float: left; position: relative; top: 0; margin: 1px .3em 0 0; border-bottom: 0; padding: 0; white-space: nowrap; }
    .ui-tabs .ui-tabs-nav li a { float: left; text-decoration: none; }
    .ui-tabs .ui-tabs-nav li.ui-tabs-active { margin-bottom: -1px; padding-bottom: 1px; }
    .ui-tabs .ui-tabs-panel { display: block; border-width: 0;  background: none; }
    .ui-tabs .ui-tabs-nav a { margin: 8px 10px; }
    .ui-state-default, .ui-widget-content .ui-state-default, .ui-widget-header .ui-state-default { border: 1px solid #dddddd; background-color: #f4f4f4; font-weight: bold; color: #0073ea; }
    .ui-state-default a, .ui-state-default a:link, .ui-state-default a:visited { color: #0073ea; text-decoration: none; }
    .ui-state-hover, .ui-widget-content .ui-state-hover, .ui-widget-header .ui-state-hover, .ui-state-focus, .ui-widget-content .ui-state-focus,.ui-widget-header .ui-state-focus { border: 1px solid #0073ea; background-color: #0073ea; font-weight: bold; color: #ffffff; }
    .ui-state-active, .ui-widget-content .ui-state-active, .ui-widget-header .ui-state-active { border: 1px solid #dddddd; background-color: #0073ea; font-weight: bold; color: #ffffff; }
    .ui-state-hover a, .ui-state-hover a:hover, .ui-state-hover a:link, .ui-state-hover a:visited { color: #ffffff; text-decoration: none; }
    .ui-state-active a, .ui-state-active a:link, .ui-state-active a:visited { color: #ffffff; text-decoration: none; }
    </style>
    <?php
    }
    
    function yasakani_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-widget');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('postbox');
        
        add_action('admin_head', array($this, 'yasakani_css'));
        add_action('admin_head', array($this, 'jquery_tab_css'));
        
        //アドオン等のスクリプト/スタイル用
        do_action( 'yc_additional_enqueue_script' );
    }

    public static function set_db_notice($message) {
    	set_transient( 'yasakani_notice', $message, MINUTE_IN_SECONDS );
    }

    public static function get_db_notice() {
        $message = get_transient('yasakani_notice');
        if(empty($message) && !empty(self::$setting['notice'])){
            $message = self::$setting['notice']; //定期的な integrity_check の整合性結果
        }        
        if(!empty($message)){
            echo "<div class='db_error' style='margin-top: 8px; padding: 1px 4px 4px; background-color: rgba(220, 112, 112, 0.2);' ><p>SQLite DB error : $message</p></div>";
            delete_transient('yasakani_notice');
        }
    }
    
    /**
     * checkbox
     *
     * @param mixed $name  - field name "options[checkbox]"
     * @param mixed $value - value false(0) / true(1) $options[checkbox]
     * @param mixed $label - label 
     */
    static function checkbox($name, $value, $label = '') {
        return "<label><input type='checkbox' name='$name' value='1' " . checked($value, 1, false) . "/> $label</label>";
    }

    /**
     * radio buttons
     *
     * @param mixed $name  - field name "options[radio]"
     * @param mixed $items - array of key=>description pairs 	array( 'Enable' => esc_html__('Enable', 'domain'), 'Disable' => esc_html__('Disable', 'domain')
     * @param mixed $checked  value options[radio]
     * @return mixed
     */
    static function radio($name, $items, $checked) {
        $name = ($name) ? "name='$name'" : "";
        $html = "";
        foreach ((array) $items as $key => $label) {
            $key = esc_attr($key);
            $html .= "<div style='display:inline-block;margin:0 10px 5px 0;'><label><input type='radio' $name value='$key' " . checked($checked, $key, false) . "/> $label</label></div>";
        }
        return $html;
    }

    /**
     * dropdown list
     *
     * @param string $name - HTML field name
     * @param array  $items - array of (key => description) to display.  If description is itself an array, only the first column is used
     * @param string $selected - currently selected value
     * @param mixed  $args - arguments to modify the display
     */
    static function dropdown($name, $items, $selected, $args = null) {
        $defaults = array(
            'id' => $name,
            'none' => false,
            'class' => null,
            'multiple' => false,
            'select_attr' => ""
        );

        if (!is_array($items))
            return;

        if (empty($items))
            $items = array();

        // Items is in key => value format.  If value is itself an array, use only the 1st column
        foreach ($items as $key => &$value) {
            if (is_array($value))
                $value = array_shift($value);
        }

        extract(wp_parse_args($args, $defaults));

        // If 'none' arg provided, prepend a blank entry
        if ($none) {
            if ($none === true)
                $none = '&nbsp;';
            $items = array('' => $none) + $items;    // Note that array_merge() won't work because it renumbers indexes!
        }

        if (!$id)
            $id = $name;

        $name = ($name) ? "name='$name'" : "";
        $id = ($id) ? "id='$id'" : "";
        $class = ($class) ? "class='$class'" : "";
        $multiple = ($multiple) ? "multiple='multiple'" : "";

        $html = "<select $name $id $class $multiple $select_attr>";

        foreach ((array) $items as $key => $label) {
            $key = esc_attr($key);
            $label = esc_attr($label);

            $html .= "<option value='$key' " . selected($selected, $key, false) . ">$label</option>";
        }
        $html .= "</select>";
        return $html;
    }

    /***************************************************************************
     * 各種設定　（設定グループ別に開閉可能なメタボックスを使用）
     ************************************************************************* */
    public function yasakani_option_page() {
        global $yasakani_cache;
        $plugin_info = get_file_data(__DIR__ . '/yasakani-cache.php', array('Version' => 'Version'), 'plugin');
        ?>
        <h2><?php esc_html_e('YASAKANI Cache Settings', 'yasakani'); ?><span style='font-size: 13px; margin-left:12px;'><?php echo "Version {$plugin_info['Version']}"; ?></span></h2>
        <p></p>
        <div class="grid-row">
            <div class="yasakani-summary">
            <?php
            if(self::$db->is_enable('sqlite')){
                $info = $this->get_db_info();
                echo '<div class="grid-row"><div class="summary-hed">' . esc_html__('DB information', 'yasakani') . '</div><div>' .  $info . '</div></div>';
                
                //アドオン等のステータスサマリー表示用
                do_action( 'yc_additional_features_summary' );
                
                self::get_db_notice();
            }
            ?>
            </div>
            <div class="side-info">
                <?php
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
                $plugins = get_plugins();
                if(empty($plugins['realtime-img-optimizer/realtime-img-optimizer.php']) || empty($plugins['yasakani-file-diff-detect-addon/yasakani-file-diff-detect-addon.php'])){
                ?>
                <div style="background-color: #f0fff0; border:1px solid #70c370; padding:4px 16px; margin:0;" >
                    <p><strong><?php esc_html_e('Introduction of plugin', 'yasakani'); ?></strong></p>
                    <p><?php esc_html_e('Thank you for using YASAKANI Cache. We offer some nifty plugin.', 'yasakani'); ?></p>
                    <?php if(empty($plugins['yasakani-file-diff-detect-addon/yasakani-file-diff-detect-addon.php'])){ ?>
                        <p><a target="_blank" rel="noopener" href="https://celtislab.net/en/wp-yasakani-file-diff-detect-restore/"> File diff detect and restore Addon</a></p>
                    <?php } ?>
                    <?php if(empty($plugins['realtime-img-optimizer/realtime-img-optimizer.php'])){ ?>
                        <p><a target="_blank" rel="noopener" href="https://celtislab.net/en/wp-realtime-image-optimizer/"> Realtime Image Optimizer Plugin</a></p>
                    <?php } ?>
                </div>
                <?php } ?>                
            </div>
        </div>
        <div class="wrap yasakani-settings">
            <?php
            add_meta_box('metabox_yc_cache',      esc_html__('Cache Settings', 'yasakani'), array($this, 'metabox_settings_cache'), 'yc_metabox_settings', 'normal');
            if (is_main_site()) {
                //アドオン等の設定画面メタボックスの表示用
                do_action( 'yc_additional_features_settings' );

                add_meta_box('metabox_yc_maintenance',__('Maintenance', 'yasakani'), array($this, 'metabox_settings_maintenance'),'yc_metabox_settings', 'normal');
            }
            ?>
            <div id="poststuff">
                <?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
                <?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
                <div id="post-body" class="metabox-holder columns-1">
                    <div id="postbox-container-1" class="postbox-container">
                        <?php do_meta_boxes( 'yc_metabox_settings', 'normal', null ); ?>
                    </div>
                </div>
            </div>
            <script type='text/javascript'>
                jQuery(document).ready(function() { postboxes.add_postbox_toggles('yc_metabox_settings'); });
            </script>
        </div>
        <p><strong><?php esc_html_e('[Important]', 'yasakani'); ?></strong></p>
        <ol class="setting-notice">
            <li><?php esc_html_e('To use the Yasakani Cache must sqlite3 module is enabled.', 'yasakani'); ?></li>
            <li><?php esc_html_e('For Page Cache, "define (WP_CACHE, true);" definition to wp-config.php file. And to generate advanced-cache.php Drop-in file.', 'yasakani'); ?></li>
            <li><?php esc_html_e('By editing php.ini / .user.ini, you can use the fastest and strongest Expert mode.', 'yasakani'); ?></li>
            <li><?php esc_html_e('Page Cache of the target (Page, Post, Custom post, embed content card and home/front_page)', 'yasakani'); ?></li>
            <li><?php esc_html_e('Cache generated to distinguish between Mobile and PC users by wp_is_mobile function.', 'yasakani'); ?></li>
            <li><?php esc_html_e('For Login user does not use Cache.', 'yasakani'); ?></li>
            <li><?php esc_html_e('Automatically clear Cache when you edit posts update. But if you change Plugins, Widgets, etc. Please click on Cache Clear button.', 'yasakani'); ?></li>
            <li><?php esc_html_e('Using Log mode you can check the access and cache status (slower only a little). In the case of Multisite, the setting of Log mode is allowed only the main-site administrator..', 'yasakani'); ?></li>
            <li><?php esc_html_e('If you are using the Apache server, it has denied using the .htaccess direct access to the "wp-content/yasakani-cache/yasakani_cache.db" database file under the plugin directory. However, if you use other servers, such as nginx, please set so that it can not be directly accessed by Administrator.', 'yasakani'); ?></li>
            <li><a target="_blank" href="https://wordpress.org/plugins/plugin-load-filter/">Plugin Load Filter</a> <?php esc_html_e('is recommended for response improvement that does not use Cache.', 'yasakani'); ?></li>
        </ol>
        <?php
    }

    /***************************************************************************
     * Cache 設定
     ************************************************************************* */
    //Cache Setting
	function metabox_settings_cache($object, $metabox) {
        if(! self::$db->is_enable('sqlite') || empty($metabox['id']) || $metabox['id'] != 'metabox_yc_cache')
            return;
        ?>
        <form method="post" autocomplete="off">
        <?php wp_nonce_field( 'yasakani-cache'); ?> 
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Page Cache', 'yasakani'); ?></label></th>
                    <td><?php
                        $apfile = ini_get('auto_prepend_file');
                        if((!empty($apfile) && strpos( $apfile, 'yasakani-cache-exload.php') !== false)){
                            $mode = array('disable' => esc_html__('Disable', 'yasakani'),
                                          'expert' => esc_html__('Expert mode', 'yasakani'));
                            if (!empty(self::$option['enable'])){
                                if(self::$option['enable'] !== 'expert')
                                    self::$option['enable'] = 'expert';
                            }
                        } else {
                            $mode = array('disable' => esc_html__('Disable', 'yasakani'),
                                          'simple'  => esc_html__('Enable', 'yasakani'));
                            if (!empty(self::$option['enable'])){
                                if(self::$option['enable'] !== 'simple')
                                    self::$option['enable'] = 'simple';
                            }
                        }
                        $enable = (!empty(self::$option['enable']))? self::$option['enable'] : 'disable';  
                        echo self::dropdown('yasakani[enable]', $mode, $enable);
                        if (is_main_site()){
                            echo '<div style="background-color: #fbf7dc; margin: 10px 0; padding: 10px;">';
                            echo '<p><strong>' . esc_html__( 'Expert mode', 'yasakani' ). '</strong></p>';
                            echo '<p>';
                            esc_html_e('If you add the following code to php.ini / .user.ini file and reload the web server, you can use faster cache.',  'yasakani');
                            echo '<br />';
                            echo '<code>auto_prepend_file = "' . WP_CONTENT_DIR . '/yasakani-cache-exload.php' . '"</code>';
                            echo '</p>';
                            echo '</div>';
                        }                        
                        echo '<p>' . self::checkbox('yasakani[avatar_cache]', self::$option['avatar_cache'], esc_html__('Generate cache file for gravatar image', 'yasakani')) . '</p>';                        
                        ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Cache Expiration', 'yasakani'); ?></label></th>
                    <td><?php
                        $expire = array('600' => esc_html__('10 minutes', 'yasakani'),
                                        '3600' => esc_html__('1 hour', 'yasakani'),
                                        '14400' => esc_html__('4 hours', 'yasakani'),
                                        '28800' => esc_html__('8 hours', 'yasakani'),
                                        '86400' => esc_html__('1 day', 'yasakani'),
                                        '604800' => esc_html__('7 days', 'yasakani'));
                        echo self::dropdown('yasakani[expire_sec]', $expire, self::$option['expire_sec']);
                        ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Exclude page', 'yasakani'); ?></label></th>
                    <td><?php
                    echo '<p style="margin-bottom: 8px;">' . self::checkbox('yasakani[exclude_home]', self::$option['exclude_home'], esc_html__('Home/Front_page', 'yasakani')) . '</p>';
                    $this->exclude_postid_table(self::$option['exclude_postid']);
                    $exclude_urlcmplist = (!empty(self::$option['exclude_urlcmplist']))? esc_textarea(self::$option['exclude_urlcmplist']): '';
                    ?> 
                    <p style='margin-top: 1em;'><?php esc_html_e('Exclude URL filter (Register URL comparison string delimited by line feed)', 'yasakani'); ?></p>
                    <div><textarea name="yasakani[exclude_urlcmplist]" rows="10" cols="30" id="exclude_urlcmplist" class="large-text code"><?php echo $exclude_urlcmplist; ?></textarea></div>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('CSS Optimization', 'yasakani'); ?></label></th>
                    <td>
                        <?php echo '<p style="margin: 10px 0;">' . self::checkbox('yasakani[embed_css]', self::$option['embed_css'], esc_html__('CSS Shrink and embed with CSS Tree Shaking', 'yasakani')) . '</p>'; ?>
                        <fieldset>
                            <p><?php echo self::checkbox('yasakani[minify_core_block_css]', self::$option['minify_core_block_css'], esc_html__('Shrink the style of WP core block and global-styles with CSS Tree shaking and embed it inline in head', 'yasakani')); ?></p>
                            <p><?php esc_html_e('Register the CSS files of plugins and themes to be shrink with CSS Tree Shaking, separated by line breaks.', 'yasakani'); ?></p>
                            <div><textarea name="yasakani[tree_shaking_css]" rows="5" cols="50" id="tree_shaking_css" class="large-text code"><?php echo esc_textarea(self::$option['tree_shaking_css']); ?></textarea></div>                          
                            <p><?php esc_html_e('Names such as registered ID and Class are excluded from CSS Tree Shaking. (Names separated by line feed)', 'yasakani'); ?><br />
                               <span class="yasakani-option-info"><?php esc_html_e('* To avoid deleting style definitions related to ID and class added by JavaScript after DOM is loaded', 'yasakani'); ?></span>
                            </p>
                            <div><textarea name="yasakani[exclude_tree_shaking_name]" rows="5" cols="50" id="exclude_tree_shaking_name" class="large-text code"><?php echo esc_textarea(self::$option['exclude_tree_shaking_name']); ?></textarea></div>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('JavaScript Optimization', 'yasakani'); ?></label></th>
                    <td>
                        <?php echo '<p style="margin: 10px 0;">' . self::checkbox('yasakani[defer_js]', self::$option['defer_js'], esc_html__('Load JavaScript as defer attribute asynchronously', 'yasakani')) . '</p>'; ?>
                        <fieldset>
                            <p><?php esc_html_e('Register JavaScript to be excluded from asynchronous load defer, separated by line feed', 'yasakani'); ?></p>
                            <div><textarea name="yasakani[exclude_defer_js]" rows="5" cols="50" id="exclude_defer_js" class="large-text code"><?php echo esc_textarea(self::$option['exclude_defer_js']); ?></textarea></div>
                        </fieldset>
                        <?php
                        echo '<div style="background-color: #fbf7dc; margin-bottom: 16px; padding: 4px;">';
                        echo '<p>';
                        esc_html_e('[Notice] : There may be an error depending on the theme and plugin used.',  'yasakani');
                        echo '</p>';
                        echo '</div>';
                        ?>
                    </td>
                </tr>
                <?php if (is_main_site()) { ?> 
                    <tr valign="top">
                        <th scope="row"><label><?php esc_html_e('Additional features', 'yasakani'); ?></label></th>
                        <td>
                        <?php
                        $log_mode = array('0' => esc_html__('OFF', 'yasakani'),
                                          '1' => esc_html__('Log', 'yasakani'),
                                          '2' => esc_html__('Log + Security', 'yasakani'));
                        $mode = (!empty(self::$setting['log_mode']))? self::$setting['log_mode'] : '0'; 
                        echo self::dropdown('yasakani[log_mode]', $log_mode, $mode);

                        do_action( 'yc_additional_features' );
                        ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>        
        </table>
        <p class="metabox-submit"><input type="submit" class="button-primary" name="yasakani_save" value="<?php esc_html_e('Option Save', 'yasakani'); ?>" /></p>
        </form>
        <?php
	}

    public function exclude_postid_table($exclude_postid) {
    ?>    
        <table id="exclude_postid_title" class="widefat">
            <thead>
                <tr>
                    <th style="width: 90%; padding:15px 10px;"><?php esc_html_e('Exclude singular post is set from the edit screen of the individual post.', 'yasakani'); ?></th>
                    <th style="width: 10%; padding:15px 10px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($exclude_postid)) {
                    $pids = array_map("trim", explode(',', $exclude_postid));
                    foreach ($pids as $pid) {
                        $post = get_post($pid);
                        if (!empty($post->post_title)) {
                            $title = $post->post_title;
                            echo '<tr>';
                            echo '<td>' . $title . '</td>';
                            $url = wp_nonce_url("options-general.php?page=yasakani-cache&amp;action=del_exclude_postid&amp;pid=$pid", "yasakani-cache");
                            echo "<td><a class='delete' href='$url'>" . esc_html__('Delete') . "</a></td>";
                            echo "</tr>";
                        }
                    }
                } ?>
            </tbody>
        </table>
    <?PHP
    }    

    /***************************************************************************
     * DB Maintenance (Main site only)
     ************************************************************************* */
  	function metabox_settings_maintenance($object, $metabox) {
        $reset_dialog = esc_html__('YASAKANI Cache Settings\nClick OK to hard reset cache database.', 'yasakani');
        
        if(! self::$db->is_enable('sqlite') || empty($metabox['id']) || $metabox['id'] != 'metabox_yc_maintenance')
            return;

        ?>
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th width='20%' scope="row" ><label><?php esc_html_e('Settings Export', 'yasakani'); ?></label></th>
                    <td width='80%'>
                        <form method="post" autocomplete="off">
                            <?php wp_nonce_field( 'yasakani-export'); ?> 
                            <p><label for="settings-export"><?php esc_html_e('Export option data. (Only some settings such as excluded pages and security)', 'yasakani'); ?></label></p>
                            <p style="margin-top: 8px;"><input type="submit" class="button-primary" name="yasakani_export" value="<?php esc_html_e('Export', 'yasakani'); ?>" /></p>
                        </form>
                    </td>
                </tr>                           
                <tr valign="top">
                    <th width='20%' scope="row" ><label><?php esc_html_e('Settings Import', 'yasakani'); ?></label></th>
                    <td width='80%'>
                        <form method="post" enctype="multipart/form-data" autocomplete="off">
                            <?php wp_nonce_field( 'yasakani-import'); ?> 
                            <p><label for="settings-import"><?php esc_html_e('Import option data with export file', 'yasakani'); ?></label></p>
                            <input type="hidden" name="MAX_FILE_SIZE" value="30000" />
                            <p style="margin-top: 8px;"><input type="file" id="yasakani_settings_import" name="yasakani_settings_import" accept=".json"></p>
                            <p style="margin-top: 8px;"><input type="submit" class="button-primary" name="yasakani_import" value="<?php esc_html_e('Import', 'yasakani'); ?>" /></p>
                        </form>
                    </td>
                </tr> 
                <tr valign="top">
                    <th width='20%' scope="row" ><label><?php esc_html_e('Cache Clear', 'yasakani'); ?></label></th>
                    <td width='80%'>
                        <form method="post" autocomplete="off">
                            <?php wp_nonce_field( 'yasakani-clear'); ?> 
                            <p><label for="soft-reset"><?php esc_html_e('For Cache clearing after changing settings, adding, or deleting plug-ins, widgets, etc.', 'yasakani'); ?></label></p>
                            <p style="margin-top: 8px;"><input type="submit" class="button-primary" name="yasakani_clear" value="<?php esc_html_e('Cache Clear', 'yasakani'); ?>" /></p>
                        </form>
                    </td>
                </tr>                           
                <tr valign="top">
                    <th width='20%' scope="row" ><label><?php esc_html_e('Hard Reset', 'yasakani'); ?></label></th>
                    <td width='80%'>
                        <form method="post" autocomplete="off">
                            <?php wp_nonce_field( 'yasakani-reset'); ?> 
                            <p><label for="hard-reset"><?php esc_html_e('For database re-creation when problems occur in database. (Cache, Log, Statistical data clear)', 'yasakani'); ?></label></p>
                            <p style="margin-top: 8px;"><input type="submit" class="button-primary" name="yasakani_reset" value="<?php esc_html_e('Hard Reset', 'yasakani'); ?>" onclick="return confirm('<?php echo $reset_dialog; ?>')" /></p>
                        </form>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    //Database info
    public function get_db_info() {
        $dbfile = WP_CONTENT_DIR . '/yasakani-cache/yasakani_cache.db';
        $res = filesize( $dbfile );
        $size = ($res !== false)? floor($res / 1024) : 0;
        $unit = 'KB';
        if($size >= 1024){
            $size = floor($size / 1024);
            $unit = 'MB';
        }
        $info = "yasakani_cache.db file size : $size $unit" . '<br />';
        $record = self::$sqldb->sql_get_var( "SELECT count(*) FROM content");
        if(!is_numeric($record))
            $record = 'failure'; 
        $info .= "Cache table record count : $record";
        return $info;
    }    
}
