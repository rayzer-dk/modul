<?php
class ControllerExtensionModuleCodecartProCompatibility extends Controller {
    public function requestPathGuard(&$route = null, &$args = null, &$output = null) {
        if (!$this->isThemeActive()) {
            return;
        }

        // A synthetic empty path changes ocStore product/category membership semantics.
        if ((string)($this->request->get['route'] ?? '') === 'product/product') {
            return;
        }

        if (!isset($this->request->get['path'])) {
            $this->request->get['path'] = '';
        }
    }

    public function featuredPathGuard(&$route = null, &$args = null, &$output = null) {
        if (!$this->isThemeActive()) {
            return;
        }

        // A synthetic empty path changes ocStore product/category membership semantics.
        if ((string)($this->request->get['route'] ?? '') === 'product/product') {
            return;
        }

        if (!isset($this->request->get['path'])) {
            $this->request->get['path'] = '';
        }
    }

    public function categoryViewBefore(&$route = null, &$data = null, &$output = null) {
        if (!$this->isThemeActive() || !is_array($data)) {
            return;
        }

        $path = isset($this->request->get['path']) ? (string)$this->request->get['path'] : '';
        $parts = array_values(array_filter(array_map('intval', explode('_', $path))));
        $category_id = $parts ? (int)end($parts) : 0;
        if ($category_id < 1) {
            return;
        }

        $this->load->model('catalog/category');
        $this->load->model('tool/image');
        $category_info = $this->model_catalog_category->getCategory($category_id);
        if (!$category_info || empty($category_info['image']) || !is_file(DIR_IMAGE . $category_info['image'])) {
            return;
        }

        $configured_width = (int)$this->config->get('theme_codecart_pro_category_hero_width');
        $configured_height = (int)$this->config->get('theme_codecart_pro_category_hero_height');
        $size_candidates = array_filter(array($configured_width, $configured_height));
        $size = $size_candidates ? min($size_candidates) : 720;
        $size = min(1400, max(480, (int)$size));

        $data['ccp_category_thumb_large'] = $this->resizeWithinBounds($category_info['image'], $size, $size)['url'];
        $data['ccp_category_thumb_width'] = $size;
        $data['ccp_category_thumb_height'] = $size;
    }

