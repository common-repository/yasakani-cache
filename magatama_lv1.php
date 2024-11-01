<?php
/*
  Module Name: yasakani cache magatama_lv1 module (auto_prepend_file or advanced-cache)
  Description: Simple, Easy, Ultra-high-speed Page Cache Powered by SQLite
  Version: 3.8.2
  Author: enomoto@celtislab
  License: GPLv2
*/

if(!defined( 'ABSPATH' ) && !defined( 'YASAKANI_CACHE_DB' ))
    exit;

if(!class_exists('\celtislab\v1_2\Celtis_sqlite')){
    require_once( __DIR__ . '/inc/sqlite-utils.php');
}
    
class YC_MUSE {

    const FORMAT_VER  = 13;  //DB Data format

    static $setting;
    static $invalid_sqlite;
    
    public $sqldb;

    public function __construct( $table = 'check') {
        $this->sqldb = null;
        self::$invalid_sqlite = 0;
        self::$setting = array();

        $dbfile = false;
        if(defined('YASAKANI_CACHE_DB')) {
            $dbfile = YASAKANI_CACHE_DB;
        } elseif(defined('WP_CONTENT_DIR')){
            $dbfile = WP_CONTENT_DIR . '/yasakani-cache/yasakani_cache.db';
        }
        if($dbfile === false){
            self::$invalid_sqlite = 1;
        } else {
            $this->sqldb = new celtislab\v1_2\Celtis_sqlite( $dbfile );
            if(!empty($this->sqldb) && $this->sqldb->is_open()){
                //https://www.sqlite.org/walformat.html
                //WAL mode writes updated data to -wal file and reflects it when committing.
                //It is reflected in base DB file when the number of commits exceeds the set value or when all connections to the DB are closed.
                //PRAGMA mmap_size If memory allocation is inappropriate, I/O performance may deteriorate or operation may become unstable, and it is not suitable for use with log data, etc., so it is not specified here.
                //For PRAGMA commands that perform settings, multiple commands can be specified in one string by separating them with semicolons.
                $this->sqldb->command('PRAGMA synchronous = NORMAL; PRAGMA journal_mode = WAL; PRAGMA journal_size_limit = 16777216; PRAGMA busy_timeout = 2000; PRAGMA temp_store = memory');
                
                if($table === 'check'){
                    self::$setting = $this->get_db_setting();
                    if(empty(self::$setting)){
                        self::$invalid_sqlite = 2;
                    } elseif(!empty(self::$setting['format']) && (int)self::$setting['format'] < self::FORMAT_VER){
                        require_once( __DIR__ . '/yasakani_dbtable.php');
                        YC_TABLE::table_update(self::$setting, $this->sqldb);
                    }
                } elseif($table === 'CREATE'){
                    require_once( __DIR__ . '/yasakani_dbtable.php');
                    YC_TABLE::table_create($this->sqldb);
                    if(! $this->sqldb->is_table_exist('setting')){
                        self::$invalid_sqlite = 2;
                    }
                }
            } else {
                self::$invalid_sqlite = 1;                
            }
        }
    }

