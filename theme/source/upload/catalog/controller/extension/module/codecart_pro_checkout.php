<?php
class ControllerExtensionModuleCodecartProCheckout extends Controller {
    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $theme_directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        $config_template = (string)$this->config->get('config_template');
        $template_directory = (string)$this->config->get('template_directory');

        return ($config_theme === 'codecart_pro' || $config_theme === 'theme_codecart_pro' || $theme_directory === 'codecart_pro' || $config_template === 'codecart_pro' || strpos($template_directory, 'codecart_pro/') === 0);
    }

    public function saveCustomer() {
        $this->load->language('extension/module/codecart_pro_checkout');

        $json = array();

        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status')) {
            $json['error']['warning'] = $this->language->get('error_theme_disabled');
            $this->sendJson($json);
            return;
        }

        $simple_checkout = $this->config->has('theme_codecart_pro_simple_checkout_status') ? (int)$this->config->get('theme_codecart_pro_simple_checkout_status') : 0;

        if (!$simple_checkout) {
            $json['error']['warning'] = $this->language->get('error_simple_checkout_disabled');
            $this->sendJson($json);
            return;
        }

        if (!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) {
            $json['redirect'] = $this->url->link('checkout/cart', '', true);
            $this->sendJson($json);
            return;
        }

        if ((!$this->cart->hasStock()) && (!$this->config->get('config_stock_checkout'))) {
            $json['redirect'] = $this->url->link('checkout/cart', '', true);
            $this->sendJson($json);
            return;
        }

        $digital_mode = $this->config->has('theme_codecart_pro_checkout_digital_mode') ? (int)$this->config->get('theme_codecart_pro_checkout_digital_mode') : 0;
        $cart_has_shipping = $this->cart->hasShipping();
        // Digital checkout can only be active when OpenCart itself reports that shipping is not required.
        $effective_digital_checkout = $digital_mode && !$cart_has_shipping;

        if ($cart_has_shipping && !$effective_digital_checkout) {
            $json['error']['warning'] = $this->language->get('error_shipping_required');
            $this->sendJson($json);
            return;
        }

        $customer_name = isset($this->request->post['customer_name']) ? trim((string)$this->request->post['customer_name']) : '';
        $email = isset($this->request->post['email']) ? trim((string)$this->request->post['email']) : '';
        $telephone = isset($this->request->post['telephone']) ? trim((string)$this->request->post['telephone']) : '';
        $comment = isset($this->request->post['comment']) ? trim((string)$this->request->post['comment']) : '';

        if ($this->customer->isLogged()) {
            if ($customer_name === '') {
                $customer_name = trim($this->customer->getFirstName() . ' ' . $this->customer->getLastName());
            }
            if ($email === '') {
                $email = (string)$this->customer->getEmail();
            }
            if ($telephone === '') {
                $telephone = (string)$this->customer->getTelephone();
            }
        }

        if ((utf8_strlen($customer_name) < 2) || (utf8_strlen($customer_name) > 128)) {
            $json['error']['customer_name'] = $this->language->get('error_customer_name');
        }

        if ((utf8_strlen($email) > 96) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $json['error']['email'] = $this->language->get('error_email');
        }

        if ($telephone !== '' && ((utf8_strlen($telephone) < 5) || (utf8_strlen($telephone) > 32))) {
            $json['error']['telephone'] = $this->language->get('error_telephone');
        }

        if (utf8_strlen($comment) > 1000) {
            $json['error']['comment'] = $this->language->get('error_comment');
        }

        if (isset($json['error'])) {
            $this->sendJson($json);
            return;
        }

        $parts = preg_split('/\s+/u', $customer_name, 2);
        $firstname = isset($parts[0]) ? trim($parts[0]) : $customer_name;
        $lastname = isset($parts[1]) ? trim($parts[1]) : '';

        if ($lastname === '') {
            $lastname = '-';
        }

        $this->load->model('localisation/country');
        $this->load->model('localisation/zone');

        $country_id = (int)$this->config->get('config_country_id');
        $zone_id = (int)$this->config->get('config_zone_id');
        $country_info = $country_id ? $this->model_localisation_country->getCountry($country_id) : array();
        $zone_info = $zone_id ? $this->model_localisation_zone->getZone($zone_id) : array();

        $address = array(
            'firstname'      => $firstname,
            'lastname'       => $lastname,
            'company'        => '',
            'address_1'      => '-',
            'address_2'      => '',
            'postcode'       => '',
            'city'           => '-',
            'zone_id'        => $zone_id,
            'zone'           => !empty($zone_info['name']) ? $zone_info['name'] : '',
            'zone_code'      => !empty($zone_info['code']) ? $zone_info['code'] : '',
            'country_id'     => $country_id,
            'country'        => !empty($country_info['name']) ? $country_info['name'] : '',
            'iso_code_2'     => !empty($country_info['iso_code_2']) ? $country_info['iso_code_2'] : '',
            'iso_code_3'     => !empty($country_info['iso_code_3']) ? $country_info['iso_code_3'] : '',
            'address_format' => !empty($country_info['address_format']) ? $country_info['address_format'] : '',
            'custom_field'   => array()
        );

        if ($this->customer->isLogged()) {
            $this->session->data['payment_address'] = $address;
            $this->session->data['shipping_address'] = $address;
        } else {
            $customer_group_id = (int)$this->config->get('config_customer_group_id');

            $this->session->data['account'] = 'guest';
            $this->session->data['guest'] = array(
                'customer_group_id' => $customer_group_id,
                'firstname'         => $firstname,
                'lastname'          => $lastname,
                'email'             => $email,
                'telephone'         => $telephone,
                'fax'               => '',
                'custom_field'      => array(),
                'shipping'          => 0,
                'payment'           => $address
            );

            $this->session->data['payment_address'] = $address;
            $this->session->data['shipping_address'] = $address;
        }

        $this->session->data['comment'] = $comment;

        if ($effective_digital_checkout) {
            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
        }

        $this->session->data['ccp_digital_checkout'] = $effective_digital_checkout ? 1 : 0;

        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);

        $json['success'] = $this->language->get('text_customer_saved');
        $json['payment_method_url'] = html_entity_decode($this->url->link('checkout/payment_method', '', true), ENT_QUOTES, 'UTF-8');

        $this->sendJson($json);
    }

    private function sendJson($json) {
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($json));
    }
}