    public function productViewBefore(&$route = null, &$data = null, &$output = null) {
        if (!$this->isThemeActive() || !is_array($data)) {
            return;
        }

        try {
            $this->applyProductViewData($data);
        } catch (Exception $e) {
            $this->log->write('CodeCart PRO Theme product view fallback: ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->log->write('CodeCart PRO Theme product view fallback: ' . $e->getMessage());
        }
    }

    private function applyProductViewData(&$data) {
        $product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
        if ($product_id < 1) {
            return;
        }

        $this->load->model('catalog/product');
        $product_info = $this->model_catalog_product->getProduct($product_id);
        if (!$product_info) {
            return;
        }

        $this->ensureProductCategoryBreadcrumbs($data, $product_info, $product_id);

        $ccp_product_language = $this->load->controller('extension/module/codecart_pro_language/product');
        if (!is_array($ccp_product_language)) { $ccp_product_language = array(); }
        $ccp_lang = function($key, $fallback = '') use ($ccp_product_language) {
            return isset($ccp_product_language[$key]) && $ccp_product_language[$key] !== '' ? $ccp_product_language[$key] : $fallback;
        };
        $ccp_language_code = (string)$this->config->get('config_language');
        $ccp_is_ru = (strpos($ccp_language_code, 'ru') === 0);
        $ccp_is_uk = (strpos($ccp_language_code, 'uk') === 0 || strpos($ccp_language_code, 'ua') === 0);
        $ccp_text = function($en, $ru, $uk) use ($ccp_is_ru, $ccp_is_uk) { return $ccp_is_ru ? $ru : ($ccp_is_uk ? $uk : $en); };
        $ccp_product_page_id = (int)$product_id;
        $data['ccp_language_id'] = (int)$this->config->get('config_language_id');
        $ccp_product_schema_version = (int)$this->config->get('theme_codecart_pro_schema_version');
        $data['ccp_information_mode'] = ($ccp_product_schema_version < 852) ? 1 : (int)$this->config->get('theme_codecart_pro_information_mode');
        $data['ccp_enable_quick_order'] = (int)$this->config->get('theme_codecart_pro_enable_quick_order');
        $data['ccp_enable_live_price'] = (int)$this->config->get('theme_codecart_pro_enable_live_price');
        $data['ccp_base_price_raw'] = 0;
        $data['ccp_price_format'] = '';
        if (isset($product_info) && is_array($product_info) && !empty($product_info['price'])) {
            $ccp_raw_price = !empty($product_info['special']) ? (float)$product_info['special'] : (float)$product_info['price'];
            if ($ccp_raw_price > 0) {
                $ccp_tax_class_id = isset($product_info['tax_class_id']) ? (int)$product_info['tax_class_id'] : 0;
                $ccp_current_currency = isset($this->session->data['currency']) ? (string)$this->session->data['currency'] : (string)$this->config->get('config_currency');
                $ccp_calculated_price = $this->tax->calculate($ccp_raw_price, $ccp_tax_class_id, $this->config->get('config_tax'));
                if (method_exists($this->currency, 'convert')) {
                    $ccp_calculated_price = $this->currency->convert($ccp_calculated_price, $this->config->get('config_currency'), $ccp_current_currency);
                }
                $data['ccp_base_price_raw'] = (float)$ccp_calculated_price;
                $data['ccp_price_format'] = !empty($data['special']) ? (string)$data['special'] : (!empty($data['price']) ? (string)$data['price'] : '');
            }
        }
        $data['ccp_enable_stock_indicator'] = (int)$this->config->get('theme_codecart_pro_enable_stock_indicator');
        $data['ccp_enable_stickers'] = (int)$this->config->get('theme_codecart_pro_enable_stickers');
        $data['ccp_card_show_stock'] = (int)$this->config->get('theme_codecart_pro_card_show_stock');
        $data['ccp_card_style'] = (string)$this->config->get('theme_codecart_pro_card_style') ?: 'commerce';
        $data['ccp_card_title_lines'] = (int)$this->config->get('theme_codecart_pro_card_title_lines') ?: 2;
        $data['ccp_card_full_click'] = (int)$this->config->get('theme_codecart_pro_card_full_click');
        $data['ccp_card_show_description'] = (int)$this->config->get('theme_codecart_pro_card_show_description');
        $data['ccp_card_show_rating'] = (int)$this->config->get('theme_codecart_pro_card_show_rating');
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
        $data['ccp_product_quantity_switch'] = (int)$this->config->get('theme_codecart_pro_product_quantity_switch');
        $data['ccp_product_show_actions'] = (int)$this->config->get('theme_codecart_pro_product_show_actions');
        $data['ccp_product_show_benefits'] = (int)$this->config->get('theme_codecart_pro_product_show_benefits');
        $data['ccp_product_show_delivery_block'] = (int)$this->config->get('theme_codecart_pro_product_show_delivery_block');
        $data['ccp_product_trust_panel_status'] = (int)$this->config->get('theme_codecart_pro_product_trust_panel_status');
        $data['ccp_product_sale_timer'] = (int)$this->config->get('theme_codecart_pro_product_sale_timer');
        $data['ccp_product_faq_status'] = (int)$this->config->get('theme_codecart_pro_product_faq_status');
        $data['ccp_product_faq_schema_status'] = (int)$this->config->get('theme_codecart_pro_product_faq_schema_status');
        $data['ccp_product_show_attributes'] = (int)$this->config->get('theme_codecart_pro_product_show_attributes');
        $data['ccp_product_attributes_style'] = (string)$this->config->get('theme_codecart_pro_product_attributes_style') ?: 'table';
        $data['ccp_product_bottom_sticky_buy'] = (int)$this->config->get('theme_codecart_pro_product_bottom_sticky_buy');
        $data['ccp_product_bottom_sticky_buy_desktop'] = (int)$this->config->get('theme_codecart_pro_product_bottom_sticky_buy_desktop');
        $data['ccp_product_bottom_sticky_buy_mobile'] = (int)$this->config->get('theme_codecart_pro_product_bottom_sticky_buy_mobile');
        $data['ccp_product_gallery_mode'] = (string)$this->config->get('theme_codecart_pro_product_gallery_mode') ?: 'clean';
        $data['ccp_product_gallery_ratio'] = (string)$this->config->get('theme_codecart_pro_product_gallery_ratio') ?: '4x3';
        $ccp_product_trust_badges = $this->config->get('theme_codecart_pro_product_trust_badges');
        $ccp_product_delivery_text = $this->config->get('theme_codecart_pro_product_delivery_text');
        $ccp_product_return_text = $this->config->get('theme_codecart_pro_product_return_text');
        $ccp_product_benefits = $this->config->get('theme_codecart_pro_product_benefits');
        $ccp_product_faq = $this->config->get('theme_codecart_pro_product_faq');
        $data['ccp_product_trust_badges'] = is_array($ccp_product_trust_badges) ? $ccp_product_trust_badges : array();
        $data['ccp_product_delivery_text'] = is_array($ccp_product_delivery_text) ? $ccp_product_delivery_text : array();
        $data['ccp_product_return_text'] = is_array($ccp_product_return_text) ? $ccp_product_return_text : array();
        $data['ccp_product_benefits'] = is_array($ccp_product_benefits) ? $ccp_product_benefits : array();
        $data['ccp_product_faq'] = is_array($ccp_product_faq) ? $ccp_product_faq : array();
        $data['ccp_text_delivery'] = $ccp_lang('ccp_text_delivery');
        $data['ccp_text_warranty'] = $ccp_lang('ccp_text_warranty');
        $data['ccp_text_trust_badges_default'] = $ccp_lang('ccp_text_trust_badges_default');
        $data['ccp_text_delivery_body_default'] = $ccp_lang('ccp_text_delivery_body_default');
        $data['ccp_text_license_body_default'] = $ccp_text('The module is available for download immediately after payment. The license key is sent separately by email or SMS after payment confirmation.', 'Модуль доступен для скачивания сразу после оплаты. Лицензионный ключ отправляется отдельно на e-mail или SMS после подтверждения оплаты.', 'Модуль доступний для завантаження одразу після оплати. Ліцензійний ключ надсилається окремо на e-mail або SMS після підтвердження оплати.');
        $data['ccp_text_faq'] = $ccp_lang('ccp_text_faq');
        $data['ccp_text_share'] = $ccp_lang('ccp_text_share');
        $data['ccp_button_quick_order'] = $ccp_lang('ccp_button_quick_order');
        $data['ccp_text_home'] = $ccp_lang('ccp_text_home');
        $data['ccp_text_catalog_short'] = $ccp_lang('ccp_text_catalog_short');
        $data['ccp_text_cart_short'] = $ccp_lang('ccp_text_cart_short');
        $data['ccp_text_minus'] = $ccp_lang('ccp_text_minus');
        $data['ccp_text_plus'] = $ccp_lang('ccp_text_plus');
        $data['ccp_text_sale_ends'] = $ccp_lang('ccp_text_sale_ends');
        $data['ccp_text_days'] = $ccp_lang('ccp_text_days');
        $data['ccp_text_hours'] = $ccp_lang('ccp_text_hours');
        $data['ccp_text_minutes'] = $ccp_lang('ccp_text_minutes');
        $data['ccp_text_seconds'] = $ccp_lang('ccp_text_seconds');
        $data['ccp_text_specifications'] = $ccp_lang('ccp_text_specifications');
        $data['ccp_text_changelog'] = $ccp_text('Changelog', 'История изменений', 'Історія змін');
        $data['ccp_text_copy_link'] = $ccp_lang('ccp_text_copy_link');
        $data['ccp_text_in_stock'] = $ccp_lang('ccp_text_in_stock');
        $data['ccp_text_quantity'] = $ccp_lang('ccp_text_quantity');
        $data['ccp_text_new'] = $ccp_lang('ccp_text_new');
        $data['ccp_text_top'] = $ccp_lang('ccp_text_top');
        $data['ccp_entry_review_plus'] = $ccp_lang('ccp_entry_review_plus');
        $data['ccp_entry_review_minus'] = $ccp_lang('ccp_entry_review_minus');
        $data['ccp_entry_review_photos'] = $ccp_lang('ccp_entry_review_photos');
        $data['ccp_text_review_photos_help'] = $ccp_lang('ccp_text_review_photos_help');
        $data['ccp_entry_review_recommend'] = $ccp_lang('ccp_entry_review_recommend');
        $data['ccp_reviews_plus_minus_status'] = (int)$this->config->get('theme_codecart_pro_reviews_plus_minus_status');
        $data['ccp_reviews_photos_status'] = (int)$this->config->get('theme_codecart_pro_reviews_photos_status');
        $data['ccp_reviews_real_buyer_status'] = (int)$this->config->get('theme_codecart_pro_reviews_real_buyer_status');
        $data['ccp_reviews_votes_status'] = (int)$this->config->get('theme_codecart_pro_reviews_votes_status');
        $data['ccp_product_changelog'] = '';
        if ($ccp_product_page_id) {
            $this->load->model('extension/theme/codecart_pro_product');
            $ccp_changelog_value = $this->model_extension_theme_codecart_pro_product->getChangelog($ccp_product_page_id, (int)$data['ccp_language_id']);
            if ($ccp_changelog_value !== '') {
                $data['ccp_product_changelog'] = html_entity_decode($ccp_changelog_value, ENT_QUOTES, 'UTF-8');
            }
        }
        $data['ccp_sticky_home'] = $this->url->link('common/home');
        $data['ccp_sticky_cart'] = $this->url->link('checkout/cart');
        $ccp_config_theme = (string)$this->config->get('config_theme');
        $ccp_theme_directory_key = $ccp_config_theme ? 'theme_' . $ccp_config_theme . '_directory' : '';
        $ccp_theme_directory = $ccp_theme_directory_key ? (string)$this->config->get($ccp_theme_directory_key) : '';
        $ccp_template_directory = (string)$this->config->get('template_directory');
        $ccp_is_codecart_theme = ($ccp_config_theme === 'codecart_pro' || $ccp_config_theme === 'theme_codecart_pro' || $ccp_theme_directory === 'codecart_pro' || (string)$this->config->get('config_template') === 'codecart_pro' || strpos($ccp_template_directory, 'codecart_pro/') === 0);
        // Generic cache URLs are retained. AVIF/WebP/JXL priority is controlled by the image optimizer.
        if (!empty($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $ccp_data_image_key => $ccp_data_image_value) {
                unset($data['images'][$ccp_data_image_key]['ccp_original']);
            }
        }
        $data['ccp_product_commercial_blocks'] = '';
        $ccp_product_modules_enabled = ($this->config->get('theme_codecart_pro_product_modules_status') === null || $this->config->get('theme_codecart_pro_product_modules_status') === '' || (int)$this->config->get('theme_codecart_pro_product_modules_status'));
        $ccp_product_module_product_enabled = ($this->config->get('theme_codecart_pro_product_module_product_status') === null || $this->config->get('theme_codecart_pro_product_module_product_status') === '' || (int)$this->config->get('theme_codecart_pro_product_module_product_status'));
        if ($ccp_product_modules_enabled && $ccp_product_page_id) {
            try {
                $this->load->controller('extension/module/codecart_pro_products/trackViewed', array((int)$ccp_product_page_id));
            } catch (Exception $ccp_optional_exception) {
                $this->log->write('CodeCart PRO Theme optional viewed tracker disabled: ' . $ccp_optional_exception->getMessage());
            } catch (Throwable $ccp_optional_throwable) {
                $this->log->write('CodeCart PRO Theme optional viewed tracker disabled: ' . $ccp_optional_throwable->getMessage());
            }
        }
        if ($ccp_product_modules_enabled && $ccp_product_module_product_enabled && $ccp_product_page_id) {
            try {
                $data['ccp_product_commercial_blocks'] = $this->load->controller('extension/module/codecart_pro_lazy/productBlocks', array(array('product_id' => (int)$ccp_product_page_id, 'limit' => 5)));
            } catch (Exception $ccp_optional_exception) {
                $data['ccp_product_commercial_blocks'] = '';
                $this->log->write('CodeCart PRO Theme optional product blocks disabled: ' . $ccp_optional_exception->getMessage());
            } catch (Throwable $ccp_optional_throwable) {
                $data['ccp_product_commercial_blocks'] = '';
                $this->log->write('CodeCart PRO Theme optional product blocks disabled: ' . $ccp_optional_throwable->getMessage());
            }
        }
        $data['ccp_product_articles'] = '';
        if ((int)$this->config->get('theme_codecart_pro_status') && (int)$this->config->get('theme_codecart_pro_blog_status')) {
            try {
                $data['ccp_product_articles'] = $this->load->controller('extension/module/codecart_pro_blog/productBlock', array(array('product_id' => (int)$ccp_product_page_id)));
            } catch (Exception $ccp_blog_exception) {
                $data['ccp_product_articles'] = '';
                $this->log->write('CodeCart PRO Theme blog productBlock disabled on product page: ' . $ccp_blog_exception->getMessage());
            } catch (Throwable $ccp_blog_throwable) {
                $data['ccp_product_articles'] = '';
                $this->log->write('CodeCart PRO Theme blog productBlock disabled on product page: ' . $ccp_blog_throwable->getMessage());
            }
        }

        // CodeCart PRO Theme v8.7.7: keep every product image in its native aspect ratio.
        // The image optimizer remains responsible for AVIF/WebP/JPEG XL and fallback selection.
        if ($product_info) {
            $this->load->model('tool/image');
            $ccp_popup_w_v862 = max(1, (int)$this->config->get('theme_codecart_pro_image_popup_width') ?: 1800);
            $ccp_popup_h_v862 = max(1, (int)$this->config->get('theme_codecart_pro_image_popup_height') ?: 1200);
            $ccp_thumb_w_v862 = max(1, (int)$this->config->get('theme_codecart_pro_image_thumb_width') ?: 1100);
            $ccp_thumb_h_v862 = max(1, (int)$this->config->get('theme_codecart_pro_image_thumb_height') ?: 700);
            $ccp_add_w_v862 = max(1, (int)$this->config->get('theme_codecart_pro_image_additional_width') ?: 420);
            $ccp_add_h_v862 = max(1, (int)$this->config->get('theme_codecart_pro_image_additional_height') ?: 280);

            if (!empty($product_info['image'])) {
                $ccp_main_popup_v862 = $this->resizeWithinBounds($product_info['image'], $ccp_popup_w_v862, $ccp_popup_h_v862);
                $ccp_main_thumb_v862 = $this->resizeWithinBounds($product_info['image'], $ccp_thumb_w_v862, $ccp_thumb_h_v862);
                $data['popup'] = $ccp_main_popup_v862['url'];
                $data['thumb'] = $ccp_main_thumb_v862['url'];
                $data['ccp_product_main_large'] = $ccp_main_popup_v862['url'];
                $data['ccp_product_main_thumb'] = $ccp_main_thumb_v862['url'];
                $data['ccp_product_main_width'] = $ccp_main_thumb_v862['width'];
                $data['ccp_product_main_height'] = $ccp_main_thumb_v862['height'];
                $data['ccp_product_main_ratio'] = $ccp_main_thumb_v862['ratio'];
            }

            $ccp_product_page_id_v862 = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
            if ($ccp_product_page_id_v862) {
                $ccp_gallery_images_v862 = $this->model_catalog_product->getProductImages($ccp_product_page_id_v862);
                $data['images'] = array();
                foreach ($ccp_gallery_images_v862 as $ccp_gallery_image_v862) {
                    if (empty($ccp_gallery_image_v862['image'])) {
                        continue;
                    }

                    $ccp_gallery_popup_v862 = $this->resizeWithinBounds($ccp_gallery_image_v862['image'], $ccp_popup_w_v862, $ccp_popup_h_v862);
                    $ccp_gallery_main_v862 = $this->resizeWithinBounds($ccp_gallery_image_v862['image'], $ccp_thumb_w_v862, $ccp_thumb_h_v862);
                    $ccp_gallery_thumb_v862 = $this->resizeWithinBounds($ccp_gallery_image_v862['image'], $ccp_add_w_v862, $ccp_add_h_v862);

                    $data['images'][] = array(
                        'popup'       => $ccp_gallery_popup_v862['url'],
                        'thumb'       => $ccp_gallery_thumb_v862['url'],
                        'ccp_main'    => $ccp_gallery_main_v862['url'],
                        'ccp_width'   => $ccp_gallery_main_v862['width'],
                        'ccp_height'  => $ccp_gallery_main_v862['height'],
                        'ccp_ratio'   => $ccp_gallery_main_v862['ratio']
                    );
                }
            }
        }

    }


    private function ensureProductCategoryBreadcrumbs(&$data, $product_info, $product_id) {
        if (!isset($data['breadcrumbs']) || !is_array($data['breadcrumbs']) || count($data['breadcrumbs']) < 2) {
            return;
        }

        foreach ($data['breadcrumbs'] as $breadcrumb) {
            $href = isset($breadcrumb['href']) ? (string)$breadcrumb['href'] : '';
            if (strpos($href, 'route=product/category') !== false || strpos($href, 'path=') !== false) {
                return;
            }
        }

        $category_id = 0;
        $rows = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "product_to_category` WHERE `product_id` = " . (int)$product_id . " ORDER BY `category_id` ASC"
        )->rows;

        if ($rows) {
            foreach ($rows as $row) {
                if (isset($row['main_category']) && (int)$row['main_category'] === 1) {
                    $category_id = (int)$row['category_id'];
                    break;
                }
            }
        }

        if ($category_id < 1 && !empty($product_info['main_category_id'])) {
            $category_id = (int)$product_info['main_category_id'];
        }

        if ($category_id < 1 && $rows) {
            $category_id = (int)$rows[0]['category_id'];
        }

        if ($category_id < 1) {
            return;
        }

        $language_id = (int)$this->config->get('config_language_id');
        $query = $this->db->query(
            "SELECT cp.path_id AS category_id, cd.name " .
            "FROM `" . DB_PREFIX . "category_path` cp " .
            "LEFT JOIN `" . DB_PREFIX . "category_description` cd " .
            "ON (cd.category_id = cp.path_id AND cd.language_id = " . $language_id . ") " .
            "WHERE cp.category_id = " . (int)$category_id . " ORDER BY cp.level ASC"
        );

        $categories = array();
        foreach ($query->rows as $row) {
            if (!empty($row['category_id']) && !empty($row['name'])) {
                $categories[] = array(
                    'category_id' => (int)$row['category_id'],
                    'name'        => (string)$row['name']
                );
            }
        }

        if (!$categories) {
            $this->load->model('catalog/category');
            $category_info = $this->model_catalog_category->getCategory($category_id);
            if ($category_info && !empty($category_info['name'])) {
                $categories[] = array(
                    'category_id' => $category_id,
                    'name'        => (string)$category_info['name']
                );
            }
        }

        if (!$categories) {
            return;
        }

        $product_breadcrumb = array_pop($data['breadcrumbs']);
        $path = array();
        foreach ($categories as $category) {
            $path[] = (int)$category['category_id'];
            $data['breadcrumbs'][] = array(
                'text' => $category['name'],
                'href' => $this->url->link('product/category', 'path=' . implode('_', $path))
            );
        }
        $data['breadcrumbs'][] = $product_breadcrumb;
    }


