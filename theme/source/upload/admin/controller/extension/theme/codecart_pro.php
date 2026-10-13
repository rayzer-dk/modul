<?php
class ControllerExtensionThemeCodecartPro extends Controller {
    private $error = array();
    private $code = 'theme_codecart_pro';
    private const SCHEMA_VERSION = 870;

    private function hasValidUserToken() {
        $expected = isset($this->session->data['user_token']) ? (string)$this->session->data['user_token'] : '';
        $provided = '';

        if (isset($this->request->get['user_token'])) {
            $provided = (string)$this->request->get['user_token'];
        } elseif (isset($this->request->post['user_token'])) {
            $provided = (string)$this->request->post['user_token'];
        }

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    public function index() {
        $this->load->language('extension/theme/codecart_pro');
        $this->document->setTitle($this->language->get('heading_title_text'));
        $this->document->addStyle('view/stylesheet/codecart_pro/theme_admin.css?v=8.7.7');
        $this->document->addScript('view/javascript/codecart_pro/theme_admin_ux.js?v=8.7.7');

        if (!$this->hasValidUserToken() || !$this->user->hasPermission('access', 'extension/theme/codecart_pro')) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme', true));
            return;
        }

        $this->load->model('setting/setting');
        $this->load->model('localisation/language');
        $form_complete = ($this->request->server['REQUEST_METHOD'] !== 'POST') || (isset($this->request->post['ccp_settings_complete']) && $this->request->post['ccp_settings_complete'] === '1');
        if (!$form_complete) {
            // A missing final marker means PHP truncated the form or the request is partial.
            $this->error['warning'] = $this->language->get('error_form_incomplete');
        }
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $form_complete && $this->validate()) {
            $settings = $this->sanitize($this->request->post);
            $schema_ready = empty($settings['theme_codecart_pro_status']) || $this->upgradeSchemaIfNeeded();
            if (!$schema_ready) {
                $this->error['warning'] = $this->language->get('error_schema_upgrade');
            } else {
                $this->model_setting_setting->editSetting($this->code, $settings);
                $this->syncRuntimeEvents(!empty($settings['theme_codecart_pro_status']));
                $this->syncBlogIndexSeoUrls($settings);
                $this->session->data['success'] = $this->language->get('text_success');

                $ccp_active_tab = isset($this->request->post['ccp_active_tab']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', ltrim((string)$this->request->post['ccp_active_tab'], '#')) : '';
                $ccp_scroll_top = isset($this->request->post['ccp_scroll_top']) ? max(0, (int)$this->request->post['ccp_scroll_top']) : 0;
                $ccp_redirect_args = 'user_token=' . $this->session->data['user_token'];
                if ($ccp_active_tab !== '') {
                    $ccp_redirect_args .= '&ccp_tab=' . rawurlencode($ccp_active_tab);
                }
                if ($ccp_scroll_top > 0) {
                    $ccp_redirect_args .= '&ccp_scroll=' . (int)$ccp_scroll_top;
                }

                $this->response->redirect($this->url->link('extension/theme/codecart_pro', $ccp_redirect_args, true));
            }
        }

        $data = array();
        foreach ($this->languageKeys() as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : (isset($this->session->data['error_warning']) ? $this->session->data['error_warning'] : '');
        unset($this->session->data['error_warning']);
        $data['success'] = $data['error_warning'] === '' && isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme', true)),
            array('text' => $this->language->get('heading_title_text'), 'href' => $this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true))
        );

