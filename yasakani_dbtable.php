<?php
/*
  File Name: yasakani_dbtable.php
  Description: Simple, Easy, Ultra-high-speed Page Cache Powered by SQLite
  Author: enomoto@celtislab
  License: GPLv2
*/

if(!defined( 'ABSPATH' ) && !defined( 'YASAKANI_CACHE_DB' ))
    exit;

if(!class_exists('\celtislab\v1_2\Celtis_sqlite')){
    require_once( __DIR__ . '/inc/sqlite-utils.php');
}
use celtislab\v1_2\Celtis_sqlite;

class YC_TABLE {

    //ver 0.95 log_putp の使用を止め, timezone, log_backup, botblocklist 追加して format = 2 へ変更
    //ver 0.97 log table に bot, setting table に loginblock, autoblock, autoblocklist, botwhitelist(reserve) 追加して format = 3 へ変更
    //ver 0.98 bot table, stat table 追加, setting table に bot_key 追加して format = 4 へ変更
    //ver 0.99 log table に method, req_data 追加（但しパスワード情報は保存しない）bot table に date_404, date_restrict 追加 format = 5 へ変更
    //ver 1.00 content table に m_header, d_header 追加 format = 6 へ変更
    //ver 1.10 logged_key table 追加, setting table に sitekey, zerodayblock, trustedfile 追加 format = 7 へ変更
    //ver 1.20 log 表示にフィルター機能を追加したので attack_log テーブル廃止
    //ver 2.0.0 setting table に maintenance 追加 format = 8 へ変更
    //ver 2.4.0 setting table に siteurlprotect, protectlist 追加 format = 9 へ変更
    //ver 2.5.0 content table に m_modified, d_modified, m_expire, d_expire 追加, filemonitor table 追加して format = 10 へ変更
    //ver 3.2.0 ファイル変更監視機能をアドオンに分けたので filemonitor テーブル廃止
    //ver 3.5.0 trustedfile に /yasakani-file-diff-detect-addon/restore-wpcore.php 追加して format = 11 へ変更
    //ver 3.6.0 setting table に block_user 追加して format = 12 へ変更
    //ver 3.8.0 setting table に notice 追加, content table の mobile, desktop のデータ型を text BLOB に変更して format = 13 へ変更
    
