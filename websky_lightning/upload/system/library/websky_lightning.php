<?php
class WebskyLightning {
    private static $captureFile = '';
    public static function serve($registry) {
        if (!self::catalogRequest() || !self::eligible($registry)) { return; }
        $config = $registry->get('config');
        if (!$config->get('module_websky_lightning_status') || !$config->get('module_websky_lightning_page_cache')) { return; }
        if (mt_rand(1, 100) === 1) { self::prunePageCache(self::cacheDirectory(), max(3600, (int)$config->get('module_websky_lightning_cache_ttl'))); }
        $file = self::cacheFile($registry);
        $ttl = (int)$config->get('module_websky_lightning_cache_ttl');
        if ($ttl < 3600) { $ttl = 3600; }
        if (is_file($file) && filemtime($file) >= time() - $ttl) {
            self::record('hit');
            $acceptsGzip = !empty($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
            $useGzip = $acceptsGzip && function_exists('gzencode') && !ini_get('zlib.output_compression');
            $gzipFile = $file . '.gz';
            $serveFile = $useGzip && is_file($gzipFile) && filemtime($gzipFile) >= filemtime($file) ? $gzipFile : $file;
            $content = @file_get_contents($serveFile);
            if (!is_string($content)) { return; }
            if (!headers_sent()) {
                header('X-Websky-Cache: HIT');
                header('X-Websky-Cache-Age: ' . (time() - filemtime($file)));
                header('X-Websky-Cache-Profile: ' . self::cacheProfile($registry));
                header('Vary: Accept, Accept-Encoding', false);
                if ($useGzip && $serveFile === $gzipFile) { header('Content-Encoding: gzip'); }
            }
            echo ($useGzip && $serveFile === $gzipFile) ? $content : ($useGzip ? gzencode($content, 6) : $content);
            exit;
        }
        self::record('miss');
        if (!headers_sent()) {
            header('X-Websky-Cache: MISS');
            header('X-Websky-Cache-Profile: ' . self::cacheProfile($registry));
            header('Vary: Accept, Accept-Encoding', false);
        }
        self::$captureFile = $file;
        ob_start(array(__CLASS__, 'capture'));
    }

    public static function capture($output) {
        $content = $output;
        if (strlen($content) > 2 && substr($content, 0, 2) === "\x1f\x8b" && function_exists('gzdecode')) {
            $decoded = @gzdecode($content);
            if (is_string($decoded)) { $content = $decoded; }
        }
        if (self::$captureFile && strlen($content) >= 500 && stripos($content, '<html') !== false) {
            $dir = dirname(self::$captureFile);
            if ((is_dir($dir) || @mkdir($dir, 0755, true)) && is_writable($dir)) {
                $tmp = self::$captureFile . '.' . getmypid() . '.tmp';
                if (@file_put_contents($tmp, $content, LOCK_EX) !== false) {
                    @rename($tmp, self::$captureFile);
                    if (function_exists('gzencode')) {
                        $gzipTmp = self::$captureFile . '.gz.' . getmypid() . '.tmp';
                        if (@file_put_contents($gzipTmp, gzencode($content, 6), LOCK_EX) !== false) { @rename($gzipTmp, self::$captureFile . '.gz'); } else { @unlink($gzipTmp); }
                    }
                } else { @unlink($tmp); }
            }
        }
        $acceptsGzip = !empty($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
        if ($acceptsGzip && function_exists('gzencode') && !ini_get('zlib.output_compression') && !headers_sent()) {
            header('Content-Encoding: gzip');
            header('Vary: Accept, Accept-Encoding', false);
            return gzencode($content, 6);
        }
        return $output;
    }

    public static function webp($file) {
        if (!function_exists('imagewebp') || !is_file($file)) { return $file; }
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, array('jpg', 'jpeg', 'png'))) { return $file; }
        $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
        if (is_file($webp) && filemtime($webp) >= filemtime($file)) { return $webp; }
        $image = $extension === 'png' ? @imagecreatefrompng($file) : @imagecreatefromjpeg($file);
        if (!$image) { return $file; }
        if ($extension === 'png') { imagepalettetotruecolor($image); imagealphablending($image, true); imagesavealpha($image, true); }
        $ok = @imagewebp($image, $webp, 82); imagedestroy($image);
        return $ok && is_file($webp) ? $webp : $file;
    }

