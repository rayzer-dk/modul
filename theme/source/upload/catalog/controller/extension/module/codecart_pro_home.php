<?php
class ControllerExtensionModuleCodecartProHome extends Controller {
    private function isCodeCartProThemeActive() {
        $ccp_config_theme = (string)$this->config->get('config_theme');
        $ccp_theme_directory_key = $ccp_config_theme ? 'theme_' . $ccp_config_theme . '_directory' : '';
        $ccp_theme_directory = $ccp_theme_directory_key ? (string)$this->config->get($ccp_theme_directory_key) : '';
        $ccp_template_directory = (string)$this->config->get('template_directory');
        $ccp_config_template = (string)$this->config->get('config_template');

        return ($ccp_config_theme === 'codecart_pro' || $ccp_config_theme === 'theme_codecart_pro' || $ccp_theme_directory === 'codecart_pro' || $ccp_config_template === 'codecart_pro' || strpos($ccp_template_directory, 'codecart_pro/') === 0);
    }


    private function safeController($route, $args = array(), $context = '') {
        try {
            // OpenCart 3 passes the second loader argument to call_user_func_array().
            // On PHP 8+, an associative array is interpreted as named arguments.
            // Our child controllers accept one settings array, so wrap it as one positional argument.
            $controller_args = !empty($args) ? array($args) : array();

            return $this->load->controller($route, $controller_args);
        } catch (Exception $e) {
            $this->log->write('CodeCart PRO Theme optional home controller disabled' . ($context ? ' [' . $context . ']' : '') . ': ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->log->write('CodeCart PRO Theme optional home controller disabled' . ($context ? ' [' . $context . ']' : '') . ': ' . $e->getMessage());
        }
        return '';
    }

    public function index($setting = array()) {
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_home_builder_status')) {
            return '';
        }

        $this->load->language('extension/module/codecart_pro_home');
        $this->load->model('tool/image');
        $language_id = (int)$this->config->get('config_language_id');

        $data = array();
        $data['language_id'] = $language_id;
        $data['slider_status'] = (int)$this->config->get('theme_codecart_pro_home_slider_status');
        $custom_slideshow_status = $this->config->has('theme_codecart_pro_custom_slideshow_status') ? (int)$this->config->get('theme_codecart_pro_custom_slideshow_status') : 1;
        if ($custom_slideshow_status) { $data['slider_status'] = 0; }
        $data['slider_autoplay'] = (int)$this->config->get('theme_codecart_pro_home_slider_autoplay');
        $height = (int)$this->config->get('theme_codecart_pro_home_slider_height');
        $data['slider_height'] = ($height >= 260 && $height <= 700) ? $height : 460;
        $data['banners_status'] = $this->enabled('theme_codecart_pro_home_banners_status', 0);
        $data['category_wall_status'] = $this->enabled('theme_codecart_pro_home_category_wall_status', 1);
        $data['product_sections_status'] = $this->enabled('theme_codecart_pro_home_product_sections_status', 1);
        $data['manufacturer_status'] = $this->enabled('theme_codecart_pro_home_manufacturer_status', 0);
        $data['benefits_status'] = $this->enabled('theme_codecart_pro_home_benefits_status', 0);
        $data['home_category_wall_columns_desktop'] = min(6, max(2, (int)$this->config->get('theme_codecart_pro_home_category_wall_columns_desktop')));
        $data['home_category_wall_columns_tablet'] = min(4, max(2, (int)$this->config->get('theme_codecart_pro_home_category_wall_columns_tablet')));
        $data['home_category_wall_columns_mobile'] = min(3, max(1, (int)$this->config->get('theme_codecart_pro_home_category_wall_columns_mobile')));
        $data['home_category_wall_image_width'] = min(1000, max(80, (int)$this->config->get('theme_codecart_pro_home_category_wall_image_width')));
        $data['home_category_wall_image_height'] = min(1000, max(80, (int)$this->config->get('theme_codecart_pro_home_category_wall_image_height')));
        $data['home_category_wall_show_count'] = $this->config->has('theme_codecart_pro_home_category_wall_show_count') ? (int)$this->config->get('theme_codecart_pro_home_category_wall_show_count') : 0;
        $data['home_category_wall_show_image'] = $this->config->has('theme_codecart_pro_home_category_wall_show_image') ? (int)$this->config->get('theme_codecart_pro_home_category_wall_show_image') : 1;
        $data['home_category_wall_show_description'] = $this->config->has('theme_codecart_pro_home_category_wall_show_description') ? (int)$this->config->get('theme_codecart_pro_home_category_wall_show_description') : 0;
        $data['home_category_wall_style'] = in_array((string)$this->config->get('theme_codecart_pro_home_category_wall_style'), array('cards', 'compact', 'overlay'), true) ? (string)$this->config->get('theme_codecart_pro_home_category_wall_style') : 'cards';
        $data['home_category_wall_display_mode'] = ((string)$this->config->get('theme_codecart_pro_home_category_wall_display_mode') === 'carousel') ? 'carousel' : 'grid';
        $data['text_categories'] = $this->language->get('text_categories');
        $data['text_latest'] = $this->language->get('text_latest');
        $data['text_special'] = $this->language->get('text_special');
        $data['text_bestseller'] = $this->language->get('text_bestseller');
        $data['text_manufacturers'] = $this->language->get('text_manufacturers');
        $data['text_benefits'] = $this->language->get('text_benefits');
        $data['text_products'] = $this->language->get('text_products');
        $data['button_cart'] = $this->language->get('button_cart');
        $data['button_more'] = $this->language->get('button_more');
                $data['button_quick_order'] = $this->language->get('button_quick_order');
        $data['ccp_enable_quick_view'] = (int)$this->config->get('theme_codecart_pro_enable_quick_view');
        $data['ccp_enable_quick_order'] = (int)$this->config->get('theme_codecart_pro_enable_quick_order');

        $data['slides'] = $this->getSlides($language_id);
        $data['banners'] = $this->getBanners($language_id);
        $data['benefits'] = $this->getBenefits($language_id);
        $data['categories'] = $this->getCategories();
        $data['manufacturers'] = $data['manufacturer_status'] ? $this->getManufacturers() : array();
        $data['ccp_product_module_blocks'] = $this->getProductModuleBlocks();

        if ($data['ccp_product_module_blocks'] !== '') {
            $data['latest_products'] = array();
            $data['special_products'] = array();
            $data['bestseller_products'] = array();
        } else {
            $data['latest_products'] = $this->getLatestProducts();
            $data['special_products'] = $this->getSpecialProducts();
            $data['bestseller_products'] = $this->getBestSellerProducts();
        }
        $data['ccp_home_blog'] = $this->safeController('extension/module/codecart_pro_blog/homeBlock', array('limit' => 5), 'home_blog');
        $data['ccp_latest_reviews'] = $this->safeController('extension/module/codecart_pro_reviews', array(), 'home_reviews');
        return $this->load->view('extension/module/codecart_pro_home', $data);
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

    private function cleanText($value) {
        return trim(strip_tags(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8')));
    }

    private function getLangValue($key, $language_id, $fallback = '') {
        $value = $this->config->get($key);
        if (is_array($value) && isset($value[$language_id]) && $value[$language_id] !== '') {
            return $this->cleanText($value[$language_id]);
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item !== '') { return $this->cleanText($item); }
            }
        }
        return $fallback;
    }

    private function image($path, $width, $height) {
        $path = trim((string)$path);
        if ($path && is_file(DIR_IMAGE . $path)) {
            return $this->model_tool_image->resize($path, $width, $height);
        }
        return '';
    }

    private function fitImage($path, $max_width, $max_height) {
        $path = trim((string)$path);
        $max_width = max(1, (int)$max_width);
        $max_height = max(1, (int)$max_height);

        if ($path === '' || !is_file(DIR_IMAGE . $path)) {
            return '';
        }

        return $this->model_tool_image->resize($path, $max_width, $max_height);
    }

    private function imageUrl($path) {
        $path = trim(str_replace('\\', '/', (string)$path), '/ ');
        if ($path === '' || !is_file(DIR_IMAGE . $path)) {
            return '';
        }

        $base = (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] != 'off') ? HTTPS_SERVER : HTTP_SERVER;
        $parts = array_map('rawurlencode', explode('/', $path));

        return rtrim($base, '/') . '/image/' . implode('/', $parts);
    }

