<?php
/*
  Plugin Name: Yasakani Cache
  Description: Simple, Easy, Ultra-high-speed Page Cache Powered by SQLite
  Version: 3.8.2
  Plugin URI: https://celtislab.net/en/wp-yasakani-cache/
  Author: enomoto@celtislab
  Author URI: https://celtislab.net/
  Requires at least: 5.5
  Tested up to: 6.5
  Requires PHP: 7.4
  License: GPLv2
  Text Domain: yasakani
  Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('YASAKANI_SETTING', true );
define('YASAKANI_CACHE_DB_DIR', WP_CONTENT_DIR . '/yasakani-cache');

if(! isset($yasakani_cache)){
    $yasakani_cache = null;
}

function yasakani_setting_load() {
    if(!isset($_REQUEST['action']) || $_REQUEST['action'] !== 'heartbeat' ){    
        $yasakani_setting = new Yasakani_setting();
    }
}
add_action('init', 'yasakani_setting_load', 1);

/***************************************************************************
 * Create database tables and avvanced-cache.php at the time of plugin activation
 ************************************************************************* */
if (is_admin()) {
    function yasakani_cache_activation($network_wide) {
        require_once( ABSPATH . 'wp-admin/includes/file.php');
        global $wp_filesystem;
        wp_mkdir_p(YASAKANI_CACHE_DB_DIR);
        $act = false;
        if (is_multisite()) {
            if (!$network_wide) {
                global $wpdb;
                $current_blog_id = get_current_blog_id();
                $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
                foreach ($blog_ids as $blog_id) {
                    if ($blog_id != $current_blog_id) {
                        switch_to_blog($blog_id);
                        if (is_plugin_active(plugin_basename(__FILE__))) {
                            $act = true;
                        }
                    }
                }
                switch_to_blog($current_blog_id);
            }
        }
        if ($act === false) {
            //db file direct access deny for apache server
            $htaccess = YASAKANI_CACHE_DB_DIR . '/.htaccess';
            if (WP_Filesystem(YASAKANI_CACHE_DB_DIR . '/.htaccess')) {
                if (!$wp_filesystem->exists($htaccess)) {
                    $wp_filesystem->put_contents($htaccess, "Deny from all\r\n", FS_CHMOD_FILE);
                    //@file_put_contents(YASAKANI_CACHE_DB_DIR . '/.htacc$htaccessss', "Deny from all\r\n");
                }
            }

            Yasakani_setting::advanced_cache_file('create');
            Yasakani_setting::wp_config_file('insert_wp_cache');

            if (!class_exists('YC_MUSE', FALSE)){
                require_once( __DIR__ . '/magatama_lv1.php');
                $yc = new YC_MUSE('CREATE');
                if (!empty($yc) && !empty($yc->sqldb)) {
                    $yc->sqldb->close();
                }
            }
        }
    }
    register_activation_hook(__FILE__, 'yasakani_cache_activation');

    //deactivation
    function yasakani_cache_deactivation($network_deactivating) {
        //cache clear
        Yasakani_setting::delete_all_content();
        $act = false;
        if (is_multisite()) {
            if (!$network_deactivating) {
                global $wpdb;
                $current_blog_id = get_current_blog_id();
                $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
                foreach ($blog_ids as $blog_id) {
                    if ($blog_id != $current_blog_id) {
                        //other site active check
                        switch_to_blog($blog_id);
                        if (is_plugin_active(plugin_basename(__FILE__)))
                            $act = true;
                    }
                }
                switch_to_blog($current_blog_id);
            }
        }
        if ($act === false) {
            Yasakani_setting::wp_config_file('delete_wp_cache');
            Yasakani_setting::advanced_cache_file('delete');
        }
    }
    register_deactivation_hook(__FILE__, 'yasakani_cache_deactivation');
   
    //uninstall
    function yasakani_cache_uninstall() {
        if (!is_multisite()) {
            delete_option('yasakani_option');
        } else {
            global $wpdb;
            $current_blog_id = get_current_blog_id();
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);
                delete_option('yasakani_option');
            }
            switch_to_blog($current_blog_id);
        }
        global $yasakani_cache;
        if (!empty($yasakani_cache) && !empty($yasakani_cache->sqldb)) {
            //WAL モードの場合、DB接続を閉じるときに .db-wal .db-shm ファイルはDB本体に反映されて削除される 
            $yasakani_cache->sqldb->close();            
        }
        Yasakani_setting::wp_config_file('delete_wp_cache');
        Yasakani_setting::advanced_cache_file('delete');
        if (file_exists(YASAKANI_CACHE_DB_DIR . '/yasakani_cache.db-journal'))
            wp_delete_file(YASAKANI_CACHE_DB_DIR . '/yasakani_cache.db-journal');
        if (file_exists(YASAKANI_CACHE_DB_DIR . '/yasakani_cache.db'))
            wp_delete_file(YASAKANI_CACHE_DB_DIR . '/yasakani_cache.db');
        if (file_exists(YASAKANI_CACHE_DB_DIR . '/yasakani_cache.db-tmp'))
            wp_delete_file(YASAKANI_CACHE_DB_DIR . '/yasakani_cache.db-tmp');
        if (file_exists(YASAKANI_CACHE_DB_DIR . '/.htaccess'))
            wp_delete_file(YASAKANI_CACHE_DB_DIR . '/.htaccess');
        @rmdir(YASAKANI_CACHE_DB_DIR);
    }
    register_uninstall_hook(__FILE__, 'yasakani_cache_uninstall');
}

/***************************************************************************
 * Yasakani cache Option Setting
 **************************************************************************/

class Yasakani_setting {

    static $setting;
    static $option;
    static $optpg;
    static $blogid;
    static $notice;
    static $avatar_urllist;

    public function __construct() {

        require_once( ABSPATH . 'wp-admin/includes/plugin.php');        
        require_once( __DIR__ . '/magatama_lv1.php');
        require_once( __DIR__ . '/yasakani_dbtable.php');

        self::$blogid = 1;
        if (is_multisite()) {
            self::$blogid = get_current_blog_id();
        }
        
        global $yasakani_cache;
        if (empty($yasakani_cache)) {
            $yasakani_cache = new YC_MUSE('CREATE');
        }

        self::$setting = YC_MUSE::get_setting();        
        self::$option = self::get_option();
        
        //css/js 最適化有効か？
        if (!empty(self::$option['embed_css']) || !empty(self::$option['defer_js'])) {
            require_once( __DIR__ . '/inc/minify-utils.php');            
        }
        
        //======== アドオン等モジュール初期化 ========
        if ($yasakani_cache->is_enable('log')) {
            include_once( __DIR__ . '/addons/admin/backend_logstat.php');
            if (class_exists('YCBK_LOGSTAT', false)){
                YCBK_LOGSTAT::init();
            }
        }
        if ($yasakani_cache->is_enable('security')){
            include_once( __DIR__ . '/addons/admin/backend_security.php');
            if (class_exists('YCBK_SECURITY', false)){
                YCBK_SECURITY::init();
            }
        }        
        do_action( 'yc_additional_features_init' );
        //===============================================

        load_plugin_textdomain('yasakani', false, basename(dirname(__FILE__)) . '/languages');
        
        self::$notice = null;
        self::$avatar_urllist = array();
        self::$optpg = null;
        if (is_admin()) {
            require_once( __DIR__ . '/yasakani_option_page.php');
            self::$optpg = new Yasakani_option(self::$option, self::$blogid );
            self::condition_check();
            add_action('init', array($this, 'yasakani_admin_start'), 9999);
            add_action('admin_init', array($this, 'action_posts'));
            add_action('add_meta_boxes', array($this, 'load_meta_boxes'), 10, 2);
            add_action('in_plugin_update_message-yasakani-cache/yasakani-cache.php', array($this, 'update_notice_message'), 10, 2);
        }
        if ($yasakani_cache->is_enable('sqlite')) {            
            global $yasakani_cache_action;
            if (!empty(self::$option['enable'])) {
                add_action('embed_head', array('Yasakani_setting', 'embed_head_start'), 1);
                add_action('embed_head', array('Yasakani_setting', 'embed_head_end'), 9999);
                add_action('template_redirect', array('Yasakani_setting', 'avatar_cache_start'));
                add_filter('wp_using_themes', array('Yasakani_setting', 'cache_store_start'), PHP_INT_MAX, 1);
                $yasakani_cache_action['cache_disable'] = false;
                
            } else {
                //キャッシュ無効でも CSS, JS 最適化有効ならフィルター実行
                if (!empty(self::$option['embed_css']) || !empty(self::$option['defer_js'])) {
                    add_filter('wp_using_themes', array('Yasakani_setting', 'cache_store_start'), PHP_INT_MAX, 1);
                }
                $yasakani_cache_action['cache_disable'] = true;
            }
            if (is_admin()) {
                //logged_key テーブルにログインユーザー確認用の情報を保存する（日替わりまで）
                if (is_user_logged_in()) {
                    $user_id = get_current_user_id();
                    $user = get_userdata( $user_id );
                    $userkey = md5($user->data->user_login);
                    $siteurl = get_site_option( 'siteurl' );
                    if ( $siteurl ){
                        if($yasakani_cache->sqldb->is_table_exist('logged_key')){
                            $usrobj = $yasakani_cache->sqldb->sql_get_row( "SELECT * FROM logged_key WHERE sitekey = ? AND userkey = ?", array($siteurl, $userkey));
                            if(!is_object($usrobj)){
                                $ret = $yasakani_cache->sqldb->sql_exec("INSERT INTO logged_key (sitekey, userkey) VALUES ( ?, ?)", array($siteurl, $userkey));
                            }
                        }
                    }
                }                
            }
            
            add_action('transition_post_status', array($this, 'yasakani_cache_statpost'), 20, 3);
            add_action('delete_post', array($this, 'yasakani_cache_delpost'));
            add_action('comment_post', array($this, 'yasakani_cache_newcomment'), 10, 3);
            add_action('edit_comment', array($this, 'yasakani_cache_editcomment'), 10, 1);
            add_action('trackback_post', array($this, 'yasakani_cache_editcomment'), 10, 1);
            add_action('pingback_post', array($this, 'yasakani_cache_editcomment'), 10, 1);
            add_action('wp_set_comment_status', array($this, 'yasakani_cache_statcomment'), 10, 2);
            
            add_action('switch_theme', array($this, 'yasakani_cache_allclear'));
            add_action('wp_update_nav_menu', array($this, 'yasakani_cache_allclear'));
            add_action('wp_ajax_yasakani_exclude', array($this, 'yasakani_ajax_exclude'));
            add_action('wp_ajax_yasakani_clear', array($this, 'yasakani_ajax_clear'));
        }
    }

