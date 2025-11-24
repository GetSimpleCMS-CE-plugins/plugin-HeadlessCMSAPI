<?php
/*
Plugin Name: Headless CMS API
Description: REST API endpoint for using GetSimple as Headless CMS with SimpleBlog support
Version: 1.2
Author: multicolor
Author URI: https://ko-fi.com/multicolorplugins
*/

# Prevent direct access
if (!defined('IN_GS')) {
    die('You cannot load this file directly!');
}

# Get plugin ID
$thisfile = basename(__FILE__, ".php");

# Register plugin
register_plugin(
    $thisfile,
    'Headless CMS API',
    '1.0',
    'multicolor',
    'https://ko-fi.com/multicolorplugins',
    'REST API endpoint for using GetSimple as Headless CMS with SimpleBlog support',
    'plugins',
    'headless_api_admin'
);

# Add link in plugins tab
add_action('plugins-sidebar', 'createSideMenu', array($thisfile, 'API Settings'));

# Hook to intercept API requests
add_action('index-pretemplate', 'headless_api_router');

# Configuration file path
define('HEADLESS_API_CONFIG', GSDATAOTHERPATH . 'headless_api_config.json');
define('SIMPLEBLOG_DB', GSDATAOTHERPATH . 'blog.db');

/**
 * Helper function to get site URL
 */
function headless_api_get_site_url() {
    global $SITEURL;
    return $SITEURL;
}

/**
 * Helper function to get admin URL
 */
function headless_api_get_admin_url() {
    global $SITEURL;
    return $SITEURL . 'admin/';
}

/**
 * Check if SimpleBlog is installed
 */
function headless_api_simpleblog_exists() {
    return file_exists(SIMPLEBLOG_DB);
}

/**
 * Connect to SimpleBlog database
 */
function headless_api_get_blog_db() {
    if (!headless_api_simpleblog_exists()) {
        return null;
    }
    
    try {
        $db = new SQLite3(SIMPLEBLOG_DB);
        return $db;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if a column exists in a table
 */
function headless_api_column_exists($db, $table, $column) {
    $result = $db->query("PRAGMA table_info($table)");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['name'] === $column) {
            return true;
        }
    }
    return false;
}

/**
 * Get SimpleBlog table structure info
 */
function headless_api_get_blog_structure() {
    static $structure = null;
    
    if ($structure !== null) {
        return $structure;
    }
    
    $db = headless_api_get_blog_db();
    if (!$db) {
        return null;
    }
    
    $structure = [
        'has_status' => headless_api_column_exists($db, 'posts', 'status'),
        'has_scheduled' => headless_api_column_exists($db, 'posts', 'scheduled_date'),
        'has_description' => headless_api_column_exists($db, 'posts', 'description'),
        'has_cover_photo' => headless_api_column_exists($db, 'posts', 'cover_photo'),
        'has_approved' => headless_api_column_exists($db, 'comments', 'approved')
    ];
    
    $db->close();
    
    return $structure;
}

/**
 * Generate secure API key
 */
function headless_api_generate_key() {
    return bin2hex(random_bytes(32));
}

/**
 * Get or create API configuration
 */
function headless_api_get_config() {
    if (!file_exists(HEADLESS_API_CONFIG)) {
        $config = [
            'api_key' => headless_api_generate_key(),
            'api_enabled' => true,
            'require_auth' => false,
            'cors_enabled' => true
        ];
        headless_api_save_config($config);
        return $config;
    }
    
    $json = file_get_contents(HEADLESS_API_CONFIG);
    return json_decode($json, true);
}

/**
 * Save configuration
 */
function headless_api_save_config($config) {
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents(HEADLESS_API_CONFIG, $json);
}

/**
 * Check API authorization
 */
function headless_api_check_auth() {
    $config = headless_api_get_config();
    
    if (!$config['require_auth']) {
        return true;
    }
    
    $provided_key = isset($_GET['key']) ? $_GET['key'] : (isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '');
    
    if (empty($provided_key) || $provided_key !== $config['api_key']) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - invalid or missing API key'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    return true;
}

/**
 * API Router
 */
