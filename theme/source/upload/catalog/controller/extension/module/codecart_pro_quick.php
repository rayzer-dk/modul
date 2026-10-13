<?php
class ControllerExtensionModuleCodecartProQuick extends Controller {
    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $theme_directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        $config_template = (string)$this->config->get('config_template');
        $template_directory = (string)$this->config->get('template_directory');

        return ($config_theme === 'codecart_pro' || $config_theme === 'theme_codecart_pro' || $theme_directory === 'codecart_pro' || $config_template === 'codecart_pro' || strpos($template_directory, 'codecart_pro/') === 0);
    }

    private function respond($json) {
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json));
    }

    public function informationMode() {
        $schema_version = (int)$this->config->get('theme_codecart_pro_schema_version');
        $information_mode = ($schema_version > 0 && $schema_version < 852)
            ? 1
            : (int)$this->config->get('theme_codecart_pro_information_mode');

        $active = $this->isCodeCartProThemeActive()
            && (int)$this->config->get('theme_codecart_pro_status')
            && $information_mode;

        $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->response->addHeader('Pragma: no-cache');
        $this->respond(array(
            'active' => $active ? 1 : 0,
            'version' => '8.7.7'
        ));
    }

    private function tableExists($table) {
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$query->num_rows;
    }

    private function columnExists($table, $column) {
        if (!$this->tableExists($table)) {
            return false;
        }
        $query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $this->db->escape($table) . "` LIKE '" . $this->db->escape($column) . "'");
        return (bool)$query->num_rows;
    }

    private function ensureStorage() {
        $required = array('quantity', 'product_options', 'product_option_data', 'source');
        if (!$this->tableExists('codecart_pro_quick_order')) {
            return false;
        }
        foreach ($required as $column) {
            if (!$this->columnExists('codecart_pro_quick_order', $column)) {
                return false;
            }
        }
        return true;
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

    private function tooManyRecentRequests($ip, $limit, $seconds) {
        if ($ip === '' || !$this->tableExists('codecart_pro_quick_order')) {
            return false;
        }

        $limit = min(20, max(1, (int)$limit));
        $seconds = min(3600, max(10, (int)$seconds));
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "codecart_pro_quick_order` WHERE ip = '" . $this->db->escape($ip) . "' AND date_added >= DATE_SUB(NOW(), INTERVAL " . (int)$seconds . " SECOND)");

        return isset($query->row['total']) && (int)$query->row['total'] >= $limit;
    }

    private function missingRequiredOptions($product_id, $posted_options) {
        $missing = array();
        if (!is_array($posted_options)) {
            $posted_options = array();
        }
        $this->load->model('catalog/product');
        $product_options = $this->model_catalog_product->getProductOptions((int)$product_id);
        foreach ($product_options as $option) {
            if (empty($option['required'])) {
                continue;
            }
            $product_option_id = (int)$option['product_option_id'];
            $value = array_key_exists($product_option_id, $posted_options) ? $posted_options[$product_option_id] : null;
            if ($value === null || $value === '' || (is_array($value) && !count(array_filter($value, 'strlen')))) {
                $missing[] = html_entity_decode($option['name'], ENT_QUOTES, 'UTF-8');
            }
        }
        return $missing;
    }

    private function selectedOptionsText($product_id, $posted_options) {
        $rows = array();
        $data = array();

        if (!is_array($posted_options)) {
            return array('', array());
        }

        $this->load->model('catalog/product');
        $product_options = $this->model_catalog_product->getProductOptions((int)$product_id);

        foreach ($product_options as $option) {
            $product_option_id = (int)$option['product_option_id'];
            if (!array_key_exists($product_option_id, $posted_options)) {
                continue;
            }

            $raw_value = $posted_options[$product_option_id];
            $option_name = html_entity_decode($option['name'], ENT_QUOTES, 'UTF-8');
            $option_type = isset($option['type']) ? $option['type'] : '';
            $values = array();
            $stored_values = array();

            if (in_array($option_type, array('select', 'radio', 'image'), true)) {
                $selected_id = (int)$raw_value;
                foreach ($option['product_option_value'] as $option_value) {
                    if ((int)$option_value['product_option_value_id'] === $selected_id) {
                        $value_text = html_entity_decode($option_value['name'], ENT_QUOTES, 'UTF-8');
                        if (!empty($option_value['price'])) {
                            $value_text .= ' (' . $option_value['price_prefix'] . $this->currency->format($this->tax->calculate($option_value['price'], isset($option_value['tax_class_id']) ? $option_value['tax_class_id'] : 0, $this->config->get('config_tax')), $this->session->data['currency']) . ')';
                        }
                        $values[] = $value_text;
                        $stored_values[] = array('product_option_value_id' => $selected_id, 'name' => $value_text);
                        break;
                    }
                }
            } elseif ($option_type === 'checkbox') {
                $selected = is_array($raw_value) ? $raw_value : array($raw_value);
                foreach ($option['product_option_value'] as $option_value) {
                    if (in_array((string)$option_value['product_option_value_id'], array_map('strval', $selected), true)) {
                        $value_text = html_entity_decode($option_value['name'], ENT_QUOTES, 'UTF-8');
                        if (!empty($option_value['price'])) {
                            $value_text .= ' (' . $option_value['price_prefix'] . $this->currency->format($this->tax->calculate($option_value['price'], isset($option_value['tax_class_id']) ? $option_value['tax_class_id'] : 0, $this->config->get('config_tax')), $this->session->data['currency']) . ')';
                        }
                        $values[] = $value_text;
                        $stored_values[] = array('product_option_value_id' => (int)$option_value['product_option_value_id'], 'name' => $value_text);
                    }
                }
            } else {
                $value_text = is_array($raw_value) ? implode(', ', array_map('trim', $raw_value)) : trim((string)$raw_value);
                if ($value_text !== '') {
                    $values[] = $value_text;
                    $stored_values[] = array('value' => $value_text);
                }
            }

            if ($values) {
                $rows[] = $option_name . ': ' . implode(', ', $values);
                $data[] = array(
                    'product_option_id' => $product_option_id,
                    'name' => $option_name,
                    'type' => $option_type,
                    'values' => $stored_values
                );
            }
        }

        return array(implode("\n", $rows), $data);
    }

    public function quickview() {
        $this->load->language('extension/module/codecart_pro_quick');
        $json = array();

        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_enable_quick_view')) {
            $json['error'] = $this->language->get('error_disabled');
            return $this->respond($json);
        }

        $product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $product = $this->model_catalog_product->getProduct($product_id);

        if (!$product) {
            $json['error'] = $this->language->get('error_product');
        } else {
            $image = $product['image'] ? $this->model_tool_image->resize($product['image'], 420, 267) : $this->model_tool_image->resize('placeholder.png', 420, 267);
            if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
                $price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
            } else {
                $price = false;
            }
            $special = (float)$product['special'] ? $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']) : false;
            $stock = ($product['quantity'] <= 0) ? $product['stock_status'] : $this->language->get('text_instock');
            $description = utf8_substr(trim(strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8'))), 0, 360);
            $html = '<div class="ccp-quick-view-grid"><div><img src="' . $image . '" alt="' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . '" width="420" height="267" decoding="async" /></div><div><h3 class="ccp-quick-title">' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . '</h3><div class="ccp-product-meta">' . $this->language->get('text_model') . ' ' . htmlspecialchars($product['model'], ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($stock, ENT_QUOTES, 'UTF-8') . '</div>';
            if ($price) {
                $html .= '<div class="ccp-quick-price">' . ($special ? $special . '<span class="ccp-quick-old-price">' . $price . '</span>' : $price) . '</div>';
            }
            $html .= '<div class="ccp-quick-desc">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</div><div class="ccp-mt-15 ccp-flex ccp-gap-2 ccp-flex-wrap"><button type="button" class="btn ccp-btn" onclick="cart.add(\'' . (int)$product_id . '\');"><i class="fa fa-shopping-cart"></i> ' . $this->language->get('button_cart') . '</button>';
            if ((int)$this->config->get('theme_codecart_pro_enable_quick_order')) {
                $html .= '<button type="button" class="btn ccp-btn-outline ccp-js-quick-order" data-product-id="' . (int)$product_id . '" data-product-name="' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . '"><i class="fa fa-phone"></i> ' . $this->language->get('button_quick_order') . '</button>';
            }
            $html .= '<a class="btn btn-default" href="' . $this->url->link('product/product', 'product_id=' . (int)$product_id) . '">' . $this->language->get('button_more') . '</a></div></div></div>';
            $json['success'] = true;
            $json['html'] = $html;
        }

        return $this->respond($json);
    }

    public function quickorder() {
        $this->load->language('extension/module/codecart_pro_quick');
        $json = array();

        if (!$this->isPostRequest() || !$this->isTrustedPostOrigin()) {
            $json['error'] = $this->language->get('error_request');
            return $this->respond($json);
        }

        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_enable_quick_order')) {
            $json['error'] = $this->language->get('error_disabled');
            return $this->respond($json);
        }

        if (!$this->ensureStorage()) {
            $json['error'] = $this->language->get('error_storage');
            return $this->respond($json);
        }

        if (!empty($this->request->post['ccp_website'])) {
            $json['error'] = $this->language->get('error_antispam');
        }

        if (isset($this->session->data['ccp_quick_order_last']) && (time() - (int)$this->session->data['ccp_quick_order_last']) < 15) {
            $json['error'] = $this->language->get('error_rate');
        }
        $client_ip = $this->clientIp();
        if (!$json && $this->tooManyRecentRequests($client_ip, 3, 60)) {
            $json['error'] = $this->language->get('error_rate');
        }

        $product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
        $quantity = isset($this->request->post['quantity']) ? max(1, (int)$this->request->post['quantity']) : 1;
        $name = isset($this->request->post['name']) ? trim($this->request->post['name']) : '';
        $phone = isset($this->request->post['phone']) ? trim($this->request->post['phone']) : '';
        $email = isset($this->request->post['email']) ? trim($this->request->post['email']) : '';
        $comment = isset($this->request->post['comment']) ? trim($this->request->post['comment']) : '';
        $page_url = isset($this->request->post['page_url']) ? trim($this->request->post['page_url']) : '';
        $source = isset($this->request->post['source']) ? utf8_substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$this->request->post['source']), 0, 32) : '';
        $name = utf8_substr($name, 0, 128);
        $phone = utf8_substr($phone, 0, 64);
        $email = utf8_substr($email, 0, 128);
        $comment = utf8_substr($comment, 0, 5000);
        $page_url = utf8_substr($page_url, 0, 2000);
        $quantity = min(9999, $quantity);
        $posted_options = (isset($this->request->post['option']) && is_array($this->request->post['option'])) ? $this->request->post['option'] : array();

        if (!$phone || utf8_strlen($phone) < 5) {
            $json['error'] = $this->language->get('error_phone');
        }

        if ((int)$this->config->get('theme_codecart_pro_quick_order_required_email') && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $json['error'] = $this->language->get('error_email');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $json['error'] = $this->language->get('error_email');
        }

        $this->load->model('catalog/product');
        $product = $this->model_catalog_product->getProduct($product_id);
        if (!$product) {
            $json['error'] = $this->language->get('error_product');
        }

        if ($product && $source === 'product') {
            $missing_options = $this->missingRequiredOptions($product_id, $posted_options);
            if ($missing_options) {
                $json['error'] = $this->language->get('error_option_required') . ' ' . implode(', ', $missing_options);
            }
        }

        list($option_text, $option_data) = $product ? $this->selectedOptionsText($product_id, $posted_options) : array('', array());

        if (!$json) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_quick_order` SET store_id = '" . (int)$this->config->get('config_store_id') . "', language_id = '" . (int)$this->config->get('config_language_id') . "', product_id = '" . (int)$product_id . "', product_name = '" . $this->db->escape($product['name']) . "', quantity = '" . (int)$quantity . "', customer_name = '" . $this->db->escape($name) . "', customer_phone = '" . $this->db->escape($phone) . "', customer_email = '" . $this->db->escape($email) . "', comment = '" . $this->db->escape($comment) . "', product_options = '" . $this->db->escape($option_text) . "', product_option_data = '" . $this->db->escape(json_encode($option_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "', page_url = '" . $this->db->escape($page_url) . "', source = '" . $this->db->escape($source) . "', ip = '" . $this->db->escape($client_ip) . "', user_agent = '" . $this->db->escape(isset($this->request->server['HTTP_USER_AGENT']) ? utf8_substr($this->request->server['HTTP_USER_AGENT'], 0, 255) : '') . "', date_added = NOW()");
            $this->session->data['ccp_quick_order_last'] = time();

            $to = trim((string)$this->config->get('theme_codecart_pro_quick_order_email'));
            if (!$to) {
                $to = $this->config->get('config_email');
            }

            $message = $this->language->get('text_mail_subject') . "\n\n";
            $message .= $this->language->get('text_product') . ': ' . $product['name'] . "\n";
            $message .= 'ID: ' . (int)$product_id . "\n";
            $message .= $this->language->get('text_quantity') . ': ' . (int)$quantity . "\n";
            if ($option_text) {
                $message .= $this->language->get('text_options') . ":\n" . $option_text . "\n";
            }
            $message .= $this->language->get('text_name') . ': ' . $name . "\n";
            $message .= $this->language->get('text_phone') . ': ' . $phone . "\n";
            $message .= 'Email: ' . $email . "\n";
            $message .= $this->language->get('text_comment') . ': ' . $comment . "\n";
            $message .= $this->language->get('text_page') . ': ' . $page_url . "\n";

            try {
                $mail = new Mail($this->config->get('config_mail_engine'));
                $mail->parameter = $this->config->get('config_mail_parameter');
                $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
                $mail->setTo($to);
                $mail->setFrom($this->config->get('config_email'));
                $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
                $mail->setSubject($this->language->get('text_mail_subject'));
                $mail->setText($message);
                $mail->send();
            } catch (Throwable $e) {
                $this->log->write('CodeCart PRO quick order mail error: ' . $e->getMessage());
            }

            $success_texts = $this->config->get('theme_codecart_pro_quick_order_success_text');
            $lang_id = (int)$this->config->get('config_language_id');
            $json['success'] = is_array($success_texts) && !empty($success_texts[$lang_id]) ? $success_texts[$lang_id] : $this->language->get('text_success');
        }

        return $this->respond($json);
    }
}