    public static function get_option() {
        $default = array(
            'enable' => false,
            'expire_sec' => 600,
            'exclude_home' => false,
            'exclude_postid' => '',
            'exclude_urlcmplist' => '/forums/' . "\n" . '/product/' . "\n" . '/cart/' . "\n" . '/checkout/' . "\n",
            'embed_css' => 0,
            'minify_core_block_css' => 1,
            'minify_varcss' => 0,
            'defer_js' => 0,
            'avatar_cache' => 0,
            'exclude_defer_js' => 'tinymce.min.js' . "\n" . 'quicktags.min.js' . "\n",
            'exclude_defer_js_postid' => '',            
            'tree_shaking_css' => '',
            'exclude_tree_shaking_name' => 'share-count' . "\n" . 'skip-link' . "\n" . 'screen-reader-text' . "\n",
            'exclude_tree_shaking_postid' => '',
            'log_type' => '',
        );
        //delete_option('yasakani_option');
        $copt = get_option('yasakani_option', array());
        $option = wp_parse_args((array) $copt, $default);
        return $option;
    }

    public static function my_admin_notice($message) {
        self::$notice = "<div class='message error'><p>Yasakani Cache : $message</p></div>";
        add_action('admin_notices', function() { echo self::$notice; });
    }

    public static function condition_check() {
        global $yasakani_cache;
        $errflag = false;
        $adcfile = WP_CONTENT_DIR . '/advanced-cache.php';
        $db_stat = YC_MUSE::get_db_stat();
        if ($db_stat === 1) {
            self::my_admin_notice(__('SQLite DB File could not be opened.', 'yasakani'));
            $errflag = true;
        } elseif ($db_stat === 2) {
            self::my_admin_notice(__('DB table not exsist. (After deactivate plugins, and then re-activate. Automatically creates DB table)', 'yasakani'));
            $errflag = true;
        } elseif (!file_exists($adcfile)) {
            self::my_admin_notice(__('advanced-cache.php file not exsist (If enable Page Cache, automatically re-generate advanced-cache.php file)', 'yasakani'));
            $errflag = true;
        } else {
            $hed = get_file_data($adcfile, array('Name' => 'Module Name', 'Type' => 'Type'));
            if (empty($hed['Name']) || false === strpos($hed['Name'], "yasakani cache")) {
                self::my_admin_notice(__('Incompatible advanced-cache.php file (advanced-cache.php is another cache plugins. If you remove advanced-cache.php file, and enable Page Cache then automatically re-generate advanced-cache.php file)', 'yasakani'));
                $errflag = true;
            } elseif (!defined('WP_CACHE') || constant('WP_CACHE') === false) {
                //Immediately after activation, since the setting is not reflected examine in preg_match
                $config = self::wp_config_file('read');
                if (empty($config) || 1 !== preg_match("#^define.+?'WP_CACHE'.+?true.+?$#m", $config)) {
                    self::my_admin_notice(__("define('WP_CACHE', true); is not in wp-config.php", 'yasakani'));
                    $errflag = true;
                }
            }
            // advanced-cache.php が旧形式のままなら自動的にアップデート
            if (!empty($hed['Name']) && false !== strpos($hed['Name'], "yasakani cache") && (empty($hed['Type']) || $hed['Type'] != '2')) {
                Yasakani_setting::advanced_cache_file('delete');
                Yasakani_setting::advanced_cache_file('create');
            }
            // yasakani-cache-exload.php ファイルがなければ作成、旧形式のままなら自動的にアップデート
            $exlfile = WP_CONTENT_DIR . '/yasakani-cache-exload.php';
            if(!file_exists( $exlfile )){
                Yasakani_setting::yasakani_cache_exload('create');
            }
            $hed = get_file_data($exlfile, array('Name' => 'Module Name', 'Type' => 'Type'));
            if (!empty($hed['Name']) && false !== strpos($hed['Name'], "yasakani-cache-exload") && (empty($hed['Type']) || $hed['Type'] != '2')) {
                Yasakani_setting::yasakani_cache_exload('update');
            }
        }            
        if (!empty(self::$option['enable']) && $errflag) {
            self::$option['enable'] = false;
        }
    }

    //is_embed cache content add meta charset
    static function embed_head_add_meta($content) {
        if (!empty($content) && false === strpos($content, "<meta charset=")) {
            $content = '<meta charset="' . get_bloginfo('charset') . '">' . $content;
        }
        return $content;
    }
    static function embed_head_start() {
        ob_start(array('Yasakani_setting', 'embed_head_add_meta'));
    }
    static function embed_head_end() {
        ob_end_flush();
    }

    //gravatar cache
    static function avatar_cache_start() {
        if (!empty(self::$option['avatar_cache']) && is_singular() && !is_user_logged_in()) {
            add_filter('get_avatar', array('Yasakani_setting', 'get_gravatar'), 9999, 6);
        }
    }
    