function headless_api_router() {
    if (!isset($_GET['api'])) {
        return;
    }
    
    $config = headless_api_get_config();
    
    if (!$config['api_enabled']) {
        http_response_code(503);
        echo json_encode(['error' => 'API is currently disabled'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Headers
    header('Content-Type: application/json; charset=utf-8');
    if ($config['cors_enabled']) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    }
    
    // Check authorization
    headless_api_check_auth();
    
    $endpoint = $_GET['api'];
    
    switch($endpoint) {
        // CMS Endpoints
        case 'pages':
            headless_api_get_all_pages();
            break;
        case 'page':
            headless_api_get_single_page();
            break;
        case 'menu':
            headless_api_get_menu();
            break;
        case 'navigation':
            headless_api_get_navigation();
            break;
        case 'search':
            headless_api_search_pages();
            break;
        case 'components':
            headless_api_get_components();
            break;
        case 'settings':
            headless_api_get_settings();
            break;
        case 'info':
            headless_api_get_info();
            break;
            
        // SimpleBlog Endpoints
        case 'blog/posts':
            headless_api_blog_get_posts();
            break;
        case 'blog/post':
            headless_api_blog_get_single_post();
            break;
        case 'blog/categories':
            headless_api_blog_get_categories();
            break;
        case 'blog/category':
            headless_api_blog_get_category_posts();
            break;
        case 'blog/recent':
            headless_api_blog_get_recent_posts();
            break;
        case 'blog/search':
            headless_api_blog_search_posts();
            break;
        case 'blog/comments':
            headless_api_blog_get_comments();
            break;
            
        default:
            http_response_code(404);
            $endpoints = [
                'info' => '?api=info - API information',
                'pages' => '?api=pages - Get all pages',
                'page' => '?api=page&slug=SLUG - Get single page',
                'menu' => '?api=menu - Get menu',
                'navigation' => '?api=navigation - Get navigation',
                'search' => '?api=search&q=QUERY - Search pages',
                'components' => '?api=components - Get components',
                'settings' => '?api=settings - Get settings'
            ];
            
            if (headless_api_simpleblog_exists()) {
                $endpoints['blog/posts'] = '?api=blog/posts - Get all blog posts';
                $endpoints['blog/post'] = '?api=blog/post&slug=SLUG - Get single post';
                $endpoints['blog/categories'] = '?api=blog/categories - Get categories';
                $endpoints['blog/category'] = '?api=blog/category&slug=SLUG - Get posts by category';
                $endpoints['blog/recent'] = '?api=blog/recent&limit=5 - Get recent posts';
                $endpoints['blog/search'] = '?api=blog/search&q=QUERY - Search posts';
                $endpoints['blog/comments'] = '?api=blog/comments&post_id=ID - Get comments';
            }
            
            echo json_encode([
                'error' => 'Invalid endpoint',
                'available_endpoints' => $endpoints
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    exit;
}

/**
 * Endpoint: API Information
 */
function headless_api_get_info() {
    global $SITENAME, $SITEURL;
    
    $config = headless_api_get_config();
    $has_blog = headless_api_simpleblog_exists();
    
    $endpoints = [
        'pages' => [
            'url' => '?api=pages',
            'method' => 'GET',
            'description' => 'Get all pages',
            'params' => [
                'include_private' => 'boolean (optional)',
                'limit' => 'integer (optional)',
                'offset' => 'integer (optional)',
                'sort' => 'string (optional)',
                'order' => 'asc|desc (optional)'
            ]
        ],
        'page' => [
            'url' => '?api=page&slug=SLUG',
            'method' => 'GET',
            'description' => 'Get single page',
            'params' => ['slug' => 'string (required)']
        ],
        'menu' => [
            'url' => '?api=menu',
            'method' => 'GET',
            'description' => 'Get menu structure'
        ],
        'navigation' => [
            'url' => '?api=navigation',
            'method' => 'GET',
            'description' => 'Get hierarchical navigation'
        ],
        'search' => [
            'url' => '?api=search&q=QUERY',
            'method' => 'GET',
            'description' => 'Search pages',
            'params' => ['q' => 'string (required)']
        ],
        'components' => [
            'url' => '?api=components',
            'method' => 'GET',
            'description' => 'Get components',
            'params' => ['name' => 'string (optional)']
        ],
        'settings' => [
            'url' => '?api=settings',
            'method' => 'GET',
            'description' => 'Get site settings'
        ]
    ];
    
    if ($has_blog) {
        $endpoints['blog/posts'] = [
            'url' => '?api=blog/posts',
            'method' => 'GET',
            'description' => 'Get all blog posts',
            'params' => [
                'limit' => 'integer (optional)',
                'offset' => 'integer (optional)'
            ]
        ];
        $endpoints['blog/post'] = [
            'url' => '?api=blog/post&slug=SLUG',
            'method' => 'GET',
            'description' => 'Get single blog post',
            'params' => ['slug' => 'string (required)']
        ];
        $endpoints['blog/categories'] = [
            'url' => '?api=blog/categories',
            'method' => 'GET',
            'description' => 'Get all blog categories'
        ];
        $endpoints['blog/category'] = [
            'url' => '?api=blog/category&slug=SLUG',
            'method' => 'GET',
            'description' => 'Get posts by category',
            'params' => [
                'slug' => 'string (required)',
                'limit' => 'integer (optional)',
                'offset' => 'integer (optional)'
            ]
        ];
        $endpoints['blog/recent'] = [
            'url' => '?api=blog/recent&limit=5',
            'method' => 'GET',
            'description' => 'Get recent blog posts',
            'params' => ['limit' => 'integer (optional, default: 5)']
        ];
        $endpoints['blog/search'] = [
            'url' => '?api=blog/search&q=QUERY',
            'method' => 'GET',
            'description' => 'Search blog posts',
            'params' => ['q' => 'string (required)']
        ];
        $endpoints['blog/comments'] = [
            'url' => '?api=blog/comments&post_id=ID',
            'method' => 'GET',
            'description' => 'Get comments for post',
            'params' => ['post_id' => 'integer (optional)']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'api_version' => '1.2',
        'site_name' => $SITENAME,
        'site_url' => $SITEURL,
        'auth_required' => $config['require_auth'],
        'simpleblog_enabled' => $has_blog,
        'endpoints' => $endpoints
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get all pages
 */
function headless_api_get_all_pages() {
    $pages = array();
    $path = GSDATAPAGESPATH;
    
    $files = glob($path . '*.xml');
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $include_private = isset($_GET['include_private']) && $_GET['include_private'] === 'true';
    
    $site_url = headless_api_get_site_url();
    
    foreach($files as $file) {
        $data = getXML($file);
        
        if (!$include_private && (string)$data->private === 'Y') {
            continue;
        }
        
        $pages[] = array(
            'slug' => (string)$data->url,
            'title' => (string)$data->title,
            'content' => (string)$data->content,
            'excerpt' => substr(strip_tags((string)$data->content), 0, 200) . '...',
            'meta_description' => (string)$data->meta,
            'meta_keywords' => (string)$data->metad,
            'parent' => (string)$data->parent,
            'template' => (string)$data->template,
            'date' => (string)$data->pubDate,
            'menu_status' => (string)$data->menuStatus === 'Y',
            'menu_order' => (int)$data->menuOrder,
            'private' => (string)$data->private === 'Y',
            'url' => $site_url . (string)$data->url . '/'
        );
    }
    
    if (isset($_GET['sort'])) {
        $sort_field = $_GET['sort'];
        $sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? -1 : 1;
        
        usort($pages, function($a, $b) use ($sort_field, $sort_order) {
            return ($a[$sort_field] <=> $b[$sort_field]) * $sort_order;
        });
    }
    
    $total = count($pages);
    
    if ($limit) {
        $pages = array_slice($pages, $offset, $limit);
    }
    
    echo json_encode([
        'success' => true,
        'total' => $total,
        'count' => count($pages),
        'offset' => $offset,
        'limit' => $limit,
        'pages' => $pages
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get single page
 */
function headless_api_get_single_page() {
    if (!isset($_GET['slug'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Slug parameter is required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $slug = $_GET['slug'];
    $file = GSDATAPAGESPATH . $slug . '.xml';
    
    if (!file_exists($file)) {
        http_response_code(404);
        echo json_encode(['error' => 'Page not found'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $data = getXML($file);
    $site_url = headless_api_get_site_url();
    
    $page = array(
        'slug' => (string)$data->url,
        'title' => (string)$data->title,
        'content' => (string)$data->content,
        'meta_description' => (string)$data->meta,
        'meta_keywords' => (string)$data->metad,
        'parent' => (string)$data->parent,
        'template' => (string)$data->template,
        'date' => (string)$data->pubDate,
        'menu_status' => (string)$data->menuStatus === 'Y',
        'menu_order' => (int)$data->menuOrder,
        'menu_text' => (string)$data->menu,
        'private' => (string)$data->private === 'Y',
        'url' => $site_url . (string)$data->url . '/'
    );
    
    echo json_encode([
        'success' => true,
        'page' => $page
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get menu
 */
function headless_api_get_menu() {
    $menu = array();
    $path = GSDATAPAGESPATH;
    $files = glob($path . '*.xml');
    $site_url = headless_api_get_site_url();
    
    foreach($files as $file) {
        $data = getXML($file);
        
        if ((string)$data->menuStatus === 'Y') {
            $menu[] = array(
                'slug' => (string)$data->url,
                'title' => (string)$data->title,
                'menu_text' => (string)$data->menu,
                'parent' => (string)$data->parent,
                'menu_order' => (int)$data->menuOrder,
                'url' => $site_url . (string)$data->url . '/'
            );
        }
    }
    
    usort($menu, function($a, $b) {
        return $a['menu_order'] - $b['menu_order'];
    });
    
    echo json_encode([
        'success' => true,
        'count' => count($menu),
        'menu' => $menu
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get hierarchical navigation
 */
function headless_api_get_navigation() {
    $pages = array();
    $path = GSDATAPAGESPATH;
    $files = glob($path . '*.xml');
    $site_url = headless_api_get_site_url();
    
    foreach($files as $file) {
        $data = getXML($file);
        
        if ((string)$data->menuStatus === 'Y') {
            $slug = (string)$data->url;
            $pages[$slug] = array(
                'slug' => $slug,
                'title' => (string)$data->title,
                'menu_text' => (string)$data->menu,
                'parent' => (string)$data->parent,
                'menu_order' => (int)$data->menuOrder,
                'url' => $site_url . $slug . '/',
                'children' => array()
            );
        }
    }
    
    $navigation = array();
    $children_map = array();
    
    foreach($pages as $slug => $page) {
        if (empty($page['parent'])) {
            $navigation[$slug] = $page;
        } else {
            if (!isset($children_map[$page['parent']])) {
                $children_map[$page['parent']] = array();
            }
            $children_map[$page['parent']][] = $page;
        }
    }
    
    foreach($navigation as $slug => $page) {
        if (isset($children_map[$slug])) {
            $navigation[$slug]['children'] = $children_map[$slug];
        }
    }
    
    echo json_encode([
        'success' => true,
        'navigation' => array_values($navigation)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Search pages
 */
function headless_api_search_pages() {
    if (!isset($_GET['q'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Query parameter (q) is required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $query = strtolower($_GET['q']);
    $results = array();
    $path = GSDATAPAGESPATH;
    $files = glob($path . '*.xml');
    $site_url = headless_api_get_site_url();
    
    foreach($files as $file) {
        $data = getXML($file);
        
        if ((string)$data->private === 'Y') {
            continue;
        }
        
        $title = strtolower((string)$data->title);
        $content = strtolower(strip_tags((string)$data->content));
        $meta = strtolower((string)$data->meta);
        
        if (strpos($title, $query) !== false || strpos($content, $query) !== false || strpos($meta, $query) !== false) {
            $results[] = array(
                'slug' => (string)$data->url,
                'title' => (string)$data->title,
                'excerpt' => substr(strip_tags((string)$data->content), 0, 200) . '...',
                'meta_description' => (string)$data->meta,
                'url' => $site_url . (string)$data->url . '/'
            );
        }
    }
    
    echo json_encode([
        'success' => true,
        'query' => $_GET['q'],
        'count' => count($results),
        'results' => $results
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get components
 */
function headless_api_get_components() {
    $components = array();
    $path = GSDATAOTHERPATH . 'components/';
    
    if (!is_dir($path)) {
        echo json_encode([
            'success' => true,
            'count' => 0,
            'components' => []
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return;
    }
    
    $files = glob($path . '*.xml');
    
    if (isset($_GET['name'])) {
        $name = $_GET['name'];
        $file = $path . $name . '.xml';
        
        if (file_exists($file)) {
            $data = getXML($file);
            $component = array(
                'name' => basename($file, '.xml'),
                'content' => (string)$data->content,
                'title' => (string)$data->title
            );
            
            echo json_encode([
                'success' => true,
                'component' => $component
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Component not found'], JSON_UNESCAPED_UNICODE);
        }
        return;
    }
    
    foreach($files as $file) {
        $data = getXML($file);
        $components[] = array(
            'name' => basename($file, '.xml'),
            'content' => (string)$data->content,
            'title' => (string)$data->title
        );
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($components),
        'components' => $components
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get site settings
 */
function headless_api_get_settings() {
    global $SITENAME, $SITEURL, $TEMPLATE;
    
    echo json_encode([
        'success' => true,
        'settings' => [
            'site_name' => $SITENAME,
            'site_url' => $SITEURL,
            'template' => $TEMPLATE
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// ==================== SIMPLEBLOG ENDPOINTS ====================

/**
 * Endpoint: Get all blog posts
 */
function headless_api_blog_get_posts() {
    $db = headless_api_get_blog_db();
    
    if (!$db) {
        http_response_code(404);
        echo json_encode(['error' => 'SimpleBlog not installed or database not accessible'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $site_url = headless_api_get_site_url();
    $structure = headless_api_get_blog_structure();
    
    $count_query = "SELECT COUNT(*) as total FROM posts";
    $count_result = $db->querySingle($count_query, true);
    $total = $count_result['total'];
    
    $query = "SELECT p.*, c.name as category_name, c.slug as category_slug 
              FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              ORDER BY p.date DESC 
              LIMIT $limit OFFSET $offset";
    
    $result = $db->query($query);
    
    $posts = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $post = array(
            'id' => $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'content' => $row['content'],
            'category' => [
                'id' => $row['category_id'],
                'name' => $row['category_name'],
                'slug' => $row['category_slug']
            ],
            'date' => $row['date'],
            'url' => $site_url . 'blog/' . $row['slug']
        );
        
        if ($structure['has_description']) {
            $post['excerpt'] = $row['description'] ?: substr(strip_tags($row['content']), 0, 200) . '...';
            $post['description'] = $row['description'];
        } else {
            $post['excerpt'] = substr(strip_tags($row['content']), 0, 200) . '...';
        }
        
        if ($structure['has_cover_photo']) {
            $post['cover_photo'] = $row['cover_photo'];
        }
        
        if ($structure['has_status']) {
            $post['status'] = $row['status'];
        }
        
        if ($structure['has_scheduled']) {
            $post['scheduled_date'] = $row['scheduled_date'];
        }
        
        $posts[] = $post;
    }
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'total' => $total,
        'count' => count($posts),
        'offset' => $offset,
        'limit' => $limit,
        'posts' => $posts
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get single blog post
 */
function headless_api_blog_get_single_post() {
    if (!isset($_GET['slug'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Slug parameter is required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $db = headless_api_get_blog_db();
    
    if (!$db) {
        http_response_code(404);
        echo json_encode(['error' => 'SimpleBlog not installed'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $slug = SQLite3::escapeString($_GET['slug']);
    $site_url = headless_api_get_site_url();
    $structure = headless_api_get_blog_structure();
    
    $query = "SELECT p.*, c.name as category_name, c.slug as category_slug 
              FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.slug = '$slug'";
    
    $result = $db->querySingle($query, true);
    
    if (!$result) {
        $db->close();
        http_response_code(404);
        echo json_encode(['error' => 'Post not found'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $post = array(
        'id' => $result['id'],
        'slug' => $result['slug'],
        'title' => $result['title'],
        'content' => $result['content'],
        'category' => [
            'id' => $result['category_id'],
            'name' => $result['category_name'],
            'slug' => $result['category_slug']
        ],
        'date' => $result['date'],
        'url' => $site_url . 'blog/' . $result['slug']
    );
    
    if ($structure['has_cover_photo']) {
        $post['cover_photo'] = $result['cover_photo'];
    }
    
    if ($structure['has_status']) {
        $post['status'] = $result['status'];
    }
    
    if ($structure['has_scheduled']) {
        $post['scheduled_date'] = $result['scheduled_date'];
    }
    
    if ($structure['has_description']) {
        $post['description'] = $result['description'];
    }
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'post' => $post
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get blog categories
 */
function headless_api_blog_get_categories() {
    $db = headless_api_get_blog_db();
    
    if (!$db) {
        http_response_code(404);
        echo json_encode(['error' => 'SimpleBlog not installed'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $site_url = headless_api_get_site_url();
    
    $query = "SELECT c.*, COUNT(p.id) as post_count 
              FROM categories c 
              LEFT JOIN posts p ON c.id = p.category_id
              GROUP BY c.id 
              ORDER BY c.name";
    
    $result = $db->query($query);
    
    if (!$result) {
        $db->close();
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $categories = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $categories[] = array(
            'id' => $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'post_count' => $row['post_count'],
            'url' => $site_url . 'blog/category/' . $row['slug']
        );
    }
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'count' => count($categories),
        'categories' => $categories
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get posts by category
 */
function headless_api_blog_get_category_posts() {
    if (!isset($_GET['slug'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Category slug is required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $db = headless_api_get_blog_db();
    
    if (!$db) {
        http_response_code(404);
        echo json_encode(['error' => 'SimpleBlog not installed'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $slug = SQLite3::escapeString($_GET['slug']);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $site_url = headless_api_get_site_url();
    $structure = headless_api_get_blog_structure();
    
    // Get category
    $category = $db->querySingle("SELECT * FROM categories WHERE slug = '$slug'", true);
    
    if (!$category) {
        $db->close();
        http_response_code(404);
        echo json_encode(['error' => 'Category not found'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $cat_id = $category['id'];
    
    $count_result = $db->querySingle("SELECT COUNT(*) as total FROM posts WHERE category_id = $cat_id", true);
    $total = $count_result['total'];
    
    $query = "SELECT * FROM posts 
              WHERE category_id = $cat_id
              ORDER BY date DESC 
              LIMIT $limit OFFSET $offset";
    
    $result = $db->query($query);
    
    $posts = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $post = array(
            'id' => $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'date' => $row['date'],
            'url' => $site_url . 'blog/' . $row['slug']
        );
        
        if ($structure['has_description']) {
            $post['excerpt'] = $row['description'] ?: substr(strip_tags($row['content']), 0, 200) . '...';
        } else {
            $post['excerpt'] = substr(strip_tags($row['content']), 0, 200) . '...';
        }
        
        if ($structure['has_cover_photo']) {
            $post['cover_photo'] = $row['cover_photo'];
        }
        
        $posts[] = $post;
    }
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'category' => [
            'id' => $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug']
        ],
        'total' => $total,
        'count' => count($posts),
        'offset' => $offset,
        'limit' => $limit,
        'posts' => $posts
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get recent blog posts
 */
function headless_api_blog_get_recent_posts() {
    $db = headless_api_get_blog_db();
    
    if (!$db) {
        http_response_code(404);
        echo json_encode(['error' => 'SimpleBlog not installed'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    $site_url = headless_api_get_site_url();
    $structure = headless_api_get_blog_structure();
    
    $query = "SELECT p.*, c.name as category_name, c.slug as category_slug 
              FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              ORDER BY p.date DESC 
              LIMIT $limit";
    
    $result = $db->query($query);
    
    $posts = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $post = array(
            'id' => $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'category' => [
                'name' => $row['category_name'],
                'slug' => $row['category_slug']
            ],
            'date' => $row['date'],
            'url' => $site_url . 'blog/' . $row['slug']
        );
        
        if ($structure['has_description']) {
            $post['excerpt'] = $row['description'] ?: substr(strip_tags($row['content']), 0, 150) . '...';
        } else {
            $post['excerpt'] = substr(strip_tags($row['content']), 0, 150) . '...';
        }
        
        if ($structure['has_cover_photo']) {
            $post['cover_photo'] = $row['cover_photo'];
        }
        
        $posts[] = $post;
    }
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'count' => count($posts),
        'posts' => $posts
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Search blog posts
 */
function headless_api_blog_search_posts() {
    if (!isset($_GET['q'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Query parameter (q) is required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $db = headless_api_get_blog_db();
    
    if (!$db) {
        http_response_code(404);
        echo json_encode(['error' => 'SimpleBlog not installed'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $search = SQLite3::escapeString($_GET['q']);
    $site_url = headless_api_get_site_url();
    $structure = headless_api_get_blog_structure();
    
    $where_parts = ["p.title LIKE '%$search%'", "p.content LIKE '%$search%'"];
    if ($structure['has_description']) {
        $where_parts[] = "p.description LIKE '%$search%'";
    }
    $where = implode(' OR ', $where_parts);
    
    $query = "SELECT p.*, c.name as category_name, c.slug as category_slug 
              FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE $where
              ORDER BY p.date DESC";
    
    $result = $db->query($query);
    
    $posts = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $post = array(
            'id' => $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'category' => [
                'name' => $row['category_name'],
                'slug' => $row['category_slug']
            ],
            'date' => $row['date'],
            'url' => $site_url . 'blog/' . $row['slug']
        );
        
        if ($structure['has_description']) {
            $post['excerpt'] = $row['description'] ?: substr(strip_tags($row['content']), 0, 200) . '...';
        } else {
            $post['excerpt'] = substr(strip_tags($row['content']), 0, 200) . '...';
        }
        
        if ($structure['has_cover_photo']) {
            $post['cover_photo'] = $row['cover_photo'];
        }
        
        $posts[] = $post;
    }
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'query' => $_GET['q'],
        'count' => count($posts),
        'results' => $posts
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * Endpoint: Get blog comments
 */
function headless_api_blog_get_comments() {
    $db = headless_api_get_blog_db();
    
    if (!$db) {
        http_response_code(404);
        echo json_encode(['error' => 'SimpleBlog not installed'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $structure = headless_api_get_blog_structure();
    
    $where = '';
    if (isset($_GET['post_id'])) {
        $post_id = (int)$_GET['post_id'];
        if ($structure['has_approved']) {
            $where = "WHERE post_id = $post_id AND approved = 1";
        } else {
            $where = "WHERE post_id = $post_id";
        }
    } else {
        if ($structure['has_approved']) {
            $where = "WHERE approved = 1";
        }
    }
    
    $query = "SELECT * FROM comments $where ORDER BY date DESC";
    
    $result = $db->query($query);
    
    $comments = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $comment = array(
            'id' => $row['id'],
            'post_id' => $row['post_id'],
            'author' => $row['author'],
            'email' => $row['email'],
            'content' => $row['content'],
            'date' => $row['date']
        );
        
        if ($structure['has_approved']) {
            $comment['approved'] = (bool)$row['approved'];
        }
        
        $comments[] = $comment;
    }
    
    $db->close();
    
    echo json_encode([
        'success' => true,
        'count' => count($comments),
        'comments' => $comments
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// ==================== ADMIN PANEL ====================

/**
 * Admin panel
 */
function headless_api_admin() {
    global $SITEURL;
    
    $config = headless_api_get_config();
    $has_blog = headless_api_simpleblog_exists();
    
    // Handle form submission
    if (isset($_POST['headless_api_submit'])) {
        $config['api_enabled'] = isset($_POST['api_enabled']);
        $config['require_auth'] = isset($_POST['require_auth']);
        $config['cors_enabled'] = isset($_POST['cors_enabled']);
        
        if (isset($_POST['regenerate_key'])) {
            $config['api_key'] = headless_api_generate_key();
        }
        
        headless_api_save_config($config);
        echo '<div class="updated">Settings saved successfully!</div>';
        $config = headless_api_get_config();
    }
    
    $auth_param = $config['require_auth'] ? '&key='.$config['api_key'] : '';
    $admin_url = headless_api_get_admin_url();
    
    ?>
    <h3>Headless CMS API - Settings</h3>
    
    <?php if ($has_blog): ?>
    <div class="updated" style="padding: 10px; margin: 15px 0;">
        <strong>✓ SimpleBlog detected!</strong> - Blog endpoints are available.
    </div>
    <?php endif; ?>
    
    <form method="post" action="<?php echo $admin_url; ?>load.php?id=<?php echo basename(__FILE__, ".php"); ?>">
        
        <h4>API Configuration</h4>
        <table class="highlight">
            <tr>
                <td><label for="api_enabled">API Enabled:</label></td>
                <td><input type="checkbox" name="api_enabled" id="api_enabled" <?php echo $config['api_enabled'] ? 'checked' : ''; ?> /></td>
            </tr>
            <tr>
                <td><label for="require_auth">Require Authorization:</label></td>
                <td><input type="checkbox" name="require_auth" id="require_auth" <?php echo $config['require_auth'] ? 'checked' : ''; ?> /></td>
            </tr>
            <tr>
                <td><label for="cors_enabled">Enable CORS:</label></td>
                <td><input type="checkbox" name="cors_enabled" id="cors_enabled" <?php echo $config['cors_enabled'] ? 'checked' : ''; ?> /></td>
            </tr>
        </table>
        
        <h4>API Key</h4>
        <p><strong>Your API Key:</strong></p>
        <input type="text" value="<?php echo $config['api_key']; ?>" readonly style="width: 100%; font-family: monospace; padding: 8px;" onclick="this.select();" />
        <p><small>Click to copy. Use as parameter <code>?key=KEY</code> or header <code>X-API-Key</code></small></p>
        
        <p>
            <label>
                <input type="checkbox" name="regenerate_key" value="1" />
                Regenerate new API key (old key will stop working!)
            </label>
        </p>
        
        <p><input type="submit" name="headless_api_submit" value="Save Settings" class="submit" /></p>
    </form>
    
    <hr>
    
    <h4>CMS Endpoints:</h4>
    <table class="highlight">
        <thead>
            <tr>
                <th>Endpoint</th>
                <th>Description</th>
                <th>Test</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>?api=info</code></td>
                <td>API information</td>
                <td><a href="<?php echo $SITEURL; ?>?api=info<?php echo $auth_param; ?>" target="_blank">Test</a></td>
            </tr>
            <tr>
                <td><code>?api=pages</code></td>
                <td>All pages</td>
                <td><a href="<?php echo $SITEURL; ?>?api=pages<?php echo $auth_param; ?>" target="_blank">Test</a></td>
            </tr>
            <tr>
                <td><code>?api=page&slug=index</code></td>
                <td>Single page</td>
                <td><a href="<?php echo $SITEURL; ?>?api=page&slug=index<?php echo $auth_param; ?>" target="_blank">Test</a></td>
            </tr>
            <tr>
                <td><code>?api=menu</code></td>
                <td>Menu structure</td>
                <td><a href="<?php echo $SITEURL; ?>?api=menu<?php echo $auth_param; ?>" target="_blank">Test</a></td>
            </tr>
            <tr>
                <td><code>?api=search&q=text</code></td>
                <td>Search pages</td>
                <td><a href="<?php echo $SITEURL; ?>?api=search&q=welcome<?php echo $auth_param; ?>" target="_blank">Test</a></td>
            </tr>
        </tbody>
    </table>
    
    <?php if ($has_blog): ?>
    <h4>SimpleBlog Endpoints:</h4>
    <table class="highlight">
        <thead>
            <tr>
                <th>Endpoint</th>
                <th>Description</th>
                <th>Test</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>?api=blog/posts</code></td>
                <td>All blog posts</td>
                <td><a href="<?php echo $SITEURL; ?>?api=blog/posts<?php echo $auth_param; ?>" target="_blank">Test</a></td>
            </tr>
            <tr>
                <td><code>?api=blog/categories</code></td>
                <td>Blog categories</td>
                <td><a href="<?php echo $SITEURL; ?>?api=blog/categories<?php echo $auth_param; ?>" target="_blank">Test</a></td>
            </tr>
            <tr>
                <td><code>?api=blog/recent&limit=5</code></td>
                <td>Recent posts</td>
                <td><a href="<?php echo $SITEURL; ?>?api=blog/recent&limit=5<?php echo $auth_param; ?>" target="_blank">Test</a></td>
            </tr>
            <tr>
                <td><code>?api=blog/search&q=text</code></td>
                <td>Search posts</td>
                <td><a href="<?php echo $SITEURL; ?>?api=blog/search&q=blog<?php echo $auth_param; ?>" target="_blank">Test</a></td>
            </tr>
        </tbody>
    </table>
    
    <?php endif; ?>
    
    <hr>
    
    <h4>Usage Examples:</h4>
    <pre style="border:solid 1px #ddd;background:#fafafa;padding:10px;margin-top:10px;"><code>// JavaScript fetch
fetch('<?php echo $SITEURL; ?>?api=pages<?php echo $auth_param; ?>')
  .then(res => res.json())
  .then(data => console.log(data));

// PHP cURL
$ch = curl_init('<?php echo $SITEURL; ?>?api=page&slug=index<?php echo $auth_param; ?>');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);</code></pre>
    
<a href='https://ko-fi.com/I3I2RHQZS' target='_blank'><img height='36' style='border:0px;height:36px;margin-top:20px;' src='https://storage.ko-fi.com/cdn/kofi5.png?v=6' border='0' alt='Buy Me a Coffee at ko-fi.com' /></a>

    <?php
}
?>
