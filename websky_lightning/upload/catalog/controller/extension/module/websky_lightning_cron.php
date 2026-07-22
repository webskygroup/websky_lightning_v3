<?php
class ControllerExtensionModuleWebskyLightningCron extends Controller {
    public function index() {
        $token = $this->config->get('module_websky_lightning_cron_token');
        if (!$token || !isset($this->request->get['token']) || !hash_equals($token, $this->request->get['token'])) { $this->response->addHeader('HTTP/1.1 403 Forbidden'); return; }
        require_once(DIR_SYSTEM . 'library/websky_lightning.php');
        $dir = WebskyLightning::cacheDirectory();
        $files = glob($dir . '*.html') ?: array();
        foreach (glob($dir . '*.tmp') ?: array() as $temporary) { if (is_file($temporary) && filemtime($temporary) < time() - 3600) { @unlink($temporary); } }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(array('ok' => true, 'preserved' => count($files), 'cleared' => 0)));
    }
}
