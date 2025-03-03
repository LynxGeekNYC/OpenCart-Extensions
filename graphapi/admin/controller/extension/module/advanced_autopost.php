<?php
class ControllerExtensionModuleAdvancedAutopost extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/advanced_autopost');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/module/advanced_autopost');

        // Save settings if form submitted
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_advanced_autopost', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/advanced_autopost', 'user_token=' . $this->session->data['user_token'], true));
        }

        // Errors
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/advanced_autopost', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/advanced_autopost', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        
        // Link to log page
        $data['log_link'] = $this->url->link('extension/module/advanced_autopost_log', 'user_token=' . $this->session->data['user_token'], true);

        // Load saved settings
        $config_keys = array(
            'module_advanced_autopost_status',
            'module_advanced_autopost_auto_mode',
            'module_advanced_autopost_facebook_token',
            'module_advanced_autopost_instagram_token',
            'module_advanced_autopost_instagram_user_id',
            'module_advanced_autopost_post_template',
            'module_advanced_autopost_post_images'
        );

        foreach ($config_keys as $key) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $data[$key] = $this->config->get($key);
            }
        }

        // Defaults
        if (!$data['module_advanced_autopost_post_template']) {
            $data['module_advanced_autopost_post_template'] = 'New product: {product_name} {product_url}';
        }

        // Output
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/advanced_autopost', $data));
    }

    public function install() {
        if ($this->user->hasPermission('modify', 'extension/module/advanced_autopost')) {
            // Create log table
            $this->load->model('extension/module/advanced_autopost');
            $this->model_extension_module_advanced_autopost->createAutopostLogTable();

            // Register events: after add/edit product
            $this->load->model('setting/event');
            $this->model_setting_event->addEvent(
                'advanced_autopost_add',
                'admin/model/catalog/product/addProduct/after',
                'extension/module/advanced_autopost/eventPostProduct'
            );
            $this->model_setting_event->addEvent(
                'advanced_autopost_edit',
                'admin/model/catalog/product/editProduct/after',
                'extension/module/advanced_autopost/eventPostProduct'
            );
        }
    }

    public function uninstall() {
        if ($this->user->hasPermission('modify', 'extension/module/advanced_autopost')) {
            // Remove events
            $this->load->model('setting/event');
            $this->model_setting_event->deleteEventByCode('advanced_autopost_add');
            $this->model_setting_event->deleteEventByCode('advanced_autopost_edit');
        }
    }

    public function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/advanced_autopost')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}
