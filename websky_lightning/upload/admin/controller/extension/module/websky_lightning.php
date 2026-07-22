<?php
class ControllerExtensionModuleWebskyLightning extends Controller {
    private $error = array();
    public function index() {
        $this->load->language('extension/module/websky_lightning');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('websky_lightning', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['action'] = $this->url->link('extension/module/websky_lightning', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['websky_lightning_status'] = isset($this->request->post['websky_lightning_status']) ? $this->request->post['websky_lightning_status'] : $this->config->get('websky_lightning_status');
        $data['header'] = $this->load->controller('common/header'); $data['column_left'] = $this->load->controller('common/column_left'); $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/websky_lightning', $data));
    }
    protected function validate() { if (!$this->user->hasPermission('modify', 'extension/module/websky_lightning')) { $this->error['warning'] = $this->language->get('error_permission'); } return !$this->error; }
    public function install() { $this->load->model('user/user_group'); $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/websky_lightning'); $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/websky_lightning'); }
}
