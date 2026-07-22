<?php
class ControllerExtensionModuleWebskyLightningCron extends Controller {
    public function index() {
        $token = $this->config->get('module_websky_lightning_cron_token');
        if (!$token || !isset($this->request->get['token']) || !hash_equals($token, $this->request->get['token'])) { $this->response->addHeader('HTTP/1.1 403 Forbidden'); return; }
        $dir = DIR_CACHE . 'websky_lightning/'; $count = 0;
        if (is_dir($dir)) foreach (glob($dir . '*.html') ?: array() as $file) { if (@unlink($file)) $count++; }
        $this->response->setOutput(json_encode(array('ok' => true, 'cleared' => $count)));
    }
}