    private function resizeWithinBounds($filename, $max_width, $max_height) {
        $filename = ltrim(str_replace('\\', '/', (string)$filename), '/');
        $max_width = max(1, (int)$max_width);
        $max_height = max(1, (int)$max_height);
        $source = DIR_IMAGE . $filename;

        if ($filename === '' || strpos($filename, "\0") !== false || strpos($filename, '..') !== false || !is_file($source)) {
            $fallback = $this->model_tool_image->resize('placeholder.png', $max_width, $max_height);
            return array('url' => $fallback, 'width' => $max_width, 'height' => $max_height, 'ratio' => round($max_width / max(1, $max_height), 6));
        }

        $info = @getimagesize($source);
        if (!$info || empty($info[0]) || empty($info[1])) {
            $fallback = $this->model_tool_image->resize($filename, $max_width, $max_height);
            return array('url' => $fallback, 'width' => $max_width, 'height' => $max_height, 'ratio' => round($max_width / max(1, $max_height), 6));
        }

        $source_width = max(1, (int)$info[0]);
        $source_height = max(1, (int)$info[1]);
        $scale = min(1, $max_width / $source_width, $max_height / $source_height);
        $width = max(1, (int)round($source_width * $scale));
        $height = max(1, (int)round($source_height * $scale));

        // Use the native model seam so image optimizer Events/OCMOD also see every source.
        $url = $this->model_tool_image->resize($filename, $width, $height);
        return array('url' => $url, 'width' => $width, 'height' => $height, 'ratio' => round($width / max(1, $height), 6));
    }

    private function isThemeActive() {
        if ((int)$this->config->get('theme_codecart_pro_status') !== 1) {
            return false;
        }

        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme !== '' ? 'theme_' . $config_theme . '_directory' : '';
        $directory = $directory_key !== '' ? (string)$this->config->get($directory_key) : '';

        return $config_theme === 'codecart_pro'
            || $config_theme === 'theme_codecart_pro'
            || $directory === 'codecart_pro'
            || (string)$this->config->get('config_template') === 'codecart_pro';
    }
}
