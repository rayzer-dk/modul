<?php
class ControllerExtensionModuleCodecartProBlog extends Controller {
    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        $config_template = (string)$this->config->get('config_template');
        $template_directory = (string)$this->config->get('template_directory');

        return ($config_theme === 'codecart_pro' || $config_theme === 'theme_codecart_pro' || $directory === 'codecart_pro' || $config_template === 'codecart_pro' || strpos($template_directory, 'codecart_pro/') === 0);
    }

    private function blogUrl($route = 'extension/module/codecart_pro_blog', $query = '') {
        $secure = !empty($this->request->server['HTTPS']) && strtolower((string)$this->request->server['HTTPS']) !== 'off';
        $base = $secure ? (string)$this->config->get('config_ssl') : (string)$this->config->get('config_url');

        if ($base === '') {
            $base = $secure && defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : '/');
        }

        $params = array('route' => (string)$route);
        if (is_string($query) && $query !== '') {
            parse_str(str_replace('&amp;', '&', $query), $extra);
            if (is_array($extra)) {
                foreach ($extra as $key => $value) {
                    if (is_scalar($value)) {
                        $params[(string)$key] = (string)$value;
                    }
                }
            }
        }

        return rtrim($base, '/') . '/index.php?' . http_build_query($params, '', '&');
    }

    private function notFound() {
        $protocol = isset($this->request->server['SERVER_PROTOCOL']) ? (string)$this->request->server['SERVER_PROTOCOL'] : 'HTTP/1.1';
        $this->response->addHeader($protocol . ' 404 Not Found');
        $this->response->setOutput($this->load->controller('error/not_found'));
    }

    public function index() {
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_blog_status')) { $this->notFound(); return; }
        $this->load->language('extension/module/codecart_pro_blog');
        $this->load->model('tool/image');
        $language_id = (int)$this->config->get('config_language_id');
        $title = $this->getLang('theme_codecart_pro_blog_title', $language_id, $this->language->get('heading_title'));
        $description = $this->getLang('theme_codecart_pro_blog_description', $language_id, '');
        $tag = isset($this->request->get['tag']) ? trim($this->request->get['tag']) : '';
        if ($tag) { $title .= ' - ' . $tag; }
        $this->document->setTitle($title);
        if ($description) { $this->document->setDescription(strip_tags($description)); }
        if ((int)$this->config->get('theme_codecart_pro_blog_canonical_status') && !$tag) { $this->document->addLink($this->blogUrl('extension/module/codecart_pro_blog'), 'canonical'); }
        $data = $this->baseListData($language_id, $title, $description);
        $data['active_category_id'] = 0;
        $data['active_tag'] = $tag;
        $data['articles'] = $this->getArticles($language_id, 0, $tag);
        $this->common($data);
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_blog', $data));
    }

    public function category() {
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_blog_status')) { $this->notFound(); return; }
        $this->load->language('extension/module/codecart_pro_blog');
        $this->load->model('tool/image');
        $language_id = (int)$this->config->get('config_language_id');
        $category_id = 0;
        foreach (array('ccp_category_id', 'ccp_blog_category_id', 'blog_category_id', 'category_id', 'codecart_pro_category_id') as $ccp_id_key) {
            if (isset($this->request->get[$ccp_id_key])) {
                $category_id = (int)$this->request->get[$ccp_id_key];
                break;
            }
        }
        if (!$category_id) {
            $category_id = $this->resolveCategoryIdFromKeyword($language_id);
        }
        $category = $this->getCategory($category_id, $language_id);
        if (!$category) { $this->notFound(); return; }
        $title = $category['meta_title'] ? $category['meta_title'] : $category['name'];
        $this->document->setTitle($title);
        if (!empty($category['meta_description'])) { $this->document->setDescription($category['meta_description']); }
        if (!empty($category['meta_keyword'])) { $this->document->setKeywords($category['meta_keyword']); }
        if ((int)$this->config->get('theme_codecart_pro_blog_canonical_status')) { $this->document->addLink($this->blogUrl('extension/module/codecart_pro_blog/category', 'ccp_category_id=' . (int)$category_id), 'canonical'); }
        $data = $this->baseListData($language_id, $category['name'], html_entity_decode($category['description'], ENT_QUOTES, 'UTF-8'));
        $data['seo_text'] = html_entity_decode(isset($category['seo_text']) ? $category['seo_text'] : '', ENT_QUOTES, 'UTF-8');
        $data['active_category_id'] = $category_id;
        $data['active_tag'] = '';
        $data['breadcrumbs'][] = array('text' => $category['name'], 'href' => $this->blogUrl('extension/module/codecart_pro_blog/category', 'ccp_category_id=' . (int)$category_id));
        $data['articles'] = $this->getArticles($language_id, $category_id, '');
        $this->common($data);
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_blog', $data));
    }

    public function article() {
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_blog_status')) { $this->notFound(); return; }
        $this->load->language('extension/module/codecart_pro_blog');
        $this->load->model('tool/image');
        $language_id = (int)$this->config->get('config_language_id');
        $article_id = 0;
        foreach (array('ccp_article_id', 'ccp_blog_article_id', 'blog_article_id', 'article_id', 'codecart_pro_article_id') as $ccp_id_key) {
            if (isset($this->request->get[$ccp_id_key])) {
                $article_id = (int)$this->request->get[$ccp_id_key];
                break;
            }
        }
        if (!$article_id) {
            $article_id = $this->resolveArticleIdFromKeyword($language_id);
        }
        $article = $this->getArticle($article_id, $language_id);
        if (!$article) { $this->notFound(); return; }
        $blog_title = $this->getLang('theme_codecart_pro_blog_title', $language_id, $this->language->get('heading_title'));
        $page_title = $article['meta_title'] ? $article['meta_title'] : $article['title'];
        $this->document->setTitle($page_title);
        if (!empty($article['meta_description'])) { $this->document->setDescription($article['meta_description']); }
        if (!empty($article['meta_keyword'])) { $this->document->setKeywords($article['meta_keyword']); }
        if ((int)$this->config->get('theme_codecart_pro_blog_canonical_status')) { $this->document->addLink($this->blogUrl('extension/module/codecart_pro_blog/article', 'ccp_article_id=' . (int)$article_id), 'canonical'); }
        $this->document->addLink($this->blogUrl('extension/module/codecart_pro_blog'), 'up');
        $data = array();
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home')),
            array('text' => $blog_title, 'href' => $this->blogUrl('extension/module/codecart_pro_blog')),
            array('text' => $article['title'], 'href' => $this->blogUrl('extension/module/codecart_pro_blog/article', 'ccp_article_id=' . (int)$article_id))
        );
        $data['article'] = $article;
        $data['heading_title'] = $article['title'];
        $data['image'] = $this->fitCacheImage(isset($article['image']) ? $article['image'] : '', 1200, 675);
        $data['date_added'] = date($this->language->get('date_format_short'), strtotime($article['date_added']));
        $data['description'] = html_entity_decode($article['description'], ENT_QUOTES, 'UTF-8');
        $data['seo_text'] = html_entity_decode(isset($article['seo_text']) ? $article['seo_text'] : '', ENT_QUOTES, 'UTF-8');
        $data['categories'] = $this->getArticleCategories($article_id, $language_id);
        $data['tags'] = $this->tagsToLinks(isset($article['tags']) ? $article['tags'] : '');
        $data['related_products'] = $this->getRelatedProducts($article_id);
        $data['related_articles'] = $this->getRelatedArticles($article_id, $language_id);
        $data['text_related_products'] = $this->language->get('text_related_products');
        $data['text_related_articles'] = $this->language->get('text_related_articles');
        $data['text_categories'] = $this->language->get('text_categories');
        $data['text_tags'] = $this->language->get('text_tags');
        $this->common($data);
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_blog_article', $data));
    }

    public function homeBlock($setting = array()) {
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_blog_status')) { return ''; }

        $this->load->language('extension/module/codecart_pro_blog');
        $this->load->model('tool/image');

        $language_id = (int)$this->config->get('config_language_id');
        $limit = isset($setting['limit']) ? (int)$setting['limit'] : 5;
        $limit = min(5, max(1, $limit));

        $data = array();
        $data['heading_title'] = $this->language->get('text_latest_articles') != 'text_latest_articles' ? $this->language->get('text_latest_articles') : $this->language->get('heading_title');
        $data['text_read_more'] = $this->language->get('text_read_more');
        $data['blog_href'] = $this->blogUrl('extension/module/codecart_pro_blog');
        $data['articles'] = $this->getArticles($language_id, 0, '', $limit);

        if (!$data['articles']) { return ''; }

        return $this->load->view('extension/module/codecart_pro_blog_home', $data);
    }

    public function productBlock($args = array()) {
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_blog_status')) { return ''; }
        $product_id = 0;
        if (is_array($args) && !empty($args['product_id'])) { $product_id = (int)$args['product_id']; }
        if (!$product_id && isset($this->request->get['product_id'])) { $product_id = (int)$this->request->get['product_id']; }
        if (!$product_id) { return ''; }

        try {
            $this->load->language('extension/module/codecart_pro_blog');
            $this->load->model('tool/image');
            $language_id = (int)$this->config->get('config_language_id');
            $rows = array();
            $seen = array();
            $date_where = $this->articleDateAvailableWhere('a');
            $title_select = $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'title', '', 'title');
            $description_select = $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'description', '', 'description');

            if ($this->tableExists('codecart_pro_blog_article_to_product')) {
                $q = $this->db->query("SELECT a.article_id, a.image, a.date_added, " . $title_select . ", " . $description_select . " FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_product` a2p INNER JOIN `" . DB_PREFIX . "codecart_pro_blog_article` a ON (a2p.article_id = a.article_id) LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') WHERE a2p.product_id = '" . (int)$product_id . "' AND a.status = '1'" . $date_where . " ORDER BY " . $this->articleOrderBy('a') . " LIMIT 6");
                foreach ($q->rows as $row) {
                    $seen[(int)$row['article_id']] = true;
                    $rows[] = $row;
                }
            }

            if (count($rows) < 6 && $this->tableExists('codecart_pro_blog_category_to_store_category') && $this->tableExists('codecart_pro_blog_article_to_category')) {
                $exclude = array_keys($seen);
                $exclude_sql = $exclude ? " AND a.article_id NOT IN (" . implode(',', array_map('intval', $exclude)) . ")" : '';
                $limit = 6 - count($rows);
                $q = $this->db->query("SELECT DISTINCT a.article_id, a.image, a.date_added, " . $title_select . ", " . $description_select . " FROM `" . DB_PREFIX . "product_to_category` p2c INNER JOIN `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` b2sc ON (p2c.category_id = b2sc.category_id) INNER JOIN `" . DB_PREFIX . "codecart_pro_blog_article_to_category` a2c ON (b2sc.blog_category_id = a2c.category_id) INNER JOIN `" . DB_PREFIX . "codecart_pro_blog_article` a ON (a2c.article_id = a.article_id) LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') WHERE p2c.product_id = '" . (int)$product_id . "' AND a.status = '1'" . $date_where . $exclude_sql . " ORDER BY " . $this->articleOrderBy('a') . " LIMIT " . (int)$limit);
                foreach ($q->rows as $row) {
                    $seen[(int)$row['article_id']] = true;
                    $rows[] = $row;
                }
            }

            if (!$rows) { return ''; }
            $data = array('heading_title' => $this->language->get('text_product_articles'), 'articles' => array());
            foreach ($rows as $row) {
                $data['articles'][] = array(
                    'title' => isset($row['title']) ? $row['title'] : '',
                    'description' => utf8_substr(strip_tags(html_entity_decode(isset($row['description']) ? $row['description'] : '', ENT_QUOTES, 'UTF-8')), 0, 140),
                    'thumb' => $this->fitCacheImage(isset($row['image']) ? $row['image'] : '', 640, 360),
                    'href' => $this->blogUrl('extension/module/codecart_pro_blog/article', 'ccp_article_id=' . (int)$row['article_id'])
                );
            }
            return $this->load->view('extension/module/codecart_pro_blog_product_block', $data);
        } catch (Exception $e) {
            $this->log->write('CodeCart PRO Blog productBlock error: ' . $e->getMessage());
            return '';
        } catch (Throwable $e) {
            $this->log->write('CodeCart PRO Blog productBlock error: ' . $e->getMessage());
            return '';
        }
    }


    private function baseListData($language_id, $title, $description) {
        $data = array();
        $data['heading_title'] = $title;
        $data['description'] = $description;
        $data['seo_text'] = '';
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home')),
            array('text' => $this->getLang('theme_codecart_pro_blog_title', $language_id, $this->language->get('heading_title')), 'href' => $this->blogUrl('extension/module/codecart_pro_blog'))
        );
        $data['categories'] = $this->getCategories($language_id);
        $data['text_categories'] = $this->language->get('text_categories');
        $data['text_all_articles'] = $this->language->get('text_all_articles');
        $data['blog_href'] = $this->blogUrl('extension/module/codecart_pro_blog');
        return $data;
    }

    private function common(&$data) {
        $ccp_schema_version = (int)$this->config->get('theme_codecart_pro_schema_version');
        $data['ccp_information_mode'] = ($ccp_schema_version < 852) ? 1 : (int)$this->config->get('theme_codecart_pro_information_mode');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
    }


    private function resolveArticleIdFromKeyword($language_id) {
        if (!$this->tableExists('codecart_pro_blog_article') || !$this->tableExists('codecart_pro_blog_article_description')) { return 0; }
        $keyword = $this->getCurrentKeyword();
        if ($keyword === '') { return 0; }
        $conditions = array();
        if ($this->columnExists('codecart_pro_blog_article', 'keyword')) { $conditions[] = "a.keyword = '" . $this->db->escape($keyword) . "'"; }
        if ($this->columnExists('codecart_pro_blog_article_description', 'keyword')) { $conditions[] = "ad.keyword = '" . $this->db->escape($keyword) . "'"; }
        if (!$conditions) { return 0; }
        $query = $this->db->query("SELECT a.article_id FROM `" . DB_PREFIX . "codecart_pro_blog_article` a LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') WHERE a.status = '1'" . $this->articleDateAvailableWhere('a') . " AND (" . implode(' OR ', $conditions) . ") LIMIT 1");
        return $query->num_rows ? (int)$query->row['article_id'] : 0;
    }


    private function resolveCategoryIdFromKeyword($language_id) {
        if (!$this->tableExists('codecart_pro_blog_category') || !$this->tableExists('codecart_pro_blog_category_description')) { return 0; }
        $keyword = $this->getCurrentKeyword();
        if ($keyword === '') { return 0; }
        $conditions = array();
        if ($this->columnExists('codecart_pro_blog_category', 'keyword')) { $conditions[] = "c.keyword = '" . $this->db->escape($keyword) . "'"; }
        if ($this->columnExists('codecart_pro_blog_category_description', 'keyword')) { $conditions[] = "cd.keyword = '" . $this->db->escape($keyword) . "'"; }
        if (!$conditions) { return 0; }
        $query = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "codecart_pro_blog_category` c LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') WHERE c.status = '1' AND (" . implode(' OR ', $conditions) . ") LIMIT 1");
        return $query->num_rows ? (int)$query->row['category_id'] : 0;
    }


    private function getCurrentKeyword() {
        $route = '';
        if (isset($this->request->get['_route_'])) {
            $route = (string)$this->request->get['_route_'];
        } elseif (!empty($this->request->server['REQUEST_URI'])) {
            $route = parse_url($this->request->server['REQUEST_URI'], PHP_URL_PATH);
        }

        $route = trim((string)$route, '/');
        if ($route === '') { return ''; }
        $parts = explode('/', $route);
        return trim((string)end($parts));
    }

    private function getArticle($article_id, $language_id) {
        if (!$this->tableExists('codecart_pro_blog_article') || !$this->tableExists('codecart_pro_blog_article_description')) { return array(); }
        $select = array(
            'a.*',
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'title', '', 'title'),
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'meta_title', '', 'meta_title'),
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'meta_description', '', 'meta_description'),
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'meta_keyword', '', 'meta_keyword'),
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'description', '', 'description'),
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'seo_text', '', 'seo_text'),
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'tags', '', 'tags')
        );
        $query = $this->db->query("SELECT " . implode(', ', $select) . " FROM `" . DB_PREFIX . "codecart_pro_blog_article` a LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') WHERE a.article_id = '" . (int)$article_id . "' AND a.status = '1'" . $this->articleDateAvailableWhere('a') . " LIMIT 1");
        return $query->num_rows ? $query->row : array();
    }



    private function normalizeImagePath($filename) {
        $filename = html_entity_decode(trim((string)$filename), ENT_QUOTES, 'UTF-8');
        $filename = str_replace('\\', '/', $filename);

        if ($filename === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $filename)) {
            $path = parse_url($filename, PHP_URL_PATH);
            $filename = is_string($path) ? $path : '';
        }

        $filename = rawurldecode((string)$filename);
        $filename = preg_replace('~[?#].*$~', '', $filename);
        $filename = ltrim($filename, '/');

        if (strpos($filename, 'image/') === 0) {
            $filename = substr($filename, 6);
        }

        if ($filename === '' || strpos($filename, "\0") !== false || preg_match('~(^|/)\.\.(/|$)~', $filename)) {
            return '';
        }

        return $filename;
    }

    private function fitCacheImage($filename, $max_width, $max_height) {
        $filename = $this->normalizeImagePath($filename);
        $max_width = max(1, (int)$max_width);
        $max_height = max(1, (int)$max_height);

        if ($filename === '' || !is_file(DIR_IMAGE . $filename)) {
            $filename = 'placeholder.png';
        }

        return $this->model_tool_image->resize($filename, $max_width, $max_height);
    }

    private function getCategory($category_id, $language_id) {
        if (!$this->tableExists('codecart_pro_blog_category')) { return array(); }
        $select = array(
            'c.*',
            $this->selectColumn('codecart_pro_blog_category_description', 'cd', 'name', '', 'name'),
            $this->selectColumn('codecart_pro_blog_category_description', 'cd', 'meta_title', '', 'meta_title'),
            $this->selectColumn('codecart_pro_blog_category_description', 'cd', 'meta_description', '', 'meta_description'),
            $this->selectColumn('codecart_pro_blog_category_description', 'cd', 'meta_keyword', '', 'meta_keyword'),
            $this->selectColumn('codecart_pro_blog_category_description', 'cd', 'description', '', 'description'),
            $this->selectColumn('codecart_pro_blog_category_description', 'cd', 'seo_text', '', 'seo_text')
        );
        $query = $this->db->query("SELECT " . implode(', ', $select) . " FROM `" . DB_PREFIX . "codecart_pro_blog_category` c LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') WHERE c.category_id = '" . (int)$category_id . "' AND c.status = '1' LIMIT 1");
        return $query->num_rows ? $query->row : array();
    }


    private function getArticles($language_id, $category_id = 0, $tag = '', $limit_override = 0) {
        if (!$this->tableExists('codecart_pro_blog_article') || !$this->tableExists('codecart_pro_blog_article_description')) { return array(); }
        $limit = $limit_override ? min(5, max(1, (int)$limit_override)) : min(50, max(1, (int)$this->config->get('theme_codecart_pro_blog_limit')));
        $join = '';
        $where = " WHERE a.status = '1'" . $this->articleDateAvailableWhere('a');
        if ($category_id) { $join .= " INNER JOIN `" . DB_PREFIX . "codecart_pro_blog_article_to_category` a2c ON (a.article_id = a2c.article_id AND a2c.category_id = '" . (int)$category_id . "')"; }
        if ($tag && $this->columnExists('codecart_pro_blog_article_description', 'tags')) { $where .= " AND ad.tags LIKE '%" . $this->db->escape($tag) . "%'"; }
        $select = array(
            'a.article_id',
            'a.image',
            'a.date_added',
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'title', '', 'title'),
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'description', '', 'description')
        );
        $query = $this->db->query("SELECT " . implode(', ', $select) . " FROM `" . DB_PREFIX . "codecart_pro_blog_article` a" . $join . " LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "')" . $where . " ORDER BY " . $this->articleOrderBy('a') . " LIMIT " . (int)$limit);
        $rows = array();
        foreach ($query->rows as $row) {
            if (empty($row['title'])) {
                $fallback_query = $this->db->query("SELECT title, description FROM `" . DB_PREFIX . "codecart_pro_blog_article_description` WHERE article_id = '" . (int)$row['article_id'] . "' AND title <> '' ORDER BY language_id ASC LIMIT 1");
                if ($fallback_query->num_rows) {
                    $row['title'] = $fallback_query->row['title'];
                    $row['description'] = $fallback_query->row['description'];
                }
            }
            if (empty($row['title'])) { continue; }
            $desc = utf8_substr(strip_tags(html_entity_decode(isset($row['description']) ? $row['description'] : '', ENT_QUOTES, 'UTF-8')), 0, 220);
            $rows[] = array('title' => (string)$row['title'], 'description' => $desc, 'date_added' => date($this->language->get('date_format_short'), strtotime($row['date_added'])), 'thumb' => $this->fitCacheImage(isset($row['image']) ? $row['image'] : '', 640, 360), 'href' => $this->blogUrl('extension/module/codecart_pro_blog/article', 'ccp_article_id=' . (int)$row['article_id']));
        }
        return $rows;
    }


    private function getCategories($language_id) {
        $rows = array();
        if (!$this->tableExists('codecart_pro_blog_category')) { return $rows; }
        $q = $this->db->query("SELECT c.category_id, cd.name FROM `" . DB_PREFIX . "codecart_pro_blog_category` c LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') WHERE c.status = '1' ORDER BY " . $this->categoryOrderBy('c', 'cd'));
        foreach ($q->rows as $row) { $rows[] = array('category_id' => (int)$row['category_id'], 'name' => $row['name'], 'href' => $this->blogUrl('extension/module/codecart_pro_blog/category', 'ccp_category_id=' . (int)$row['category_id'])); }
        return $rows;
    }

    private function getArticleCategories($article_id, $language_id) {
        $rows = array();
        if (!$this->tableExists('codecart_pro_blog_article_to_category')) { return $rows; }
        $q = $this->db->query("SELECT c.category_id, cd.name FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_category` a2c INNER JOIN `" . DB_PREFIX . "codecart_pro_blog_category` c ON (a2c.category_id = c.category_id) LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') WHERE a2c.article_id = '" . (int)$article_id . "' AND c.status = '1' ORDER BY " . $this->categoryOrderBy('c', 'cd'));
        foreach ($q->rows as $row) { $rows[] = array('name' => $row['name'], 'href' => $this->blogUrl('extension/module/codecart_pro_blog/category', 'ccp_category_id=' . (int)$row['category_id'])); }
        return $rows;
    }

    private function getRelatedProducts($article_id) {
        $products = array();
        if (!$this->tableExists('codecart_pro_blog_article_to_product')) { return $products; }
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $q = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_product` WHERE article_id = '" . (int)$article_id . "' LIMIT 12");
        foreach ($q->rows as $row) {
            $product_info = $this->model_catalog_product->getProduct((int)$row['product_id']);
            if ($product_info) {
                $image = $product_info['image'] ? $this->fitCacheImage($product_info['image'], 300, 191) : $this->model_tool_image->resize('placeholder.png', 300, 191);
                $price = $this->customer->isLogged() || !$this->config->get('config_customer_price') ? $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']) : false;
                $products[] = array('product_id' => (int)$product_info['product_id'], 'name' => $product_info['name'], 'thumb' => $image, 'price' => $price, 'href' => $this->url->link('product/product', 'product_id=' . (int)$product_info['product_id']));
            }
        }
        return $products;
    }

    private function getRelatedArticles($article_id, $language_id) {
        $articles = array();
        if (!$this->tableExists('codecart_pro_blog_article_related')) { return $articles; }
        $select = array(
            'a.article_id',
            'a.image',
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'title', '', 'title'),
            $this->selectColumn('codecart_pro_blog_article_description', 'ad', 'description', '', 'description')
        );
        $q = $this->db->query("SELECT " . implode(', ', $select) . " FROM `" . DB_PREFIX . "codecart_pro_blog_article_related` ar INNER JOIN `" . DB_PREFIX . "codecart_pro_blog_article` a ON (ar.related_article_id = a.article_id) LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') WHERE ar.article_id = '" . (int)$article_id . "' AND a.status = '1'" . $this->articleDateAvailableWhere('a') . " ORDER BY " . $this->articleOrderBy('a') . " LIMIT 6");
        foreach ($q->rows as $row) { $articles[] = array('title' => isset($row['title']) ? $row['title'] : '', 'description' => utf8_substr(strip_tags(html_entity_decode(isset($row['description']) ? $row['description'] : '', ENT_QUOTES, 'UTF-8')), 0, 160), 'thumb' => $this->fitCacheImage(isset($row['image']) ? $row['image'] : '', 640, 360), 'href' => $this->blogUrl('extension/module/codecart_pro_blog/article', 'ccp_article_id=' . (int)$row['article_id'])); }
        return $articles;
    }


    private function tagsToLinks($tags) {
        $out = array();
        foreach (explode(',', (string)$tags) as $tag) { $tag = trim($tag); if ($tag !== '') { $out[] = array('text' => $tag, 'href' => $this->blogUrl('extension/module/codecart_pro_blog', 'tag=' . urlencode($tag))); } }
        return $out;
    }

    private function articleOrderBy($alias = 'a') {
        $order = array();
        if ($this->columnExists('codecart_pro_blog_article', 'sort_order')) {
            $order[] = $alias . ".`sort_order` ASC";
        }
        if ($this->columnExists('codecart_pro_blog_article', 'date_added')) {
            $order[] = $alias . ".`date_added` DESC";
        } else {
            $order[] = $alias . ".`article_id` DESC";
        }
        return implode(', ', $order);
    }

    private function categoryOrderBy($alias = 'c', $name_alias = 'cd') {
        $order = array();
        if ($this->columnExists('codecart_pro_blog_category', 'sort_order')) {
            $order[] = $alias . ".`sort_order` ASC";
        }
        $order[] = $name_alias . ".`name` ASC";
        return implode(', ', $order);
    }

    private function articleDateAvailableWhere($alias = 'a') {
        return $this->columnExists('codecart_pro_blog_article', 'date_available') ? " AND (" . $alias . ".`date_available` IS NULL OR " . $alias . ".`date_available` = '0000-00-00' OR " . $alias . ".`date_available` <= CURDATE())" : '';
    }

    private function selectColumn($table, $alias, $column, $fallback = '', $as = '') {
        $as = $as ? $as : $column;
        if ($this->columnExists($table, $column)) {
            return $alias . ".`" . $column . "` AS `" . $as . "`";
        }
        return "'" . $this->db->escape((string)$fallback) . "' AS `" . $as . "`";
    }

    private function columnExists($table, $column) {
        static $cache = array();
        $key = $table . '.' . $column;
        if (isset($cache[$key])) { return $cache[$key]; }
        if (!$this->tableExists($table)) { $cache[$key] = false; return false; }
        $q = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $this->db->escape($table) . "` LIKE '" . $this->db->escape($column) . "'");
        $cache[$key] = (bool)$q->num_rows;
        return $cache[$key];
    }

    private function getLang($key, $language_id, $fallback) { $value = $this->config->get($key); return is_array($value) && !empty($value[$language_id]) ? $value[$language_id] : $fallback; }
    private function tableExists($table) { $q = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'"); return (bool)$q->num_rows; }
}
