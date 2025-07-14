<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}

// add_action and add_filter will write to this
$GLOBALS['wp_actions_filters'] = array(
    'actions' => array(),
    'filters' => array(),
);

$GLOBALS['wpFunctionArgs'] = array();

$GLOBALS['wpReturnVals'] = array(
    'blogInfo' => array(
        'version' => '6.7.2',
    ),
    'locale' => 'en_US',
    'option' => array(
        'page_for_posts' => 42,
        'page_on_front' => 69,
        'show_on_front' => 'page',
    ),
    'post_type_object' => (object) array(
        'labels' => (object) array(
            'singular_name' => 'bean',
        ),
    ),
    'settings_errors' => array(),
    'type' => 'page',
);

// mock actual wordpress globals
$GLOBALS['wp_object_cache'] = (object) array(
    'cache' => array(),
    'cache_hits' => 69,
    'cache_misses' => 42,
);
$GLOBALS['template'] = __DIR__ . '/template.php';
$GLOBALS['wp'] = (object) array(
    'request' => '/ding/dong/',
    'query_string' => 'foo=bar&baz=qux',
    'matched_rule' => 'some_rule',
    'matched_query' => 'some_query',
);
$GLOBALS['wp_query'] = new WpQuery();
$GLOBALS['wpdb'] = new WpDb();

function wp_reset_mock()
{
    $GLOBALS['wp_actions_filters'] = array(
        'actions' => array(),
        'filters' => array(),
    );
    $GLOBALS['wpFunctionArgs'] = array();
}

function is_404() { return $GLOBALS['wpReturnVals']['type'] === '404';}
function is_archive() { return $GLOBALS['wpReturnVals']['type'] === 'archive'; }
function is_attachment() { return $GLOBALS['wpReturnVals']['type'] === 'attachment'; }
function is_author() { return $GLOBALS['wpReturnVals']['type'] === 'author'; }
function is_category() { return $GLOBALS['wpReturnVals']['type'] === 'category'; }
function is_date() { return $GLOBALS['wpReturnVals']['type'] === 'date'; }
function is_front_page() { return $GLOBALS['wpReturnVals']['type'] === 'front_page'; }
function is_home() { return $GLOBALS['wpReturnVals']['type'] === 'home'; }
function is_page() { return $GLOBALS['wpReturnVals']['type'] === 'page'; }
function is_paged() { return $GLOBALS['wpReturnVals']['type'] === 'paged'; }
function is_search() { return $GLOBALS['wpReturnVals']['type'] === 'search'; }
function is_singl() { return $GLOBALS['wpReturnVals']['type'] === 'singl'; }
function is_tag() { return $GLOBALS['wpReturnVals']['type'] === 'tag'; }
function is_tax() { return $GLOBALS['wpReturnVals']['type'] === 'tax'; }

function admin_url($path = '', $scheme = 'admin')
{
    return '/wp-admin/' . $path;
}

function add_action($name, $callable)
{
    $GLOBALS['wp_actions_filters']['actions'][$name][] = $callable;
}

function remove_action($name, $callable)
{
    foreach ($GLOBALS['wp_actions_filters']['actions'][$name] as $key => $action) {
        if ($action === $callable) {
            unset($GLOBALS['wp_actions_filters']['actions'][$name][$key]);
        }
    }
    $GLOBALS['wp_actions_filters']['actions'][$name] = \array_values($GLOBALS['wp_actions_filters']['actions'][$name]);
}

function add_filter($name, $callable)
{
    $GLOBALS['wp_actions_filters']['filters'][$name][] = $callable;
}

function remove_filter($name, $callable)
{
    foreach ($GLOBALS['wp_actions_filters']['filters'][$name] as $key => $filter) {
        if ($filter === $callable) {
            unset($GLOBALS['wp_actions_filters']['filters'][$name][$key]);
        }
    }
    $GLOBALS['wp_actions_filters']['filters'][$name] = \array_values($GLOBALS['wp_actions_filters']['filters'][$name]);
}

