<?php
/*
  File Name: yasakani_logstat.php
  Description: Simple, Easy, Ultra-high-speed Page Cache Powered by SQLite
  Author: enomoto@celtislab
  License: GPLv2
*/
if(!defined( 'ABSPATH' ) && !defined( 'YASAKANI_CACHE_DB' ))
    exit;

//yasakani-cache-exload.php / advanced-cache.php から呼び出すアクセスログ処理
class YC_LOGPUT {
    static $setting;

    public function __construct() {}

    /**
     * Log data save
     * 
     * type(Hit/Store/exclude_post/login_user/comment_author/post_password/not_single/not_publish/etc ....... )
     * login 0=page access, 1=login/signup, 2=comment/trackback, 3=xmlrpc, 4=wp-mail, 5=wp-admin, 6=admin-ajax, 7=wp-json, 8=etc, 9=404
     *       10=wp-cron, 11=feed, 12=robot.txt, 100= login user access
     * 
     * ※この関数は get_option が使用可能になる前に呼び出される場合もあるのでカスタマイズ時は注意すること
     */
    static function log_save($type, $req_url, $title, $blogid, $postid, $e = null, $bot = 0 ) {
        
        global $yasakani_cache, $yasakani_cache_action;
        self::$setting = YC_MUSE::get_setting();
        $ntype = $type;
        //特殊なアクセスを区別
        $login = self::access_type_check($req_url, $postid, $ntype);
        $type = $ntype;

        $t = array();
        $t['type']      = $type;
        $t['req_url']   = $req_url;
        $t['req_data']  = '';
        $method = (!empty($_SERVER['REQUEST_METHOD']))? $_SERVER['REQUEST_METHOD'] : '';
        $t['method']    = $method;
        if( $type == 'wp-cron'){
            if(!empty($yasakani_cache_action['cron_hook'])){
                //cron リクエスト時に実行された hook, callback function（複数の可能性あり）
                $t['req_data'] = json_encode($yasakani_cache_action['cron_hook']);
            }
        } else if( $type == 'wp-json'){
            //REST API Request & Result
            if(!empty($yasakani_cache_action['rest'])){
                $t['req_data'] = json_encode($yasakani_cache_action['rest']);
            }            
        } else if(($method != 'POST' || empty($_POST))){
            if($type == 'wp_redirect'){
                $t['req_data'] = json_encode( array('redirect'=>$yasakani_cache_action['wp_redirect'], 'post'=>array()) );            
            } else {
                $t['req_data']  = '';
            }
            
        } else {
            //ログイン情報 'log', 'user_login', 'user_email' は保存 'pwd', 'pass1', 'pass2', 'post_password' は * に置き換える
            foreach(array('pwd', 'pass1', 'pass2', 'post_password') as $item ){
                if(!empty($_POST[$item])){
                    $_POST[$item] = '*';
                }
            }                       
            //$_FILES がセットされていたらログへ表示したいので req_data に追記
            if(!empty($_FILES)){
                $_POST['_FILES'] = $_FILES;
            }

            if($type == 'wp_redirect'){
                $t['req_data'] = json_encode( array('redirect'=>$yasakani_cache_action['wp_redirect'], 'post'=>$_POST ) );
            } else {
                $t['req_data'] = json_encode($_POST);                
            }
        }
        $t['title']     = $title;
        $t['blogid']    = $blogid;
        $t['postid']    = $postid;
        $t['login']     = $login;

        $psurl = parse_url($req_url);
        if(!empty($psurl['host'])){
            $t['host'] = $psurl['host'];
        } else {
            $t['host'] = (!empty($_SERVER['HTTP_HOST']))? $_SERVER['HTTP_HOST'] : '';
        }
        $myhost = str_replace( 'www.', '', $t['host']);
        
        $t['path']  = (!empty($psurl['path']))? $psurl['path'] : '';
        $t['query'] = (!empty($psurl['query']))? $psurl['query'] : '';
        $ip = $t['ip'] = (!empty($_SERVER["REMOTE_ADDR"]))? $_SERVER["REMOTE_ADDR"] : '';
        $user_agent = $t['user_agent']= (!empty($_SERVER['HTTP_USER_AGENT']))? $_SERVER['HTTP_USER_AGENT'] : '';
        $is_mobile = YC_MUSE::is_mobile();

        $referer = $t['referer']  = '';
        $t['refhost']  = '';
        $t['refpath']  = '';
        $t['refquery'] = '';
        if(!empty($_SERVER['HTTP_REFERER'])){
            if (!( strpos($_SERVER['HTTP_REFERER'], $myhost) !== false && (strpos($_SERVER['HTTP_REFERER'], '/wp-admin' ) !== false || strpos($_SERVER['HTTP_REFERER'], '/wp-cron' ) !== false))){
                $referer = $t['referer'] = urldecode($_SERVER['HTTP_REFERER']);
                $psurl = parse_url($t['referer']);
                $t['refhost']  = (!empty($psurl['host']))? $psurl['host'] : '';
                $t['refpath']  = (!empty($psurl['path']))? $psurl['path'] : '';
                $t['refquery'] = (!empty($psurl['query']))? $psurl['query'] : '';
            }
        }
        $err = '';
        if(!empty($e['type'])){
            $err .= "Type: ";
            switch ($e['type']){
                case 1:     $err .= 'E_ERROR ';           break;
                case 2:     $err .= 'E_WARNING ';         break;
                case 4:     $err .= 'E_PARSE ';           break;
                case 8:     $err .= 'E_NOTICE ';          break;
                case 16:    $err .= 'E_CORE_ERROR ';      break;
                case 32:    $err .= 'E_CORE_WARNING ';    break;
                case 64:    $err .= 'E_COMPILE_ERROR ';   break;
                case 128:   $err .= 'E_COMPILE_WARNING '; break;
                case 256:   $err .= 'E_USER_ERROR ';      break;
                case 512:   $err .= 'E_USER_WARNING ';    break;
                case 1024:  $err .= 'E_USER_NOTICE ';     break;
                case 2048:  $err .= 'E_STRICT ';          break;
                case 4096:  $err .= 'E_RECOVERABLE_ERROR '; break;
                case 8192:  $err .= 'E_DEPRECATED ';      break;
                case 16384: $err .= 'E_USER_DEPRECATED '; break;
                default:    $err .= "{$e['type']} ";      break;
            }
        }
        if(!empty($e['message']))
            $err .= "Message: {$e['message']} ";
        if(!empty($e['file']))
            $err .= "File: {$e['file']} ";
        if(!empty($e['line']))
            $err .= "Line: {$e['line']}";
        $t['error'] = $err;
                  
        if(empty($bot)){
            $bot = self::is_bot();
        }
        $t['bot'] = (empty($bot))? 0 : 1;
        
        $now = new DateTime("now", new DateTimeZone('utc'));
        global $yasakani_timestart;
        $t['response'] = number_format( microtime( true ) - $yasakani_timestart, 3 );
                
        $localtm = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
        $t['date'] = $localtm->format('Y-m-d H:i:s');
        $backupday = $localtm->format("Y-m-d");

        //日替わり検出
        $sqldb = $yasakani_cache->sqldb;
        $setobj = $sqldb->sql_get_row( "SELECT * FROM setting WHERE ROWID = 1");
        if(is_object($setobj)){

            if($sqldb->beginTransaction('IMMEDIATE')){
                try {
                    //同時アクセス時に繰り返し実行しないよう対策
                    if($setobj->log_backup != $backupday){
                        require_once( YASAKANI_CACHE_DIR . '/yasakani_dbtable.php');

                        //日替わりごとに整合性チェックを実施する
                        $result = $sqldb->get_command("PRAGMA integrity_check('content')");
                        if(!empty($result) && $result != 'ok'){
                            //管理画面表示時に notice がセットされていたら表示を行い注意喚起する
                            $setobj->notice = 'A problem occurred while checking the consistency of cache data. Recommend performing a hard reset.';
                        } else {
                            if(!empty($setobj->notice)){
                                $setobj->notice = '';
                            }
                        }

                        $lastday = new DateTime($setobj->log_backup);
                        $week = array("SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT");
                        $w = (int)$lastday->format('w');

                        //統計情報をバックアップ
                        $statobj = $sqldb->sql_get_row( "SELECT data FROM stat WHERE ROWID = 1");
                        if(is_object($statobj) && !empty($statobj->data)){
                            $bkupstattbl = 'stat_' . $week[$w];
                            $sqldb->sql_exec( "DROP TABLE IF EXISTS $bkupstattbl;" );
                            $sqldb->sql_exec( "CREATE TABLE $bkupstattbl AS SELECT * FROM stat" );
                            //前日の人気記事等の表示用に set_transient へ保存するため
                            $sqldb->sql_exec( "DROP TABLE IF EXISTS laststat;" );
                            $sqldb->sql_exec( "CREATE TABLE laststat AS SELECT * FROM stat" );
                        }
                        //ログをバックアップ
                        $bkuplogtbl = 'log_' . $week[$w];
                        $sqldb->sql_exec( "DROP TABLE IF EXISTS $bkuplogtbl;" );
                        $sqldb->sql_exec( "CREATE TABLE $bkuplogtbl AS SELECT * FROM log" );
                        
                        //テーブルをクリアして新たにログを記録する
                        $setobj->autoblocklist = '';   //auto block IP clear
                        $sqldb->sql_exec("UPDATE setting SET log_backup = ?, autoblocklist = ?, notice = ?", array($backupday, $setobj->autoblocklist, $setobj->notice));

                        $sqldb->sql_exec( "DROP TABLE IF EXISTS log" );
                        $sqldb->sql_exec( YC_TABLE::CREATE_LOG_TABLE );
                        $sqldb->sql_exec( "CREATE INDEX date ON log (date);" );
                        $sqldb->sql_exec( "CREATE INDEX lpostid ON log (blogid,postid);" );
                        $sqldb->sql_exec( "CREATE INDEX ip ON log (ip);" );   

                        $sqldb->sql_exec( "DROP TABLE IF EXISTS bot" );
                        $sqldb->sql_exec( YC_TABLE::CREATE_BOT_TABLE );
                        $sqldb->sql_exec( "CREATE INDEX bip ON bot (bip);" );

                        $sqldb->sql_exec( "DROP TABLE IF EXISTS stat" );
                        $sqldb->sql_exec( YC_TABLE::CREATE_STAT_TABLE );

                        $sqldb->sql_exec( "DROP TABLE IF EXISTS logged_key" );
                        $sqldb->sql_exec( YC_TABLE::CREATE_LOGGED_KEY_TABLE );
                    } else {
                        if(function_exists( 'set_transient' ) && $sqldb->is_table_exist('laststat')){
                            $statobj = $sqldb->sql_get_row( "SELECT data FROM laststat WHERE ROWID = 1");
                            if(is_object($statobj) && !empty($statobj->data)){
                                set_transient( 'yasakani_statistics', $statobj->data, DAY_IN_SECONDS * 2 );
                                $sqldb->sql_exec( "DROP TABLE IF EXISTS laststat;" );
                            }
                        }
                    }
                    $sqldb->commit();

                } catch (Exception $e) {
                    $sqldb->rollback();
                    $errmsg = $e->getMessage();
                    $yasakani_cache_action['db_error'] = $errmsg;
                    return;
                }                
            }

            //ログ＆統計情報の更新
            if($sqldb->beginTransaction('IMMEDIATE')){
                try {
                    //IP統計情報(Botブロック用) - bot table & setting
                    //※ここでは apply_filters() が使用できないので直接 bot_db_update() を呼び出す 
                    if($yasakani_cache->is_enable('security') && !empty($ip)){
                        include_once( YASAKANI_CACHE_DIR . '/addons/yasakani_security.php');
                        if (class_exists('YC_SECURITY', false)){
                            $t = YC_SECURITY::bot_db_update( $t, $req_url, $ip, $user_agent );
                        }
                    }

                    //人気記事等の統計情報生成 - stat table
                    $statobj = $sqldb->sql_get_row( "SELECT data FROM stat WHERE ROWID = 1");
                    $stat_data = array();
                    if(is_object($statobj) && !empty($statobj->data)){
                        $a_array = json_decode( $statobj->data, true );
                        if(is_array($a_array))
                            $stat_data = $a_array;

                        if(!empty($stat_data)){
                            self::set_statistics($stat_data, $localtm, $type, $req_url, $blogid, $postid, $myhost, $referer, $is_mobile, $bot);
                            $json_data = json_encode( $stat_data );
                            if($json_data !== false)
                                $sqldb->sql_exec("UPDATE stat SET data = ? WHERE ROWID = 1", array( $json_data ));
                        }
                    } else {
                        self::set_statistics($stat_data, $localtm, $type, $req_url, $blogid, $postid, $myhost, $referer, $is_mobile, $bot);
                        $json_data = json_encode( $stat_data );
                        if($json_data !== false)
                            $sqldb->sql_exec("INSERT INTO stat (data) VALUES ( ? )", array( $json_data ));
                    }            
                    //アクセスログ - log table
                    $res = $sqldb->sql_exec("INSERT INTO log ( date, response, type, req_url, method, req_data, blogid, postid, host, path, query, title, user_agent, referer, refhost, refpath, refquery, ip, error, login, bot) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                            array($t['date'], $t['response'], $t['type'], $t['req_url'], $t['method'], $t['req_data'], $t['blogid'], $t['postid'], $t['host'], $t['path'], $t['query'], $t['title'], $t['user_agent'], $t['referer'], $t['refhost'], $t['refpath'], $t['refquery'], $t['ip'], $t['error'], $t['login'], $t['bot']));

                    $sqldb->commit();

                } catch (Exception $e) {
                    $sqldb->rollback();
                    $errmsg = $e->getMessage();
                    $yasakani_cache_action['db_error'] = $errmsg;
                    return;
                }                
            }
        }
        $yasakani_cache_action['put_log'] = true;
    }

    //非ログインユーザーの wp-admin へのアクセス等の php ダイレクトアクセスはサイト攻撃の可能性大            
    static function access_type_check( $req_url, $postid, &$type ) {
        global $yasakani_cache_action;
        $login = 0;
        if($type === 'etc' && $postid === 0){
            //特殊なアクセスを区別
            if ( strpos($req_url, '/wp-login' ) !== false || strpos($req_url, '/wp-signup' ) !== false || strpos($req_url, '/wp-activate' ) !== false){
                $login = 1;
                $type = 'wp-login';     //user account & password 認証により許可
            } else if ( strpos($req_url, '/wp-comments' ) !== false || strpos($req_url, '/wp-trackback' ) !== false){
                $login = 2;
                $type = 'wp-comments';  //通常は匿名で利用される
            } else if ( strpos($req_url, '/xmlrpc' ) !== false){
                $login = 3;
                $type = 'xmlrpc';       //user account & password 認証により許可
            } else if ( strpos($req_url, '/wp-mail' ) !== false){
                $login = 4;
                $type = 'wp-mail';      //メールによる投稿: 認証により許可
            } else if ( strpos($req_url, '/wp-admin' ) !== false){
                $login = 5;
                $type = 'wp-admin';     //認証後にアクセス可
                if ( strpos($req_url, '/admin-ajax' ) !== false){
                    //非ログインユーザーからの wp_ajax_nopriv_xxxx フックが使用あり
                    $login = 6;
                    $type = 'admin-ajax';
                }

            } else if ( strpos($req_url, '/wp-json' ) !== false || strpos($req_url, 'rest_route=' ) !== false){
                $login = 7;
                $type = 'wp-json';      //read:だれでも write:認証により許可

            } else if ( strpos($req_url, '/wp-cron' ) !== false){
                $login = 10;
                $type = 'wp-cron';
            } else if ( strpos($req_url, '/feed' ) !== false){
                $login = 11;
                $type = 'feed';
            } else if ( strpos($req_url, '/robots' ) !== false){
                $login = 12;
                $type = 'robots';

            } else if ( strpos($req_url, 'sitemap' ) !== false){
                $login = 13;
                $type = 'sitemap';

            } else {    //etc
                if(!empty($yasakani_cache_action['wp_redirect'])){
                    $type = 'wp_redirect';
                }
                $login = 8;
            }
        } else if($type === '404'){
            $login = 9;
        }        
        
        if(function_exists('is_user_logged_in') && is_user_logged_in()){
            $login = 100;   //login user access
        } else if( $login === 9){
            //非ログインユーザーの php へのアクセスは悪質なボットの可能性大            
            if (!empty($_POST) ) {
                $post_data = $_POST;
                if(is_array($_POST))
                    $post_data = json_encode($post_data);
            }
            $post_data = (!empty($post_data))? $post_data : '';
            if ( strpos($req_url . $post_data, '.php' ) !== false ){
                $type = 'InvalidRequest';
            }
        }
        return $login;
    }

    //ボット判定（不完全だが User Agent から 主なボットの判定は可能）
    static function is_bot() {
        global $yasakani_cache;
        $bot = false;
        $botlist = (!empty(self::$setting['bot_key']))? array_filter( array_map("trim", explode(',', self::$setting['bot_key'])), 'strlen') : '';
        if(empty($_SERVER['HTTP_USER_AGENT']))
            $bot = true;
        else if(!empty($botlist)) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            foreach ($botlist as $key) {
                if ( stripos($user_agent, $key ) !== false){
                    $bot = true;
                    break;
                }
            }
        }
        return $bot;
    }
    
