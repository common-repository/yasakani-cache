<?php
/*
  File Name: backend_security.php
  Description: Simple, Easy, Ultra-high-speed Page Cache Powered by SQLite
  Author: enomoto@celtislab
  License: GPLv2
*/
if(!defined( 'ABSPATH' ) && !defined( 'YASAKANI_CACHE_DB' ))
    exit;

class YCBK_SECURITY {
    static $setting;
    static $option;

    public function __construct() {
    }

    //===========================================================================================
    // 主にバックエンドで行うセキュリティ関連処理
    // plugins_loaded 後に初期化すること(advanced-cache.php からの呼び出し不可)
    //===========================================================================================
    static function init() {
        global $yasakani_cache;
        self::$setting = YC_MUSE::get_setting();
        self::$option = Yasakani_setting::get_option();
        //セキュリティ関連オプションがなければ初期値設定
        if(! isset(self::$option['replace_url'])){
            self::$option['replace_url'] = false;
            self::$option['replace_urlsearch'] = '';
            self::$option['replace_urlreplace'] = '';
        }
        
        //キャッシュが有効？
        if (!empty(self::$option['enable'])) {
            if (class_exists('YC_SECURITY', false)) {
                add_action('template_redirect', array('YC_SECURITY', 'bot_blocking'), 1);
            }
            //ログイン成功時の関連処理
            add_filter('authenticate', array('YCBK_SECURITY', 'did_authenticate'), 10000, 3);
            //siteurl / home option value protect check
            add_filter('pre_update_option', array('YCBK_SECURITY', 'pre_update_option'), 10, 3);
            //Expired check about 1800 sec interval
            if ( !wp_next_scheduled( 'cron_yasakani_bruteforce_expired' ) ) {
                if ($yasakani_cache->is_enable('loginblock') && !empty(self::$setting['autoblocklist'])){
                    wp_schedule_single_event( time() + 1800, 'cron_yasakani_bruteforce_expired' );
                }
            }
        }

        //オプション設定ページにセキュリティ関連を追加表示
        if (is_main_site() && is_admin()) {
            add_action('yc_additional_features_option_update', array('YCBK_SECURITY', 'action_posts'));
            add_action('yc_additional_features_option_import', array('YCBK_SECURITY', 'option_import'), 10, 1);
            add_filter('yc_additional_features_option_export', array('YCBK_SECURITY', 'option_export'), 10, 1);
            
            add_action('yc_additional_features_settings', function(){
                add_meta_box('metabox_yc_security',   esc_html__('Security / Utility Settings', 'yasakani'), array('YCBK_SECURITY', 'metabox_settings_security'), 'yc_metabox_settings', 'normal');
            }, 11);            
        }
        add_action('cron_yasakani_bruteforce_expired', array( 'YCBK_SECURITY', 'delete_bruteforce_expired' ) );
    }
    
    //自動ブロックリストから30分以上たっている BruteForce ブロックIPを削除する
    static function delete_bruteforce_expired() {
        global $yasakani_cache;
        if(!empty(self::$setting['autoblocklist'])){
            $array = array_map("trim", explode(',', self::$setting['autoblocklist']));
            if(!empty($array)){
                $localtm = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
                $cmptm = $localtm->modify("-1800 second");

                $narray = array();
                foreach ($array as $item) {
                    if (strpos($item, 'BruteForce') === false){
                        $narray[] = $item;
                    } else {
                        //"[$ip/$addtm/$type]" type=InvalidRequest/BruteForce
                        $dt = explode('/', $item);
                        $expired = new DateTime( $dt[1], new DateTimeZone(self::$setting['timezone']));
                        if($expired > $cmptm ){
                            $narray[] = $item;
                        }
                    }
                }
                $array = $narray;
            }
            $lists = implode(',', $array); 
            if(self::$setting['autoblocklist'] != $lists){
                self::$setting['autoblocklist'] = $lists;
                $yasakani_cache->sqldb->sql_exec("UPDATE setting SET autoblocklist = ?", array(self::$setting['autoblocklist']));
            }
        }
    }
  