    public function slideshow() {
        $custom_slideshow_status = $this->config->has('theme_codecart_pro_custom_slideshow_status') ? (int)$this->config->get('theme_codecart_pro_custom_slideshow_status') : 1;
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !$custom_slideshow_status) {
            return '';
        }

        $this->load->language('extension/module/codecart_pro_home');
        $this->load->model('tool/image');

        $language_id = (int)$this->config->get('config_language_id');
        $source = (string)$this->config->get('theme_codecart_pro_custom_slideshow_image_source');
        $source = ($source === 'cache') ? 'cache' : 'direct';
        $fit = (string)$this->config->get('theme_codecart_pro_custom_slideshow_fit_mode');

        if (!in_array($fit, array('height', 'width', 'cover'), true)) {
            $fit = 'height';
        }

        $slides = array();

        for ($i = 1; $i <= 5; $i++) {
            $path = trim((string)$this->config->get('theme_codecart_pro_home_slide' . $i . '_image'));
            $image = ($source === 'cache') ? $this->image($path, 1600, 520) : $this->imageUrl($path);

            if ($image === '') {
                continue;
            }

            $slides[] = array(
                'title' => $this->getLangValue('theme_codecart_pro_home_slide' . $i . '_title', $language_id),
                'subtitle' => $this->getLangValue('theme_codecart_pro_home_slide' . $i . '_subtitle', $language_id),
                'button' => $this->getLangValue('theme_codecart_pro_home_slide' . $i . '_button', $language_id),
                'link' => trim((string)$this->config->get('theme_codecart_pro_home_slide' . $i . '_link')),
                'image' => $image
            );
        }

