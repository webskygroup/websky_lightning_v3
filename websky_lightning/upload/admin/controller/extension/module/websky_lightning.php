<?php
class ControllerExtensionModuleWebskyLightning extends Controller {
    private $error = array();
    private $version = '1.12.0';
    private $version_extension = 'websky_lightning_v3';
    private $download_url = 'https://opencart-ir.com/dl/v3/websky_lightning.ocmod.zip883948';

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
            'text_server','text_avg','text_median','text_p95','text_ttfb','text_min','text_max','text_http','text_response_size','text_improvement',
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
        $data['upgrade_url'] = html_entity_decode($this->url->link('extension/module/websky_lightning/upgrade', 'user_token=' . $this->session->data['user_token'], true), ENT_QUOTES, 'UTF-8');

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

    public function upgrade() {
        $this->load->language('extension/module/websky_lightning');

        $json = array();
        $temporary_directory = '';
        $zip_file = '';

        if (!$this->user->hasPermission('modify', 'extension/module/websky_lightning')) {
            $json['error'] = $this->language->get('error_permission');
        }
        if (!$json && !class_exists('ZipArchive')) {
            $json['error'] = 'ZipArchive is not available on the server.';
        }
        if (!$json) {
            $package = $this->downloadPackage();
            if (!$package) {
                $json['error'] = 'The update package could not be downloaded or is invalid.';
            }
        }
        if (!$json) {
            $token = function_exists('token') ? token(12) : substr(sha1(uniqid('', true)), 0, 12);
            $temporary_directory = rtrim(DIR_UPLOAD, '/\\') . '/websky-lightning-' . $token;
            $zip_file = $temporary_directory . '.ocmod.zip';
            if (@file_put_contents($zip_file, $package) === false) {
                $json['error'] = 'The update package could not be saved.';
            }
        }
        if (!$json) {
            $zip = new ZipArchive();
            $zip_opened = $zip->open($zip_file) === true;
            if (!$zip_opened || !$this->validateArchive($zip)) {
                $json['error'] = 'The update package failed validation.';
            } elseif (!is_dir($temporary_directory) && !@mkdir($temporary_directory, 0755, true)) {
                $json['error'] = 'The temporary update directory could not be created.';
            } elseif (!$zip->extractTo($temporary_directory)) {
                $json['error'] = 'The update package could not be extracted.';
            }
            if ($zip_opened) {
                $zip->close();
            }
        }
        if (!$json && !$this->copyUpgradeFiles($temporary_directory . '/upload')) {
            $json['error'] = 'The module files could not be installed.';
        }
        if (!$json && !$this->refreshModification($temporary_directory . '/install.xml')) {
            $json['error'] = 'The modification definition could not be refreshed.';
        }
        if ($zip_file && is_file($zip_file)) {
            @unlink($zip_file);
        }
        if ($temporary_directory && is_dir($temporary_directory)) {
            $this->removeDirectory($temporary_directory);
        }
        if (!$json) {
            @unlink(DIR_CACHE . 'websky_lightning_update.json');
            $json['success'] = $this->language->get('text_upgrade_success');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
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
                'speed_before_ttfb' => isset($baseline['ttfb_median']) ? (int)$baseline['ttfb_median'] : null,
                'speed_after_ttfb' => isset($after['ttfb_median']) ? (int)$after['ttfb_median'] : null,
                'speed_before_cache' => isset($baseline['cache']) ? $baseline['cache'] : array(),
                'speed_after_cache' => isset($after['cache']) ? $after['cache'] : array(),
                'speed_improvement' => $this->improvement($baseline, $after)
            );
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function pregenerate() {
        $json = array('success' => false, 'generated' => 0, 'hits' => 0, 'failed' => 0, 'total' => 0);
        if (!$this->user->hasPermission('modify', 'extension/module/websky_lightning')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $offset = max(0, (int)(isset($this->request->get['offset']) ? $this->request->get['offset'] : 0));
            $limit = min(10, max(1, (int)(isset($this->request->get['limit']) ? $this->request->get['limit'] : 10)));
            $urls = array();
            $urls[] = defined('HTTPS_CATALOG') ? rtrim(HTTPS_CATALOG, '/') . '/' : rtrim(HTTP_CATALOG, '/') . '/';
            try {
                $query = $this->db->query("SELECT `query`, `keyword` FROM `" . DB_PREFIX . "seo_url` WHERE `store_id` = 0 AND (`query` LIKE 'product_id=%' OR `query` LIKE 'category_id=%') ORDER BY `seo_url_id` ASC LIMIT " . $offset . "," . $limit);
                foreach ($query->rows as $row) {
                    if (!empty($row['keyword'])) { $urls[] = (defined('HTTPS_CATALOG') ? rtrim(HTTPS_CATALOG, '/') : rtrim(HTTP_CATALOG, '/')) . '/' . ltrim($row['keyword'], '/'); }
                }
                $count = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "seo_url` WHERE `store_id` = 0 AND (`query` LIKE 'product_id=%' OR `query` LIKE 'category_id=%')");
                $json['total'] = isset($count->row['total']) ? (int)$count->row['total'] : count($urls) - 1;
            } catch (Exception $e) {
                $json['error'] = 'SEO URL table is unavailable';
            }
            if (empty($json['error']) && function_exists('curl_init')) {
                foreach (array_unique($urls) as $url) {
                    $target = $url . (strpos($url, '?') === false ? '?' : '&') . 'websky_pregen=1';
                    $ch = curl_init($target);
                    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Websky-Lightning-PreGenerator/1.0'));
                    curl_exec($ch);
                    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $time = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
                    curl_close($ch);
                    if ($code === 200) { $time < 1.0 ? $json['hits']++ : $json['generated']++; } else { $json['failed']++; }
                }
                $json['success'] = true;
                $json['next_offset'] = $offset + $limit;
            } elseif (empty($json['error'])) {
                $json['error'] = 'cURL is required for safe pre-generation';
            }
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
                'before_ttfb' => (int)$baseline['ttfb_median'],
                'after_ttfb' => (int)$after['ttfb_median'],
                'before_cache' => $baseline['cache'],
                'after_cache' => $after['cache'],
                'improvement' => $this->improvement($baseline, $after),
                'status' => (int)$after['status']
            );
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function runBenchmark($bypass = false) {
        $runs = array(); $ttfb = array(); $status = 0; $bytes = 0; $cache = array();
        for ($i = 0; $i < 5; $i++) {
            $result = $this->benchmarkRequest($bypass);
            $runs[] = $result['time']; $ttfb[] = $result['ttfb']; $status = $result['status']; $bytes = $result['bytes']; $cache[] = $result['cache'];
        }
        sort($runs); sort($ttfb);
        return array('time' => date('c'), 'runs' => $runs, 'ttfb_runs' => $ttfb,
            'avg' => $this->percentile($runs, 50), 'mean' => round(array_sum($runs) / count($runs)), 'median' => $this->percentile($runs, 50), 'p95' => $this->percentile($runs, 95),
            'ttfb_avg' => round(array_sum($ttfb) / count($ttfb)), 'ttfb_median' => $this->percentile($ttfb, 50),
            'min' => min($runs), 'max' => max($runs), 'status' => $status, 'bytes' => $bytes, 'cache' => array_count_values($cache));
    }

    private function percentile($values, $percentile) {
        if (!$values) return 0;
        $index = (count($values) - 1) * ($percentile / 100);
        $lower = (int)floor($index); $upper = (int)ceil($index);
        if ($lower === $upper) return (int)$values[$lower];
        return (int)round($values[$lower] + (($values[$upper] - $values[$lower]) * ($index - $lower)));
    }

    private function benchmarkRequest($bypass = false) {
        $url = defined('HTTPS_CATALOG') ? HTTPS_CATALOG : HTTP_CATALOG;
        $test_url = $bypass ? $url . (strpos($url, '?') === false ? '?websky_bypass=1&websky_benchmark=' : '&websky_bypass=1&websky_benchmark=') . mt_rand() : $url;
        $start = microtime(true); $body = ''; $status = 0;
        $ttfb = 0; $cache = 'UNKNOWN';
        if (function_exists('curl_init')) {
            $ch = curl_init($test_url);
            curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Websky-Lightning/' . $this->version));
            $body = (string)curl_exec($ch); $info = curl_getinfo($ch); $status = (int)$info['http_code'];
            $ttfb = (int)round(((float)$info['starttransfer_time']) * 1000);
            $header_size = isset($info['header_size']) ? (int)$info['header_size'] : 0;
            $headers = substr($body, 0, $header_size); $body = substr($body, $header_size);
            if (preg_match('/^X-Websky-Cache:\s*(HIT|MISS)/mi', $headers, $match)) $cache = strtoupper($match[1]);
            curl_close($ch);
        } else {
            $context = stream_context_create(array('http' => array('timeout' => 30, 'header' => "User-Agent: Websky-Lightning\r\n"), 'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
            $body = (string)@file_get_contents($test_url, false, $context); $status = $body !== '' ? 200 : 0;
        }
        return array('time' => round((microtime(true) - $start) * 1000), 'ttfb' => $ttfb, 'cache' => $cache, 'status' => $status, 'bytes' => strlen($body));
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
            if (is_array($cached) && !empty($cached['version']) && version_compare($cached['version'], $this->version, '>=')) {
                // Keep older cache entries from sending administrators to GitHub.
                $cached['download'] = $this->download_url;
                return $cached;
            }
        }
        if ($refresh) { @unlink($cache_file); }
        $result = array('version' => $this->version, 'date' => '', 'body' => '', 'download' => $this->download_url, 'connected' => false);
        if (function_exists('curl_init')) {
            $ch = curl_init('https://opencart-ir.com/version/index.php?route=extension/websky_lastversion/module/websky_lastversion');
            curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => array('extension_name' => $this->version_extension), CURLOPT_USERAGENT => 'Websky-Lightning/' . $this->version));
            $json = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $release = json_decode((string)$json, true);
            if ($code === 200 && is_array($release) && !empty($release['version_ext'])) {
                $result['connected'] = true;
                $result['version'] = (string)$release['version_ext'];
                $date = !empty($release['date_released']) ? $release['date_released'] : (isset($release['date_added']) ? $release['date_added'] : '');
                $result['date'] = $date ? substr($date, 0, 10) : '';
                foreach (array('release_notes', 'description', 'log', 'change_log', 'changes') as $key) {
                    if (!empty($release[$key])) {
                        $result['body'] = is_array($release[$key]) ? implode("\n", $release[$key]) : (string)$release[$key];
                        break;
                    }
                }
            }
        }
        @file_put_contents($cache_file, json_encode($result), LOCK_EX);
        return $result;
    }