    //ブロックユーザー名のログインはブロックする
    //ログイン成功時にブロックリストに同じIPが登録されていたら回復させる
    static function did_authenticate($user, $username, $password) {
        global $yasakani_cache;
        if ( $yasakani_cache->is_enable('loginblock')) {
            if(!empty(self::$setting['block_user'])){ 
                $blocklist = array_filter( array_map("trim", explode(',', self::$setting['block_user'])), 'strlen');
                foreach ($blocklist as $name) {
                    if($username === $name){
                        $user = null;
                        if(!empty($_SERVER["REMOTE_ADDR"])){
                            YC_SECURITY::add_autoblocklist( $_SERVER["REMOTE_ADDR"], 'BruteForce' );
                        }
                        break;
                    }
                }
            }
        }
        if( $user != null && !is_wp_error($user) && !empty($_SERVER["REMOTE_ADDR"])) {
            if (class_exists('YC_SECURITY', false)) {
                YC_SECURITY::delete_autoblocklist( $_SERVER["REMOTE_ADDR"] );
            }
        }
        return $user;
    }
            
    //===========================================================================================
    // yasakani-cache.php action_posts() にフックする追加処理
    //===========================================================================================
    static function action_posts() {
        //Security & Utility setting
        if (isset($_POST['yasakani_util']) && isset($_POST['yasakani'])) {
            check_admin_referer('yasakani-cache');

            $bot_key = (!empty($_POST['yasakani']['bot_key'])) ? stripslashes_from_strings_only(trim($_POST['yasakani']['bot_key'])) : '';
            $upd = self::set_bot_key($bot_key);

            $block_user = (!empty($_POST['yasakani']['block_user'])) ? stripslashes_from_strings_only(trim($_POST['yasakani']['block_user'])) : '';
            $upd = self::set_block_user($block_user);
            
            $botblocklist = (!empty($_POST['yasakani']['botblocklist'])) ? stripslashes_from_strings_only(trim($_POST['yasakani']['botblocklist'])) : '';
            $upd = self::set_botblocklist($botblocklist);

            $autoblock = (!empty($_POST['yasakani']['autoblock'])) ? 1 : 0;
            $loginblock = (!empty($_POST['yasakani']['loginblock'])) ? 1 : 0;
            $zerodayblock = (!empty($_POST['yasakani']['zerodayblock'])) ? 1 : 0;                       
            $trustedfile = (!empty($_POST['yasakani']['trustedfile'])) ? stripslashes_from_strings_only(trim($_POST['yasakani']['trustedfile'])) : '';
            $upd = self::set_trustedfile($trustedfile);
            $siteurlprotect = (!empty($_POST['yasakani']['siteurlprotect'])) ? 1 : 0;                       
            $protectlist = (!empty($_POST['yasakani']['protectlist'])) ? stripslashes_from_strings_only(trim($_POST['yasakani']['protectlist'])) : '';
            $upd = self::set_protectlist($protectlist);
            self::set_autoblock($autoblock, $zerodayblock, $siteurlprotect, $loginblock);

            self::$option['replace_url'] = (!empty($_POST['yasakani']['replace_url'])) ? 1 : 0;
            self::$option['replace_urlsearch'] = (!empty($_POST['yasakani']['replace_urlsearch'])) ? stripslashes_from_strings_only(trim($_POST['yasakani']['replace_urlsearch'])) : '';
            self::$option['replace_urlreplace'] = (!empty($_POST['yasakani']['replace_urlreplace'])) ? stripslashes_from_strings_only(trim($_POST['yasakani']['replace_urlreplace'])) : '';
            update_option('yasakani_option', self::$option);

            wp_safe_redirect(admin_url('options-general.php?page=yasakani-cache#metabox_yc_security'));
            exit;
        }
        //Autoblock ip list
        else if (!empty($_GET['action']) && $_GET['action'] == 'del_autoblock_ip' && !empty($_GET['ip'])) {
            check_admin_referer('yasakani-cache');
            
            if (is_main_site() && class_exists('YC_SECURITY', false)) {                    
                $ip = esc_attr($_GET['ip']);
                YC_SECURITY::delete_autoblocklist( $ip );
            }
            wp_safe_redirect(admin_url('options-general.php?page=yasakani-cache#metabox_yc_log'));
            exit;
        }
    }