        if (!$slides) {
            return '';
        }

        $height_desktop = (int)$this->config->get('theme_codecart_pro_custom_slideshow_height_desktop');
        $height_tablet = (int)$this->config->get('theme_codecart_pro_custom_slideshow_height_tablet');
        $height_mobile = (int)$this->config->get('theme_codecart_pro_custom_slideshow_height_mobile');
        $delay = (int)$this->config->get('theme_codecart_pro_custom_slideshow_autoplay_delay');

        $data = array(
            'slides' => $slides,
            'fit_mode' => $fit,
            'show_text' => ($this->config->has('theme_codecart_pro_custom_slideshow_show_text') ? (int)$this->config->get('theme_codecart_pro_custom_slideshow_show_text') : 0),
            'hide_native' => ($this->config->has('theme_codecart_pro_custom_slideshow_hide_native') ? (int)$this->config->get('theme_codecart_pro_custom_slideshow_hide_native') : 1),
            'autoplay' => (int)$this->config->get('theme_codecart_pro_home_slider_autoplay'),
            'delay' => ($delay >= 1500 && $delay <= 20000) ? $delay : 4000,
            'height_desktop' => ($height_desktop >= 180 && $height_desktop <= 700) ? $height_desktop : 380,
            'height_tablet' => ($height_tablet >= 160 && $height_tablet <= 600) ? $height_tablet : 300,
            'height_mobile' => ($height_mobile >= 120 && $height_mobile <= 420) ? $height_mobile : 190,
            'store_name' => $this->config->get('config_name')
        );

