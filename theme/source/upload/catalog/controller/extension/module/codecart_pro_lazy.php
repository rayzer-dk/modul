<?php
class ControllerExtensionModuleCodecartProLazy extends Controller {
    private function isCodeCartThemeActive() {
        $ccp_config_theme = (string)$this->config->get('config_theme');
        $ccp_theme_directory_key = $ccp_config_theme ? 'theme_' . $ccp_config_theme . '_directory' : '';
        $ccp_theme_directory = $ccp_theme_directory_key ? (string)$this->config->get($ccp_theme_directory_key) : '';
        $ccp_template_directory = (string)$this->config->get('template_directory');

        return (bool)(
            (int)$this->config->get('theme_codecart_pro_status') &&
            ($ccp_config_theme === 'codecart_pro' ||
             $ccp_config_theme === 'theme_codecart_pro' ||
             $ccp_theme_directory === 'codecart_pro' ||
             (string)$this->config->get('config_template') === 'codecart_pro' ||
             strpos($ccp_template_directory, 'codecart_pro/') === 0)
        );
    }

    private function enabled($key, $default = 1) {
        if (method_exists($this->config, 'has') && $this->config->has($key)) {
            return (int)$this->config->get($key) ? 1 : 0;
        }
        $value = $this->config->get($key);
        if ($value === null || $value === '') {
            return (int)$default ? 1 : 0;
        }
        return (int)$value ? 1 : 0;
    }


    private function safeController($route, $args = array(), $context = '') {
        try {
            // OpenCart 3 passes the second loader argument to call_user_func_array().
            // On PHP 8+, an associative array is interpreted as named arguments.
            // Our child controllers accept one settings array, so wrap it as one positional argument.
            $controller_args = !empty($args) ? array($args) : array();

            return $this->load->controller($route, $controller_args);
        } catch (Exception $e) {
            $this->log->write('CodeCart PRO Theme optional lazy controller disabled' . ($context ? ' [' . $context . ']' : '') . ': ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->log->write('CodeCart PRO Theme optional lazy controller disabled' . ($context ? ' [' . $context . ']' : '') . ': ' . $e->getMessage());
        }
        return '';
    }

    public function productBlocks($args = array()) {
        $return_html = is_array($args) && !empty($args);
        if (!$return_html) {
            $this->response->addHeader('Content-Type: text/html; charset=utf-8');
        }

        if (!$this->isCodeCartThemeActive() || !$this->enabled('theme_codecart_pro_product_modules_status', 1) || !$this->enabled('theme_codecart_pro_product_module_product_status', 1)) {
            if ($return_html) { return ''; }
            $this->response->setOutput('');
            return;
        }

        $product_id = isset($args['product_id']) ? (int)$args['product_id'] : (isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0);
        if ($product_id <= 0) {
            if ($return_html) { return ''; }
            $this->response->setOutput('');
            return;
        }

        $limit = isset($args['limit']) ? (int)$args['limit'] : 5;
        $limit = min(5, max(1, $limit ? $limit : 5));
        $html = '';

        // CodeCart PRO v8.7.7: product page must show product blocks reliably.
        // Render blocks server-side when called from product.twig and AJAX when requested directly.
        $category_html = '';
        if ($this->enabled('theme_codecart_pro_product_module_category_related_status', 1)) {
            $category_html = $this->safeController('extension/module/codecart_pro_products', array(
                'type'       => 'category',
                'product_id' => $product_id,
                'limit'      => $limit
            ), 'product_category');
        }
        if ($category_html !== '') {
            $html .= $category_html;
        }

        $related_html = '';
        if ($this->enabled('theme_codecart_pro_product_module_related_status', 1)) {
            $related_html = $this->safeController('extension/module/codecart_pro_products', array(
                'type'       => 'related',
                'product_id' => $product_id,
                'limit'      => $limit
            ), 'product_related');
        }

        if ($related_html !== '') {
            $html .= $related_html;
        } elseif ($this->enabled('theme_codecart_pro_product_module_auto_related_status', 1)) {
            $html .= $this->safeController('extension/module/codecart_pro_products', array(
                'type'       => 'auto_related',
                'product_id' => $product_id,
                'limit'      => $limit
            ), 'product_auto_related');
        }

        $types = array(
            'viewed'     => 'theme_codecart_pro_product_module_viewed_status',
            'latest'     => 'theme_codecart_pro_product_module_latest_status',
            'special'    => 'theme_codecart_pro_product_module_special_status',
            'bestseller' => 'theme_codecart_pro_product_module_bestseller_status'
        );

        foreach ($types as $type => $setting_key) {
            if ($this->enabled($setting_key, in_array($type, array('viewed','latest'), true) ? 1 : 0)) {
                $html .= $this->safeController('extension/module/codecart_pro_products', array(
                    'type'       => $type,
                    'product_id' => $product_id,
                    'limit'      => $limit
                ), 'product_' . $type);
            }
        }

        if ($return_html) {
            return $html;
        }

        $this->response->setOutput($html);
    }

    public function categoryBlocks() {
        $this->response->addHeader('Content-Type: text/html; charset=utf-8');

        if (!$this->isCodeCartThemeActive() || !$this->enabled('theme_codecart_pro_product_modules_status', 1) || !$this->enabled('theme_codecart_pro_product_module_category_status', 1)) {
            $this->response->setOutput('');
            return;
        }

        $category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;
        if ($category_id <= 0) {
            $this->response->setOutput('');
            return;
        }

        $limit = 5;
        $html = '';

        if ($this->enabled('theme_codecart_pro_product_module_category_related_status', 1)) {
            $html .= $this->safeController('extension/module/codecart_pro_products', array(
                'type'        => 'category',
                'category_id' => $category_id,
                'limit'       => $limit
            ), 'category_related');
        }

        if ($this->enabled('theme_codecart_pro_product_module_special_status', 0)) {
            $html .= $this->safeController('extension/module/codecart_pro_products', array(
                'type'  => 'special',
                'limit' => $limit
            ), 'category_special');
        }

        if ($this->enabled('theme_codecart_pro_product_module_viewed_status', 1)) {
            $html .= $this->safeController('extension/module/codecart_pro_products', array(
                'type'  => 'viewed',
                'limit' => $limit
            ), 'category_viewed');
        }

        $this->response->setOutput($html);
    }
}
