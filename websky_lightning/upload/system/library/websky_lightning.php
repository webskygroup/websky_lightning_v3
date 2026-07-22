<?php
class WebskyLightning {
    private static $captureFile = '';
    public static function serve($registry) {
        if (!self::catalogRequest() || !self::eligible($registry)) { return; }
        $config = $registry->get('config');
        if (!$config->get('module_websky_lightning_status') || !$config->get('module_websky_lightning_page_cache')) { return; }
        $file = self::cacheFile($registry);
        $ttl = (int)$config->get('module_websky_lightning_cache_ttl');
        if ($ttl < 60) { $ttl = 900; }
        if (is_file($file) && filemtime($file) >= time() - $ttl) {
            self::record('hit');
            $content = @file_get_contents($file);
            if (!is_string($content)) { return; }
            $acceptsGzip = !empty($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
            $useGzip = $acceptsGzip && function_exists('gzencode') && !ini_get('zlib.output_compression');
            if (!headers_sent()) {
                header('X-Websky-Cache: HIT');
                header('X-Websky-Cache-Age: ' . (time() - filemtime($file)));
                header('Vary: Accept, Accept-Encoding', false);
                if ($useGzip) { header('Content-Encoding: gzip'); }
            }
            echo $useGzip ? gzencode($content, 6) : $content;
            exit;
        }
        self::record('miss');
        if (!headers_sent()) { header('X-Websky-Cache: MISS'); header('Vary: Accept, Accept-Encoding', false); }
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
                if (@file_put_contents($tmp, $content, LOCK_EX) !== false) { @rename($tmp, self::$captureFile); } else { @unlink($tmp); }
            }
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

    private static function eligible($registry) {
        $request = $registry->get('request');
        $session = $registry->get('session');
        if (!$request || !isset($request->server['REQUEST_METHOD']) || strtoupper($request->server['REQUEST_METHOD']) !== 'GET') { return false; }
        if (isset($request->get['websky_bypass'])) { return false; }
        if (!empty($request->server['HTTP_X_REQUESTED_WITH'])) { return false; }
        if ($session && (!empty($session->data['customer_id']) || !empty($session->data['customer']) || !empty($session->data['cart']) || !empty($session->data['guest']))) { return false; }
        $route = isset($request->get['route']) ? $request->get['route'] : 'common/home';
        $allowed = array('common/home', 'product/product', 'product/category', 'product/manufacturer/info', 'information/information');
        return in_array($route, $allowed, true);
    }

    private static function cacheFile($registry) {
        $request = $registry->get('request'); $session = $registry->get('session'); $config = $registry->get('config');
        $uri = isset($request->server['REQUEST_URI']) ? $request->server['REQUEST_URI'] : '/';
        $language = $session && isset($session->data['language']) ? $session->data['language'] : $config->get('config_language');
        $currency = $session && isset($session->data['currency']) ? $session->data['currency'] : $config->get('config_currency');
        $acceptsWebp = !empty($request->server['HTTP_ACCEPT']) && strpos($request->server['HTTP_ACCEPT'], 'image/webp') !== false ? 'webp' : 'legacy';
        $key = (int)$config->get('config_store_id') . '|' . $language . '|' . $currency . '|' . $acceptsWebp . '|' . $uri;
        return DIR_CACHE . 'websky_lightning/' . hash('sha256', $key) . '.html';
    }

    private static function catalogRequest() {
        return defined('DIR_APPLICATION') && strtolower(basename(rtrim(DIR_APPLICATION, '/\\'))) === 'catalog';
    }

    private static function record($type) {
        $file = DIR_CACHE . 'websky_lightning_stats.json';
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
