<?php
class ControllerExtensionModuleCodecartProProducts extends Controller {
    private $ccpColumnCache = array();
    private $ccpSecondImageCache = array();

    private function isCodeCartProThemeActive() {
        $ccp_config_theme = (string)$this->config->get('config_theme');
        $ccp_theme_directory_key = $ccp_config_theme ? 'theme_' . $ccp_config_theme . '_directory' : '';
        $ccp_theme_directory = $ccp_theme_directory_key ? (string)$this->config->get($ccp_theme_directory_key) : '';
        $ccp_template_directory = (string)$this->config->get('template_directory');
        $ccp_config_template = (string)$this->config->get('config_template');

        return ($ccp_config_theme === 'codecart_pro' || $ccp_config_theme === 'theme_codecart_pro' || $ccp_theme_directory === 'codecart_pro' || $ccp_config_template === 'codecart_pro' || strpos($ccp_template_directory, 'codecart_pro/') === 0);
    }

    private function canRenderProductsTemplate() {
        $ccp_template_directory = (string)$this->config->get('template_directory');
        if ($ccp_template_directory && is_file(DIR_TEMPLATE . $ccp_template_directory . 'extension/module/codecart_pro_products.twig')) {
            return true;
        }

        if (is_file(DIR_TEMPLATE . 'default/template/extension/module/codecart_pro_products.twig')) {
            return true;
        }

        return false;
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

    public function index($setting = array()) {
        if (!$this->isCodeCartProThemeActive()) {
            return '';
        }

        if (!$this->canRenderProductsTemplate()) {
            return '';
        }
        if (!(int)$this->config->get('theme_codecart_pro_status')) {
            return '';
        }

        if (!$this->enabled('theme_codecart_pro_product_modules_status', 1)) {
            return '';
        }

        $this->load->language('extension/module/codecart_pro_products');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $type = isset($setting['type']) ? (string)$setting['type'] : (isset($this->request->get['type']) ? (string)$this->request->get['type'] : 'latest');
        $allowed = array('latest','special','bestseller','viewed','related','auto_related','category');
        if (!in_array($type, $allowed, true)) { $type = 'latest'; }

        $limit = isset($setting['limit']) ? (int)$setting['limit'] : (int)$this->config->get('theme_codecart_pro_product_module_limit');
        $limit = min(12, max(1, $limit ? $limit : 5));

        $layout = isset($setting['layout']) ? (string)$setting['layout'] : (string)$this->config->get('theme_codecart_pro_product_module_layout');
        if (!in_array($layout, array('carousel', 'grid'), true)) {
            $layout = 'carousel';
        }

        $product_id = isset($setting['product_id']) ? (int)$setting['product_id'] : (isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0);
        $category_id = isset($setting['category_id']) ? (int)$setting['category_id'] : $this->detectCategoryId();

        $products = $this->getProductsByType($type, $limit, $product_id, $category_id);
        if (!$products) { return ''; }

        $data = array();
        $data['module_type'] = $type;
        $data['module_layout'] = $layout;
        $data['module_title'] = isset($setting['title']) && $setting['title'] ? $setting['title'] : $this->getTitle($type);
        $data['products'] = $products;
        $data['button_cart'] = $this->language->get('button_cart');
        $data['button_wishlist'] = $this->language->get('button_wishlist');
        $data['button_compare'] = $this->language->get('button_compare');
        $data['button_more'] = $this->language->get('button_more');
                $data['ccp_button_quick_order'] = $this->language->get('button_quick_order');
        $data['text_tax'] = $this->language->get('text_tax');
        $data['ccp_text_in_stock'] = $this->language->get('text_in_stock');
        $data['ccp_text_quantity'] = $this->language->get('text_quantity');
        $data['ccp_enable_quick_view'] = (int)$this->config->get('theme_codecart_pro_enable_quick_view');
        $data['ccp_enable_quick_order'] = (int)$this->config->get('theme_codecart_pro_enable_quick_order');
        $data['ccp_enable_stickers'] = (int)$this->config->get('theme_codecart_pro_enable_stickers');
        $data['ccp_card_style'] = (string)$this->config->get('theme_codecart_pro_card_style') ?: 'commerce';
        $data['ccp_card_title_lines'] = (int)$this->config->get('theme_codecart_pro_card_title_lines') ?: 2;
        $data['ccp_card_full_click'] = (int)$this->config->get('theme_codecart_pro_card_full_click');
        $data['ccp_card_show_description'] = (int)$this->config->get('theme_codecart_pro_card_show_description');
        $data['ccp_card_show_rating'] = (int)$this->config->get('theme_codecart_pro_card_show_rating');
        $data['ccp_card_show_stock'] = (int)$this->config->get('theme_codecart_pro_card_show_stock');
        $data['ccp_card_show_quick_actions'] = (int)$this->config->get('theme_codecart_pro_card_show_quick_actions');
        $data['ccp_card_show_wishlist'] = (int)$this->config->get('theme_codecart_pro_card_show_wishlist');
        $data['ccp_card_show_compare'] = (int)$this->config->get('theme_codecart_pro_card_show_compare');
        $data['ccp_card_show_quantity'] = (int)$this->config->get('theme_codecart_pro_card_show_quantity');
        $data['ccp_card_show_buy_button'] = (int)$this->config->get('theme_codecart_pro_card_show_buy_button');
        $data['ccp_card_stock_mode'] = (string)$this->config->get('theme_codecart_pro_card_stock_mode') ?: 'smart';
        $data['ccp_card_second_image_status'] = (int)$this->config->get('theme_codecart_pro_card_second_image_status');
        $data['ccp_card_sale_percent_status'] = (int)$this->config->get('theme_codecart_pro_card_sale_percent_status');
        $data['ccp_card_hover_actions_mode'] = (string)$this->config->get('theme_codecart_pro_card_hover_actions_mode') ?: 'smart';
        $data['ccp_card_show_model'] = (int)$this->config->get('theme_codecart_pro_card_show_model');
        $data['ccp_card_show_manufacturer'] = (int)$this->config->get('theme_codecart_pro_card_show_manufacturer');
        $data['ccp_card_meta_position'] = (string)$this->config->get('theme_codecart_pro_card_meta_position') ?: 'under_title';
        $data['ccp_performance_lazy_images'] = (int)$this->config->get('theme_codecart_pro_performance_lazy_images');
        $data['ccp_performance_eager_first_images'] = (int)$this->config->get('theme_codecart_pro_performance_eager_first_images');
        $data['ccp_performance_fetchpriority_status'] = (int)$this->config->get('theme_codecart_pro_performance_fetchpriority_status');
        $data['ccp_performance_image_decoding_status'] = (int)$this->config->get('theme_codecart_pro_performance_image_decoding_status');

        return $this->load->view('extension/module/codecart_pro_products', $data);
    }

    public function trackViewed($product_id = 0) {
        if (!$this->isCodeCartProThemeActive()) { return; }
        if (!(int)$this->config->get('theme_codecart_pro_status')) { return; }
        if (!$this->enabled('theme_codecart_pro_product_modules_status', 1)) { return; }
        $product_id = (int)$product_id;
        if ($product_id <= 0) { return; }
        $viewed = $this->getViewedProductIds();
        $viewed = array_values(array_diff($viewed, array($product_id)));
        array_unshift($viewed, $product_id);
        $this->saveViewedProductIds(array_slice($viewed, 0, 40));
    }

    private function getViewedProductIds() {
        $viewed = isset($this->session->data['ccp_viewed_products']) && is_array($this->session->data['ccp_viewed_products']) ? $this->session->data['ccp_viewed_products'] : array();
        if (!empty($this->request->cookie['ccp_viewed_products'])) {
            $cookie_ids = explode(',', (string)$this->request->cookie['ccp_viewed_products']);
            $viewed = array_merge($viewed, $cookie_ids);
        }
        return array_slice(array_values(array_filter(array_unique(array_map('intval', $viewed)))), 0, 40);
    }

    private function saveViewedProductIds($viewed) {
        $viewed = array_slice(array_values(array_filter(array_unique(array_map('intval', (array)$viewed)))), 0, 40);
        $this->session->data['ccp_viewed_products'] = $viewed;
        $cookie_value = implode(',', $viewed);
        if (!headers_sent()) {
            $secure = !empty($this->request->server['HTTPS']) && strtolower((string)$this->request->server['HTTPS']) !== 'off';
            setcookie('ccp_viewed_products', $cookie_value, time() + 2592000, '/', '', $secure, true);
        }
        $this->request->cookie['ccp_viewed_products'] = $cookie_value;
    }

    private function getProductsByType($type, $limit, $product_id, $category_id) {
        $raw_items = array();

        if ($type === 'latest') {
            $raw_items = $this->model_catalog_product->getLatestProducts($limit);
        } elseif ($type === 'special') {
            $raw_items = $this->model_catalog_product->getProductSpecials(array('start' => 0, 'limit' => $limit));
        } elseif ($type === 'bestseller' && method_exists($this->model_catalog_product, 'getBestSellerProducts')) {
            $raw_items = $this->model_catalog_product->getBestSellerProducts($limit);
        } elseif ($type === 'viewed') {
            $viewed = $this->getViewedProductIds();

            foreach ($viewed as $id) {
                if ($product_id && $id === (int)$product_id) { continue; }
                $product = $this->model_catalog_product->getProduct($id);
                if ($product) { $raw_items[] = $product; }
                if (count($raw_items) >= $limit) { break; }
            }

            // Do not substitute latest products: a viewed-products block must represent real history.
        } elseif ($type === 'related' && $product_id) {
            foreach ($this->model_catalog_product->getProductRelated($product_id) as $product) {
                $raw_items[(int)$product['product_id']] = $product;
                if (count($raw_items) >= $limit) { break; }
            }
        } elseif ($type === 'auto_related' && $product_id) {
            foreach ($this->getProductCategoryIds($product_id, $category_id) as $cid) {
                $filter = array(
                    'filter_category_id'  => (int)$cid,
                    'filter_sub_category' => false,
                    'sort'                => 'p.sort_order',
                    'order'               => 'ASC',
                    'start'               => 0,
                    'limit'               => $limit * 3
                );

                foreach ($this->model_catalog_product->getProducts($filter) as $product) {
                    if ((int)$product['product_id'] === (int)$product_id) { continue; }
                    $raw_items[(int)$product['product_id']] = $product;
                    if (count($raw_items) >= $limit) { break 2; }
                }
            }
        } elseif ($type === 'category') {
            $category_ids = array();
            if ($category_id) {
                $category_ids[] = (int)$category_id;
            } elseif ($product_id) {
                $category_ids = $this->getProductCategoryIds($product_id, 0);
            }

            foreach ($category_ids as $cid) {
                $filter = array(
                    'filter_category_id'  => (int)$cid,
                    'filter_sub_category' => true,
                    'start'               => 0,
                    'limit'               => $limit * 3
                );
                foreach ($this->model_catalog_product->getProducts($filter) as $product) {
                    if ($product_id && (int)$product['product_id'] === (int)$product_id) { continue; }
                    $raw_items[(int)$product['product_id']] = $product;
                    if (count($raw_items) >= $limit) { break 2; }
                }
            }
        }

        $raw_items = array_slice(array_values($raw_items), 0, $limit);
        if (!$raw_items) { return array(); }

        $this->primeSecondImageCache($raw_items);
        $items = array();
        foreach ($raw_items as $product) {
            $items[] = $this->productArray($product);
        }

        return $items;
    }

    private function detectCategoryId() {
        if (isset($this->request->get['path'])) {
            $parts = explode('_', (string)$this->request->get['path']);
            return (int)end($parts);
        }
        if (isset($this->request->get['category_id'])) { return (int)$this->request->get['category_id']; }
        return 0;
    }

    private function getProductCategoryIds($product_id, $preferred_category_id = 0) {
        $ids = array();
        $product_id = (int)$product_id;
        $preferred_category_id = (int)$preferred_category_id;

        if ($product_id <= 0) {
            return $ids;
        }

        // ocStore/SEO modules: product_to_category.main_category has first priority.
        if ($this->hasColumn('product_to_category', 'main_category')) {
            $query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . $product_id . "' AND main_category = '1' ORDER BY category_id ASC LIMIT 1");
            if ($query->num_rows && (int)$query->row['category_id'] > 0) {
                $ids[] = (int)$query->row['category_id'];
            }
        }

        // Alternative main-category implementation used by some OpenCart builds.
        if ($this->hasColumn('product', 'main_category_id')) {
            $query = $this->db->query("SELECT main_category_id FROM `" . DB_PREFIX . "product` WHERE product_id = '" . $product_id . "' LIMIT 1");
            if ($query->num_rows && (int)$query->row['main_category_id'] > 0) {
                $ids[] = (int)$query->row['main_category_id'];
            }
        }

        // Explicit route context is useful after the canonical main category.
        if ($preferred_category_id > 0) {
            $query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . $product_id . "' AND category_id = '" . $preferred_category_id . "' LIMIT 1");
            if ($query->num_rows) {
                $ids[] = $preferred_category_id;
            }
        }