function get_bloginfo($show = 'name', $filter = 'raw')
{
    return isset($GLOBALS['wpReturnVals']['bloginfo'][$show])
        ? $GLOBALS['wpReturnVals']['bloginfo'][$show]
        : '';
}

function get_locale()
{
    return $GLOBALS['wpReturnVals']['locale'];
}

function get_option($name)
{
    return isset($GLOBALS['wpReturnVals']['option'][$name])
        ? $GLOBALS['wpReturnVals']['option'][$name]
        : null;
}

function get_post_type_object($name)
{
    return $GLOBALS['wpReturnVals']['post_type_object'];
}

function is_wp_error($thing)
{
    return false;
}

function register_setting($option_group, $option_name, $args = array())
{
}

function add_options_page()
{
    $GLOBALS['wpFunctionArgs']['add_options_page'][] = func_get_args();
}

function add_settings_section($id, $title, $callback, $page, $args = array())
{
    $GLOBALS['wpFunctionArgs']['add_settings_section'][] = array(
        'args' => $args,
        'callback' => $callback,
        'id' => $id,
        'page' => $page,
        'title' => $title,
    );
}

function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {
    $GLOBALS['wpFunctionArgs']['add_settings_field'][] = array(
        'args' => $args,
        'callback' => $callback,
        'id' => $id,
        'page' => $page,
        'section' => $section,
        'title' => $title,
    );
}

function get_settings_errors($setting = '', $sanitize = false)
{
    return $GLOBALS['wpReturnVals']['settings_errors'];
}

/**
 * Gets the path to a plugin file or directory, relative to the plugins directory
 *
 * @param string $file
 *
 * @return string
 */
function plugin_basename($file)
{
    $rootDir = $_SERVER['DOCUMENT_ROOT'] ?: \dirname(TEST_DIR);
    $file = \str_replace('\\', '/', $file);
    $file = \str_replace($rootDir, '', $file);
    $file = \ltrim($file, '/');
    return $file;
}

/**
 * Only meant to sorta mimic the real thing
 *
 * @param string $page Page slug
 *
 * @return void
 */
function do_settings_sections($page)
{
    // echo 'do_settings_sections(' . $page . ')' . "\n";
    foreach ($GLOBALS['wpFunctionArgs']['add_settings_section'] as $section) {
        echo "\n" . '<h2>' . $section['title'] . '</h2>' . "\n\n";
        foreach ($GLOBALS['wpFunctionArgs']['add_settings_field'] as $field) {
            if ($field['page'] === $section['page'] && $field['section'] === $section['id']) {
                echo ($field['args']['label_for']
                    ? '<label for="' . $field['args']['label_for'] . '">' . $field['title'] . '</label>'
                    : $field['title']
                ) . "\n";
                echo $field['callback']() . "\n";
            }
        }
    }
}

function settings_fields($option_group)
{
    echo 'settings_fields(' . $option_group . ')' . "\n";
}

function wp_get_development_mode()
{
    return '';
}

function wp_get_environment_type()
{
    return 'production';
}

class WpDb
{
    public $dbname = 'some_db';
    public $dbhost = 'hosty';
    public $dbpassword = 'sesame';
    public $dbuser = 'user';

    public $dbh;

    public function __construct()
    {
        $this->dbh = new Dbh();
    }

    public function parse_db_host($host)
    {
        return array(
            'host' => 'hosty',
            'port' => 3306,
            'socket' => '/path/to/socket',
            'isIpv6' => false,
        );
    }
}

class Dbh
{
    public $host_info = array();
    public $server_info = '5.7.21';
    public function stat() {
        return 'Uptime: 123456  Threads: 1  Questions: 1234  Slow queries: 0  Opens: 123  Flush tables: 1  Open tables: 123  Queries per second avg: 0.005';
    }
}

class WpQuery
{
    public $query = array('foo' => 'bar');

    /** @var string sql ?? */
    public $request = 'SELECT * from `some_table` where id = 42';

    public function get_queried_object()
    {
        return (object) array(
            'post_type' => 'post',
        );
    }

    public function get_queried_object_id()
    {
        return 42;
    }
}