    /**
     * Setting mode Get 
     * 
     * @return setting 
     */
    public function get_db_setting() {
        $setting = array();
        $obj = $this->sqldb->sql_get_row( "SELECT * FROM setting WHERE ROWID = 1");        
        if(is_object($obj)){
            $setting['format']      = (int)$obj->format;
            $setting['log_mode']    = (int)$obj->log_mode;
            $setting['maintenance'] = (!empty($obj->maintenance))? $obj->maintenance : 0;
            $setting['timezone']    = (!empty($obj->timezone))? $obj->timezone : 'utc';
            $setting['botblocklist']= (!empty($obj->botblocklist))? $obj->botblocklist : '';
            $setting['botwhitelist']= (!empty($obj->botwhitelist))? $obj->botwhitelist : '';
            $setting['log_backup']  = (!empty($obj->log_backup))? $obj->log_backup : '0000-00-00';
            $setting['bot_key']     = (!empty($obj->bot_key))? $obj->bot_key : '';
            $setting['loginblock']  = (!empty($obj->loginblock))? (int)$obj->loginblock : 0;
            $setting['block_user']  = (!empty($obj->block_user))? $obj->block_user : '';
            $setting['autoblock']   = (!empty($obj->autoblock))? (int)$obj->autoblock : 0;
            $setting['autoblocklist']= (!empty($obj->autoblocklist))? $obj->autoblocklist : '';
            $setting['sitekey']     = (!empty($obj->sitekey))? $obj->sitekey : '';
            $setting['zerodayblock']= (!empty($obj->zerodayblock))? (int)$obj->zerodayblock : 0;
            $setting['trustedfile'] = (!empty($obj->trustedfile))? $obj->trustedfile : '';
            $setting['siteurlprotect']= (!empty($obj->siteurlprotect))? (int)$obj->siteurlprotect : 0;
            $setting['protectlist'] = (!empty($obj->protectlist))? $obj->protectlist : '';
            $setting['notice']      = (!empty($obj->notice))? $obj->notice : '';
        }
        return $setting;
    }

    static function get_setting() {
        return self::$setting;
    }

    static function get_db_stat() {
        return self::$invalid_sqlite;
    }
    
    public function is_enable( $check_type) {
        if(self::$invalid_sqlite !== 0 || empty(self::$setting)){
            return false;
        }
            
        switch($check_type){
        case 'sqlite':
            return true;

        case 'log':
            if(self::$setting['log_mode'] != 0)
                return true;
            else
                return false;

        case 'security':
            if(self::$setting['log_mode'] == 2)
                return true;
            else
                return false;
            
        case 'autoblock':
            if(self::$setting['autoblock'] != 0)
                return true;
            else
                return false;
            
        case 'zerodayblock':
            //for expert
            if(self::$setting['zerodayblock'] != 0 ){
                $apfile = ini_get('auto_prepend_file');
                if(!empty($apfile) && strpos( $apfile, 'yasakani-cache-exload.php') !== false)
                    return true;
            }
            return false;
            
        case 'siteurlprotect':
            if(self::$setting['siteurlprotect'] != 0 )
                return true;
            else
                return false;
            
        case 'loginblock':
            if(self::$setting['loginblock'] != 0)
                return true;
            else
                return false;
            
        case 'maintenance':
            if(!empty(self::$setting['maintenance'])){
                $now = new DateTime("now", new DateTimeZone('utc'));
                $exp = new DateTime(self::$setting['maintenance'], new DateTimeZone('utc'));
                if($now <= $exp)
                    return true;
            }
            return false;

        default:
            return false;
        }
    }
   
    public function get_sitekey() {
        $keys = array();
        if(!empty(self::$setting['sitekey'])){
            $keys = array_map("trim", explode(',', self::$setting['sitekey']));
        }
        return $keys;
    }
    
