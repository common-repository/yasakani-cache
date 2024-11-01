<?php
/*
  File Name: backend_logstat.php
  Description: Simple, Easy, Ultra-high-speed Page Cache Powered by SQLite
  Author: enomoto@celtislab
  License: GPLv2
*/
if(!defined( 'ABSPATH' ) && !defined( 'YASAKANI_CACHE_DB' ))
    exit;

class YCBK_LOGSTAT {
    static $setting;

    public function __construct() {}

    //init action で初期化 (advanced-cache.php からの呼び出し不可)
    static function init() {
        self::$setting = YC_MUSE::get_setting();
        
        //オプション設定ページにログ関連を追加表示
        if (is_main_site()) {
            add_action( 'yc_additional_features_settings', function(){
                add_meta_box('metabox_yc_log',  esc_html__('Access Log', 'yasakani'), array('YCBK_LOGSTAT', 'metabox_settings_log'),  'yc_metabox_settings', 'normal');
                add_meta_box('metabox_yc_stat', esc_html__('Statistics', 'yasakani'), array('YCBK_LOGSTAT', 'metabox_settings_stat'), 'yc_metabox_settings', 'normal');
            }, 12);
            add_action( 'yc_additional_features_summary', function(){
                $nowstat  = self::get_statistics('today');
                if(!empty($nowstat)){
                    $pvdt = self::sub_chart_data($nowstat['Cache'], $nowstat['PV'], $nowstat['PV/h'], $nowstat['bot_block'], $nowstat['bot/h']);
                    echo '<div class="grid-row"><div class="summary-hed">' . esc_html__("Today's Access summary", 'yasakani') . '</div><div>' . $pvdt['hed'] . '</div></div>';
                }
            }, 10);
        }

        //ログインユーザー情報
        add_filter( 'authenticate', array( 'YCBK_LOGSTAT', 'did_authenticate'), 9999, 3);
        //wp_redirect 識別
        add_filter( 'wp_redirect_status',  array( 'YCBK_LOGSTAT', 'wp_redirect_status' ), 10, 2 );
        //Server side http request ログ
        add_filter( 'pre_http_request',  array( 'YCBK_LOGSTAT', 'pre_http_request' ), 1, 3 );
        add_action( 'http_api_debug',    array( 'YCBK_LOGSTAT', 'http_api_debug' ), 10, 5 );
        //Server side mail send ログ
        add_action( 'phpmailer_init', array( 'YCBK_LOGSTAT', 'phpmailer_init'), 8, 1 );
        //wp-cron hook,argv,callback function ログ
        add_filter( 'pre_unschedule_event',  array( 'YCBK_LOGSTAT', 'pre_unschedule_event' ), 10, 4 );
        //REST API ログ
        add_filter( 'rest_pre_echo_response',  array( 'YCBK_LOGSTAT', 'rest_pre_echo_response' ), 10, 3 );
        add_filter( 'rest_pre_serve_request',  array( 'YCBK_LOGSTAT', 'rest_pre_serve_request' ), 10, 4 );
        
        add_action( 'wp_ajax_yasakani_log_filter', array( 'YCBK_LOGSTAT', 'ajax_log_filter'));        
    }

    //Retrieve Login authenticate filter action is fired 
    static function did_authenticate($user, $username, $password) {
        global $yasakani_cache_action;
        $yasakani_cache_action['username'] = $username;
        $yasakani_cache_action['did_authenticate'] = true;
        return $user;
    }
    
    //wp_redirect_status が実行されたことの識別用
    static function wp_redirect_status( $status, $location) {
        if ( !empty($location) ){
            global $yasakani_cache_action;
            $yasakani_cache_action['wp_redirect'] = "[$status] : $location";
        }
        return $status;
    }
    
    /*
     * Server side request action start
     * 
     * @param false|array|WP_Error $preempt Whether to preempt an HTTP request's return value. Default false.
	 * @param array               $r        HTTP request arguments.
	 * @param string              $url      The request URL.
     */
    static function pre_http_request($preempt, $r, $url) {
        global $yasakani_request_timestart;
        $yasakani_request_timestart = microtime( true );
        return $preempt;
    }

    /*
     * Server side request action end
     * 
	 * @param array|WP_Error $response HTTP response or WP_Error object.
	 * @param string         $context  'response'
	 * @param string         $class    'Requests'
	 * @param array          $r        HTTP request arguments.
	 * @param string         $url      The request URL.
     */
    static function http_api_debug( $response, $context, $class, $r, $url) {
        self::put_serverlog('HTTP_Request', $r, $url, $response);
        return;
    }

    static function phpmailer_init( $phpmailer ) {
        $recipient = $phpmailer->getAllRecipientAddresses();
        if(!empty($recipient)){
            $from = "{$phpmailer->FromName}<{$phpmailer->From}>";
            $to   = '';
            foreach ($recipient as $key => $value) {
                $to .= "$key ";
            }            
            $subject = $phpmailer->Subject;
            $body    = $phpmailer->Body;
            self::put_mailerlog('phpmailer', $from, $to, $subject, $body);
        }
    }
    
	/**
	 * Filter to preflight or hijack unscheduling of events.
	 *
	 * Returning a non-null value will short-circuit the normal unscheduling
	 * process, causing the function to return the filtered value instead.
	 *
	 * For plugins replacing wp-cron, return true if the event was successfully
	 * unscheduled, false if not.
	 *
	 * @since 5.1.0
	 *
	 * @param null|bool $pre       Value to return instead. Default null to continue unscheduling the event.
	 * @param int       $timestamp Timestamp for when to run the event.
	 * @param string    $hook      Action hook, the execution of which will be unscheduled.
	 * @param array     $args      Arguments to pass to the hook's callback function.
	 */
    static function pre_unschedule_event($pre, $timestamp, $hook, $args ){
        if( defined('DOING_CRON') && DOING_CRON ){
            global $wp_filter, $yasakani_cache_action;
        	if ( isset( $wp_filter[ $hook ] ) && is_array($wp_filter[ $hook ]->callbacks) ) {
                foreach($wp_filter[$hook]->callbacks as $priority => $callbacks ){
                    foreach ($callbacks as $key => $function) {
                        $func = $key;
                        if (is_array($function['function'])) {
                            $func = $function['function'][1];
                        }
                        $yasakani_cache_action['cron_hook'][] = array('hook'=>$hook, 'priority'=>$priority, 'function'=>$func, 'args'=>$args);
                    }
                }
            }
        }
        return $pre;
    }

    //連想配列内の長い文字列データを最大 512文字に丸めて縮小化する
    static function array_textminify($a_array) {
        $r_array = $a_array;
        if(!empty($a_array) && is_array($a_array)){
            $r_array = array();
            foreach ($a_array as $key => $data) {
                if(is_array($data)){
                    $r_array[$key] = self::array_textminify($data);
                } else if(is_string($data)){
                    if(function_exists('mb_strlen') && function_exists('mb_substr')){
                        $r_array[$key] = (mb_strlen($data, "utf-8") < 512 )? $data : mb_substr($data, 0, 512, "utf-8") . ' ...'; 
                    } else {
                        $r_array[$key] = (strlen($data) < 512 )? $data : substr($data, 0, 512) . ' ...'; 
                    }
                    
                } else {
                    $r_array[$key] = $data; 
                }
            }
        }
        return $r_array;
    }
    