        // Safe fallback: all linked categories in deterministic order.
        $query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . $product_id . "' ORDER BY category_id ASC");
        foreach ($query->rows as $row) {
            $ids[] = (int)$row['category_id'];
        }

        return array_slice(array_values(array_unique(array_filter($ids))), 0, 12);
    }

    private function hasColumn($table, $column) {
        $table = (string)$table;
        $column = (string)$column;
        $cache_key = $table . '.' . $column;

        if (isset($this->ccpColumnCache[$cache_key])) {
            return $this->ccpColumnCache[$cache_key];
        }

        if (!in_array($table, array('product', 'product_to_category'), true)) {
            $this->ccpColumnCache[$cache_key] = false;
            return false;
        }

        $query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` LIKE '" . $this->db->escape($column) . "'");
        $this->ccpColumnCache[$cache_key] = (bool)$query->num_rows;

        return $this->ccpColumnCache[$cache_key];
    }


    private function primeSecondImageCache($products) {
        if (!(int)$this->config->get('theme_codecart_pro_card_second_image_status')) {
            return;
        }

        $product_ids = array();
        foreach ((array)$products as $product) {
            if (!empty($product['product_id'])) {
                $product_ids[] = (int)$product['product_id'];
            }
        }
        $product_ids = array_values(array_unique(array_filter($product_ids)));
        if (!$product_ids) { return; }

        foreach ($product_ids as $product_id) {
            if (!array_key_exists($product_id, $this->ccpSecondImageCache)) {
                $this->ccpSecondImageCache[$product_id] = '';
            }
        }

        $query = $this->db->query("SELECT pi.product_id, pi.image FROM `" . DB_PREFIX . "product_image` pi INNER JOIN (SELECT product_id, MIN(sort_order) AS min_sort FROM `" . DB_PREFIX . "product_image` WHERE product_id IN (" . implode(',', array_map('intval', $product_ids)) . ") GROUP BY product_id) first_image ON (first_image.product_id = pi.product_id AND first_image.min_sort = pi.sort_order) WHERE pi.product_id IN (" . implode(',', array_map('intval', $product_ids)) . ") ORDER BY pi.product_id ASC, pi.product_image_id ASC");

        foreach ($query->rows as $row) {
            $product_id = (int)$row['product_id'];
            if ($product_id > 0 && $this->ccpSecondImageCache[$product_id] === '' && !empty($row['image'])) {
                $this->ccpSecondImageCache[$product_id] = (string)$row['image'];
            }
        }
    }

    private function fitCacheImage($filename, $max_width, $max_height) {
        $filename = (string)$filename;
        $max_width = max(1, (int)$max_width);
        $max_height = max(1, (int)$max_height);

        if ($filename === '' || !is_file(DIR_IMAGE . $filename)) {
            $filename = 'placeholder.png';
        }

        return $this->model_tool_image->resize($filename, $max_width, $max_height);
    }

    private function getTitle($type) {
        $map = array(
            'latest' => 'text_latest_products',
            'special' => 'text_special_products',
            'bestseller' => 'text_bestseller_products',
            'viewed' => 'text_viewed_products',
            'related' => 'text_related_products',
            'auto_related' => 'text_auto_related_products',
            'category' => 'text_category_products'
        );
        return $this->language->get(isset($map[$type]) ? $map[$type] : 'text_products');
    }

    private function productArray($product) {
        $width = (int)$this->config->get('theme_codecart_pro_image_product_width') ?: 900;
        $height = (int)$this->config->get('theme_codecart_pro_image_product_height') ?: 573;

        // CodeCart PRO: product-module promo banners must keep a wide banner ratio.
        // Legacy square 320x320 and old 640x360 caches created visible padding around 1100x700 promo covers.
        // Normalize only the known legacy values so custom user sizes stay intact.
        if (($width === $height) || ($width === 640 && $height === 360) || ($width === 320 && $height === 320)) {
            $width = 900;
            $height = 573;
        }

        $image = !empty($product['image']) ? $this->fitCacheImage($product['image'], $width, $height) : $this->fitCacheImage('placeholder.png', $width, $height);
        $second_thumb = '';
        $product_id = isset($product['product_id']) ? (int)$product['product_id'] : 0;
        if ((int)$this->config->get('theme_codecart_pro_card_second_image_status') && $product_id > 0) {
            if (!array_key_exists($product_id, $this->ccpSecondImageCache)) {
                $this->primeSecondImageCache(array($product));
            }
            if (!empty($this->ccpSecondImageCache[$product_id])) {
                $second_thumb = $this->fitCacheImage($this->ccpSecondImageCache[$product_id], $width, $height);
            }
        }
        if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
            $price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
        } else { $price = false; }
        $special = false;
        if (isset($product['special']) && (float)$product['special']) {
            $special = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
        }
        $tax = false;
        if ($this->config->get('config_tax')) {
            $tax = $this->currency->format((float)(isset($product['special']) && $product['special'] ? $product['special'] : $product['price']), $this->session->data['currency']);
        }
        $sale_percent = 0;
        if (isset($product['special']) && (float)$product['special'] && isset($product['price']) && (float)$product['price'] > 0) {
            $sale_percent = max(1, min(99, (int)round((1 - ((float)$product['special'] / (float)$product['price'])) * 100)));
        }
        $quantity = isset($product['quantity']) ? (int)$product['quantity'] : 0;
        $stock_status = $quantity > 0 ? $this->language->get('text_in_stock') : (isset($product['stock_status']) ? $product['stock_status'] : '');
        return array(
            'product_id' => (int)$product['product_id'],
            'thumb' => $image,
            'ccp_second_thumb' => $second_thumb,
            'name' => trim(strip_tags(html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'))),
            'description' => utf8_substr(trim(strip_tags(html_entity_decode(isset($product['description']) ? $product['description'] : '', ENT_QUOTES, 'UTF-8'))), 0, 120),
            'price' => $price,
            'special' => $special,
            'tax' => $tax,
            'minimum' => isset($product['minimum']) && (int)$product['minimum'] > 0 ? (int)$product['minimum'] : 1,
            'rating' => isset($product['rating']) ? (int)$product['rating'] : 0,
            'ccp_model' => isset($product['model']) ? $product['model'] : '',
            'ccp_manufacturer' => isset($product['manufacturer']) ? $product['manufacturer'] : '',
            'ccp_stock_status' => $stock_status,
            'ccp_stock_class' => $quantity > 0 ? 'in' : 'out',
            'ccp_is_new' => !empty($product['date_added']) && strtotime($product['date_added']) >= strtotime('-30 days'),
            'ccp_sale_percent' => $sale_percent,
            'href' => $this->url->link('product/product', 'product_id=' . (int)$product['product_id'])
        );
    }

}