        return $this->load->view('extension/module/codecart_pro_slideshow', $data);
    }

    private function getManufacturers() {
        $this->load->model('catalog/manufacturer');
        $limit = (int)$this->config->get('theme_codecart_pro_home_manufacturer_limit');
        $limit = min(30, max(1, $limit ?: 12));
        $items = array();

        foreach ($this->model_catalog_manufacturer->getManufacturers(array('start' => 0, 'limit' => $limit)) as $manufacturer) {
            $image = !empty($manufacturer['image']) ? $this->model_tool_image->resize($manufacturer['image'], 180, 90) : '';
            $items[] = array(
                'name' => $manufacturer['name'],
                'thumb' => $image,
                'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . (int)$manufacturer['manufacturer_id'])
            );
        }

        return $items;
    }

    private function getSlides($language_id) {
        $slides = array();
        for ($i = 1; $i <= 5; $i++) {
            $title = $this->getLangValue('theme_codecart_pro_home_slide' . $i . '_title', $language_id);
            $subtitle = $this->getLangValue('theme_codecart_pro_home_slide' . $i . '_subtitle', $language_id);
            $button = $this->getLangValue('theme_codecart_pro_home_slide' . $i . '_button', $language_id);
            $link = trim((string)$this->config->get('theme_codecart_pro_home_slide' . $i . '_link'));
            $image = $this->image($this->config->get('theme_codecart_pro_home_slide' . $i . '_image'), 1360, 520);
            if ($title || $subtitle || $image) {
                $slides[] = array('title' => $title, 'subtitle' => $subtitle, 'button' => $button, 'link' => $link, 'image' => $image);
            }
        }
        return $slides;
    }

    private function getBanners($language_id) {
        $items = array();
        if (!(int)$this->config->get('theme_codecart_pro_home_banners_status')) { return $items; }

        $default_icons = array(1 => 'fa-truck', 2 => 'fa-shield', 3 => 'fa-comments', 4 => 'fa-credit-card');

        for ($i = 1; $i <= 4; $i++) {
            $title = $this->getLangValue('theme_codecart_pro_home_banner' . $i . '_title', $language_id);
            $text = $this->getLangValue('theme_codecart_pro_home_banner' . $i . '_text', $language_id);
            $link = trim((string)$this->config->get('theme_codecart_pro_home_banner' . $i . '_link'));
            $image = $this->image($this->config->get('theme_codecart_pro_home_banner' . $i . '_image'), 520, 260);
            $icon = $this->normalizeIcon($this->config->get('theme_codecart_pro_home_banner' . $i . '_icon'), isset($default_icons[$i]) ? $default_icons[$i] : 'fa-star');

            if ($title || $text || $image) {
                $items[] = array(
                    'title' => $title,
                    'text' => $text,
                    'link' => $link,
                    'image' => $image,
                    'icon' => $icon
                );
            }
        }

        return $items;
    }

    private function getBenefits($language_id) {
        $benefits = array();
        $raw = $this->getLangValue('theme_codecart_pro_home_benefits_text', $language_id);

        if ($this->containsPhysicalBenefitText($raw)) {
            $raw = $this->digitalBenefitsFallback();
        }

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim((string)$line);

            if ($line === '') {
                continue;
            }

            $parts = explode('|', $line, 2);

            if (count($parts) === 2) {
                $icon = $this->normalizeIcon($parts[0], 'fa-check');
                $text = $this->cleanText($parts[1]);
            } else {
                $icon = 'fa-check';
                $text = $this->cleanText($line);
            }

            if ($text !== '') {
                $benefits[] = array(
                    'icon' => $icon,
                    'text' => $text
                );
            }
        }

        return $benefits;
    }


    private function containsPhysicalBenefitText($text) {
        $text = (string)$text;
        $markers = array(
            'Швидка доставка', 'Быстрая доставка', 'Fast delivery',
            'Офіційна гарантія', 'Официальная гарантия', 'Official warranty',
            'Доставка', 'Delivery', 'shipping', 'гарантия', 'гарантія'
        );

        foreach ($markers as $marker) {
            if ($marker !== '' && mb_stripos($text, $marker, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    private function digitalBenefitsFallback() {
        $code = strtolower((string)$this->config->get('config_language'));

        if (strpos($code, 'uk') === 0 || strpos($code, 'ua') === 0) {
            return "fa-download|Миттєве отримання\nfa-key|Ліцензійний ключ\nfa-code|Сумісність з OpenCart 3.x\nfa-life-ring|Підтримка та консультація";
        }

        if (strpos($code, 'ru') === 0) {
            return "fa-download|Мгновенное получение\nfa-key|Лицензионный ключ\nfa-code|Совместимость с OpenCart 3.x\nfa-life-ring|Поддержка и консультация";
        }

        return "fa-download|Instant access\nfa-key|License key\nfa-code|OpenCart 3.x compatibility\nfa-life-ring|Support and consultation";
    }

    private function normalizeIcon($icon, $fallback = 'fa-check') {
        $icon = trim((string)$icon);
        $icon = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $icon);
        $icon = trim(preg_replace('/\s+/', ' ', $icon));

        if ($icon === '') {
            return $fallback;
        }

        if (strpos($icon, 'fa ') === 0 || strpos($icon, 'fa-') === 0) {
            return $icon;
        }

        return 'fa-' . $icon;
    }

    private function getCategories() {
        $items = array();
        if (!$this->enabled('theme_codecart_pro_home_category_wall_status', 1)) { return $items; }

        $limit = min(100, max(0, (int)$this->config->get('theme_codecart_pro_home_category_wall_limit')));
        $show_count = $this->config->has('theme_codecart_pro_home_category_wall_show_count') ? (int)$this->config->get('theme_codecart_pro_home_category_wall_show_count') : 0;
        $image_width = min(1000, max(80, (int)$this->config->get('theme_codecart_pro_home_category_wall_image_width')));
        $image_height = min(1000, max(80, (int)$this->config->get('theme_codecart_pro_home_category_wall_image_height')));
        $ids_raw = trim((string)$this->config->get('theme_codecart_pro_home_category_wall_category_ids'));
        $allowed_ids = array();

        if ($ids_raw !== '') {
            foreach (explode(',', $ids_raw) as $id) {
                $id = (int)trim($id);
                if ($id > 0) { $allowed_ids[$id] = $id; }
            }
        }

        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $categories = $this->model_catalog_category->getCategories(0);

        foreach ($categories as $category) {
            $category_id = (int)$category['category_id'];

            if ($allowed_ids && !isset($allowed_ids[$category_id])) {
                continue;
            }

            if ($limit > 0 && count($items) >= $limit) { break; }

            $image = '';
            if ((int)$this->config->get('theme_codecart_pro_home_category_wall_show_image') && !empty($category['image'])) {
                $image_side = max($image_width, $image_height);
                $image = $this->fitImage($category['image'], $image_side, $image_side);
            }
            $description = '';
            if ((int)$this->config->get('theme_codecart_pro_home_category_wall_show_description') && !empty($category['description'])) {
                $description = utf8_substr($this->cleanText($category['description']), 0, 140);
            }

            $product_count = 0;
            if ($show_count) {
                $product_count = (int)$this->model_catalog_product->getTotalProducts(array(
                    'filter_category_id' => $category_id,
                    'filter_sub_category' => true
                ));
            }

            $items[] = array(
                'category_id' => $category_id,
                'name' => $this->cleanText($category['name']),
                'image' => $image,
                'count' => $product_count,
                'description' => $description,
                'href' => $this->url->link('product/category', 'path=' . $category_id)
            );
        }
        return $items;
    }

    private function productArray($product) {
        $width = (int)$this->config->get('theme_codecart_pro_image_product_width') ?: 640;
        $height = (int)$this->config->get('theme_codecart_pro_image_product_height') ?: 407;

        // Legacy square and old 16:9 defaults created visible padding around wide module banners.
        // Normalize only the known legacy values so custom user sizes stay intact.
        if (($width === $height) || ($width === 640 && $height === 360) || ($width === 320 && $height === 320)) {
            $width = 640;
            $height = 407;
        }

        if ($product['image']) { $image = $this->model_tool_image->resize($product['image'], $width, $height); }
        else { $image = $this->model_tool_image->resize('placeholder.png', $width, $height); }
        if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
            $price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
        } else { $price = false; }
        $special = false;
        if ((float)$product['special']) {
            $special = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
        }
        return array(
            'product_id' => (int)$product['product_id'],
            'thumb' => $image,
            'name' => $this->cleanText($product['name']),
            'description' => utf8_substr(trim(strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8'))), 0, 120),
            'price' => $price,
            'special' => $special,
            'rating' => isset($product['rating']) ? (int)$product['rating'] : 0,
            'href' => $this->url->link('product/product', 'product_id=' . (int)$product['product_id'])
        );
    }

    private function getLatestProducts() {
        if (!$this->enabled('theme_codecart_pro_home_product_sections_status', 1) || !$this->enabled('theme_codecart_pro_home_latest_status', 1)) { return array(); }
        $limit = min(12, max(1, (int)$this->config->get('theme_codecart_pro_home_product_section_limit') ?: 5));
        $this->load->model('catalog/product');
        $items = array();
        foreach ($this->model_catalog_product->getLatestProducts($limit) as $product) { $items[] = $this->productArray($product); }
        return $items;
    }

    private function getSpecialProducts() {
        if (!$this->enabled('theme_codecart_pro_home_product_sections_status', 1) || !$this->enabled('theme_codecart_pro_home_special_status', 0)) { return array(); }
        $limit = min(12, max(1, (int)$this->config->get('theme_codecart_pro_home_product_section_limit') ?: 5));
        $this->load->model('catalog/product');
        $items = array();
        foreach ($this->model_catalog_product->getProductSpecials(array('start' => 0, 'limit' => $limit)) as $product) { $items[] = $this->productArray($product); }
        return $items;
    }

    private function getBestSellerProducts() {
        if (!$this->enabled('theme_codecart_pro_home_product_sections_status', 1) || !$this->enabled('theme_codecart_pro_home_bestseller_status', 0)) { return array(); }
        $limit = min(12, max(1, (int)$this->config->get('theme_codecart_pro_home_product_section_limit') ?: 5));
        $this->load->model('catalog/product');
        $items = array();
        if (method_exists($this->model_catalog_product, 'getBestSellerProducts')) {
            foreach ($this->model_catalog_product->getBestSellerProducts($limit) as $product) { $items[] = $this->productArray($product); }
        }
        return $items;
    }

    private function getProductModuleBlocks() {
        if (!$this->enabled('theme_codecart_pro_product_module_home_status', 1)) { return ''; }
        $html = '';
        $limit = min(12, max(1, (int)$this->config->get('theme_codecart_pro_product_module_limit') ?: 5));
        $layout = (string)$this->config->get('theme_codecart_pro_product_module_layout');
        if (!in_array($layout, array('carousel', 'grid'), true)) { $layout = 'carousel'; }
        foreach (array('latest','special','bestseller','viewed') as $type) {
            if ($this->enabled('theme_codecart_pro_product_module_' . $type . '_status', ($type === 'latest' || $type === 'viewed') ? 1 : 0)) {
                $html .= $this->safeController('extension/module/codecart_pro_products', array('type' => $type, 'limit' => $limit, 'layout' => $layout), 'home_products_' . $type);
            }
        }
        return $html;
    }

}
