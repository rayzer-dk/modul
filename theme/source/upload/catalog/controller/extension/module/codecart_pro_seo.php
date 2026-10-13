<?php
class ControllerExtensionModuleCodecartProSeo extends Controller {
    public function index() {
        if (!$this->isCodeCartProThemeActive()) { return ''; }
        if (!(int)$this->config->get('theme_codecart_pro_status')) { return ''; }

        $route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';
        $current = $this->currentUrl();
        $title = $this->document->getTitle();
        $description = $this->document->getDescription();
        $article = array();

        if ($route == 'extension/module/codecart_pro_blog/article' && isset($this->request->get['ccp_article_id'])) {
            $article = $this->getBlogArticle((int)$this->request->get['ccp_article_id']);
        }

        $out = '';

        if ((int)$this->config->get('theme_codecart_pro_seo_opengraph_status')) {
            $og_type = 'website';
            if ($route == 'product/product') { $og_type = 'product'; }
            if ($route == 'extension/module/codecart_pro_blog/article') { $og_type = 'article'; }

            $out .= "\n<meta property=\"og:type\" content=\"" . $this->escape($og_type) . "\" />";
            $out .= "\n<meta property=\"og:title\" content=\"" . $this->escape($title) . "\" />";
            if ($description) { $out .= "\n<meta property=\"og:description\" content=\"" . $this->escape($description) . "\" />"; }
            $out .= "\n<meta property=\"og:url\" content=\"" . $this->escape($current) . "\" />";
            $out .= "\n<meta property=\"og:site_name\" content=\"" . $this->escape($this->config->get('config_name')) . "\" />";

            $og_image = $this->getOgImage($route, $article);
            if ($og_image) { $out .= "\n<meta property=\"og:image\" content=\"" . $this->escape($og_image) . "\" />"; }
        }

        if ((int)$this->config->get('theme_codecart_pro_hreflang_status')) {
            $out .= $this->buildHreflangLinks($route, $article);
        }

        if ((int)$this->config->get('theme_codecart_pro_seo_index_guard_status')) {
            $guard = $this->buildCategoryIndexGuard($route);
            if ($guard) { $out .= $guard; }
        }

        if ((int)$this->config->get('theme_codecart_pro_seo_schema_status')) {
            $schema = $this->buildSchema($route, $current, $article);
            if ($schema) { $out .= "\n<script type=\"application/ld+json\">" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>"; }
        }

        return $out;
    }

    private function currentUrl() {
        $server = $this->config->get('config_url');
        if (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] != 'off') {
            $server = $this->config->get('config_ssl') ? $this->config->get('config_ssl') : $server;
        }
        if (!empty($this->request->server['REQUEST_URI'])) {
            return rtrim($server, '/') . $this->request->server['REQUEST_URI'];
        }
        return $server;
    }



    private function buildCategoryIndexGuard($route) {
        if ($route !== 'product/category' || empty($this->request->get['path'])) {
            return '';
        }

        $noindex = false;
        if ((int)$this->config->get('theme_codecart_pro_seo_noindex_sort') && (isset($this->request->get['sort']) || isset($this->request->get['order']))) {
            $noindex = true;
        }
        if ((int)$this->config->get('theme_codecart_pro_seo_noindex_limit') && isset($this->request->get['limit'])) {
            $noindex = true;
        }
        if ((int)$this->config->get('theme_codecart_pro_seo_noindex_filter') && isset($this->request->get['filter'])) {
            $noindex = true;
        }
        if ((int)$this->config->get('theme_codecart_pro_seo_noindex_page') && isset($this->request->get['page']) && (int)$this->request->get['page'] > 1) {
            $noindex = true;
        }

        $out = '';
        if ($noindex) {
            $out .= "\n<meta name=\"robots\" content=\"noindex,follow\" />";
        }

        if ($noindex && (int)$this->config->get('theme_codecart_pro_seo_clean_canonical')) {
            $path = preg_replace('/[^0-9_]/', '', (string)$this->request->get['path']);
            if ($path !== '') {
                $out .= "\n<link rel=\"canonical\" href=\"" . $this->escape($this->url->link('product/category', 'path=' . $path, true)) . "\" />";
            }
        }

        return $out;
    }

