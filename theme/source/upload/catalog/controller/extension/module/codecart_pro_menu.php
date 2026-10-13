<?php
class ControllerExtensionModuleCodecartProMenu extends Controller {
    public function index() {
        if (!$this->isCodeCartProThemeActive()) { return ''; }
        $this->load->language('extension/theme/codecart_pro');
        if (!(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_mega_menu_status')) {
            return '';
        }

        $this->load->model('catalog/category');
        $this->load->model('tool/image');

        $data = array();
        $data['language_id'] = (int)$this->config->get('config_language_id');
        $data['home'] = $this->url->link('common/home');
        $data['show_home'] = (int)$this->config->get('theme_codecart_pro_menu_show_home');
        $data['columns'] = $this->bound((int)$this->config->get('theme_codecart_pro_mega_menu_columns'), 2, 6, 4);
        $data['show_images'] = (int)$this->config->get('theme_codecart_pro_mega_menu_show_images');
        $data['show_counts'] = (int)$this->config->get('theme_codecart_pro_mega_menu_show_counts');
        $data['promo_status'] = (int)$this->config->get('theme_codecart_pro_mega_menu_promo_status');
        $data['promo_title'] = $this->pickLang($this->config->get('theme_codecart_pro_mega_menu_promo_title'), 'Expert selection');
        $data['promo_text'] = $this->pickLang($this->config->get('theme_codecart_pro_mega_menu_promo_text'), 'Choose products faster with clear categories and useful links.');
        $data['promo_link'] = (string)$this->config->get('theme_codecart_pro_mega_menu_promo_link');
        $data['text_more_details'] = $this->language->get('ccp_text_more_details');
        $data['categories'] = array();
        $data['custom_links'] = $this->customLinks();
        $data['blog_status'] = (int)$this->config->get('theme_codecart_pro_blog_status');
        $blog_title = $this->config->get('theme_codecart_pro_blog_title');
        $data['blog_title'] = $this->pickLang($blog_title, 'Blog');
        $ccp_menu_secure = !empty($this->request->server['HTTPS']) && strtolower((string)$this->request->server['HTTPS']) !== 'off';
        $ccp_menu_base = $ccp_menu_secure ? (string)$this->config->get('config_ssl') : (string)$this->config->get('config_url');
        if ($ccp_menu_base === '') { $ccp_menu_base = $ccp_menu_secure && defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : '/'); }
        $data['blog_href'] = rtrim($ccp_menu_base, '/') . '/index.php?route=extension/module/codecart_pro_blog';

        if ((int)$this->config->get('theme_codecart_pro_menu_show_categories')) {
            $depth = $this->bound((int)$this->config->get('theme_codecart_pro_mega_menu_depth'), 1, 4, 3);
            $data['categories'] = $this->categories(0, 1, $depth, '');
        }

        return $this->load->view('extension/module/codecart_pro_mega_menu', $data);
    }

    private function categories($parent_id, $level, $max_depth, $path) {
        $rows = $this->model_catalog_category->getCategories((int)$parent_id);
        $items = array();
        $only_top = (int)$this->config->get('theme_codecart_pro_mega_menu_only_top');
        $show_images = (int)$this->config->get('theme_codecart_pro_mega_menu_show_images');
        $show_counts = (int)$this->config->get('theme_codecart_pro_mega_menu_show_counts');

        if ($show_counts) {
            $this->load->model('catalog/product');
        }

        foreach ($rows as $row) {
            if (!$parent_id && $only_top && isset($row['top']) && !$row['top']) {
                continue;
            }

            $category_path = $path ? $path . '_' . (int)$row['category_id'] : (string)(int)$row['category_id'];
            $children = array();
            if ($level < $max_depth) {
                $children = $this->categories((int)$row['category_id'], $level + 1, $max_depth, $category_path);
            }

            $image = '';
            if ($show_images && !empty($row['image'])) {
                $image = $this->model_tool_image->resize($row['image'], 72, 72);
            }

            $total = null;
            if ($show_counts) {
                $total = (int)$this->model_catalog_product->getTotalProducts(array(
                    'filter_category_id' => (int)$row['category_id'],
                    'filter_sub_category' => true
                ));
            }

            $items[] = array(
                'category_id' => (int)$row['category_id'],
                'name' => $row['name'],
                'href' => $this->url->link('product/category', 'path=' . $category_path),
                'image' => $image,
                'total' => $total,
                'children' => $children
            );
        }

        return $items;
    }

    private function customLinks() {
        $links = array();
        for ($i = 1; $i <= 6; $i++) {
            $title = $this->pickLang($this->config->get('theme_codecart_pro_menu_link' . $i . '_title'), '');
            $href = trim((string)$this->config->get('theme_codecart_pro_menu_link' . $i . '_url'));
            $icon = trim((string)$this->config->get('theme_codecart_pro_menu_link' . $i . '_icon'));
            if ($title && $href) {
                $links[] = array('title' => $title, 'href' => $href, 'icon' => $icon);
            }
        }
        return $links;
    }

    private function pickLang($value, $fallback) {
        $language_id = (int)$this->config->get('config_language_id');
        if (is_array($value)) {
            if (!empty($value[$language_id])) {
                return $value[$language_id];
            }
            foreach ($value as $item) {
                if (!empty($item)) {
                    return $item;
                }
            }
        }
        return $fallback;
    }

    private function bound($value, $min, $max, $fallback) {
        $value = (int)$value;
        if ($value < $min || $value > $max) {
            return $fallback;
        }
        return $value;
    }

    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        return ($config_theme === 'codecart_pro' || $directory === 'codecart_pro' || (string)$this->config->get('config_template') === 'codecart_pro');
    }

}