    public static function cacheDirectory() {
        $storage = defined('DIR_STORAGE') ? DIR_STORAGE : dirname(rtrim(DIR_CACHE, '/\\')) . DIRECTORY_SEPARATOR;
        $dir = rtrim($storage, '/\\') . DIRECTORY_SEPARATOR . 'websky_lightning_cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

        $legacy = rtrim(DIR_CACHE, '/\\') . DIRECTORY_SEPARATOR . 'websky_lightning' . DIRECTORY_SEPARATOR;
        if (is_dir($legacy) && is_dir($dir)) {
            foreach (glob($legacy . '*') ?: array() as $file) {
                if (!is_file($file)) { continue; }
                $target = $dir . basename($file);
                if (!is_file($target)) { @rename($file, $target) || @copy($file, $target); }
            }
        }
        return $dir;
    }

    public static function databaseQuery($adaptor, $sql) {
        $trimmed = ltrim((string)$sql);
        if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE|ALTER|CREATE|DROP|RENAME)\b/i', $trimmed)) {
            $result = $adaptor->query($sql);
            self::invalidateDatabaseCache();
            if (self::adminRequest() && self::changesStorefrontContent($trimmed)) {
                self::invalidatePageCache();
            }
            return $result;
        }

        if (!defined('WEBSKY_LIGHTNING_QUERY_CACHE') || !WEBSKY_LIGHTNING_QUERY_CACHE || !self::cacheableDatabaseQuery($trimmed)) {
            return $adaptor->query($sql);
        }

        $dir = self::databaseCacheDirectory();
        $generation = self::databaseCacheGeneration($dir);
        $file = $dir . hash('sha256', $generation . '|' . $trimmed) . '.qcache';
        $ttl = 300;
        if (is_file($file) && filemtime($file) >= time() - $ttl) {
            $cached = @unserialize((string)@file_get_contents($file));
            if (is_array($cached) && isset($cached['rows'], $cached['num_rows'])) {
                self::recordDatabaseCache('hit');
                $query = new \stdClass();
                $query->row = isset($cached['row']) ? $cached['row'] : array();
                $query->rows = $cached['rows'];
                $query->num_rows = (int)$cached['num_rows'];
                return $query;
            }
        }

        self::recordDatabaseCache('miss');
        $query = $adaptor->query($sql);
        if (is_object($query) && isset($query->rows, $query->num_rows)) {
            $payload = serialize(array('row' => isset($query->row) ? $query->row : array(), 'rows' => $query->rows, 'num_rows' => (int)$query->num_rows));
            $tmp = $file . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) { @rename($tmp, $file); } else { @unlink($tmp); }
        }
        if (mt_rand(1, 200) === 1) { self::pruneDatabaseCache($dir); }
        return $query;
    }

    public static function invalidateDatabaseCache() {
        $dir = self::databaseCacheDirectory();
        $file = $dir . 'generation';
        $handle = @fopen($file, 'c+');
        if (!$handle) { return; }
        if (@flock($handle, LOCK_EX)) {
            $value = (int)trim((string)stream_get_contents($handle));
            ftruncate($handle, 0); rewind($handle); fwrite($handle, (string)($value + 1)); fflush($handle); flock($handle, LOCK_UN);
        }
        fclose($handle);
    }

    public static function invalidatePageCache() {
        $dir = self::cacheDirectory();
        foreach (glob($dir . '*.html') ?: array() as $file) {
            if (is_file($file)) { @unlink($file); @unlink($file . '.gz'); }
        }
    }

    private static function changesStorefrontContent($sql) {
        return (bool)preg_match(
            '/\b[a-z0-9_]*(?:product(?:_[a-z0-9_]+)?|category(?:_[a-z0-9_]+)?|manufacturer(?:_[a-z0-9_]+)?|information(?:_[a-z0-9_]+)?|banner(?:_[a-z0-9_]+)?|review|seo_url)\b/i',
            $sql
        );
    }

    private static function cacheableDatabaseQuery($sql) {
        if (!preg_match('/^(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $sql)) { return false; }
        if (preg_match('/\b(FOR\s+UPDATE|LOCK\s+IN\s+SHARE|INTO\s+OUTFILE|SQL_NO_CACHE|SQL_CALC_FOUND_ROWS)\b/i', $sql)) { return false; }
        if (preg_match('/\b(RAND|NOW|CURDATE|CURTIME|CURRENT_TIMESTAMP|UNIX_TIMESTAMP|LAST_INSERT_ID|FOUND_ROWS|CONNECTION_ID|UUID)\s*\(/i', $sql)) { return false; }
        return true;
    }

    private static function databaseCacheDirectory() {
        $storage = defined('DIR_STORAGE') ? DIR_STORAGE : dirname(rtrim(DIR_CACHE, '/\\')) . DIRECTORY_SEPARATOR;
        $dir = rtrim($storage, '/\\') . DIRECTORY_SEPARATOR . 'websky_lightning_db_cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        return $dir;
    }

    private static function databaseCacheGeneration($dir) {
        $file = $dir . 'generation';
        if (!is_file($file)) { @file_put_contents($file, '1', LOCK_EX); }
        return max(1, (int)trim((string)@file_get_contents($file)));
    }

    private static function recordDatabaseCache($type) {
        $file = self::databaseCacheDirectory() . 'stats.json';
        $handle = @fopen($file, 'c+');
        if (!$handle) { return; }
        if (@flock($handle, LOCK_EX)) {
            $stats = json_decode((string)stream_get_contents($handle), true);
            if (!is_array($stats)) { $stats = array('hit' => 0, 'miss' => 0); }
            $stats[$type] = isset($stats[$type]) ? (int)$stats[$type] + 1 : 1;
            $stats['updated'] = time();
            ftruncate($handle, 0); rewind($handle); fwrite($handle, json_encode($stats)); fflush($handle); flock($handle, LOCK_UN);
        }
        fclose($handle);
    }

    private static function pruneDatabaseCache($dir) {
        foreach (glob($dir . '*.qcache') ?: array() as $file) { if (is_file($file) && filemtime($file) < time() - 86400) { @unlink($file); } }
    }

    private static function eligible($registry) {
        $request = $registry->get('request');
        $session = $registry->get('session');
        $config = $registry->get('config');
        if (!$request || !isset($request->server['REQUEST_METHOD']) || strtoupper($request->server['REQUEST_METHOD']) !== 'GET') { return false; }
        if (isset($request->get['websky_bypass'])) { return false; }
        if (!empty($request->server['HTTP_X_REQUESTED_WITH'])) { return false; }
        // Anonymous sessions may contain OpenCart's guest marker. Keep them
        // on the shared page cache unless customer or cart state is present.
        if ($session && (!empty($session->data['customer_id']) || !empty($session->data['customer']) || !empty($session->data['cart']))) { return false; }
        $route = self::detectedRoute($registry);
        $scope = $config ? $config->get('module_websky_lightning_cache_scope') : 'core';
        if ($scope === 'all') {
            return !preg_match('#^(account/|checkout/|api/|sale/|common/login|common/logout|extension/payment/|extension/total/)#i', $route);
        }
        return in_array($route, array('common/home', 'product/product', 'product/category'), true);
    }

    private static function detectedRoute($registry) {
        $request = $registry->get('request');
        if (isset($request->get['route']) && $request->get['route'] !== '') { return (string)$request->get['route']; }
        $uri = isset($request->server['REQUEST_URI']) ? (string)$request->server['REQUEST_URI'] : '/';
        $path = trim((string)parse_url($uri, PHP_URL_PATH), '/');
        if ($path === '' || $path === 'index.php') { return 'common/home'; }
        if (preg_match('#(?:^|/)product/#i', $path)) { return 'product/product'; }
        if (preg_match('#(?:^|/)category/#i', $path)) { return 'product/category'; }
        $segments = explode('/', $path);
        $keyword = rawurldecode((string)end($segments));
        $db = $registry->get('db');
        if ($db && $keyword !== '') {
            try {
                $query = $db->query("SELECT `query` FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $db->escape($keyword) . "' ORDER BY `seo_url_id` DESC LIMIT 1");
                if (!empty($query->row['query'])) {
                    $value = $query->row['query'];
                    if (strpos($value, 'product_id=') === 0) { return 'product/product'; }
                    if (strpos($value, 'category_id=') === 0) { return 'product/category'; }
                    if (strpos($value, 'manufacturer_id=') === 0) { return 'product/manufacturer/info'; }
                    if (strpos($value, 'information_id=') === 0) { return 'information/information'; }
                }
            } catch (\Exception $e) {}
        }
        return 'common/page';
    }

    private static function cacheFile($registry) {
        $request = $registry->get('request'); $session = $registry->get('session'); $config = $registry->get('config');
        $uri = self::normalizedUri(isset($request->server['REQUEST_URI']) ? $request->server['REQUEST_URI'] : '/');
        $language = $session && isset($session->data['language']) ? $session->data['language'] : $config->get('config_language');
        $currency = $session && isset($session->data['currency']) ? $session->data['currency'] : $config->get('config_currency');
        $acceptsWebp = !empty($request->server['HTTP_ACCEPT']) && strpos($request->server['HTTP_ACCEPT'], 'image/webp') !== false ? 'webp' : 'legacy';
        $scope = $config->get('module_websky_lightning_cache_scope') === 'all' ? 'all' : 'core';
        $device = self::deviceClass($request);
        $customerGroup = self::customerGroupId($registry);
        $key = (int)$config->get('config_store_id') . '|' . $language . '|' . $currency . '|' . $acceptsWebp . '|' . $scope . '|group-' . $customerGroup . '|' . $device . '|' . $uri;
        return self::cacheDirectory() . $scope . '_' . hash('sha256', $key) . '.html';
    }

    private static function cacheProfile($registry) {
        return 'group-' . self::customerGroupId($registry) . '-' . self::deviceClass($registry->get('request'));
    }

    private static function customerGroupId($registry) {
        $session = $registry->get('session');
        if ($session && isset($session->data['customer_group_id'])) {
            return max(0, (int)$session->data['customer_group_id']);
        }
        $customer = $registry->get('customer');
        if ($customer && method_exists($customer, 'getGroupId')) {
            return max(0, (int)$customer->getGroupId());
        }
        $config = $registry->get('config');
        return $config ? max(0, (int)$config->get('config_customer_group_id')) : 0;
    }

    private static function deviceClass($request) {
        $ua = strtolower(isset($request->server['HTTP_USER_AGENT']) ? (string)$request->server['HTTP_USER_AGENT'] : '');
        if (preg_match('/ipad|tablet|playbook|silk|android(?!.*mobile)/i', $ua)) { return 'tablet'; }
        if (preg_match('/mobile|iphone|ipod|android|blackberry|windows phone|opera mini/i', $ua)) { return 'mobile'; }
        return 'desktop';
    }

    private static function prunePageCache($dir, $ttl) {
        $cutoff = time() - max(86400, $ttl * 2);
        foreach (glob($dir . '*.html') ?: array() as $file) {
            if (is_file($file) && @filemtime($file) < $cutoff) { @unlink($file); @unlink($file . '.gz'); }
        }
    }

    private static function normalizedUri($uri) {
        $parts = parse_url((string)$uri);
        $path = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
        if (empty($parts['query'])) { return $path; }
        parse_str($parts['query'], $query);
        foreach (array_keys($query) as $name) {
            $lower = strtolower((string)$name);
            if (strpos($lower, 'utm_') === 0 || in_array($lower, array('gclid', 'dclid', 'fbclid', 'msclkid', 'yclid', 'mc_cid', 'mc_eid', 'websky_pregen'), true)) {
                unset($query[$name]);
            }
        }
        if (!$query) { return $path; }
        ksort($query);
        return $path . '?' . http_build_query($query, '', '&');
    }

    private static function catalogRequest() {
        return defined('DIR_APPLICATION') && strtolower(basename(rtrim(DIR_APPLICATION, '/\\'))) === 'catalog';
    }

    private static function adminRequest() {
        return defined('DIR_APPLICATION') && strtolower(basename(rtrim(DIR_APPLICATION, '/\\'))) === 'admin';
    }

    private static function record($type) {
        $file = self::cacheDirectory() . 'stats.json';
        $handle = @fopen($file, 'c+');
        if (!$handle) { return; }
        if (@flock($handle, LOCK_EX)) {
            $raw = stream_get_contents($handle);
            $stats = json_decode((string)$raw, true);
            if (!is_array($stats)) { $stats = array('hit' => 0, 'miss' => 0); }
            $stats[$type] = isset($stats[$type]) ? (int)$stats[$type] + 1 : 1;
            $stats['updated'] = time();
            ftruncate($handle, 0); rewind($handle); fwrite($handle, json_encode($stats)); fflush($handle); flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}
