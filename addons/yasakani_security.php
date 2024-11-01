<?php
/*
  File Name: yasakani_security.php
  Description: Simple, Easy, Ultra-high-speed Page Cache Powered by SQLite
  Author: enomoto@celtislab
  License: GPLv2
*/
if(!defined( 'ABSPATH' ) && !defined( 'YASAKANI_CACHE_DB' ))
    exit;

class YC_SECURITY {
    static $setting;

    public function __construct() {
    }

    //yasakani-cache-exload.php / advanced-cache.php から呼び出す不正リクエスト検出処理
    static function safecheck($is_login, $site) {
        global $yasakani_cache;
        self::$setting = YC_MUSE::get_setting();
        //auto block(1)  サイト攻撃の疑いのあるアクセスをチェック 
        //Brute Force ログイン認証エラーは authenticate / wp_login_failed にフックして判定し5回でそのIPを自動ブロック
        if ($yasakani_cache->is_enable('autoblock') || $yasakani_cache->is_enable('loginblock') ) {
            //既にブロックされているか？
            if(self::is_autoblocked( self::$setting['autoblocklist'] )){
                header( $_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403 );
                $yasakani_cache->put_log('auto_block', $_SERVER['REQUEST_URI'], '-', 1, 0, null, 1 );
                exit;
            }
            //不正リクエストチェック
            if(self::is_invalid_request( $_SERVER['REQUEST_URI'] )){
                header( $_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403 );
                $yasakani_cache->put_log('InvalidRequest', $_SERVER['REQUEST_URI'], '-', 1, 0, null, 1 );
                exit;
            }
        }
        //ゼロデイ攻撃のアクセスをチェック(ログイン有無により対応を区別)
        if ($yasakani_cache->is_enable('zerodayblock')) {
            if(self::is_zeroday_attack( $_SERVER['REQUEST_URI'], $is_login, $site )){
                header( $_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403 );
                $yasakani_cache->put_log('InvalidRequest', $_SERVER['REQUEST_URI'], '-', 1, 0, null, 1 );
                exit;
            }
        }
        //非ログインユーザーの _POST 内データをチェック
        if ($is_login === false && !empty($_POST) && $yasakani_cache->is_enable('autoblock') ) {
            if(self::is_invalid_postreq( $_POST )){
                header( $_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403 );
                $yasakani_cache->put_log('InvalidRequest', $_SERVER['REQUEST_URI'], '-', 1, 0, null, 1 );
                exit;
            }                    
        }
        //ブロック指定されているボットのアクセスかチェック
        if(self::is_bot_blocking()){
            header( $_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403 );
            $yasakani_cache->put_log('bot_block', $_SERVER['REQUEST_URI'], '-', 1, 0, null, 1 );
            exit;
        }        
    }
    
    //ブロック指定されているボットアクセス禁止
    static function is_bot_blocking( $ip='', $user_agent='') {
        // robots.txt が主体なので、robots.txt のリクエストはブロックしない 
        global $yasakani_cache;
        $block = false;
        if(!empty(self::$setting['botblocklist']) && strpos($_SERVER['REQUEST_URI'], "robots.txt" ) === false){
            
            if(empty($ip) && !empty($_SERVER['REMOTE_ADDR']))
                $ip = $_SERVER['REMOTE_ADDR'];
            if(empty($user_agent) && !empty($_SERVER['HTTP_USER_AGENT']))
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $lists = array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", self::$setting['botblocklist'])));
            
            if(!empty($user_agent)){
                foreach ($lists as $key) {
                    if ( strpos($user_agent, $key ) !== false){
                        $block = true;
                        break;
                    }
                }
            }
            if($block === false && !empty($ip)){
                //IPは先頭からのチェックに限定して 0 判定
                foreach ($lists as $key) {
                    if ( strpos($ip, $key ) === 0){
                        $block = true;
                        break;
                    }
                }
            }
        }
        return $block;
    }
    