    /**
     * シングラーの gravatarサーバーへのアクセスをキャッシュ画像のURLへ置き換える
     * ※主にコメントのアバターを想定 - コメントの沢山あるページだとそれなりの効果が期待できる
     *
     * @param string $avatar      &lt;img&gt; tag for the user's avatar.
     * @param mixed  $id_or_email The Gravatar to retrieve. Accepts a user_id, gravatar md5 hash,
     *                            user email, WP_User object, WP_Post object, or WP_Comment object.
     * @param int    $size        Square avatar width and height in pixels to retrieve.
     * @param string $default     URL for the default image or a default type. Accepts '404', 'retro', 'monsterid',
     *                            'wavatar', 'indenticon','mystery' (or 'mm', or 'mysteryman'), 'blank', or 'gravatar_default'.
     *                            Default is the value of the 'avatar_default' option, with a fallback of 'mystery'.
     * @param string $alt         Alternative text to use in the avatar image tag. Default empty.
     * @param array  $args        Arguments passed to get_avatar_data(), after processing.
     */
     //キャッシュはページ毎のキャッシュ有効期限と同期し、ページキャッシュの更新時に gravatar も上書き更新される
     //クリアはメンテナンスのキャッシュクリア、テーマ切り替え、アンインストール時の一括クリアのみ(関連記事等で表示している場合もあるのでページ単位のクリアは不可)
    static function get_gravatar($avatar, $id_or_email, $size, $default, $alt, $args) {
        if(!empty($args['url']) && strpos($args['url'], 'gravatar.com/avatar') !== false ){
            $post = get_post();
            if(!empty($post) && preg_match('#s=([0-9]+)#u', $args['url'], $size)){
                $bid = self::$blogid;
                $pid = $post->ID;
                if ((empty(self::$option['exclude_postid']) || !in_array($pid, array_map("trim", explode(',', self::$option['exclude_postid'])))) && empty($post->post_password) && in_array( $post->post_status, array( 'publish', 'inherit', 'closed' ))){
                    if(self::is_exclude_urlcmplist( $_SERVER['REQUEST_URI'] ) === false){
                        $upload = wp_upload_dir();
                        $x1size = (int)$size[1];
                        $x2size = (int)$size[1] * 2;
                        $urls['src'] = $args['url'];
                        $urls['srcset'] = str_replace("s=$x1size", "s=$x2size", $args['url']);

                        foreach ($urls as $key => $url) {
                            //同一リクエスト内で既にurlが実行済か
                            if( !empty(self::$avatar_urllist[$url])){
                                $gravatar = self::$avatar_urllist[$url];
                                $ext = '.' . pathinfo($gravatar, PATHINFO_EXTENSION);
                            } else {
                                $gravatar = '';
                                wp_mkdir_p($upload['basedir'] . "/yasakani-gravatar-cache/b$bid");
                                $args = array( 'timeout' => 60 );
                                $response = wp_safe_remote_get( $url, $args );
                                if ( ! is_wp_error( $response ) && $response['response']['code'] === 200 ) {
                                    $mime_type = wp_remote_retrieve_header( $response, 'content-type' );
                                    $ext = '';
                                    if ( $mime_type == 'image/jpeg'){
                                        $ext = '.jpg';
                                    } elseif ( $mime_type == 'image/png') {
                                        $ext = '.png';
                                    } elseif ( $mime_type == 'image/gif') {
                                        $ext = '.gif';
                                    } elseif ( $mime_type == 'image/webp') {
                                        $ext = '.webp';
                                    } elseif ( $mime_type == 'image/avif') {
                                        $ext = '.avif';
                                    }
                                    if ( !empty($ext)) {
                                        $gravatar = $upload['basedir'] . "/yasakani-gravatar-cache/b$bid/" . md5($url) . $ext;
                                        $ifp = @ fopen( $gravatar, 'wb' );
                                        if ( $ifp ){
                                            @fwrite( $ifp, $response['body'] );
                                            fclose( $ifp );
                                            clearstatcache();
                                            // Set correct file permissions
                                            $stat = @ stat( dirname( $gravatar ) );
                                            $perms = $stat['mode'] & 0007777;
                                            $perms = $perms & 0000666;
                                            @ chmod( $gravatar, $perms );
                                            clearstatcache();
                                            self::$avatar_urllist[$url] = $gravatar;
                                        }
                                    }
                                }
                            }
                            if (is_file($gravatar)){
                                $cache = $upload['baseurl'] . "/yasakani-gravatar-cache/b$bid/" . md5($url) . $ext;
                                if($key == 'src') {
                                    $avatar = preg_replace('#(<img.+?src=\')([^\']+)(\'.*?>)#u', "$1$cache$3", $avatar);                            
                                } elseif($key == 'srcset') {
                                    $avatar = preg_replace('#(<img.+?srcset=\')([^\']+)(\'.*?>)#u', "$1$cache 2x$3", $avatar);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $avatar;
    }

    /**
     * get header data  
     * ※wp super cache の header データ取得処理をカスタマイズ
     * wordpress デフォルトで投稿に設定する headers は下記3種と思われる
     *  $headers['Content-Type']
     *  $headers['Last-Modified']
     *  $headers['ETag']
     * YASAKANI Cache では ETag は使わずに 'Cache-Control: no-cache' と 'Last-Modified' を活用
     * よくわからないものは基本的に元コードを尊重し、自動的に出力されたり、不要と判断したものをここから除外
     * 但し、Webサーバーで設定してくるものもあるのでここで除外しても出力されるものもある
     */
    static function get_response_headers() {
        static $known_headers = array(
            'Access-Control-Allow-Origin',  //ブラウザがアクセスしてよいリソースのURI
            'Accept-Ranges',                //返答したデータのレンジ
            //'Age',                        //キャッシュデータの場合の経過秒数
            'Allow',                        //利用可能なリクエストメソッド一覧
            'Cache-Control',                //キャッシュコントロール指示や情報
            'Connection',                   //接続の永続性
            'Content-Encoding',             //コンテンツのエンコード方法
            'Content-Language',             //コンテンツの使用言語コード
            'Content-Length',               //コンテンツのサイズ(バイト) GETメソッド使用時は指定不要
            'Content-Location',             //コンテンツのURI
            'Content-MD5',                  //コンテンツのMD5検証値
            'Content-Disposition',          //コンテンツのインライン表示か、添付ファイル表示かを示す
            'Content-Range',                //コンテンツのデータのレンジ
            'Content-Type',                 //コンテンツのアプリケーション・メディアタイプ
            //'Date',                       //日付情報
            //'ETag',                       //コンテンツのハッシュ値（コンテンツの変更を確認）
            //'Expires',                    //コンテンツの有効期限(ブラウザキャッシュの制御　過去指定等の特殊な設定も)
            'Last-Modified',                //コンテンツの最終更新時刻
            'Link',                         //リンクファイル（Jetpack short link url 等）
            'Location',                     //リダイレクト先URL
            'P3P',                          //プライバシーポリシーの表明
            'Pragma',                       //クライアント／プロキシ／サーバに認識させるための特殊な追加情報を記述
            'Proxy-Authenticate',           //プロキシサーバとクライアントの間で認証が必要であることを示す
            "Referrer-Policy",              //リファラーポリシー           
            //'Refresh',                    //ページ再描画（リダイレクト）までの秒数
            //'Retry-After',                //数秒後の再要求の指示を示し、503（Service Unavailable）や 3xx（Redirection）ステータスにより返される
            //'Server',                     //HTTPサーバアプリケーション種類を示す固有値
            //'Status',                     //HTTP レスポンスステータスコード
            'Strict-Transport-Security',    //HTTP の代わりに HTTPS を用いて通信を行うようブラウザに通知
            'Trailer',                      //チャンク形式エンコーディングで使用されるフィールド
            'Transfer-Encoding',            //転送エンコーディング形式
            'Upgrade',                      //HTTP/2.0への移行関連？
            'Vary',                         //指定フィールドがサーバによって受け入れ可能オプションと判断されたことを示す
            'Via',                          //経由したプロキシ等の情報を格納（ループ検知）
            'Warning',                      //レスポンス・ステータスコードの付加的なコード番号やテキスト情報
            'WWW-Authenticate',             //認証が必要であることを示す
            'X-Frame-Options',              //ページの <frame> または <iframe> の内部に表示への許可設定（クリックジャッキング対策）
            'Public-Key-Pins',              //HTTP 公開鍵ピンニング拡張(不正SSL証明書対策)
            'X-XSS-Protection',             //ブラウザのクロスサイトスクリプティング（XSS）フィルタ機能を強制的に有効化する
            'Content-Security-Policy',      //ロード可能なスクリプトを制限する
            "X-Pingback",                   //ピンバックのアクセス先情報
            'X-Content-Security-Policy',    //ロード可能なスクリプトを制限する
            'X-WebKit-CSP',                 //ロード可能なスクリプトを制限する
            'X-Content-Type-Options',       //Content-Typeに合致しない動作を制限する
            //'X-Powered-By',               //PHP バージョン情報
            'X-UA-Compatible',              //IE特定バージョンのエミュレート要求
        );
        $known_headers = array_map( 'strtolower', $known_headers );

        $headers = array();
        //if ( function_exists( 'apache_response_headers' ) ) {
        //    var_dump(apache_response_headers());
        //    $headers = apache_response_headers();
        // エックスサーバーで何故か ["Content-Typ"]=>"text/html; charset=UTF-8" のようにキーの最後の1文字が欠落するので headers_list を使用する   
        //}
        if ( empty( $headers ) && function_exists( 'headers_list' ) ) {
        //  var_dump(headers_list());
            $headers = array();
            foreach( headers_list() as $hdr ) {
                $header_parts = explode( ':', $hdr, 2 );
                $header_name  = isset( $header_parts[0] ) ? trim( $header_parts[0] ) : '';
                $header_value = isset( $header_parts[1] ) ? trim( $header_parts[1] ) : '';
                if(empty($headers[$header_name])){
                    $headers[$header_name] = $header_value;
                } else {
                    //Link 等が複数（通常、ショート）設定されている場合あり
                    $headers[$header_name] .= ',' . $header_value;
                }
            }
        }
        foreach( $headers as $key => $value ) {
            if ( ! in_array( strtolower( $key ), $known_headers ) ) {
                unset( $headers[ $key ] );
            }
        }
        return $headers;
    }
    
    /**
     * Content Cache Data Insert/Update
     */
    static function set_content($req_url, $blogid, $postid, $value, $header, $modified, $expire) {
        global $yasakani_cache;
        $key = md5( urldecode($req_url) );
        $is_mobile = wp_is_mobile();
        $obj = $yasakani_cache->sqldb->sql_get_row( "SELECT * FROM content WHERE key = ?", array($key));
        if(!is_object($obj)){
            if($is_mobile){
                $mobile   = $value;
                $m_header = $header;
                $m_modified = $modified;
                $m_expire = $expire;
                $desktop  = null;
                $d_header = null;
                $d_modified = null;
                $d_expire = null;
            } else {
                $mobile   = null;
                $m_header = null;
                $m_modified = null;
                $m_expire = null;
                $desktop  = $value;
                $d_header = $header;
                $d_modified = $modified;
                $d_expire = $expire;
            }
            $title = $postid;
            if($postid > 0){
                $post = get_post($postid);
                $title = $post->post_title;
            } else {
                $title = wp_get_document_title();
            }
            if($yasakani_cache->sqldb->beginTransaction('IMMEDIATE')){
                try {            
                    $res = $yasakani_cache->sqldb->sql_exec("INSERT INTO content (key, req_url, blogid, postid, title, mobile, desktop, m_header, d_header, m_modified, d_modified, m_expire, d_expire) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                        array($key, $req_url, (int)$blogid, (int)$postid, $title, $mobile, $desktop, $m_header, $d_header, $m_modified, $d_modified, $m_expire, $d_expire));
                    $yasakani_cache->sqldb->commit();

                } catch (Exception $e) {
                    $yasakani_cache->sqldb->rollback();
                }                   
            }
             
            
        } else {
            if($yasakani_cache->sqldb->beginTransaction('IMMEDIATE')){
                try {    
                    if($is_mobile){
                        $res = $yasakani_cache->sqldb->sql_exec("UPDATE content SET mobile = ?, m_header = ?, m_modified = ?, m_expire = ? WHERE key = ?", 
                            array($value, $header, $modified, $expire, $key));
                    } else {
                        $res = $yasakani_cache->sqldb->sql_exec("UPDATE content SET desktop = ?, d_header = ?, d_modified = ?, d_expire = ? WHERE key = ?", 
                            array($value, $header, $modified, $expire, $key));
                    }
                    $yasakani_cache->sqldb->commit();
                } catch (Exception $e) {
                    $yasakani_cache->sqldb->rollback();
                }                
            }
        }
        return $res;
    }

    //Cashe Clear by postid 
    static function delete_id_content( $blogid, $postid, $with_home = true ) {
        global $yasakani_cache;
        if($with_home){
            $obj_ar = $yasakani_cache->sqldb->sql_get_results( "SELECT key FROM content WHERE blogid = ? AND ( postid = ? OR postid = 0)", array((int)$blogid, (int)$postid));
            if(!empty($obj_ar)){
                $yasakani_cache->sqldb->sql_exec("UPDATE content SET m_expire = NULL, d_expire = NULL WHERE blogid = ? AND ( postid = ? OR postid = 0)", array((int)$blogid, (int)$postid));
            }
        } else {
            $obj_ar = $yasakani_cache->sqldb->sql_get_results( "SELECT key FROM content WHERE blogid = ? AND postid = ?", array((int)$blogid, (int)$postid));
            if(!empty($obj_ar)){
                $yasakani_cache->sqldb->sql_exec("UPDATE content SET m_expire = NULL, d_expire = NULL WHERE blogid = ? AND postid = ?", array((int)$blogid, (int)$postid));
            }
        }
    }

    //gravatar 画像キャッシュ削除
    static function delete_gravatar($blogid) {
        $uploads = wp_upload_dir();
        if ( file_exists( $uploads['basedir'] . "/yasakani-gravatar-cache" ) ) {
            $directory = new RecursiveDirectoryIterator( 
                            $uploads['basedir'] . "/yasakani-gravatar-cache/", 
                            FilesystemIterator::CURRENT_AS_FILEINFO |
                            FilesystemIterator::KEY_AS_PATHNAME |
                            FilesystemIterator::SKIP_DOTS
                        );
            $iterator = new RecursiveIteratorIterator( $directory );
            $files = new RegexIterator( $iterator, "#uploads.yasakani\-gravatar\-cache.b$blogid.[0-9a-z]+\.(jpg|png|gif)#ui", RecursiveRegexIterator::MATCH);
            foreach($files as $file_path => $file_info) {
                if(is_file($file_path)){
                    wp_delete_file($file_path);
                }
            }            
        }
    }

    //exclude page preg_match check
    static function is_exclude_urlcmplist( $requrl ) {
        $exclude = false;
        if (!empty(self::$option['exclude_urlcmplist'])) {
            $lists = array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", self::$option['exclude_urlcmplist'])));
            foreach ($lists as $patten) {
                if( !empty($patten) && strpos( $requrl, $patten ) !== false ){
                    $exclude = true;
                    break;
                }
            }
        }
        return $exclude;
    }
    
    //Cache Store Content by template_include filter hook 
    static function cache_store_start( $is_use_themes ) {
        global $yasakani_cache;
        if ($is_use_themes && did_action('template_redirect') !== 0 && $yasakani_cache->is_enable('sqlite')) {
            global $yasakani_cache_action;
            $yasakani_cache_action['output_cache'] = true;
            //shutdown callback 
            ob_start('Yasakani_setting::output_cache');            
        }
        return $is_use_themes;
    }
    
    static function output_cache($html) {
        global $yasakani_cache;
        global $post, $yasakani_cache_action;
        if ( empty($yasakani_cache_action['output_cache']) || empty($html) ) {
            return false;
        }
        //URL Replacement in Contents. (Such as site URL change)
        if (!empty(self::$option['replace_url']) && !empty(self::$option['replace_urlsearch']) && !empty(self::$option['replace_urlreplace'])) {
            $html = str_replace(self::$option['replace_urlsearch'], self::$option['replace_urlreplace'], $html);
        }
        //HTML Output data filter
        $html = apply_filters('yasakani_output_html', $html);
        
        if (!empty(self::$option['embed_css']) || !empty(self::$option['defer_js'])) {
            //iframe(preview) 内の表示で js defer するとエラーとなるので除外
            if(!isset($_SERVER['HTTP_SEC_FETCH_DEST']) || $_SERVER['HTTP_SEC_FETCH_DEST'] !== 'iframe'){
                $html = YC_minfy::css_js_optimize($html, self::$option );
            }
        }                 
        
        $e = error_get_last();

        if (is_singular() || is_embed() || is_home() || is_front_page()) {
            $postid = (is_singular() || is_embed()) ? $post->ID : 0;
            $type = 'Store';
            if(!empty($yasakani_cache_action['cache_disable'])){
                $type = 'Cache_disable';
            } else {
                if (!empty($postid)) {
                    if (empty(self::$option['exclude_postid']) || !in_array($postid, array_map("trim", explode(',', self::$option['exclude_postid'])))) {
                        if (!empty($post->post_password)){
                            $type = 'post_password';
                        } elseif ($post->post_status != 'publish') {
                            if ($post->post_status == 'inherit') {
                                $parent = get_post($post->post_parent);
                                if ($parent->post_status != 'publish')
                                    $type = 'not_publish';
                            } else {
                                $type = 'not_publish';
                            }
                        } else {
                            $psurl = parse_url($_SERVER['REQUEST_URI']);
                            if(!empty($psurl['query']) && false !== strpos($psurl['query'], 'nonce') )
                                $type = 'exclude_page';
                        }
                        if ($type == 'Store') {
                            //exclude page preg_match check
                            if(self::is_exclude_urlcmplist( $_SERVER['REQUEST_URI'] )){
                                $type = 'exclude_page';
                            }
                        }                    
                    } else {
                        $type = 'exclude_page';
                    }
                } else {
                    if (!empty(self::$option['exclude_home']))
                        $type = 'exclude_page';
                }                
            } 
            if ($type == 'Store') {
                $e = error_get_last();
                $notice = (!empty($e) && in_array($e['type'], array(E_NOTICE, E_STRICT, E_DEPRECATED, E_USER_NOTICE, E_USER_DEPRECATED, E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING))) ? true : false;

                if (!empty($e) && $notice === false) {
                    $type = 'php_error'; //PHP Error,Warning,Notice check
                } elseif (!empty($yasakani_cache_action['comment_approve'])){
                    $type = 'comment_user';
                } elseif (is_user_logged_in()){
                    $type = 'login_user';
                } else {
                    $now_utc = new DateTime("now", new DateTimeZone('utc'));
                    $modified = $now_utc->format('D, d M Y H:i:s') . ' GMT';
                    $expire_sec = self::$option['expire_sec'];
                    $expd = $now_utc->modify("+{$expire_sec} second");
                    @header('Cache-Control: no-cache');
                    @header('Last-Modified: ' . $modified);
                    @header('Vary: Accept-Encoding');
                    
                    $headers = self::get_response_headers();
                    $gzhtml = $html;
                    if ( function_exists('gzencode') && function_exists('gzdecode') ) {
                        //保存容量の縮小及び高速化の為 gzip 形式でキャッシュ保存 (圧縮レベル 1-9 でテストして 5 に決定)
                        $gzhtml = gzencode($html, 5);
                        $headers['Content-Encoding'] = 'gzip';
                    }
                    if(!empty($headers))
                        $jsonheader = wp_json_encode($headers);
                    $jsonheader = (!empty($jsonheader))? $jsonheader : '';
                    
                    if(! $yasakani_cache->is_enable('maintenance')){
                        self::set_content($_SERVER['REQUEST_URI'], self::$blogid, $postid, $gzhtml, $jsonheader, $modified, $expd->format('Y-m-d H:i:s'));                        
                    }

                    //current access gzip support?
                    if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false)) {
                        @header('Content-Encoding: gzip' );
                        $html = $gzhtml;
                    }
                }
            }
            if (is_home() || is_front_page()){
                $title = wp_get_document_title();
            } else {
                $title = single_post_title('', false);
            }
            $yasakani_cache->put_log($type, $_SERVER['REQUEST_URI'], $title, self::$blogid, $postid, $e);
            
        } else {
            if (is_404()){
                $type = '404';
            } elseif (is_search()) {
                $type = 'Search';
            } elseif (is_archive()) {
                $type = 'Archive';
            } else {
                $type = 'etc';
            }
            $title = wp_get_document_title();
            $yasakani_cache->put_log($type, $_SERVER['REQUEST_URI'], $title, self::$blogid, 0, $e);
        }
        return $html;
    }  
    
    //Dropin plugin advanced-cache.php file
    static function advanced_cache_file($type) {
        require_once( ABSPATH . 'wp-admin/includes/file.php');
        $adcfile = WP_CONTENT_DIR . '/advanced-cache.php';
        if ($type == 'create') {
            global $wp_filesystem;
            if (WP_Filesystem($adcfile)) {
                if (!$wp_filesystem->exists($adcfile)) {
                    $adc = "<?php" . PHP_EOL;
                    $adc .= "/* Module Name: advanced-cache by yasakani cache */" . PHP_EOL;
                    $adc .= "/* Type: 2 */" . PHP_EOL;
                    $adc .= "if ( !defined('YASAKANI_CACHE_DIR') ){" . PHP_EOL;
                    $adc .= "define('YA_CONTENT_DIR', '" . WP_CONTENT_DIR . "');" . PHP_EOL;
                    $adc .= "define('YASAKANI_CACHE_DIR', '" . untrailingslashit(plugin_dir_path(__FILE__)) . "');" . PHP_EOL;
                    $adc .= "define('YASAKANI_CACHE_DB', '" . WP_CONTENT_DIR . '/yasakani-cache/yasakani_cache.db' . "');" . PHP_EOL;
                    $adc .= "include_once( YASAKANI_CACHE_DIR . '/magatama_lv1.php' );" . PHP_EOL;
                    $adc .= "}" . PHP_EOL;
                    if (!$wp_filesystem->put_contents($adcfile, $adc, FS_CHMOD_FILE)){
                        self::my_admin_notice( 'advanced-cache.php ' . esc_html__('file create error', 'yasakani'));
                    }
                }
            }
        } elseif ($type == 'delete') {
            if (file_exists($adcfile)) {
                $hed = get_file_data($adcfile, array('Name' => 'Module Name'));
                if (!empty($hed['Name']) && false !== strpos($hed['Name'], "yasakani cache"))
                    wp_delete_file($adcfile);
            }
        }
    }

    //yasakani-cache-exload.php create/update（ファイル削除は手動で削除したほうが安全なのでここではしない）
    static function yasakani_cache_exload($type) {
        require_once( ABSPATH . 'wp-admin/includes/file.php');
        $exlfile = WP_CONTENT_DIR . '/yasakani-cache-exload.php';
        if ($type == 'update') {
            if (file_exists($exlfile)) {
                wp_delete_file( $exlfile );
            }
        }
        global $wp_filesystem;
        if (WP_Filesystem($exlfile)) {
            if ($type == 'create' || $type == 'update') {
                if (!$wp_filesystem->exists($exlfile)) {
                    $exl = '<?php' . PHP_EOL;
                    $exl .= '/* Module Name: yasakani-cache-exload called by auto_prepend_file  */' . PHP_EOL;
                    $exl .= '/* Type: 2 */' . PHP_EOL;
                    $exl .= "define('ADVANCED_CACHE_FILE', '" . WP_CONTENT_DIR . '/advanced-cache.php' . "');" . PHP_EOL;
                    $exl .= 'if(is_file(ADVANCED_CACHE_FILE)){'. PHP_EOL;
                    $exl .= '$fp = @fopen(ADVANCED_CACHE_FILE, "r" ); $file_data = @fread( $fp, 128 ); @fclose($fp);'. PHP_EOL;
                    $exl .= 'if(false !== strpos($file_data, "yasakani cache")) { ' . PHP_EOL;
                    $exl .= "define('YA_CONTENT_DIR', '" . WP_CONTENT_DIR . "');" . PHP_EOL;
                    $exl .= "define('YASAKANI_CACHE_DIR', '" . untrailingslashit(plugin_dir_path(__FILE__)) . "');" . PHP_EOL;
                    $exl .= "define('YASAKANI_CACHE_DB', '" . WP_CONTENT_DIR . '/yasakani-cache/yasakani_cache.db' . "');" . PHP_EOL;
                    $exl .= "include_once( YASAKANI_CACHE_DIR . '/magatama_lv1.php' );" . PHP_EOL;
                    $exl .= '}' . PHP_EOL;
                    $exl .= '}' . PHP_EOL;
                    if (!$wp_filesystem->put_contents($exlfile, $exl, FS_CHMOD_FILE)){
                        self::my_admin_notice( 'yasakani-cache-exload.php ' . esc_html__('file create error', 'yasakani'));
                    }
                }
            }           
        }
    }
    
    //wp-config.php file customize
    static function insert_cache_define($matches) {
        $content = "define('WP_CACHE', true); //Added by Yasakani cache" . PHP_EOL . $matches[0];
        return $content;
    }

    static function replace_cache_define($matches) {
        if ($matches[1] == 'false' || $matches[0][0] == '/')
            $content = "define('WP_CACHE', true); //Added by Yasakani cache";
        else
            $content = "//" . $matches[0];
        return $content;
    }

    //File editing of synchronization folder of the virtual environment is delayed to reflect
    //eg: vagrant sync folder wp-config.php edi
    static function wp_config_file($type = 'read') {
        require_once( ABSPATH . 'wp-admin/includes/file.php');
        global $wp_filesystem;
        $res = false;
        $config_file = (file_exists(ABSPATH . 'wp-config.php') ) ? ABSPATH . 'wp-config.php' : dirname(ABSPATH) . '/wp-config.php';
        if (WP_Filesystem($config_file)) {
            if (!$wp_filesystem->exists($config_file)) {
                self::my_admin_notice(__('wp-config.php file not found', 'yasakani'));
                return false;
            }
            $config_source = $wp_filesystem->get_contents($config_file);
            if (empty($config_source)) {
                self::my_admin_notice(__('Read error of wp-config.php file', 'yasakani'));
                return false;
            } else {
                $config_source = preg_replace('/\r\n?/', "\n", $config_source); //win,mac => unix line feed
                if ($type == 'read') {
                    return $config_source;
                } elseif ($type == 'insert_wp_cache') {
                    if (!defined('WP_CACHE') || constant('WP_CACHE') === false) {
                        if (!($wp_filesystem->is_readable($config_file) && $wp_filesystem->is_writable($config_file))) {
                            self::my_admin_notice(__('No write permission to wp-config.php file', 'yasakani'));
                            return false;
                        }
                        if (preg_match("#^define.+?'WP_CACHE'.+?false.+?$#m", $config_source)) {
                            $config_source = preg_replace_callback("#^define.+?'WP_CACHE'.+?(false).+?$#m", "Yasakani_setting::replace_cache_define", $config_source, 1);
                        } elseif (preg_match("#^//define.+?'WP_CACHE'.+?true.+?//Added by Yasakani cache#m", $config_source)) {
                            $config_source = preg_replace_callback("#^//define.+?'WP_CACHE'.+?(true).+?//Added by Yasakani cache#m", "Yasakani_setting::replace_cache_define", $config_source, 1);
                        } else {
                            $config_source = preg_replace_callback("#^define.+?'DB_NAME'.+?;#m", "Yasakani_setting::insert_cache_define", $config_source, 1);
                        }
                        if (!empty($config_source) && preg_match("#^define.+?'WP_CACHE'.+?true.+?$#m", $config_source)) {
                            $res = $wp_filesystem->put_contents($config_file, $config_source);
                            if ($res === false)
                                self::my_admin_notice(__('WP_CACHE defined code insertion errors to wp-config.php file', 'yasakani'));
                        }
                    } else
                        $res = true;
                } elseif ($type == 'delete_wp_cache') {
                    if (preg_match("#^define.'WP_CACHE', true.+?$#m", $config_source)) {
                        if (!($wp_filesystem->is_readable($config_file) && $wp_filesystem->is_writable($config_file))) {
                            self::my_admin_notice(__('No write permission to wp-config.php file', 'yasakani'));
                            return false;
                        }
                        $config_source = preg_replace_callback("#^define.+?'WP_CACHE'.+?(true).+?$#m", "Yasakani_setting::replace_cache_define", $config_source);
                        if (!empty($config_source) && preg_match("#^//define.+?'WP_CACHE'.+?true.+?$#m", $config_source))
                            $res = $wp_filesystem->put_contents($config_file, $config_source);
                    } else
                        $res = true;
                }
            }
        }
        return $res;
    }

    public function bbpress_related_ids_cache($post) {
        $current = $post;
        $ids[] = $post->ID;
        while(!empty($post->post_parent)){
            $post = get_post($post->post_parent);
            if(!empty($post)){
                $ids[] = $post->ID;
            }
        }
        $childs = get_children( array('post_parent' => $current->ID, 'post_type' => array('forum','topic','reply') ));
        foreach ($childs as $key => $child) {
            if(!empty($child)){
                $ids[] = $child->ID;
            }
        }
        return $ids;
    }
    
    //Post status
    //save_post,update_post,wp_trash_post... Supplement all of post state change
    public function yasakani_cache_statpost($new_status, $old_status, $post) {
        $ids = array();
        //bbPress なら親や子に該当するキャッシュもクリア
        if ( in_array($post->post_type, array('forum', 'topic', 'reply'))){
            $ids = $this->bbpress_related_ids_cache($post);
        } else {
            $ids[] = $post->ID;
        }
        foreach ($ids as $id) {
            self::delete_id_content(self::$blogid, $id);
        }
    }

    public function yasakani_cache_delpost($postid) {
        $dpost = get_post($postid);
        if(!empty($dpost)){
            $ids = array();
            if ( in_array($dpost->post_type, array('forum', 'topic', 'reply'))){
                $ids = $this->bbpress_related_ids_cache($dpost);
            } else {
                $ids[] = $dpost->ID;
            }
            foreach ($ids as $id) {
                self::delete_id_content(self::$blogid, $id);
            }
        }
    }

    /**
     * Content Cache Data Delete
     */ 
    // All Cache Clear
    static function delete_all_content( $opt = '') {
        global $yasakani_cache;
        global $yasakani_cache_action;
        if (!empty($yasakani_cache) && $yasakani_cache->is_enable('sqlite')) {
            if(! $yasakani_cache->is_enable('maintenance')){

                YC_TABLE::set_maintenance_mode(1);
                if($yasakani_cache->sqldb->beginTransaction('IMMEDIATE')){   //参照可、更新不可でロック
                    try {
                        $yasakani_cache->sqldb->sql_exec( "DROP TABLE IF EXISTS content" );
                        $yasakani_cache->sqldb->sql_exec( YC_TABLE::CREATE_CONTENT_TABLE );
                        $yasakani_cache->sqldb->sql_exec( "CREATE UNIQUE INDEX key ON content (key);" );
                        $yasakani_cache->sqldb->sql_exec( "CREATE INDEX postid ON content (blogid,postid);" );
                        $yasakani_cache->sqldb->commit();

                        if($opt == 'VACUUM'){
                            $yasakani_cache->sqldb->command("VACUUM");
                            $yasakani_cache->sqldb->command("PRAGMA wal_checkpoint(truncate)");                        
                        }                    
                    } catch (Exception $e) {
                        $yasakani_cache->sqldb->rollback();
                        $errmsg = $e->getMessage();
                        $yasakani_cache_action['db_error'] = $errmsg;
                    }
                }
                YC_TABLE::set_maintenance_mode(0);

                if(empty($yasakani_cache_action['db_error'])){
                    if (is_multisite()) {
                        if (!$network_wide) {
                            global $wpdb;
                            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
                            foreach ($blog_ids as $blogid) {
                                self::delete_gravatar($blogid);
                            }
                        }
                    } else {
                        $blogid = get_current_blog_id();
                        self::delete_gravatar($blogid);
                    }
                }
            }
        }
    }
    
    //Comment status
    //'hold', 'approve', 'spam', 'trash', 'delete' ... Supplement all of comment state change
    public function yasakani_cache_statcomment($comment_ID, $comment_status) {
        $cobj = get_comment($comment_ID);
        if (is_object($cobj))
            self::delete_id_content(self::$blogid, $cobj->comment_post_ID);
    }

    public function yasakani_cache_newcomment($comment_ID, $comment_approved, $commentdata) {
        if (!empty($commentdata['comment_post_ID']))
            self::delete_id_content(self::$blogid, $commentdata['comment_post_ID']);
    }

    public function yasakani_cache_editcomment($comment_ID) {
        $cobj = get_comment($comment_ID);
        if (is_object($cobj))
            self::delete_id_content(self::$blogid, $cobj->comment_post_ID);
    }

    public function yasakani_cache_allclear() {
        self::delete_all_content();
    }
    
    public function sitekey_update() {
        global $yasakani_cache;
        $sitekey = '';
        if (is_multisite()) {
            global $wpdb;
            $current_blog_id = get_current_blog_id();
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blog_ids as $blog_id) {
                if ($blog_id != $current_blog_id) {
                    switch_to_blog($blog_id);
                    $siteurl = get_site_option( 'siteurl' );
                    if ( $siteurl ){
                        $sitekey .= (empty($sitekey))? $siteurl : ','. $siteurl; 
                    }
                }
            }
            switch_to_blog($current_blog_id);
        } else {
            $siteurl = get_site_option( 'siteurl' );
            if ( $siteurl ){
                $sitekey = $siteurl;
            }
        }
        if(self::$setting['sitekey'] != $sitekey){
            self::$setting['sitekey'] = $sitekey;
            $yasakani_cache->sqldb->sql_exec("UPDATE setting SET sitekey = ?", array( $sitekey ));
        }        
    }    

    //管理画面からの設定更新時にタイムゾーンデータの確認を行い変更あれば更新
    public function setting_timezone_check() {
        global $yasakani_cache;
        $obj = $yasakani_cache->sqldb->sql_get_row( "SELECT * FROM setting WHERE ROWID = 1");
        if(is_object($obj)){
            $timezone = wp_timezone_string();
            $localtm = new DateTime("now", new DateTimeZone($timezone));
            $backupday = $localtm->format("Y-m-d");
            if($obj->timezone != $timezone){
                $yasakani_cache->sqldb->sql_exec("UPDATE setting SET timezone = ?, log_backup = ?", array( $timezone, $backupday ));
            } 
        }        
    }

    public function set_log_mode($log_mode) {
        global $yasakani_cache;
        if(self::$setting['log_mode'] != $log_mode){
            self::$setting['log_mode'] = (int)$log_mode;
            $yasakani_cache->sqldb->sql_exec("UPDATE setting SET log_mode = ?", array( (int)$log_mode));
        }
    }


    //Yasakani cache option action request (clear, save)
    function action_posts() {
        global $yasakani_cache;
        if (current_user_can('activate_plugins')) {
            if ($yasakani_cache->is_enable('sqlite')) {
                global $yasakani_cache_action;
                //Cache setting
                if (isset($_POST['yasakani_save']) && isset($_POST['yasakani'])) {
                    check_admin_referer('yasakani-cache');
                    if (!empty($_POST['yasakani']['enable']) && in_array($_POST['yasakani']['enable'], array('simple', 'expert') )) {
                        $mode = $_POST['yasakani']['enable'];
                        self::advanced_cache_file('create');
                        $ret = self::wp_config_file('insert_wp_cache');
                        self::$option['enable'] = ($ret === false) ? 0 : $mode;
                    } else {
                        self::$option['enable'] = 0;
                    }
                    $oopt['expire_sec'] = self::$option['expire_sec'];
                    $oopt['embed_css'] = self::$option['embed_css'];
                    $oopt['minify_core_block_css'] = self::$option['minify_core_block_css'];                    
                    $oopt['defer_js'] = self::$option['defer_js'];
                    $oopt['avatar_cache'] = self::$option['avatar_cache'];
                    self::$option['expire_sec'] = (!empty($_POST['yasakani']['expire_sec'])) ? (int) $_POST['yasakani']['expire_sec'] : 600;

                    self::$option['embed_css'] = (!empty($_POST['yasakani']['embed_css'])) ? (int) $_POST['yasakani']['embed_css'] : 0;
                    self::$option['minify_core_block_css'] = (!empty($_POST['yasakani']['minify_core_block_css'])) ? (int) $_POST['yasakani']['minify_core_block_css'] : 0;
                    $list = (!empty($_POST['yasakani']['tree_shaking_css'])) ? array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", stripslashes_from_strings_only($_POST['yasakani']['tree_shaking_css'])))) : '';
                    self::$option['tree_shaking_css'] = (!empty($list))? implode("\n", $list) : ''; 
                    $list = (!empty($_POST['yasakani']['exclude_tree_shaking_name'])) ? array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", stripslashes_from_strings_only($_POST['yasakani']['exclude_tree_shaking_name'])))) : '';
                    self::$option['exclude_tree_shaking_name'] = (!empty($list))? implode("\n", $list) : ''; 

                    self::$option['defer_js'] = (!empty($_POST['yasakani']['defer_js'])) ? (int) $_POST['yasakani']['defer_js'] : 0; 
                    //trimを行って改行区切り文字列へ戻す
                    $list = (!empty($_POST['yasakani']['exclude_defer_js'])) ? array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", stripslashes_from_strings_only($_POST['yasakani']['exclude_defer_js'])))) : '';
                    self::$option['exclude_defer_js'] = (!empty($list))? implode("\n", $list) : ''; 
                    self::$option['avatar_cache'] = (!empty($_POST['yasakani']['avatar_cache'])) ? (int) $_POST['yasakani']['avatar_cache'] : 0; 
                    self::$option['exclude_home'] = (!empty($_POST['yasakani']['exclude_home'])) ? (int) $_POST['yasakani']['exclude_home'] : 0;
                    $list = (!empty($_POST['yasakani']['exclude_urlcmplist'])) ? array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", stripslashes_from_strings_only($_POST['yasakani']['exclude_urlcmplist'])))) : '';
                    self::$option['exclude_urlcmplist'] = (!empty($list))? implode("\n", $list) : ''; 

                    if (empty(self::$option['enable']) || $oopt['expire_sec'] !== self::$option['expire_sec'] ) {
                        self::delete_all_content();
                    } elseif (self::$option['exclude_home']) {
                        self::delete_id_content(self::$blogid, 0, false);
                    }
                    if (is_main_site()) {
                        $this->sitekey_update();
                        $this->setting_timezone_check();
                        $log_mode = (!empty($_POST['yasakani']['log_mode'])) ? (int) $_POST['yasakani']['log_mode'] : 0;
                        if ($log_mode > 2)
                            $log_mode = 2;
                        $this->set_log_mode($log_mode);
                    }
                    update_option('yasakani_option', self::$option);
                    wp_safe_redirect(admin_url('options-general.php?page=yasakani-cache#metabox_yc_cache'));
                    exit;
                }
                else if (!empty($_GET['action']) && $_GET['action'] == 'del_exclude_postid' && !empty($_GET['pid'])) {
                    check_admin_referer('yasakani-cache');
                    $ids = (!empty(self::$option['exclude_postid'])) ? array_map("trim", explode(',', self::$option['exclude_postid'])) : array();
                    if (($key = array_search((int) $_GET['pid'], $ids)) !== false) {
                        unset($ids[$key]);
                    }
                    self::$option['exclude_postid'] = (!empty($ids)) ? implode(",", $ids) : '';
                    update_option('yasakani_option', self::$option);
                    wp_safe_redirect(admin_url('options-general.php?page=yasakani-cache#metabox_yc_cache'));
                    exit;
                }
                //Maintenance(Main Site only)
                elseif (isset($_POST['yasakani_export'])) {
                    check_admin_referer('yasakani-export');
                    if (is_main_site()) {
                        $option = array(
                            'tree_shaking_css'  => self::$option['tree_shaking_css'],
                            'exclude_tree_shaking_name'  => self::$option['exclude_tree_shaking_name'],
                            'exclude_defer_js'  => self::$option['exclude_defer_js'],
                            'exclude_home'      => self::$option['exclude_home'],
                            'exclude_postid'    => self::$option['exclude_postid'],
                            'exclude_urlcmplist'=> self::$option['exclude_urlcmplist'],
                            'bot_key'           => self::$setting['bot_key'],
                            'block_user'        => self::$setting['block_user'],
                            'botblocklist'      => self::$setting['botblocklist'],
                            'trustedfile'       => self::$setting['trustedfile'],
                            'protectlist'       => self::$setting['protectlist'],
                        );
                        
                        //アドオン等のエクスポートデータ用フィルター
                        $option = apply_filters('yc_additional_features_option_export', $option);
                        
                        $optjson = wp_json_encode( $option );
                        if(!empty($optjson)){
                            $file = 'yasakani_cache_option.json';
                            header('Content-Description: File Transfer');
                            header("Content-Type:application/json");
                            header("Content-Disposition: attachment; filename=$file");
                            header('Expires: 0');
                            header('Cache-Control: must-revalidate');
                            header('Pragma: public');
                            header('Content-Length: ' . strlen($optjson));
                            echo $optjson;
                            @flush();
                            exit;
                        }
                    }
                    wp_safe_redirect(admin_url('options-general.php?page=yasakani-cache#metabox_yc_maintenance'));
                    exit;
                }
                elseif (isset($_POST['yasakani_import'])) {
                    check_admin_referer('yasakani-import');
                    if (is_main_site()) {
                        if(!empty($_FILES) && !empty($_FILES['yasakani_settings_import']['tmp_name'])) {
                            if(!empty($_FILES['yasakani_settings_import']['type']) && $_FILES['yasakani_settings_import']['type'] == 'application/json'){
                                $size = !empty($_FILES['yasakani_settings_import']['size'])? $_FILES['yasakani_settings_import']['size'] : 0;
                                if($size > 0 && $size < 30000){
                                    $impfile =  YASAKANI_CACHE_DB_DIR . '/' . md5($_FILES['yasakani_settings_import']['tmp_name']) . '.json';
                                    if(move_uploaded_file($_FILES['yasakani_settings_import']['tmp_name'], $impfile)) {
                                        $fh = new SplFileObject( $impfile, "rb" );
                                        $data = $fh->fread($fh->getSize());
                                        $fh = null;                                        
                                        if($data !== false){
                                            if(is_array(json_decode($data, true)) && (json_last_error() == JSON_ERROR_NONE)){
                                                $option = json_decode( $data, true);
                                                foreach ($option as $key => $value) {
                                                    switch($key){
                                                    case 'tree_shaking_css':
                                                        self::$option['tree_shaking_css'] = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                                                        break;
                                                    case 'exclude_tree_shaking_name':
                                                        self::$option['exclude_tree_shaking_name'] = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                                                        break;
                                                    case 'exclude_defer_js':
                                                        self::$option['exclude_defer_js'] = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                                                        break;
                                                    case 'exclude_home':
                                                        self::$option['exclude_home'] = (!empty($value)) ? 1 : 0;
                                                        if (self::$option['exclude_home']) {
                                                            self::delete_id_content(self::$blogid, 0, false);
                                                        }
                                                        break;
                                                    case 'exclude_postid':
                                                        self::$option['exclude_postid'] = (!empty($value)) ? $value : '';
                                                        if(!empty($value)){
                                                            $ids = array_map("trim", explode(',', $value));
                                                            foreach($ids as $id){
                                                                self::delete_id_content(self::$blogid, $id);
                                                            }
                                                        }
                                                        break;
                                                    case 'exclude_urlcmplist':
                                                        self::$option['exclude_urlcmplist'] = (!empty($value)) ? stripslashes_from_strings_only(trim($value)) : '';
                                                        if(!empty($value)){
                                                            //全キャッシュクリアはマニュアル操作のクリアに任せる
                                                        }
                                                        break;
                                                    default:
                                                        break;
                                                    }
                                                }
                                                update_option('yasakani_option', self::$option);

                                                //アドオン等のインポート登録更新用フック
                                                do_action( 'yc_additional_features_option_import', $option );
                                            }
                                        }
                                        wp_delete_file($impfile);
                                    }
                                }
                            }
                        }
                    }
                    wp_safe_redirect(admin_url('options-general.php?page=yasakani-cache#metabox_yc_maintenance'));
                    exit;
                }
                elseif (isset($_POST['yasakani_clear'])) {
                    check_admin_referer('yasakani-clear');
                    if (is_main_site()) {                    
                        self::delete_all_content('VACUUM');
                        if(!empty($yasakani_cache_action['db_error'])){
                            Yasakani_option::set_db_notice( $yasakani_cache_action['db_error'] );
                        }
                    }
                    wp_safe_redirect(admin_url('options-general.php?page=yasakani-cache#metabox_yc_maintenance'));
                    exit;
                }
                elseif (isset($_POST['yasakani_reset'])) {
                    check_admin_referer('yasakani-reset');
                    if (is_main_site()) {                    
                        YC_TABLE::reset_create();
                        if(!empty($yasakani_cache_action['db_error'])){
                            Yasakani_option::set_db_notice( $yasakani_cache_action['db_error'] );
                        }
                    }
                    wp_safe_redirect(admin_url('options-general.php?page=yasakani-cache#metabox_yc_maintenance'));
                    exit;
                }

                //アドオン等のオプション登録更新用フック
                do_action( 'yc_additional_features_option_update' );
            }
        }
    }
                    
    //プラグイン一覧に更新時の注意メッセージを追加
    //※ダッシュボードの更新ページの一括アップデートでは readme.txt == Upgrade Notice == に定義したメッセージが表示される（日本語化は不可）
    public function update_notice_message($plugin_data, $response) {
        if (!empty($plugin_data['update']) && $plugin_data['slug'] == 'yasakani-cache'){
            echo '<br /><span style="padding: 10px; margin-top: 10px"> ' . esc_html__('[Upgrade Notice] : Plug-in update must be done with plug-in deactivated.', 'yasakani') , '</span>';
        }        
    }
    
    //Yasakani cache admin setting start 
    public function yasakani_admin_start() {
        add_action('admin_menu', array($this, 'yasakani_option_menu'));
    }

    //Settings menu add
    public function yasakani_option_menu() {
        if(!empty(self::$optpg)){
            $page = add_options_page('YASAKANI Cache', esc_html__('YASAKANI Cache', 'yasakani'), 'manage_options', 'yasakani-cache', array(self::$optpg, 'yasakani_option_page'));
            add_action('admin_print_scripts-' . $page, array(self::$optpg, 'yasakani_scripts'));
        }
    }

    /***************************************************************************
     * Post Edit Meta box
     * Exclude page cache meta box for Post/Page/CustomPost
     ************************************************************************* */
    function load_meta_boxes($post_type, $post) {
        if (current_user_can('activate_plugins', $post->ID)) {
            add_meta_box('yasakani-exclude-div', esc_html__('YASAKANI Cache', 'yasakani'), array($this, 'yasakani_meta_box'), null, 'side');
            add_action('admin_footer', array($this, 'yasakani_meta_script'));
        }
    }

    function yasakani_meta_box($post, $box) {
        global $yasakani_cache;
        if (is_object($post)) {
            echo '<div id="yasakani-cache-select">';
            echo '<style>#yasakani-cache-select p { margin: 1em 0;}</style>';
            $exclude = null;
            if ($yasakani_cache->is_enable('sqlite')) {
                if(!empty($post->post_password) || $post->post_status == 'private'){
                    $disp_notice_sw = 'display:inline;';
                    $disp_ui_sw = 'display:none;';
                } else {
                    $disp_notice_sw = 'display:none;';
                    $disp_ui_sw = 'display:inline;';
                }
                echo '<div id="yc-select-notice" style="'. $disp_notice_sw . '">';
                echo '<p style="color:#007cba;">' . esc_html__('This post is password protected or private, so it will not cache.', 'yasakani') . '</p>';
                echo '</div>';

                $ajax_nonce = wp_create_nonce('yasakani-cache-' . $post->ID);
                $exclude = (!empty(self::$option['exclude_postid']) && in_array($post->ID, array_map("trim", explode(',', self::$option['exclude_postid']))))? 'exclude' : 'include';
                $obj_ar = null;
                if ($exclude == 'include') {
                    //公開ページ以外はキャッシュ有無を確認しない
                    // draft 下書き / pending 承認待ち / future 予約中 / publish 公開 / private 非公開 / trash ゴミ箱 / auto-draft 自動保存 / inherit 継承(child post) / bbpress(spam, closed)
                    if (in_array( $post->post_status, array( 'publish', 'inherit', 'closed' ) ) ) {  //bbpress(closed)
                        $obj_ar = $yasakani_cache->sqldb->sql_get_row( "SELECT key FROM content WHERE blogid = ? AND postid = ? AND ( d_expire IS NOT NULL OR m_expire IS NOT NULL)", array((int)self::$blogid, (int)$post->ID));
                    }
                }
                $status = (!empty($obj_ar))?  esc_html__('Status : With cache', 'yasakani') : esc_html__('Status : No cache', 'yasakani');
                echo '<div id="yc-select-ui" style="'. $disp_ui_sw . '">';
                echo '<p class="components-panel__row hide-if-no-js"><span id="yasakani-cache-status" >' . $status . '</span><a class="button" style="margin-left: 20px;" onclick="YasakaniExclude(\'' . $ajax_nonce . '\',\'clear\');return false;" >' . esc_html__('Clear') . '</a></p>';
                //Cache option 
                echo "<p><label><input type='checkbox' name='yasakani-exclude' value='include' " . checked($exclude, 'exclude', false) . '/>' . esc_html__('Do not Cache this post', 'yasakani') . '</label></p>';
                echo '</div>';
                
                //CSS Tree Shaking option
                if (!empty(self::$option['embed_css'])){
                    $exclude_css = (!empty(self::$option['exclude_tree_shaking_postid']) && in_array($post->ID, array_map("trim", explode(',', self::$option['exclude_tree_shaking_postid']))))? 'exclude' : 'include';
                    echo "<p><label><input type='checkbox' name='yasakani-exclude-css' value='include' " . checked($exclude_css, 'exclude', false) . '/>' . esc_html__('Do not Minify CSS', 'yasakani') . '</label></p>';
                } 
                //JS defer option
                if (!empty(self::$option['defer_js'])){
                    $exclude_js = (!empty(self::$option['exclude_defer_js_postid']) && in_array($post->ID, array_map("trim", explode(',', self::$option['exclude_defer_js_postid']))))? 'exclude' : 'include';
                    echo "<p><label><input type='checkbox' name='yasakani-exclude-js' value='include' " . checked($exclude_js, 'exclude', false) . '/>' . esc_html__('Do not JS defer-asynchronously', 'yasakani') . '</label></p>';
                }
                if ($disp_ui_sw !== 'display:none;' || !empty(self::$option['embed_css']) || !empty(self::$option['defer_js'])){
                    echo '<p class="hide-if-no-js"><a id="yasakani-exclude-submit" class="button" href="#yasakani-exclude-div" onclick="YasakaniExclude(\'' . $ajax_nonce . '\',\'save\');return false;" >' . esc_html__('Save') . '</a></p>';
                }
            }
            else {
                echo '<p style="color: red;">' . esc_html__('Yasakani Cache is not enabled.', 'yasakani') . '</p>';
            }
            echo '</div>';
        }
    }

    //wp_ajax_yasakani_clear called function
    function yasakani_ajax_clear() {
        global $yasakani_cache;
        if (isset($_POST['post_id'])) {
            $pid = (int) $_POST['post_id'];
            if (!current_user_can('activate_plugins', $pid))
                wp_die(-1);
            check_ajax_referer("yasakani-cache-$pid");

            $status = '';
            if ($yasakani_cache->is_enable('sqlite')) {
                //Cache Clear by postid
                self::delete_id_content(self::$blogid, $pid);

                $obj_ar = null;
                $post = get_post($pid);
				if (!empty($post) &&  in_array( $post->post_status, array( 'publish', 'inherit', 'closed' ) ) ) {
                    $obj_ar = $yasakani_cache->sqldb->sql_get_row( "SELECT key FROM content WHERE blogid = ? AND postid = ? AND ( d_expire IS NOT NULL OR m_expire IS NOT NULL)", array((int)self::$blogid, (int)$pid));
                }
                $status = (!empty($obj_ar))?  esc_html__('Status : With cache', 'yasakani') : esc_html__('Status : No cache', 'yasakani');
                
            }
            ob_end_clean(); //JS に json データを出力する前に念の為バッファクリア                
            wp_send_json_success( $status );
        }
        wp_die(0);
    }
    
    //wp_ajax_yasakani_exclude called function
    function yasakani_ajax_exclude() {
        global $yasakani_cache;
        if (isset($_POST['post_id'])) {
            $pid = (int) $_POST['post_id'];
            if (!current_user_can('activate_plugins', $pid)){
                wp_die(-1);
            }
            check_ajax_referer("yasakani-cache-$pid");

            $status = '';
            if ($yasakani_cache->is_enable('sqlite')) {
                if(!empty($_POST['excl_cache'])){
                    $ids = (!empty(self::$option['exclude_postid']))? array_map("trim", explode(',', self::$option['exclude_postid'])) : array();
                    if ($_POST['excl_cache'] == 'true') {
                        if (!in_array($pid, $ids)){
                            $ids[] = $pid;
                        }
                    } else {
                        if (($key = array_search($pid, $ids)) !== false){
                            unset($ids[$key]);
                        }
                    }
                    self::$option['exclude_postid'] = (!empty($ids)) ? implode(",", $ids) : '';
                }
                if (!empty(self::$option['exclude_postid'])) {
                    //Cache Clear by postid
                    self::delete_id_content(self::$blogid, $pid);
                }

                if(!empty($_POST['excl_css'])){
                    $ids = (!empty(self::$option['exclude_tree_shaking_postid']))? array_map("trim", explode(',', self::$option['exclude_tree_shaking_postid'])) : array();
                    if ($_POST['excl_css'] == 'true') {
                        if (!in_array($pid, $ids)){
                            $ids[] = $pid;
                        }
                    } else {
                        if (($key = array_search($pid, $ids)) !== false){
                            unset($ids[$key]);
                        }
                    }
                    self::$option['exclude_tree_shaking_postid'] = (!empty($ids)) ? implode(",", $ids) : '';
                }
                
                if(!empty($_POST['excl_js'])){
                    $ids = (!empty(self::$option['exclude_defer_js_postid']))? array_map("trim", explode(',', self::$option['exclude_defer_js_postid'])) : array();
                    if ($_POST['excl_js'] == 'true') {
                        if (!in_array($pid, $ids)){
                            $ids[] = $pid;
                        }
                    } else {
                        if (($key = array_search($pid, $ids)) !== false){
                            unset($ids[$key]);
                        }
                    }
                    self::$option['exclude_defer_js_postid'] = (!empty($ids)) ? implode(",", $ids) : '';
                }

                update_option('yasakani_option', self::$option);
                
                $obj_ar = null;
                if ($_POST['excl_cache'] == 'false') {
                    $post = get_post($pid);
        			if (!empty($post) &&  in_array( $post->post_status, array( 'publish', 'inherit', 'closed' ) ) ) {
                        $obj_ar = $yasakani_cache->sqldb->sql_get_row( "SELECT key FROM content WHERE blogid = ? AND postid = ? AND ( d_expire IS NOT NULL OR m_expire IS NOT NULL)", array((int)self::$blogid, (int)$pid));
                    }
                }
                $status = (!empty($obj_ar))?  esc_html__('Status : With cache', 'yasakani') : esc_html__('Status : No cache', 'yasakani');

            }
            ob_end_clean(); //JS に json データを出力する前に念の為バッファクリア                
            wp_send_json_success( $status );
        }
        wp_die(0);
    }

    function yasakani_meta_script() {
    ?>
    <script type='text/javascript' >
    YasakaniExclude = function (nonce, type) {
        const tcache = document.querySelector('input[name="yasakani-exclude"]');
        const excl_cache = (tcache)? tcache.checked : '';
        if(type == 'clear'){
            if(excl_cache != true){
                fetch( ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 
                        action: "yasakani_clear",
                        post_id: document.querySelector('#post_ID').value,
                        _ajax_nonce: nonce                             
                    }).toString()  
                })   
                .then( function(response){
                    if(response.ok) {
                        return response.json();
                    }
                    throw new Error('Network response was not ok.');
                })
                .then( function(json) {
                    if(json.data !== ''){
                        document.querySelector('#yasakani-cache-status').innerHTML = json.data;
                    }
                })                    
                .catch( function(error){})        
            }
        } else {
            const tcss = document.querySelector('input[name="yasakani-exclude-css"]');
            const excl_css = (tcss)? tcss.checked : '';
            const tjs = document.querySelector('input[name="yasakani-exclude-js"]');
            const excl_js  = (tjs)? tjs.checked : '';
            fetch( ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ 
                    action: "yasakani_exclude",
                    post_id: document.querySelector('#post_ID').value,
                    excl_cache: excl_cache,
                    excl_css: excl_css,
                    excl_js: excl_js,
                    _ajax_nonce: nonce                             
                }).toString()  
            })   
            .then( function(response){
                if(response.ok) {
                    return response.json();
                }
                throw new Error('Network response was not ok.');
            })
            .then( function(json) {
                if(json.data !== ''){
                    document.querySelector('#yasakani-cache-status').innerHTML = json.data;
                }
            })                    
            .catch( function(error){})        
        }
    };
    window.addEventListener('DOMContentLoaded', function(){
        //gutenberg status-visibility の状態を監視して表示を切り替える
        if(wp.data != undefined){
            const { subscribe } = wp.data;
            let iPostVisibility = wp.data.select( 'core/editor' ).getEditedPostVisibility();
            const unssubscribe = subscribe( () => { //MutationObserver 同等
                let cPostVisibility = wp.data.select( 'core/editor' ).getEditedPostVisibility();
                if ( iPostVisibility !== cPostVisibility) {
                    if(cPostVisibility == 'private' || cPostVisibility == 'password'){
                        document.getElementById('yc-select-notice').style.display = 'inline';
                        document.getElementById('yc-select-ui').style.display = 'none';
                    } else {
                        document.getElementById('yc-select-notice').style.display = 'none';
                        document.getElementById('yc-select-ui').style.display = 'inline';
                    }
                    iPostVisibility = cPostVisibility;
                }                
            } );
        }
    });
    //# sourceURL=http://localhost/wordpress/yasakani.js
   </script>
   <?php }        
}  