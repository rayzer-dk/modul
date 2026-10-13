<?php
class ControllerExtensionModuleCodecartProSearch extends Controller {
    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $theme_directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        $config_template = (string)$this->config->get('config_template');
        $template_directory = (string)$this->config->get('template_directory');

        return ($config_theme === 'codecart_pro' || $config_theme === 'theme_codecart_pro' || $theme_directory === 'codecart_pro' || $config_template === 'codecart_pro' || strpos($template_directory, 'codecart_pro/') === 0);
    }

    public function live() {
        $this->load->language('extension/module/codecart_pro_search');
        $json = array('products' => array(), 'categories' => array(), 'total' => 0);

        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_enable_live_search')) {
            return $this->respond($json);
        }

        $search = isset($this->request->get['search']) ? trim((string)$this->request->get['search']) : '';
        if ($search === '' && isset($this->request->get['q'])) {
            $search = trim((string)$this->request->get['q']);
        }

        $min_chars = (int)$this->config->get('theme_codecart_pro_live_search_min_chars');
        if ($min_chars < 1) { $min_chars = 2; }
        if (utf8_strlen($search) < $min_chars) {
            return $this->respond($json);
        }

        $limit = (int)$this->config->get('theme_codecart_pro_live_search_limit');
        if ($limit < 3) { $limit = 6; }
        if ($limit > 20) { $limit = 20; }

        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $filter_data = array(
            'filter_name' => $search,
            'filter_tag' => $search,
            'filter_description' => false,
            'start' => 0,
            'limit' => $limit
        );

        $products = $this->model_catalog_product->getProducts($filter_data);
        foreach ($products as $product) {
            $image = $product['image'] ? $this->model_tool_image->resize($product['image'], 80, 80) : $this->model_tool_image->resize('placeholder.png', 80, 80);
            if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
                $price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
            } else {
                $price = '';
            }
            $special = (float)$product['special'] ? $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']) : '';
            $json['products'][] = array(
                'product_id' => (int)$product['product_id'],
                'name' => html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
                'model' => $product['model'],
                'image' => $image,
                'price' => $price,
                'special' => $special,
                'href' => $this->url->link('product/product', 'product_id=' . (int)$product['product_id'])
            );
        }

        $category_query = $this->db->query("SELECT c.category_id, cd.name FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id) WHERE c.status = '1' AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND cd.name LIKE '%" . $this->db->escape($search) . "%' ORDER BY cd.name ASC LIMIT " . (int)min(6, $limit));
        foreach ($category_query->rows as $category) {
            $json['categories'][] = array(
                'category_id' => (int)$category['category_id'],
                'name' => html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8'),
                'href' => $this->url->link('product/category', 'path=' . (int)$category['category_id'])
            );
        }

        $json['total'] = count($json['products']) + count($json['categories']);
        return $this->respond($json);
    }

    private function respond($json) {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