    //Equal treatment for when the wp_is_mobile is not yet available（wp-include/vars.php wp_is_mobile)
    public static function is_mobile( $ua = '' ) {
        $is_mobile = false;
        if(empty($ua)){
            $ua = (!empty( $_SERVER['HTTP_USER_AGENT'] ))? $_SERVER['HTTP_USER_AGENT'] : '';
            if ( isset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) ) {
                // This is the `Sec-CH-UA-Mobile` user agent client hint HTTP request header.
                // See <https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Sec-CH-UA-Mobile>.
                $is_mobile = ( '?1' === $_SERVER['HTTP_SEC_CH_UA_MOBILE'] );
            }
        }
        if ( $is_mobile === false && !empty( $ua ) ) {
            if ( str_contains( $ua, 'Mobile' ) // Many mobile devices (all iPhone, iPad, etc.)
                || str_contains( $ua, 'Android' )
                || str_contains( $ua, 'Silk/' )
                || str_contains( $ua, 'Kindle' )
                || str_contains( $ua, 'BlackBerry' )
                || str_contains( $ua, 'Opera Mini' )
                || str_contains( $ua, 'Opera Mobi' ) ) {
                    $is_mobile = true;
            }
        }          
        return $is_mobile;
    }
        
    /**
     * Content Cache Data Get
     * 
     * @param string $req_url : Request URL
     * @return array(bool,object) 
     */
    public function get_content($req_url, $is_mobile) {
        $reqs[] = $req_url;
        $trailingslash = substr($req_url, -1);
        if($trailingslash !== '/' && false === strpbrk($req_url, "?.")){
            $reqs[] = $req_url . '/';
        }
        $cache = array();
        foreach($reqs as $req){
            $key = md5( urldecode($req) );
            if($is_mobile){
                $cache = $this->sqldb->sql_get_row( "SELECT req_url, blogid, postid, title, mobile AS body, m_header AS header, m_modified AS modified, m_expire AS expire FROM content WHERE key = ?", array( $key ), SQLITE3_ASSOC);            
            } else {
                $cache = $this->sqldb->sql_get_row( "SELECT req_url, blogid, postid, title, desktop AS body, d_header AS header, d_modified AS modified, d_expire AS expire FROM content WHERE key = ?", array( $key ), SQLITE3_ASSOC);                        
            }
            if(!empty($cache['body'])){
                break;                                 
            }
        }
        return $cache;
    }

    public function get_device_content() {
        $cache = $this->get_content($_SERVER['REQUEST_URI'], self::is_mobile());
        if( !empty($cache['body']) && !empty($cache['expire'])){
            $now =  new DateTime("now", new DateTimeZone('utc'));
            $exp =  new DateTime($cache['expire'], new DateTimeZone('utc'));
            if($now <= $exp){
                if (!(isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false))) {
                    // If gzip is not supported, decode the compressed data and also modify the header.                    
                    if ( strpos($cache['header'], 'gzip') !== false) {
                        $cache['body']   = gzdecode( $cache['body'] );
                        $cache['header'] = str_replace(',"Content-Encoding":"gzip"', '', $cache['header']);
                    }
                }
            } else {
                $cache = false;
            }
        } else {
            $cache = false;            
        }
        return $cache;
    }   
        
    /**
     * Log data save
     * 
     * type(Hit/Store/exclude_post/login_user/comment_author/post_password/not_single/not_publish/etc ....... )
     * login 0=page access, 1=login/signup, 2=comment/trackback, 3=xmlrpc, 4=wp-mail, 5=wp-admin, 6=admin-ajax, 7=wp-json, 8=etc, 9=404
     *       10=wp-cron, 11=feed, 12=robot.txt, 100= login user access
     * 
     */
    public function put_log($type, $req_url, $title, $blogid, $postid, $e = null, $bot = 0 ) {
        if($this->is_enable('log')){
            global $yasakani_cache_action;
            if($this->is_enable('maintenance') || !empty($yasakani_cache_action['db_error'])){
                return;
            }
            if(function_exists( 'did_action' )){
                if( did_action( 'wp_ajax_heartbeat' ) !== 0 || did_action( 'wp_ajax_nopriv_heartbeat') !== 0)
                    return;
            }
            if (!class_exists('YC_LOGPUT', false)){
                include_once( __DIR__ . '/addons/yasakani_logstat.php');
            }
            $req_url = urldecode($req_url);
            YC_LOGPUT::log_save($type, $req_url, $title, $blogid, $postid, $e, $bot);
        }
    }
}