    static function set_bot_key($bot_key) {
        global $yasakani_cache;
        $upd = false;
        if(self::$setting['bot_key'] != $bot_key){
            $list = (!empty($bot_key))? array_filter( array_map("trim", explode(',', $bot_key)), 'strlen') : '';
            if(!empty($list))
                $list = implode(',', $list); 
            
            self::$setting['bot_key'] = $list;
            $yasakani_cache->sqldb->sql_exec("UPDATE setting SET bot_key = ?", array($list));
            $upd = true;
        }
        return $upd;
    }

    static function set_block_user($block_user) {
        global $yasakani_cache;
        $upd = false;
        if(self::$setting['block_user'] != $block_user){
            $list = (!empty($block_user))? array_filter( array_map("trim", explode(',', $block_user)), 'strlen') : '';
            if(!empty($list))
                $list = implode(',', $list); 
            
            self::$setting['block_user'] = $list;
            $yasakani_cache->sqldb->sql_exec("UPDATE setting SET block_user = ?", array($list));
            $upd = true;
        }
        return $upd;
    }
    
    static function set_botblocklist($botblocklist) {
        global $yasakani_cache;
        $upd = false;
        if(self::$setting['botblocklist'] != $botblocklist){
            //trim &　空行の削除を行って改行区切りの文字列へ戻す
            $list = (!empty($botblocklist))? array_filter( array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", $botblocklist))), 'strlen') : '';
            if(!empty($list)){
                $list = implode("\n", $list); 
            }
            self::$setting['botblocklist'] = $list;
            $yasakani_cache->sqldb->sql_exec("UPDATE setting SET botblocklist = ?", array($list));
            $upd = true;
        }
        return $upd;
    }
    
    static function set_trustedfile($trustedfile) {
        global $yasakani_cache;
        $upd = false;
        if(self::$setting['trustedfile'] != $trustedfile){
            //trim &　空行の削除を行って改行区切りの文字列へ戻す
            $list = (!empty($trustedfile))? array_filter( array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", $trustedfile))), 'strlen') : '';
            if(!empty($list)){
                $list = implode("\n", $list); 
            }
            self::$setting['trustedfile'] = $list;
            $yasakani_cache->sqldb->sql_exec("UPDATE setting SET trustedfile = ?", array($list));
            $upd = true;
        }
        return $upd;
    }

    static function set_protectlist($protectlist) {
        global $yasakani_cache;
        $upd = false;
        if(self::$setting['protectlist'] != $protectlist){
            //trim &　空行の削除を行って改行区切りの文字列へ戻す
            $list = (!empty($protectlist))? array_filter( array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", $protectlist))), 'strlen') : '';
            if(!empty($list)){
                $list = implode("\n", $list); 
            }
            self::$setting['protectlist'] = $list;
            $yasakani_cache->sqldb->sql_exec("UPDATE setting SET protectlist = ?", array($list));
            $upd = true;
        }
        return $upd;
    }
        
    //設定オプション更新
    static function set_autoblock($autoblock, $zerodayblock, $siteurlprotect, $loginblock) {
        global $yasakani_cache;
        self::$setting = YC_MUSE::get_setting();
        if(self::$setting['autoblock'] != $autoblock || self::$setting['zerodayblock'] != $zerodayblock || self::$setting['siteurlprotect'] != $siteurlprotect || self::$setting['loginblock'] != $loginblock){
            self::$setting['autoblock'] = (int)$autoblock;
            self::$setting['zerodayblock'] = (int)$zerodayblock;
            self::$setting['siteurlprotect'] = (int)$siteurlprotect;
            self::$setting['loginblock'] = (int)$loginblock;
            $yasakani_cache->sqldb->sql_exec("UPDATE setting SET autoblock = ?, loginblock = ?, zerodayblock = ?, siteurlprotect = ?", array( (int)$autoblock, (int)$loginblock, (int)$zerodayblock, (int)$siteurlprotect));
        }
    }
    
    //===========================================================================================
    // option インポート/エクスポート
    //===========================================================================================
    //yasakani-cache.php action_posts - import にフックする追加処理
    static function option_import( $option ) {
        self::$option = Yasakani_setting::get_option();
        $bot_key = '';
        $block_user = '';
        $botblocklist = '';
        $trustedfile = '';
        $protectlist = '';
        //zerodayblock 等は不用意に設定するとアクセスできなくなるケースもあるのでクリアのみ
        $loginblock = $autoblock = $zerodayblock = $siteurlprotect = 0;
        foreach ($option as $key => $value) {
            switch($key){
            case 'bot_key':
                $bot_key = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                break;
            case 'block_user':
                $block_user = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                break;
            case 'botblocklist':
                $botblocklist = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                break;
            case 'trustedfile':
                $trustedfile = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                break;
            case 'protectlist':
                $protectlist = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                break;
            case 'replace_url':
                self::$option['replace_url'] = (!empty($value)) ? 1 : 0;
                break;
            case 'replace_urlsearch':
                self::$option['replace_urlsearch'] = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                break;
            case 'replace_urlreplace':
                self::$option['replace_urlreplace'] = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                break;            
            default:
                break;
            }
        }
        //sqlite setting table update
        self::set_bot_key($bot_key);
        self::set_block_user($block_user);       
        self::set_botblocklist($botblocklist);
        self::set_trustedfile($trustedfile);
        self::set_protectlist($protectlist);
        //security option update
        update_option('yasakani_option', self::$option);
    }

    //yasakani-cache.php action_posts - export にフックする追加処理
    static function option_export( $option ) {
        self::$option = Yasakani_setting::get_option();
        $option['replace_url'] = self::$option['replace_url'];
        $option['replace_urlsearch'] = self::$option['replace_urlsearch'];
        $option['replace_urlreplace'] = self::$option['replace_urlreplace'];
        return $option;
    }
    
    //===========================================================================================
    // siteurl / home option データ更新プロテクト
    //===========================================================================================
	/**
	 * Filters an option before its value is (maybe) serialized and updated.
	 *
	 * @param mixed  $value     The new, unserialized option value.
	 * @param string $option    Name of the option.
	 * @param mixed  $old_value The old option value.
	 */
    static function pre_update_option($value, $option, $old_value) {
        global $yasakani_cache, $yasakani_cache_action;
        $nvalue = $value;
        if ($yasakani_cache->is_enable('siteurlprotect')) {
            if(!empty(self::$setting['protectlist'])){
                $protect = array_filter( array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", self::$setting['protectlist']))), 'strlen');
            }
            $protect[] = 'siteurl';
            $protect[] = 'home';            
            
            if( in_array($option, $protect)){
            	if ( $value !== $old_value || maybe_serialize( $value ) !== maybe_serialize( $old_value ) ) {
                    //書き込み保護が有効なら新しい値を無効化する
                    $nvalue = $old_value;
                    if($yasakani_cache->is_enable('log')){
                        YC_LOGPUT::put_protect_log('protect_option', $option, maybe_serialize($value), self::$blogid);
                    }
                }
            }
        }
        return $nvalue;
    }
    
    //===========================================================================================
    // 設定オプションページのメタボックス
    //===========================================================================================
    // セキュリティ/ユーティリティ
  	static function metabox_settings_security($object, $metabox) {
        global $yasakani_cache;
        if(! $yasakani_cache->is_enable('sqlite') || empty($metabox['id']) || $metabox['id'] != 'metabox_yc_security')
            return;
        ?>
        <form method="post" autocomplete="off">
        <?php wp_nonce_field( 'yasakani-cache'); ?> 
        <table class="form-table">
            <tbody>
                <tr valign="top">
                    <th width='20%' scope="row" ><label><?php esc_html_e('Bot keyword', 'yasakani'); ?></label></th>
                    <td width='80%'>
                        <p><label for="bot_key"><?php esc_html_e('Please set keywords to identify bot access from user agent. (comma separated)', 'yasakani'); ?></label></p>
                        <div><input type="text" class="large-text" name="yasakani[bot_key]" value="<?php echo esc_textarea(self::$setting['bot_key']); ?>"  /></div>
                    </td>
                </tr>                           
                <tr valign="top">
                    <th width='20%' scope="row" ><label><?php esc_html_e('Bot Black Lists', 'yasakani'); ?></label></th>
                    <td width='80%'>
                        <p><label for="botblocklist"><?php esc_html_e('Block bot access that could not be restricted by robots.txt. If IP or User agent contains these strings, that access will be blocked. One word per line or IP.', 'yasakani'); ?></label></p>
                        <div><textarea name="yasakani[botblocklist]" rows="10" cols="50" id="botblocklist" class="large-text code"><?php echo esc_textarea(self::$setting['botblocklist']); ?></textarea></div>
                    </td>
                </tr>
                <tr valign="top">
                    <th width='20%' scope="row" ><label><?php esc_html_e('Simple & Fast WordPress Security', 'yasakani'); ?></label></th>
                    <td>
                    <?php 
                    echo '<p>' . Yasakani_option::checkbox('yasakani[loginblock]', self::$setting['loginblock'], esc_html__('Brute Force - Login failed 5 times or block user　name (Unblock in about 30 minutes)', 'yasakani')) . '</p>';
                    ?>
                    <p><span class="yasakani-option-info"><?php esc_html_e('Register login user name to be considered a brute force attack. For example, if `admin` or site name is used as the most common user to try to login, then registering them separated by commas will block the login.
', 'yasakani'); ?></span></p>
                    <div><input type="text" class="large-text" name="yasakani[block_user]" value="<?php echo esc_textarea(self::$setting['block_user']); ?>"  /></div>
                    <?php 
                    echo '<p>' . Yasakani_option::checkbox('yasakani[autoblock]', self::$setting['autoblock'], esc_html__('Invalid Request Attack [NULL byte / Directory traversal / Command injection ...]　(Unblock when the date changes)', 'yasakani')) . '</p>';
                    //trusted file list for expert mode 
                    echo '<p>' . Yasakani_option::checkbox('yasakani[zerodayblock]', self::$setting['zerodayblock'], esc_html__('PHP Script Zeroday Attack [*Only when expert mode is valid]　(Unblock when the date changes)', 'yasakani')) . '</p>';
                    ?>
                    <p><span class="yasakani-option-info"><?php esc_html_e('Block direct access other than specified php and /index.php, /wp-login.php. (Excludes PHP in /wp-admin area by login user). For direct access permission, register the PHP file path from the root directory with a new line delimiter.', 'yasakani'); ?></span></p>
                    <div><textarea name="yasakani[trustedfile]" rows="10" cols="50" id="trustedfile" class="large-text code"><?php echo esc_textarea(self::$setting['trustedfile']); ?></textarea></div>
                    <?php 
                    echo '<p>' . Yasakani_option::checkbox('yasakani[siteurlprotect]', self::$setting['siteurlprotect'], esc_html__('Rewrite protection for WordPress address (siteurl) / Site address (home) / other options.', 'yasakani')) . '</p>';
                    ?>
                    <p><span class="yasakani-option-info">
                        <?php esc_html_e('* If you want to rewrite the General Setting page / other option setting page, please disable it.', 'yasakani'); ?><br />
                        <?php esc_html_e('Set options for rewriting protection other than siteurl and home. (One option name per line)', 'yasakani'); ?>
                        </span></p>
                    <div><textarea name="yasakani[protectlist]" rows="10" cols="50" id="protectlist" class="large-text code"><?php echo esc_textarea(self::$setting['protectlist']); ?></textarea></div>
                    </td>
                </tr>
                <tr valign="top">
                    <th width='20%' scope="row" ><label><?php esc_html_e('URL address replace', 'yasakani'); ?></label></th>
                    <td width='80%'>
                        <?php echo '<p>' . Yasakani_option::checkbox('yasakani[replace_url]', self::$option['replace_url'], esc_html__('URL replacement such as img, link etc in contents.', 'yasakani')) . '</p>'; ?> 
                        <label style="margin-right:10px;"><?php esc_html_e('Search', 'yasakani'); ?></label><input type="url" class="medium-text" name="yasakani[replace_urlsearch]" value="<?php echo self::$option['replace_urlsearch']; ?>"  />
                        <label style="margin:0px 10px;"><?php esc_html_e('Replace', 'yasakani'); ?></label><input type="url" class="medium-text" name="yasakani[replace_urlreplace]" value="<?php echo self::$option['replace_urlreplace']; ?>"  />
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="metabox-submit"><input type="submit" class="button-primary" name="yasakani_util" value="<?php esc_html_e('Option Save', 'yasakani'); ?>" /></p>
        </form>
        <?php
    }
}