    private function downloadPackage() {
        $package = $this->requestRemote($this->download_url, array(), 60, false);
        if (!$package || strlen($package) > 52428800 || substr($package, 0, 2) !== 'PK') {
            return '';
        }
        return $package;
    }

    private function requestRemote($url, $fields, $timeout, $post = true) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'OpenCart Websky Lightning/' . $this->version
            ));
            if ($post) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            }
            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($response !== false && $status >= 200 && $status < 300) ? $response : '';
        }

        $options = array('http' => array(
            'method' => $post ? 'POST' : 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "User-Agent: OpenCart Websky Lightning/" . $this->version . "\r\n"
        ));
        if ($post) {
            $options['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $options['http']['content'] = http_build_query($fields);
        }
        $response = @file_get_contents($url, false, stream_context_create($options));
        return $response === false ? '' : $response;
    }

    private function validateArchive($zip) {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($index));
            if (!$name || strpos($name, "\0") !== false || preg_match('#(^/|^[A-Za-z]:|(?:^|/)\.\.(?:/|$))#', $name)) {
                return false;
            }
            if ($name !== 'install.xml' && strpos($name, 'upload/') !== 0) {
                return false;
            }
        }
        return true;
    }

    private function copyUpgradeFiles($upload_directory) {
        if (!is_dir($upload_directory)) {
            return false;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_directory, FilesystemIterator::SKIP_DOTS));
        $copied = 0;
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($upload_directory) + 1));
            if (!preg_match('#^(admin|catalog|system)/#', $relative) || strpos($relative, "\0") !== false || preg_match('#(^|/)\.\.(?:/|$)#', $relative)) {
                return false;
            }
            if (strpos($relative, 'admin/') === 0) {
                $destination = rtrim(DIR_APPLICATION, '/\\') . '/' . substr($relative, 6);
            } elseif (strpos($relative, 'catalog/') === 0) {
                $destination = rtrim(DIR_CATALOG, '/\\') . '/' . substr($relative, 8);
            } else {
                $destination = rtrim(DIR_SYSTEM, '/\\') . '/' . substr($relative, 7);
            }
            $destination_directory = dirname($destination);
            if (!is_dir($destination_directory) && !@mkdir($destination_directory, 0755, true)) {
                return false;
            }
            if (!@copy($file->getPathname(), $destination)) {
                return false;
            }
            $copied++;
        }
        return $copied > 0;
    }

    private function refreshModification($xml_file) {
        if (!is_file($xml_file)) {
            return false;
        }
        $xml = @file_get_contents($xml_file);
        if (!$xml) {
            return false;
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        if (!@$dom->loadXML($xml)) {
            return false;
        }
        $get = function($tag) use ($dom) {
            $node = $dom->getElementsByTagName($tag)->item(0);
            return $node ? trim($node->nodeValue) : '';
        };
        $code = $get('code');
        if ($code === '') {
            return false;
        }
        $this->load->model('setting/modification');
        $existing = $this->model_setting_modification->getModificationByCode($code);
        if ($existing) {
            $this->model_setting_modification->deleteModification($existing['modification_id']);
        }
        $this->model_setting_modification->addModification(array(
            'extension_install_id' => 0,
            'name' => $get('name'),
            'code' => $code,
            'author' => $get('author'),
            'version' => $get('version'),
            'link' => $get('link'),
            'xml' => $xml,
            'status' => 1
        ));
        return true;
    }

    private function removeDirectory($directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
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
        $cpu_percent = $this->cpuPercent(min(100, round(($load / $cores) * 100, 1)));
        $stats = json_decode((string)@file_get_contents($this->pageCacheDir() . 'stats.json'), true);
        $hits = is_array($stats) && isset($stats['hit']) ? (int)$stats['hit'] : 0;
        $misses = is_array($stats) && isset($stats['miss']) ? (int)$stats['miss'] : 0;
        $total = $hits + $misses;
        return array('cpu_percent' => $cpu_percent, 'cpu_load' => round($load, 2), 'cpu_cores' => $cores, 'hits' => $hits, 'misses' => $misses, 'hit_rate' => $total ? round(($hits / $total) * 100, 1) : 0);
    }
    private function cpuPercent($fallback) {
        // Measure two /proc/stat snapshots during this request. This keeps the
        // value live even when the admin session is not persisted between AJAX
        // requests (and avoids relying on the much slower 1-minute load average).
        $before = $this->cpuSample();
        if (!$before) { return $fallback; }
        if (function_exists('usleep')) { @usleep(100000); }
        $after = $this->cpuSample();
        if (!$after) { return $fallback; }
        $total_delta = $after['total'] - (float)$before['total'];
        $idle_delta = $after['idle'] - (float)$before['idle'];
        if ($total_delta <= 0 || $idle_delta < 0) { return $fallback; }
        return round(max(0, min(100, (($total_delta - $idle_delta) / $total_delta) * 100)), 1);
    }
    private function cpuSample() {
        if (!is_readable('/proc/stat')) { return array(); }
        $stat = @file_get_contents('/proc/stat');
        if (!$stat || !preg_match('/^cpu\s+(.+)$/m', $stat, $match)) { return array(); }
        $values = preg_split('/\s+/', trim($match[1]));
        if (count($values) < 5) { return array(); }
        $values = array_map('floatval', array_slice($values, 0, 8));
        $total = array_sum($values);
        $idle = (isset($values[3]) ? $values[3] : 0) + (isset($values[4]) ? $values[4] : 0);
        return array('total' => $total, 'idle' => $idle);
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