    private function buildSchema($route, $current, $article = array()) {
        $name = (string)$this->config->get('theme_codecart_pro_seo_organization_name');
        if ($name === '') { $name = (string)$this->config->get('config_name'); }
        $logo = (string)$this->config->get('theme_codecart_pro_seo_organization_logo');
        $phone = (string)$this->config->get('theme_codecart_pro_seo_organization_phone');
        $email = (string)$this->config->get('theme_codecart_pro_seo_organization_email');
        $base_url = rtrim($this->config->get('config_url'), '/');

        $org = array('@id' => $base_url . '#organization', '@type' => 'Organization', 'name' => $name, 'url' => $this->config->get('config_url'));
        if ($logo) {
            $logo_url = $this->absoluteUrl($logo);
            if ($logo_url !== '') { $org['logo'] = $logo_url; }
        }
        if ($phone) { $org['telephone'] = $phone; }
        if ($email) { $org['email'] = $email; }

        $schema = array('@context' => 'https://schema.org', '@graph' => array($org));

        if ((int)$this->config->get('theme_codecart_pro_website_search_schema_status')) {
            $schema['@graph'][] = array(
                '@id' => $base_url . '#website',
                '@type' => 'WebSite',
                'url' => $this->config->get('config_url'),
                'name' => $name,
                'publisher' => array('@id' => $org['@id']),
                'potentialAction' => array(
                    '@type' => 'SearchAction',
                    'target' => $this->url->link('product/search', 'search={search_term_string}', true),
                    'query-input' => 'required name=search_term_string'
                )
            );
        }

        if ($route == 'product/product' && isset($this->request->get['product_id']) && (int)$this->config->get('theme_codecart_pro_merchant_schema_status')) {
            $this->load->model('catalog/product');
            $this->load->model('tool/image');
            $product_id = (int)$this->request->get['product_id'];
            $product = $this->model_catalog_product->getProduct($product_id);

            if ($product) {
                $description = trim(strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8')));
                $item = array(
                    '@type' => 'Product',
                    '@id' => $current . '#product',
                    'name' => $product['name'],
                    'description' => utf8_substr($description, 0, 1000),
                    'url' => $current
                );

                $sku = !empty($product['sku']) ? $product['sku'] : (!empty($product['model']) ? $product['model'] : 'product-' . $product_id);
                if ($sku) { $item['sku'] = $sku; }
                if (!empty($product['mpn'])) { $item['mpn'] = $product['mpn']; }
                foreach (array('ean' => 'gtin13', 'upc' => 'gtin12', 'jan' => 'gtin13', 'isbn' => 'gtin13') as $field => $schema_field) {
                    if (!empty($product[$field])) { $item[$schema_field] = $product[$field]; break; }
                }
                if (!empty($product['manufacturer'])) { $item['brand'] = array('@type' => 'Brand', 'name' => $product['manufacturer']); }

                $images = $this->getProductImagesForSchema($product, $product_id);
                if ($images) { $item['image'] = $images; }

                if ((float)$product['price'] > 0) {
                    $currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
                    $price = $product['special'] ? $product['special'] : $product['price'];
                    $offer = array(
                        '@type' => 'Offer',
                        'url' => $current,
                        'priceCurrency' => $currency_code,
                        'price' => number_format((float)$this->currency->format($price, $currency_code, '', false), 2, '.', ''),
                        'availability' => ((int)$product['quantity'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'),
                        'itemCondition' => 'https://schema.org/NewCondition'
                    );

                    $special_end = $this->getSpecialDateEnd($product_id);
                    if ($special_end) { $offer['priceValidUntil'] = $special_end; }

                    if ((int)$this->config->get('theme_codecart_pro_shipping_schema_status')) {
                        $shipping = $this->buildShippingDetails($currency_code);
                        if ($shipping) { $offer['shippingDetails'] = $shipping; }
                    }
                    if ((int)$this->config->get('theme_codecart_pro_return_schema_status')) {
                        $return_policy = $this->buildReturnPolicy();
                        if ($return_policy) { $offer['hasMerchantReturnPolicy'] = $return_policy; }
                    }
                    $item['offers'] = $offer;
                }

                if ((int)$this->config->get('theme_codecart_pro_product_review_schema_status')) {
                    $rating = $this->getProductRatingSummary($product_id);
                    if ($rating) { $item['aggregateRating'] = $rating; }
                    $reviews = $this->getProductReviewsForSchema($product_id);
                    if ($reviews) { $item['review'] = $reviews; }
                }

                $schema['@graph'][] = $item;
            }
        }

        if ($route == 'extension/module/codecart_pro_blog/article' && $article && (int)$this->config->get('theme_codecart_pro_blog_schema_status')) {
            $image = $this->getArticleImage($article, 1200, 630);
            $blog = array(
                '@type' => 'BlogPosting',
                'headline' => $article['title'],
                'mainEntityOfPage' => array('@type' => 'WebPage', '@id' => $current),
                'datePublished' => date('c', strtotime($article['date_added'])),
                'dateModified' => !empty($article['date_modified']) ? date('c', strtotime($article['date_modified'])) : date('c', strtotime($article['date_added'])),
                'author' => array('@id' => $org['@id']),
                'publisher' => array('@id' => $org['@id']),
                'description' => $article['meta_description'] ? $article['meta_description'] : utf8_substr(trim(strip_tags(html_entity_decode($article['description'], ENT_QUOTES, 'UTF-8'))), 0, 300)
            );
            if ($image) { $blog['image'] = array($image); }
            $schema['@graph'][] = $blog;
            $schema['@graph'][] = array('@type' => 'BreadcrumbList', 'itemListElement' => array(
                array('@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $this->url->link('common/home', '', true)),
                array('@type' => 'ListItem', 'position' => 2, 'name' => $this->getBlogTitle(), 'item' => $this->url->link('extension/module/codecart_pro_blog', '', true)),
                array('@type' => 'ListItem', 'position' => 3, 'name' => $article['title'], 'item' => $current)
            ));
        }

        return $schema;
    }

    private function getOgImage($route, $article) {
        if ($route == 'extension/module/codecart_pro_blog/article' && $article && (int)$this->config->get('theme_codecart_pro_blog_opengraph_image_status')) {
            return $this->getArticleImage($article, 1200, 630);
        }
        if ($route == 'product/product' && isset($this->request->get['product_id'])) {
            $this->load->model('catalog/product');
            $this->load->model('tool/image');
            $product = $this->model_catalog_product->getProduct((int)$this->request->get['product_id']);
            if ($product && !empty($product['image'])) { return $this->model_tool_image->resize($product['image'], 1200, 630); }
        }
        return '';
    }

    private function getArticleImage($article, $width, $height) {
        if (empty($article['image'])) { return ''; }
        $this->load->model('tool/image');
        return $this->model_tool_image->resize($article['image'], (int)$width, (int)$height);
    }

    private function getBlogArticle($article_id) {
        if (!$this->tableExists('codecart_pro_blog_article') || !$this->tableExists('codecart_pro_blog_article_description')) { return array(); }
        $language_id = (int)$this->config->get('config_language_id');
        $query = $this->db->query("SELECT a.*, ad.title, ad.meta_title, ad.meta_description, ad.meta_keyword, ad.description, ad.seo_text FROM `" . DB_PREFIX . "codecart_pro_blog_article` a LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') WHERE a.article_id = '" . (int)$article_id . "' AND a.status = '1' AND (a.date_available IS NULL OR a.date_available <= CURDATE()) LIMIT 1");
        return $query->num_rows ? $query->row : array();
    }

    private function tableExists($table) {
        $q = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$q->num_rows;
    }

    private function getBlogTitle() {
        $value = $this->config->get('theme_codecart_pro_blog_title');
        $language_id = (int)$this->config->get('config_language_id');
        if (is_array($value) && !empty($value[$language_id])) { return $value[$language_id]; }
        return 'Blog';
    }

    private function absoluteUrl($path) {
        $path = ltrim(trim((string)$path), '/');
        if (strpos($path, 'image/') === 0) { $path = substr($path, 6); }

        if ($path === ''
            || strpos($path, '..') !== false
            || strpos($path, ':') !== false
            || strpos($path, '//') !== false
            || !preg_match('/\.(?:jpe?g|png|gif|webp|avif)$/i', $path)
            || !is_file(DIR_IMAGE . $path)
        ) {
            return '';
        }

        return rtrim($this->config->get('config_url'), '/') . '/image/' . $path;
    }



    private function getProductImagesForSchema($product, $product_id) {
        $images = array();
        if (!empty($product['image'])) { $images[] = $this->model_tool_image->resize($product['image'], 1000, 1000); }
        $query = $this->db->query("SELECT image FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC LIMIT 5");
        foreach ($query->rows as $row) {
            if (!empty($row['image'])) { $images[] = $this->model_tool_image->resize($row['image'], 1000, 1000); }
        }
        return array_values(array_unique(array_filter($images)));
    }

    private function getSpecialDateEnd($product_id) {
        $customer_group_id = isset($this->customer) && $this->customer->isLogged() ? (int)$this->customer->getGroupId() : (int)$this->config->get('config_customer_group_id');
        $query = $this->db->query("SELECT date_end FROM `" . DB_PREFIX . "product_special` WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$customer_group_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");
        if ($query->num_rows && !empty($query->row['date_end']) && $query->row['date_end'] != '0000-00-00') { return $query->row['date_end']; }
        return '';
    }

    private function getProductRatingSummary($product_id) {
        $query = $this->db->query("SELECT COUNT(*) AS total, AVG(rating) AS rating FROM `" . DB_PREFIX . "review` WHERE product_id = '" . (int)$product_id . "' AND status = '1'");
        if (!$query->num_rows || (int)$query->row['total'] <= 0) { return array(); }
        return array('@type' => 'AggregateRating', 'ratingValue' => number_format((float)$query->row['rating'], 1, '.', ''), 'reviewCount' => (int)$query->row['total'], 'bestRating' => '5', 'worstRating' => '1');
    }

    private function getProductReviewsForSchema($product_id) {
        $reviews = array();
        $query = $this->db->query("SELECT author, text, rating, date_added FROM `" . DB_PREFIX . "review` WHERE product_id = '" . (int)$product_id . "' AND status = '1' ORDER BY date_added DESC LIMIT 3");
        foreach ($query->rows as $row) {
            $text = utf8_substr(trim(strip_tags(html_entity_decode($row['text'], ENT_QUOTES, 'UTF-8'))), 0, 500);
            if ($text === '') { continue; }
            $reviews[] = array('@type' => 'Review', 'author' => array('@type' => 'Person', 'name' => $row['author']), 'datePublished' => date('Y-m-d', strtotime($row['date_added'])), 'reviewBody' => $text, 'reviewRating' => array('@type' => 'Rating', 'ratingValue' => (int)$row['rating'], 'bestRating' => '5', 'worstRating' => '1'));
        }
        return $reviews;
    }

    private function buildShippingDetails($currency_code) {
        $country = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', (string)$this->config->get('theme_codecart_pro_shipping_country')), 0, 2));
        if ($country === '') { return array(); }
        $price = number_format(max(0, (float)str_replace(',', '.', (string)$this->config->get('theme_codecart_pro_shipping_price'))), 2, '.', '');
        $min_days = max(0, (int)$this->config->get('theme_codecart_pro_shipping_days_min'));
        $max_days = max($min_days, (int)$this->config->get('theme_codecart_pro_shipping_days_max'));
        return array('@type' => 'OfferShippingDetails', 'shippingRate' => array('@type' => 'MonetaryAmount', 'value' => $price, 'currency' => $currency_code), 'shippingDestination' => array('@type' => 'DefinedRegion', 'addressCountry' => $country), 'deliveryTime' => array('@type' => 'ShippingDeliveryTime', 'handlingTime' => array('@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'), 'transitTime' => array('@type' => 'QuantitativeValue', 'minValue' => $min_days, 'maxValue' => $max_days, 'unitCode' => 'DAY')));
    }

    private function buildReturnPolicy() {
        $country = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', (string)$this->config->get('theme_codecart_pro_return_country')), 0, 2));
        if ($country === '') { return array(); }
        $days = max(0, (int)$this->config->get('theme_codecart_pro_return_days'));
        return array('@type' => 'MerchantReturnPolicy', 'applicableCountry' => $country, 'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow', 'merchantReturnDays' => $days, 'returnMethod' => 'https://schema.org/ReturnByMail', 'returnFees' => 'https://schema.org/ReturnShippingFees');
    }

    private function buildHreflangLinks($route, $article = array()) {
        if (!in_array($route, array('extension/module/codecart_pro_blog', 'extension/module/codecart_pro_blog/article'), true)) { return ''; }
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $out = '';
        $article_id = ($route == 'extension/module/codecart_pro_blog/article' && !empty($article['article_id'])) ? (int)$article['article_id'] : 0;
        foreach ($languages as $language) {
            if (empty($language['status'])) { continue; }
            $href = $this->buildBlogHref((int)$language['language_id'], (string)$language['code'], $article_id);
            if ($href) {
                $hreflang = strtolower(str_replace('_', '-', (string)$language['code']));
                $out .= "\n<link rel=\"alternate\" hreflang=\"" . $this->escape($hreflang) . "\" href=\"" . $this->escape($href) . "\" />";
            }
        }
        if ((int)$this->config->get('theme_codecart_pro_hreflang_xdefault_status')) {
            $default_href = $this->buildBlogHref((int)$this->config->get('config_language_id'), (string)$this->config->get('config_language'), $article_id);
            if ($default_href) { $out .= "\n<link rel=\"alternate\" hreflang=\"x-default\" href=\"" . $this->escape($default_href) . "\" />"; }
        }
        return $out;
    }

    private function buildBlogHref($language_id, $language_code, $article_id = 0) {
        $base = rtrim($this->getCatalogBase(), '/') . '/';
        if ((int)$this->config->get('theme_codecart_pro_langdir_status')) {
            $prefixes = $this->config->get('theme_codecart_pro_langdir_prefix');
            $prefix = is_array($prefixes) && isset($prefixes[$language_id]) ? trim((string)$prefixes[$language_id], '/ ') : $this->defaultPrefix($language_code);
            if ($prefix !== '') { $base .= rawurlencode($prefix) . '/'; }
        }
        if ($article_id) {
            $keyword = $this->getSeoKeyword('ccp_article_id=' . (int)$article_id, $language_id);
            return $keyword ? $base . rawurlencode($keyword) : $base . 'index.php?route=extension/module/codecart_pro_blog/article&ccp_article_id=' . (int)$article_id;
        }
        $keyword = $this->getSeoKeyword('route=extension/module/codecart_pro_blog', $language_id);
        return $keyword ? $base . rawurlencode($keyword) : $base . 'index.php?route=extension/module/codecart_pro_blog';
    }

    private function getSeoKeyword($query, $language_id) {
        if (!$this->tableExists('seo_url')) { return ''; }
        $q = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE query = '" . $this->db->escape($query) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");
        return $q->num_rows ? (string)$q->row['keyword'] : '';
    }

    private function defaultPrefix($language_code) {
        $code = strtolower((string)$language_code);
        if (strpos($code, 'uk') === 0 || strpos($code, 'ua') === 0) { return 'ua'; }
        if (strpos($code, 'ru') === 0) { return 'ru'; }
        if (strpos($code, 'en') === 0) { return 'en'; }
        return substr($code, 0, 2);
    }

    private function getCatalogBase() {
        if (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] != 'off' && $this->config->get('config_ssl')) { return $this->config->get('config_ssl'); }
        return $this->config->get('config_url');
    }

    private function escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        return ($config_theme === 'codecart_pro' || $directory === 'codecart_pro' || (string)$this->config->get('config_template') === 'codecart_pro');
    }

}