    /**
     * Filters the API response.
     *
     * Allows modification of the response data after inserting
     * embedded data (if any) and before echoing the response data.
     *
     * @since 4.8.1
     *
     * @param array            $result  Response data to send to the client.
     * @param WP_REST_Server   $this    Server instance.
     * @param WP_REST_Request  $request Request used to generate the response.
     */    
    static function rest_pre_echo_response($result, $server, $request ){
        if( defined('REST_REQUEST') && REST_REQUEST ){
            global $yasakani_cache_action;
			$jscnv = wp_json_encode( $result );       //json 変換事前チェック
    		$last_error_code = json_last_error();
            if ( ( defined( 'JSON_ERROR_NONE' ) && JSON_ERROR_NONE === $last_error_code ) || empty( $last_error_code ) ) {
                $resp = self::array_textminify($result); //$result 配列内の大きなデータを縮小化
            } else {                                  //json encode err set
                $json_error_message = json_last_error_msg();
                $json_error_obj = new WP_Error( 'rest_encode_error', $json_error_message, array( 'status' => 500 ) );
                $resp = $server->error_to_response( $json_error_obj );
                $resp = $resp->data[0];
                $yasakani_cache_action['rest']['Status']  = array('status' => 500);
            }
            $yasakani_cache_action['rest']['Result'] = $resp;
        }
        return $result;
    }

    /**
     * Filters whether the request has already been served.
     *
     * Allow sending the request manually - by returning true, the API result
     * will not be sent to the client.
     *
     * @since 4.4.0
     *
     * @param bool             $served  Whether the request has already been served.
     *                                           Default false.
     * @param WP_HTTP_Response $result  Result to send to the client. Usually a WP_REST_Response.
     * @param WP_REST_Request  $request Request used to generate the response.
     * @param WP_REST_Server   $this    Server instance.
     */
    static function rest_pre_serve_request($served, $result, $request, $server) {
        if( defined('REST_REQUEST') && REST_REQUEST ){
            global $yasakani_cache_action;
            $route = $request->get_route();
            $params = $request->get_params();
            $method = $request->get_method();
            $status = $result->get_status();
            $params = self::array_textminify($params); //$params 配列内の大きなデータを縮小化
            $yasakani_cache_action['rest']['Request'] = array('route'=>$route, 'params'=>$params);
            $yasakani_cache_action['rest']['Status']  = array('status'=>$status);
        }
        return $served;
    }
        
    //連想配列データを再帰的にキーと値に分けて表示
    static function a_array_print($a_array, &$output, &$depth) {
        if(empty($a_array))
            return;
        foreach ($a_array as $key => $data) {
            if(!empty($data)){
                $key = esc_html($key);
                if(is_array($data)){
                    $output .= "\n";
                    $output .= "[$key] : ";
                    $depth++;
                    self::a_array_print($data, $output, $depth);
                    $depth--;
                } else {
                    $cdatas = $data;
                    if(is_array(json_decode($data, true)) && (json_last_error() == JSON_ERROR_NONE)){
                        $cdatas = json_decode( $data, true);
                    } else {
                        //シリアライズ ワーニング抑制のため簡易チェック追加
                        if(preg_match("#s:\d{1,4}:#", $data)){
                            $unsdata = @unserialize($data);
                            if($unsdata !== false){
                                $cdatas = (is_object($unsdata))? (array)$unsdata : $unsdata;
                            }
                        }
                    }
                    if(is_array($cdatas)){
                        $cdepth = 0;
                        $cdata = '';
                        self::a_array_print($cdatas, $cdata, $cdepth);
                        $data = $cdata;
                    } else {
                        $data = esc_html($cdatas);
                    }
                    $output .= "\n";
                    for($n=0; $n<$depth; $n++)
                        $output .= '  ';
                    $output .= "[$key] : $data";
                }                
            }
        }
    }
    
