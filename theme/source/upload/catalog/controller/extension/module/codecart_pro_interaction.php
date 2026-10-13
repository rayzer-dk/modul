<?php
class ControllerExtensionModuleCodecartProInteraction extends Controller {
    private function tableExists($table) {
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$query->num_rows;
    }

    private function isCodeCartThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $theme_directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $theme_directory = $theme_directory_key ? (string)$this->config->get($theme_directory_key) : '';
        $config_template = (string)$this->config->get('config_template');
        $template_directory = (string)$this->config->get('template_directory');
        $active = ($config_theme === 'codecart_pro' || $config_theme === 'theme_codecart_pro' || $theme_directory === 'codecart_pro' || $config_template === 'codecart_pro' || strpos($template_directory, 'codecart_pro/') === 0);

        return $active && (bool)(int)$this->config->get('theme_codecart_pro_status');
    }

    private function isFeatureEnabled($key, $default = 1) {
        if ($this->config->has($key)) {
            return (bool)(int)$this->config->get($key);
        }

        return (bool)$default;
    }


    private function isPostRequest() {
        return isset($this->request->server['REQUEST_METHOD']) && strtoupper((string)$this->request->server['REQUEST_METHOD']) === 'POST';
    }

    private function isTrustedPostOrigin() {
        $source = '';

        if (!empty($this->request->server['HTTP_ORIGIN'])) {
            $source = (string)$this->request->server['HTTP_ORIGIN'];
        } elseif (!empty($this->request->server['HTTP_REFERER'])) {
            $source = (string)$this->request->server['HTTP_REFERER'];
        }

        if ($source === '') {
            return false;
        }

        $source_host = strtolower((string)parse_url($source, PHP_URL_HOST));
        if ($source_host === '') {
            return false;
        }

        $allowed_hosts = array();
        foreach (array((string)$this->config->get('config_url'), (string)$this->config->get('config_ssl')) as $url) {
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            if ($host !== '') {
                $allowed_hosts[$host] = true;
            }
        }

        if (defined('HTTP_SERVER')) {
            $host = strtolower((string)parse_url(HTTP_SERVER, PHP_URL_HOST));
            if ($host !== '') {
                $allowed_hosts[$host] = true;
            }
        }

        if (defined('HTTPS_SERVER')) {
            $host = strtolower((string)parse_url(HTTPS_SERVER, PHP_URL_HOST));
            if ($host !== '') {
                $allowed_hosts[$host] = true;
            }
        }

        return isset($allowed_hosts[$source_host]);
    }

    private function clientIp() {
        $ip = isset($this->request->server['REMOTE_ADDR']) ? trim((string)$this->request->server['REMOTE_ADDR']) : '';

        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        return utf8_substr($ip, 0, 64);
    }

    private function tooManyRecentRequests($table, $ip, $limit, $seconds) {
        if (!in_array((string)$table, array('codecart_pro_callback_request'), true) || $ip === '' || !$this->tableExists($table)) {
            return false;
        }

        $limit = min(20, max(1, (int)$limit));
        $seconds = min(3600, max(10, (int)$seconds));
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . $table . "` WHERE ip = '" . $this->db->escape($ip) . "' AND date_added >= DATE_SUB(NOW(), INTERVAL " . (int)$seconds . " SECOND)");

        return isset($query->row['total']) && (int)$query->row['total'] >= $limit;
    }

    private function ensureCallbackStorage() {
        return $this->tableExists('codecart_pro_callback_request');
    }

    private function ensureSubscriberStorage() {
        return $this->tableExists('codecart_pro_subscriber');
    }

    public function callback() {
        $this->load->language('extension/module/codecart_pro_interaction');
        $json = array();

        if (!$this->isPostRequest() || !$this->isTrustedPostOrigin()) {
            $json['error'] = $this->language->get('error_request');
            return $this->respond($json);
        }

        if (!$this->isCodeCartThemeActive() || !$this->isFeatureEnabled('theme_codecart_pro_enable_callback', 1)) {
            $json['error'] = $this->language->get('error_disabled');
            return $this->respond($json);
        }

        if (!$this->ensureCallbackStorage()) {
            $json['error'] = $this->language->get('error_storage');
            return $this->respond($json);
        }

        if (!empty($this->request->post['ccp_website'])) {
            $json['error'] = $this->language->get('error_antispam');
        }
        if (isset($this->session->data['ccp_callback_last']) && (time() - (int)$this->session->data['ccp_callback_last']) < 20) {
            $json['error'] = $this->language->get('error_rate');
        }
        $client_ip = $this->clientIp();
        if (!$json && $this->tooManyRecentRequests('codecart_pro_callback_request', $client_ip, 3, 60)) {
            $json['error'] = $this->language->get('error_rate');
        }

        $name = isset($this->request->post['name']) ? trim((string)$this->request->post['name']) : '';
        $phone = isset($this->request->post['phone']) ? trim((string)$this->request->post['phone']) : '';
        $email = isset($this->request->post['email']) ? trim((string)$this->request->post['email']) : '';
        $comment = isset($this->request->post['comment']) ? trim((string)$this->request->post['comment']) : '';
        $page_url = isset($this->request->post['page_url']) ? utf8_substr(trim((string)$this->request->post['page_url']), 0, 2000) : '';
        $name = utf8_substr($name, 0, 128);
        $phone = utf8_substr($phone, 0, 64);
        $email = utf8_substr($email, 0, 128);
        $comment = utf8_substr($comment, 0, 5000);

        if (!$phone || utf8_strlen($phone) < 5) {
            $json['error'] = $this->language->get('error_phone');
        }
        if ((int)$this->config->get('theme_codecart_pro_callback_required_email') && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $json['error'] = $this->language->get('error_email');
        }
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $json['error'] = $this->language->get('error_email');
        }

        if (!$json) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_callback_request` SET store_id = '" . (int)$this->config->get('config_store_id') . "', language_id = '" . (int)$this->config->get('config_language_id') . "', customer_name = '" . $this->db->escape($name) . "', customer_phone = '" . $this->db->escape($phone) . "', customer_email = '" . $this->db->escape($email) . "', comment = '" . $this->db->escape($comment) . "', page_url = '" . $this->db->escape($page_url) . "', ip = '" . $this->db->escape($client_ip) . "', user_agent = '" . $this->db->escape(isset($this->request->server['HTTP_USER_AGENT']) ? utf8_substr($this->request->server['HTTP_USER_AGENT'], 0, 255) : '') . "', date_added = NOW()");
            $this->session->data['ccp_callback_last'] = time();
            $mail_message = $this->language->get('text_mail_callback_title') . "\n\n";
            $mail_message .= $this->language->get('text_mail_name') . ': ' . $name . "\n";
            $mail_message .= $this->language->get('text_mail_phone') . ': ' . $phone . "\n";
            $mail_message .= $this->language->get('text_mail_email') . ': ' . $email . "\n";
            $mail_message .= $this->language->get('text_mail_comment') . ': ' . $comment . "\n";
            $mail_message .= $this->language->get('text_mail_page') . ': ' . $page_url . "\n";
            $this->sendMail($this->language->get('text_mail_callback_subject'), $mail_message);
            $texts = $this->config->get('theme_codecart_pro_callback_success_text');
            $lang_id = (int)$this->config->get('config_language_id');
            $json['success'] = is_array($texts) && !empty($texts[$lang_id]) ? $texts[$lang_id] : $this->language->get('text_success_callback');
        }

        return $this->respond($json);
    }

    public function subscribe() {
        $this->load->language('extension/module/codecart_pro_interaction');
        $json = array();

        if (!$this->isPostRequest() || !$this->isTrustedPostOrigin()) {
            $json['error'] = $this->language->get('error_request');
            return $this->respond($json);
        }

        if (!$this->isCodeCartThemeActive() || !$this->isFeatureEnabled('theme_codecart_pro_enable_subscribe', 1)) {
            $json['error'] = $this->language->get('error_disabled');
            return $this->respond($json);
        }
        if (!$this->ensureSubscriberStorage()) {
            $json['error'] = $this->language->get('error_storage');
            return $this->respond($json);
        }
        if (!empty($this->request->post['ccp_website'])) {
            $json['error'] = $this->language->get('error_antispam');
        }
        if (isset($this->session->data['ccp_subscribe_last']) && (time() - (int)$this->session->data['ccp_subscribe_last']) < 15) {
            $json['error'] = $this->language->get('error_rate');
        }

        $email = isset($this->request->post['email']) ? trim((string)$this->request->post['email']) : '';
        $name = isset($this->request->post['name']) ? utf8_substr(trim((string)$this->request->post['name']), 0, 128) : '';
        $source = isset($this->request->post['source']) ? trim((string)$this->request->post['source']) : 'footer';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $json['error'] = $this->language->get('error_email');
        }

        if (!$json) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_subscriber` SET store_id = '" . (int)$this->config->get('config_store_id') . "', language_id = '" . (int)$this->config->get('config_language_id') . "', email = '" . $this->db->escape($email) . "', name = '" . $this->db->escape($name) . "', source = '" . $this->db->escape(utf8_substr($source, 0, 64)) . "', ip = '" . $this->db->escape($this->clientIp()) . "', status = '1', date_added = NOW() ON DUPLICATE KEY UPDATE name = VALUES(name), source = VALUES(source), ip = VALUES(ip), status = '1'");
            $this->session->data['ccp_subscribe_last'] = time();
            $texts = $this->config->get('theme_codecart_pro_subscribe_success_text');
            $lang_id = (int)$this->config->get('config_language_id');
            $json['success'] = is_array($texts) && !empty($texts[$lang_id]) ? $texts[$lang_id] : $this->language->get('text_success_subscribe');
        }

        return $this->respond($json);
    }


    public function cartProductQuantity() {
        $this->load->language('extension/module/codecart_pro_interaction');
        $json = array('quantity' => 0, 'matched' => false);

        if (!$this->isCodeCartThemeActive()) {
            $json['error'] = $this->language->get('error_disabled');
            return $this->respond($json);
        }

        // OpenCart 3 Request exposes GET and POST separately; there is no request property.
        // POST has priority because cart synchronization is a state-related operation.
        $request_data = array_merge((array)$this->request->get, (array)$this->request->post);
        $product_id = isset($request_data['product_id']) ? (int)$request_data['product_id'] : 0;
        $quantity = 0;

        if ($product_id > 0) {
            $option = isset($request_data['option']) && is_array($request_data['option']) ? array_filter($request_data['option']) : array();
            $recurring_id = isset($request_data['recurring_id']) ? (int)$request_data['recurring_id'] : 0;
            $option_json = json_encode($option);

            $query = $this->db->query("SELECT SUM(quantity) AS quantity FROM `" . DB_PREFIX . "cart` WHERE api_id = '" . (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0) . "' AND customer_id = '" . (int)$this->customer->getId() . "' AND session_id = '" . $this->db->escape($this->session->getId()) . "' AND product_id = '" . (int)$product_id . "' AND recurring_id = '" . (int)$recurring_id . "' AND `option` = '" . $this->db->escape($option_json) . "'");

            if ($query->row && (int)$query->row['quantity'] > 0) {
                $quantity = (int)$query->row['quantity'];
                $json['matched'] = true;
            }
        }

        $json['quantity'] = $quantity;
        return $this->respond($json);
    }

    public function cartSyncQuantity() {
        $this->load->language('extension/module/codecart_pro_interaction');
        $json = array();

        if (!$this->isPostRequest() || !$this->isTrustedPostOrigin()) {
            $json['error'] = $this->language->get('error_request');
            return $this->respond($json);
        }

        if (!$this->isCodeCartThemeActive()) {
            $json['error'] = $this->language->get('error_disabled');
            return $this->respond($json);
        }

        $product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
        $quantity = isset($this->request->post['quantity']) ? (int)$this->request->post['quantity'] : 1;
        if ($quantity < 1) {
            $quantity = 1;
        }

        if ($product_id > 0) {
            $option = isset($this->request->post['option']) && is_array($this->request->post['option']) ? array_filter($this->request->post['option']) : array();
            $recurring_id = isset($this->request->post['recurring_id']) ? (int)$this->request->post['recurring_id'] : 0;
            $option_json = json_encode($option);

            $query = $this->db->query("SELECT cart_id FROM `" . DB_PREFIX . "cart` WHERE api_id = '" . (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0) . "' AND customer_id = '" . (int)$this->customer->getId() . "' AND session_id = '" . $this->db->escape($this->session->getId()) . "' AND product_id = '" . (int)$product_id . "' AND recurring_id = '" . (int)$recurring_id . "' AND `option` = '" . $this->db->escape($option_json) . "' ORDER BY cart_id DESC LIMIT 1");

            if ($query->num_rows) {
                $this->cart->update((int)$query->row['cart_id'], $quantity);
                unset($this->session->data['shipping_method']);
                unset($this->session->data['shipping_methods']);
                unset($this->session->data['payment_method']);
                unset($this->session->data['payment_methods']);
                $json['success'] = true;
                $json['quantity'] = $quantity;
            } else {
                $json['success'] = false;
                $json['warning'] = $this->language->get('error_cart_line');
            }
        } else {
            $json['success'] = false;
            $json['warning'] = $this->language->get('error_product_id');
        }

        return $this->respond($json);
    }

    private function sendMail($subject, $message) {
        try {
            $mail = new Mail($this->config->get('config_mail_engine'));
            $mail->parameter = $this->config->get('config_mail_parameter');
            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
            $mail->smtp_port = $this->config->get('config_mail_smtp_port');
            $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
            $mail->setTo($this->config->get('config_email'));
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject($subject);
            $mail->setText($message);
            $mail->send();
        } catch (Throwable $e) {
            $this->log->write('CodeCart PRO interaction mail error: ' . $e->getMessage());
        }
    }

    private function respond($json) {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
