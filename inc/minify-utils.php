<?php
/**
 * JS, CSS optimize minify utility
 * 
 * Description: JS, CSS 最適化/縮小ユーティリティ
 * 
 * Author: enomoto@celtislab
 * Author URI: https://celtislab.net/
 * License: GPLv2
 * 
 */

defined( 'ABSPATH' ) || exit;

if(!class_exists('\celtislab\v3_2\CSS_tree_shaking')){
    require_once( __DIR__ . '/css-tree-shaking.php');
}
use celtislab\v3_2\CSS_tree_shaking;

class YC_minfy {
    
	function __construct() {}

    //CSS file 内の url() の相対パスを絶対パスに変換
    static function url_fpath2abs($base_url, $urlpath ) {
        $file = str_replace( "\\", "/", $urlpath);
        if (false === stripos( $file, "data:") && false === strpos( $file, "//")) {
            $file = trim($file);
            $file = trim($file, '"');
            $file = trim($file, "'");
            if (0 === strpos( $file, "/")){
                $file = trim($file, "/");
            }
            $file = "$base_url/$file";
            $file = str_replace("/./", '/', $file);
            while( preg_match('|\/[-_.!~*\'()a-zA-Z0-9;?:\@&=+\$,%#]+\/..\/|', $file)){
                $file = preg_replace('|\/[-_.!~*\'()a-zA-Z0-9;?:\@&=+\$,%#]+\/..\/|', '/', $file, 1);
            }
        }
        return $file;
    }

    //CSS file 内の import css の再帰読み込みと url() の相対パスを絶対パスへ変換
    static function css_import_urlpath_cnv($cssdata, $base_url ) {
        $cssdata = CSS_tree_shaking::simple_minify($cssdata);
        $base_url = str_replace( "\\", "/", $base_url);
        $cssdata = preg_replace_callback('|@import\surl\((.+?)\);|', function($matches) use(&$base_url) {
            $imp = $matches[0];
            if (false === stripos( $matches[1], "data:") && false === strpos( $matches[1], "//")) {
                $file = self::url_fpath2abs($base_url, $matches[1] );
                $fh = new SplFileObject( $file, "rb" );
                $impdata = $fh->fread($fh->getSize());
                $fh = null;                      
                if(!empty($impdata)){
                    $info = pathinfo( $file );
                    $ext = (!empty($info['extension']))? strtolower($info['extension']) : '';
                    if($ext == 'css'){
                        $impdata = self::css_import_urlpath_cnv($impdata, $info['dirname'] );
                    }
                    $imp = $impdata;  
                }
            }
            return $imp;
        }, $cssdata);
        //import 以外の url 絶対パス変換埋め込み
        $cssdata = preg_replace_callback('|url\((.+?)\)|', function($matches) use(&$base_url) {
            $url = $matches[0];
            if (false === stripos( $matches[1], "data:") && false === strpos( $matches[1], "//")) {
                $file = self::url_fpath2abs($base_url, $matches[1] );
                $url = "url('$file')";
            }
            return $url;
        }, $cssdata);

        return $cssdata;
    }