    //仮ログインユーザーと推定された場合に template_redirect でログイン状態を確定して blocking bot アクセスか再確認
    //※ログインユーザーは間違えてブロックIPに登録されても無視
    //※非ログインの blocking bot access magatama_lv1 で対応済み
    static function bot_blocking() {
        global $yasakani_cache;
        if ($yasakani_cache->is_enable('sqlite')) {
            if (!is_user_logged_in() && self::is_bot_blocking()) {
                header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
                $yasakani_cache->put_log('bot_block', $_SERVER['REQUEST_URI'], '-', 1, 0, null, 1);
                exit;
            }
        }
    }

    //不正アクセスにより自動的にブロックされたIP等からのアクセスか判定
    static function is_autoblocked( $autoblocklist ) {
        $req_url = $_SERVER['REQUEST_URI'];
        $block = false;
        $ip = (!empty($_SERVER["REMOTE_ADDR"]))? $_SERVER["REMOTE_ADDR"] : '';
        if (!empty($ip) && !empty($autoblocklist) && strpos($autoblocklist, $ip ) !== false){
            if ( strpos($req_url, '/wp-cron.php' ) !== false){
                //BruteForce 有効期限切れの解除 wp-cron がデバッグ時にブロックされないようにスルー
                if ( !preg_match( "#wp\-cron\.php\?doing_wp_cron=[0-9\.]+?$#m", $req_url, $matches ) ){
                    $block = true;
                }
            } else if ( strpos($req_url, '/wp-login.php' ) !== false){
                //BruteForce ブロック登録でなければ、login へのアクセスは許可する (間違ってログインユーザーが不正アクセスでブロックされても正しくログインできれば回復出来る)
                $pattern = "\[" . preg_quote($ip) . "/([^/]+?)/BruteForce\]"; //"[$ip/$addtm/$type]" type=InvalidRequest/BruteForce
                if ( preg_match( "#$pattern#", $autoblocklist, $matches ) ){
                    if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET' ) {
                        //wp-login.php ページはリセット操作を行わせる為にスルー
                    } else if ( strpos($req_url, '?action=lostpassword' ) !== false || strpos($req_url, '?action=rp' ) !== false || strpos($req_url, '?action=resetpass' ) !== false){
                        //BruteForce ブロックでもパスワードのリセット＆再設定を許可する為 action パラメータが lostpassword / rp / resetpass はスルー
                    } else {
                        $block = true;
                    }
                }
            } else {
                $block = true;
            }
        }
        return $block;
    }
    
    static function a_array_csstr($a_array, $excludekey, &$string) {
        foreach ($a_array as $key => $data) {
            $excfg = false;
            foreach ($excludekey as $exkey) {
               if(false !== strpos($key, $exkey )) {
                   $excfg = true;
                   break;
               }
            }
            if($excfg === false){
                if(is_array($data)){
                    $string .= '[' . $key . ']';
                    self::a_array_csstr($data, $excludekey, $string);
                    $string .= ',';
                } else {
                    $string .= '[' . $key . ']' . $data . ',';
                }
            }
        }
    }

    //サイト攻撃と疑われるアクセスを自動的にブロックしてブロックリストへIPを登録する
    //XSS 何らかのタグが request_url に含まれている
    //query に不正なSQLをつけてくるリクエスト(主なコマンドのみチェックする)                    
    //スーパーグローバル変数汚染
    //NULL(\0 \x00 %00) バイト攻撃, ディレクトリトラサーバル (../), 重要な保護ファイル
    //PHP シェルインジェクション攻撃 (eval preg_replace usort call_user_func callback system include require exec passthru popen proc_open shell_exec, よく使われるコード変換と `バッククオート) 
    static function is_invalid_request( $checkdata ) {
        $block = false;
        $psurl = @parse_url($checkdata);
        if ((strpos($checkdata, '<' ) !== false || strpos($checkdata, '%' ) !== false) && (preg_match( '/<.+>/s', $checkdata) || preg_match( '/%3C.+%3E/is', $checkdata) )){
            $block = true;
        } else if (!empty($psurl['query'])){
            foreach (array('SELECT ','UNION ','UPDATE ','INSERT ','CREATE ','NULL') as $cmd) {
                if ( stripos($psurl['query'], $cmd ) !== false){
                    $block = true;
                    break;
                }
            }
        }
        if(!$block){
            foreach(array($_GET , $_POST , $_COOKIE) as $arr) {
                if (!empty($arr)){
                    foreach (array('GLOBALS','_GET','_POST','_COOKIE','_REQUEST','_SERVER','_ENV','_FILES','_SESSION') as $sg) {
                        if (!empty($arr[$sg])){
                            $block = true;
                            break;
                        }
                    }
                }
            }            
        }
        if(!$block){
            foreach (array('%00','\0','\x00','../','..%2F','wp-config.','.htaccess','.htpasswd','/passwd') as $key) {
                if ( stripos($checkdata, $key ) !== false){
                    $block = true;
                    break;
                }
            }
        }
        if(!$block){
            if (strpos($checkdata, '`' ) !== false){ //backquote
                $block = true;
            } else {
                foreach (array('eval','preg_replace','usort','call_user_func','system','exec','passthru','popen','proc_open','pcntl_exec','pcntl_fork','shell_exec','ini_set','base64_decode','uudecode','str_rot13') as $key) {
                    if ( (stripos($checkdata, $key )) !== false){
                        if (stripos($checkdata, "$key " ) !== false || stripos($checkdata, "$key(" ) !== false){
                            $block = true;
                            break;
                        }
                    }
                }
            }
        }
        if($block && !empty($_SERVER['REMOTE_ADDR'])){
            self::add_autoblocklist( $_SERVER['REMOTE_ADDR'] );
        }
        return $block;
    }

    //ゼロデイ攻撃 PHP Direct access (auto_prepend_file で呼び出された場合に対応可能)
    //ホワイトリスト以外の documentroot, wp-admin, wp-includes, wp-content/plugins, wp-content/themes 以下への　php へのダイレクトアクセスをブロック
    //※auto_prepend_file でないと php既存ファイルの脆弱性に対するダイレクトアクセス攻撃は wordpress にリクエストが渡されないので防げない
    static function is_zeroday_attack( $checkdata, $is_login, $login_site ) {
        global $yasakani_cache;
        $block = false;
        $psurl = @parse_url($checkdata);
        if(!empty($psurl['path'])){
            $checkpath = $psurl['path'];
            if ( stripos($checkpath, '.php' ) !== false){
                // $checkpath に /wordpress/index.php のようにサブディレクトリが含まれる場合があるのでサイト名が含まれていたら取り除いてから以下の処理を行う
                $loginurl = '';
                $scheme = '';
                $host = '';
                $sitekeys = $yasakani_cache->get_sitekey();
                if(!empty($sitekeys)){
                    foreach($sitekeys as $key) {
                        $loginurl = $key;
                        $site = @parse_url($key);
                        $scheme = $site['scheme'];
                        $host = $site['host'];
                        if(!empty($site['path']) && $site['path'] != '/'){
                            if ( stripos($checkpath, $site['path'] ) === 0){
                                $checkpath = str_replace( $site['path'], '', $checkpath);
                                break;
                            }
                        }
                    }
                    if(!empty(self::$setting['trustedfile'])){
                        $lists = array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", self::$setting['trustedfile'])));
                    }
                    $lists[] = '/index.php';
                    $lists[] = '/wp-login.php';
                    $whitefile = false;
                    foreach ($lists as $file) {
                        if ( stripos($checkpath, $file ) === 0){
                            $whitefile = true;
                            break;
                        }
                    }
                    if(!$whitefile){
                        if($is_login === false){
                            //ログインしていない wp-admin へのアクセスはリダイレクトさせてログインを促す
                            if ( stripos($checkpath, '/wp-admin' ) === 0 && !empty($loginurl)){
                                global $yasakani_cache_action;
                                $redirect = $loginurl. '/wp-login.php?redirect_to=' . urlencode("$scheme://$host$checkdata") . '&reauth=1';
                                $yasakani_cache_action['wp_redirect'] = "[302] : $redirect";
                                header("Location: $redirect", true, 302 );
                                exit;
                            } else {
                                //Server による wp-cron はスルーさせる
                                $inserver = false;
                                if(!empty($_SERVER['SERVER_ADDR']) && !empty($_SERVER['REMOTE_ADDR']) && $_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'] ){
                                    $inserver = true;
                                }
                                if(!($inserver && stripos($checkpath, '/wp-cron' ) !== false)){
                                    $block = true;
                                }
                            }
                        } else {
                            //ログインしてるなら wp-admin はコアの処理に任せるが、それ以外はブロック
                            //※wp-admin 以下に不正な PHP を入れられるリスクもあるが、基本的には管理者以外書き込み禁止に設定されているはずなので使いやすさ優先
                            //※gutenberg を使うと wp-includes 下の wp-tinymce.php へ何故かアクセスしてくるので除外する 
                            if ( stripos($checkpath, '/wp-admin' ) === false && stripos($checkpath, '/wp-includes/js/tinymce/wp-tinymce.php' ) === false){
                                $block = true;
                            }
                        }
                    }                        
                }
            }
        }
        if($block && !empty($_SERVER['REMOTE_ADDR'])){
            self::add_autoblocklist( $_SERVER['REMOTE_ADDR'] );
        }
        return $block;
    }

    //非ログインユーザーの _POST 内のデータチェック
    //NULL(\0 \x00 %00) バイト攻撃, ディレクトリトラサーバル (../), 重要な保護ファイル
    //PHP シェルインジェクション攻撃 (eval preg_replace usort call_user_func callback system include require exec passthru popen proc_open shell_exec, よく使われるコード変換と `バッククオート) 
    //但し、コメントやbbpressフォーラム投稿(post_content, bbp_topic_content, bbp_reply_content)等のサニタイズやスパム判定は WP標準機能や Akismet で対策可なので除く
    static function is_invalid_postreq( $checkdata ) {
        $block = false;
        $post_str = '';
        if(is_array($checkdata)){
            //$_POST data カンマ区切りの文字列へ変換して評価する
            self::a_array_csstr($checkdata, array('comment', 'content'), $post_str );
        }
        $checkdata = $post_str; 
        foreach (array('%00','\0','\x00','../','..%2F','wp-config.','.htaccess','.htpasswd','/passwd') as $key) {
            if ( stripos($checkdata, $key ) !== false){
                $block = true;
                break;
            }
        }
        if(!$block){
            if (strpos($checkdata, '`' ) !== false){ //backquote
                $block = true;
            } else {
                foreach (array('eval','preg_replace','usort','call_user_func','system','exec','passthru','popen','proc_open','pcntl_exec','pcntl_fork','shell_exec','ini_set','base64_decode','uudecode','str_rot13') as $key) {
                    if ( (stripos($checkdata, $key )) !== false){
                        if (stripos($checkdata, "$key " ) !== false || stripos($checkdata, "$key(" ) !== false){
                            $block = true;
                            break;
                        }
                    }
                }
            } 
        }
        if($block && !empty($_SERVER['REMOTE_ADDR'])){
            self::add_autoblocklist( $_SERVER['REMOTE_ADDR'] );
        }
        return $block;
    }

    //自動ブロックリストへIPを登録
    static function add_autoblocklist( $ip, $type='InvalidRequest' ) {
        global $yasakani_cache;
        $ret = false;
        if (!empty($ip)){
            $pattern = "\[" . preg_quote($ip) . "/([^/]+?)/$type\]"; //"[$ip/$addtm/$type]" type=InvalidRequest/BruteForce
            if ( !preg_match( "#$pattern#", self::$setting['autoblocklist'], $matches ) ) {
                //サーバーIP,ログインIPをチェックしてログイン履歴のあるIPは除外
                $loginip = array();
                if($yasakani_cache->is_enable('log')){
                    $loginip = $yasakani_cache->sqldb->sql_get_results("SELECT ip FROM log WHERE login = 100 GROUP BY ip;", array(), SQLITE3_ASSOC);
                }
                if(!empty($_SERVER['SERVER_ADDR'])){
                    $loginip[] = array('ip' => $_SERVER['SERVER_ADDR']);
                }
                $loginlist = '';
                if(!empty($loginip)){
                    foreach($loginip as $item){
                        $loginlist .= "{$item['ip']},";
                    }
                }
                if ( empty($loginlist) || strpos($loginlist, $ip ) === false){
                    $localtm = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
                    $addtm = $localtm->format('Y-m-d H:i:s');
                    if(!empty(self::$setting['autoblocklist']))
                        self::$setting['autoblocklist'] .= ",";
                    self::$setting['autoblocklist'] .= "[$ip/$addtm/$type]";
                    $yasakani_cache->sqldb->sql_exec("UPDATE setting SET autoblocklist = ?", array(self::$setting['autoblocklist']));                
                    $ret = true;
                }
            }
        }
        return $ret;
    }
    
    //自動ブロックリストから all / 指定IP を削除する
    static function delete_autoblocklist($ip='', $type='') {
        global $yasakani_cache;
        if(!empty(self::$setting['autoblocklist'])){
            $upd = false;
            if(empty($ip)){
                self::$setting['autoblocklist'] = '';
                $upd = true;
            } else {
                //ブロックリストから指定IPをクリア
                $array = array_map("trim", explode(',', self::$setting['autoblocklist']));
                if(!empty($array)){
                    $narray = array();
                    foreach ($array as $item) {
                        if (!empty($type)){
                            if(strpos($item, $type) === false){
                                $narray[] = $item;
                            } else if (strpos($item, $ip ) === false){
                                $narray[] = $item;
                            }  
                        } else if (strpos($item, $ip ) === false){
                            $narray[] = $item;
                        }
                    }
                    $array = $narray;
                }
                $lists = implode(',', $array); 
                if(self::$setting['autoblocklist'] != $lists){
                    self::$setting['autoblocklist'] = $lists;
                    $upd = true;
                }
            }
            if($upd)
                $yasakani_cache->sqldb->sql_exec("UPDATE setting SET autoblocklist = ?", array(self::$setting['autoblocklist']));
        }
    }

    //セキュリティ用のボットデータテーブルの更新
    //※エラー時は logdt にエラータイプをセットする
    static function bot_db_update( $logdt, $req_url, $ip, $user_agent ) {
        global $yasakani_cache, $yasakani_cache_action;
        $obj = $yasakani_cache->sqldb->sql_get_row( "SELECT * FROM setting WHERE ROWID = 1");
        if(is_object($obj)){
            self::$setting['autoblocklist']= (!empty($obj->autoblocklist))? $obj->autoblocklist : '';
        }
        if( !empty($yasakani_cache_action['did_authenticate']) && !empty($yasakani_cache_action['username']) && function_exists( 'did_action' ) && did_action( 'wp_login_failed' ) !== 0) {
            //既に backend_security did_authenticate フィルターでブロック済み
            if ( preg_match( "#\[" . preg_quote($ip) . "/([^/]+?)/BruteForce\]#", self::$setting['autoblocklist'] ) ) {
                $logdt['type'] = 'BruteForce';
            }                
        }

        $login = $logdt['login'];   //内部処理用のログ種別コード 5=wp-admin, 9=404 type
        $ipobj = $yasakani_cache->sqldb->sql_get_row( "SELECT * FROM bot WHERE bip = ?", array($ip));
        if(!is_object($ipobj)){
            $count = 1;
            $count_restrict = ($login == 5)? 1 : 0;
            $count_404 = 0;
            $date_404 = '2000-01-01 00:00:00';
            if($login == 9) {
                $count_404 = 1;
                $date_404 = $logdt['date'];
            }
            $count_login = ((!empty($yasakani_cache_action['did_authenticate']) && !empty($yasakani_cache_action['username']) && function_exists( 'did_action' ) && did_action( 'wp_login_failed' ) !== 0))? 1 : 0;
            $res = $yasakani_cache->sqldb->sql_exec("INSERT INTO bot (bip, user_agent, count, count_404, count_restrict, count_login, date_404) VALUES ( ?, ?, ?, ?, ?, ?, ?)", 
                    array($ip, $user_agent, $count, $count_404, $count_restrict, $count_login, $date_404));
        } else {
            $count = (int)$ipobj->count + 1;
            $count_restrict = (int)$ipobj->count_restrict;
            $count_404 = (int)$ipobj->count_404;
            if ($login == 5){
                //wp-admin へのアクセスはログインしていなければリダイレクトされる
                $count_restrict += 1;
            }
            $date_404 = (!empty($ipobj->date_404))? $ipobj->date_404 : '2000-01-01 00:00:00';
            if($login == 9) {
                //date_404 が未設定か現在のローカル時間より１分以内ならカウントアップ、1分以上ならカウント１と時間更新のみ
                $last_404 = new DateTime( $date_404, new DateTimeZone(self::$setting['timezone']));
                $localtm  = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
                $cmptm = $localtm->modify("-60 second");
                if($last_404 >= $cmptm){
                    //iPhone が apple-touch-icon-xxxxx.png の数種類のデータ取得を連続して試みるので除外
                    //使用停止したプラグイン等の js, css へのアクセスの場合あり
                    if(strpos($req_url, 'apple-touch-icon') !== false || strpos($req_url, '.css') !== false || strpos($req_url, '.js') !== false)
                        $count_404 = 1;
                    else        
                        $count_404 = $count_404 + 1;
                } else {
                    $count_404 = 1;
                }
                $date_404 = $logdt['date'];
                //同一IPから1分以内の 404 アクセスが5回あった場合は不正アクセスと見なす
                if ( $yasakani_cache->is_enable('autoblock') && $count_404 >= 5) {
                    $count_404 = 0; //再監視用にカウントリセット
                    $logdt['type'] = 'InvalidRequest';
                    self::add_autoblocklist( $ip, 'InvalidRequest' );
                }
            }

            //ログインに5回以上失敗した場合そのIPをブロックリストへ登録(ログイン履歴がある場合は除外)
            $count_login = (int)$ipobj->count_login;
            if( !empty($yasakani_cache_action['did_authenticate']) && !empty($yasakani_cache_action['username']) && function_exists( 'did_action' ) && did_action( 'wp_login_failed' ) !== 0) {
                if ( $yasakani_cache->is_enable('loginblock')) {
                    $count_login += 1;
                    if ($count_login >= 5) {
                        $count_login = 0; //再監視用にカウントリセット
                        $logdt['type'] = 'BruteForce';
                        self::add_autoblocklist( $ip, 'BruteForce' );
                    }
                } else {
                    $count_login += 1;
                }
            }
            $res = $yasakani_cache->sqldb->sql_exec("UPDATE bot SET count = ?, count_404 = ?, count_restrict = ?, count_login = ?, date_404 = ? WHERE bip = ?", 
                    array($count, $count_404, $count_restrict, $count_login, $date_404, $ip));
        }
        //パスワードリセットされたら　BruteForce ブロック解除
        if( function_exists( 'did_action' ) && did_action( 'password_reset' ) !== 0) {
            if ( $yasakani_cache->is_enable('loginblock')) {
                self::delete_autoblocklist( $ip, 'BruteForce' );
            }
        }
        //ゼロデイ攻撃や php への権限のないダイレクトアクセス 、短時間に5回以上の404アクセスやがあれば、autoblocklist へ登録(次のアクセスから自動ブロック)
        if (($yasakani_cache->is_enable('autoblock') || $yasakani_cache->is_enable('zerodayblock')) && $logdt['type'] == 'InvalidRequest') {
            self::add_autoblocklist( $ip );
        }
        return $logdt;
    }    
}
