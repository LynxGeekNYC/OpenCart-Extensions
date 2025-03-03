<?php
class ControllerExtensionModuleAdvancedAutopostLog extends Controller {
    public function index() {
        $this->load->language('extension/module/advanced_autopost_log');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/advanced_autopost');

        $data['logs'] = array();

        $results = $this->model_extension_module_advanced_autopost->getLogs();
        foreach ($results as $result) {
            $data['logs'][] = array(
                'product_id' => $result['product_id'],
                'platform'   => $result['platform'],
                'status'     => $result['status'],
                'message'    => $result['message'],
                'date_added' => $result['date_added']
            );
        }

        $data['clear'] = $this->url->link('extension/module/advanced_autopost_log/clear', 'user_token=' . $this->session->data['user_token'], true);

        // Breadcrumbs
        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/advanced_autopost_log', 'user_token=' . $this->session->data['user_token'], true)
            )
        );

        $data['heading_title'] = $this->language->get('heading_title');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/advanced_autopost_log', $data));
    }

    public function clear() {
        $this->load->language('extension/module/advanced_autopost_log');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('extension/module/advanced_autopost');

        $this->model_extension_module_advanced_autopost->clearLogs();

        $this->session->data['success'] = $this->language->get('text_success');

        $this->response->redirect($this->url->link('extension/module/advanced_autopost_log', 'user_token=' . $this->session->data['user_token'], true));
    }
}
