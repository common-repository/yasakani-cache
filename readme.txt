=== YASAKANI Cache ===
Contributors: enomoto celtislab
Tags: cache, performance, SQLite, CSS minify(tree shaking), auto_prepend_file, bot block, IP block, security, Brute Force, statistics
Requires at least: 5.5
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 3.8.2
Donate link: https://celtislab.net/en/wp-yasakani-file-diff-detect-restore/
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple ! Easy !! Ultra-high-speed !!!. Definitive edition of the page cache. And Bot and Security Utility.


== Description ==

This plug-in stores the HTML data that dynamic WordPress blog has been generated as a page cache by SQLite. After the page cache, it can respond to the request to the ultra-high speed by using a cache without starting the WordPress of processing.


= Simple Setup =

* Enable the page cache, select the cache expiration.


= Cache exclusion condition =

Users

* Login user

Pages

* Home/Front_page, Fixed Page, Post, Custom Post and WP embedded content card only. Other than this page does not cache.
* Page you want to exclude from the cache, you can specify from the edit screen of the meta box.
* Pages that are protected by a password does not cache.
* PHP error (excluding E_NOTICE, E_STRICT, E_DEPRECATED) occurred page does not cache.


= Cache Clear =

* Clear the cache of automatically corresponding post in the articles and editing changes and the like of the comment.
* The cache is a plugins and widgets such as a change is not clear. If you make these configuration changes, etc., should be cleared to use "Cache Clear" button.


= Log =

* When you activate the log, you can easily check the behavior and execution time of the cache. (slower only a little)
* SQLite database keeps logs for one week.


= To further speed-up =

Page cache processing of this plugin is processing in PHP and SQLite.
You can also use a faster Expert mode. To use Expert mode you need to edit 'php.ini' and add auto_prepend_file.
Or you can edit the .htaccess file and use mod_deflate and mod_expires or mod_pagespeed etc to make it faster.
If you do .htaccess edit, edit from the well studied. Do not forget that you back up your .htaccess file.