    // CSS, JS の最適化処理
    static function css_js_optimize($html, $option ) {
        global $post;
        $postid = (is_singular()) ? $post->ID : 0;
        if(!empty($postid)){
            $exclid     = in_array($postid, array_map("trim", explode(',', $option['exclude_tree_shaking_postid'])));
            $minify_css = (!empty($option['embed_css']) && !$exclid)? true : false;
            $exclid     = in_array($postid, array_map("trim", explode(',', $option['exclude_defer_js_postid'])));
            $defer_js   = (!empty($option['defer_js']) && !$exclid)? true : false;            
        } else {
            $minify_css  = $option['embed_css'];
            $defer_js    = $option['defer_js'];            
        }
        $core_css = (!empty($option['minify_core_block_css']))? true : false;        
        $pattern  = '';
        if (!empty($defer_js)) {
            $exclude_js = array();
            if(!empty($option['exclude_defer_js'])){
                $exclude_js = array_filter( array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", $option['exclude_defer_js']))), 'strlen');
            }
            $pattern = '<(?<script>script[^>]*?)>(?<jscode>.*?)</script';
        }
        if (!empty($minify_css)) {
            $shaking_css = array();
            if(!empty($option['tree_shaking_css'])){
                $shaking_css = array_filter( array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", $option['tree_shaking_css']))), 'strlen');
            }
            if($core_css){
                $shaking_css[] = 'wp-block-';
                $shaking_css[] = 'global-styles';
                $shaking_css[] = 'classic-theme-styles';
            }
            
            $exclude_css = array();
            if(!empty($option['exclude_tree_shaking_name'])){
                $exclude_css = array_filter( array_map("trim", explode("\n", str_replace( array( "\r\n", "\r" ), "\n", $option['exclude_tree_shaking_name']))), 'strlen');
            }
            if(!empty($shaking_css)) {
                if(!empty($pattern)){
                    $pattern .= '|';
                }
                $pattern .= '<(?<style>style[^>]*?)>(?<inlinecss>.*?)</style';             
                foreach ($shaking_css as $key) {
                    if(!empty($key)){
                        if(!empty($pattern)){
                            $pattern .= '|';
                        }
                        $pattern .= '<link[^>]+?' . preg_quote($key) . '[^>]*?>';                        
                    }
                }
            }
        }

        if(!empty($pattern)){
            $pattern = '#' . $pattern . '#su';
            
            $base_html  = $html;
            $base_head  = '';
            $apend_html = '';
            $pos = strpos($html, "</html>" );
            if($pos !== false){
                //html 閉じタグ以降があれば最適化から除外する
                $base_html = substr($html, 0, $pos + 8);
                $html_len  = strlen($html);
                if($html_len > $pos + 8){
                    $apend_html = substr($html, $pos + 8);
                }
            }
            if(preg_match( '#<head.*?<\/head>#s', $base_html, $head)){
                $base_head = $head[0];
            }
            $includes_url = includes_url();
            $content_url  = content_url();
            $inline_id4shaking = array();
            
            $nbase_html = $base_html;
            $nbase_html = preg_replace_callback( $pattern, function($matches) use(&$exclude_js, &$exclude_css, &$core_css, &$base_html, &$base_head, &$includes_url, &$content_url, &$inline_id4shaking) {
                if(!empty($matches['script'])){
                    //JSファイルに defer を付ける
                    $atrb = $matches['script'];
                    $code = $matches['jscode'];
                    if(strpos($atrb, 'src=') !== false && (strpos($atrb, 'async') === false && strpos($atrb, 'defer') === false)){
                        $exclude = false;
                        if(!empty($exclude_js)){
                            foreach ($exclude_js as $key) {
                                if (!empty($key) &&  stripos($atrb, $key ) !== false){
                                    $exclude = true;
                                    break;
                                }
                            }
                        }                
                        if(!$exclude){
                            $atrb = str_replace('script ', 'script defer ',  $atrb );
                        }
                    }        
                    return "<$atrb>" . $code . '</script';
                    
                } elseif(!empty($matches['style'])){
                    $atrb = $matches['style'];
                    $code = $matches['inlinecss'];
                    if( $core_css && preg_match('#id=[\'"](wp\-block\-[\w-]+inline\-css)[\'"]#', $atrb, $idcore)){
                        //wp core block inline css
                        $code = CSS_tree_shaking::extended_minify(CSS_tree_shaking::simple_minify($code), $base_html, $exclude_css, false);
                    } elseif( $core_css && preg_match('#id=[\'"](global\-styles\-inline\-css)[\'"]#', $atrb, $idcore)){
                        //wp core global styles inline css
                        $code = CSS_tree_shaking::extended_minify(CSS_tree_shaking::simple_minify($code), $base_html, $exclude_css, false);
                    } elseif( $core_css && preg_match('#id=[\'"](classic\-theme\-styles\-inline\-css)[\'"]#', $atrb, $idcore)){
                        //wp core classic theme styles inline css
                        $code = CSS_tree_shaking::extended_minify(CSS_tree_shaking::simple_minify($code), $base_html, $exclude_css, false);
                    } elseif( preg_match('#id=[\'"](.+?)[\'"]#', $atrb, $ida)){
                        //縮小対象CSSファイルに関連するインラインCSSも縮小
                        if(isset($inline_id4shaking["$ida[1]"])){
                            $code = CSS_tree_shaking::extended_minify(CSS_tree_shaking::simple_minify($code), $base_html, $exclude_css, false);
                        }
                    }
                    return "<$atrb>" . $code . '</style';
                    
                } else {
                    //サイト内ファイル( WP core, plugins, themes)対象 css tree shaking
                    $file = '';
                    if(preg_match('#href=[\'"](.+?)\.css#', $matches[0], $url)){                        
                        if(strpos($url[1], $includes_url) !== false){
                            $file = ABSPATH . 'wp-includes' . substr( $url[1], stripos($url[1], 'wp-includes') + 11) . '.css';
                        } else if(strpos($url[1], $content_url) !== false){
                            $file = WP_CONTENT_DIR . substr( $url[1], stripos($url[1], 'wp-content') + 10) . '.css';
                        }
                    }
                    if(!empty($file) && is_file($file)){
                        $before = $data = $after = '';

                        $fh = new SplFileObject( $file, "rb" );
                        $data = $fh->fread($fh->getSize());
                        $fh = null;                               
                        $info = pathinfo( $url[1] );
                        $data = preg_replace('/^\xEF\xBB\xBF/i', '', $data); //BOM mark clear
                        $data = self::css_import_urlpath_cnv($data, $info['dirname'] );

                        $idatr = '';
                        if( $core_css && preg_match('#id=[\'"](wp\-block\-[\w-]+\-css)[\'"]#', $matches[0], $ida)){
                            //wp core block css file : ここでの inline css チェック不要                        
                            $idatr = str_replace("'", '"', $ida[0]) . ' ';
                        } elseif( preg_match('#id=[\'"](.+?)[\'"]#', $matches[0], $ida)){
                            $idatr = str_replace("'", '"', $ida[0]) . ' ';
                            $customid = preg_replace('/\-css$/', '-inline-css', $ida[1]);
                            if( preg_match('#<style.*?id=[\'"]' . $customid . '[\'"].*?>(.*?)</style#su', $base_head, $match)){
                                $inline_id4shaking["$customid"] = 1;
                            }
                        }
                        $cls = '';
                        if( preg_match('#class=[\'"](.+?)[\'"]#', $matches[0], $mc)){
                            $cls = str_replace("'", '"', $mc[0]) . ' ';
                        }

                        // @import の入れ子読み込みは対象外
                        if (false === stripos( $data, "@import")) {
                            $before = '<style '. $idatr . $cls . '>';
                            $after = '</style>';
                            // css file をインライン化して縮小
                            $data = CSS_tree_shaking::extended_minify(CSS_tree_shaking::simple_minify($data), $base_html, $exclude_css, false);
                            if(empty($data)){
                                $data = "/* maybe unused : {$url[1]} */";
                            }
                            return $before . $data . $after;
                        }
                    }
                }
                return $matches[0];                
            }, $nbase_html);                        
            return $nbase_html . $apend_html;            
        }        
        return $html;
    }        
}