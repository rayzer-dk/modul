<?php
class ControllerExtensionModuleCodecartProReview extends Controller {
    private $error = array();

    private function hasValidUserToken() {
        $expected = isset($this->session->data['user_token']) ? (string)$this->session->data['user_token'] : '';
        $provided = '';
        if (isset($this->request->get['user_token'])) { $provided = (string)$this->request->get['user_token']; }
        elseif (isset($this->request->post['user_token'])) { $provided = (string)$this->request->post['user_token']; }
        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    public function index() {
        $this->load->language('extension/module/codecart_pro_review');
        $this->document->setTitle($this->language->get('heading_title_text'));
        $this->document->addStyle('view/stylesheet/codecart_pro/theme_admin.css?v=8.7.7');

        if (!$this->checkPermission('access')) {
            return;
        }

        if (!$this->tableExists('codecart_pro_review_extra')) {
            $this->session->data['error_warning'] = $this->language->get('error_storage');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
        $limit = (int)$this->config->get('config_admin_limit');
        if ($limit < 1) {
            $limit = 20;
        }
        $start = ($page - 1) * $limit;
        $language_id = (int)$this->config->get('config_language_id');

        $filters = array(
            'filter_author' => isset($this->request->get['filter_author']) ? trim((string)$this->request->get['filter_author']) : '',
            'filter_product' => isset($this->request->get['filter_product']) ? trim((string)$this->request->get['filter_product']) : '',
            'filter_text' => isset($this->request->get['filter_text']) ? trim((string)$this->request->get['filter_text']) : '',
            'filter_status' => isset($this->request->get['filter_status']) ? (string)$this->request->get['filter_status'] : '',
            'filter_rating' => isset($this->request->get['filter_rating']) ? (int)$this->request->get['filter_rating'] : 0,
            'filter_real_buyer' => isset($this->request->get['filter_real_buyer']) ? (string)$this->request->get['filter_real_buyer'] : ''
        );

        $where = array('1=1');
        if ($filters['filter_author'] !== '') {
            $where[] = "r.author LIKE '%" . $this->db->escape($filters['filter_author']) . "%'";
        }
        if ($filters['filter_product'] !== '') {
            $where[] = "pd.name LIKE '%" . $this->db->escape($filters['filter_product']) . "%'";
        }
        if ($filters['filter_text'] !== '') {
            $where[] = "r.text LIKE '%" . $this->db->escape($filters['filter_text']) . "%'";
        }
        if ($filters['filter_status'] === '0' || $filters['filter_status'] === '1') {
            $where[] = "r.status = '" . (int)$filters['filter_status'] . "'";
        }
        if ($filters['filter_rating'] >= 1 && $filters['filter_rating'] <= 5) {
            $where[] = "r.rating = '" . (int)$filters['filter_rating'] . "'";
        }
        if ($filters['filter_real_buyer'] === '0' || $filters['filter_real_buyer'] === '1') {
            $where[] = "COALESCE(e.real_buyer, 0) = '" . (int)$filters['filter_real_buyer'] . "'";
        }

        $sql_where = ' WHERE ' . implode(' AND ', $where);
        $join = " FROM `" . DB_PREFIX . "review` r LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (r.product_id = pd.product_id AND pd.language_id = '" . $language_id . "') LEFT JOIN `" . DB_PREFIX . "codecart_pro_review_extra` e ON (r.review_id = e.review_id)";

        $total_query = $this->db->query('SELECT COUNT(DISTINCT r.review_id) AS total' . $join . $sql_where);
        $total = (int)$total_query->row['total'];

        $query = $this->db->query(
            "SELECT r.review_id, r.product_id, r.author, r.text, r.rating, r.status, r.date_added, r.date_modified, pd.name AS product, " .
            "e.review_extra_id, e.admin_reply, e.images, e.real_buyer, e.votes_plus, e.votes_minus" .
            $join . $sql_where .
            " ORDER BY r.date_added DESC, r.review_id DESC LIMIT " . (int)$start . ',' . (int)$limit
        );

        $summary_query = $this->db->query(
            "SELECT COUNT(*) AS total, " .
            "SUM(CASE WHEN r.status = '1' THEN 1 ELSE 0 END) AS enabled, " .
            "SUM(CASE WHEN r.status = '0' THEN 1 ELSE 0 END) AS disabled, " .
            "SUM(CASE WHEN COALESCE(e.real_buyer, 0) = '1' THEN 1 ELSE 0 END) AS verified, " .
            "SUM(CASE WHEN e.images IS NOT NULL AND e.images <> '' AND e.images <> '[]' THEN 1 ELSE 0 END) AS with_photos, " .
            "ROUND(AVG(r.rating), 2) AS average_rating" . $join
        );

        $data = $this->languageData();
        $data['error_warning'] = isset($this->session->data['error_warning']) ? $this->session->data['error_warning'] : '';
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['error_warning'], $this->session->data['success']);

        $data['user_token'] = $this->session->data['user_token'];
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('heading_title_text'), 'href' => $this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true))
        );

        $data['summary'] = array(
            'total' => (int)$summary_query->row['total'],
            'enabled' => (int)$summary_query->row['enabled'],
            'disabled' => (int)$summary_query->row['disabled'],
            'verified' => (int)$summary_query->row['verified'],
            'with_photos' => (int)$summary_query->row['with_photos'],
            'average_rating' => $summary_query->row['average_rating'] !== null ? (float)$summary_query->row['average_rating'] : 0
        );

        $data['reviews'] = array();
        foreach ($query->rows as $row) {
            $images = !empty($row['images']) ? json_decode((string)$row['images'], true) : array();
            $text = trim(strip_tags(html_entity_decode((string)$row['text'], ENT_QUOTES, 'UTF-8')));
            if (utf8_strlen($text) > 150) {
                $text = utf8_substr($text, 0, 147) . '...';
            }

            $data['reviews'][] = array(
                'review_id' => (int)$row['review_id'],
                'author' => (string)$row['author'],
                'text' => $text,
                'product' => $row['product'] !== null && $row['product'] !== '' ? (string)$row['product'] : '#' . (int)$row['product_id'],
                'rating' => (int)$row['rating'],
                'status' => (int)$row['status'],
                'real_buyer' => (int)$row['real_buyer'],
                'has_reply' => trim((string)$row['admin_reply']) !== '',
                'images_count' => is_array($images) ? count($images) : 0,
                'date_added' => (string)$row['date_added'],
                'edit' => $this->url->link('extension/module/codecart_pro_review/edit', 'user_token=' . $this->session->data['user_token'] . '&review_id=' . (int)$row['review_id'], true),
                'view_product' => HTTP_CATALOG . 'index.php?route=product/product&product_id=' . (int)$row['product_id']
            );
        }

        foreach ($filters as $key => $value) {
            $data[$key] = $value;
        }

        $filter_url = $this->buildFilterUrl($filters);
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'] . $filter_url . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit), $total, $total ? ceil($total / $limit) : 0);

        $data['action'] = $this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true);
        $data['clear'] = $this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true);
        $data['bulk'] = $this->url->link('extension/module/codecart_pro_review/bulkAction', 'user_token=' . $this->session->data['user_token'], true);
        $data['back'] = $this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_review_list', $data));
    }

    public function edit() {
        $this->load->language('extension/module/codecart_pro_review');
        $this->document->setTitle($this->language->get('heading_title_text'));
        $this->document->addStyle('view/stylesheet/codecart_pro/theme_admin.css?v=8.7.7');

        if (!$this->checkPermission('access')) {
            return;
        }
        if (!$this->tableExists('codecart_pro_review_extra')) {
            $this->session->data['error_warning'] = $this->language->get('error_storage');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $review_id = isset($this->request->get['review_id']) ? (int)$this->request->get['review_id'] : 0;
        $review_query = $this->db->query("SELECT r.*, pd.name AS product FROM `" . DB_PREFIX . "review` r LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (r.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE r.review_id = '" . $review_id . "' LIMIT 1");
        if (!$review_query->num_rows) {
            $this->response->redirect($this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validateForm()) {
            $this->saveReview($review_id, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $this->load->model('tool/image');
        $extra_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE review_id = '" . $review_id . "' LIMIT 1");
        $extra = $extra_query->num_rows ? $extra_query->row : array(
            'plus_text' => '',
            'minus_text' => '',
            'admin_reply' => '',
            'images' => '',
            'real_buyer' => 0,
            'votes_plus' => 0,
            'votes_minus' => 0
        );

        $review = $review_query->row;
        if ($this->request->server['REQUEST_METHOD'] === 'POST') {
            $review['author'] = isset($this->request->post['author']) ? $this->request->post['author'] : $review['author'];
            $review['text'] = isset($this->request->post['text']) ? $this->request->post['text'] : $review['text'];
            $review['rating'] = isset($this->request->post['rating']) ? (int)$this->request->post['rating'] : $review['rating'];
            $review['status'] = isset($this->request->post['status']) ? (int)$this->request->post['status'] : 0;
            foreach (array('plus_text', 'minus_text', 'admin_reply', 'real_buyer') as $key) {
                if (isset($this->request->post[$key])) {
                    $extra[$key] = $this->request->post[$key];
                } elseif ($key === 'real_buyer') {
                    $extra[$key] = 0;
                }
            }
        }

        $image_paths = array();
        if (isset($this->request->post['images']) && is_array($this->request->post['images'])) {
            $image_paths = $this->normalizeImagePaths($this->request->post['images']);
        } elseif (!empty($extra['images'])) {
            $decoded = json_decode((string)$extra['images'], true);
            if (is_array($decoded)) {
                $image_paths = $this->normalizeImagePaths($decoded);
            }
        }

        $data = $this->languageData();
        $data['user_token'] = $this->session->data['user_token'];
        $data['action'] = $this->url->link('extension/module/codecart_pro_review/edit', 'user_token=' . $this->session->data['user_token'] . '&review_id=' . $review_id, true);
        $data['cancel'] = $this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true);
        $data['view_product'] = HTTP_CATALOG . 'index.php?route=product/product&product_id=' . (int)$review['product_id'];
        $data['review'] = $review;
        $data['plus_text'] = (string)$extra['plus_text'];
        $data['minus_text'] = (string)$extra['minus_text'];
        $data['admin_reply'] = (string)$extra['admin_reply'];
        $data['real_buyer'] = (int)$extra['real_buyer'];
        $data['votes_plus'] = (int)$extra['votes_plus'];
        $data['votes_minus'] = (int)$extra['votes_minus'];
        $data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
        $data['review_images'] = array();
        foreach ($image_paths as $image) {
            $data['review_images'][] = array(
                'image' => $image,
                'thumb' => is_file(DIR_IMAGE . $image) ? $this->model_tool_image->resize($image, 100, 100) : $data['placeholder']
            );
        }
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_author'] = isset($this->error['author']) ? $this->error['author'] : '';
        $data['error_text'] = isset($this->error['text']) ? $this->error['text'] : '';
        $data['error_rating'] = isset($this->error['rating']) ? $this->error['rating'] : '';
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('heading_title_text'), 'href' => $this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('text_edit'), 'href' => $data['action'])
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/codecart_pro_review_form', $data));
    }

    public function bulkAction() {
        $this->load->language('extension/module/codecart_pro_review');
        if (!$this->checkPermission('modify')) { return; }
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->response->redirect($this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $selected = isset($this->request->post['selected']) && is_array($this->request->post['selected']) ? array_values(array_unique(array_filter(array_map('intval', $this->request->post['selected'])))) : array();
        $selected = array_slice($selected, 0, 500);
        $action = isset($this->request->post['bulk_action']) ? (string)$this->request->post['bulk_action'] : '';
        $allowed = array('enable', 'disable', 'verified', 'unverified', 'delete');
        if (!$selected || !in_array($action, $allowed, true)) {
            $this->session->data['error_warning'] = $this->language->get('error_bulk_action');
            $this->response->redirect($this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $ids = implode(',', $selected);
        if ($action === 'enable' || $action === 'disable') {
            $status = $action === 'enable' ? 1 : 0;
            $this->db->query("UPDATE `" . DB_PREFIX . "review` SET status = '" . $status . "', date_modified = NOW() WHERE review_id IN (" . $ids . ")");
        } elseif ($action === 'verified' || $action === 'unverified') {
            $real_buyer = $action === 'verified' ? 1 : 0;
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_review_extra` (`review_id`,`product_id`,`real_buyer`,`date_added`) SELECT r.review_id, r.product_id, '" . $real_buyer . "', NOW() FROM `" . DB_PREFIX . "review` r WHERE r.review_id IN (" . $ids . ") ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), real_buyer = VALUES(real_buyer)");
        } elseif ($action === 'delete') {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE review_id IN (" . $ids . ")");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "review` WHERE review_id IN (" . $ids . ")");
        }

        $this->session->data['success'] = sprintf($this->language->get('text_success_bulk'), count($selected));
        $this->response->redirect($this->url->link('extension/module/codecart_pro_review', 'user_token=' . $this->session->data['user_token'], true));
    }

    private function saveReview($review_id, $post) {
        $review_query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "review` WHERE review_id = '" . (int)$review_id . "' LIMIT 1");
        if (!$review_query->num_rows) {
            return;
        }

        $author = trim((string)$post['author']);
        $text = trim((string)$post['text']);
        $rating = max(1, min(5, (int)$post['rating']));
        $status = !empty($post['status']) ? 1 : 0;

        $this->db->query(
            "UPDATE `" . DB_PREFIX . "review` SET " .
            "author = '" . $this->db->escape($author) . "', " .
            "text = '" . $this->db->escape($text) . "', " .
            "rating = '" . $rating . "', " .
            "status = '" . $status . "', " .
            "date_modified = NOW() WHERE review_id = '" . (int)$review_id . "'"
        );

        $plus = isset($post['plus_text']) ? trim((string)$post['plus_text']) : '';
        $minus = isset($post['minus_text']) ? trim((string)$post['minus_text']) : '';
        $reply = isset($post['admin_reply']) ? trim((string)$post['admin_reply']) : '';
        $images = isset($post['images']) && is_array($post['images']) ? $this->normalizeImagePaths($post['images']) : array();
        $images_json = json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $real_buyer = !empty($post['real_buyer']) ? 1 : 0;
        $product_id = (int)$review_query->row['product_id'];

        $extra_query = $this->db->query("SELECT review_extra_id FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE review_id = '" . (int)$review_id . "' LIMIT 1");
        if ($extra_query->num_rows) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "codecart_pro_review_extra` SET " .
                "product_id = '" . $product_id . "', " .
                "plus_text = '" . $this->db->escape($plus) . "', " .
                "minus_text = '" . $this->db->escape($minus) . "', " .
                "admin_reply = '" . $this->db->escape($reply) . "', " .
                "images = '" . $this->db->escape($images_json) . "', " .
                "real_buyer = '" . $real_buyer . "' WHERE review_id = '" . (int)$review_id . "'"
            );
        } else {
            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . "codecart_pro_review_extra` SET " .
                "review_id = '" . (int)$review_id . "', " .
                "product_id = '" . $product_id . "', " .
                "plus_text = '" . $this->db->escape($plus) . "', " .
                "minus_text = '" . $this->db->escape($minus) . "', " .
                "admin_reply = '" . $this->db->escape($reply) . "', " .
                "images = '" . $this->db->escape($images_json) . "', " .
                "real_buyer = '" . $real_buyer . "', date_added = NOW()"
            );
        }
    }

    private function normalizeImagePaths($images) {
        $result = array();
        foreach ((array)$images as $image) {
            $image = ltrim(trim(str_replace('\\', '/', (string)$image)), '/');
            if ($image === '' || strpos($image, '..') !== false || preg_match('#^(?:https?:)?//#i', $image)) {
                continue;
            }
            if (!preg_match('#^[a-zA-Z0-9_./ ()-]+$#', $image)) {
                continue;
            }
            if (strpos($image, 'image/') === 0) {
                $image = substr($image, 6);
            }
            if ($image !== '' && is_file(DIR_IMAGE . $image)) {
                $result[] = $image;
            }
        }
        return array_values(array_unique(array_slice($result, 0, 20)));
    }

    private function validateForm() {
        if (!$this->hasValidUserToken() || !$this->user->hasPermission('modify', 'extension/module/codecart_pro_review')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $author = isset($this->request->post['author']) ? trim((string)$this->request->post['author']) : '';
        if (utf8_strlen($author) < 2 || utf8_strlen($author) > 64) {
            $this->error['author'] = $this->language->get('error_author');
        }

        $text = isset($this->request->post['text']) ? trim((string)$this->request->post['text']) : '';
        if (utf8_strlen($text) < 3 || utf8_strlen($text) > 5000) {
            $this->error['text'] = $this->language->get('error_text');
        }

        $rating = isset($this->request->post['rating']) ? (int)$this->request->post['rating'] : 0;
        if ($rating < 1 || $rating > 5) {
            $this->error['rating'] = $this->language->get('error_rating');
        }

        return !$this->error;
    }

    private function buildFilterUrl($filters) {
        $url = '';
        foreach ($filters as $key => $value) {
            if ($value !== '' && $value !== 0 && $value !== '0') {
                $url .= '&' . $key . '=' . urlencode((string)$value);
            } elseif (($key === 'filter_status' || $key === 'filter_real_buyer') && ($value === '0' || $value === 0)) {
                $url .= '&' . $key . '=0';
            }
        }
        return $url;
    }

    private function tableExists($table) {
        if (!in_array((string)$table, array('codecart_pro_review_extra'), true)) {
            return false;
        }
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$query->num_rows;
    }

    private function checkPermission($permission) {
        if (!$this->hasValidUserToken() || !$this->user->hasPermission($permission, 'extension/module/codecart_pro_review')) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('extension/theme/codecart_pro', 'user_token=' . $this->session->data['user_token'], true));
            return false;
        }
        return true;
    }

    private function languageData() {
        $keys = array(
            'heading_title', 'heading_title_text', 'text_home', 'text_edit', 'text_list', 'text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_no_results', 'text_success', 'text_success_bulk', 'text_pagination',
            'text_summary_total', 'text_summary_enabled', 'text_summary_disabled', 'text_summary_verified', 'text_summary_photos', 'text_summary_average', 'text_tab_review', 'text_tab_details', 'text_tab_reply', 'text_tab_service', 'text_review_information', 'text_review_details', 'text_reply_information', 'text_service_information', 'text_select_action', 'text_bulk_enable', 'text_bulk_disable', 'text_bulk_verified', 'text_bulk_unverified', 'text_bulk_delete', 'text_confirm_delete', 'text_no_reply',
            'entry_author', 'entry_product', 'entry_review_text', 'entry_filter_text', 'entry_status', 'entry_rating', 'entry_plus', 'entry_minus', 'entry_admin_reply', 'entry_images', 'entry_real_buyer', 'entry_votes_plus', 'entry_votes_minus', 'entry_date_added',
            'button_filter', 'button_clear', 'button_save', 'button_cancel', 'button_edit', 'button_view', 'button_back', 'button_apply', 'button_add_image', 'button_remove',
            'column_review', 'column_product', 'column_rating', 'column_status', 'column_details', 'column_date_added', 'column_action',
            'help_images', 'help_review_text', 'help_real_buyer', 'help_admin_reply', 'error_permission', 'error_storage', 'error_author', 'error_text', 'error_rating', 'error_bulk_action'
        );
        $data = array();
        foreach ($keys as $key) {
            $data[$key] = $this->language->get($key);
        }
        return $data;
    }
}