    //Sever side request log data
    static function put_serverlog($type, $r, $req_url, $response ) {
        
        global $yasakani_cache;
        $req_url = urldecode($req_url);
        
        $t = array();
        $t['type']      = $type;
        $t['req_url']   = $req_url;

        $method = (!empty($r['method']))? $r['method'] : 'GET';
        $t['method']    = $method;
        $t['req_data']  = '';
        $t['title']     = "Server Side HTTP Request [$method]";
        $t['blogid']    = 0;
        $t['postid']    = 0;
        $t['login']     = 0;
        $t['bot']       = 0;

        $psurl = parse_url($req_url);
        $t['host'] = (!empty($psurl['host']))? $psurl['host'] : '';
        $t['path']  = (!empty($psurl['path']))? $psurl['path'] : '';
        $query['query'] = (!empty($psurl['query']))? $psurl['query'] : '';
        if($method == 'POST' && !empty($r['body'])){
            $prmdata = $r['body'];
            if(is_string($prmdata)){
                if(is_array(json_decode($prmdata, true)) && (json_last_error() == JSON_ERROR_NONE)){
                    $prmdata = json_decode( $prmdata, true);
                } else {
                    if(preg_match("#s:\d{1,4}:#", $prmdata)){
                        $unsdata = @unserialize($prmdata);
                        if($unsdata !== false){
                            $prmdata = (is_object($unsdata))? (array)$unsdata : $unsdata;
                        }
                    }
                }
            }
            if(is_array($prmdata)){
                $depth = 0;
                $getdata = '';
                self::a_array_print($prmdata, $getdata, $depth);
                $query['parameter'] = $getdata;
            } else {
                $query['parameter'] = (strlen($prmdata) < 1024 )? esc_html($prmdata) : substr(esc_html($prmdata), 0, 1020) . '...';                    
            }
        }
        $t['query'] = wp_json_encode($query);
        
        //$t['ip'] = (!empty($_SERVER["REMOTE_ADDR"]))? $_SERVER["REMOTE_ADDR"] : '';
        $t['ip'] = (!empty($_SERVER["SERVER_ADDR"]))? $_SERVER["SERVER_ADDR"] : '';
        $t['user_agent'] = '';
        
        $t['referer']  = '';
        $t['refhost']  = '';
        $t['refpath']  = '';
        $t['refquery'] = '';

        $result = $content_type = '';
        if( is_wp_error( $response ) ){
            $result = $response->get_error_message();
        } else {
            $t['user_agent']= (!empty($response['user-agent']))? $response['user-agent'] : '';
            if(!empty($response['response']['code']))
                $t['title'] .= " {$response['response']['code']}";
            if(!empty($response['response']['message']))
                $t['title'] .= " {$response['response']['message']}";
            if(!empty($response['headers']['content-type'])){
                $content_type = $response['headers']['content-type'];
                $t['title'] .= " $content_type";
            }
            if(!empty($response['body'])){
                $is_json = false;
                if(is_string($response['body'])){
                    //レスポンスは raw データのままで先頭データのみを保存
                    if(strlen($response['body']) < 1024 )
                        $t['req_data'] .= esc_html($response['body']);
                    else
                        $t['req_data'] .= substr(esc_html($response['body']), 0, 1020) . '...';                    
                }
            }
        }
        $t['error'] = $result;
        
        global $yasakani_request_timestart;
        $t['response'] = number_format( microtime( true ) - $yasakani_request_timestart, 3 );
                
        $localtm = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
        $t['date'] = $localtm->format('Y-m-d H:i:s');

        //Log Add
        $sqldb = $yasakani_cache->sqldb;
        if($sqldb->beginTransaction('IMMEDIATE')){
            try {
                $res = $sqldb->sql_exec("INSERT INTO log ( date, response, type, req_url, method, req_data, blogid, postid, host, path, query, title, user_agent, referer, refhost, refpath, refquery, ip, error, login, bot) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                        array($t['date'], $t['response'], $t['type'], $t['req_url'], $t['method'], $t['req_data'], $t['blogid'], $t['postid'], $t['host'], $t['path'], $t['query'], $t['title'], $t['user_agent'], $t['referer'], $t['refhost'], $t['refpath'], $t['refquery'], $t['ip'], $t['error'], $t['login'], $t['bot']));

                $sqldb->commit();

            } catch (Exception $e) {
                global $yasakani_cache_action;
                $sqldb->rollback();
                $errmsg = $e->getMessage();
                $yasakani_cache_action['db_error'] = $errmsg;
            }            
        }
    }

    //phpmailer send log    
    static function put_mailerlog($type, $from, $to, $subject, $body ) {
        global $yasakani_cache;
        $t = array();
        $t['type']      = $type;
        $t['req_url']   = "Mail to : $to";

        $t['method']    = 'Send';
        $t['title']     = $subject;
        $t['req_data']  = "From : $from" . "\n";
        $t['req_data']  .= (strlen($body) < 1024 )? $body : substr($body, 0, 1020) . '...';;
        
        $t['blogid']    = 0;
        $t['postid']    = 0;
        $t['host']      = '';
        $t['path']      = '';        
        $t['query']     = '';
        $t['login']     = 0;
        $t['bot']       = 0;
        $t['ip']        = '';
        $t['user_agent']= '';
        $t['referer']  = '';
        $t['refhost']  = '';
        $t['refpath']  = '';
        $t['refquery'] = '';

        $t['error']    = '';
                
        $localtm = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
        $t['date'] = $localtm->format('Y-m-d H:i:s');
        $t['response'] = '';

        //Log Add
        $sqldb = $yasakani_cache->sqldb;
        if($sqldb->beginTransaction('IMMEDIATE')){
            try {
                $res = $sqldb->sql_exec("INSERT INTO log ( date, response, type, req_url, method, req_data, blogid, postid, host, path, query, title, user_agent, referer, refhost, refpath, refquery, ip, error, login, bot) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                        array($t['date'], $t['response'], $t['type'], $t['req_url'], $t['method'], $t['req_data'], $t['blogid'], $t['postid'], $t['host'], $t['path'], $t['query'], $t['title'], $t['user_agent'], $t['referer'], $t['refhost'], $t['refpath'], $t['refquery'], $t['ip'], $t['error'], $t['login'], $t['bot']));

                $sqldb->commit();

            } catch (Exception $e) {
                global $yasakani_cache_action;
                $sqldb->rollback();
                $errmsg = $e->getMessage();
                $yasakani_cache_action['db_error'] = $errmsg;
            }            
        }
    }

    //protect option log    
    static function put_protect_log( $type, $option, $value, $blogid ) {
        global $yasakani_cache;
        $t = array();
        $t['type']      = $type;
        $t['req_url']   = $option;

        $t['method']    = '';
        $t['title']     = "Update option protect";
        $t['req_data']  = $value;
        
        $t['blogid']    = $blogid;
        $t['postid']    = 0;
        $t['host']      = '';
        $t['path']      = '';        
        $t['query']     = '';
        $t['login']     = 0;
        if(function_exists('is_user_logged_in') && is_user_logged_in()){
            $t['login'] = 100;   //login
        }         
        $t['bot']       = 0;
        $t['ip']        = '';
        $t['user_agent']= '';
        $t['referer']  = '';
        $t['refhost']  = '';
        $t['refpath']  = '';
        $t['refquery'] = '';

        $t['error']    = '';
                
        $localtm = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
        $t['date'] = $localtm->format('Y-m-d H:i:s');
        $t['response'] = '';

        //Log Add
        $sqldb = $yasakani_cache->sqldb;
        if($sqldb->beginTransaction('IMMEDIATE')){
            try {
                $res = $sqldb->sql_exec("INSERT INTO log ( date, response, type, req_url, method, req_data, blogid, postid, host, path, query, title, user_agent, referer, refhost, refpath, refquery, ip, error, login, bot) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                        array($t['date'], $t['response'], $t['type'], $t['req_url'], $t['method'], $t['req_data'], $t['blogid'], $t['postid'], $t['host'], $t['path'], $t['query'], $t['title'], $t['user_agent'], $t['referer'], $t['refhost'], $t['refpath'], $t['refquery'], $t['ip'], $t['error'], $t['login'], $t['bot']));

                $sqldb->commit();

            } catch (Exception $e) {
                global $yasakani_cache_action;
                $sqldb->rollback();
                $errmsg = $e->getMessage();
                $yasakani_cache_action['db_error'] = $errmsg;
            }            
        }
    }

    //===========================================================================================
    // 設定オプションページのメタボックス
    //===========================================================================================
    // Access Log
  	static function metabox_settings_log($object, $metabox) {
        global $yasakani_cache;
        if(! $yasakani_cache->is_enable('sqlite') || empty($metabox['id']) || $metabox['id'] != 'metabox_yc_log')
            return;
        
        ?>
        <form method="post" autocomplete="off">
        <?php wp_nonce_field( 'yasakani-cache');
        
        if ($yasakani_cache->is_enable('log')) {
            $now = new DateTime("now", new DateTimeZone('utc'));
            $localtm = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
            $day = $localtm->format("Y-m-d");
            $ntime = $localtm->format('H:i');
            $ajax_nonce = wp_create_nonce('yasakani_log_filter');

            echo '<div class="filter-opt">';
            esc_html_e('Log Filter', 'yasakani');
//            $log_day  = array('' => esc_html__('Today', 'yasakani'), 'lastday' => esc_html__('Lastday', 'yasakani'));
            $log_day  = array('' => esc_html__('Today', 'yasakani'),
                '-1 day' => esc_html__('Lastday', 'yasakani'),
                '-2 day' => esc_html__('-2 day', 'yasakani'),
                '-3 day' => esc_html__('-3 day', 'yasakani'),
                '-4 day' => esc_html__('-4 day', 'yasakani'),
                '-5 day' => esc_html__('-5 day', 'yasakani'),
                '-6 day' => esc_html__('-6 day', 'yasakani'),
                );
            
            echo '<span>' . Yasakani_option::dropdown('yasakani_log_day', $log_day, '') . '<input name="yasakani_log_time" type="time" value="' . $ntime . '" title="Time zone" /></span>';
            echo '<span>' . esc_html__('Type ','yasakani');
            $reqtype = array(''                   => esc_html__('All access', 'yasakani'),
                             'wp-login'           => esc_html__('Login', 'yasakani'),
                             'loggedin'           => esc_html__('Logged in access', 'yasakani'),
                             'nonloggedin'        => esc_html__('Non logged in access', 'yasakani'),
                             'wp-comments'        => esc_html__('Comments', 'yasakani'),
                             'xmlrpc'             => esc_html__('XML-RPC', 'yasakani'),
                             'wp-json'            => esc_html__('REST API', 'yasakani'),
                             'wp-admin'           => esc_html__('wp-admin', 'yasakani'),
                             'wp_redirect'        => esc_html__('Redirect', 'yasakani'),
                             'bot_block'          => esc_html__('Blocked Bot', 'yasakani'),
                             '404'                => esc_html__('404 error', 'yasakani'),
                             'InvalidRequest'     => esc_html__('Invalid Request', 'yasakani'),
                             'phpmailer'          => esc_html__('wp_mail send', 'yasakani'),
                             'serverside'         => esc_html__('Server Side Request', 'yasakani'));

            $option = Yasakani_setting::get_option();
            $log_type = '';
            if(in_array($option['log_type'], array('wp-login','loggedin','nonloggedin','wp-comments','xmlrpc','wp-json','wp-admin','wp_redirect','bot_block','404','phpmailer','serverside' ))){
                $log_type = $option['log_type'];
            }
            echo Yasakani_option::dropdown('yasakani_log_type', $reqtype, $log_type );
            echo '</span>';
            echo '<span>' . esc_html__('PostID ','yasakani'). '<input class="small-text"　type="text" name="yasakani_log_pid" value="" pattern="\d{1,6}" /></span>';
            echo '<span>' . esc_html__('IP Filter ','yasakani'). '<input class="medium-text"　type="text" name="yasakani_log_ip" value="" pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}" placeholder="xxx.xxx.xxx.xxx" /></span>';
            echo '<span class="hide-if-no-js"><a class="button yasakani-filter-opt-apply"  href="#yasakani-log-1" onclick="Apply_log_filter(\'' . $ajax_nonce . '\',\'apply\');return false;" >' . esc_html__('Apply') . '</a></span>';
            echo '</div>';
            ?>
            <script type='text/javascript'>
            Apply_log_filter = function(nonce, button){
                let logpage = parseInt(document.querySelector('#yasakani-logpage').value, 10);
                if(button == 'next'){
                    logpage += 1;
                } else if(button == 'prev'){
                    logpage -= 1;
                } else {
                    logpage = 0;
                }
                if(logpage >= 0){
                    fetch( ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ 
                            action: "yasakani_log_filter", 
                            log_day:  document.querySelector("select[name='yasakani_log_day']").value,
                            log_time: document.querySelector("input[name='yasakani_log_time']").value,                            
                            log_page: logpage,
                            log_type: document.querySelector("select[name='yasakani_log_type']").value, 
                            log_pid:  document.querySelector("input[name='yasakani_log_pid']").value, 
                            log_ip:   document.querySelector("input[name='yasakani_log_ip']").value, 
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
                            document.querySelector('#yasakani-log-1').innerHTML = json.data;
                            document.querySelector('#yasakani-logpage').value = json.logpage;
                            setTimeout(function(){ document.querySelector('#yasakani-log-1').scrollTo(0, 0);}, 10);
                            YC_log_detail();
                        } else { alert( json.msg ); }
                    })                    
                    .catch( function(error){
                    })        
                }
            };            
            </script> 
            <?php
            //$res0 = timer_stop();
            $logs = self::get_log($day, $ntime, 0, 'log', $log_type);
            //$res1 = timer_stop();
            self::cache_log_table($logs);
                                    
            //不正アクセスIPリスト
            //Debug data
            //self::$db->add_autoblocklist( '192.168.0.1' );
            //self::$db->add_autoblocklist( '192.168.0.3' );
            //self::$db->add_autoblocklist( '192.168.0.5' );
            
            $autoblocklist = (!empty(self::$setting['autoblocklist']))? array_filter( array_map("trim", explode(',', self::$setting['autoblocklist'])), 'strlen') : '';
            if (!empty($autoblocklist)) {
            ?>
                <table id="yasakani_autoblocklist" class="widefat" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 90%; padding:15px 10px;"><?php esc_html_e('IP List that is automatically Blocking due to an Invalid Request', 'yasakani'); ?></th>
                            <th style="width: 10%; padding:15px 10px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                        foreach ($autoblocklist as $item) {
                            if ( preg_match( "#\[([^/]+?)/([^/]+?)/([^\]]+?)\]#", $item, $match ) ) { //"[$ip/$addtm/$type]" type=InvalidRequest/BruteForce
                                echo '<tr>';
                                echo '<td>' . $item . '</td>';
                                $ip  = $match[1];
                                $url = wp_nonce_url("options-general.php?page=yasakani-cache&amp;action=del_autoblock_ip&amp;ip=$ip", "yasakani-cache");
                                echo "<td><a class='delete' href='$url'>" . esc_html__('Delete') . "</a></td>";
                                echo "</tr>";
                            }
                        }
                    ?>
                    </tbody>
                </table>    
            <?php
            }
        } else {
            echo '<p>' . esc_html__("It is displayed when 'Log Mode' of 'Cache Setting' is enabled", 'yasakani') . '</p>';
        } ?>
        </form>
        <?php
    }