        $data['action'] = $this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme', true);
        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['version'] = '8.7.7';
        $data['ccp_active_tab'] = isset($this->request->get['ccp_tab']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$this->request->get['ccp_tab']) : '';
        $data['ccp_scroll_top'] = isset($this->request->get['ccp_scroll']) ? max(0, (int)$this->request->get['ccp_scroll']) : 0;
        $data['author_url'] = 'https://codecartpro.com';
        $data['export_settings'] = $this->url->link('extension/theme/codecart_pro/exportSettings', 'user_token=' . $this->session->data['user_token'], true);
        $data['import_settings'] = $this->url->link('extension/theme/codecart_pro/importSettings', 'user_token=' . $this->session->data['user_token'], true);
        $data['preset_action'] = $this->url->link('extension/theme/codecart_pro/applyPreset', 'user_token=' . $this->session->data['user_token'], true);
        $data['blog_manager'] = $this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true);
        $data['review_manager'] = $this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true);
        $data['ccp_filemanager_url'] = 'index.php?route=common/filemanager&user_token=' . $this->session->data['user_token'];
        $data['ccp_sys_input_vars'] = (int)ini_get('max_input_vars');
        $data['ccp_product_autocomplete_url'] = 'index.php?route=catalog/product/autocomplete&user_token=' . $this->session->data['user_token'];
        $data['ccp_category_autocomplete_url'] = 'index.php?route=catalog/category/autocomplete&user_token=' . $this->session->data['user_token'];
        $data['ccp_information_autocomplete_url'] = 'index.php?route=catalog/information/autocomplete&user_token=' . $this->session->data['user_token'];
        $catalog_url = defined('HTTPS_CATALOG') ? HTTPS_CATALOG : (defined('HTTP_CATALOG') ? HTTP_CATALOG : '../');
        $data['theme_preview'] = rtrim($catalog_url, '/') . '/catalog/view/theme/codecart_pro/image/codecart_pro.png';
        $data['theme_directory'] = 'codecart_pro';
        $data['theme_selected'] = (((string)$this->config->get('config_theme') === 'codecart_pro') || ((string)$this->config->get('theme_' . $this->config->get('config_theme') . '_directory') === 'codecart_pro') || ((string)$this->config->get('config_template') === 'codecart_pro')) ? 1 : 0;
        $data['theme_enabled'] = (int)$this->config->get('theme_codecart_pro_status');
        $data['store_settings_url'] = $this->url->link('setting/setting', 'user_token=' . $this->session->data['user_token'], true);
        $data['extensions_theme_url'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=theme', true);
        $data['blog_sitemap_url'] = rtrim($catalog_url, '/') . '/index.php?route=extension/feed/codecart_pro_sitemap';
        $data['catalog_home_url'] = rtrim($catalog_url, '/') . '/';
        $data['diagnostics'] = $this->getDiagnostics();
        $data['diagnostic_summary'] = array('total' => count($data['diagnostics']), 'good' => 0, 'warning' => 0, 'bad' => 0);
        foreach ($data['diagnostics'] as $diagnostic_item) {
            $diagnostic_status = isset($diagnostic_item['status']) ? (string)$diagnostic_item['status'] : 'warning';
            if (!isset($data['diagnostic_summary'][$diagnostic_status])) {
                $diagnostic_status = 'warning';
            }
            $data['diagnostic_summary'][$diagnostic_status]++;
        }
        $data['ccp_request_summary'] = $this->getRequestSummary();
        $request_limit = (int)$this->config->get('config_admin_limit');
        if ($request_limit < 1) { $request_limit = 20; }
        $request_limit = min(100, $request_limit);

        $quick_page = isset($this->request->get['quick_page']) ? max(1, (int)$this->request->get['quick_page']) : 1;
        $callback_page = isset($this->request->get['callback_page']) ? max(1, (int)$this->request->get['callback_page']) : 1;
        $subscriber_page = isset($this->request->get['subscriber_page']) ? max(1, (int)$this->request->get['subscriber_page']) : 1;

        $quick_data = $this->getPagedRows('codecart_pro_quick_order', $quick_page, $request_limit);
        $callback_data = $this->getPagedRows('codecart_pro_callback_request', $callback_page, $request_limit);
        $subscriber_data = $this->getPagedRows('codecart_pro_subscriber', $subscriber_page, $request_limit);

        $data['ccp_recent_quick_orders'] = $quick_data['rows'];
        $data['ccp_recent_callbacks'] = $callback_data['rows'];
        $data['ccp_recent_subscribers'] = $subscriber_data['rows'];
        $data['ccp_quick_pagination'] = $this->renderRequestPagination($quick_data['total'], $quick_page, $request_limit, 'quick_page');
        $data['ccp_callback_pagination'] = $this->renderRequestPagination($callback_data['total'], $callback_page, $request_limit, 'callback_page');
        $data['ccp_subscriber_pagination'] = $this->renderRequestPagination($subscriber_data['total'], $subscriber_page, $request_limit, 'subscriber_page');
        $data['ccp_quick_results'] = $this->paginationResults($quick_data['total'], $quick_page, $request_limit);
        $data['ccp_callback_results'] = $this->paginationResults($callback_data['total'], $callback_page, $request_limit);
        $data['ccp_subscriber_results'] = $this->paginationResults($subscriber_data['total'], $subscriber_page, $request_limit);

        $data['ccp_recent_blog_articles'] = $this->getRecentRows('codecart_pro_blog_article', 'article_id', 8);
        $data['ccp_recent_blog_categories'] = $this->getRecentRows('codecart_pro_blog_category', 'category_id', 8);
        $data['ccp_recent_enhanced_reviews'] = $this->getRecentRows('codecart_pro_review_extra', 'review_extra_id', 8);

        $defaults = $this->remapLanguageDefaults($this->defaults());
        foreach ($defaults as $key => $value) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } elseif ($this->config->has($key)) {
                $data[$key] = $this->config->get($key);
            } else {
                $data[$key] = $value;
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/theme/codecart_pro', $data));
    }

    public function exportSettings() {
        $this->load->language('extension/theme/codecart_pro');

        if (!$this->hasValidUserToken() || !$this->user->hasPermission('access', 'extension/theme/codecart_pro')) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode(array('error' => $this->language->get('error_permission'))));
            return;
        }

        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting($this->code);
        $export = array(
            'module' => 'CodeCart PRO Theme',
            'version' => '8.7.7',
            'exported_at' => date('c'),
            'settings' => array()
        );

        foreach ($settings as $key => $value) {
            if (strpos($key, 'theme_codecart_pro_') === 0 && $key !== 'theme_codecart_pro_directory') {
                $export['settings'][$key] = $value;
            }
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->addHeader('Content-Disposition: attachment; filename="codecart_pro_theme_settings_v8.7.7.json"');
        $this->response->setOutput(json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function importSettings() {
        $this->load->language('extension/theme/codecart_pro');
        if (!isset($this->request->server['REQUEST_METHOD']) || strtoupper((string)$this->request->server['REQUEST_METHOD']) !== 'POST') {
            $this->session->data['error_warning'] = $this->language->get('error_request_method');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }
        $this->load->model('setting/setting');
        $this->load->model('localisation/language');

        if (!$this->validate()) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        if (empty($this->request->files['ccp_import_file']['tmp_name']) || !is_uploaded_file($this->request->files['ccp_import_file']['tmp_name'])) {
            $this->session->data['error_warning'] = $this->language->get('error_import_file');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $import_file = $this->request->files['ccp_import_file'];
        $import_size = isset($import_file['size']) ? (int)$import_file['size'] : (int)@filesize($import_file['tmp_name']);
        $max_import_size = 2 * 1024 * 1024;

        if ($import_size <= 0 || $import_size > $max_import_size) {
            $this->session->data['error_warning'] = $this->language->get('error_import_size');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $raw = file_get_contents($import_file['tmp_name']);
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            $this->session->data['error_warning'] = $this->language->get('error_import_json');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $incoming = isset($json['settings']) && is_array($json['settings']) ? $json['settings'] : $json;
        $allowed = array_keys($this->defaults());
        $filtered = array();

        foreach ($incoming as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $filtered[$key] = $value;
            }
        }

        if (!$filtered) {
            $this->session->data['error_warning'] = $this->language->get('error_import_empty');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $current = $this->model_setting_setting->getSetting($this->code);
        $settings = array_merge($this->remapLanguageDefaults($this->defaults()), $current, $filtered);
        $settings = $this->sanitize($settings);
        if (!empty($settings['theme_codecart_pro_status']) && !$this->upgradeSchemaIfNeeded()) {
            $this->schemaUpgradeWarning();
            return;
        }
        $this->model_setting_setting->editSetting($this->code, $settings);
        $this->syncRuntimeEvents(!empty($settings['theme_codecart_pro_status']));
        $this->syncBlogIndexSeoUrls($settings);

        $this->session->data['success'] = $this->language->get('text_import_success');
        $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function applyPreset() {
        $this->load->language('extension/theme/codecart_pro');
        if (!isset($this->request->server['REQUEST_METHOD']) || strtoupper((string)$this->request->server['REQUEST_METHOD']) !== 'POST') {
            $this->session->data['error_warning'] = $this->language->get('error_request_method');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }
        $this->load->model('setting/setting');
        $this->load->model('localisation/language');

        if (!$this->validate()) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $preset = isset($this->request->post['ccp_preset']) ? (string)$this->request->post['ccp_preset'] : 'balanced';
        $settings = array_merge($this->remapLanguageDefaults($this->defaults()), $this->model_setting_setting->getSetting($this->code));

        if ($preset === 'speed') {
            $settings['theme_codecart_pro_home_slider_autoplay'] = 0;
            $settings['theme_codecart_pro_card_second_image_status'] = 0;
            $settings['theme_codecart_pro_mega_menu_show_images'] = 0;
            $settings['theme_codecart_pro_mega_menu_show_counts'] = 0;
            $settings['theme_codecart_pro_performance_eager_first_images'] = 1;
            $settings['theme_codecart_pro_product_module_layout'] = 'compact';
            $settings['theme_codecart_pro_popup_notification_status'] = 0;
        } elseif ($preset === 'commerce') {
            $settings['theme_codecart_pro_home_slider_status'] = 1;
            $settings['theme_codecart_pro_home_product_sections_status'] = 1;
            $settings['theme_codecart_pro_enable_quick_order'] = 1;
            $settings['theme_codecart_pro_enable_callback'] = 1;
            $settings['theme_codecart_pro_cart_drawer_status'] = 1;
            $settings['theme_codecart_pro_enable_stickers'] = 1;
            $settings['theme_codecart_pro_product_module_layout'] = 'carousel';
        } elseif ($preset === 'seo') {
            $settings['theme_codecart_pro_seo_schema_status'] = 1;
            $settings['theme_codecart_pro_merchant_schema_status'] = 1;
            $settings['theme_codecart_pro_website_search_schema_status'] = 1;
            $settings['theme_codecart_pro_schema_breadcrumbs_status'] = 1;
            $settings['theme_codecart_pro_blog_status'] = 1;
            $settings['theme_codecart_pro_blog_sitemap_status'] = 1;
            $settings['theme_codecart_pro_seo_index_guard_status'] = 1;
        } else {
            $settings['theme_codecart_pro_home_slider_status'] = 1;
            $settings['theme_codecart_pro_product_modules_status'] = 1;
            $settings['theme_codecart_pro_performance_eager_first_images'] = 2;
            $settings['theme_codecart_pro_ajax_catalog_status'] = 1;
            $settings['theme_codecart_pro_checkout_style_status'] = 1;
        }

        $settings = $this->sanitize($settings);
        if (!empty($settings['theme_codecart_pro_status']) && !$this->upgradeSchemaIfNeeded()) {
            $this->schemaUpgradeWarning();
            return;
        }
        $this->model_setting_setting->editSetting($this->code, $settings);
        $this->syncRuntimeEvents(!empty($settings['theme_codecart_pro_status']));
        $this->syncBlogIndexSeoUrls($settings);

        $this->session->data['success'] = $this->language->get('text_preset_success');
        $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
    }

    private function canManageLifecycle() {
        return $this->hasValidUserToken()
            && ($this->user->hasPermission('modify', 'extension/extension/theme')
                || $this->user->hasPermission('modify', 'extension/theme/codecart_pro'));
    }

    public function install() {
        if (!$this->canManageLifecycle()) { return; }
        $this->load->model('setting/setting');
        $this->load->model('localisation/language');
        $defaults = $this->remapLanguageDefaults($this->defaults());
        $current = $this->model_setting_setting->getSetting($this->code);
        $is_fresh_install = empty($current);
        $settings = array_merge($defaults, $current);
        $settings['theme_codecart_pro_schema_version'] = self::SCHEMA_VERSION;
        $settings['theme_codecart_pro_blog_schema_version'] = self::SCHEMA_VERSION;
        if ($is_fresh_install) {
            $settings['theme_codecart_pro_status'] = 0;
            $settings['theme_codecart_pro_information_mode'] = 1;
        }
        if (!$this->prepareSchema()) {
            $this->schemaUpgradeWarning();
            return;
        }
        $this->model_setting_setting->editSetting($this->code, $settings);
        $this->syncRuntimeEvents(!empty($settings['theme_codecart_pro_status']));
        $this->syncBlogIndexSeoUrls($settings);
        $this->load->model('user/user_group');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/theme/codecart_pro');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/theme/codecart_pro');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/codecart_pro_blog');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/codecart_pro_blog');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/codecart_pro_review');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/codecart_pro_review');
    }

    private function syncRuntimeEvents($enabled) {
        $this->load->model('setting/event');

        $events = array(
            'codecart_pro_request_path_guard' => array('trigger' => 'catalog/controller/startup/router/before', 'action' => 'extension/module/codecart_pro_compatibility/requestPathGuard'),
            'codecart_pro_featured_path_guard' => array('trigger' => 'catalog/controller/extension/module/featured_product/before', 'action' => 'extension/module/codecart_pro_compatibility/featuredPathGuard'),
            'codecart_pro_product_path_guard' => array('trigger' => 'catalog/controller/product/product/before', 'action' => 'extension/module/codecart_pro_compatibility/featuredPathGuard'),
            'codecart_pro_category_view' => array('trigger' => 'catalog/view/product/category/before', 'action' => 'extension/module/codecart_pro_compatibility/categoryViewBefore'),
            'codecart_pro_product_view' => array('trigger' => 'catalog/view/product/product/before', 'action' => 'extension/module/codecart_pro_compatibility/productViewBefore')
        );

        foreach ($events as $code => $event) {
            $existing = array();
            if ($this->tableExists('event')) {
                $query = $this->db->query("SELECT event_id, `trigger`, action, status, sort_order FROM `" . DB_PREFIX . "event` WHERE code = '" . $this->db->escape($code) . "' ORDER BY event_id ASC");
                $existing = $query->rows;
            }

            $matches = count($existing) === 1
                && (string)$existing[0]['trigger'] === (string)$event['trigger']
                && (string)$existing[0]['action'] === (string)$event['action']
                && (int)$existing[0]['status'] === 1
                && (int)$existing[0]['sort_order'] === 0;

            if ($enabled && $matches) {
                continue;
            }
            if (!$enabled && !$existing) {
                continue;
            }

            // OpenCart exposes models through Proxy::__call; method_exists() on
            // that proxy cannot detect the public model methods.
            $this->model_setting_event->deleteEventByCode($code);
            if ($enabled) {
                $this->model_setting_event->addEvent($code, $event['trigger'], $event['action'], 1, 0);
            }
        }
    }

    private function ensureSchema() {
        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_quick_order` (
            `quick_order_id` INT(11) NOT NULL AUTO_INCREMENT,
            `store_id` INT(11) NOT NULL DEFAULT 0,
            `language_id` INT(11) NOT NULL DEFAULT 0,
            `product_id` INT(11) NOT NULL DEFAULT 0,
            `product_name` VARCHAR(255) NOT NULL DEFAULT '',
            `quantity` INT(11) NOT NULL DEFAULT 1,
            `customer_name` VARCHAR(128) NOT NULL DEFAULT '',
            `customer_phone` VARCHAR(64) NOT NULL DEFAULT '',
            `customer_email` VARCHAR(128) NOT NULL DEFAULT '',
            `comment` TEXT NULL,
            `product_options` TEXT NULL,
            `product_option_data` MEDIUMTEXT NULL,
            `page_url` TEXT NULL,
            `source` VARCHAR(32) NOT NULL DEFAULT '',
            `ip` VARCHAR(64) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            `status` TINYINT(1) NOT NULL DEFAULT 0,
            `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`quick_order_id`),
            KEY `product_id` (`product_id`),
            KEY `date_added` (`date_added`),
            KEY `ip_date` (`ip`, `date_added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_callback_request` (
            `callback_request_id` INT(11) NOT NULL AUTO_INCREMENT,
            `store_id` INT(11) NOT NULL DEFAULT 0,
            `language_id` INT(11) NOT NULL DEFAULT 0,
            `customer_name` VARCHAR(128) NOT NULL DEFAULT '',
            `customer_phone` VARCHAR(64) NOT NULL DEFAULT '',
            `customer_email` VARCHAR(128) NOT NULL DEFAULT '',
            `comment` TEXT NULL,
            `product_options` TEXT NULL,
            `product_option_data` MEDIUMTEXT NULL,
            `page_url` TEXT NULL,
            `source` VARCHAR(32) NOT NULL DEFAULT '',
            `ip` VARCHAR(64) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
            `status` TINYINT(1) NOT NULL DEFAULT 0,
            `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`callback_request_id`),
            KEY `customer_phone` (`customer_phone`),
            KEY `date_added` (`date_added`),
            KEY `ip_date` (`ip`, `date_added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_subscriber` (
            `subscriber_id` INT(11) NOT NULL AUTO_INCREMENT,
            `store_id` INT(11) NOT NULL DEFAULT 0,
            `language_id` INT(11) NOT NULL DEFAULT 0,
            `email` VARCHAR(191) NOT NULL DEFAULT '',
            `name` VARCHAR(128) NOT NULL DEFAULT '',
            `source` VARCHAR(64) NOT NULL DEFAULT 'footer',
            `ip` VARCHAR(64) NOT NULL DEFAULT '',
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`subscriber_id`),
            UNIQUE KEY `store_email` (`store_id`, `email`),
            KEY `date_added` (`date_added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article` (
            `article_id` INT(11) NOT NULL AUTO_INCREMENT,
            `store_id` INT(11) NOT NULL DEFAULT 0,
            `image` VARCHAR(255) NOT NULL DEFAULT '',
            `keyword` VARCHAR(191) NOT NULL DEFAULT '',
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `date_available` DATE NULL DEFAULT NULL,
            `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_modified` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`article_id`),
            KEY `status` (`status`),
            KEY `keyword` (`keyword`),
            KEY `date_added` (`date_added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_description` (
            `article_id` INT(11) NOT NULL,
            `language_id` INT(11) NOT NULL,
            `keyword` VARCHAR(191) NOT NULL DEFAULT '',
            `title` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_description` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_keyword` VARCHAR(255) NOT NULL DEFAULT '',
            `description` MEDIUMTEXT NULL,
            `seo_text` MEDIUMTEXT NULL,
            PRIMARY KEY (`article_id`, `language_id`),
            KEY `title` (`title`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_category` (
            `category_id` INT(11) NOT NULL AUTO_INCREMENT,
            `image` VARCHAR(255) NOT NULL DEFAULT '',
            `keyword` VARCHAR(191) NOT NULL DEFAULT '',
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_modified` DATETIME NULL,
            PRIMARY KEY (`category_id`),
            KEY `status_sort` (`status`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_category_description` (
            `category_id` INT(11) NOT NULL,
            `language_id` INT(11) NOT NULL,
            `keyword` VARCHAR(191) NOT NULL DEFAULT '',
            `name` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_description` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_keyword` VARCHAR(255) NOT NULL DEFAULT '',
            `description` MEDIUMTEXT NULL,
            `seo_text` MEDIUMTEXT NULL,
            PRIMARY KEY (`category_id`, `language_id`),
            KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_to_category` (`article_id` INT(11) NOT NULL, `category_id` INT(11) NOT NULL, PRIMARY KEY (`article_id`, `category_id`), KEY `category_id` (`category_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_to_product` (`article_id` INT(11) NOT NULL, `product_id` INT(11) NOT NULL, PRIMARY KEY (`article_id`, `product_id`), KEY `product_id` (`product_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_related` (`article_id` INT(11) NOT NULL, `related_article_id` INT(11) NOT NULL, PRIMARY KEY (`article_id`, `related_article_id`), KEY `related_article_id` (`related_article_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` (`blog_category_id` INT(11) NOT NULL, `category_id` INT(11) NOT NULL, PRIMARY KEY (`blog_category_id`, `category_id`), KEY `category_id` (`category_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $column = @$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "codecart_pro_blog_article_description` LIKE 'tags'");
        if (!$column->num_rows) {
            @$this->db->query("ALTER TABLE `" . DB_PREFIX . "codecart_pro_blog_article_description` ADD `tags` VARCHAR(255) NOT NULL DEFAULT '' AFTER `keyword`");
        }



        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_product_changelog` (
            `product_id` INT(11) NOT NULL,
            `language_id` INT(11) NOT NULL,
            `changelog` MEDIUMTEXT NULL,
            `date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`product_id`, `language_id`),
            KEY `language_id` (`language_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_review_extra` (
            `review_extra_id` INT(11) NOT NULL AUTO_INCREMENT,
            `review_id` INT(11) NOT NULL DEFAULT 0,
            `product_id` INT(11) NOT NULL DEFAULT 0,
            `plus_text` TEXT NULL,
            `minus_text` TEXT NULL,
            `admin_reply` TEXT NULL,
            `images` MEDIUMTEXT NULL,
            `real_buyer` TINYINT(1) NOT NULL DEFAULT 0,
            `votes_plus` INT(11) NOT NULL DEFAULT 0,
            `votes_minus` INT(11) NOT NULL DEFAULT 0,
            `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`review_extra_id`),
            UNIQUE KEY `review_id` (`review_id`),
            KEY `product_id` (`product_id`),
            KEY `real_buyer` (`real_buyer`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $schema_columns = array(
            'codecart_pro_quick_order' => array('quantity', 'product_options', 'product_option_data', 'source'),
            'codecart_pro_callback_request' => array('product_options', 'product_option_data', 'source'),
            'codecart_pro_blog_article' => array('date_available', 'date_modified'),
            'codecart_pro_blog_article_description' => array('tags', 'seo_text'),
            'codecart_pro_blog_category' => array('date_modified'),
            'codecart_pro_blog_category_description' => array('seo_text'),
            'codecart_pro_review_extra' => array('product_id', 'admin_reply', 'images', 'real_buyer', 'votes_plus', 'votes_minus')
        );

        foreach ($schema_columns as $table => $columns) {
            foreach ($columns as $column) {
                $this->ensureSchemaColumn($table, $column);
            }
        }

        $schema_indexes = array(
            'codecart_pro_quick_order' => array('product_id', 'date_added', 'ip_date'),
            'codecart_pro_callback_request' => array('customer_phone', 'date_added', 'ip_date'),
            'codecart_pro_subscriber' => array('date_added'),
            'codecart_pro_blog_article' => array('status', 'keyword', 'date_added'),
            'codecart_pro_blog_category' => array('status_sort'),
            'codecart_pro_review_extra' => array('product_id', 'real_buyer')
        );

        foreach ($schema_indexes as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->ensureSchemaIndex($table, $index);
            }
        }

        $this->ensureReviewUniqueIndex();
    }

    private function ensureReviewUniqueIndex() {
        if (!$this->tableExists('codecart_pro_review_extra')) {
            return;
        }

        try {
            $index = @$this->db->query("SHOW INDEX FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE Key_name = 'review_id'");
            if ($index->num_rows) {
                return;
            }

            $duplicates = @$this->db->query("SELECT review_id FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE review_id > 0 GROUP BY review_id HAVING COUNT(*) > 1 LIMIT 1");
            if ($duplicates->num_rows) {
                $this->log->write('CodeCart PRO Theme schema migration skipped unique review_id index because duplicate legacy rows exist. Resolve duplicates in codecart_pro_review_extra and save theme settings again.');
                return;
            }

            @$this->db->query("ALTER TABLE `" . DB_PREFIX . "codecart_pro_review_extra` ADD UNIQUE KEY `review_id` (`review_id`)");
        } catch (Exception $e) {
            $this->log->write('CodeCart PRO Theme schema unique review index migration failed: ' . $e->getMessage());
            throw $e;
        } catch (Throwable $e) {
            $this->log->write('CodeCart PRO Theme schema unique review index migration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function ensureSchemaColumn($table, $column) {
        $definitions = array(
            'codecart_pro_quick_order' => array(
                'quantity' => "INT(11) NOT NULL DEFAULT 1 AFTER `product_name`",
                'product_options' => "TEXT NULL AFTER `comment`",
                'product_option_data' => "MEDIUMTEXT NULL AFTER `product_options`",
                'source' => "VARCHAR(32) NOT NULL DEFAULT '' AFTER `page_url`"
            ),
            'codecart_pro_callback_request' => array(
                'product_options' => "TEXT NULL AFTER `comment`",
                'product_option_data' => "MEDIUMTEXT NULL AFTER `product_options`",
                'source' => "VARCHAR(32) NOT NULL DEFAULT '' AFTER `page_url`"
            ),
            'codecart_pro_blog_article' => array(
                'date_available' => "DATE NULL DEFAULT NULL AFTER `sort_order`",
                'date_modified' => "DATETIME NULL DEFAULT NULL AFTER `date_added`"
            ),
            'codecart_pro_blog_article_description' => array(
                'tags' => "VARCHAR(255) NOT NULL DEFAULT '' AFTER `keyword`",
                'seo_text' => "MEDIUMTEXT NULL AFTER `description`"
            ),
            'codecart_pro_blog_category' => array(
                'date_modified' => "DATETIME NULL DEFAULT NULL AFTER `date_added`"
            ),
            'codecart_pro_blog_category_description' => array(
                'seo_text' => "MEDIUMTEXT NULL AFTER `description`"
            ),
            'codecart_pro_review_extra' => array(
                'product_id' => "INT(11) NOT NULL DEFAULT 0 AFTER `review_id`",
                'admin_reply' => "TEXT NULL AFTER `minus_text`",
                'images' => "MEDIUMTEXT NULL AFTER `admin_reply`",
                'real_buyer' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `images`",
                'votes_plus' => "INT(11) NOT NULL DEFAULT 0 AFTER `real_buyer`",
                'votes_minus' => "INT(11) NOT NULL DEFAULT 0 AFTER `votes_plus`"
            )
        );

        if (!isset($definitions[$table][$column]) || !$this->tableExists($table)) {
            return;
        }

        try {
            $query = @$this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` LIKE '" . $this->db->escape($column) . "'");
            if (!$query->num_rows) {
                @$this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD `" . $column . "` " . $definitions[$table][$column]);
            }
        } catch (Exception $e) {
            $this->log->write('CodeCart PRO Theme schema column migration failed [' . $table . '.' . $column . ']: ' . $e->getMessage());
            throw $e;
        } catch (Throwable $e) {
            $this->log->write('CodeCart PRO Theme schema column migration failed [' . $table . '.' . $column . ']: ' . $e->getMessage());
            throw $e;
        }
    }

    private function ensureSchemaIndex($table, $index) {
        $definitions = array(
            'codecart_pro_quick_order' => array(
                'product_id' => "KEY `product_id` (`product_id`)",
                'date_added' => "KEY `date_added` (`date_added`)",
                'ip_date' => "KEY `ip_date` (`ip`, `date_added`)"
            ),
            'codecart_pro_callback_request' => array(
                'customer_phone' => "KEY `customer_phone` (`customer_phone`)",
                'date_added' => "KEY `date_added` (`date_added`)",
                'ip_date' => "KEY `ip_date` (`ip`, `date_added`)"
            ),
            'codecart_pro_subscriber' => array(
                'date_added' => "KEY `date_added` (`date_added`)"
            ),
            'codecart_pro_blog_article' => array(
                'status' => "KEY `status` (`status`)",
                'keyword' => "KEY `keyword` (`keyword`)",
                'date_added' => "KEY `date_added` (`date_added`)"
            ),
            'codecart_pro_blog_category' => array(
                'status_sort' => "KEY `status_sort` (`status`, `sort_order`)"
            ),
            'codecart_pro_review_extra' => array(
                'product_id' => "KEY `product_id` (`product_id`)",
                'real_buyer' => "KEY `real_buyer` (`real_buyer`)"
            )
        );

        if (!isset($definitions[$table][$index]) || !$this->tableExists($table)) {
            return;
        }

        try {
            $query = @$this->db->query("SHOW INDEX FROM `" . DB_PREFIX . $table . "` WHERE Key_name = '" . $this->db->escape($index) . "'");
            if (!$query->num_rows) {
                @$this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD " . $definitions[$table][$index]);
            }
        } catch (Exception $e) {
            $this->log->write('CodeCart PRO Theme schema index migration failed [' . $table . '.' . $index . ']: ' . $e->getMessage());
            throw $e;
        } catch (Throwable $e) {
            $this->log->write('CodeCart PRO Theme schema index migration failed [' . $table . '.' . $index . ']: ' . $e->getMessage());
            throw $e;
        }
    }

    private function prepareSchema() {
        try {
            $this->ensureSchema();
            return true;
        } catch (Throwable $error) {
            $this->log->write('CodeCart PRO Theme required schema migration failed: ' . $error->getMessage());
            return false;
        }
    }

    private function schemaUpgradeWarning() {
        $this->load->language('extension/theme/codecart_pro');
        $this->session->data['error_warning'] = $this->language->get('error_schema_upgrade');
        $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
    }

    private function upgradeSchemaIfNeeded() {
        // Schema changes belong to an explicitly authorised save/install flow.
        // Presentation settings and customer custom code are never migrations.
        if ((int)$this->config->get('theme_codecart_pro_schema_version') < self::SCHEMA_VERSION
            || (int)$this->config->get('theme_codecart_pro_blog_schema_version') < self::SCHEMA_VERSION) {
            return $this->prepareSchema();
        }
        return true;
    }

    public function uninstall() {
        if (!$this->canManageLifecycle()) { return; }
        $this->load->model('setting/setting');
        $this->syncRuntimeEvents(false);

        // Keep commercial/content data by default. The store owner can disable this before uninstalling.
        // This prevents accidental loss of quick orders, callbacks, subscribers, blog content and enhanced reviews.
        $keep_data = $this->config->has('theme_codecart_pro_uninstall_keep_data') ? (int)$this->config->get('theme_codecart_pro_uninstall_keep_data') : 1;

        // Remove active integration records even when user data tables are intentionally preserved.
        if ($this->tableExists('seo_url')) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query IN ('ccp_blog=1', 'route=extension/module/codecart_pro_blog')");
        }

        $this->load->model('user/user_group');
        $routes = array(
            'extension/theme/codecart_pro',
            'extension/module/codecart_pro_blog',
            'extension/module/codecart_pro_review'
        );
        foreach ($routes as $route) {
            $this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', $route);
            $this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', $route);
        }

        // Detach stores selecting this theme before its settings/templates disappear.
        // No other store theme, product, order or customer data is changed.
        $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET value = 'default' WHERE code = 'config' AND `key` IN ('config_theme', 'config_template') AND value = 'codecart_pro'");
        $this->cache->delete('config');

        $this->model_setting_setting->deleteSetting($this->code);

        if (!$keep_data) {
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_quick_order`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_callback_request`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_subscriber`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_blog_article`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_description`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_blog_category`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_blog_category_description`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_to_category`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_to_product`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_related`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_review_extra`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codecart_pro_product_changelog`");
        }
    }

    protected function validate() {
        if (!$this->hasValidUserToken() || !$this->user->hasPermission('modify', 'extension/theme/codecart_pro')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    private function languageKeys() {
        return array(
            'heading_title','heading_title_text','text_edit','text_enabled','text_disabled','text_yes','text_no','text_general','text_header','text_home_builder','text_cards','text_category','text_product','text_storefront_depth','text_mobile','text_product_ux','text_product_modules','text_interactions','text_requests','text_seo_content','text_performance','text_custom_code','text_release_tools','text_settings_backup','text_preset_wizard','text_checkout_style_pack','text_diagnostics','text_about','text_about_description','text_about_value','text_images','text_design','text_dark_theme','text_author','text_version','text_good','text_warning','text_bad','text_palette','text_px','text_width','text_height','text_language_values','text_theme_dashboard','text_theme_preview','text_theme_enabled','text_theme_disabled','text_theme_selected','text_theme_not_selected','text_theme_directory','text_store_settings','text_extensions_theme','text_quick_links','entry_footer_signature','placeholder_worktime','placeholder_trust_badges','placeholder_faq_pair','help_ajax_catalog','placeholder_product_benefits','placeholder_quick_order_success','entry_header_show_phone','entry_header_show_email','entry_header_show_worktime','entry_menu_show_home','entry_menu_show_categories','entry_menu_sticky','entry_menu_bg_color','entry_menu_text_color','entry_footer_show_contacts','entry_footer_bg_color','entry_floating_contacts','entry_floating_position','help_floating_position','entry_price_color','entry_old_price_color','entry_cart_drawer_status','help_cart_drawer','entry_cart_toast_status','help_cart_toast','entry_card_full_click','help_card_full_click','entry_card_show_buy_button','entry_card_show_quantity','help_card_show_quantity','entry_card_show_wishlist','entry_card_show_compare','entry_card_second_image_status','help_card_second_image','entry_card_sale_percent_status','help_card_sale_percent','entry_category_sticky_toolbar','help_category_sticky_toolbar','entry_category_description_collapse','help_category_description_collapse','entry_category_description_height','help_category_description_height','entry_category_hero_width','entry_category_hero_height','help_category_hero_size','entry_breadcrumb_schema','help_breadcrumb_schema','entry_category_itemlist_schema','help_category_itemlist_schema','entry_product_quantity_switch','help_product_quantity_switch','entry_product_bottom_sticky_buy','entry_product_bottom_sticky_buy_desktop','entry_product_bottom_sticky_buy_mobile','help_product_bottom_sticky_buy','entry_product_show_actions','help_product_show_actions','entry_product_show_attributes_pro','help_product_show_attributes','entry_product_sale_timer','help_product_sale_timer','entry_product_faq_status','help_product_faq_status','help_product_faq_schema','entry_product_trust_panel_status','help_product_trust_panel','entry_mega_menu_status','help_mega_menu_status','entry_mega_menu_only_top','help_mega_menu_only_top','entry_mega_menu_show_images','help_mega_menu_show_images','entry_mega_menu_show_counts','help_mega_menu_show_counts','entry_mega_menu_promo_status','text_link_number','entry_mobile_menu_show_search','entry_mobile_menu_show_categories','entry_mobile_menu_show_blog','entry_mobile_menu_show_contacts','entry_mobile_menu_show_account','entry_floating_cart_status','entry_floating_wishlist_status','entry_floating_compare_status','help_merchant_schema','help_product_review_schema','help_website_search_schema','help_shipping_schema','help_return_schema','help_eager_first_images','text_email','text_id','text_quantity','text_options','text_no_matches','text_open_cart_menu','text_native_collapse_to_icons','text_native_hide_menu','text_native_show_menu','text_inner_general','text_inner_stickers','text_inner_quick_order','text_inner_settings','text_inner_seo','text_inner_service','text_top_bar','text_mobile_bar','text_commerce_ux','text_mega_menu','text_hero_slider','text_promo_banners','text_categories_products_brands','text_grid','text_list','text_stickers','text_quick_order','help_quick_order_email','help_product_modules_light','text_carousel_horizontal','text_overlay','text_compact','text_where_show','text_block_types','text_header_contacts','entry_header_layout','text_classic','text_centered','text_phone','text_worktime','text_menu','entry_menu_style','entry_menu_width','text_horizontal','text_boxed','text_minimal','text_menu_width_full','text_menu_width_short','help_menu_width','text_footer_contacts','entry_footer_text','help_footer_signature','entry_payment_icons','entry_telegram_url','entry_viber_url','entry_whatsapp_url','text_prices_buttons','help_prices_buttons','text_cart_notifications','entry_cart_drawer_position','text_right','text_left','entry_notification_position','text_top_right','text_top_left','text_bottom_right','text_bottom_left','help_storefront_depth','text_product_card_list','text_commerce','help_card_title_lines','text_smart_available','text_always_show','text_hide','entry_card_hover_actions_mode','text_smart_hover','text_always_show_short','text_category_mobile_grid','text_position_top','text_position_bottom','text_position_both','help_category_description_position','text_product_card','text_clean','text_thumbs_bottom','text_thumbs_left','entry_product_attributes_style','text_table','text_cards_plural','text_compactly','text_product_trust_badges','help_pipe_short_items','text_product_faq','text_mega_menu_pro','entry_mega_menu_source','text_codecart_menu','text_native_menu','text_codecart_native_fallback','help_mega_menu_source','entry_mega_menu_depth','help_mega_menu_depth','entry_mega_menu_columns','entry_promo_title','entry_promo_text','entry_promo_link','text_custom_menu_links','help_custom_menu_links','text_name','text_url','text_fontawesome_icon','text_mobile_floating_actions','entry_header_search_mode','text_wide','text_quick_orders','text_callbacks','text_subscribers','text_recent_quick_orders','text_product_item','text_customer','text_date','text_no_data','text_pagination_results','text_recent_callbacks','text_comment','text_recent_subscribers','text_source','text_merchant_schema_google','help_shipping_country','help_shipping_price','help_shipping_days_min','help_shipping_days_max','help_return_days','text_seo_texts','help_seo_footer_text','text_blog_news','help_blog_sitemap_url','text_langdir_hreflang_seo_url','help_blog_keyword','help_langdir_prefix','help_blog_seo_v390','help_blog_crud_v390','text_reviews','help_reviews_v480','help_legacy_css','help_legacy_js','text_zone','text_selector','text_purpose','text_selector_header_purpose','text_selector_topbar_purpose','text_selector_menu_purpose','text_selector_product_card_purpose','text_selector_category_purpose','text_selector_product_page_purpose','text_selector_mobile_bar_purpose','text_selector_checkout_purpose','text_export','text_import','text_preset','text_preset_balanced','text_preset_speed','text_preset_seo','help_one_item_per_line','text_check','text_value','text_status','help_diagnostics_safe','text_expand','text_collapse','text_tab_search','text_compact_menu','text_group_base','text_group_catalog','text_group_sales','text_group_seo','text_group_service','text_max_input_vars_warning','text_select_image','text_choose_image','text_clear_image','text_link_helper','text_link_type_category','text_link_type_product','text_link_type_information','text_link_search_placeholder','text_link_preset_home','text_link_preset_catalog','text_link_preset_contact','text_link_preset_blog','help_home_link',
            'entry_status','entry_uninstall_keep_data','entry_checkout_style_status','entry_simple_checkout_status','entry_direct_checkout_after_add_status','entry_checkout_digital_mode','entry_checkout_digital_instruction_status','entry_checkout_digital_instruction_text','entry_checkout_hide_coupon_status','entry_checkout_hide_voucher_status','entry_checkout_hide_reward_status','entry_checkout_compact_status','entry_checkout_trust_text','entry_product_limit','entry_product_description_length','entry_image_category','entry_image_thumb','entry_image_popup','entry_image_product','entry_image_additional','entry_image_related','entry_image_compare','entry_image_wishlist','entry_image_cart','entry_image_location',
            'entry_primary_color','entry_accent_color','entry_dark_theme_status','entry_dark_theme_default','entry_dark_theme_show_toggle','entry_dark_theme_background','entry_dark_theme_surface','entry_dark_theme_card','entry_dark_theme_text','entry_dark_theme_muted','entry_dark_theme_border','help_dark_theme','entry_success_color','entry_danger_color','entry_background_color','entry_page_side_background_color','entry_page_side_background_image','entry_text_color','entry_container_width','entry_element_radius','entry_card_radius','entry_button_radius','entry_card_shadow','entry_show_top_bar','entry_sticky_header','entry_compact_header','entry_header_notice','entry_card_show_description','entry_card_show_rating','entry_card_show_stock','entry_card_show_quick_actions','entry_card_image_ratio','entry_card_style','entry_card_title_lines','entry_card_full_click','entry_card_show_wishlist','entry_card_show_compare','entry_card_show_model','help_card_show_model','entry_card_show_manufacturer','help_card_show_manufacturer','entry_card_meta_position','help_card_meta_position','text_under_title','text_under_price','entry_card_show_quantity','entry_card_show_buy_button','entry_card_stock_mode','entry_category_columns_desktop','entry_category_columns_tablet','entry_category_columns_mobile','entry_category_description_position','entry_category_sticky_toolbar','entry_enable_quick_view','help_enable_quick_view','entry_enable_quick_order','entry_enable_stickers','entry_enable_stock_indicator','entry_enable_live_price','entry_sticker_sale_color','entry_sticker_new_color','entry_sticker_hit_color','entry_quick_order_email','entry_quick_order_required_email','entry_quick_order_success_text','entry_quick_order_privacy_text','entry_enable_live_search','entry_live_search_limit','entry_live_search_min_chars','entry_enable_callback','entry_callback_required_email','entry_callback_success_text','entry_enable_subscribe','entry_subscribe_success_text','entry_popup_notification_status','entry_popup_notification_delay','entry_popup_notification_text','entry_seo_schema_status','entry_seo_opengraph_status','entry_seo_index_guard_status','entry_seo_noindex_sort','entry_seo_noindex_limit','entry_seo_noindex_filter','entry_seo_noindex_page','entry_seo_clean_canonical','entry_merchant_schema_status','entry_product_review_schema_status','entry_website_search_schema_status','entry_shipping_schema_status','entry_shipping_country','entry_shipping_price','entry_shipping_days_min','entry_shipping_days_max','entry_return_schema_status','entry_return_country','entry_return_days','entry_seo_organization_name','entry_seo_organization_logo','entry_seo_organization_phone','entry_seo_organization_email','entry_seo_footer_text_status','entry_seo_footer_text','entry_blog_status','entry_blog_title','entry_blog_description','entry_blog_limit','entry_blog_canonical_status','entry_blog_sitemap_status','entry_blog_schema_status','entry_blog_opengraph_image_status','entry_blog_sitemap_url','entry_blog_keyword','entry_langdir_status','entry_langdir_prefix','entry_hreflang_status','entry_hreflang_xdefault_status','entry_blog_sitemap_hreflang_status','entry_reviews_block_status','entry_reviews_limit','entry_reviews_plus_minus_status','entry_reviews_photos_status','entry_reviews_photos_limit','entry_reviews_photo_max_size','entry_reviews_admin_reply_status','entry_reviews_votes_status','entry_reviews_real_buyer_status','entry_home_builder_status','entry_home_slider_status','entry_home_slider_autoplay','entry_home_slider_height','entry_mobile_hide_home_slider_status','entry_mobile_hide_home_banners_status','entry_mobile_hide_native_slideshow_status','entry_mobile_hide_native_banners_status','entry_mobile_hide_banner_breakpoint','entry_native_banner_fit_mode','help_mobile_hide_home_slider','help_mobile_hide_home_banners','help_mobile_hide_native_slideshow','help_mobile_hide_native_banners','help_mobile_hide_banner_breakpoint','help_native_banner_fit_mode','text_fit_width','text_fit_height','text_fit_cover','entry_home_category_wall_status','entry_home_category_wall_limit','entry_home_category_wall_category_ids','entry_home_category_wall_style','entry_home_category_wall_display_mode','entry_home_category_wall_columns_desktop','entry_home_category_wall_columns_tablet','entry_home_category_wall_columns_mobile','entry_home_category_wall_image_width','entry_home_category_wall_image_height','entry_home_category_wall_show_image','entry_home_category_wall_show_description','entry_home_category_wall_show_count','help_home_category_wall_category_ids','help_home_category_wall_display_mode','entry_home_product_sections_status','entry_home_product_section_limit','entry_home_latest_status','entry_home_special_status','entry_home_bestseller_status','entry_home_manufacturer_status','entry_home_manufacturer_limit','entry_home_benefits_status','text_custom_slideshow','entry_custom_slideshow_status','entry_custom_slideshow_hide_native','entry_custom_slideshow_show_text','entry_custom_slideshow_autoplay_delay','entry_custom_slideshow_height_desktop','entry_custom_slideshow_height_tablet','entry_custom_slideshow_height_mobile','entry_custom_slideshow_fit_mode','entry_custom_slideshow_image_source','text_image_source_direct','text_image_source_cache','help_custom_slideshow_status','help_custom_slideshow_hide_native','help_custom_slideshow_image_source','entry_home_slide_title','entry_home_slide_subtitle','entry_home_slide_button','entry_home_slide_link','entry_home_slide_image','entry_home_banner_title','entry_home_banner_text','entry_home_banner_link','entry_home_banner_image','entry_home_banner_icon','text_banner_icon_quick','help_banner_icon_quick','entry_home_benefits_text','help_home_benefits_icons','help_product_category_related','entry_category_layout','entry_category_show_subcategories','entry_ajax_catalog_status','entry_product_sticky_buy','entry_product_quantity_switch','entry_product_gallery_mode','entry_product_gallery_ratio','entry_product_show_actions','entry_product_show_benefits','entry_product_show_delivery_block','entry_product_delivery_text','entry_product_return_text','entry_product_benefits','entry_mobile_bottom_bar','entry_product_modules_status','entry_product_module_layout','entry_product_module_limit','entry_product_module_home_status','entry_product_module_category_status','entry_product_module_product_status','entry_product_module_latest_status','entry_product_module_special_status','entry_product_module_bestseller_status','entry_product_module_viewed_status','entry_product_module_related_status','entry_product_module_auto_related_status','entry_product_module_category_related_status','entry_performance_lazy_images','entry_performance_eager_first_images','entry_performance_fetchpriority_status','entry_performance_image_decoding_status','entry_enable_custom_css','entry_custom_css','entry_custom_css_global','entry_custom_css_header','entry_custom_css_menu','entry_custom_css_product_card','entry_custom_css_category','entry_custom_css_product','entry_custom_css_mobile','entry_custom_css_checkout','entry_enable_custom_js','entry_custom_js','entry_custom_js_head','entry_custom_js_footer','entry_custom_js_after_load','entry_enable_custom_html','entry_custom_html_head','entry_custom_html_after_body','entry_custom_html_header_top','entry_custom_html_before_footer','entry_custom_html_footer_bottom',
            'help_status','entry_information_mode','help_information_mode','help_uninstall_keep_data','help_product_limit','help_product_description_length','help_color','help_page_side_background_image','help_header_notice','help_card_actions','help_mobile_bottom_bar','help_custom_css','help_custom_js','help_performance','help_seo_index_guard','button_save','button_cancel','button_export_settings','button_import_settings','button_apply_preset','button_blog_manager','button_review_manager','error_permission','error_import_file','error_import_size','error_import_json','error_import_empty','text_import_success','text_preset_success','help_export_settings','help_import_settings','help_preset_wizard','help_checkout_style_pack','help_simple_checkout','help_direct_checkout_after_add','help_checkout_digital_mode','help_checkout_digital_instruction','help_checkout_hide_totals','text_admin_overview','text_admin_overview_help','text_status_theme','text_status_selected','text_status_information_mode','text_status_diagnostics','text_diag_warning_short','text_diag_bad_short','text_theme_not_selected_help','text_theme_disabled_help','text_open_storefront','text_no_unsaved_changes','text_unsaved_changes','text_current_section','text_diag_filter_all','text_diag_filter_good','text_diag_filter_warning','text_diag_filter_bad'
        );
    }

    private function defaults() {
        return array(
            'theme_codecart_pro_status' => 0,
            'theme_codecart_pro_information_mode' => 1,
            'theme_codecart_pro_uninstall_keep_data' => 1,
            'theme_codecart_pro_checkout_style_status' => 1,
            'theme_codecart_pro_simple_checkout_status' => 0,
            'theme_codecart_pro_direct_checkout_after_add_status' => 0,
            'theme_codecart_pro_checkout_digital_mode' => 0,
            'theme_codecart_pro_checkout_digital_instruction_status' => 1,
            'theme_codecart_pro_checkout_digital_instruction_text' => array(1 => 'After payment confirmation, the module archive is available for immediate download, and the license key is sent separately by email or SMS.', 2 => 'После подтверждения оплаты архив модуля доступен для скачивания сразу, а лицензионный ключ отправляется отдельно на e-mail или SMS.', 3 => 'Після підтвердження оплати архів модуля доступний для завантаження одразу, а ліцензійний ключ надсилається окремо на e-mail або SMS.'),
            'theme_codecart_pro_checkout_hide_coupon_status' => 1,
            'theme_codecart_pro_checkout_hide_voucher_status' => 1,
            'theme_codecart_pro_checkout_hide_reward_status' => 1,
            'theme_codecart_pro_checkout_compact_status' => 1,
            'theme_codecart_pro_checkout_trust_text' => array(1 => 'Secure checkout|Clear delivery options|Manager confirmation if needed', 2 => 'Безопасное оформление|Понятные способы доставки|Подтверждение менеджером при необходимости', 3 => 'Безпечне оформлення|Зрозумілі способи доставки|Підтвердження менеджером за потреби'),
            'theme_codecart_pro_directory' => 'codecart_pro',
            'theme_codecart_pro_product_limit' => 12,
            'theme_codecart_pro_product_description_length' => 160,
            'theme_codecart_pro_image_category_width' => 90,
            'theme_codecart_pro_image_category_height' => 90,
            'theme_codecart_pro_image_thumb_width' => 650,
            'theme_codecart_pro_image_thumb_height' => 414,
            'theme_codecart_pro_image_popup_width' => 1100,
            'theme_codecart_pro_image_popup_height' => 700,
            'theme_codecart_pro_image_product_width' => 640,
            'theme_codecart_pro_image_product_height' => 407,
            'theme_codecart_pro_image_additional_width' => 110,
            'theme_codecart_pro_image_additional_height' => 70,
            'theme_codecart_pro_image_related_width' => 320,
            'theme_codecart_pro_image_related_height' => 204,
            'theme_codecart_pro_image_compare_width' => 90,
            'theme_codecart_pro_image_compare_height' => 90,
            'theme_codecart_pro_image_wishlist_width' => 70,
            'theme_codecart_pro_image_wishlist_height' => 70,
            'theme_codecart_pro_image_cart_width' => 70,
            'theme_codecart_pro_image_cart_height' => 70,
            'theme_codecart_pro_image_location_width' => 270,
            'theme_codecart_pro_image_location_height' => 190,
            'theme_codecart_pro_primary_color' => '#0057b7',
            'theme_codecart_pro_accent_color' => '#ffd700',
            'theme_codecart_pro_success_color' => '#16a34a',
            'theme_codecart_pro_danger_color' => '#dc2626',
            'theme_codecart_pro_background_color' => '#f5f7fb',
            'theme_codecart_pro_page_side_background_color' => '#f5f7fb',
            'theme_codecart_pro_page_side_background_image' => '',
            'theme_codecart_pro_dark_theme_status' => 0,
            'theme_codecart_pro_dark_theme_default' => 0,
            'theme_codecart_pro_dark_theme_show_toggle' => 1,
            'theme_codecart_pro_dark_theme_background' => '#0f172a',
            'theme_codecart_pro_dark_theme_surface' => '#111827',
            'theme_codecart_pro_dark_theme_card' => '#1e293b',
            'theme_codecart_pro_dark_theme_text' => '#f8fafc',
            'theme_codecart_pro_dark_theme_muted' => '#cbd5e1',
            'theme_codecart_pro_dark_theme_border' => '#334155',
            'theme_codecart_pro_text_color' => '#1f2937',
            'theme_codecart_pro_container_width' => 1500,
            'theme_codecart_pro_element_radius' => 14,
            'theme_codecart_pro_card_radius' => 16,
            'theme_codecart_pro_button_radius' => 12,
            'theme_codecart_pro_card_shadow' => 1,
            'theme_codecart_pro_show_top_bar' => 1,
            'theme_codecart_pro_sticky_header' => 1,
            'theme_codecart_pro_compact_header' => 0,
            'theme_codecart_pro_header_notice' => array(),
            'theme_codecart_pro_card_show_description' => 1,
            'theme_codecart_pro_card_show_rating' => 1,
            'theme_codecart_pro_card_show_stock' => 1,
            'theme_codecart_pro_card_show_quick_actions' => 1,
            'theme_codecart_pro_card_image_ratio' => '1x1',
            'theme_codecart_pro_card_style' => 'commerce',
            'theme_codecart_pro_card_title_lines' => 2,
            'theme_codecart_pro_card_full_click' => 1,
            'theme_codecart_pro_card_show_wishlist' => 1,
            'theme_codecart_pro_card_show_compare' => 1,
            'theme_codecart_pro_card_show_quantity' => 1,
            'theme_codecart_pro_card_show_buy_button' => 1,
            'theme_codecart_pro_card_stock_mode' => 'smart',
            'theme_codecart_pro_card_second_image_status' => 0,
            'theme_codecart_pro_card_sale_percent_status' => 1,
            'theme_codecart_pro_card_hover_actions_mode' => 'smart',
            'theme_codecart_pro_card_show_model' => 1,
            'theme_codecart_pro_card_show_manufacturer' => 1,
            'theme_codecart_pro_card_meta_position' => 'under_title',
            'theme_codecart_pro_category_columns_desktop' => 5,
            'theme_codecart_pro_category_columns_tablet' => 3,
            'theme_codecart_pro_category_columns_mobile' => 2,
            'theme_codecart_pro_category_description_position' => 'top',
            'theme_codecart_pro_category_sticky_toolbar' => 1,
            'theme_codecart_pro_category_description_collapse' => 1,
            'theme_codecart_pro_category_description_height' => 360,
            'theme_codecart_pro_category_hero_width' => 720,
            'theme_codecart_pro_category_hero_height' => 720,
            'theme_codecart_pro_schema_breadcrumbs_status' => 1,
            'theme_codecart_pro_category_itemlist_schema_status' => 1,
            'theme_codecart_pro_ajax_catalog_status' => 1,
            'theme_codecart_pro_seo_index_guard_status' => 1,
            'theme_codecart_pro_seo_noindex_sort' => 1,
            'theme_codecart_pro_seo_noindex_limit' => 1,
            'theme_codecart_pro_seo_noindex_filter' => 1,
            'theme_codecart_pro_seo_noindex_page' => 0,
            'theme_codecart_pro_seo_clean_canonical' => 0,
            'theme_codecart_pro_merchant_schema_status' => 1,
            'theme_codecart_pro_product_review_schema_status' => 1,
            'theme_codecart_pro_website_search_schema_status' => 1,
            'theme_codecart_pro_shipping_schema_status' => 0,
            'theme_codecart_pro_shipping_country' => 'UA',
            'theme_codecart_pro_shipping_price' => '0.00',
            'theme_codecart_pro_shipping_days_min' => 1,
            'theme_codecart_pro_shipping_days_max' => 5,
            'theme_codecart_pro_return_schema_status' => 0,
            'theme_codecart_pro_return_country' => 'UA',
            'theme_codecart_pro_return_days' => 14,
            'theme_codecart_pro_cart_drawer_status' => 1,
            'theme_codecart_pro_cart_toast_status' => 1,
            'theme_codecart_pro_cart_drawer_position' => 'right',
            'theme_codecart_pro_notification_position' => 'top-right',
            'theme_codecart_pro_category_layout' => 'grid',
            'theme_codecart_pro_category_show_subcategories' => 1,
            'theme_codecart_pro_product_sticky_buy' => 1,
            'theme_codecart_pro_product_quantity_switch' => 1,
            'theme_codecart_pro_product_bottom_sticky_buy' => 1,
            'theme_codecart_pro_product_bottom_sticky_buy_desktop' => 1,
            'theme_codecart_pro_product_bottom_sticky_buy_mobile' => 1,
            'theme_codecart_pro_product_gallery_mode' => 'clean',
            'theme_codecart_pro_product_gallery_ratio' => '4x3',
            'theme_codecart_pro_product_show_attributes' => 1,
            'theme_codecart_pro_product_attributes_style' => 'table',
            'theme_codecart_pro_product_sale_timer' => 1,
            'theme_codecart_pro_product_faq_status' => 1,
            'theme_codecart_pro_product_faq_schema_status' => 1,
            'theme_codecart_pro_product_trust_panel_status' => 1,
            'theme_codecart_pro_product_trust_badges' => array(1 => 'Product information|Payment options|Delivery information|Customer support', 2 => 'Информация о товаре|Способы оплаты|Информация о доставке|Поддержка покупателей', 3 => 'Інформація про товар|Способи оплати|Інформація про доставку|Підтримка покупців'),
            'theme_codecart_pro_product_faq' => array(1 => '', 2 => '', 3 => ''),
            'theme_codecart_pro_product_show_actions' => 1,
            'theme_codecart_pro_product_show_benefits' => 1,
            'theme_codecart_pro_product_show_delivery_block' => 1,
            'theme_codecart_pro_product_delivery_text' => array(1 => 'Delivery methods and costs are shown at checkout. See the delivery page for details.', 2 => 'Способы и стоимость доставки указаны при оформлении заказа. Подробности доступны на странице доставки.', 3 => 'Способи та вартість доставки вказані під час оформлення замовлення. Подробиці доступні на сторінці доставки.'),
            'theme_codecart_pro_product_return_text' => array(1 => 'See the store policies for warranty and return terms.', 2 => 'Условия гарантии и возврата указаны в правилах магазина.', 3 => 'Умови гарантії та повернення вказані в правилах магазину.'),
            'theme_codecart_pro_product_benefits' => array(),
            'theme_codecart_pro_mobile_bottom_bar' => 1,
            'theme_codecart_pro_product_modules_status' => 1,
            'theme_codecart_pro_product_module_layout' => 'carousel',
            'theme_codecart_pro_product_module_limit' => 8,
            'theme_codecart_pro_product_module_home_status' => 0,
            'theme_codecart_pro_product_module_category_status' => 1,
            'theme_codecart_pro_product_module_product_status' => 1,
            'theme_codecart_pro_product_module_latest_status' => 1,
            'theme_codecart_pro_product_module_special_status' => 1,
            'theme_codecart_pro_product_module_bestseller_status' => 1,
            'theme_codecart_pro_product_module_viewed_status' => 1,
            'theme_codecart_pro_product_module_related_status' => 0,
            'theme_codecart_pro_product_module_auto_related_status' => 1,
            'theme_codecart_pro_product_module_category_related_status' => 1,
            'theme_codecart_pro_home_builder_status' => 1,
            'theme_codecart_pro_home_slider_status' => 1,
            'theme_codecart_pro_home_slider_autoplay' => 1,
            'theme_codecart_pro_home_slider_height' => 460,
            'theme_codecart_pro_custom_slideshow_status' => 1,
            'theme_codecart_pro_custom_slideshow_hide_native' => 1,
            'theme_codecart_pro_custom_slideshow_show_text' => 0,
            'theme_codecart_pro_custom_slideshow_autoplay_delay' => 4000,
            'theme_codecart_pro_custom_slideshow_height_desktop' => 380,
            'theme_codecart_pro_custom_slideshow_height_tablet' => 300,
            'theme_codecart_pro_custom_slideshow_height_mobile' => 190,
            'theme_codecart_pro_custom_slideshow_fit_mode' => 'height',
            'theme_codecart_pro_custom_slideshow_image_source' => 'direct',
            'theme_codecart_pro_home_slide1_title' => array(1 => 'CodeCart PRO Theme', 2 => 'CodeCart PRO Theme', 3 => 'CodeCart PRO Theme'),
            'theme_codecart_pro_home_slide1_subtitle' => array(1 => 'Modern fast theme for OpenCart', 2 => 'Современная быстрая тема для OpenCart', 3 => 'Сучасна швидка тема для OpenCart'),
            'theme_codecart_pro_home_slide1_button' => array(1 => 'Shop now', 2 => 'Перейти в каталог', 3 => 'Перейти в каталог'),
            'theme_codecart_pro_home_slide1_link' => 'index.php?route=product/category',
            'theme_codecart_pro_home_slide1_image' => '',
            'theme_codecart_pro_home_slide2_title' => array(),
            'theme_codecart_pro_home_slide2_subtitle' => array(),
            'theme_codecart_pro_home_slide2_button' => array(),
            'theme_codecart_pro_home_slide2_link' => '',
            'theme_codecart_pro_home_slide2_image' => '',
            'theme_codecart_pro_home_slide3_title' => array(),
            'theme_codecart_pro_home_slide3_subtitle' => array(),
            'theme_codecart_pro_home_slide3_button' => array(),
            'theme_codecart_pro_home_slide3_link' => '',
            'theme_codecart_pro_home_slide3_image' => '',
            'theme_codecart_pro_home_slide4_title' => array(),
            'theme_codecart_pro_home_slide4_subtitle' => array(),
            'theme_codecart_pro_home_slide4_button' => array(),
            'theme_codecart_pro_home_slide4_link' => '',
            'theme_codecart_pro_home_slide4_image' => '',
            'theme_codecart_pro_home_slide5_title' => array(),
            'theme_codecart_pro_home_slide5_subtitle' => array(),
            'theme_codecart_pro_home_slide5_button' => array(),
            'theme_codecart_pro_home_slide5_link' => '',
            'theme_codecart_pro_home_slide5_image' => '',
            'theme_codecart_pro_home_banners_status' => 0,
            'theme_codecart_pro_mobile_hide_home_slider_status' => 0,
            'theme_codecart_pro_mobile_hide_home_banners_status' => 0,
            'theme_codecart_pro_mobile_hide_native_slideshow_status' => 1,
            'theme_codecart_pro_mobile_hide_native_banners_status' => 1,
            'theme_codecart_pro_mobile_hide_banner_breakpoint' => 767,
            'theme_codecart_pro_native_banner_fit_mode' => 'height',
            'theme_codecart_pro_home_banner1_title' => array(1 => 'Instant access', 2 => 'Мгновенное получение', 3 => 'Миттєве отримання'),
            'theme_codecart_pro_home_banner1_text' => array(1 => 'Module files are delivered digitally after payment', 2 => 'Файлы модуля передаются в электронном виде после оплаты', 3 => 'Файли модуля передаються в електронному вигляді після оплати'),
            'theme_codecart_pro_home_banner1_link' => '',
            'theme_codecart_pro_home_banner1_image' => '',
            'theme_codecart_pro_home_banner1_icon' => 'fa-download',
            'theme_codecart_pro_home_banner2_title' => array(1 => 'OpenCart 3.x', 2 => 'OpenCart 3.x', 3 => 'OpenCart 3.x'),
            'theme_codecart_pro_home_banner2_text' => array(1 => 'Compatibility is specified on each product page', 2 => 'Совместимость указана в карточке каждого модуля', 3 => 'Сумісність вказана в картці кожного модуля'),
            'theme_codecart_pro_home_banner2_link' => '',
            'theme_codecart_pro_home_banner2_image' => '',
            'theme_codecart_pro_home_banner2_icon' => 'fa-code',
            'theme_codecart_pro_home_banner3_title' => array(1 => 'Support', 2 => 'Поддержка', 3 => 'Підтримка'),
            'theme_codecart_pro_home_banner3_text' => array(1 => 'Help with installation and setup', 2 => 'Помощь с установкой и настройкой', 3 => 'Допомога з установкою та налаштуванням'),
            'theme_codecart_pro_home_banner3_link' => '',
            'theme_codecart_pro_home_banner3_image' => '',
            'theme_codecart_pro_home_banner3_icon' => 'fa-life-ring',
            'theme_codecart_pro_home_banner4_title' => array(),
            'theme_codecart_pro_home_banner4_text' => array(),
            'theme_codecart_pro_home_banner4_link' => '',
            'theme_codecart_pro_home_banner4_image' => '',
            'theme_codecart_pro_home_banner4_icon' => 'fa-credit-card',
            'theme_codecart_pro_home_category_wall_status' => 1,
            'theme_codecart_pro_home_category_wall_limit' => 0,
            'theme_codecart_pro_home_category_wall_category_ids' => '',
            'theme_codecart_pro_home_category_wall_style' => 'cards',
            'theme_codecart_pro_home_category_wall_display_mode' => 'grid',
            'theme_codecart_pro_home_category_wall_columns_desktop' => 5,
            'theme_codecart_pro_home_category_wall_columns_tablet' => 3,
            'theme_codecart_pro_home_category_wall_columns_mobile' => 2,
            'theme_codecart_pro_home_category_wall_image_width' => 420,
            'theme_codecart_pro_home_category_wall_image_height' => 260,
            'theme_codecart_pro_home_category_wall_show_image' => 1,
            'theme_codecart_pro_home_category_wall_show_description' => 0,
            'theme_codecart_pro_home_category_wall_show_count' => 0,
            'theme_codecart_pro_home_product_sections_status' => 1,
            'theme_codecart_pro_home_product_section_limit' => 8,
            'theme_codecart_pro_home_latest_status' => 1,
            'theme_codecart_pro_home_special_status' => 1,
            'theme_codecart_pro_home_bestseller_status' => 1,
            'theme_codecart_pro_home_manufacturer_status' => 0,
            'theme_codecart_pro_home_manufacturer_limit' => 12,
            'theme_codecart_pro_home_benefits_status' => 1,
            'theme_codecart_pro_home_benefits_text' => array(1 => 'fa-shopping-bag|Product selection
fa-credit-card|Payment options
fa-truck|Delivery information
fa-life-ring|Customer support', 2 => 'fa-shopping-bag|Выбор товаров
fa-credit-card|Способы оплаты
fa-truck|Информация о доставке
fa-life-ring|Поддержка покупателей', 3 => 'fa-shopping-bag|Вибір товарів
fa-credit-card|Способи оплати
fa-truck|Інформація про доставку
fa-life-ring|Підтримка покупців'),
            'theme_codecart_pro_performance_lazy_images' => 1,
            'theme_codecart_pro_performance_eager_first_images' => 2,
            'theme_codecart_pro_performance_fetchpriority_status' => 1,
            'theme_codecart_pro_performance_image_decoding_status' => 1,
            
            'theme_codecart_pro_header_layout' => 'classic',
            'theme_codecart_pro_header_show_phone' => 1,
            'theme_codecart_pro_header_show_email' => 1,
            'theme_codecart_pro_header_show_worktime' => 1,
            'theme_codecart_pro_header_phone' => '',
            'theme_codecart_pro_header_email' => '',
            'theme_codecart_pro_header_worktime' => array(),
            'theme_codecart_pro_menu_style' => 'horizontal',
            'theme_codecart_pro_menu_width' => 'short',
            'theme_codecart_pro_menu_show_home' => 1,
            'theme_codecart_pro_menu_show_categories' => 1,
            'theme_codecart_pro_menu_sticky' => 0,

            'theme_codecart_pro_mega_menu_status' => 1,
            'theme_codecart_pro_mega_menu_source' => 'codecart',
            'theme_codecart_pro_mega_menu_only_top' => 1,
            'theme_codecart_pro_mega_menu_depth' => 3,
            'theme_codecart_pro_mega_menu_columns' => 4,
            'theme_codecart_pro_mega_menu_show_images' => 0,
            'theme_codecart_pro_mega_menu_show_counts' => 0,
            'theme_codecart_pro_mega_menu_promo_status' => 1,
            'theme_codecart_pro_mega_menu_promo_title' => array(1 => 'Expert selection', 2 => 'Экспертный подбор', 3 => 'Експертний підбір'),
            'theme_codecart_pro_mega_menu_promo_text' => array(1 => 'Choose products faster with clear categories and useful links.', 2 => 'Быстрее выбирайте товары через понятные категории и полезные ссылки.', 3 => 'Швидше вибирайте товари через зрозумілі категорії та корисні посилання.'),
            'theme_codecart_pro_mega_menu_promo_link' => '',
            'theme_codecart_pro_mobile_menu_show_search' => 1,
            'theme_codecart_pro_mobile_menu_show_categories' => 1,
            'theme_codecart_pro_mobile_menu_show_blog' => 1,
            'theme_codecart_pro_mobile_menu_show_contacts' => 1,
            'theme_codecart_pro_mobile_menu_show_account' => 1,
            'theme_codecart_pro_header_search_mode' => 'wide',
            'theme_codecart_pro_floating_cart_status' => 1,
            'theme_codecart_pro_floating_wishlist_status' => 0,
            'theme_codecart_pro_floating_compare_status' => 0,
            'theme_codecart_pro_menu_link1_title' => array(),
            'theme_codecart_pro_menu_link1_url' => '',
            'theme_codecart_pro_menu_link1_icon' => '',
            'theme_codecart_pro_menu_link2_title' => array(),
            'theme_codecart_pro_menu_link2_url' => '',
            'theme_codecart_pro_menu_link2_icon' => '',
            'theme_codecart_pro_menu_link3_title' => array(),
            'theme_codecart_pro_menu_link3_url' => '',
            'theme_codecart_pro_menu_link3_icon' => '',
            'theme_codecart_pro_menu_link4_title' => array(),
            'theme_codecart_pro_menu_link4_url' => '',
            'theme_codecart_pro_menu_link4_icon' => '',
            'theme_codecart_pro_menu_link5_title' => array(),
            'theme_codecart_pro_menu_link5_url' => '',
            'theme_codecart_pro_menu_link5_icon' => '',
            'theme_codecart_pro_menu_link6_title' => array(),
            'theme_codecart_pro_menu_link6_url' => '',
            'theme_codecart_pro_menu_link6_icon' => '',
            'theme_codecart_pro_footer_show_contacts' => 1,
            'theme_codecart_pro_footer_text' => array(),
            'theme_codecart_pro_footer_signature' => array(1 => '© ' . date('Y') . ' ' . $this->config->get('config_name') . '. All rights reserved.', 2 => '© ' . date('Y') . ' ' . $this->config->get('config_name') . '. Все права защищены.', 3 => '© ' . date('Y') . ' ' . $this->config->get('config_name') . '. Усі права захищені.'),
            'theme_codecart_pro_footer_payment_icons' => '',
            'theme_codecart_pro_floating_contacts' => 1,
            'theme_codecart_pro_floating_phone' => '',
            'theme_codecart_pro_floating_telegram' => '',
            'theme_codecart_pro_floating_viber' => '',
            'theme_codecart_pro_floating_whatsapp' => '',
            'theme_codecart_pro_floating_position' => 'right',
            'theme_codecart_pro_price_color' => '#0057b7',
            'theme_codecart_pro_old_price_color' => '#9ca3af',
            'theme_codecart_pro_menu_bg_color' => '#0057b7',
            'theme_codecart_pro_menu_text_color' => '#ffffff',
            'theme_codecart_pro_footer_bg_color' => '#0f172a',
            'theme_codecart_pro_footer_text_color' => '#cbd5e1',
            'theme_codecart_pro_enable_quick_view' => 0,
            'theme_codecart_pro_enable_quick_order' => 1,
            'theme_codecart_pro_enable_stickers' => 1,
            'theme_codecart_pro_enable_stock_indicator' => 1,
            'theme_codecart_pro_enable_live_price' => 1,
            'theme_codecart_pro_sticker_sale_color' => '#dc2626',
            'theme_codecart_pro_sticker_new_color' => '#16a34a',
            'theme_codecart_pro_sticker_hit_color' => '#f59e0b',
            'theme_codecart_pro_quick_order_email' => '',
            'theme_codecart_pro_quick_order_required_email' => 0,
            'theme_codecart_pro_quick_order_success_text' => array(),
            'theme_codecart_pro_quick_order_privacy_text' => array(),
            'theme_codecart_pro_enable_live_search' => 1,
            'theme_codecart_pro_live_search_limit' => 6,
            'theme_codecart_pro_live_search_min_chars' => 2,
            'theme_codecart_pro_enable_callback' => 1,
            'theme_codecart_pro_callback_required_email' => 0,
            'theme_codecart_pro_callback_success_text' => array(1 => 'Thank you. We will call you back.', 2 => 'Спасибо. Мы перезвоним вам.', 3 => 'Дякуємо. Ми передзвонимо вам.'),
            'theme_codecart_pro_enable_subscribe' => 1,
            'theme_codecart_pro_subscribe_success_text' => array(1 => 'Subscription saved.', 2 => 'Подписка сохранена.', 3 => 'Підписку збережено.'),
            'theme_codecart_pro_popup_notification_status' => 0,
            'theme_codecart_pro_popup_notification_delay' => 2500,
            'theme_codecart_pro_popup_notification_text' => array(),

            'theme_codecart_pro_seo_schema_status' => 1,
            'theme_codecart_pro_seo_opengraph_status' => 1,
            'theme_codecart_pro_seo_organization_name' => '',
            'theme_codecart_pro_seo_organization_logo' => '',
            'theme_codecart_pro_seo_organization_phone' => '',
            'theme_codecart_pro_seo_organization_email' => '',
            'theme_codecart_pro_seo_footer_text_status' => 1,
            'theme_codecart_pro_seo_footer_text' => array(),
            'theme_codecart_pro_blog_status' => 1,
            'theme_codecart_pro_blog_title' => array(1 => 'Blog', 2 => 'Блог', 3 => 'Блог'),
            'theme_codecart_pro_blog_description' => array(1 => 'Useful articles and buying guides', 2 => 'Полезные статьи и рекомендации по выбору', 3 => 'Корисні статті та рекомендації щодо вибору'),
            'theme_codecart_pro_blog_keyword' => array(1 => 'blog', 2 => 'blog', 3 => 'blog'),
            'theme_codecart_pro_langdir_status' => 1,
            'theme_codecart_pro_langdir_prefix' => array(1 => 'en', 2 => 'ru', 3 => 'ua'),
            'theme_codecart_pro_hreflang_status' => 1,
            'theme_codecart_pro_hreflang_xdefault_status' => 1,
            'theme_codecart_pro_blog_sitemap_hreflang_status' => 1,
            'theme_codecart_pro_blog_limit' => 12,
            'theme_codecart_pro_blog_canonical_status' => 1,
            'theme_codecart_pro_blog_sitemap_status' => 1,
            'theme_codecart_pro_blog_schema_status' => 1,
            'theme_codecart_pro_blog_opengraph_image_status' => 1,
            'theme_codecart_pro_reviews_block_status' => 1,
            'theme_codecart_pro_reviews_limit' => 8,
            'theme_codecart_pro_reviews_plus_minus_status' => 1,
            'theme_codecart_pro_reviews_photos_status' => 1,
            'theme_codecart_pro_reviews_photos_limit' => 5,
            'theme_codecart_pro_reviews_photo_max_size' => 2,
            'theme_codecart_pro_reviews_admin_reply_status' => 1,
            'theme_codecart_pro_reviews_votes_status' => 1,
            'theme_codecart_pro_reviews_real_buyer_status' => 1,
            'theme_codecart_pro_enable_custom_css' => 0,
            'theme_codecart_pro_custom_css' => '',
            'theme_codecart_pro_custom_css_global' => '',
            'theme_codecart_pro_custom_css_header' => '',
            'theme_codecart_pro_custom_css_menu' => '',
            'theme_codecart_pro_custom_css_product_card' => '',
            'theme_codecart_pro_custom_css_category' => '',
            'theme_codecart_pro_custom_css_product' => '',
            'theme_codecart_pro_custom_css_mobile' => '',
            'theme_codecart_pro_custom_css_checkout' => '',
            'theme_codecart_pro_enable_custom_js' => 0,
            'theme_codecart_pro_custom_js' => '',
            'theme_codecart_pro_custom_js_head' => '',
            'theme_codecart_pro_custom_js_footer' => '',
            'theme_codecart_pro_custom_js_after_load' => '',
            'theme_codecart_pro_enable_custom_html' => 0,
            'theme_codecart_pro_custom_html_head' => '',
            'theme_codecart_pro_custom_html_after_body' => '',
            'theme_codecart_pro_custom_html_header_top' => '',
            'theme_codecart_pro_custom_html_before_footer' => '',
            'theme_codecart_pro_custom_html_footer_bottom' => '',
            'theme_codecart_pro_schema_version' => self::SCHEMA_VERSION,
            'theme_codecart_pro_blog_schema_version' => self::SCHEMA_VERSION
        );
    }

    private function sanitize($post) {
        $defaults = $this->defaults();
        $out = array();
        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $post)) {
                $out[$key] = is_int($default) ? 0 : $default;
                continue;
            }
            $value = $post[$key];
            if (is_int($default)) {
                $out[$key] = max(0, (int)$value);
            } elseif (is_array($default)) {
                $out[$key] = is_array($value) ? $value : array();
            } else {
                $out[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        $out['theme_codecart_pro_directory'] = 'codecart_pro';
        // An OFF save must not claim that a deferred database migration ran.
        foreach (array('theme_codecart_pro_schema_version', 'theme_codecart_pro_blog_schema_version') as $schema_key) {
            $stored_version = (int)$this->config->get($schema_key);
            $out[$schema_key] = !empty($out['theme_codecart_pro_status']) ? max(self::SCHEMA_VERSION, $stored_version) : $stored_version;
        }
        $color_keys = array(
            'theme_codecart_pro_primary_color',
            'theme_codecart_pro_accent_color',
            'theme_codecart_pro_success_color',
            'theme_codecart_pro_danger_color',
            'theme_codecart_pro_background_color',
            'theme_codecart_pro_page_side_background_color',
            'theme_codecart_pro_dark_theme_background',
            'theme_codecart_pro_dark_theme_surface',
            'theme_codecart_pro_dark_theme_card',
            'theme_codecart_pro_dark_theme_text',
            'theme_codecart_pro_dark_theme_muted',
            'theme_codecart_pro_dark_theme_border',
            'theme_codecart_pro_text_color',
            'theme_codecart_pro_price_color',
            'theme_codecart_pro_old_price_color',
            'theme_codecart_pro_menu_bg_color',
            'theme_codecart_pro_menu_text_color',
            'theme_codecart_pro_footer_bg_color',
            'theme_codecart_pro_footer_text_color',
            'theme_codecart_pro_sticker_sale_color',
            'theme_codecart_pro_sticker_new_color',
            'theme_codecart_pro_sticker_hit_color'
        );

        foreach ($color_keys as $key) {
            $value = isset($out[$key]) ? (string)$out[$key] : '';
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $out[$key] = isset($defaults[$key]) ? $defaults[$key] : '#000000';
            }
        }
        $out['theme_codecart_pro_container_width'] = min(1680, max(1400, (int)$out['theme_codecart_pro_container_width']));
        $out['theme_codecart_pro_element_radius'] = min(40, max(0, (int)$out['theme_codecart_pro_element_radius']));
        $out['theme_codecart_pro_card_radius'] = min(40, max(0, (int)$out['theme_codecart_pro_card_radius']));
        $out['theme_codecart_pro_button_radius'] = min(40, max(0, (int)$out['theme_codecart_pro_button_radius']));
        $out['theme_codecart_pro_page_side_background_image'] = $this->sanitizeImagePath($out['theme_codecart_pro_page_side_background_image']);
        $out['theme_codecart_pro_seo_organization_logo'] = $this->sanitizeImagePath($out['theme_codecart_pro_seo_organization_logo']);
        $out['theme_codecart_pro_product_limit'] = min(100, max(1, (int)$out['theme_codecart_pro_product_limit']));
        $out['theme_codecart_pro_product_description_length'] = min(500, max(0, (int)$out['theme_codecart_pro_product_description_length']));
        $out['theme_codecart_pro_card_title_lines'] = min(4, max(1, (int)$out['theme_codecart_pro_card_title_lines']));
        $out['theme_codecart_pro_category_columns_desktop'] = min(6, max(2, (int)$out['theme_codecart_pro_category_columns_desktop']));
        $out['theme_codecart_pro_category_columns_tablet'] = min(4, max(2, (int)$out['theme_codecart_pro_category_columns_tablet']));
        $out['theme_codecart_pro_category_columns_mobile'] = min(2, max(1, (int)$out['theme_codecart_pro_category_columns_mobile']));
        if (!in_array($out['theme_codecart_pro_card_style'], array('commerce','minimal','compact'), true)) { $out['theme_codecart_pro_card_style'] = 'commerce'; }
        if (!in_array($out['theme_codecart_pro_card_stock_mode'], array('smart','always','hide'), true)) { $out['theme_codecart_pro_card_stock_mode'] = 'smart'; }
        if (!in_array($out['theme_codecart_pro_card_hover_actions_mode'], array('smart','always','hide'), true)) { $out['theme_codecart_pro_card_hover_actions_mode'] = 'smart'; }
        if (!in_array($out['theme_codecart_pro_card_meta_position'], array('under_title','under_price','hide'), true)) { $out['theme_codecart_pro_card_meta_position'] = 'under_title'; }
        if (!in_array($out['theme_codecart_pro_product_attributes_style'], array('table','cards','compact'), true)) { $out['theme_codecart_pro_product_attributes_style'] = 'table'; }
        if (!in_array($out['theme_codecart_pro_category_description_position'], array('top','bottom','both','hide'), true)) { $out['theme_codecart_pro_category_description_position'] = 'top'; }
        if (!in_array($out['theme_codecart_pro_product_gallery_mode'], array('clean','thumbs_left','thumbs_bottom'), true)) { $out['theme_codecart_pro_product_gallery_mode'] = 'clean'; }
        if (!in_array($out['theme_codecart_pro_product_gallery_ratio'], array('1x1','4x3','3x4','16x9'), true)) { $out['theme_codecart_pro_product_gallery_ratio'] = '4x3'; }
        $out['theme_codecart_pro_category_description_height'] = min(500, max(80, (int)$out['theme_codecart_pro_category_description_height']));
        $out['theme_codecart_pro_category_hero_width'] = min(2400, max(480, (int)$out['theme_codecart_pro_category_hero_width']));
        $out['theme_codecart_pro_category_hero_height'] = min(1600, max(300, (int)$out['theme_codecart_pro_category_hero_height']));
        $out['theme_codecart_pro_uninstall_keep_data'] = !empty($out['theme_codecart_pro_uninstall_keep_data']) ? 1 : 0;
        $out['theme_codecart_pro_checkout_style_status'] = !empty($out['theme_codecart_pro_checkout_style_status']) ? 1 : 0;
        $out['theme_codecart_pro_simple_checkout_status'] = !empty($out['theme_codecart_pro_simple_checkout_status']) ? 1 : 0;
        $out['theme_codecart_pro_direct_checkout_after_add_status'] = !empty($out['theme_codecart_pro_direct_checkout_after_add_status']) ? 1 : 0;
        $out['theme_codecart_pro_checkout_digital_mode'] = !empty($out['theme_codecart_pro_checkout_digital_mode']) ? 1 : 0;
        $out['theme_codecart_pro_checkout_hide_coupon_status'] = !empty($out['theme_codecart_pro_checkout_hide_coupon_status']) ? 1 : 0;
        $out['theme_codecart_pro_checkout_hide_voucher_status'] = !empty($out['theme_codecart_pro_checkout_hide_voucher_status']) ? 1 : 0;
        $out['theme_codecart_pro_checkout_hide_reward_status'] = !empty($out['theme_codecart_pro_checkout_hide_reward_status']) ? 1 : 0;
        $out['theme_codecart_pro_checkout_compact_status'] = !empty($out['theme_codecart_pro_checkout_compact_status']) ? 1 : 0;
        $out['theme_codecart_pro_performance_eager_first_images'] = min(6, max(0, (int)$out['theme_codecart_pro_performance_eager_first_images']));
        $out['theme_codecart_pro_shipping_country'] = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', (string)$out['theme_codecart_pro_shipping_country']), 0, 2));
        if ($out['theme_codecart_pro_shipping_country'] === '') { $out['theme_codecart_pro_shipping_country'] = 'UA'; }
        $out['theme_codecart_pro_return_country'] = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', (string)$out['theme_codecart_pro_return_country']), 0, 2));
        if ($out['theme_codecart_pro_return_country'] === '') { $out['theme_codecart_pro_return_country'] = $out['theme_codecart_pro_shipping_country']; }
        $out['theme_codecart_pro_shipping_price'] = number_format(max(0, (float)str_replace(',', '.', (string)$out['theme_codecart_pro_shipping_price'])), 2, '.', '');
        $out['theme_codecart_pro_shipping_days_min'] = min(60, max(0, (int)$out['theme_codecart_pro_shipping_days_min']));
        $out['theme_codecart_pro_shipping_days_max'] = min(120, max($out['theme_codecart_pro_shipping_days_min'], (int)$out['theme_codecart_pro_shipping_days_max']));
        $out['theme_codecart_pro_return_days'] = min(365, max(0, (int)$out['theme_codecart_pro_return_days']));
        if (!in_array($out['theme_codecart_pro_cart_drawer_position'], array('left','right'), true)) { $out['theme_codecart_pro_cart_drawer_position'] = 'right'; }
        if (!in_array($out['theme_codecart_pro_notification_position'], array('top-right','top-left','bottom-right','bottom-left'), true)) { $out['theme_codecart_pro_notification_position'] = 'top-right'; }
        $out['theme_codecart_pro_home_slider_height'] = min(700, max(260, (int)$out['theme_codecart_pro_home_slider_height']));
        $out['theme_codecart_pro_custom_slideshow_status'] = !empty($out['theme_codecart_pro_custom_slideshow_status']) ? 1 : 0;
        $out['theme_codecart_pro_custom_slideshow_hide_native'] = !empty($out['theme_codecart_pro_custom_slideshow_hide_native']) ? 1 : 0;
        $out['theme_codecart_pro_custom_slideshow_show_text'] = !empty($out['theme_codecart_pro_custom_slideshow_show_text']) ? 1 : 0;
        $out['theme_codecart_pro_custom_slideshow_autoplay_delay'] = min(20000, max(1500, (int)$out['theme_codecart_pro_custom_slideshow_autoplay_delay']));
        $out['theme_codecart_pro_custom_slideshow_height_desktop'] = min(700, max(180, (int)$out['theme_codecart_pro_custom_slideshow_height_desktop']));
        $out['theme_codecart_pro_custom_slideshow_height_tablet'] = min(600, max(160, (int)$out['theme_codecart_pro_custom_slideshow_height_tablet']));
        $out['theme_codecart_pro_custom_slideshow_height_mobile'] = min(420, max(120, (int)$out['theme_codecart_pro_custom_slideshow_height_mobile']));
        if (!in_array($out['theme_codecart_pro_custom_slideshow_fit_mode'], array('width','height','cover'), true)) { $out['theme_codecart_pro_custom_slideshow_fit_mode'] = 'height'; }
        if (!in_array($out['theme_codecart_pro_custom_slideshow_image_source'], array('direct','cache'), true)) { $out['theme_codecart_pro_custom_slideshow_image_source'] = 'direct'; }
        if (!in_array($out['theme_codecart_pro_native_banner_fit_mode'], array('width','height','cover'), true)) { $out['theme_codecart_pro_native_banner_fit_mode'] = 'height'; }
        $out['theme_codecart_pro_dark_theme_status'] = !empty($out['theme_codecart_pro_dark_theme_status']) ? 1 : 0;
        $out['theme_codecart_pro_dark_theme_default'] = !empty($out['theme_codecart_pro_dark_theme_default']) ? 1 : 0;
        $out['theme_codecart_pro_dark_theme_show_toggle'] = !empty($out['theme_codecart_pro_dark_theme_show_toggle']) ? 1 : 0;
        $out['theme_codecart_pro_home_category_wall_limit'] = min(100, max(0, (int)$out['theme_codecart_pro_home_category_wall_limit']));
        $out['theme_codecart_pro_home_category_wall_columns_desktop'] = min(6, max(2, (int)$out['theme_codecart_pro_home_category_wall_columns_desktop']));
        $out['theme_codecart_pro_home_category_wall_columns_tablet'] = min(4, max(2, (int)$out['theme_codecart_pro_home_category_wall_columns_tablet']));
        $out['theme_codecart_pro_home_category_wall_columns_mobile'] = min(3, max(1, (int)$out['theme_codecart_pro_home_category_wall_columns_mobile']));
        $out['theme_codecart_pro_home_category_wall_image_width'] = min(1000, max(80, (int)$out['theme_codecart_pro_home_category_wall_image_width']));
        $out['theme_codecart_pro_home_category_wall_image_height'] = min(1000, max(80, (int)$out['theme_codecart_pro_home_category_wall_image_height']));
        $out['theme_codecart_pro_home_category_wall_show_image'] = !empty($out['theme_codecart_pro_home_category_wall_show_image']) ? 1 : 0;
        $out['theme_codecart_pro_home_category_wall_show_description'] = !empty($out['theme_codecart_pro_home_category_wall_show_description']) ? 1 : 0;
        $out['theme_codecart_pro_home_category_wall_show_count'] = !empty($out['theme_codecart_pro_home_category_wall_show_count']) ? 1 : 0;
        if (!in_array($out['theme_codecart_pro_home_category_wall_style'], array('cards','compact','overlay'), true)) { $out['theme_codecart_pro_home_category_wall_style'] = 'cards'; }
        if (!in_array($out['theme_codecart_pro_home_category_wall_display_mode'], array('grid','carousel'), true)) { $out['theme_codecart_pro_home_category_wall_display_mode'] = 'grid'; }
        $out['theme_codecart_pro_home_product_section_limit'] = min(24, max(1, (int)$out['theme_codecart_pro_home_product_section_limit']));
        $out['theme_codecart_pro_home_manufacturer_limit'] = min(40, max(1, (int)$out['theme_codecart_pro_home_manufacturer_limit']));
        $out['theme_codecart_pro_live_search_limit'] = min(20, max(3, (int)$out['theme_codecart_pro_live_search_limit']));
        $out['theme_codecart_pro_live_search_min_chars'] = min(5, max(1, (int)$out['theme_codecart_pro_live_search_min_chars']));
        $out['theme_codecart_pro_popup_notification_delay'] = min(20000, max(0, (int)$out['theme_codecart_pro_popup_notification_delay']));
        $out['theme_codecart_pro_product_module_limit'] = min(24, max(1, (int)$out['theme_codecart_pro_product_module_limit']));
        if (!in_array($out['theme_codecart_pro_product_module_layout'], array('carousel','grid','compact'), true)) { $out['theme_codecart_pro_product_module_layout'] = 'carousel'; }
        $out['theme_codecart_pro_blog_limit'] = min(50, max(1, (int)$out['theme_codecart_pro_blog_limit']));
        $out['theme_codecart_pro_reviews_limit'] = min(50, max(1, (int)$out['theme_codecart_pro_reviews_limit']));
        $out['theme_codecart_pro_reviews_photos_limit'] = min(10, max(0, (int)$out['theme_codecart_pro_reviews_photos_limit']));
        $out['theme_codecart_pro_reviews_photo_max_size'] = min(10, max(1, (int)$out['theme_codecart_pro_reviews_photo_max_size']));
        if (isset($out['theme_codecart_pro_blog_keyword']) && is_array($out['theme_codecart_pro_blog_keyword'])) {
            foreach ($out['theme_codecart_pro_blog_keyword'] as $language_id => $keyword) {
                $keyword = $this->slug($keyword);
                $out['theme_codecart_pro_blog_keyword'][(int)$language_id] = $keyword ? $keyword : 'blog';
            }
        }
        for ($i = 1; $i <= 5; $i++) {
            $slide_image_key = 'theme_codecart_pro_home_slide' . $i . '_image';
            $banner_image_key = 'theme_codecart_pro_home_banner' . $i . '_image';
            if (isset($out[$slide_image_key])) {
                $out[$slide_image_key] = $this->sanitizeImagePath($out[$slide_image_key]);
            }
            if (isset($out[$banner_image_key])) {
                $out[$banner_image_key] = $this->sanitizeImagePath($out[$banner_image_key]);
            }
        }

        if (isset($out['theme_codecart_pro_langdir_prefix']) && is_array($out['theme_codecart_pro_langdir_prefix'])) {
            foreach ($out['theme_codecart_pro_langdir_prefix'] as $language_id => $prefix) {
                $prefix = trim((string)$prefix, "/ ");
                $prefix = preg_replace('/[^a-zA-Z0-9_-]+/', '', $prefix);
                $out['theme_codecart_pro_langdir_prefix'][(int)$language_id] = $prefix;
            }
        }
        return $out;
    }


    private function sanitizeImagePath($path) {
        $path = trim(str_replace('\\', '/', (string)$path));
        $path = ltrim($path, '/');
        if (strpos($path, 'image/') === 0) {
            $path = substr($path, 6);
        }

        if ($path === '' || strpos($path, '..') !== false || strpos($path, '://') !== false || strpos($path, '//') === 0 || strpos($path, '?') !== false || strpos($path, '#') !== false || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return '';
        }

        return preg_match('#\.(?:jpe?g|png|gif|webp|avif)$#i', $path) ? $path : '';
    }

    private function remapLanguageDefaults($defaults) {
        if (!isset($this->model_localisation_language)) {
            $this->load->model('localisation/language');
        }

        $languages = $this->model_localisation_language->getLanguages();
        if (!$languages) {
            return $defaults;
        }

        $samples = array(
            1 => 'en',
            2 => 'ru',
            3 => 'uk'
        );

        foreach ($defaults as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $has_sample_keys = false;
            foreach (array_keys($samples) as $sample_key) {
                if (array_key_exists($sample_key, $value)) {
                    $has_sample_keys = true;
                    break;
                }
            }

            if (!$has_sample_keys) {
                continue;
            }

            $mapped = array();
            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                $code = strtolower((string)$language['code']);
                if (strpos($code, 'ru') === 0) {
                    $sample_id = 2;
                } elseif (strpos($code, 'uk') === 0 || strpos($code, 'ua') === 0) {
                    $sample_id = 3;
                } else {
                    $sample_id = 1;
                }

                if (array_key_exists($sample_id, $value)) {
                    $mapped[$language_id] = $value[$sample_id];
                } elseif (array_key_exists(1, $value)) {
                    $mapped[$language_id] = $value[1];
                } else {
                    $mapped[$language_id] = '';
                }
            }
            $defaults[$key] = $mapped;
        }

        return $defaults;
    }



    private function syncBlogIndexSeoUrls($settings) {
        if (!$this->tableExists('seo_url')) { return; }

        // Called only by authorised lifecycle/settings transitions, never by an ordinary OFF request.
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query IN ('ccp_blog=1', 'route=extension/module/codecart_pro_blog')");
        if (empty($settings['theme_codecart_pro_status']) || empty($settings['theme_codecart_pro_blog_status'])) {
            return;
        }

        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $keywords = isset($settings['theme_codecart_pro_blog_keyword']) && is_array($settings['theme_codecart_pro_blog_keyword']) ? $settings['theme_codecart_pro_blog_keyword'] : array();
        $stores = array(0);
        if ($this->tableExists('store')) {
            $q = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "store`");
            foreach ($q->rows as $row) { $stores[] = (int)$row['store_id']; }
        }
        foreach ($stores as $store_id) {
            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                $keyword = !empty($keywords[$language_id]) ? $this->slug($keywords[$language_id]) : 'blog';
                if ($keyword) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'route=extension/module/codecart_pro_blog', keyword = '" . $this->db->escape($keyword) . "'");
                }
            }
        }
    }

    private function slug($value) {
        $value = trim((string)$value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $map = array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ь'=>'','ы'=>'y','ъ'=>'','э'=>'e','ю'=>'yu','я'=>'ya');
        $lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : (function_exists('utf8_strtolower') ? utf8_strtolower($value) : strtolower($value));
        $value = strtr($lower, $map);
        $value = preg_replace('/[^a-z0-9_-]+/u', '-', $value);
        $value = trim($value, '-');
        return $value;
    }

    private function tableExists($table) {
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$query->num_rows;
    }

    private function getRequestSummary() {
        $summary = array('quick_orders' => 0, 'callbacks' => 0, 'subscribers' => 0);
        if ($this->tableExists('codecart_pro_quick_order')) {
            $q = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "codecart_pro_quick_order`");
            $summary['quick_orders'] = (int)$q->row['total'];
        }
        if ($this->tableExists('codecart_pro_callback_request')) {
            $q = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "codecart_pro_callback_request`");
            $summary['callbacks'] = (int)$q->row['total'];
        }
        if ($this->tableExists('codecart_pro_subscriber')) {
            $q = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "codecart_pro_subscriber`");
            $summary['subscribers'] = (int)$q->row['total'];
        }
        return $summary;
    }

    private function recentTableMap() {
        return array(
            'codecart_pro_quick_order' => 'quick_order_id',
            'codecart_pro_callback_request' => 'callback_request_id',
            'codecart_pro_subscriber' => 'subscriber_id',
            'codecart_pro_blog_article' => 'article_id',
            'codecart_pro_blog_category' => 'category_id',
            'codecart_pro_blog_category_to_store_category' => 'blog_category_id',
            'codecart_pro_review_extra' => 'review_extra_id'
        );
    }

    private function getRecentRows($table, $id, $limit = 8) {
        $allowed = $this->recentTableMap();
        if (!isset($allowed[$table]) || $allowed[$table] !== $id || !$this->tableExists($table)) {
            return array();
        }

        $limit = min(20, max(1, (int)$limit));
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . $table . "` ORDER BY `" . $allowed[$table] . "` DESC LIMIT " . (int)$limit);
        return $query->rows;
    }

    private function getPagedRows($table, $page, $limit) {
        $allowed = $this->recentTableMap();
        $result = array('rows' => array(), 'total' => 0);

        if (!isset($allowed[$table]) || !$this->tableExists($table)) {
            return $result;
        }

        $page = max(1, (int)$page);
        $limit = min(100, max(1, (int)$limit));
        $start = ($page - 1) * $limit;
        $count = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . $table . "`");
        $result['total'] = (int)$count->row['total'];

        if ($result['total'] > 0) {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . $table . "` ORDER BY `" . $allowed[$table] . "` DESC LIMIT " . (int)$start . "," . (int)$limit);
            $result['rows'] = $query->rows;
        }

        return $result;
    }

    private function renderRequestPagination($total, $page, $limit, $page_parameter) {
        if ((int)$total <= (int)$limit) {
            return '';
        }

        $allowed_parameters = array('quick_page', 'callback_page', 'subscriber_page');
        if (!in_array($page_parameter, $allowed_parameters, true)) {
            return '';
        }

        $pagination = new Pagination();
        $pagination->total = (int)$total;
        $pagination->page = max(1, (int)$page);
        $pagination->limit = max(1, (int)$limit);
        $pagination->url = $this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'] . '&ccp_tab=tab-requests&' . $page_parameter . '={page}', true);
        return $pagination->render();
    }

    private function paginationResults($total, $page, $limit) {
        $total = (int)$total;
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $from = $total ? (($page - 1) * $limit) + 1 : 0;
        $to = min($total, $page * $limit);
        $pages = max(1, (int)ceil($total / $limit));

        return sprintf($this->language->get('text_pagination_results'), $from, $to, $total, $pages);
    }

    private function getStorageDiagnostics() {
        $tables = array(
            'codecart_pro_quick_order',
            'codecart_pro_callback_request',
            'codecart_pro_subscriber',
            'codecart_pro_blog_article',
            'codecart_pro_blog_article_description',
            'codecart_pro_blog_category',
            'codecart_pro_blog_category_description',
            'codecart_pro_blog_article_to_category',
            'codecart_pro_blog_article_to_product',
            'codecart_pro_blog_article_related',
            'codecart_pro_blog_category_to_store_category',
            'codecart_pro_review_extra'
        );

        $missing = array();
        $bad_engine = array();
        $bad_charset = array();
        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = DB_PREFIX . $table;
                continue;
            }

            $query = $this->db->query("SELECT ENGINE, TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . $table) . "' LIMIT 1");
            if ($query->num_rows) {
                $engine = strtoupper((string)$query->row['ENGINE']);
                $collation = strtolower((string)$query->row['TABLE_COLLATION']);
                if ($engine !== 'INNODB') {
                    $bad_engine[] = DB_PREFIX . $table . ':' . $engine;
                }
                if (strpos($collation, 'utf8mb4') !== 0) {
                    $bad_charset[] = DB_PREFIX . $table . ':' . $collation;
                }
            }
        }

        if ($missing) {
            $engine_value = $this->language->get('text_diag_missing_tables') . ': ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? '...' : '');
            $charset_value = $engine_value;
            return array('engine' => $engine_value, 'engine_status' => 'warning', 'charset' => $charset_value, 'charset_status' => 'warning');
        }

        return array(
            'engine' => $bad_engine ? implode(', ', array_slice($bad_engine, 0, 5)) . (count($bad_engine) > 5 ? '...' : '') : $this->language->get('text_diag_all_innodb'),
            'engine_status' => $bad_engine ? 'warning' : 'good',
            'charset' => $bad_charset ? implode(', ', array_slice($bad_charset, 0, 5)) . (count($bad_charset) > 5 ? '...' : '') : $this->language->get('text_diag_all_utf8mb4'),
            'charset_status' => $bad_charset ? 'warning' : 'good'
        );
    }

    private function getDiagnostics() {
        $items = array();
        $theme_path = DIR_CATALOG . 'view/theme/codecart_pro/template/common/header.twig';

        $items[] = array('label' => 'PHP', 'value' => PHP_VERSION, 'status' => (version_compare(PHP_VERSION, '7.4.0', '>=') && version_compare(PHP_VERSION, '8.6.0', '<')) ? 'good' : 'warning');
        $items[] = array('label' => $this->language->get('text_diag_theme_files'), 'value' => is_file($theme_path) ? $this->language->get('text_diag_ok') : $this->language->get('text_diag_missing'), 'status' => is_file($theme_path) ? 'good' : 'bad');
        $items[] = array('label' => $this->language->get('text_diag_protected_loaders'), 'value' => $this->language->get('text_diag_not_required'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_database_target'), 'value' => 'InnoDB / utf8mb4', 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_extra_frontend_payload'), 'value' => $this->language->get('text_diag_extra_frontend_payload_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_quick_order_storage'), 'value' => $this->language->get('text_diag_quick_order_storage_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_home_builder'), 'value' => $this->language->get('text_diag_home_builder_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_live_search'), 'value' => $this->language->get('text_diag_live_search_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_product_modules'), 'value' => $this->language->get('text_diag_product_modules_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_requests_storage'), 'value' => $this->language->get('text_diag_requests_storage_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_seo_schema'), 'value' => $this->language->get('text_diag_seo_schema_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_seo_index_guard'), 'value' => (int)$this->config->get('theme_codecart_pro_seo_index_guard_status') ? $this->language->get('text_diag_seo_index_guard_on') : $this->language->get('text_disabled'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_image_lcp_mode'), 'value' => sprintf($this->language->get('text_diag_image_lcp_mode_value'), (int)$this->config->get('theme_codecart_pro_performance_eager_first_images')), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_content_layer'), 'value' => $this->language->get('text_diag_content_layer_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_mega_menu'), 'value' => (int)$this->config->get('theme_codecart_pro_mega_menu_status') ? $this->language->get('text_diag_mega_menu_on') : $this->language->get('text_diag_mega_menu_off'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_blog_admin_crud'), 'value' => $this->language->get('text_diag_blog_admin_crud_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_blog_seo_urls'), 'value' => $this->language->get('text_diag_blog_seo_urls_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_blog_sitemap'), 'value' => 'extension/feed/codecart_pro_sitemap', 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_blog_structured_data'), 'value' => $this->language->get('text_diag_blog_structured_data_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_langdir_hints'), 'value' => (int)$this->config->get('theme_codecart_pro_langdir_status') ? $this->language->get('text_diag_langdir_hints_on') : $this->language->get('text_disabled'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_blog_index_seo_url'), 'value' => $this->tableExists('seo_url') ? $this->language->get('text_diag_blog_index_seo_url_on') : $this->language->get('text_diag_blog_index_seo_url_off'), 'status' => $this->tableExists('seo_url') ? 'good' : 'warning');
        $items[] = array('label' => 'hreflang', 'value' => (int)$this->config->get('theme_codecart_pro_hreflang_status') ? $this->language->get('text_diag_hreflang_on') : $this->language->get('text_disabled'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_uninstall_data_policy'), 'value' => (int)$this->config->get('theme_codecart_pro_uninstall_keep_data') ? $this->language->get('text_diag_uninstall_data_policy_keep') : $this->language->get('text_diag_uninstall_data_policy_drop'), 'status' => (int)$this->config->get('theme_codecart_pro_uninstall_keep_data') ? 'good' : 'warning');
        $items[] = array('label' => $this->language->get('text_diag_settings_backup_tools'), 'value' => $this->language->get('text_diag_settings_backup_tools_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_preset_wizard'), 'value' => $this->language->get('text_diag_preset_wizard_value'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_checkout_style_pack'), 'value' => (int)$this->config->get('theme_codecart_pro_checkout_style_status') ? $this->language->get('text_diag_checkout_style_pack_on') : $this->language->get('text_disabled'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_simple_checkout'), 'value' => ($this->config->has('theme_codecart_pro_simple_checkout_status') ? (int)$this->config->get('theme_codecart_pro_simple_checkout_status') : 0) ? $this->language->get('text_diag_simple_checkout_on') : $this->language->get('text_disabled'), 'status' => (($this->config->has('theme_codecart_pro_simple_checkout_status') ? (int)$this->config->get('theme_codecart_pro_simple_checkout_status') : 0) ? 'warning' : 'good'));
        $items[] = array('label' => $this->language->get('text_diag_digital_checkout_delivery_mode'), 'value' => ($this->config->has('theme_codecart_pro_checkout_digital_mode') ? (int)$this->config->get('theme_codecart_pro_checkout_digital_mode') : 0) ? $this->language->get('text_diag_digital_checkout_delivery_mode_safe') : $this->language->get('text_disabled'), 'status' => 'good');
        $items[] = array('label' => $this->language->get('text_diag_digital_instruction'), 'value' => ($this->config->has('theme_codecart_pro_checkout_digital_instruction_status') ? (int)$this->config->get('theme_codecart_pro_checkout_digital_instruction_status') : 1) ? $this->language->get('text_diag_digital_instruction_on') : $this->language->get('text_disabled'), 'status' => 'good');
        $storage = $this->getStorageDiagnostics();
        $items[] = array('label' => $this->language->get('text_diag_module_table_engines'), 'value' => $storage['engine'], 'status' => $storage['engine_status']);
        $items[] = array('label' => $this->language->get('text_diag_module_table_charset'), 'value' => $storage['charset'], 'status' => $storage['charset_status']);
        return $items;
    }
}