    const CREATE_SETTING_TABLE = "CREATE TABLE setting (
                format text DEFAULT '0' NOT NULL,
                maintenance datetime,
                log_mode int DEFAULT 0 NULL,
                timezone text DEFAULT 'utc' NOT NULL,
                log_backup date DEFAULT '0000-00-00' NOT NULL,
                bot_key text DEFAULT '' NOT NULL,
                loginblock int DEFAULT 0 NULL,
                block_user text DEFAULT '' NOT NULL,
                autoblock int DEFAULT 0 NULL,
                autoblocklist text DEFAULT '' NOT NULL,
                botblocklist text DEFAULT '' NOT NULL,
                botwhitelist text DEFAULT '' NOT NULL,
                sitekey text DEFAULT '' NOT NULL,
                zerodayblock int DEFAULT 0 NULL,
                trustedfile text DEFAULT '' NOT NULL,
                siteurlprotect int DEFAULT 0 NULL,
                protectlist text DEFAULT '' NOT NULL,
                notice text DEFAULT '' NOT NULL
                );";
    
    const CREATE_CONTENT_TABLE = "CREATE TABLE content (
                key text UNIQUE NOT NULL,
                req_url text NOT NULL,
                blogid int NOT NULL, 
                postid int NOT NULL, 
                title text,
                mobile BLOB,
                desktop BLOB,
                m_header text,
                d_header text,
                m_modified text,
                d_modified text,
                m_expire datetime,
                d_expire datetime
                );";
    
    const CREATE_LOG_TABLE = "CREATE TABLE log (
                date datetime,
                response real,
                type text,
                req_url text,
                method text,
                req_data text,
                blogid int NOT NULL, 
                postid int NOT NULL, 
                host text,
                path text,
                query text,
                title text,
                user_agent text,
                referer text,
                refhost text,
                refpath text,
                refquery text,
                ip text,
                error text,
                login int NOT NULL,
                bot int NOT NULL
                );";
    
    const CREATE_BOT_TABLE = "CREATE TABLE bot (
                bip text DEFAULT '' NOT NULL,
                user_agent text,
                count int DEFAULT 0 NOT NULL,
                count_404 int DEFAULT 0 NOT NULL,
                count_restrict int DEFAULT 0 NOT NULL,
                count_login int DEFAULT 0 NOT NULL,
                date_404 datetime,
                date_restrict datetime
                );";
    
    const CREATE_STAT_TABLE = "CREATE TABLE stat (
                data text DEFAULT '' NOT NULL
                );";
    
    const CREATE_LOGGED_KEY_TABLE = "CREATE TABLE logged_key (
                sitekey text '' NOT NULL,
                userkey text '' NOT NULL,
                role text DEFAULT '' NOT NULL
                );";    

    public function __construct() {
    }

    //ボット判定デフォルトリスト
    static function bot_key_default() {
        $bot_key = 'google,facebook,bot,slurp,spider,crawl,wget,python,java,perl,curl,geturl';
        return $bot_key;
    }   

    //PHPスクリプトのゼロデイ攻撃判定対象から除外するPHPファイルのホワイトリスト
    //日本語環境用の特別対応：プラグイン wp-multibyte-patch の wplink.php へのアクセス許可を追加
    //WPコア復元用の特別対応：プラグイン yasakani-file-diff-detect の restore-wpcore.php へのアクセス許可を追加
    static function trasted_file_default() {
        $trasted_default = "/wp-signup.php\n/wp-activate.php\n/wp-mail.php\n/wp-comments-post.php\n/wp-trackback.php\n/wp-cron.php\n/xmlrpc.php\n/wp-admin/admin-ajax.php\n/wp-admin/load-scripts.php\n/wp-admin/load-styles.php\n";
        if(defined('WP_CONTENT_DIR')){
            $plugin_dir = WP_CONTENT_DIR . '/plugins';
        } else if(defined('YASAKANI_CACHE_DB')) {
            $plugin_dir = YA_CONTENT_DIR . '/plugins';
        }
        if(isset($plugin_dir)){
            $trasted_default .= "/wp-content/plugins/yasakani-file-diff-detect-addon/restore-wpcore.php\n";
            if(is_file( $plugin_dir . '/wp-multibyte-patch/wplink.php' )){
                $trasted_default .= "/wp-content/plugins/wp-multibyte-patch/wplink.php\n";
            }            
        }
        return $trasted_default;
    }

    /**
     * SQLite DB Table Create
     *
     */
    static function table_create($sqldb) {
        if($sqldb->is_table_exist('setting')){
            return;
        }

        if($sqldb->beginTransaction()){
            try {
                //Option Setting Table
                $sqldb->sql_exec( self::CREATE_SETTING_TABLE );
                $timezone = wp_timezone_string();
                $localtm = new DateTime("now", new DateTimeZone($timezone));
                $backupday = $localtm->format("Y-m-d");
                $bot_key_default = self::bot_key_default();
                $trasted_default = self::trasted_file_default();
                $sqldb->sql_exec("INSERT INTO setting (format, maintenance, log_mode, timezone, log_backup, bot_key, trustedfile) VALUES ( ?, ?, ?, ?, ?, ?, ? )", array( YC_MUSE::FORMAT_VER, 0, 0, $timezone, $backupday, $bot_key_default, $trasted_default));

                //Content Cache Table
                $sqldb->sql_exec( "DROP TABLE IF EXISTS content" );
                $sqldb->sql_exec( self::CREATE_CONTENT_TABLE );
                $sqldb->sql_exec( "CREATE UNIQUE INDEX key ON content (key);" );
                $sqldb->sql_exec( "CREATE INDEX postid ON content (blogid,postid);" );

                //Log Table
                $sqldb->sql_exec( "DROP TABLE IF EXISTS log" );
                $sqldb->sql_exec( self::CREATE_LOG_TABLE );
                $sqldb->sql_exec( "CREATE INDEX date ON log (date);" );
                $sqldb->sql_exec( "CREATE INDEX lpostid ON log (blogid,postid);" );
                $sqldb->sql_exec( "CREATE INDEX ip ON log (ip);" );

                //Bot sampling table
                $sqldb->sql_exec( "DROP TABLE IF EXISTS bot" );
                $sqldb->sql_exec( self::CREATE_BOT_TABLE );
                $sqldb->sql_exec( "CREATE INDEX bip ON bot (bip);" );

                //Stat table
                $sqldb->sql_exec( "DROP TABLE IF EXISTS stat" );
                $sqldb->sql_exec( self::CREATE_STAT_TABLE );

                //logged_key table
                $sqldb->sql_exec( "DROP TABLE IF EXISTS logged_key" );
                $sqldb->sql_exec( self::CREATE_LOGGED_KEY_TABLE );

                $sqldb->commit();
            } 
            catch (Exception $e) {
                global $yasakani_cache_action;
                $errmsg = $e->getMessage();
                $yasakani_cache_action['db_error'] = $errmsg;
                $sqldb->rollback();
                return;
            }            
        }
    }        

    //Setting テーブルアップデート（フォーマットバージョン更新時に実行） 
    static function table_update( $setting, $sqldb ) {
        if($sqldb->beginTransaction()){
            try {
                //setting テーブルのフォーマットバージョン更新（他の設定値は setting データで更新）
                $sqldb->sql_exec( "DROP TABLE IF EXISTS setting;" );
                $sqldb->sql_exec( self::CREATE_SETTING_TABLE );
                $sqldb->sql_exec("INSERT INTO setting (format, maintenance, log_mode, timezone, log_backup, bot_key, loginblock, block_user, autoblock, autoblocklist, botblocklist, botwhitelist, sitekey, zerodayblock, trustedfile, siteurlprotect, protectlist, notice) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )",
                        array(YC_MUSE::FORMAT_VER, $setting['maintenance'],  (int)$setting['log_mode'], $setting['timezone'], $setting['log_backup'], $setting['bot_key'], (int)$setting['loginblock'],  $setting['block_user'], (int)$setting['autoblock'], $setting['autoblocklist'], $setting['botblocklist'], $setting['botwhitelist'], $setting['sitekey'], $setting['zerodayblock'], $setting['trustedfile'], $setting['siteurlprotect'], $setting['protectlist'], $setting['notice']));

                $sqldb->sql_exec( "DROP TABLE IF EXISTS content" );
                $sqldb->sql_exec( self::CREATE_CONTENT_TABLE );
                $sqldb->sql_exec( "CREATE UNIQUE INDEX key ON content (key);" );
                $sqldb->sql_exec( "CREATE INDEX postid ON content (blogid,postid);" );

                if(! $sqldb->is_table_exist('bot')){
                    $sqldb->sql_exec( self::CREATE_BOT_TABLE );
                    $sqldb->sql_exec( "CREATE INDEX bip ON bot (bip);" );
                }
                if(! $sqldb->is_table_exist('stat')){
                    $sqldb->sql_exec( self::CREATE_STAT_TABLE );
                }
                if(! $sqldb->is_table_exist('logged_key')){
                    $sqldb->sql_exec( self::CREATE_LOGGED_KEY_TABLE );
                }
                $sqldb->commit();               

            } catch (Exception $e) {
                $sqldb->rollback();
                return false;
            }            
        }
    }

    //メンテナンス
    //maintenance mode set ($mode = 0 or 1)
    static function set_maintenance_mode($mode) {
        global $yasakani_cache;
        $musetting = YC_MUSE::get_setting();
        if(!empty($mode)){
            $now = new DateTime("now", new DateTimeZone('utc'));
            if(empty($musetting['maintenance']) || $now > ($exp = new DateTime($musetting['maintenance'], new DateTimeZone('utc')))){
                //5min maintenance mode(テーブルにメンテナンスモードが不正に残っても一定時間経過後には自動解除)
                $expd = $now->modify("+300 second");
                $musetting['maintenance'] = $expd->format('Y-m-d H:i:s');
                $yasakani_cache->sqldb->sql_exec("UPDATE setting SET maintenance = ?", array( $musetting['maintenance']));
            }
        } else {
            if(!empty($musetting['maintenance'])){
                $yasakani_cache->sqldb->sql_exec("UPDATE setting SET maintenance = NULL");
            }
        }
    }
    
    //データベースに問題がある時の再生成
    static function reset_create() {
        global $yasakani_cache;
        global $yasakani_cache_action;        
        $sqldb = $yasakani_cache->sqldb;
        if(!empty($sqldb)){
            $setting = YC_MUSE::get_setting();    //現在の設定値取得
            if(! $yasakani_cache->is_enable('maintenance')){

                self::set_maintenance_mode(1);
                $tmpdbfile = WP_CONTENT_DIR . '/yasakani-cache/yasakani_cache.db-tmp';
                if (file_exists($tmpdbfile)){
                    wp_delete_file($tmpdbfile);
                }

                //new db file create
                $tmpdb = new Celtis_sqlite( $tmpdbfile );
                if(!empty($tmpdb) && $tmpdb->is_open()){
                    self::table_create($tmpdb);
                    if($tmpdb->is_table_exist('setting')){
                        $setting['maintenance'] = 0;
                        $setting['notice'] = '';
                        //ここでは WAL モードは使用しない
                        $tmpdb->command("PRAGMA journal_mode = DELETE");
                        if($tmpdb->beginTransaction('IMMEDIATE')){
                            try {
                                $tmpdb->sql_exec("UPDATE setting SET format = ?, maintenance = ?, log_mode = ?, timezone = ?, log_backup = ?, bot_key = ?, loginblock = ?, block_user = ?, autoblock = ?, autoblocklist = ?, botblocklist = ?, botwhitelist = ?, sitekey = ?, zerodayblock = ?, trustedfile = ?, siteurlprotect = ?, protectlist = ?, notice = ?",
                                    array(YC_MUSE::FORMAT_VER, $setting['maintenance'], (int)$setting['log_mode'], $setting['timezone'], $setting['log_backup'], $setting['bot_key'], (int)$setting['loginblock'], $setting['block_user'], (int)$setting['autoblock'], $setting['autoblocklist'], $setting['botblocklist'], $setting['botwhitelist'], $setting['sitekey'], $setting['zerodayblock'], $setting['trustedfile'], $setting['siteurlprotect'], $setting['protectlist'], $setting['notice']));
                                $tmpdb->commit();
                            } catch (Exception $e) {
                                $errmsg = $e->getMessage();
                                $yasakani_cache_action['db_error'] = $errmsg;
                                $tmpdb->rollback();
                            }                            
                        }
                    } else {
                        $yasakani_cache_action['db_error'] = "database file re-create error";                                            
                    }
                    $tmpdb->close();
                    
                } else {
                    $yasakani_cache_action['db_error'] = "database file re-create error";                    
                }
                self::set_maintenance_mode(0);                    

                if(empty($yasakani_cache_action['db_error'])){
                    $dbfile = WP_CONTENT_DIR . '/yasakani-cache/yasakani_cache.db';                    
                    //rename する為にデータベースを閉じる
                    //接続を閉じるときに -wal -shm ファイルはDB本体に反映されて削除される                                       
                    $sqldb->close();
                    try {
                        //rename ならファイルデータのコピーせずにディレクトリ情報の書き換えだけなので安全？
                        if (!rename($tmpdbfile,$dbfile)) {
                            //rename できない場合は copy
                            if(!copy($tmpdbfile,$dbfile)){
                                $yasakani_cache_action['db_error'] = "database file re-create error";                                
                            }
                        }
                    } catch (Exception $e) {
                        $errmsg = $e->getMessage();
                        $yasakani_cache_action['db_error'] = "database file re-create error";
                    }                  
                }
                if (file_exists($tmpdbfile)){
                    wp_delete_file($tmpdbfile);
                }
            }
        }
    }
}