    //統計情報生成
    static function set_statistics(&$stat_data, $localtm, $type, $req_url, $blogid, $postid, $myhost, $referer, $is_mobile, $bot) {
        if(empty($stat_data['date'])){
            $stat_data['date'] = $localtm->format("Y-m-d");
        }
        $tm_h = $localtm->format('G');
        //Cache ヒット率（ブロックされていないボットを含む）
        if(!empty($postid)){
            if($type == 'Hit' || $type == 'not_modified'){
                if(empty($stat_data['Cache']['Hit']))
                    $stat_data['Cache']['Hit'] = 1;
                else
                    $stat_data['Cache']['Hit'] += 1;
            } else {
                //Store, login_user, comment_user, exclude_page, not_publish, post_password, Cache_disable, php_error
                if(empty($stat_data['Cache']['Non']))
                    $stat_data['Cache']['Non'] = 1;
                else
                    $stat_data['Cache']['Non'] += 1;
            }
        }
        if(empty($bot)){
            //PV (login_user は含むが embed はWPブログカードの iframe からのリクエストなので除く)
            if(!empty($postid) && !preg_match("#(/|&|\?)embed#u", $req_url)){
                //全体のPVのみモバイル/デスクトップ別に集計
                $dev = ($is_mobile)? 'mobile' : 'desktop';
                if(empty($stat_data['PV'][$dev]))
                    $stat_data['PV'][$dev] = 1;
                else
                    $stat_data['PV'][$dev] += 1;
                //時間毎のPV
                if(empty($stat_data['PV/h'][$tm_h]))
                    $stat_data['PV/h'][$tm_h] = 1;
                else
                    $stat_data['PV/h'][$tm_h] += 1;
                //記事毎のPV
                if(empty($stat_data['post']["$blogid,$postid"]))
                    $stat_data['post']["$blogid,$postid"] = 1;
                else
                    $stat_data['post']["$blogid,$postid"] += 1;
            }
            //リファラー (embed 含む)
            if(!empty($referer)){
                $pos = strpos($referer, $myhost);
                //自サイト内のリファラを除くため先頭から40字以内に自サイト名がある場合は除く
                if ($pos === false || $pos >= 40){
                    if(empty($stat_data['REF'][$referer]))
                        $stat_data['REF'][$referer] = 1;
                    else
                        $stat_data['REF'][$referer] += 1;
                }
            }
        } else {
            //BOT アクセスを計測（BOT識別が正確ではないので目安として）
            if(empty($stat_data['bot']))
                $stat_data['bot'] = 1;
            else
                $stat_data['bot'] += 1;
            if($type == 'bot_block' || $type == 'auto_block'){
                if(empty($stat_data['bot_block']))
                    $stat_data['bot_block'] = 1;
                else
                    $stat_data['bot_block'] += 1;
            }
            //時間毎のBOT
            if(empty($stat_data['bot/h'][$tm_h]))
                $stat_data['bot/h'][$tm_h] = 1;
            else
                $stat_data['bot/h'][$tm_h] += 1;
        }
    }
}