    /**
     * Log data get（メインサイトのみ表示）
     * $tmfilter  00:00 ... 23:00
     * $logpage   Log page offset 0 ... (x100)
     * $table log(today) / log_SUN / log_MON / log_TUE / log_WED / log_THU / log_FRI / log_SAT
     * $typefilter ''(all) / loggedin / nonloggedin / wp-login / wp-comments / xmlrpc / wp-admin (wp-admin/admin-ajax) / wp_redirect / wp-json / 404 / Bot block (bot_block) / InvalidRequest(auto_block/InvalidRequest/BruteForce/protect_option) /serverside(HTTP_Request) /phpmailer)
     * $ipfilter  ''(all) / xxx.xxx.xxx.xxx
     */
    static function get_log($day, $tmfilter, $logpage=0, $table='log', $typefilter='', $pidfilter='', $ipfilter='') {
        global $yasakani_cache;       
        if(! $yasakani_cache->is_enable('log') || !is_main_site() || !in_array($table, array('log', 'log_SUN', 'log_MON', 'log_TUE', 'log_WED', 'log_THU', 'log_FRI', 'log_SAT')))
            return 0;

        if(! $yasakani_cache->sqldb->is_table_exist( $table ))
            return 0;

        $sday = "'$day 00:00:00'";
        $eday = "'$day $tmfilter:59'";
        $offset = (!empty($logpage))? (int)$logpage * 100 : 0;
        $type = '';
        if(!empty($typefilter)){
            if($typefilter == 'loggedin')
                $type = "AND login = 100 ";
            else if($typefilter == 'nonloggedin')
                $type = "AND login != 100 AND type != 'HTTP_Request' AND type != 'phpmailer' ";
            else if($typefilter == 'wp-login')
                $type = "AND (type = 'wp-login' OR type = 'BruteForce')";
            else if($typefilter == 'wp-admin')
                $type = "AND (type = 'wp-admin' OR type = 'admin-ajax')";
            else if($typefilter == 'wp_redirect')
                $type = "AND type = 'wp_redirect' ";
            else if($typefilter == 'InvalidRequest')
                $type = "AND (type = 'InvalidRequest' OR type = 'auto_block' OR type = 'BruteForce' OR type = 'protect_option')";
            else if($typefilter == 'phpmailer')
                $type = "AND type = 'phpmailer' ";
            else if($typefilter == 'serverside')
                $type = "AND type = 'HTTP_Request' ";
            else    
                $type = "AND type = '$typefilter'";
        }
        $pid = (!empty($pidfilter))? "AND postid = '$pidfilter'" : '';
        $ip = (!empty($ipfilter))? "AND ip = '$ipfilter'" : '';

        $logs = $yasakani_cache->sqldb->sql_get_results("SELECT * FROM $table WHERE date >= $sday AND date <= $eday $type $pid $ip ORDER BY date DESC LIMIT ?, 100", array( $offset ));
        //$logs = $yasakani_cache->sqldb->sql_get_results("SELECT * FROM $table WHERE date <= $eday $type $ip ORDER BY date DESC LIMIT $offset, 100");   //For Debug
        //$logs = $yasakani_cache->sqldb->sql_get_results("SELECT * FROM log WHERE blogid = '$blogid' AND date IS NOT NULL ORDER BY date DESC LIMIT 0, 100");
        return $logs;
    }   

