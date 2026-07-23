<?php
class ControllerExtensionModuleWebskyLightning extends Controller {
    private $error = array();
    private $version = '1.7.1';

    public function index() {
        $this->load->language('extension/module/websky_lightning');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['benchmark_mode']) && $this->validate()) {
            $mode = $this->request->post['benchmark_mode'] === 'before' ? 'baseline' : 'after';
            $report = $this->runBenchmark($mode === 'baseline');
            $settings = $this->model_setting_setting->getSetting('module_websky_lightning');
            $settings['module_websky_lightning_' . $mode] = json_encode($report);
            $this->model_setting_setting->editSetting('module_websky_lightning', $settings);
            $this->response->redirect($this->url->link('extension/module/websky_lightning', 'user_token=' . $this->session->data['user_token'], true));
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_websky_lightning', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/websky_lightning', 'user_token=' . $this->session->data['user_token'], true));
        }

        foreach (array(
            'heading_title','text_edit','text_enabled','text_disabled','text_dashboard','text_cache_files',
            'text_cache_size','text_webp_support','text_db_driver','text_version','text_on','text_off','text_help',
            'text_report','text_environment','text_benchmark','text_before','text_after','text_no_data','text_recommendations',
            'text_php_version','text_memory_limit','text_opcache','text_gzip','text_database_size','text_database_overhead',
            'text_server','text_avg','text_min','text_max','text_http','text_response_size','text_improvement',
            'text_update','text_current_version','text_latest_version','text_release_date','text_changelog','text_update_status',
            'text_up_to_date','text_update_available','text_update_unavailable','button_download_update','button_check_update',
            'text_cpu_load','text_cache_hit_rate','text_cached_pages_graph','text_live_overview','text_hits','text_misses',
            'entry_status','entry_page_cache','entry_query_cache','entry_webp','entry_cache_scope','text_scope_core','text_scope_all','help_cache_scope','button_save','button_cancel',
            'button_test_before','button_test_after','button_clear_cache','button_refresh','error_permission'
        ) as $key) { $data[$key] = $this->language->get($key); }

        $data['action'] = $this->url->link('extension/module/websky_lightning', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['benchmark_url'] = html_entity_decode($this->url->link('extension/module/websky_lightning/speedTest', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');
        $data['clear_url'] = html_entity_decode($this->url->link('extension/module/websky_lightning/clearCache', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');
        $data['update_check_url'] = html_entity_decode($this->url->link('extension/module/websky_lightning', 'user_token=' . $this->session->data['user_token'] . '&refresh_update=1#tab-update', true), ENT_QUOTES, 'UTF-8');

        $defaults = array('status' => 1, 'page_cache' => 0, 'query_cache' => 0, 'webp' => 0);
        foreach ($defaults as $key => $default) {
            $name = 'module_websky_lightning_' . $key;
            $old = 'websky_lightning_' . $key;
            $value = isset($this->request->post[$name]) ? $this->request->post[$name] : $this->config->get($name);
            if ($value === null) { $value = $this->config->get($old); }
            $data[$name] = $value === null ? $default : $value;
        }
        $data['module_websky_lightning_cache_scope'] = isset($this->request->post['module_websky_lightning_cache_scope']) ? $this->request->post['module_websky_lightning_cache_scope'] : $this->config->get('module_websky_lightning_cache_scope');
        if (!in_array($data['module_websky_lightning_cache_scope'], array('core', 'all'), true)) { $data['module_websky_lightning_cache_scope'] = 'core'; }

        $cache = $this->cacheStats();
        $data['cache_file_count'] = $cache['files'];
        $data['cache_size'] = $this->formatBytes($cache['bytes']);
        $performance = $this->performanceStats($cache);
        $data['cpu_percent'] = $performance['cpu_percent'];
        $data['cpu_load'] = $performance['cpu_load'];
        $data['cpu_cores'] = $performance['cpu_cores'];
        $data['cache_hit_rate'] = $performance['hit_rate'];
        $data['cache_hits'] = $performance['hits'];
        $data['cache_misses'] = $performance['misses'];
        $data['cache_pages_percent'] = min(100, round($cache['files'] / 100 * 100));
        $data['webp_supported'] = function_exists('imagewebp');
        $data['db_driver'] = defined('DB_DRIVER') ? DB_DRIVER : 'MySQL';
        $data['module_version'] = $this->version;
        $update = $this->updateInfo(isset($this->request->get['refresh_update']));
        $data['latest_version'] = $update['version'];
        $data['release_date'] = $update['date'];
        $data['changelog'] = $update['body'];
        $data['update_download_url'] = $update['download'];
        $data['update_available'] = $update['version'] && version_compare($update['version'], $this->version, '>');
        $data['update_connected'] = $update['connected'];
        $data['diagnostics'] = $this->diagnostics();
        $data['baseline'] = $this->decodeReport($this->config->get('module_websky_lightning_baseline'));
        $data['after'] = $this->decodeReport($this->config->get('module_websky_lightning_after'));
        $data['improvement'] = $this->improvement($data['baseline'], $data['after']);
        $data['recommendations'] = $this->recommendations($data['diagnostics']);
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/websky_lightning', $data));
    }

    public function speedTest() {
        $this->load->language('extension/module/websky_lightning');
        $json = array();
        if (!$this->validate()) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $mode = isset($this->request->post['mode']) && $this->request->post['mode'] === 'before' ? 'baseline' : 'after';
            $report = $this->runBenchmark($mode === 'baseline');
            $this->load->model('setting/setting');
            $settings = $this->model_setting_setting->getSetting('module_websky_lightning');
            $settings['module_websky_lightning_' . $mode] = json_encode($report);
            $this->model_setting_setting->editSetting('module_websky_lightning', $settings);
            $json['success'] = true;
            $json['report'] = $report;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function clearCache() {
        $this->load->language('extension/module/websky_lightning');
        $json = array();
        if (!$this->validate()) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $dir = $this->pageCacheDir(); $count = 0;
            foreach (glob($dir . '*') ?: array() as $file) { if (is_file($file) && @unlink($file)) { $count++; } }
            @unlink($dir . 'stats.json');
            $json = array('success' => true, 'cleared' => $count);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function headerStats() {
        $json = array();
        if (!$this->user->hasPermission('access', 'extension/module/websky_lightning')) {
            $json['error'] = 'Access denied';
        } else {
            $cache = $this->cacheStats();
            $stats = $this->performanceStats($cache);
            $db_stats = $this->databaseCacheStats();
            $baseline = $this->decodeReport($this->config->get('module_websky_lightning_baseline'));
            $after = $this->decodeReport($this->config->get('module_websky_lightning_after'));
            $json = array(
                'success' => true,
                'cpu' => $stats['cpu_percent'],
                'load' => $stats['cpu_load'],
                'cores' => $stats['cpu_cores'],
                'hit_rate' => $stats['hit_rate'],
                'hits' => $stats['hits'],
                'misses' => $stats['misses'],
                'pages' => $cache['files'],
                'size' => $this->formatBytes($cache['bytes']),
                'db_hits' => $db_stats['hits'],
                'db_misses' => $db_stats['misses'],
                'db_rate' => $db_stats['rate'],
                'db_files' => $db_stats['files'],
                'speed_before' => isset($baseline['avg']) ? (int)$baseline['avg'] : null,
                'speed_after' => isset($after['avg']) ? (int)$after['avg'] : null,
                'speed_improvement' => $this->improvement($baseline, $after)
            );
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function headerSpeedTest() {
        $this->load->language('extension/module/websky_lightning');
        $json = array();
        if (!$this->validate()) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $baseline = $this->runBenchmark(true);

            // Warm the public homepage once before measuring cache-assisted requests.
            $this->benchmarkRequest(false);
            $after = $this->runBenchmark(false);

            $this->load->model('setting/setting');
            $settings = $this->model_setting_setting->getSetting('module_websky_lightning');
            $settings['module_websky_lightning_baseline'] = json_encode($baseline);
            $settings['module_websky_lightning_after'] = json_encode($after);
            $this->model_setting_setting->editSetting('module_websky_lightning', $settings);

            $json = array(
                'success' => true,
                'before' => (int)$baseline['avg'],
                'after' => (int)$after['avg'],
                'improvement' => $this->improvement($baseline, $after),
                'status' => (int)$after['status']
            );
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function runBenchmark($bypass = false) {
        $runs = array(); $status = 0; $bytes = 0;
        for ($i = 0; $i < 5; $i++) {
            $result = $this->benchmarkRequest($bypass);
            $runs[] = $result['time']; $status = $result['status']; $bytes = $result['bytes'];
        }
        return array('time' => date('c'), 'runs' => $runs, 'avg' => round(array_sum($runs) / count($runs)), 'min' => min($runs), 'max' => max($runs), 'status' => $status, 'bytes' => $bytes);
    }

    private function benchmarkRequest($bypass = false) {
        $url = defined('HTTPS_CATALOG') ? HTTPS_CATALOG : HTTP_CATALOG;
        $test_url = $bypass ? $url . (strpos($url, '?') === false ? '?websky_bypass=1&websky_benchmark=' : '&websky_bypass=1&websky_benchmark=') . mt_rand() : $url;
        $start = microtime(true); $body = ''; $status = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($test_url);
            curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Websky-Lightning/' . $this->version));
            $body = (string)curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        } else {
            $context = stream_context_create(array('http' => array('timeout' => 30, 'header' => "User-Agent: Websky-Lightning\r\n"), 'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
            $body = (string)@file_get_contents($test_url, false, $context); $status = $body !== '' ? 200 : 0;
        }
        return array('time' => round((microtime(true) - $start) * 1000), 'status' => $status, 'bytes' => strlen($body));
    }

    private function diagnostics() {
        $db_size = 0; $db_overhead = 0;
        try {
            $query = $this->db->query('SHOW TABLE STATUS');
            foreach ($query->rows as $row) { $db_size += (int)$row['Data_length'] + (int)$row['Index_length']; $db_overhead += (int)$row['Data_free']; }
        } catch (Exception $e) {}
        return array(
            'php' => PHP_VERSION,
            'memory' => ini_get('memory_limit'),
            'opcache' => function_exists('opcache_get_status') && @opcache_get_status(false) ? $this->language->get('text_on') : $this->language->get('text_off'),
            'gzip' => extension_loaded('zlib') ? $this->language->get('text_on') : $this->language->get('text_off'),
            'webp' => function_exists('imagewebp') ? $this->language->get('text_on') : $this->language->get('text_off'),
            'server' => isset($this->request->server['SERVER_SOFTWARE']) ? $this->request->server['SERVER_SOFTWARE'] : php_sapi_name(),
            'db_size' => $this->formatBytes($db_size),
            'db_overhead' => $this->formatBytes($db_overhead)
        );
    }

    private function recommendations($d) {
        $items = array();
        if ($d['opcache'] === $this->language->get('text_off')) { $items[] = $this->language->get('recommend_opcache'); }
        if (!function_exists('imagewebp')) { $items[] = $this->language->get('recommend_webp'); }
        if ($this->toBytes(ini_get('memory_limit')) < 134217728) { $items[] = $this->language->get('recommend_memory'); }
        if (!$items) { $items[] = $this->language->get('recommend_good'); }
        return $items;
    }

    private function updateInfo($refresh = false) {
        $cache_file = DIR_CACHE . 'websky_lightning_update.json';
        if (!$refresh && is_file($cache_file) && filemtime($cache_file) > time() - 21600) {
            $cached = json_decode((string)@file_get_contents($cache_file), true);
            if (is_array($cached) && !empty($cached['version']) && version_compare($cached['version'], $this->version, '>=')) { return $cached; }
        }
        if ($refresh) { @unlink($cache_file); }
        $result = array('version' => $this->version, 'date' => '', 'body' => '', 'download' => 'https://github.com/webskygroup/websky_lightning_v3/releases', 'connected' => false);
        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.github.com/repos/webskygroup/websky_lightning_v3/releases/latest' . ($refresh ? '?refresh=' . time() : ''));
            curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_USERAGENT => 'Websky-Lightning/' . $this->version));
            $json = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $release = json_decode((string)$json, true);
            if ($code === 200 && is_array($release)) {
                $result['connected'] = true;
                $source = (isset($release['tag_name']) ? $release['tag_name'] : '') . ' ' . (isset($release['name']) ? $release['name'] : '');
                if (preg_match('/\d+\.\d+\.\d+/', $source, $match)) { $result['version'] = $match[0]; }
                $result['date'] = isset($release['published_at']) ? substr($release['published_at'], 0, 10) : '';
                $result['body'] = isset($release['body']) ? $release['body'] : '';
                if (!empty($release['assets'][0]['browser_download_url'])) { $result['download'] = $release['assets'][0]['browser_download_url']; }
                elseif (!empty($release['html_url'])) { $result['download'] = $release['html_url']; }
            }
        }
        @file_put_contents($cache_file, json_encode($result), LOCK_EX);
        return $result;
    }

    private function cacheStats() { $files = glob($this->pageCacheDir() . '*.html') ?: array(); $bytes = 0; foreach ($files as $file) { if (is_file($file)) { $bytes += filesize($file); } } return array('files' => count($files), 'bytes' => $bytes); }
    private function performanceStats($cache) {
        $cores = 1;
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = @file_get_contents('/proc/cpuinfo');
            if ($cpuinfo) { $cores = max(1, preg_match_all('/^processor\s*:/m', $cpuinfo, $matches)); }
        }
        $loads = function_exists('sys_getloadavg') ? sys_getloadavg() : array(0, 0, 0);
        $load = isset($loads[0]) ? (float)$loads[0] : 0;
        $cpu_percent = min(100, round(($load / $cores) * 100, 1));
        $stats = json_decode((string)@file_get_contents($this->pageCacheDir() . 'stats.json'), true);
        $hits = is_array($stats) && isset($stats['hit']) ? (int)$stats['hit'] : 0;
        $misses = is_array($stats) && isset($stats['miss']) ? (int)$stats['miss'] : 0;
        $total = $hits + $misses;
        return array('cpu_percent' => $cpu_percent, 'cpu_load' => round($load, 2), 'cpu_cores' => $cores, 'hits' => $hits, 'misses' => $misses, 'hit_rate' => $total ? round(($hits / $total) * 100, 1) : 0);
    }
    private function decodeReport($value) { $data = json_decode((string)$value, true); return is_array($data) ? $data : array(); }
    private function improvement($before, $after) { return !empty($before['avg']) && isset($after['avg']) ? round((($before['avg'] - $after['avg']) / $before['avg']) * 100, 1) : null; }
    private function formatBytes($bytes) { if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB'; if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB'; if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB'; return $bytes . ' B'; }
    private function toBytes($value) { $value = trim($value); $unit = strtolower(substr($value, -1)); $number = (int)$value; if ($unit === 'g') return $number * 1073741824; if ($unit === 'm') return $number * 1048576; if ($unit === 'k') return $number * 1024; return $number; }
    private function pageCacheDir() { require_once(DIR_SYSTEM . 'library/websky_lightning.php'); return WebskyLightning::cacheDirectory(); }
    private function databaseCacheStats() { $dir = (defined('DIR_STORAGE') ? rtrim(DIR_STORAGE, '/\\') : dirname(rtrim(DIR_CACHE, '/\\'))) . DIRECTORY_SEPARATOR . 'websky_lightning_db_cache' . DIRECTORY_SEPARATOR; $stats = json_decode((string)@file_get_contents($dir . 'stats.json'), true); $hits = is_array($stats) && isset($stats['hit']) ? (int)$stats['hit'] : 0; $misses = is_array($stats) && isset($stats['miss']) ? (int)$stats['miss'] : 0; $total = $hits + $misses; return array('hits'=>$hits,'misses'=>$misses,'rate'=>$total ? round($hits / $total * 100, 1) : 0,'files'=>count(glob($dir . '*.qcache') ?: array())); }
    protected function validate() { if (!$this->user->hasPermission('modify', 'extension/module/websky_lightning')) { $this->error['warning'] = $this->language->get('error_permission'); } return !$this->error; }
    public function install() { $this->load->model('user/user_group'); $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/websky_lightning'); $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/websky_lightning'); $this->load->model('setting/setting'); $this->model_setting_setting->editSetting('module_websky_lightning', array('module_websky_lightning_status'=>1,'module_websky_lightning_page_cache'=>0,'module_websky_lightning_query_cache'=>0,'module_websky_lightning_webp'=>0,'module_websky_lightning_cache_scope'=>'core')); }
}