[plugin load filter](https://wordpress.org/plugins/plugin-load-filter/) is also recommended for speed you do not use the cache.

For more detailed information, there is an introduction page.

[Documentation](https://celtislab.net/en/wp-yasakani-cache/ "Documentation")

== Installation ==

1. Upload the `yasakani-cache` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress
3. Set up from `YASAKANI cache` to be added to the Settings menu of Admin mode.

Note

 * This plugin uses the sqlite3 module.
 * For Page Cache, "define (WP_CACHE, true);" definition to wp-config.php file. And to generate advanced-cache.php Drop-in file.

== Upgrade Notice ==

[Upgrade Notice] : Plug-in update must be done with plug-in deactivated.

== Screenshots ==

1. Yasakani Cache Settings
2. Exclude Setting
3. Maintenance
4. Request URL and Cache status Log.
5. Statistics (PV / Bot / Popular Post / Referer).
6. Security / Utilities Settings
7. File change detect and restore (Addon)

== Changelog ==

= 3.8.2 =
* 2024-5-15
* Changed sqlite operations from pdo_sqlite to sqlite3 module
* Change sqlite transaction processing to WAL mode
* Added integrity_check processing for sqlite database
* Changed Ajax(jquery) to fetch(js)
* Changed so that you can view logs for one week
* Change the attached data size of access log display
* Fixed CSS minification function of WP core block
* delete disable_block_separate_css option
* refactoring


= 3.7.3 =
* 2024-4-16
* WordPress6.5 tested
* Security measures
* refactoring
* css tree shaking updated


= 3.7.1 =
* 2023-8-17
* WordPress6.3 tested
* css tree shaking updated (amp-custom style unsuported)


= 3.7.0 =
* 2023-3-31
* WordPress6.2 tested
* PHP8.2 tested
* css tree shaking updated
* Fixed PHP notice error 


= 3.6.4 =
* 2022-11-2
* WordPress6.1 tested
* css tree shaking updated


= 3.6.3 =
* 2022-7-21
* Fixed PHP error when using PHP8.1


= 3.6.2 =
* 2022-7-20
* Add option - Shrink CSS for all WP core blocks(id=wp-block-xxxx) and embed inline in head
* Fixed a bug that CSS is loaded on Admin pages other than the plugin settings page


= 3.6.1 =
* 2022-6-21
* WP6.0 tested
* Add function to judge login input user as brute force attack. 
* Excluded in iframe due to CSS/JS optimization error in customizer


= 3.5.0 =
* 2022-4-27
* Add support file restore by yasakani file diff detect and restore addon
* Other minor fixes


= 3.4.0 =
* 2022-2-10
* WP5.9 tested
* PHP8.1 tested
* CSS tree shaking - Exclude CSS pseudo classes (:not :where :is :has) from tree seeking.
* CSS tree shaking - Removed the option to remove unused CSS variable definitions. However, it is automatically implemented for amp-custom style.
* Added option to disable WordPress core block style separate load function.


= 3.3.0 =
* 2021-11-25
* Changed Image optimizer from add-on to regular plugin format, so clean up code that is no longer needed


= 3.2.0 =
* 2021-9-24
* change Removed file-change-monitoring from standard features and separated it as an addon feature  


= 3.1.0 =
* 2021-7-26
* WP5.8 tested
* CSS tree shaking - Support for partial match selectors for id and css attributes.
* CSS tree shaking - Added option to remove unused CSS variable definitions.
* CSS tree shaking - Added per-page disabling option feature.
* CSS tree shaking - Performance improvement.
* Fixed : The log detail dialog was sometimes not displayed
* Abolished : HTML minify


= 3.0.1 =
* 2021-2-5
* WP5.6 tested
* PHP8 tested
* Added support for Image Optimizer Addon
* Extensive Code Refactoring
* Replaced with SplFileObject as a workaround for sites that cannot use file_get_contents
* Access log: Change to allow separate search for phpmailer in HTTP_Request
* rename used in maintenance hard reset may fail depending on operating environment, so copy is now used as a fallback
* Other minor fixes

= 2.6.1 =
* 2020-4-20
* Fixed a bug where Hard Reset was not working.
* Fixed SQL error in per-page cache clearing metabox. 

= 2.6.0 =
* 2020-4-8
* Changed cache processing timing so that it can be used with AMP plugin(https://wordpress.org/plugins/amp/).
* CSS optimization only for CSS tree shaking (no longer preload) 
* added option to shrink AMP page for amp-custom style
* WP version 5.1 or higher is required

= 2.5.3 =
* 2020-4-3
* Changed CSS asynchronous loading from preload to media attribute rewriting (https://www.filamentgroup.com/lab/load-css-simpler/)

= 2.5.2 =
* 2020-3-6
* fix : CSS tree shaking bug: Converting URL relative path to absolute path in CSS files.

= 2.5.1 =
* 2020-2-21
* add : Judgment of cache exclusion page from URL substring.
* add : Add / Delete / Update file change monitoring (size / update date / permission)
* fix : There was a case where the cache was not updated due to a problem in the cache expiration date judgment process.
* fix : Use wp_timezone_string () function to get timezone data.

= 2.4.1 =
* 2019-11-29
* fix : Bug fix that the definition using "not" in css selector was deleted in css tree shaking.

= 2.4.0 =
* 2019-10-9
* Add Rewrite protection for WordPress address (siteurl) / Site address (home) / other options.

= 2.3.1 =
* 2019-7-26
* Add callback function information to wp-cron execution log
* Add REST API Requests and Results to log

= 2.3.0 =
* 2019-7-11
* Added CSS Tree Shaking feature

= 2.2.5 =
* 2019-5-8
* fix : Exclusion process when css preload is specified.
* fix : Add 'rest_route' as well as 'wp-json' to identify access log of REST API request. 

= 2.2.4 =
* 2019-4-4
* fix : php error in add_autoblocklist function 

= 2.2.3 =
* 2019-4-1
* fix : log filter
* fix : php error due to static declaration missing 

= 2.2.2 =
* 2019-3-28 
Refactored the code and Add gravatar cache(beta).

= 2.0.5 =
* 2019-3-8
wp5.1 tested and Add post id item to log etc.

= 2.0.4 =
* 2018-10-11
Add option to exclude JavaScript from asynchronous load defer.

= 2.0.2 =
* 2018-8-14
changed : Since the gutenberg editor accesses 'wp-includes/js/tinymce/wp-tinymce.php', Exclude this as not to be treated as a zero day attack.
fix : log type mode select bug

= 2.0.1 =
* 2018-8-1
* Changed : cache to gzip format to reduce cache data capacity and speed up.
* Changed : asynchronous loading of CSS, JS files.
* Changed : ob_start processing when saving cache data. (Measures against error of global variable in template file)
* Changed : setting page user interface (added maintenance function)
* Abolished : APCu mode


= 1.4.5 =
* 2018-5-7
* Added small CSS, JS inline embedding and HTML Minify function for page speed improvement.
* fix : cache clear function.
* fix : Measures against invalid request URL.

= 1.3.2 =
* 2018-4-11
* fix : As posting edit screen display was sometimes slowed down, cache status display is limited when post status is "publish". 

= 1.3.1 =
* 2018-3-30
* Added cache clear button for each post.
* Change fixed the priority of the filter hook of caching processing to 99999 because the short code might not be executed.

= 1.2.1 =
* 2018-2-16
* fix PHP error occurred in log mode

= 1.2.0 =
* 2017-12-28
* Added Log display filter. And added a record of events such as wp-redirect and Server Side HTTP_Request.

= 1.1.2 =
* 2017-11-29
* Added super fast expert mode using auto_prepend_file (Only when php.ini can be edited)
* Added zero-day attack blocking function (Only when php.ini can be edited)
* Added automatic cache clear processing for bbPress forum, topic, reply.

= 1.0.0 =
* 2017-08-02
* Add Cache HTTP headers with page content. 
* Add Auto block IP mode (simple & fast wordpress security : NULL byte / Directory traversal / Command injection / Brute Force ... )
* Save POST data to log.
* Change Fixed to easy-to-see log display.

= 0.9.8 =
* 2017-06-20
* WordPress 4.8 support
* Add simple access statistics mode.
* Change log display to main site only. Fixed to easy-to-see log display.

= 0.9.6 =
* 2017-3-27
* Change Configuration change of setting table.
* Change Configuration change of log table.
* Addition of bot block function as optional utility function.
* Added URL replacement function of images and links in content that can be used when migrating site URL as optional utility function.

= 0.9.1 =
* 2016-09-12
* fix PHP Error

= 0.9.0 =
* 2016-09-09
* APC/APCu support(Beta test). You can specify the "SQLite + APC/APCu" as cache storage in case "APC/APCu" is enabled. 

= 0.8.3 =
* 2016-09-02
* change Log display item(REQUEST_URI, HTTP_REFERER) urldecode 
* fix Status of the attachment, such as an image was not able to cash in the case of 'inherit'
* fix Processing at the time of invalid cache in a multsite    

= 0.8.2 =
* 2016-08-23  
* fix WP_CACHE define replacement process 
* fix DB file path (wp-content/yasakani-cache/yasakani_cache.db).
* add Apache server .htaccess installation for direct access forbidden to the DB file.
* add Cache Expiration setting 4 hours
* add setting form autocomplete="off" for firefox

= 0.8.1 =
* 2016-08-19  Release

= 0.8.0 =
* 2016-08-17  wordpress.org plugin submit

= 0.7.0 =
* 2016-07-20  Beta Version
 