function yasakani_cache_shutdown() {
    global $yasakani_cache, $yasakani_cache_action;
    //Save logs when you are not using theme templates such as admin page or login
    if(empty($yasakani_cache_action['output_cache']) && empty($yasakani_cache_action['put_log'])){
        if ( $yasakani_cache->is_enable('log')) {
            $blogid = 0;
            $postid = 0;
            $title  = '-';
            $type   = 'etc';
            if(!empty($GLOBALS['wp_the_query']) && function_exists('is_multisite') && function_exists('is_admin') && function_exists('is_singular')){
                $blogid = 1;
                if(is_multisite()){
                    $blogid = get_current_blog_id();
                }
                //Cases where the process ends with the template_redirect,
                global $post;
                if(!empty($post)){
                    $postid = $post->ID;
                    if ( !empty( $post->post_title ) ) {
                        $title = $post->post_title;
                    }
                }
                if(!empty($yasakani_cache_action['cache_disable']) && (is_singular() || is_embed() || is_home() || is_front_page() ))
                    $type = 'Cache_disable';
            }
            $e = error_get_last();
            $yasakani_cache->put_log($type, $_SERVER['REQUEST_URI'], $title, $blogid, $postid, $e );            
        }
    }
}

//------------------------------------------------------------------------------
// YASAKANI Cache Main
//------------------------------------------------------------------------------
if ( !defined('YASAKANI_SETTING')){
    if (empty($_SERVER['REQUEST_URI'])){ 
        exit;
    }
    
    //Realtime Image Optimizer Request 
    //https://celtislab.net/en/wp-realtime-image-optimizer/
    if (strpos($_SERVER['REQUEST_URI'], '=imgopt' ) !== false){
        return;        
    }
    //Ajax heartbeat Request (Do not open yasakani_cache.db)
    if (strpos($_SERVER['REQUEST_URI'], 'admin-ajax.php' ) !== false && isset($_REQUEST['action']) && $_REQUEST['action'] === 'heartbeat'){
        return;        
    }

    $yasakani_timestart = microtime( true );
    $yasakani_request_timestart = 0;
    $yasakani_cache_action = array();
        
    $yasakani_cache = new YC_MUSE();
    if (! $yasakani_cache->is_enable('sqlite') ){
        return;
    }

    //Login COOKIE (including site name)
    $is_login = false;
    $site  = false;
    if ( ! empty( $_COOKIE ) ) {
        $sitekeys = $yasakani_cache->get_sitekey();
        if(!empty($sitekeys)){
            foreach($sitekeys as $key) {
                $logincookie = 'wordpress_logged_in_' . md5($key);
                if(!empty($_COOKIE[$logincookie])){                    
                    //template_redirect After determining the login status of the hook position, perform a second check with blocking bot access 
                    $is_login = true;
                    $site  = $key;
                    break;
                }
            }
        }
    }

    //Brute Force, countering unauthorized access such as zero-day attacks, registered bots, etc.
    if ($yasakani_cache->is_enable('security')) {
        include_once( __DIR__ . '/addons/yasakani_security.php');
        YC_SECURITY::safecheck($is_login, $site);
    }

    //Page Cache (Login, backend, non-GET, maintenance excluded)
    if($is_login !== false || strpos($_SERVER['REQUEST_URI'], 'wp-admin' ) !== false || empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET' || $yasakani_cache->is_enable('maintenance')){
        $cache = false;
    } else {
        $cache = $yasakani_cache->get_device_content();
    }   
    if(empty($cache)){
        register_shutdown_function( 'yasakani_cache_shutdown' );        
    } else {
        if(!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $cache['modified'] ) {
            $stat = 'not_modified';
            header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
        } else {
            $stat = 'Hit';
            $headers = (!empty($cache['header']))? json_decode( $cache['header'], true ) : array();
            if(!empty($headers)){
                foreach( $headers as $key => $value ) {
                    header( "$key: $value" );
                }
            } else {
                header( 'Cache-Control: no-cache' );
                header( 'Last-Modified: ' . $cache['modified'] );
            }
            echo $cache['body'];
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            // Force end of output buffering and send output to the client
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        $yasakani_cache->put_log($stat, $cache['req_url'], $cache['title'], $cache['blogid'], $cache['postid'] );
        exit;
    }
}