    static function log2html( $logs ) {
        //Type, LocalTime, Time, Title, req_url, referer, ip, user_agent
        $html = '';
        if(empty($logs))
            return '';
        $idx = 0;
        foreach ($logs as $log) {
            $idx++;
            $html .= '<tr>';
            switch ($log->type) {
                case 'Hit':
                case 'not_modified':
                    $html .= "<td class='log-hit m-size'>$log->type</td>";
                    break;
                case 'Store':
                    if (empty($log->error))
                        $html .= "<td class='log-save m-size'>$log->type</td>";
                    else
                        $html .= "<td class='log-save m-size' title='$log->error'>$log->type(E)</td>";
                    break;
                case 'login_user':
                case 'comment_user':
                case 'exclude_page':
                case 'not_publish':
                case 'post_password':
                case 'Cache_disable':
                case 'wp_redirect':
                    if (empty($log->error))
                        $html .= "<td class='log-exclude m-size'>$log->type</td>";
                    else
                        $html .= "<td class='log-exclude m-size' title='$log->error'>$log->type(E)</td>";
                    break;
                case 'InvalidRequest':
                case 'BruteForce':
                    $html .= "<td class='log-atack m-size'>$log->type</td>";
                    break;
                case 'auto_block':
                    $html .= "<td class='log-autoblock m-size'>$log->type</td>";
                    break;
                case 'bot_block':
                    $html .= "<td class='log-botblock m-size'>$log->type</td>";
                    break;
                case 'php_error':
                    $html .= "<td class='log-phperror m-size' title='$log->error'>$log->type</td>";
                    break;
                case 'HTTP_Request':                    
                case 'phpmailer':                    
                    if (empty($log->error))
                        $html .= "<td class='log-server m-size'>$log->type</td>";
                    else
                        $html .= "<td class='log-server m-size' title='$log->error'>$log->type(E)</td>";
                    break;
                case 'wp-json':                    
                    if (empty($log->error))
                        $html .= "<td class='log-etc m-size'>REST_API</td>";
                    else
                        $html .= "<td class='log-etc m-size' title='$log->error'>REST_API(E)</td>";
                    break;
                case 'protect_option':                    
                    $html .= "<td class='log-protect m-size'>$log->type</td>";
                    break;
                default:
                    if (empty($log->error))
                        $html .= "<td class='log-etc m-size'>$log->type</td>";
                    else
                        $html .= "<td class='log-etc m-size' title='$log->error'>$log->type(E)</td>";
                    break;
            }
            //log date utc -> local に変更したので改めてローカル時間へ変換する処理は不要
            $date = new DateTime($log->date);
            $fmtdate = $date->format('H:i:s');
            $dc_req_url = esc_html(urldecode($log->req_url));
            $dc_req_data = $dc_req_url;
            $dc_title = '';
            $files_data = '';
            $rest_result = '';
            $ajax_result = '';
            if($log->type == 'wp_redirect' && !empty($log->req_data)){
                if(!empty($log->method) && $log->method != 'GET')
                    $dc_req_url = "($log->method)" . $dc_req_url;

                $params = json_decode( $log->req_data, true);
                $depth = 0;
                self::a_array_print($params, $dc_req_data, $depth);
            } else if($log->type == 'wp-cron'){
                if(!empty($log->req_data)){
                    $dc_req_data = "Callback functions";
                    $funcs = json_decode( $log->req_data, true);
                    $hook = '';
                    foreach($funcs as $func){
                        $hook .= ' : ' . $func['hook'];
                    }
                    $dc_title = "Callback functions{$hook}";
                    $depth = 0;
                    self::a_array_print($funcs, $dc_req_data, $depth);
                }
                
            } else if($log->type == 'wp-json'){
                if(!empty($log->method) && $log->method != 'GET')
                    $dc_req_url = "($log->method)" . $dc_req_url;
                $dc_req_data = "";
                if(!empty($log->req_data)){
                    $rest_data = json_decode( $log->req_data, true);
                    if(!empty($rest_data['Request']['route'])){
                        $route = $rest_data['Request']['route'];
                        $dc_title = "REST route : {$route}";
                    }
                    if(!empty($rest_data['Result'])){
                        $rest_result = $rest_data['Result'];
                    }
                    $depth = 0;
                    self::a_array_print($rest_data['Request'], $dc_req_data, $depth);
                }

            } else if($log->type == 'HTTP_Request' && (!empty($log->query) || !empty($log->req_data))){
                if(!empty($log->query)){
                    $query = json_decode( $log->query, true);
                    if(!empty($query['parameter']))
                        $dc_req_data .= "\n" . '(Parameter)' . $query['parameter'];
                }
                if(!empty($log->req_data)){
                    $dc_req_data .= "\n" . "\n" . '(Response)' . "\n" . $log->req_data;
                }
            } else if($log->type == 'phpmailer' && !empty($log->req_data)){
                $dc_req_data .= "\n" . $log->req_data;
            } else if($log->type == 'protect_option' && !empty($log->req_data)){
                $dc_req_url = 'option=' . $dc_req_url . ', value=' . $log->req_data;
                $dc_req_data = $dc_req_url;
            } else {
                if(!empty($log->method) && $log->method != 'GET')
                    $dc_req_url = "($log->method)" . $dc_req_url;
                if($log->method == 'POST' && !empty($log->req_data)){
                    $params = json_decode( $log->req_data, true);
                    if(!empty($params['_FILES'])){
                        $files_data = $params['_FILES'];
                        unset($params['_FILES']);
                    }
                    $depth = 0;
                    self::a_array_print($params, $dc_req_data, $depth);
                }
            }
            if(empty($dc_title)){
                $dc_title = esc_html( $log->title );
                if($log->method == 'POST' && !empty($log->req_data) && strpos($log->req_url, 'admin-ajax.php' ) !== false){
                    if ( preg_match( '#"action":"(.+?)"#m', $log->req_data, $match ) ){
                        $dc_title = 'Action : ' . $match[1];
                        $ajax_result = $dc_req_data;
                    }
                }
            }
            $dc_referer = esc_html( urldecode($log->referer) );
            $dc_ua = esc_html( $log->user_agent );
            $is_mobile = YC_MUSE::is_mobile( $dc_ua );
            $ua_mobile = ($is_mobile)? 'ua-mobile' : '';
            if(!empty($log->response))
                $html .= "<td class='s-size'><div>$fmtdate</div><div>($log->response)</div></td>";
            else
                $html .= "<td class='s-size'><div>$fmtdate</div></td>";
            if(is_multisite()){
                $sno = (!empty($log->blogid))? $log->blogid : '';
                $html .= "<td class='s-size'>$sno</td>";
            }
            $html .= "<td class='l-size'>";
            $html .= "<div class='over-hide' title='$dc_title'>$dc_title</div>";
            //表示都合により ' を置き換え
            $dc_req_data = str_replace("'", " ", $dc_req_data);
            if(!empty($files_data)){
                $act = '';
                foreach ($files_data as $key => $value) {
                    $act .= "$key "; 
                }
                $depth = 0;
                $dc_files_data = '';
                self::a_array_print($files_data, $dc_files_data, $depth);
                $html .= "<div class='over-hide'><div title='$dc_req_data'>$dc_req_url</div><div class='post-files' title='$dc_files_data'>(FILES) $act</div></div>";
            } else if(!empty($rest_result)){
                $depth = 0;
                $dc_rest_result = '';
                self::a_array_print($rest_result, $dc_rest_result, $depth);
                $stscode = '';
                if(!empty($rest_data['Status'])){
                    $stscode = " : Status {$rest_data['Status']['status']}";
                }
                //$html .= "<div class='over-hide'><div title='$dc_req_data'>$dc_req_url</div><div class='dialog-result' title='$dc_rest_result'>(REST Result)</div></div>";
                $html .= "<div class='over-hide'><div title='$dc_req_data'>$dc_req_url</div><a href='#window-{$idx}' class='dialog-result' data-index='$idx'>REST Result $stscode</a><div id='overlay-{$idx}' class='modal-overlay'></div><div id='window-{$idx}' class='modal-window'><textarea rows='15' class='large-text' readonly>$dc_rest_result</textarea></div></div>";
            } else if(!empty($ajax_result)){
                $html .= "<div class='over-hide'><div>$dc_req_url</div><a href='#window-{$idx}' class='dialog-result' data-index='$idx'>Ajax Post Data</a><div id='overlay-{$idx}' class='modal-overlay'></div><div id='window-{$idx}' class='modal-window'><textarea rows='15' class='large-text' readonly>$ajax_result</textarea></div></div>";
            } else {
                $html .= "<div class='over-hide'><div title='$dc_req_data'>$dc_req_url</div></div>";
            }
            
            $html .= '</td>';

            $pid = (!empty($log->postid))? $log->postid : '';
            $html .= "<td class='s-size'>$pid</td>";            
            
            $iptype = '';
            if($log->type == 'auto_block'){
                $iptype = 'blackbot-ip';
            }
            else if(!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] == $log->ip){
                $iptype = 'server-ip';
            }
            else if (!empty($log->bot)) {
                $iptype = 'bot-ip';
            }
            else if ($log->login == 100) {
                $iptype = 'login-ip';
            }
            $html .= "<td class='" . $iptype . " m-size'>$log->ip</td>";
            $html .= "<td>";
            $html .= "<div class='over-hide' title='$dc_referer'>$dc_referer</div>";
            $html .= "<div class='over-hide $ua_mobile' title='$dc_ua'>$dc_ua</div>";
            $html .= '</td>';
            $html .= '</tr>';
        }
        return $html;
    }
 
    //===========================================================================================
    //wp_ajax_apply_log_filter called function
    //===========================================================================================
    static function ajax_log_filter() {
        global $yasakani_cache;
        if (isset($_POST['log_time'])) {
            if (!current_user_can('activate_plugins'))
                wp_die(-1);
            check_ajax_referer("yasakani_log_filter");
            
            //$_POST['log_day'],$_POST['log_time'],$_POST['log_type'],$_POST['log_pid'],$_POST['log_ip']
            $localtm = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
            if(in_array($_POST['log_day'], array('-1 day', '-2 day', '-3 day', '-4 day', '-5 day', '-6 day'))) {
                $pastday = $localtm->modify( $_POST['log_day'] );
                $week = array("SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT");
                $w = (int)$pastday->format('w');
                $table = 'log_' . $week[$w];
                $day = $pastday->format("Y-m-d");
            } else {
                $table = 'log';
                $day = $localtm->format("Y-m-d");                
            }
            if(preg_match("#(\d{2}:\d{2})#", $_POST['log_time'], $match)){
                $tmfilter = $match[1];
            } else {
                $tmfilter = $localtm->format('H:i');
            }
            $typefilter = '';
            if (in_array($_POST['log_type'], array('', 'loggedin', 'nonloggedin', 'wp-login', 'wp-comments', 'xmlrpc', 'wp-json', 'wp-admin', 'wp_redirect','bot_block', '404', 'InvalidRequest','phpmailer','serverside') )) {
                $typefilter = $_POST['log_type'];
                $opt = get_option('yasakani_option', array());
                $opt['log_type'] = $typefilter;
                update_option('yasakani_option', $opt);
            }
            $pidfilter='';
            if(preg_match("#(\d{1,6})#", $_POST['log_pid'], $match))
                $pidfilter = $match[1];
            
            $ipfilter='';
            if(preg_match("#(\d{1,3}.\d{1,3}.\d{1,3}.\d{1,3})#", $_POST['log_ip'], $match))
                $ipfilter = $match[1];
            
            if ($yasakani_cache->is_enable('sqlite')) {
                $logpage = (isset($_POST['log_page']))? (int)$_POST['log_page'] : 0;
                $logs = self::get_log($day, $tmfilter, $logpage, $table, $typefilter, $pidfilter, $ipfilter);
                $html = self::log2html($logs);
                //wp_send_json_success($html);
                $response = array();
               	$response['success'] = true;
           		$response['data'] = $html;
                $response['msg']  = (!empty($logs))? '' : esc_html__('Specified log does not exist.', 'yasakani');
                $response['logpage'] = (string)$logpage;
                ob_end_clean(); //JS に json データを出力する前に念の為バッファクリア                
            	wp_send_json( $response );
            }
        } 
        wp_die(0);
    }
    
    static function cache_log_table($logs) {
        ?>  
        <div class="wrap_yasakani-log">
            <table id="yasakani-logtable-1" class="yasakani-table widefat">
                <?php if(is_multisite()) { ?>
                    <thead><tr><th class="m-size">Type</th><th class="s-size">Date</th><th class="s-size">Site</th><th class="l-size">Title / req_url</th><th class="s-size">PostID</th><th class="m-size">ip</th><th>referer / user_agent</th></tr></thead>
                <?php } else { ?>
                    <thead><tr><th class="m-size">Type</th><th class="s-size">Date</th><th class="l-size">Title / req_url</th><th class="s-size">PostID</th><th class="m-size">ip</th><th>referer / user_agent</th></tr></thead>
                <?php } ?>
                <tbody id="yasakani-log-1" class="yasakani-log-body">
                    <?php
                    $html = self::log2html($logs);
                    echo $html;
                    ?>
                </tbody>
            </table>
        </div>
        <div class="yasakani-filter-nav">
            <?php
            $ajax_nonce = wp_create_nonce('yasakani_log_filter');
            echo '<span class="hide-if-no-js"><a class="button yasakani-filter-opt-prev"  href="#yasakani-log-1" style="width: 80px;" onclick="Apply_log_filter(\'' . $ajax_nonce . '\',\'prev\');return false;" >' . esc_html__('< Prev', 'yasakani') . '</a>';
            echo '<input type="text" readonly id="yasakani-logpage" class="tiny-text" value="0" />';
            echo '<a class="button yasakani-filter-opt-next"  href="#yasakani-log-1" style="width: 80px;" onclick="Apply_log_filter(\'' . $ajax_nonce . '\',\'next\');return false;" >' . esc_html__('Next >', 'yasakani') . '</a></span>';
            ?>
        </div>
        <script type="text/javascript">
        YC_log_detail = function(){
            jQuery('.dialog-result').on('click',function(e){
                e.preventDefault();
                let idx = jQuery(this).data('index');
                let overlay = '#overlay-' + idx + ',#window-' + idx; 
                jQuery(overlay).fadeIn("slow");
                jQuery('#overlay-' + idx).on('click',function(){
                    jQuery(overlay).fadeOut("slow");
                });
            });
        };
        YC_log_detail();
        </script>       
        <?php
    }

    //===========================================================================================
    // 設定オプションページのメタボックス
    //===========================================================================================
    // Statistics
  	static function metabox_settings_stat($object, $metabox) {
        global $yasakani_cache;
        if(! $yasakani_cache->is_enable('sqlite') || empty($metabox['id']) || $metabox['id'] != 'metabox_yc_stat')
            return;

        if ($yasakani_cache->is_enable('log')) {
            //ボットリスト
            $botlist = self::get_bot();
            if(!empty($botlist)){
                self::botlist_table($botlist);
            }                
            $nowstat  = self::get_statistics('today');
            for($n=0; $n<6; $n++){
                $paststat[$n] = self::get_statistics('-' . $n+1 .' day');
            }
            ?>
            <div id="yasakani-stat-tabs">
                <ul>
                  <li><a href="#yasakani-now-stat" ><?php esc_html_e('Today', 'yasakani'); ?></a></li>
                  <?php
                  foreach($paststat as $idx => $pstat){
                      if(!empty($pstat)) {
                          echo '<li><a href="#yasakani-past-stat-' . $idx+1 . '" >';
                          switch ($idx+1) {
                            case 1: esc_html_e('Lastday', 'yasakani'); break;                              
                            case 2: esc_html_e('-2 day', 'yasakani'); break;
                            case 3: esc_html_e('-3 day', 'yasakani'); break;
                            case 4: esc_html_e('-4 day', 'yasakani'); break;
                            case 5: esc_html_e('-5 day', 'yasakani'); break;
                            case 6: esc_html_e('-6 day', 'yasakani'); break;
                          }
                          echo '</a></li>';
                      }                      
                  }
                  ?>
                </ul>
                <?php self::pv_chart_data($nowstat, $paststat); ?>
                <div id="yasakani-now-stat" style="display : none;">
                <?php
                if(!empty($nowstat)){
                    self::popularlist_table($nowstat['post']);
                    self::refererlist_table($nowstat['REF']);
                }
                ?>
                </div>                
                <?php
                foreach($paststat as $idx => $pstat){
                    echo '<div id="yasakani-past-stat-' . $idx+1 . '" style="display : none;">';
                    if(!empty($pstat)){
                        self::popularlist_table($pstat['post']);
                        self::refererlist_table($pstat['REF']);
                    }
                    echo '</div>';
                }
                ?>                
            </div>
            <script type='text/javascript' >
            jQuery(document).ready(function ($) {
                yasakani_stat_tabs(); function yasakani_stat_tabs() { $('#yasakani-stat-tabs').tabs({active: 0 }); }
                $( '#yasakani-stat-tabs' ).tabs({
                    activate: function(event, ui){
                        var tabnum = ui.newTab.index();
                        yasakani_pv_drawChart('yasakani_pv_chart', yk_pv_title[tabnum], yk_pv_data[tabnum]);
                    }
                })
            });
            </script>  
        <?php
        } else {
            echo '<p>' . esc_html__("It is displayed when 'Statistics' is enabled in 'Log Mode' of 'Cache Setting'", 'yasakani') . '</p>';
        }
    }
    
    /**
     * bot list get
     * 同一IPから指定回数以上アクセスのあるIPリストデータ（統計情報有効時のメインサイトのみ表示）
     */
    static function get_bot() {
        global $yasakani_cache;       
        $list = $yasakani_cache->sqldb->sql_get_results("SELECT * FROM bot WHERE count >= 20 ORDER BY count DESC", array(), SQLITE3_ASSOC);
        if(!empty($list)){
            //ログインIP, サーバーIPの除外処理  
            $loginip = $yasakani_cache->sqldb->sql_get_results("SELECT ip FROM log WHERE login = 100 GROUP BY ip;", array(), SQLITE3_ASSOC);
            $loginip = (!empty($loginip))? $loginip : array();
            if(!empty($_SERVER['SERVER_ADDR'])){
                $loginip[] = array('ip' => $_SERVER['SERVER_ADDR']);
            }
            $loginlist = '';
            if(!empty($loginip)){
                foreach($loginip as $item){
                    $loginlist .= "{$item['ip']},";
                }
            }
            $nlist = array();
            foreach ($list as $item){
                if ( empty($loginlist) || strpos($loginlist, $item['bip'] ) === false)
                    $nlist[] = $item;
            }
            $list = $nlist;
        }
        return $list;
    }
    
    //人気記事等の統計情報取得
    static function get_statistics($type='today') {        
        global $yasakani_cache;
        $table = 'stat';
        $stat_list = array();
        if(in_array($type, array('-1 day', '-2 day', '-3 day', '-4 day', '-5 day', '-6 day'))) {
            $localtm = new DateTime("now", new DateTimeZone(self::$setting['timezone']));
            $pastday = $localtm->modify( $type );
            $week = array("SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT");
            $w = (int)$pastday->format('w');
            $table = 'stat_' . $week[$w];
        }
        if($yasakani_cache->sqldb->is_table_exist( $table )){
            $obj = $yasakani_cache->sqldb->sql_get_row( "SELECT data FROM $table WHERE ROWID = 1");
            if(is_object($obj)){
                $stat_list = json_decode( $obj->data, true );
            }
        }
        if(!empty($stat_list)){
            //Hit
            if(empty($stat_list['Cache']['Hit']))
                $stat_list['Cache']['Hit'] = 0;
            if(empty($stat_list['Cache']['Non']))
                $stat_list['Cache']['Non'] = 0;
            //PV
            if(empty($stat_list['PV']['mobile']))
                $stat_list['PV']['mobile'] = 0;
            if(empty($stat_list['PV']['desktop']))
                $stat_list['PV']['desktop'] = 0;
            $pvh = array();
            for($n=0; $n<24; $n++){
                $pvh[$n] = (empty($stat_list['PV/h'][$n]))? 0 : $stat_list['PV/h'][$n];
            }
            $stat_list['PV/h'] = $pvh;

            //BOT
            if(empty($stat_list['bot']))
                $stat_list['bot'] = 0;
            if(empty($stat_list['bot_block']))
                $stat_list['bot_block'] = 0;
            $bth = array();
            for($n=0; $n<24; $n++){
                $bth[$n] = (empty($stat_list['bot/h'][$n]))? 0 : $stat_list['bot/h'][$n];
            }
            $stat_list['bot/h'] = $bth;
            
            //人気記事 Top50
            $post = array();
            if(!empty($stat_list['post'])){
                arsort($stat_list['post'], SORT_NUMERIC);
                $n = 0;
                foreach($stat_list['post'] as $bpid => $count){
                    $id = explode(',', $bpid);
                    $post[$n] = array('blog' =>$id[0], 'id' =>$id[1], 'PV' => $count);
                    if(++$n >= 50)
                        break;
                }                
            }
            $stat_list['post'] = $post;
            
            //リファラー 
            $ref = array();
            if(!empty($stat_list['REF'])){
                arsort($stat_list['REF'], SORT_NUMERIC);
                foreach($stat_list['REF'] as $r => $count){
                    $ref[] = array('ref' => $r, 'count' => $count);
                }
            }
            $stat_list['REF'] = $ref;
            
        }
        return $stat_list;
    }        
    
    static function botlist_table($lists) {
        ?>
        <div class="wrap_yasakani-log">
            <h3><?php esc_html_e('Lots of access from the same IP address.(excluding login users)', 'yasakani'); ?></h3>
            <table id="yasakani-bot" class="yasakani-table widefat">
                <thead><tr><th class="s-size">Times</th><th class="m-size">ip</th><th>user_agent</th></tr></thead>
                <tbody class="yasakani-stat-body">
                    <?php
                    //Time, ip, user_agent
                    $html = '';
                    $atblk = (class_exists('YC_SECURITY', false) && !empty(self::$setting['autoblocklist']))? self::$setting['autoblocklist'] : '';
                    foreach ($lists as $item) {
                        $html .= '<tr>';
                        $html .= "<td class='s-size'>{$item['count']}</td>";
                        $ip = $item['bip']; 
                        $ua = esc_html( $item['user_agent'] );
                        if (class_exists('YC_SECURITY', false) && ( YC_SECURITY::is_bot_blocking($ip, $ua) || strpos($atblk, $ip ) !== false)) {
                            $html .= "<td class='m-size log-botblock'>$ip</td>";
                            $html .= "<td class='over-hide log-botblock'>$ua</td>";
                        } else {
                            $html .= "<td class='m-size'>$ip</td>";
                            $html .= "<td class='over-hide'>$ua</td>";
                        }
                        $html .= '</tr>';
                    }
                    echo $html;
                    ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    static function sub_chart_data($cache, $pv, $pv_h, $bot_block, $bot_h) {
        $pvdata = null;
        if (!empty($pv)) {
            $pvcsv = array();
            $pvsum = $pv['mobile'] + $pv['desktop'];
            $pvmp = (!empty($pvsum)) ? (int)($pv['mobile'] / $pvsum * 100) : 0;
            $pvdp = (!empty($pvsum)) ? (int)($pv['desktop'] / $pvsum * 100) : 0;
            $pvhed = "Page View : $pvsum (Mobile $pvmp% Desktop $pvdp%)" . '<br>' .  PHP_EOL;

            if (!empty($cache)) {
                $csum = $cache['Hit'] + $cache['Non'];
                $chit = (!empty($csum)) ? (int)($cache['Hit'] / $csum * 100) : 0;
                $pvhed .= "Cache Hit : {$cache['Hit']} ($chit%)" . '<br>' . PHP_EOL;
            }
            if (!empty($bot_block)) {
                $pvhed .= "Bot Blocked : $bot_block";
            }

            $arpv[] = array("time", "PV", "Bot");
            $pvar = array();
            foreach ($pv_h as $h => $pv) {
                $pvar[$h] = (int) $pv;
            }
            $btar = array();
            foreach ($bot_h as $h => $bot) {
                $btar[$h] = (int) $bot;
            }
            for ($h = 0; $h < 24; $h++) {
                $arpv[] = array((string) $h, (int) $pvar[$h], (int) $btar[$h]);
            }
            $pvcsv = $arpv;

            $pvdata['hed'] = $pvhed;
            $pvdata['csv'] = $pvcsv;
        }
        return $pvdata;
    }

    static function pv_chart_data($nowstat, $paststat) {
        if(!empty($nowstat)){
            $pvdt = self::sub_chart_data($nowstat['Cache'], $nowstat['PV'], $nowstat['PV/h'], $nowstat['bot_block'], $nowstat['bot/h']);
            $pvhed[] = str_replace("<br>", '', $pvdt['hed']);
            $pvcsv[] = $pvdt['csv'];
            if(!empty($paststat)){
                foreach($paststat as $idx => $pstat){
                    if(!empty($pstat)){
                        $pvdt = self::sub_chart_data($pstat['Cache'], $pstat['PV'], $pstat['PV/h'], $pstat['bot_block'], $pstat['bot/h']);
                        $pvhed[] = str_replace("<br>", '', $pvdt['hed']);
                        $pvcsv[] = $pvdt['csv'];                        
                    }                    
                }
            }
            $pvhedjson = wp_json_encode($pvhed);
            $pviewjson = wp_json_encode($pvcsv);
            ?>
            <div class="wrap_yasakani_pv_chart">
                <div id="yasakani_pv_chart" style="width: 96%; height: 200px;" ></div>
                <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
                <script type='text/javascript' >
                    google.charts.load('current', {packages: ['corechart', 'bar']});
                    function yasakani_pv_drawChart(pv_id, pv_title, pv_data) {
                        var data = google.visualization.arrayToDataTable(pv_data);
                        var options = {title: pv_title, titleTextStyle: {color: '#1F79CF', fontSize: 14}, hAxis: {title: 'Time(h)'}, vAxis: {title: 'Page Views'}, backgroundColor: 'transparent'};
                        var chart = new google.visualization.ColumnChart(document.getElementById( pv_id ));
                        chart.draw(data, options);
                    }
                    var yk_pv_title = <?php echo $pvhedjson; ?>;
                    var yk_pv_data = <?php echo $pviewjson; ?>;
                    google.charts.setOnLoadCallback(yasakani_pv_drawChart.bind(this,'yasakani_pv_chart', yk_pv_title[0], yk_pv_data[0]));
                </script> 
            </div>
        <?php
        }
    }
    
    static function popularlist_table($lists) {
        ?>
        <div class="wrap_yasakani-log">
            <h4><?php echo esc_html__('Poplar Posts', 'yasakani'); ?></h4>
            <table id="yasakani-popular" class="yasakani-table widefat">
                    <?php if (is_multisite()) { ?>
                    <thead><tr><th class="s-size">PV</th><th class="s-size">Site</th><th class="s-size">PostID</th><th>Title</th></tr></thead>
                    <?php } else { ?>
                    <thead><tr><th class="s-size">PV</th><th class="s-size">PostID</th><th>Title</th></tr></thead>
                    <?php } ?>
                <tbody class="yasakani-stat-body">
                    <?php
                    //Title(permalink), PV
                    $html = '';
                    foreach ($lists as $item) {
                        $html .= '<tr>';
                        $html .= "<td class='s-size'>{$item['PV']}</td>";
                        $blog = $item['blog'];
                        $id = $item['id'];
                        $html .= "<td class='s-size'>$id</td>";            
                        if (is_multisite()) {
                            $html .= "<td class='s-size'>$blog</td>";

                            $current_blog_id = get_current_blog_id();
                            if ($blog != $current_blog_id)
                                switch_to_blog($blog);

                            $post = get_post($id);
                            $html .= (!empty($post))? '<td class="over-hide"><a href="' . get_permalink($id) . '">' . $post->post_title . '</a></td>' : '<td class="over-hide"></td>';

                            if ($blog != $current_blog_id)
                                switch_to_blog($current_blog_id);
                        } else {
                            $post = get_post($id);
                            $html .= (!empty($post))? '<td class="over-hide"><a href="' . get_permalink($id) . '">' . $post->post_title . '</a></td>' : '<td class="over-hide"></td>';
                        }
                        $html .= '</tr>';
                    }
                    echo $html;
                    ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    static function refererlist_table($reflists) {
        ?>
        <div class="wrap_yasakani-log">
            <h4><?php esc_html_e('Referer', 'yasakani'); ?></h4>
            <table id="yasakani-referer" class="yasakani-table widefat">
                <thead><tr><th class="s-size">Count</th><th class="l-size">Search/Page</th><th>Referer URL</th></tr></thead>
                <tbody class="yasakani-stat-body">
                    <?php
                    $html = '';
                    foreach ($reflists as $item) {
                        $search = '-';
                        if(preg_match( '#(q|qt|MT|Text|url)=([^&]+?)(&|$)#mu', $item['ref'], $match )){
                            $search = $match[2];
                            if(0 === strpos($search, 'http')){
                                $surl = urldecode($search);
                                if(preg_match( '#https?://.+?/(.+)?$#mu', $surl, $match )){
                                    $surl = $match[1];
                                }
                                $surl = esc_html($surl);
                                $search = '<a href="' . esc_url($search) . '" title="' . $surl . '">' . $surl . '</a>';
                            }
                        }
                        $reflink = '<a href="' . esc_url($item['ref']) . '">' . esc_html($item['ref']) . '</a>';
                        $html .= '<tr>';
                        $html .= "<td class='s-size'>{$item['count']}</td>";
                        $html .= "<td class='l-size over-hide'>$search</td>";
                        $html .= "<td class='over-hide'>$reflink</td>";
                        $html .= '</tr>';
                    }
                    echo $html;
                    ?>
                </tbody>
            </table>
        </div>
    <?php
    }    
}
