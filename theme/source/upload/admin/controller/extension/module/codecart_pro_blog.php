<?php
class ControllerExtensionModuleCodecartProBlog extends Controller {
    private $error = array();

    private function hasValidUserToken() {
        $expected = isset($this->session->data['user_token']) ? (string)$this->session->data['user_token'] : '';
        $provided = '';
        if (isset($this->request->get['user_token'])) { $provided = (string)$this->request->get['user_token']; }
        elseif (isset($this->request->post['user_token'])) { $provided = (string)$this->request->post['user_token']; }
        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    public function index() {
        $this->load->language('extension/module/codecart_pro_blog');
        $this->document->setTitle($this->language->get('heading_title_text'));
        $this->document->addStyle('view/stylesheet/codecart_pro/theme_admin.css?v=8.7.7');
        if (!$this->checkAccess('access')) { return; }
        $this->ensureTables();
        $this->load->model('tool/image');
        $this->load->model('localisation/language');

        $data = $this->baseData();
        $language_id = (int)$this->config->get('config_language_id');
        $page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
        $limit = (int)$this->config->get('config_admin_limit');
        if ($limit < 1) { $limit = 20; }

        $filters = $this->articleFilters();
        $filter_url = $this->articleFilterUrl($filters);

        $data['filter_title'] = $filters['filter_title'];
        $data['filter_keyword'] = $filters['filter_keyword'];
        $data['filter_status'] = $filters['filter_status'];
        $data['filter_category_id'] = $filters['filter_category_id'];
        $data['filter_date_from'] = $filters['filter_date_from'];
        $data['filter_date_to'] = $filters['filter_date_to'];
        $data['clear_filter'] = $this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true);

        $data['add'] = $this->url->link('extension/module/codecart_pro_blog/form', 'user_token=' . $this->session->data['user_token'], true);
        $data['delete'] = $this->url->link('extension/module/codecart_pro_blog/delete', 'user_token=' . $this->session->data['user_token'], true);
        $data['bulk'] = $this->url->link('extension/module/codecart_pro_blog/bulkAction', 'user_token=' . $this->session->data['user_token'], true);
        $data['export'] = $this->url->link('extension/module/codecart_pro_blog/exportCsv', 'user_token=' . $this->session->data['user_token'] . $filter_url, true);
        $data['import'] = $this->url->link('extension/module/codecart_pro_blog/importCsv', 'user_token=' . $this->session->data['user_token'], true);
        $data['regenerate_seo'] = $this->url->link('extension/module/codecart_pro_blog/regenerateAllSeo', 'user_token=' . $this->session->data['user_token'], true);
        $data['seo_audit'] = $this->url->link('extension/module/codecart_pro_blog/seoAudit', 'user_token=' . $this->session->data['user_token'], true);
        $data['categories_all'] = $this->getCategoriesForForm($language_id);
        $data['categories'] = $this->url->link('extension/module/codecart_pro_blog/categories', 'user_token=' . $this->session->data['user_token'], true);
        $data['back'] = $this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true);
        $data['articles'] = array();

        $where = $this->articleWhereSql($filters, $language_id);
        $total_query = $this->db->query("SELECT COUNT(DISTINCT a.article_id) AS total FROM `" . DB_PREFIX . "codecart_pro_blog_article` a LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') " . $where['join'] . " WHERE " . implode(' AND ', $where['where']));
        $article_total = (int)$total_query->row['total'];

