<?php
class ControllerExtensionModuleCodecartProCategoryWall extends Controller {
    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $theme_directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        $config_template = (string)$this->config->get('config_template');
        $template_directory = (string)$this->config->get('template_directory');

        return ($config_theme === 'codecart_pro' || $config_theme === 'theme_codecart_pro' || $theme_directory === 'codecart_pro' || $config_template === 'codecart_pro' || strpos($template_directory, 'codecart_pro/') === 0);
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

    public function index($setting = array()) {
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status')) { return ''; }
        $this->load->language('extension/module/codecart_pro_category_wall');

        $setting = is_array($setting) ? $setting : array();
        $status = array_key_exists('status', $setting) ? (int)$setting['status'] : ($this->config->has('module_codecart_pro_category_wall_status') ? (int)$this->config->get('module_codecart_pro_category_wall_status') : 0);
        if (!$status) {
            return '';
        }

        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $ccp_get = function($key, $fallback = null) use ($setting) {
            return array_key_exists($key, $setting) ? $setting[$key] : ($this->config->has('module_codecart_pro_category_wall_' . $key) ? $this->config->get('module_codecart_pro_category_wall_' . $key) : $fallback);
        };

        $language_id = (int)$this->config->get('config_language_id');
        $title = $ccp_get('title', array());
        $subtitle = $ccp_get('subtitle', array());

        $data['heading_title'] = isset($title[$language_id]) && trim($title[$language_id]) !== '' ? $title[$language_id] : $this->language->get('heading_title');
        $data['text_subtitle'] = isset($subtitle[$language_id]) && trim($subtitle[$language_id]) !== '' ? $subtitle[$language_id] : $this->language->get('text_subtitle');
        $data['text_products'] = $this->language->get('text_products');
        $data['text_open_category'] = $this->language->get('text_open_category');

        $limit = (int)$ccp_get('limit', 12);
        $limit = min(60, max(0, $limit));

        $image_width = (int)$ccp_get('image_width', 420);
        $image_height = (int)$ccp_get('image_height', 260);
        $image_width = $image_width > 0 ? min($image_width, 800) : 420;
        $image_height = $image_height > 0 ? min($image_height, 800) : 260;

        $data['style'] = ((string)$ccp_get('style', 'cards')) ?: 'cards';
        $display_mode = (string)$ccp_get('display_mode', 'grid');
        $data['display_mode'] = in_array($display_mode, array('grid', 'carousel'), true) ? $display_mode : 'grid';
        $data['columns_desktop'] = max(1, min(6, (int)$ccp_get('columns_desktop', 4) ?: 4));
        $data['columns_tablet'] = max(1, min(4, (int)$ccp_get('columns_tablet', 3) ?: 3));
        $data['columns_mobile'] = max(1, min(3, (int)$ccp_get('columns_mobile', 2) ?: 2));
        $data['card_basis_desktop'] = round(100 / $data['columns_desktop'], 6) . '%';
        $data['card_basis_tablet'] = round(100 / $data['columns_tablet'], 6) . '%';
        $data['card_basis_mobile'] = round(100 / $data['columns_mobile'], 6) . '%';
        $data['show_description'] = (int)$ccp_get('show_description', 0);
        $data['show_count'] = (int)$ccp_get('show_count', 0);
        $data['show_image'] = (int)$ccp_get('show_image', 1);

        $selected = $ccp_get('category_ids', '');
        $category_ids = array();
        if (is_array($selected)) {
            $category_ids = array_map('intval', $selected);
        } elseif (is_string($selected) && trim($selected) !== '') {
            $category_ids = array_map('intval', preg_split('/[,;\s]+/', $selected));
        }
        $category_ids = array_values(array_filter(array_unique($category_ids)));

        $categories = array();
        if ($category_ids) {
            foreach ($category_ids as $category_id) {
                $category_info = $this->model_catalog_category->getCategory($category_id);
                if ($category_info) {
                    $categories[] = $category_info;
                }
            }
        } else {
            $categories = $this->model_catalog_category->getCategories(0);
        }

        $data['categories'] = array();
        $count = 0;
        foreach ($categories as $category) {
            if ($limit > 0 && $count >= $limit) {
                break;
            }

            $category_id = (int)$category['category_id'];
            $thumb = '';
            if (!empty($category['image']) && $data['show_image']) {
                $thumb = $this->fitImage($category['image'], $image_width, $image_height);
            }

            $total_products = null;
            if ($data['show_count']) {
                $total_products = (int)$this->model_catalog_product->getTotalProducts(array(
                    'filter_category_id'  => $category_id,
                    'filter_sub_category' => true
                ));
            }

            $description = '';
            if ($data['show_description'] && !empty($category['description'])) {
                $description = utf8_substr(trim(strip_tags(html_entity_decode($category['description'], ENT_QUOTES, 'UTF-8'))), 0, 120);
            }

            $data['categories'][] = array(
                'category_id' => $category_id,
                'name'        => $category['name'],
                'description' => $description,
                'thumb'       => $thumb,
                'total'       => $total_products,
                'href'        => $this->url->link('product/category', 'path=' . $category_id)
            );
            $count++;
        }

        if (!$data['categories']) {
            return '';
        }

        return $this->load->view('extension/module/codecart_pro_category_wall', $data);
    }
}