        $summary_query = $this->db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = '1' AND date_available <= CURDATE() THEN 1 ELSE 0 END) AS published, SUM(CASE WHEN status = '0' THEN 1 ELSE 0 END) AS drafts, SUM(CASE WHEN status = '1' AND date_available > CURDATE() THEN 1 ELSE 0 END) AS scheduled FROM `" . DB_PREFIX . "codecart_pro_blog_article`");
        $category_count_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "codecart_pro_blog_category`");
        $data['summary'] = array(
            'total' => (int)$summary_query->row['total'],
            'published' => (int)$summary_query->row['published'],
            'drafts' => (int)$summary_query->row['drafts'],
            'scheduled' => (int)$summary_query->row['scheduled'],
            'categories' => (int)$category_count_query->row['total']
        );

        $query = $this->db->query("SELECT DISTINCT a.*, ad.title, ad.keyword AS language_keyword FROM `" . DB_PREFIX . "codecart_pro_blog_article` a LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') " . $where['join'] . " WHERE " . implode(' AND ', $where['where']) . " ORDER BY a.sort_order ASC, a.article_id DESC LIMIT " . (int)(($page - 1) * $limit) . "," . (int)$limit);
        $article_ids = array();
        foreach ($query->rows as $row) { $article_ids[] = (int)$row['article_id']; }
        $article_categories = $this->getArticleCategoryNamesBatch($article_ids, $language_id);
        foreach ($query->rows as $row) {
            $data['articles'][] = array(
                'article_id' => (int)$row['article_id'],
                'title' => $this->csvSafe($row['title']) ? $row['title'] : $this->language->get('text_no_title'),
                'status' => (int)$row['status'],
                'sort_order' => (int)$row['sort_order'],
                'keyword' => !empty($row['language_keyword']) ? $row['language_keyword'] : $row['keyword'],
                'date_available' => $this->csvSafe($row['date_available']),
                'date_added' => $row['date_added'],
                'categories' => isset($article_categories[(int)$row['article_id']]) ? $article_categories[(int)$row['article_id']] : '',
                'thumb' => $this->csvSafe($row['image']) && is_file(DIR_IMAGE . $row['image']) ? $this->model_tool_image->resize($row['image'], 80, 80) : $this->model_tool_image->resize('no_image.png', 80, 80),
                'edit' => $this->url->link('extension/module/codecart_pro_blog/form', 'user_token=' . $this->session->data['user_token'] . '&article_id=' . (int)$row['article_id'], true),
                'view' => HTTP_CATALOG . 'index.php?route=extension/module/codecart_pro_blog/article&ccp_article_id=' . (int)$row['article_id']
            );
        }

        $pagination = new Pagination();
        $pagination->total = $article_total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'] . $filter_url . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination_results'), ($article_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($article_total - $limit)) ? $article_total : ((($page - 1) * $limit) + $limit), $article_total, ceil($article_total / $limit));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_blog_list', $data));
    }

        public function categories() {
        $this->load->language('extension/module/codecart_pro_blog');
        $this->document->setTitle($this->language->get('heading_category_title_text'));
        $this->document->addStyle('view/stylesheet/codecart_pro/theme_admin.css?v=8.7.7');
        if (!$this->checkAccess('access')) { return; }
        $this->ensureTables();
        $this->load->model('tool/image');
        $this->load->model('localisation/language');

        $data = $this->baseData();
        $language_id = (int)$this->config->get('config_language_id');
        $page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
        $limit = (int)$this->config->get('config_admin_limit');
        if ($limit < 1) { $limit = 20; }

        $filters = $this->categoryFilters();
        $filter_url = $this->categoryFilterUrl($filters);

        $data['filter_name'] = $filters['filter_name'];
        $data['filter_keyword'] = $filters['filter_keyword'];
        $data['filter_status'] = $filters['filter_status'];
        $data['filter_store_category_id'] = $filters['filter_store_category_id'];
        $data['filter_store_category_name'] = $filters['filter_store_category_name'];
        $data['clear_filter'] = $this->url->link('extension/module/codecart_pro_blog/categories', 'user_token=' . $this->session->data['user_token'], true);

        $data['add'] = $this->url->link('extension/module/codecart_pro_blog/categoryForm', 'user_token=' . $this->session->data['user_token'], true);
        $data['delete'] = $this->url->link('extension/module/codecart_pro_blog/categoryDelete', 'user_token=' . $this->session->data['user_token'], true);
        $data['bulk'] = $this->url->link('extension/module/codecart_pro_blog/categoryBulkAction', 'user_token=' . $this->session->data['user_token'], true);
        $data['export'] = $this->url->link('extension/module/codecart_pro_blog/exportCategoriesCsv', 'user_token=' . $this->session->data['user_token'] . $filter_url, true);
        $data['import'] = $this->url->link('extension/module/codecart_pro_blog/importCategoriesCsv', 'user_token=' . $this->session->data['user_token'], true);
        $data['regenerate_seo'] = $this->url->link('extension/module/codecart_pro_blog/regenerateAllSeo', 'user_token=' . $this->session->data['user_token'], true);
        $data['seo_audit'] = $this->url->link('extension/module/codecart_pro_blog/seoAudit', 'user_token=' . $this->session->data['user_token'], true);
        $data['back'] = $this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true);
        $data['categories'] = array();

        $where = $this->categoryWhereSql($filters, $language_id);
        $total_query = $this->db->query("SELECT COUNT(DISTINCT c.category_id) AS total FROM `" . DB_PREFIX . "codecart_pro_blog_category` c LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') " . $where['join'] . " WHERE " . implode(' AND ', $where['where']));
        $category_total = (int)$total_query->row['total'];

        $query = $this->db->query("SELECT DISTINCT c.*, cd.name, cd.keyword AS language_keyword FROM `" . DB_PREFIX . "codecart_pro_blog_category` c LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') " . $where['join'] . " WHERE " . implode(' AND ', $where['where']) . " ORDER BY c.sort_order ASC, c.category_id DESC LIMIT " . (int)(($page - 1) * $limit) . "," . (int)$limit);
        foreach ($query->rows as $row) {
            $data['categories'][] = array(
                'category_id' => (int)$row['category_id'],
                'name' => $row['name'] ? $row['name'] : $this->language->get('text_no_title'),
                'status' => (int)$row['status'],
                'sort_order' => (int)$row['sort_order'],
                'keyword' => !empty($row['language_keyword']) ? $row['language_keyword'] : $row['keyword'],
                'thumb' => $row['image'] && is_file(DIR_IMAGE . $row['image']) ? $this->model_tool_image->resize($row['image'], 80, 80) : $this->model_tool_image->resize('no_image.png', 80, 80),
                'edit' => $this->url->link('extension/module/codecart_pro_blog/categoryForm', 'user_token=' . $this->session->data['user_token'] . '&category_id=' . (int)$row['category_id'], true),
                'view' => HTTP_CATALOG . 'index.php?route=extension/module/codecart_pro_blog/category&ccp_category_id=' . (int)$row['category_id']
            );
        }

        $pagination = new Pagination();
        $pagination->total = $category_total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('extension/module/codecart_pro_blog/categories', 'user_token=' . $this->session->data['user_token'] . $filter_url . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination_results'), ($category_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($category_total - $limit)) ? $category_total : ((($page - 1) * $limit) + $limit), $category_total, ceil($category_total / $limit));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_blog_category_list', $data));
    }

        public function categoryForm() {
        $this->load->language('extension/module/codecart_pro_blog');
        $this->document->setTitle($this->language->get('heading_category_title_text'));
        $this->document->addStyle('view/stylesheet/codecart_pro/theme_admin.css?v=8.7.7');
        $this->document->addStyle('view/javascript/summernote/summernote.css');
        $this->document->addScript('view/javascript/summernote/summernote.js');
        $this->document->addScript('view/javascript/summernote/opencart.js');
        if (!$this->checkAccess('access')) { return; }
        $this->ensureTables();
        $this->load->model('tool/image');
        $this->load->model('localisation/language');

        $category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->saveCategory($category_id, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success_category');
            $this->response->redirect($this->url->link('extension/module/codecart_pro_blog/categories', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $data = $this->baseData();
        $data['summernote'] = (string)$this->config->get('config_admin_language');
        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['category_id'] = $category_id;
        $data['action'] = $this->url->link('extension/module/codecart_pro_blog/categoryForm', 'user_token=' . $this->session->data['user_token'] . ($category_id ? '&category_id=' . $category_id : ''), true);
        $data['cancel'] = $this->url->link('extension/module/codecart_pro_blog/categories', 'user_token=' . $this->session->data['user_token'], true);
        $data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

        $category = array('image' => '', 'keyword' => '', 'status' => 1, 'sort_order' => 0);
        $descriptions = array();
        if ($category_id) {
            $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_category` WHERE category_id = '" . (int)$category_id . "'");
            if ($q->num_rows) { $category = $q->row; }
            $dq = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_category_description` WHERE category_id = '" . (int)$category_id . "'");
            foreach ($dq->rows as $row) { $descriptions[(int)$row['language_id']] = $row; }
        }
        if (isset($this->request->post['category'])) { $category = array_merge($category, $this->request->post['category']); }
        if (isset($this->request->post['description'])) { $descriptions = $this->request->post['description']; }

        $data['category'] = $category;
        $data['descriptions'] = $descriptions;
        $data['store_categories'] = $this->getStoreCategoriesForForm($category_id, (int)$this->config->get('config_language_id'));
        $data['thumb'] = !empty($category['image']) && is_file(DIR_IMAGE . $category['image']) ? $this->model_tool_image->resize($category['image'], 100, 100) : $data['placeholder'];
        $data['user_token'] = $this->session->data['user_token'];
        $data['view'] = $category_id ? HTTP_CATALOG . 'index.php?route=extension/module/codecart_pro_blog/category&ccp_category_id=' . (int)$category_id : '';
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_blog_category_form', $data));
    }

    public function categoryDelete() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('modify')) { return; }
        $this->ensureTables();
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $selected = isset($this->request->post['selected']) && is_array($this->request->post['selected']) ? array_values(array_unique(array_filter(array_map('intval', $this->request->post['selected'])))) : array();
            $selected = array_slice($selected, 0, 500);
            if ($selected) {
                $ids = implode(',', $selected);
                $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_category_description` WHERE category_id IN (" . $ids . ")");
                $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_category` WHERE category_id IN (" . $ids . ")");
                $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` WHERE blog_category_id IN (" . $ids . ")");
                $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query IN ('ccp_category_id=" . implode("','ccp_category_id=", $selected) . "')");
                $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_category` WHERE category_id IN (" . $ids . ")");
                $this->session->data['success'] = $this->language->get('text_success_category_delete');
            }
        }
        $this->response->redirect($this->url->link('extension/module/codecart_pro_blog/categories', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function form() {
        $this->load->language('extension/module/codecart_pro_blog');
        $this->document->setTitle($this->language->get('heading_title_text'));
        $this->document->addStyle('view/stylesheet/codecart_pro/theme_admin.css?v=8.7.7');
        $this->document->addStyle('view/javascript/summernote/summernote.css');
        $this->document->addScript('view/javascript/summernote/summernote.js');
        $this->document->addScript('view/javascript/summernote/opencart.js');
        if (!$this->checkAccess('access')) { return; }
        $this->ensureTables();
        $this->load->model('tool/image');
        $this->load->model('localisation/language');

        $article_id = isset($this->request->get['article_id']) ? (int)$this->request->get['article_id'] : 0;

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $article_id = $this->saveArticle($article_id, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $data = $this->baseData();
        $data['summernote'] = (string)$this->config->get('config_admin_language');
        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['article_id'] = $article_id;
        $data['action'] = $this->url->link('extension/module/codecart_pro_blog/form', 'user_token=' . $this->session->data['user_token'] . ($article_id ? '&article_id=' . $article_id : ''), true);
        $data['cancel'] = $this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true);
        $data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

        $article = array('image' => '', 'keyword' => '', 'status' => 1, 'sort_order' => 0, 'date_available' => date('Y-m-d'));
        $descriptions = array();
        if ($article_id) {
            $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_article` WHERE article_id = '" . (int)$article_id . "'");
            if ($q->num_rows) { $article = $q->row; }
            $dq = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_article_description` WHERE article_id = '" . (int)$article_id . "'");
            foreach ($dq->rows as $row) { $descriptions[(int)$row['language_id']] = $row; }
        }
        if (isset($this->request->post['article'])) { $article = array_merge($article, $this->request->post['article']); }
        if (isset($this->request->post['description'])) { $descriptions = $this->request->post['description']; }

        $data['article'] = $article;
        $data['descriptions'] = $descriptions;
        $data['categories_all'] = $this->getCategoriesForForm((int)$this->config->get('config_language_id'));
        $data['article_categories'] = $this->getArticleCategoryIds($article_id);
        $data['related_product_ids'] = implode(',', $this->getArticleProductIds($article_id));
        $data['related_article_ids'] = implode(',', $this->getArticleRelatedIds($article_id));
        $data['related_products'] = $this->getProductsForForm($this->getArticleProductIds($article_id), (int)$this->config->get('config_language_id'));
        $data['related_articles'] = $this->getArticlesForForm($this->getArticleRelatedIds($article_id), (int)$this->config->get('config_language_id'));
        $data['thumb'] = !empty($article['image']) && is_file(DIR_IMAGE . $article['image']) ? $this->model_tool_image->resize($article['image'], 100, 100) : $data['placeholder'];
        $data['user_token'] = $this->session->data['user_token'];
        $data['view'] = $article_id ? HTTP_CATALOG . 'index.php?route=extension/module/codecart_pro_blog/article&ccp_article_id=' . (int)$article_id : '';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_blog_form', $data));
    }

    public function delete() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('modify')) { return; }
        $this->ensureTables();
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $selected = isset($this->request->post['selected']) && is_array($this->request->post['selected']) ? array_values(array_unique(array_filter(array_map('intval', $this->request->post['selected'])))) : array();
            $selected = array_slice($selected, 0, 500);
            if ($selected) {
                $ids = implode(',', $selected);
                $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_description` WHERE article_id IN (" . $ids . ")");
                $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_category` WHERE article_id IN (" . $ids . ")");
                $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_product` WHERE article_id IN (" . $ids . ")");
                $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_related` WHERE article_id IN (" . $ids . ") OR related_article_id IN (" . $ids . ")");
                $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query IN ('ccp_article_id=" . implode("','ccp_article_id=", $selected) . "')");
                $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article` WHERE article_id IN (" . $ids . ")");
                $this->session->data['success'] = $this->language->get('text_success_delete');
            }
        }
        $this->response->redirect($this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function autocompleteProduct() {
        $this->load->language('extension/module/codecart_pro_blog');
        $json = array();
        if (!$this->user->hasPermission('access', 'extension/module/codecart_pro_blog')) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        $this->ensureTables();
        $filter_name = isset($this->request->get['filter_name']) ? trim($this->request->get['filter_name']) : '';
        if ($filter_name !== '') {
            $language_id = (int)$this->config->get('config_language_id');
            $q = $this->db->query("SELECT p.product_id, pd.name, p.model FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $language_id . "') WHERE (pd.name LIKE '" . $this->db->escape($filter_name) . "%' OR pd.name LIKE '%" . $this->db->escape($filter_name) . "%' OR p.model LIKE '%" . $this->db->escape($filter_name) . "%') ORDER BY pd.name ASC LIMIT 20");
            foreach ($q->rows as $row) {
                $json[] = array(
                    'product_id' => (int)$row['product_id'],
                    'name' => html_entity_decode($row['name'] ? $row['name'] : ('#' . (int)$row['product_id']), ENT_QUOTES, 'UTF-8') . ($row['model'] ? ' [' . $row['model'] . ']' : '')
                );
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function autocompleteArticle() {
        $this->load->language('extension/module/codecart_pro_blog');
        $json = array();
        if (!$this->user->hasPermission('access', 'extension/module/codecart_pro_blog')) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        $this->ensureTables();
        $filter_name = isset($this->request->get['filter_name']) ? trim($this->request->get['filter_name']) : '';
        $exclude_id = isset($this->request->get['article_id']) ? (int)$this->request->get['article_id'] : 0;
        if ($filter_name !== '') {
            $language_id = (int)$this->config->get('config_language_id');
            $sql = "SELECT a.article_id, ad.title FROM `" . DB_PREFIX . "codecart_pro_blog_article` a LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . $language_id . "') WHERE (ad.title LIKE '" . $this->db->escape($filter_name) . "%' OR ad.title LIKE '%" . $this->db->escape($filter_name) . "%')";
            if ($exclude_id) { $sql .= " AND a.article_id <> '" . $exclude_id . "'"; }
            $sql .= " ORDER BY ad.title ASC LIMIT 20";
            $q = $this->db->query($sql);
            foreach ($q->rows as $row) {
                $json[] = array(
                    'article_id' => (int)$row['article_id'],
                    'name' => html_entity_decode($row['title'] ? $row['title'] : ('#' . (int)$row['article_id']), ENT_QUOTES, 'UTF-8')
                );
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function autocompleteStoreCategory() {
        $this->load->language('extension/module/codecart_pro_blog');
        $json = array();
        if (!$this->user->hasPermission('access', 'extension/module/codecart_pro_blog')) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        $this->ensureTables();
        $filter_name = isset($this->request->get['filter_name']) ? trim($this->request->get['filter_name']) : '';
        if ($filter_name !== '') {
            $language_id = (int)$this->config->get('config_language_id');
            $q = $this->db->query("SELECT c.category_id, cd.name FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . $language_id . "') WHERE (cd.name LIKE '" . $this->db->escape($filter_name) . "%' OR cd.name LIKE '%" . $this->db->escape($filter_name) . "%') ORDER BY cd.name ASC LIMIT 20");
            foreach ($q->rows as $row) {
                $json[] = array(
                    'category_id' => (int)$row['category_id'],
                    'name' => html_entity_decode($row['name'] ? $row['name'] : ('#' . (int)$row['category_id']), ENT_QUOTES, 'UTF-8')
                );
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    public function seoAudit() {
        $this->load->language('extension/module/codecart_pro_blog');
        $this->document->setTitle($this->language->get('heading_seo_audit_text'));
        $this->document->addStyle('view/stylesheet/codecart_pro/theme_admin.css?v=8.7.7');
        if (!$this->checkAccess('access')) { return; }
        $this->ensureTables();
        $this->load->model('localisation/language');

        $data = $this->baseData();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_seo_audit_text'),
            'href' => $this->url->link('extension/module/codecart_pro_blog/seoAudit', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['back'] = $this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true);
        $data['categories'] = $this->url->link('extension/module/codecart_pro_blog/categories', 'user_token=' . $this->session->data['user_token'], true);
        $data['regenerate_seo'] = $this->url->link('extension/module/codecart_pro_blog/regenerateAllSeo', 'user_token=' . $this->session->data['user_token'], true);
        $data['autofix_seo'] = $this->url->link('extension/module/codecart_pro_blog/autofixSeo', 'user_token=' . $this->session->data['user_token'], true);
        $data['article_issues'] = $this->getSeoAuditArticleIssues();
        $data['category_issues'] = $this->getSeoAuditCategoryIssues();
        $data['article_summary'] = $this->summarizeAuditIssues($data['article_issues']);
        $data['category_summary'] = $this->summarizeAuditIssues($data['category_issues']);
        $data['total_summary'] = $this->summarizeAuditIssues(array_merge($data['article_issues'], $data['category_issues']));
        $data['audit_limit'] = 500;
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_blog_seo_audit', $data));
    }


    public function autofixSeo() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('modify')) { return; }
        $this->ensureTables();
        $fixed = 0;

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $options = array(
                'articles' => !empty($this->request->post['autofix_articles']),
                'categories' => !empty($this->request->post['autofix_categories']),
                'meta' => !empty($this->request->post['autofix_meta']),
                'keywords' => !empty($this->request->post['autofix_keywords']),
                'duplicates' => !empty($this->request->post['autofix_duplicates']),
                'sync_urls' => !empty($this->request->post['autofix_sync_urls'])
            );

            if ($options['articles']) { $fixed += $this->autofixArticleSeo($options); }
            if ($options['categories']) { $fixed += $this->autofixCategorySeo($options); }

            $this->session->data['success'] = sprintf($this->language->get('text_success_autofix'), $fixed);
        }

        $this->response->redirect($this->url->link('extension/module/codecart_pro_blog/seoAudit', 'user_token=' . $this->session->data['user_token'], true));
    }


    public function bulkAction() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('modify')) { return; }
        $this->ensureTables();
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $selected = isset($this->request->post['selected']) && is_array($this->request->post['selected']) ? array_values(array_unique(array_filter(array_map('intval', $this->request->post['selected'])))) : array();
            $selected = array_slice($selected, 0, 500);
            $action = isset($this->request->post['bulk_action']) ? (string)$this->request->post['bulk_action'] : '';
            $updated = 0;

            if (!empty($this->request->post['sort_order']) && is_array($this->request->post['sort_order'])) {
                $cases = array(); $sort_ids = array();
                foreach ($this->request->post['sort_order'] as $article_id => $sort_order) {
                    $article_id = (int)$article_id;
                    if ($article_id > 0) { $sort_ids[] = $article_id; $cases[] = "WHEN " . $article_id . " THEN " . (int)$sort_order; }
                }
                $sort_ids = array_slice(array_values(array_unique($sort_ids)), 0, 1000);
                if ($sort_ids && $cases) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_article` SET sort_order = CASE article_id " . implode(' ', $cases) . " ELSE sort_order END, date_modified = NOW() WHERE article_id IN (" . implode(',', $sort_ids) . ")");
                    $updated += count($sort_ids);
                }
            }

            if ($selected) {
                $ids = implode(',', $selected);
                if ($action === 'enable' || $action === 'disable') {
                    $status = $action === 'enable' ? 1 : 0;
                    $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_article` SET status = '" . $status . "', date_modified = NOW() WHERE article_id IN (" . $ids . ")");
                    $updated += count($selected);
                } elseif ($action === 'assign_category') {
                    $category_id = isset($this->request->post['bulk_category_id']) ? (int)$this->request->post['bulk_category_id'] : 0;
                    if ($category_id > 0) {
                        $values = array(); foreach ($selected as $id) { $values[] = "('" . (int)$id . "','" . $category_id . "')"; }
                        $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "codecart_pro_blog_article_to_category` (`article_id`,`category_id`) VALUES " . implode(',', $values));
                        $updated += count($selected);
                    }
                } elseif ($action === 'regenerate_seo') {
                    $q = $this->db->query("SELECT article_id, keyword FROM `" . DB_PREFIX . "codecart_pro_blog_article` WHERE article_id IN (" . $ids . ")");
                    foreach ($q->rows as $row) { $this->saveSeoUrl((int)$row['article_id'], (string)$row['keyword']); $updated++; }
                }
            }
            $this->session->data['success'] = sprintf($this->language->get('text_success_bulk'), $updated);
        }
        $this->response->redirect($this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function categoryBulkAction() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('modify')) { return; }
        $this->ensureTables();
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $selected = isset($this->request->post['selected']) && is_array($this->request->post['selected']) ? array_values(array_unique(array_filter(array_map('intval', $this->request->post['selected'])))) : array();
            $selected = array_slice($selected, 0, 500);
            $action = isset($this->request->post['bulk_action']) ? (string)$this->request->post['bulk_action'] : '';
            $updated = 0;
            if (!empty($this->request->post['sort_order']) && is_array($this->request->post['sort_order'])) {
                $cases = array(); $sort_ids = array();
                foreach ($this->request->post['sort_order'] as $category_id => $sort_order) {
                    $category_id = (int)$category_id;
                    if ($category_id > 0) { $sort_ids[] = $category_id; $cases[] = "WHEN " . $category_id . " THEN " . (int)$sort_order; }
                }
                $sort_ids = array_slice(array_values(array_unique($sort_ids)), 0, 1000);
                if ($sort_ids && $cases) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_category` SET sort_order = CASE category_id " . implode(' ', $cases) . " ELSE sort_order END, date_modified = NOW() WHERE category_id IN (" . implode(',', $sort_ids) . ")");
                    $updated += count($sort_ids);
                }
            }
            if ($selected) {
                $ids = implode(',', $selected);
                if ($action === 'enable' || $action === 'disable') {
                    $status = $action === 'enable' ? 1 : 0;
                    $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_category` SET status = '" . $status . "', date_modified = NOW() WHERE category_id IN (" . $ids . ")");
                    $updated += count($selected);
                } elseif ($action === 'regenerate_seo') {
                    $q = $this->db->query("SELECT category_id, keyword FROM `" . DB_PREFIX . "codecart_pro_blog_category` WHERE category_id IN (" . $ids . ")");
                    foreach ($q->rows as $row) { $this->saveCategorySeoUrl((int)$row['category_id'], (string)$row['keyword']); $updated++; }
                }
            }
            $this->session->data['success'] = sprintf($this->language->get('text_success_bulk'), $updated);
        }
        $this->response->redirect($this->url->link('extension/module/codecart_pro_blog/categories', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function regenerateAllSeo() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('modify')) { return; }
        if (!isset($this->request->server['REQUEST_METHOD']) || strtoupper((string)$this->request->server['REQUEST_METHOD']) !== 'POST') {
            $this->response->redirect($this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }
        $this->ensureTables();
        $updated = 0;
        if ($this->validate()) {
            $q = $this->db->query("SELECT article_id, keyword FROM `" . DB_PREFIX . "codecart_pro_blog_article`");
            foreach ($q->rows as $row) {
                $this->saveSeoUrl((int)$row['article_id'], $row['keyword']);
                $updated++;
            }
            $cq = $this->db->query("SELECT category_id, keyword FROM `" . DB_PREFIX . "codecart_pro_blog_category`");
            foreach ($cq->rows as $row) {
                $this->saveCategorySeoUrl((int)$row['category_id'], $row['keyword']);
                $updated++;
            }
            $this->session->data['success'] = sprintf($this->language->get('text_success_seo_regenerated'), $updated);
        }
        $this->response->redirect($this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function exportCsv() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('access')) { return; }
        $this->ensureTables();

        $language_id = (int)$this->config->get('config_language_id');
        $filters = $this->articleFilters();
        $where = $this->articleWhereSql($filters, $language_id);
        $ids = array();
        $id_query = $this->db->query("SELECT DISTINCT a.article_id FROM `" . DB_PREFIX . "codecart_pro_blog_article` a LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') " . $where['join'] . " WHERE " . implode(' AND ', $where['where']) . " ORDER BY a.article_id ASC");
        foreach ($id_query->rows as $row) { $ids[] = (int)$row['article_id']; }

        $out = fopen('php://temp', 'r+');
        fputcsv($out, array('article_id','language_id','title','keyword','meta_title','meta_description','meta_keyword','tags','status','sort_order','date_available','image','category_ids','product_ids','related_article_ids','description','seo_text'));
        if ($ids) {
            $category_map = $this->getRelationMap('codecart_pro_blog_article_to_category', 'article_id', 'category_id', $ids);
            $product_map = $this->getRelationMap('codecart_pro_blog_article_to_product', 'article_id', 'product_id', $ids);
            $related_map = $this->getRelationMap('codecart_pro_blog_article_related', 'article_id', 'related_article_id', $ids);
            $q = $this->db->query("SELECT a.*, ad.language_id, ad.title, ad.keyword AS language_keyword, ad.meta_title, ad.meta_description, ad.meta_keyword, ad.tags, ad.description, ad.seo_text FROM `" . DB_PREFIX . "codecart_pro_blog_article` a LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id) WHERE a.article_id IN (" . implode(',', $ids) . ") ORDER BY a.article_id ASC, ad.language_id ASC");
            foreach ($q->rows as $row) {
                $article_id = (int)$row['article_id'];
                fputcsv($out, array(
                    $article_id,
                    (int)$row['language_id'],
                    $this->csvSafe($row['title']),
                    $this->csvSafe($row['language_keyword'] ? $row['language_keyword'] : $row['keyword']),
                    $this->csvSafe($row['meta_title']),
                    $this->csvSafe($row['meta_description']),
                    $this->csvSafe($row['meta_keyword']),
                    $this->csvSafe($row['tags']),
                    (int)$row['status'],
                    (int)$row['sort_order'],
                    $this->csvSafe($row['date_available']),
                    $this->csvSafe($row['image']),
                    implode('|', isset($category_map[$article_id]) ? $category_map[$article_id] : array()),
                    implode('|', isset($product_map[$article_id]) ? $product_map[$article_id] : array()),
                    implode('|', isset($related_map[$article_id]) ? $related_map[$article_id] : array()),
                    $this->csvSafe($row['description']),
                    $this->csvSafe($row['seo_text'])
                ));
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        $this->response->addHeader('Content-Type: text/csv; charset=utf-8');
        $this->response->addHeader('Content-Disposition: attachment; filename="codecart_blog_articles_filtered_' . date('Y-m-d_H-i') . '.csv"');
        $this->response->setOutput($csv);
    }

    public function exportCategoriesCsv() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('access')) { return; }
        $this->ensureTables();

        $language_id = (int)$this->config->get('config_language_id');
        $filters = $this->categoryFilters();
        $where = $this->categoryWhereSql($filters, $language_id);
        $ids = array();
        $id_query = $this->db->query("SELECT DISTINCT c.category_id FROM `" . DB_PREFIX . "codecart_pro_blog_category` c LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') " . $where['join'] . " WHERE " . implode(' AND ', $where['where']) . " ORDER BY c.category_id ASC");
        foreach ($id_query->rows as $row) { $ids[] = (int)$row['category_id']; }

        $out = fopen('php://temp', 'r+');
        fputcsv($out, array('category_id','language_id','name','keyword','meta_title','meta_description','meta_keyword','status','sort_order','image','store_category_ids','description','seo_text'));
        if ($ids) {
            $store_category_map = $this->getRelationMap('codecart_pro_blog_category_to_store_category', 'blog_category_id', 'category_id', $ids);
            $q = $this->db->query("SELECT c.*, cd.language_id, cd.name, cd.keyword AS language_keyword, cd.meta_title, cd.meta_description, cd.meta_keyword, cd.description, cd.seo_text FROM `" . DB_PREFIX . "codecart_pro_blog_category` c LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (c.category_id = cd.category_id) WHERE c.category_id IN (" . implode(',', $ids) . ") ORDER BY c.category_id ASC, cd.language_id ASC");
            foreach ($q->rows as $row) {
                $category_id = (int)$row['category_id'];
                fputcsv($out, array($category_id, (int)$row['language_id'], $this->csvSafe($row['name']), $this->csvSafe($row['language_keyword'] ? $row['language_keyword'] : $row['keyword']), $this->csvSafe($row['meta_title']), $this->csvSafe($row['meta_description']), $this->csvSafe($row['meta_keyword']), (int)$row['status'], (int)$row['sort_order'], $this->csvSafe($row['image']), implode('|', isset($store_category_map[$category_id]) ? $store_category_map[$category_id] : array()), $this->csvSafe($row['description']), $this->csvSafe($row['seo_text'])));
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        $this->response->addHeader('Content-Type: text/csv; charset=utf-8');
        $this->response->addHeader('Content-Disposition: attachment; filename="codecart_blog_categories_filtered_' . date('Y-m-d_H-i') . '.csv"');
        $this->response->setOutput($csv);
    }

    public function importCsv() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('modify')) { return; }
        $this->ensureTables();

        $result = $this->importCsvFile('article');
        if (!empty($result['error'])) {
            $this->session->data['error_warning'] = $result['error'];
        } else {
            $this->session->data['success'] = sprintf($this->language->get('text_success_import'), (int)$result['count']);
        }

        $this->response->redirect($this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function importCategoriesCsv() {
        $this->load->language('extension/module/codecart_pro_blog');
        if (!$this->checkAccess('modify')) { return; }
        $this->ensureTables();

        $result = $this->importCsvFile('category');
        if (!empty($result['error'])) {
            $this->session->data['error_warning'] = $result['error'];
        } else {
            $this->session->data['success'] = sprintf($this->language->get('text_success_import'), (int)$result['count']);
        }

        $this->response->redirect($this->url->link('extension/module/codecart_pro_blog/categories', 'user_token=' . $this->session->data['user_token'], true));
    }

    private function importCsvFile($type) {
        $result = array('count' => 0, 'error' => '');

        if (!isset($this->request->server['REQUEST_METHOD']) || strtoupper((string)$this->request->server['REQUEST_METHOD']) !== 'POST' || !$this->validate()) {
            $result['error'] = $this->language->get('error_permission');
            return $result;
        }

        if (empty($this->request->files['import_file']['tmp_name']) || !is_uploaded_file($this->request->files['import_file']['tmp_name'])) {
            $result['error'] = $this->language->get('error_import_file');
            return $result;
        }

        $file = $this->request->files['import_file'];
        $size = isset($file['size']) ? (int)$file['size'] : (int)@filesize($file['tmp_name']);
        if ($size <= 0 || $size > 10485760) {
            $result['error'] = $this->language->get('error_import_size');
            return $result;
        }

        $handle = @fopen($file['tmp_name'], 'rb');
        if (!$handle) {
            $result['error'] = $this->language->get('error_import_file');
            return $result;
        }

        $header = fgetcsv($handle);
        $map = $this->csvHeaderMap($header);
        $required = $type === 'category' ? array('language_id', 'name') : array('language_id', 'title');
        foreach ($required as $required_column) {
            if (!isset($map[$required_column])) {
                fclose($handle);
                $result['error'] = $this->language->get('error_import_header');
                return $result;
            }
        }

        $max_rows = 5000;
        $this->db->query("START TRANSACTION");

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (!array_filter($row, 'strlen')) {
                    continue;
                }

                if ($result['count'] >= $max_rows) {
                    throw new RuntimeException($this->language->get('error_import_limit'));
                }

                $entity_id = $type === 'category'
                    ? $this->upsertCategoryFromCsv($row, $map)
                    : $this->upsertArticleFromCsv($row, $map);

                if ($entity_id) {
                    $result['count']++;
                }
            }

            $this->db->query("COMMIT");
        } catch (Throwable $e) {
            $this->db->query("ROLLBACK");
            $result['count'] = 0;
            $result['error'] = $e->getMessage() ? $e->getMessage() : $this->language->get('error_import_file');
        }

        fclose($handle);
        return $result;
    }


    private function articleFilters() {
        return array(
            'filter_title' => isset($this->request->get['filter_title']) ? trim((string)$this->request->get['filter_title']) : '',
            'filter_keyword' => isset($this->request->get['filter_keyword']) ? trim((string)$this->request->get['filter_keyword']) : '',
            'filter_status' => isset($this->request->get['filter_status']) ? trim((string)$this->request->get['filter_status']) : '',
            'filter_category_id' => isset($this->request->get['filter_category_id']) ? (int)$this->request->get['filter_category_id'] : 0,
            'filter_date_from' => isset($this->request->get['filter_date_from']) ? trim((string)$this->request->get['filter_date_from']) : '',
            'filter_date_to' => isset($this->request->get['filter_date_to']) ? trim((string)$this->request->get['filter_date_to']) : ''
        );
    }

    private function categoryFilters() {
        $filter_store_category_id = isset($this->request->get['filter_store_category_id']) ? (int)$this->request->get['filter_store_category_id'] : 0;
        $filter_store_category_name = isset($this->request->get['filter_store_category_name']) ? trim((string)$this->request->get['filter_store_category_name']) : '';
        if ($filter_store_category_id && !$filter_store_category_name) {
            $language_id = (int)$this->config->get('config_language_id');
            $q = $this->db->query("SELECT name FROM `" . DB_PREFIX . "category_description` WHERE category_id = '" . (int)$filter_store_category_id . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");
            if ($q->num_rows) { $filter_store_category_name = $q->row['name']; }
        }
        return array(
            'filter_name' => isset($this->request->get['filter_name']) ? trim((string)$this->request->get['filter_name']) : '',
            'filter_keyword' => isset($this->request->get['filter_keyword']) ? trim((string)$this->request->get['filter_keyword']) : '',
            'filter_status' => isset($this->request->get['filter_status']) ? trim((string)$this->request->get['filter_status']) : '',
            'filter_store_category_id' => $filter_store_category_id,
            'filter_store_category_name' => $filter_store_category_name
        );
    }

    private function articleWhereSql($filters, $language_id) {
        $join = '';
        $where = array('1 = 1');
        if (!empty($filters['filter_title'])) {
            $value = $this->db->escape($filters['filter_title']);
            $where[] = "(ad.title LIKE '%" . $value . "%' OR a.article_id = '" . (int)$filters['filter_title'] . "')";
        }
        if (!empty($filters['filter_keyword'])) {
            $value = $this->db->escape($filters['filter_keyword']);
            $where[] = "(a.keyword LIKE '%" . $value . "%' OR ad.keyword LIKE '%" . $value . "%')";
        }
        if ($filters['filter_status'] !== '') {
            $where[] = "a.status = '" . (int)$filters['filter_status'] . "'";
        }
        if (!empty($filters['filter_category_id'])) {
            $join .= " INNER JOIN `" . DB_PREFIX . "codecart_pro_blog_article_to_category` a2c ON (a.article_id = a2c.article_id)";
            $where[] = "a2c.category_id = '" . (int)$filters['filter_category_id'] . "'";
        }
        if (!empty($filters['filter_date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['filter_date_from'])) {
            $where[] = "a.date_available >= '" . $this->db->escape($filters['filter_date_from']) . "'";
        }
        if (!empty($filters['filter_date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['filter_date_to'])) {
            $where[] = "a.date_available <= '" . $this->db->escape($filters['filter_date_to']) . "'";
        }
        return array('join' => $join, 'where' => $where);
    }

    private function categoryWhereSql($filters, $language_id) {
        $join = '';
        $where = array('1 = 1');
        if (!empty($filters['filter_name'])) {
            $value = $this->db->escape($filters['filter_name']);
            $where[] = "(cd.name LIKE '%" . $value . "%' OR c.category_id = '" . (int)$filters['filter_name'] . "')";
        }
        if (!empty($filters['filter_keyword'])) {
            $value = $this->db->escape($filters['filter_keyword']);
            $where[] = "(c.keyword LIKE '%" . $value . "%' OR cd.keyword LIKE '%" . $value . "%')";
        }
        if ($filters['filter_status'] !== '') {
            $where[] = "c.status = '" . (int)$filters['filter_status'] . "'";
        }
        if (!empty($filters['filter_store_category_id'])) {
            $join .= " INNER JOIN `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` c2sc ON (c.category_id = c2sc.blog_category_id)";
            $where[] = "c2sc.category_id = '" . (int)$filters['filter_store_category_id'] . "'";
        }
        return array('join' => $join, 'where' => $where);
    }

    private function articleFilterUrl($filters) {
        $url = '';
        foreach (array('filter_title','filter_keyword','filter_status','filter_category_id','filter_date_from','filter_date_to') as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== 0) {
                $url .= '&' . $key . '=' . urlencode($filters[$key]);
            }
        }
        return $url;
    }

    private function categoryFilterUrl($filters) {
        $url = '';
        foreach (array('filter_name','filter_keyword','filter_status','filter_store_category_id','filter_store_category_name') as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== 0) {
                $url .= '&' . $key . '=' . urlencode($filters[$key]);
            }
        }
        return $url;
    }

    private function saveArticle($article_id, $post) {
        $article = isset($post['article']) && is_array($post['article']) ? $post['article'] : array();
        $image = $this->normalizeImagePath(isset($article['image']) ? $article['image'] : '');
        $keyword = isset($article['keyword']) ? $this->slug($article['keyword']) : '';
        $status = !empty($article['status']) ? 1 : 0;
        $sort_order = isset($article['sort_order']) ? (int)$article['sort_order'] : 0;
        $date_available = isset($article['date_available']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $article['date_available']) ? $article['date_available'] : date('Y-m-d');

        if ($article_id) {
            $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_article` SET image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . (int)$status . "', sort_order = '" . (int)$sort_order . "', date_available = '" . $this->db->escape($date_available) . "', date_modified = NOW() WHERE article_id = '" . (int)$article_id . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_article` SET store_id = '0', image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . (int)$status . "', sort_order = '" . (int)$sort_order . "', date_available = '" . $this->db->escape($date_available) . "', date_added = NOW(), date_modified = NOW()");
            $article_id = (int)$this->db->getLastId();
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_description` WHERE article_id = '" . (int)$article_id . "'");
        $descriptions = isset($post['description']) && is_array($post['description']) ? $post['description'] : array();
        foreach ($descriptions as $language_id => $row) {
            $language_keyword = isset($row['keyword']) ? $this->slug($row['keyword']) : '';
            if (!$language_keyword) { $language_keyword = $keyword; }
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_article_description` SET article_id = '" . (int)$article_id . "', language_id = '" . (int)$language_id . "', keyword = '" . $this->db->escape($language_keyword) . "', tags = '" . $this->db->escape(isset($row['tags']) ? trim($row['tags']) : '') . "', title = '" . $this->db->escape(isset($row['title']) ? trim($row['title']) : '') . "', meta_title = '" . $this->db->escape(isset($row['meta_title']) ? trim($row['meta_title']) : '') . "', meta_description = '" . $this->db->escape(isset($row['meta_description']) ? trim($row['meta_description']) : '') . "', meta_keyword = '" . $this->db->escape(isset($row['meta_keyword']) ? trim($row['meta_keyword']) : '') . "', description = '" . $this->db->escape(isset($row['description']) ? $row['description'] : '') . "', seo_text = '" . $this->db->escape(isset($row['seo_text']) ? $row['seo_text'] : '') . "'");
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_category` WHERE article_id = '" . (int)$article_id . "'");
        if (!empty($post['article_category']) && is_array($post['article_category'])) {
            foreach ($post['article_category'] as $category_id) {
                $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "codecart_pro_blog_article_to_category` SET article_id = '" . (int)$article_id . "', category_id = '" . (int)$category_id . "'");
            }
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_product` WHERE article_id = '" . (int)$article_id . "'");
        foreach ($this->parseIds(isset($post['related_product_ids']) ? $post['related_product_ids'] : '') as $product_id) {
            $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "codecart_pro_blog_article_to_product` SET article_id = '" . (int)$article_id . "', product_id = '" . (int)$product_id . "'");
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_related` WHERE article_id = '" . (int)$article_id . "'");
        foreach ($this->parseIds(isset($post['related_article_ids']) ? $post['related_article_ids'] : '') as $related_article_id) {
            if ($related_article_id != $article_id) {
                $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "codecart_pro_blog_article_related` SET article_id = '" . (int)$article_id . "', related_article_id = '" . (int)$related_article_id . "'");
            }
        }

        $this->saveSeoUrl($article_id, $keyword);
        return $article_id;
    }

    private function saveCategory($category_id, $post) {
        $category = isset($post['category']) && is_array($post['category']) ? $post['category'] : array();
        $image = $this->normalizeImagePath(isset($category['image']) ? $category['image'] : '');
        $keyword = isset($category['keyword']) ? $this->slug($category['keyword']) : '';
        $status = !empty($category['status']) ? 1 : 0;
        $sort_order = isset($category['sort_order']) ? (int)$category['sort_order'] : 0;

        if ($category_id) {
            $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_category` SET image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . (int)$status . "', sort_order = '" . (int)$sort_order . "', date_modified = NOW() WHERE category_id = '" . (int)$category_id . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_category` SET image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . (int)$status . "', sort_order = '" . (int)$sort_order . "', date_added = NOW(), date_modified = NOW()");
            $category_id = (int)$this->db->getLastId();
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_category_description` WHERE category_id = '" . (int)$category_id . "'");
        $descriptions = isset($post['description']) && is_array($post['description']) ? $post['description'] : array();
        foreach ($descriptions as $language_id => $row) {
            $language_keyword = isset($row['keyword']) ? $this->slug($row['keyword']) : '';
            if (!$language_keyword) { $language_keyword = $keyword; }
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_category_description` SET category_id = '" . (int)$category_id . "', language_id = '" . (int)$language_id . "', keyword = '" . $this->db->escape($language_keyword) . "', name = '" . $this->db->escape(isset($row['name']) ? trim($row['name']) : '') . "', meta_title = '" . $this->db->escape(isset($row['meta_title']) ? trim($row['meta_title']) : '') . "', meta_description = '" . $this->db->escape(isset($row['meta_description']) ? trim($row['meta_description']) : '') . "', meta_keyword = '" . $this->db->escape(isset($row['meta_keyword']) ? trim($row['meta_keyword']) : '') . "', description = '" . $this->db->escape(isset($row['description']) ? $row['description'] : '') . "', seo_text = '" . $this->db->escape(isset($row['seo_text']) ? $row['seo_text'] : '') . "'");
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` WHERE blog_category_id = '" . (int)$category_id . "'");
        foreach ($this->parseIds(isset($post['related_store_category_ids']) ? $post['related_store_category_ids'] : '') as $store_category_id) {
            $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` SET blog_category_id = '" . (int)$category_id . "', category_id = '" . (int)$store_category_id . "'");
        }

        $this->saveCategorySeoUrl($category_id, $keyword);
        return $category_id;
    }

    private function normalizeImagePath($image) {
        $image = ltrim(trim(str_replace('\\', '/', (string)$image)), '/');
        if (strpos($image, 'image/') === 0) {
            $image = substr($image, 6);
        }
        if ($image === '' || strpos($image, '..') !== false || preg_match('#^(?:https?:)?//#i', $image)) {
            return '';
        }
        if (!preg_match('#^[a-zA-Z0-9_./ ()-]+$#', $image)) {
            return '';
        }
        return is_file(DIR_IMAGE . $image) ? $image : '';
    }

    private function saveSeoUrl($article_id, $fallback_keyword) {
        if (!$this->tableExists('seo_url')) { return; }
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'ccp_article_id=" . (int)$article_id . "'");
        foreach ($languages as $language) {
            $language_id = (int)$language['language_id'];
            $dq = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "codecart_pro_blog_article_description` WHERE article_id = '" . (int)$article_id . "' AND language_id = '" . $language_id . "' LIMIT 1");
            $keyword = ($dq->num_rows && $dq->row['keyword']) ? $this->slug($dq->row['keyword']) : $fallback_keyword;
            if (!$keyword) { $keyword = 'article-' . (int)$article_id; }
            $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '0', language_id = '" . $language_id . "', query = 'ccp_article_id=" . (int)$article_id . "', keyword = '" . $this->db->escape($keyword) . "'");
        }
    }

    private function saveCategorySeoUrl($category_id, $fallback_keyword) {
        if (!$this->tableExists('seo_url')) { return; }
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query = 'ccp_category_id=" . (int)$category_id . "'");
        foreach ($languages as $language) {
            $language_id = (int)$language['language_id'];
            $dq = $this->db->query("SELECT keyword FROM `" . DB_PREFIX . "codecart_pro_blog_category_description` WHERE category_id = '" . (int)$category_id . "' AND language_id = '" . $language_id . "' LIMIT 1");
            $keyword = ($dq->num_rows && $dq->row['keyword']) ? $this->slug($dq->row['keyword']) : $fallback_keyword;
            if (!$keyword) { $keyword = 'blog-category-' . (int)$category_id; }
            $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '0', language_id = '" . $language_id . "', query = 'ccp_category_id=" . (int)$category_id . "', keyword = '" . $this->db->escape($keyword) . "'");
        }
    }

    private function baseData() {
        $data = array();
        foreach ($this->languageKeys() as $key) { $data[$key] = $this->language->get($key); }
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        $data['user_token'] = $this->session->data['user_token'];
        unset($this->session->data['success']);
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('heading_title_text'), 'href' => $this->url->link('extension/module/codecart_pro_blog', 'user_token=' . $this->session->data['user_token'], true))
        );
        return $data;
    }

    private function ensureTables() {
        if ((int)$this->config->get('theme_codecart_pro_blog_schema_version') >= 852) {
            return;
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article` (
            `article_id` INT(11) NOT NULL AUTO_INCREMENT,
            `store_id` INT(11) NOT NULL DEFAULT 0,
            `image` VARCHAR(255) NOT NULL DEFAULT '',
            `keyword` VARCHAR(191) NOT NULL DEFAULT '',
            `status` TINYINT(1) NOT NULL DEFAULT 0,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `date_available` DATE NULL,
            `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_modified` DATETIME NULL,
            PRIMARY KEY (`article_id`),
            KEY `status_date` (`status`, `date_available`),
            KEY `sort_order` (`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_description` (
            `article_id` INT(11) NOT NULL,
            `language_id` INT(11) NOT NULL,
            `keyword` VARCHAR(191) NOT NULL DEFAULT '',
            `tags` VARCHAR(255) NOT NULL DEFAULT '',
            `title` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_description` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_keyword` VARCHAR(255) NOT NULL DEFAULT '',
            `description` MEDIUMTEXT NULL,
            `seo_text` MEDIUMTEXT NULL,
            PRIMARY KEY (`article_id`, `language_id`),
            KEY `title` (`title`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->addColumnIfMissing('codecart_pro_blog_article', 'sort_order', "ALTER TABLE `" . DB_PREFIX . "codecart_pro_blog_article` ADD `sort_order` INT(11) NOT NULL DEFAULT 0 AFTER `status`");
        $this->addColumnIfMissing('codecart_pro_blog_article', 'date_available', "ALTER TABLE `" . DB_PREFIX . "codecart_pro_blog_article` ADD `date_available` DATE NULL AFTER `sort_order`");
        $this->addColumnIfMissing('codecart_pro_blog_article_description', 'tags', "ALTER TABLE `" . DB_PREFIX . "codecart_pro_blog_article_description` ADD `tags` VARCHAR(255) NOT NULL DEFAULT '' AFTER `keyword`");
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_category` (
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
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_category_description` (
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
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_to_category` (`article_id` INT(11) NOT NULL, `category_id` INT(11) NOT NULL, PRIMARY KEY (`article_id`, `category_id`), KEY `category_id` (`category_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_to_product` (`article_id` INT(11) NOT NULL, `product_id` INT(11) NOT NULL, PRIMARY KEY (`article_id`, `product_id`), KEY `product_id` (`product_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_article_related` (`article_id` INT(11) NOT NULL, `related_article_id` INT(11) NOT NULL, PRIMARY KEY (`article_id`, `related_article_id`), KEY `related_article_id` (`related_article_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->addColumnIfMissing('codecart_pro_blog_category', 'sort_order', "ALTER TABLE `" . DB_PREFIX . "codecart_pro_blog_category` ADD `sort_order` INT(11) NOT NULL DEFAULT 0 AFTER `status`");
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` (`blog_category_id` INT(11) NOT NULL, `category_id` INT(11) NOT NULL, PRIMARY KEY (`blog_category_id`, `category_id`), KEY `category_id` (`category_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSettingValue('theme_codecart_pro', 'theme_codecart_pro_blog_schema_version', 852);
    }

    private function addColumnIfMissing($table, $column, $sql) {
        $allowed = array(
            'codecart_pro_blog_article' => array('sort_order', 'date_available'),
            'codecart_pro_blog_article_description' => array('tags'),
            'codecart_pro_blog_category' => array('sort_order')
        );

        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
            return;
        }

        $q = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` LIKE '" . $this->db->escape($column) . "'");
        if (!$q->num_rows) { $this->db->query($sql); }
    }

    private function getCategoriesForForm($language_id) {
        $rows = array();
        if (!$this->tableExists('codecart_pro_blog_category')) { return $rows; }
        $q = $this->db->query("SELECT c.category_id, cd.name FROM `" . DB_PREFIX . "codecart_pro_blog_category` c LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') ORDER BY c.sort_order ASC, cd.name ASC");
        foreach ($q->rows as $row) { $rows[] = array('category_id' => (int)$row['category_id'], 'name' => $row['name'] ? $row['name'] : ('#' . (int)$row['category_id'])); }
        return $rows;
    }

    private function getRelationMap($table, $owner_column, $value_column, $owner_ids) {
        $allowed = array(
            'codecart_pro_blog_article_to_category' => array('article_id', 'category_id'),
            'codecart_pro_blog_article_to_product' => array('article_id', 'product_id'),
            'codecart_pro_blog_article_related' => array('article_id', 'related_article_id'),
            'codecart_pro_blog_category_to_store_category' => array('blog_category_id', 'category_id')
        );
        $map = array();

        if (!isset($allowed[$table]) || $allowed[$table][0] !== $owner_column || $allowed[$table][1] !== $value_column || !$this->tableExists($table)) {
            return $map;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$owner_ids))));
        if (!$ids) {
            return $map;
        }

        $query = $this->db->query("SELECT `" . $owner_column . "`, `" . $value_column . "` FROM `" . DB_PREFIX . $table . "` WHERE `" . $owner_column . "` IN (" . implode(',', $ids) . ") ORDER BY `" . $owner_column . "` ASC, `" . $value_column . "` ASC");
        foreach ($query->rows as $row) {
            $owner_id = (int)$row[$owner_column];
            if (!isset($map[$owner_id])) {
                $map[$owner_id] = array();
            }
            $map[$owner_id][] = (int)$row[$value_column];
        }

        return $map;
    }

    private function getArticleCategoryIds($article_id) {
        $ids = array();
        if (!$article_id || !$this->tableExists('codecart_pro_blog_article_to_category')) { return $ids; }
        $q = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_category` WHERE article_id = '" . (int)$article_id . "'");
        foreach ($q->rows as $row) { $ids[] = (int)$row['category_id']; }
        return $ids;
    }

    private function getArticleProductIds($article_id) {
        $ids = array();
        if (!$article_id || !$this->tableExists('codecart_pro_blog_article_to_product')) { return $ids; }
        $q = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_product` WHERE article_id = '" . (int)$article_id . "'");
        foreach ($q->rows as $row) { $ids[] = (int)$row['product_id']; }
        return $ids;
    }

    private function getArticleRelatedIds($article_id) {
        $ids = array();
        if (!$article_id || !$this->tableExists('codecart_pro_blog_article_related')) { return $ids; }
        $q = $this->db->query("SELECT related_article_id FROM `" . DB_PREFIX . "codecart_pro_blog_article_related` WHERE article_id = '" . (int)$article_id . "'");
        foreach ($q->rows as $row) { $ids[] = (int)$row['related_article_id']; }
        return $ids;
    }

    private function getArticleCategoryNamesBatch($article_ids, $language_id) {
        $result = array();
        $article_ids = array_values(array_unique(array_filter(array_map('intval', (array)$article_ids))));
        if (!$article_ids || !$this->tableExists('codecart_pro_blog_article_to_category')) { return $result; }
        $q = $this->db->query("SELECT a2c.article_id, cd.name FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_category` a2c LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_category_description` cd ON (a2c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') WHERE a2c.article_id IN (" . implode(',', $article_ids) . ") ORDER BY a2c.article_id ASC, cd.name ASC");
        foreach ($q->rows as $row) {
            $article_id = (int)$row['article_id'];
            if (!isset($result[$article_id])) { $result[$article_id] = array(); }
            if (!empty($row['name'])) { $result[$article_id][] = $row['name']; }
        }
        foreach ($result as $article_id => $names) { $result[$article_id] = implode(', ', $names); }
        return $result;
    }

    private function getProductsForForm($ids, $language_id) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
        if (!$ids) { return array(); }
        $q = $this->db->query("SELECT p.product_id, p.model, pd.name FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "') WHERE p.product_id IN (" . implode(',', $ids) . ")");
        $map = array();
        foreach ($q->rows as $row) {
            $name = !empty($row['name']) ? $row['name'] : ('#' . (int)$row['product_id']);
            if (!empty($row['model'])) { $name .= ' [' . $row['model'] . ']'; }
            $map[(int)$row['product_id']] = array('product_id' => (int)$row['product_id'], 'name' => $name);
        }
        $rows = array();
        foreach ($ids as $id) { if (isset($map[$id])) { $rows[] = $map[$id]; } }
        return $rows;
    }

    private function getArticlesForForm($ids, $language_id) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
        if (!$ids) { return array(); }
        $q = $this->db->query("SELECT a.article_id, ad.title FROM `" . DB_PREFIX . "codecart_pro_blog_article` a LEFT JOIN `" . DB_PREFIX . "codecart_pro_blog_article_description` ad ON (a.article_id = ad.article_id AND ad.language_id = '" . (int)$language_id . "') WHERE a.article_id IN (" . implode(',', $ids) . ")");
        $map = array();
        foreach ($q->rows as $row) { $map[(int)$row['article_id']] = array('article_id' => (int)$row['article_id'], 'name' => !empty($row['title']) ? $row['title'] : ('#' . (int)$row['article_id'])); }
        $rows = array();
        foreach ($ids as $id) { if (isset($map[$id])) { $rows[] = $map[$id]; } }
        return $rows;
    }

    private function getStoreCategoriesForForm($blog_category_id, $language_id) {
        $rows = array();
        if (!$blog_category_id || !$this->tableExists('codecart_pro_blog_category_to_store_category')) { return $rows; }
        $q = $this->db->query("SELECT c.category_id, cd.name FROM `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` b2c INNER JOIN `" . DB_PREFIX . "category` c ON (b2c.category_id = c.category_id) LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "') WHERE b2c.blog_category_id = '" . (int)$blog_category_id . "' ORDER BY cd.name ASC");
        foreach ($q->rows as $row) { $rows[] = array('category_id' => (int)$row['category_id'], 'name' => $row['name'] ? $row['name'] : ('#' . (int)$row['category_id'])); }
        return $rows;
    }


    private function csvSafe($value) {
        $value = (string)$value;

        if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    private function csvHeaderMap($header) {
        $map = array();
        if (is_array($header)) {
            foreach ($header as $index => $name) {
                $map[trim((string)$name)] = $index;
            }
        }
        return $map;
    }

    private function csvValue($row, $map, $key, $default = '') {
        if (isset($map[$key]) && isset($row[$map[$key]])) { return $row[$map[$key]]; }
        return $default;
    }

    private function upsertArticleFromCsv($row, $map) {
        $article_id = (int)$this->csvValue($row, $map, 'article_id', 0);
        $language_id = (int)$this->csvValue($row, $map, 'language_id', (int)$this->config->get('config_language_id'));
        if (!$language_id) { $language_id = (int)$this->config->get('config_language_id'); }
        $keyword = $this->slug($this->csvValue($row, $map, 'keyword'));
        $status = (int)$this->csvValue($row, $map, 'status', 1) ? 1 : 0;
        $sort_order = (int)$this->csvValue($row, $map, 'sort_order', 0);
        $date_available = $this->csvValue($row, $map, 'date_available', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_available)) { $date_available = date('Y-m-d'); }
        $image = trim($this->csvValue($row, $map, 'image'));

        if ($article_id) {
            $exists = $this->db->query("SELECT article_id FROM `" . DB_PREFIX . "codecart_pro_blog_article` WHERE article_id = '" . $article_id . "' LIMIT 1");
            if ($exists->num_rows) {
                $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_article` SET image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . $status . "', sort_order = '" . $sort_order . "', date_available = '" . $this->db->escape($date_available) . "', date_modified = NOW() WHERE article_id = '" . $article_id . "'");
            } else {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_article` SET article_id = '" . $article_id . "', store_id = '0', image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . $status . "', sort_order = '" . $sort_order . "', date_available = '" . $this->db->escape($date_available) . "', date_added = NOW(), date_modified = NOW()");
            }
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_article` SET store_id = '0', image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . $status . "', sort_order = '" . $sort_order . "', date_available = '" . $this->db->escape($date_available) . "', date_added = NOW(), date_modified = NOW()");
            $article_id = (int)$this->db->getLastId();
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_description` WHERE article_id = '" . $article_id . "' AND language_id = '" . $language_id . "'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_article_description` SET article_id = '" . $article_id . "', language_id = '" . $language_id . "', keyword = '" . $this->db->escape($keyword) . "', tags = '" . $this->db->escape($this->csvValue($row, $map, 'tags')) . "', title = '" . $this->db->escape($this->csvValue($row, $map, 'title')) . "', meta_title = '" . $this->db->escape($this->csvValue($row, $map, 'meta_title')) . "', meta_description = '" . $this->db->escape($this->csvValue($row, $map, 'meta_description')) . "', meta_keyword = '" . $this->db->escape($this->csvValue($row, $map, 'meta_keyword')) . "', description = '" . $this->db->escape($this->csvValue($row, $map, 'description')) . "', seo_text = '" . $this->db->escape($this->csvValue($row, $map, 'seo_text')) . "'");

        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_category` WHERE article_id = '" . $article_id . "'");
        foreach ($this->parseIds($this->csvValue($row, $map, 'category_ids')) as $category_id) { $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "codecart_pro_blog_article_to_category` SET article_id = '" . $article_id . "', category_id = '" . (int)$category_id . "'"); }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_to_product` WHERE article_id = '" . $article_id . "'");
        foreach ($this->parseIds($this->csvValue($row, $map, 'product_ids')) as $product_id) { $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "codecart_pro_blog_article_to_product` SET article_id = '" . $article_id . "', product_id = '" . (int)$product_id . "'"); }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_article_related` WHERE article_id = '" . $article_id . "'");
        foreach ($this->parseIds($this->csvValue($row, $map, 'related_article_ids')) as $related_article_id) { if ((int)$related_article_id != $article_id) { $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "codecart_pro_blog_article_related` SET article_id = '" . $article_id . "', related_article_id = '" . (int)$related_article_id . "'"); } }
        $this->saveSeoUrl($article_id, $keyword);
        return $article_id;
    }

    private function upsertCategoryFromCsv($row, $map) {
        $category_id = (int)$this->csvValue($row, $map, 'category_id', 0);
        $language_id = (int)$this->csvValue($row, $map, 'language_id', (int)$this->config->get('config_language_id'));
        if (!$language_id) { $language_id = (int)$this->config->get('config_language_id'); }
        $keyword = $this->slug($this->csvValue($row, $map, 'keyword'));
        $status = (int)$this->csvValue($row, $map, 'status', 1) ? 1 : 0;
        $sort_order = (int)$this->csvValue($row, $map, 'sort_order', 0);
        $image = trim($this->csvValue($row, $map, 'image'));

        if ($category_id) {
            $exists = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "codecart_pro_blog_category` WHERE category_id = '" . $category_id . "' LIMIT 1");
            if ($exists->num_rows) {
                $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_category` SET image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . $status . "', sort_order = '" . $sort_order . "', date_modified = NOW() WHERE category_id = '" . $category_id . "'");
            } else {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_category` SET category_id = '" . $category_id . "', image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . $status . "', sort_order = '" . $sort_order . "', date_added = NOW(), date_modified = NOW()");
            }
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_category` SET image = '" . $this->db->escape($image) . "', keyword = '" . $this->db->escape($keyword) . "', status = '" . $status . "', sort_order = '" . $sort_order . "', date_added = NOW(), date_modified = NOW()");
            $category_id = (int)$this->db->getLastId();
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_category_description` WHERE category_id = '" . $category_id . "' AND language_id = '" . $language_id . "'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_blog_category_description` SET category_id = '" . $category_id . "', language_id = '" . $language_id . "', keyword = '" . $this->db->escape($keyword) . "', name = '" . $this->db->escape($this->csvValue($row, $map, 'name')) . "', meta_title = '" . $this->db->escape($this->csvValue($row, $map, 'meta_title')) . "', meta_description = '" . $this->db->escape($this->csvValue($row, $map, 'meta_description')) . "', meta_keyword = '" . $this->db->escape($this->csvValue($row, $map, 'meta_keyword')) . "', description = '" . $this->db->escape($this->csvValue($row, $map, 'description')) . "', seo_text = '" . $this->db->escape($this->csvValue($row, $map, 'seo_text')) . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` WHERE blog_category_id = '" . $category_id . "'");
        foreach ($this->parseIds($this->csvValue($row, $map, 'store_category_ids')) as $store_category_id) { $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "codecart_pro_blog_category_to_store_category` SET blog_category_id = '" . $category_id . "', category_id = '" . (int)$store_category_id . "'"); }
        $this->saveCategorySeoUrl($category_id, $keyword);
        return $category_id;
    }


    private function autofixArticleSeo($options) {
        $updated = 0;
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $article_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_article` ORDER BY article_id ASC LIMIT 1000");
        $article_ids = array();
        foreach ($article_query->rows as $article_row) { $article_ids[] = (int)$article_row['article_id']; }
        $description_map = array();
        if ($article_ids) {
            $dq = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_article_description` WHERE article_id IN (" . implode(',', $article_ids) . ")");
            foreach ($dq->rows as $row) { $description_map[(int)$row['article_id']][(int)$row['language_id']] = $row; }
        }

        foreach ($article_query->rows as $article) {
            $article_id = (int)$article['article_id'];
            $fallback_keyword = '';

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                if (!isset($description_map[$article_id][$language_id])) { continue; }
                $row = $description_map[$article_id][$language_id];
                $changes = array();
                $title = trim((string)$row['title']);
                $description = $this->plainText($row['description']);
                $seo_text = $this->plainText($row['seo_text']);
                $source = $description ? $description : ($seo_text ? $seo_text : $title);

                if (!empty($options['meta'])) {
                    if ($title !== '' && trim((string)$row['meta_title']) === '') {
                        $changes['meta_title'] = $this->limitText($title, 70);
                    }
                    if ($source !== '' && trim((string)$row['meta_description']) === '') {
                        $changes['meta_description'] = $this->limitText($source, 165);
                    }
                }

                if (!empty($options['keywords'])) {
                    $keyword = $this->slug($row['keyword']);
                    if (!$keyword && $title !== '') {
                        $keyword = $this->slug($title);
                    }
                    if (!$keyword) { $keyword = 'article-' . $article_id; }
                    if (!empty($options['duplicates'])) {
                        $keyword = $this->uniqueSeoKeyword($keyword, 'ccp_article_id=' . $article_id, $language_id);
                    }
                    if ($keyword !== $row['keyword']) {
                        $changes['keyword'] = $keyword;
                    }
                    if (!$fallback_keyword) { $fallback_keyword = $keyword; }
                } else {
                    $fallback_keyword = $this->slug($row['keyword'] ? $row['keyword'] : $article['keyword']);
                }

                if ($changes) {
                    $sets = array();
                    $allowed_fields = array('meta_title', 'meta_description', 'keyword');
                    foreach ($changes as $field => $value) {
                        if (in_array($field, $allowed_fields, true)) {
                            $sets[] = "`" . $field . "` = '" . $this->db->escape($value) . "'";
                        }
                    }
                    $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_article_description` SET " . implode(', ', $sets) . " WHERE article_id = '" . $article_id . "' AND language_id = '" . $language_id . "'");
                    $updated++;
                }
            }

            if (!empty($options['keywords']) && !$this->slug($article['keyword'])) {
                if (!$fallback_keyword) { $fallback_keyword = 'article-' . $article_id; }
                $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_article` SET keyword = '" . $this->db->escape($fallback_keyword) . "', date_modified = NOW() WHERE article_id = '" . $article_id . "'");
                $updated++;
            }

            if (!empty($options['sync_urls'])) {
                $this->saveSeoUrl($article_id, $fallback_keyword ? $fallback_keyword : $article['keyword']);
                $updated++;
            }
        }

        return $updated;
    }

    private function autofixCategorySeo($options) {
        $updated = 0;
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $category_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_category` ORDER BY category_id ASC LIMIT 1000");
        $category_ids = array();
        foreach ($category_query->rows as $category_row) { $category_ids[] = (int)$category_row['category_id']; }
        $description_map = array();
        if ($category_ids) {
            $dq = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_category_description` WHERE category_id IN (" . implode(',', $category_ids) . ")");
            foreach ($dq->rows as $row) { $description_map[(int)$row['category_id']][(int)$row['language_id']] = $row; }
        }

        foreach ($category_query->rows as $category) {
            $category_id = (int)$category['category_id'];
            $fallback_keyword = '';

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                if (!isset($description_map[$category_id][$language_id])) { continue; }
                $row = $description_map[$category_id][$language_id];
                $changes = array();
                $name = trim((string)$row['name']);
                $description = $this->plainText($row['description']);
                $seo_text = $this->plainText($row['seo_text']);
                $source = $description ? $description : ($seo_text ? $seo_text : $name);

                if (!empty($options['meta'])) {
                    if ($name !== '' && trim((string)$row['meta_title']) === '') {
                        $changes['meta_title'] = $this->limitText($name, 70);
                    }
                    if ($source !== '' && trim((string)$row['meta_description']) === '') {
                        $changes['meta_description'] = $this->limitText($source, 165);
                    }
                }

                if (!empty($options['keywords'])) {
                    $keyword = $this->slug($row['keyword']);
                    if (!$keyword && $name !== '') {
                        $keyword = $this->slug($name);
                    }
                    if (!$keyword) { $keyword = 'blog-category-' . $category_id; }
                    if (!empty($options['duplicates'])) {
                        $keyword = $this->uniqueSeoKeyword($keyword, 'ccp_category_id=' . $category_id, $language_id);
                    }
                    if ($keyword !== $row['keyword']) {
                        $changes['keyword'] = $keyword;
                    }
                    if (!$fallback_keyword) { $fallback_keyword = $keyword; }
                } else {
                    $fallback_keyword = $this->slug($row['keyword'] ? $row['keyword'] : $category['keyword']);
                }

                if ($changes) {
                    $sets = array();
                    $allowed_fields = array('meta_title', 'meta_description', 'keyword');
                    foreach ($changes as $field => $value) {
                        if (in_array($field, $allowed_fields, true)) {
                            $sets[] = "`" . $field . "` = '" . $this->db->escape($value) . "'";
                        }
                    }
                    $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_category_description` SET " . implode(', ', $sets) . " WHERE category_id = '" . $category_id . "' AND language_id = '" . $language_id . "'");
                    $updated++;
                }
            }

            if (!empty($options['keywords']) && !$this->slug($category['keyword'])) {
                if (!$fallback_keyword) { $fallback_keyword = 'blog-category-' . $category_id; }
                $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_blog_category` SET keyword = '" . $this->db->escape($fallback_keyword) . "', date_modified = NOW() WHERE category_id = '" . $category_id . "'");
                $updated++;
            }

            if (!empty($options['sync_urls'])) {
                $this->saveCategorySeoUrl($category_id, $fallback_keyword ? $fallback_keyword : $category['keyword']);
                $updated++;
            }
        }

        return $updated;
    }

    private function uniqueSeoKeyword($keyword, $query, $language_id) {
        $base = $this->slug($keyword);
        if (!$base) { $base = 'codecart-page'; }
        $candidate = $base;
        $i = 2;
        while ($this->seoKeywordExists($candidate, $query, $language_id)) {
            $candidate = $base . '-' . $i;
            $i++;
            if ($i > 200) { $candidate = $base . '-' . time(); break; }
        }
        return $candidate;
    }

    private function seoKeywordExists($keyword, $query, $language_id) {
        if (!$this->tableExists('seo_url')) { return false; }
        $q = $this->db->query("SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url` WHERE keyword = '" . $this->db->escape($keyword) . "' AND language_id = '" . (int)$language_id . "' AND query <> '" . $this->db->escape($query) . "' LIMIT 1");
        return (bool)$q->num_rows;
    }

    private function plainText($value) {
        $text = html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    private function limitText($value, $limit) {
        $value = $this->plainText($value);
        if (!$limit) { return $value; }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') <= $limit) { return $value; }
            return rtrim(mb_substr($value, 0, $limit - 1, 'UTF-8'));
        }
        if (strlen($value) <= $limit) { return $value; }
        return rtrim(substr($value, 0, $limit - 1));
    }


    private function getSeoAuditArticleIssues() {
        $issues = array();
        $languages = $this->getAuditLanguages();
        $duplicate_keywords = $this->getDuplicateSeoKeywords();
        $article_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_article` ORDER BY article_id DESC LIMIT 500");
        foreach ($article_query->rows as $article) {
            $article_id = (int)$article['article_id'];
            $edit = $this->url->link('extension/module/codecart_pro_blog/form', 'user_token=' . $this->session->data['user_token'] . '&article_id=' . $article_id, true);
            if (!(int)$article['status']) {
                $this->addAuditIssue($issues, 'article', $article_id, '-', $this->language->get('audit_field_status'), 'info', $this->language->get('audit_issue_disabled'), $edit);
            }
            if (empty($article['image'])) {
                $this->addAuditIssue($issues, 'article', $article_id, '-', $this->language->get('audit_field_image'), 'info', $this->language->get('audit_issue_missing_image'), $edit);
            }
            if (!empty($article['date_available']) && $article['date_available'] > date('Y-m-d')) {
                $this->addAuditIssue($issues, 'article', $article_id, '-', $this->language->get('audit_field_date_available'), 'info', $this->language->get('audit_issue_future_date'), $edit);
            }
            if (!$this->hasRows("codecart_pro_blog_article_to_category", "article_id", $article_id)) {
                $this->addAuditIssue($issues, 'article', $article_id, '-', $this->language->get('audit_field_category'), 'warning', $this->language->get('audit_issue_no_category'), $edit);
            }
            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                $language_name = $language['name'];
                $dq = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_article_description` WHERE article_id = '" . $article_id . "' AND language_id = '" . $language_id . "' LIMIT 1");
                if (!$dq->num_rows) {
                    $this->addAuditIssue($issues, 'article', $article_id, $language_name, $this->language->get('entry_description'), 'critical', $this->language->get('audit_issue_missing_language'), $edit);
                    continue;
                }
                $row = $dq->row;
                $this->auditTextField($issues, 'article', $article_id, $language_name, $this->language->get('entry_title'), $row['title'], 10, 90, true, $edit);
                $this->auditTextField($issues, 'article', $article_id, $language_name, $this->language->get('entry_meta_title'), $row['meta_title'], 30, 70, true, $edit);
                $this->auditTextField($issues, 'article', $article_id, $language_name, $this->language->get('entry_meta_description'), $row['meta_description'], 80, 170, true, $edit);
                $this->auditTextField($issues, 'article', $article_id, $language_name, $this->language->get('entry_description'), $row['description'], 300, 0, true, $edit);
                $this->auditTextField($issues, 'article', $article_id, $language_name, $this->language->get('entry_seo_text'), $row['seo_text'], 200, 0, true, $edit);
                $keyword = $this->slug($row['keyword'] ? $row['keyword'] : $article['keyword']);
                if (!$keyword) {
                    $this->addAuditIssue($issues, 'article', $article_id, $language_name, $this->language->get('entry_keyword'), 'critical', $this->language->get('audit_issue_missing_keyword'), $edit);
                } elseif (isset($duplicate_keywords[$language_id . '|' . $keyword])) {
                    $message = sprintf($this->language->get('audit_issue_duplicate_keyword'), $keyword, $duplicate_keywords[$language_id . '|' . $keyword]['total']);
                    $this->addAuditIssue($issues, 'article', $article_id, $language_name, $this->language->get('entry_keyword'), 'critical', $message, $edit);
                }
            }
        }
        return $issues;
    }

    private function getSeoAuditCategoryIssues() {
        $issues = array();
        $languages = $this->getAuditLanguages();
        $duplicate_keywords = $this->getDuplicateSeoKeywords();
        $category_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_category` ORDER BY category_id DESC LIMIT 500");
        foreach ($category_query->rows as $category) {
            $category_id = (int)$category['category_id'];
            $edit = $this->url->link('extension/module/codecart_pro_blog/categoryForm', 'user_token=' . $this->session->data['user_token'] . '&category_id=' . $category_id, true);
            if (!(int)$category['status']) {
                $this->addAuditIssue($issues, 'category', $category_id, '-', $this->language->get('audit_field_status'), 'info', $this->language->get('audit_issue_disabled'), $edit);
            }
            if (empty($category['image'])) {
                $this->addAuditIssue($issues, 'category', $category_id, '-', $this->language->get('audit_field_image'), 'info', $this->language->get('audit_issue_missing_image'), $edit);
            }
            if (!$this->hasRows("codecart_pro_blog_category_to_store_category", "blog_category_id", $category_id)) {
                $this->addAuditIssue($issues, 'category', $category_id, '-', $this->language->get('audit_field_store_category'), 'warning', $this->language->get('audit_issue_no_store_category'), $edit);
            }
            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                $language_name = $language['name'];
                $dq = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_blog_category_description` WHERE category_id = '" . $category_id . "' AND language_id = '" . $language_id . "' LIMIT 1");
                if (!$dq->num_rows) {
                    $this->addAuditIssue($issues, 'category', $category_id, $language_name, $this->language->get('entry_description'), 'critical', $this->language->get('audit_issue_missing_language'), $edit);
                    continue;
                }
                $row = $dq->row;
                $this->auditTextField($issues, 'category', $category_id, $language_name, $this->language->get('entry_name'), $row['name'], 10, 90, true, $edit);
                $this->auditTextField($issues, 'category', $category_id, $language_name, $this->language->get('entry_meta_title'), $row['meta_title'], 30, 70, true, $edit);
                $this->auditTextField($issues, 'category', $category_id, $language_name, $this->language->get('entry_meta_description'), $row['meta_description'], 80, 170, true, $edit);
                $this->auditTextField($issues, 'category', $category_id, $language_name, $this->language->get('entry_description'), $row['description'], 200, 0, true, $edit);
                $this->auditTextField($issues, 'category', $category_id, $language_name, $this->language->get('entry_seo_text'), $row['seo_text'], 200, 0, true, $edit);
                $keyword = $this->slug($row['keyword'] ? $row['keyword'] : $category['keyword']);
                if (!$keyword) {
                    $this->addAuditIssue($issues, 'category', $category_id, $language_name, $this->language->get('entry_keyword'), 'critical', $this->language->get('audit_issue_missing_keyword'), $edit);
                } elseif (isset($duplicate_keywords[$language_id . '|' . $keyword])) {
                    $message = sprintf($this->language->get('audit_issue_duplicate_keyword'), $keyword, $duplicate_keywords[$language_id . '|' . $keyword]['total']);
                    $this->addAuditIssue($issues, 'category', $category_id, $language_name, $this->language->get('entry_keyword'), 'critical', $message, $edit);
                }
            }
        }
        return $issues;
    }

    private function auditTextField(&$issues, $entity_type, $entity_id, $language, $field, $value, $min, $max, $required, $edit) {
        $length = $this->textLength($value);
        if ($required && $length < 1) {
            $this->addAuditIssue($issues, $entity_type, $entity_id, $language, $field, 'critical', sprintf($this->language->get('audit_issue_missing_field'), $field), $edit);
            return;
        }
        if ($min && $length > 0 && $length < $min) {
            $this->addAuditIssue($issues, $entity_type, $entity_id, $language, $field, 'warning', sprintf($this->language->get('audit_issue_too_short'), $length, $min), $edit);
        }
        if ($max && $length > $max) {
            $this->addAuditIssue($issues, $entity_type, $entity_id, $language, $field, 'warning', sprintf($this->language->get('audit_issue_too_long'), $length, $max), $edit);
        }
    }

    private function addAuditIssue(&$issues, $entity_type, $entity_id, $language, $field, $severity, $message, $edit) {
        $issues[] = array(
            'entity_type' => $entity_type,
            'entity_id' => (int)$entity_id,
            'language' => $language,
            'field' => $field,
            'severity' => $severity,
            'message' => $message,
            'edit' => $edit
        );
    }

    private function summarizeAuditIssues($issues) {
        $summary = array('total' => 0, 'critical' => 0, 'warning' => 0, 'info' => 0);
        foreach ($issues as $issue) {
            $summary['total']++;
            if (isset($summary[$issue['severity']])) { $summary[$issue['severity']]++; }
        }
        return $summary;
    }

    private function getAuditLanguages() {
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $rows = array();
        foreach ($languages as $language) {
            $rows[] = array('language_id' => (int)$language['language_id'], 'name' => $language['name']);
        }
        return $rows;
    }

    private function getDuplicateSeoKeywords() {
        $duplicates = array();
        if (!$this->tableExists('seo_url')) { return $duplicates; }
        $query = $this->db->query("SELECT language_id, keyword, COUNT(*) AS total FROM `" . DB_PREFIX . "seo_url` WHERE keyword <> '' GROUP BY language_id, keyword HAVING total > 1");
        foreach ($query->rows as $row) {
            $duplicates[(int)$row['language_id'] . '|' . $row['keyword']] = array('total' => (int)$row['total']);
        }
        return $duplicates;
    }

    private function hasRows($table, $column, $id) {
        $allowed = array(
            'codecart_pro_blog_article_to_category' => 'article_id',
            'codecart_pro_blog_category_to_store_category' => 'blog_category_id'
        );

        if (!isset($allowed[$table]) || $allowed[$table] !== $column || !$this->tableExists($table)) {
            return false;
        }

        $query = $this->db->query("SELECT 1 FROM `" . DB_PREFIX . $table . "` WHERE `" . $column . "` = '" . (int)$id . "' LIMIT 1");
        return (bool)$query->num_rows;
    }

    private function textLength($value) {
        $text = trim(strip_tags(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8')));
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }


    private function parseIds($value) {
        $ids = array();
        if (is_array($value)) { $parts = $value; } else { $parts = preg_split('/[^0-9]+/', (string)$value); }
        foreach ($parts as $part) { $id = (int)$part; if ($id > 0) { $ids[$id] = $id; } }
        return array_values($ids);
    }

    private function slug($value) {
        $value = trim((string)$value);
        $value = str_replace(' ', '-', $value);
        $value = preg_replace('/[^\p{L}\p{N}\-\_]+/u', '', $value);
        $value = preg_replace('/[\-\_]{2,}/', '-', $value);
        return trim($value, '-_');
    }

    private function tableExists($table) {
        $q = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$q->num_rows;
    }

    private function checkAccess($permission = 'access') {
        if (!$this->hasValidUserToken() || !$this->user->hasPermission($permission, 'extension/module/codecart_pro_blog')) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return false;
        }
        return true;
    }

    protected function validate() {
        if (!$this->hasValidUserToken() || !$this->user->hasPermission('modify', 'extension/module/codecart_pro_blog')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    private function languageKeys() {
        return array('heading_title','heading_title_text','heading_category_title','heading_category_title_text','text_home','text_list','text_category_list','text_form','text_category_form','text_filter','text_pagination_results','text_enabled','text_disabled','text_no_results','text_confirm','text_success','text_success_delete','text_success_category','text_success_category_delete','text_no_title','entry_title','entry_name','entry_meta_title','entry_meta_description','entry_meta_keyword','entry_description','entry_seo_text','entry_tags','entry_image','entry_keyword','entry_language_keyword','entry_status','entry_sort_order','entry_date_available','entry_categories','entry_related_product_ids','entry_related_article_ids','entry_related_products','entry_related_articles','entry_related_store_categories','column_image','column_title','column_name','column_keyword','column_categories','column_status','column_sort_order','column_date_added','column_action','button_add','button_edit','button_delete','button_save','button_cancel','button_back','button_view','button_categories','button_articles','button_remove','help_keyword','help_related_product_ids','help_related_article_ids','help_related_products','help_related_articles','help_related_store_categories','button_export','button_import','button_bulk_apply','button_regenerate_seo','entry_import_file','entry_filter_title','entry_filter_name','entry_filter_keyword','entry_filter_status','entry_filter_category','entry_filter_store_category','entry_filter_date_from','entry_filter_date_to','button_filter','button_clear_filter','entry_bulk_action','entry_bulk_category','text_bulk_none','text_bulk_enable','text_bulk_disable','text_bulk_assign_category','text_bulk_regenerate_seo','text_success_bulk','text_success_import','text_success_seo_regenerated','error_import_file','error_import_size','error_import_header','error_import_limit','help_import_articles','help_import_categories','column_id','heading_seo_audit','heading_seo_audit_text','button_seo_audit','button_autofix_seo','text_autofix_panel','text_autofix_warning','entry_autofix_articles','entry_autofix_categories','entry_autofix_meta','entry_autofix_keywords','entry_autofix_duplicates','entry_autofix_sync_urls','text_success_autofix','text_audit_summary','text_audit_articles','text_audit_categories','text_audit_entity','text_audit_language','text_audit_field','text_audit_severity','text_audit_problem','text_audit_action','text_audit_critical','text_audit_warning','text_audit_info','text_audit_limited','text_audit_no_issues','text_audit_total','text_audit_article','text_audit_category','audit_field_status','audit_field_image','audit_field_date_available','audit_field_category','audit_field_store_category','audit_issue_disabled','audit_issue_missing_image','audit_issue_future_date','audit_issue_no_category','audit_issue_no_store_category','audit_issue_missing_language','audit_issue_missing_keyword','audit_issue_duplicate_keyword','audit_issue_missing_field','audit_issue_too_short','audit_issue_too_long','text_summary_total','text_summary_published','text_summary_drafts','text_summary_scheduled','text_summary_categories','text_tools','text_content','text_seo','text_publication','text_relations','text_content_help','text_seo_help','text_publication_help','text_relations_help','text_category_content','text_category_seo','text_category_settings','text_category_relations','button_tools','button_open_article','button_open_category','button_select_image','button_clear_image','error_permission');
    }
}
