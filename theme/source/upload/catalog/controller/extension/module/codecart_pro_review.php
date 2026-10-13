<?php
class ControllerExtensionModuleCodecartProReview extends Controller {
    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $theme_directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        $config_template = (string)$this->config->get('config_template');
        $template_directory = (string)$this->config->get('template_directory');

        return ($config_theme === 'codecart_pro' || $config_theme === 'theme_codecart_pro' || $theme_directory === 'codecart_pro' || $config_template === 'codecart_pro' || strpos($template_directory, 'codecart_pro/') === 0);
    }

    private function tableExists($table) {
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$query->num_rows;
    }


    private function isPostRequest() {
        return isset($this->request->server['REQUEST_METHOD']) && strtoupper((string)$this->request->server['REQUEST_METHOD']) === 'POST';
    }

    private function isTrustedPostOrigin() {
        $source = '';

        if (!empty($this->request->server['HTTP_ORIGIN'])) {
            $source = (string)$this->request->server['HTTP_ORIGIN'];
        } elseif (!empty($this->request->server['HTTP_REFERER'])) {
            $source = (string)$this->request->server['HTTP_REFERER'];
        }

        if ($source === '') {
            return false;
        }

        $source_host = strtolower((string)parse_url($source, PHP_URL_HOST));
        if ($source_host === '') {
            return false;
        }

        $allowed_hosts = array();
        foreach (array((string)$this->config->get('config_url'), (string)$this->config->get('config_ssl')) as $url) {
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            if ($host !== '') {
                $allowed_hosts[$host] = true;
            }
        }

        if (defined('HTTP_SERVER')) {
            $host = strtolower((string)parse_url(HTTP_SERVER, PHP_URL_HOST));
            if ($host !== '') {
                $allowed_hosts[$host] = true;
            }
        }

        if (defined('HTTPS_SERVER')) {
            $host = strtolower((string)parse_url(HTTPS_SERVER, PHP_URL_HOST));
            if ($host !== '') {
                $allowed_hosts[$host] = true;
            }
        }

        return isset($allowed_hosts[$source_host]);
    }

    private function isReviewImagePath($path) {
        return (bool)preg_match('#^catalog/codecart_pro_reviews/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$#i', (string)$path);
    }

    private function reviewImageFile($path) {
        if (!$this->isReviewImagePath($path)) {
            return '';
        }

        return DIR_IMAGE . str_replace('/', DIRECTORY_SEPARATOR, (string)$path);
    }

    private function cleanupPendingUploads() {
        $pending = isset($this->session->data['ccp_review_pending_uploads']) && is_array($this->session->data['ccp_review_pending_uploads'])
            ? $this->session->data['ccp_review_pending_uploads']
            : array();
        $now = time();

        foreach ($pending as $path => $created_at) {
            if (!$this->isReviewImagePath($path) || ($now - (int)$created_at) > 3600) {
                $file = $this->reviewImageFile($path);
                if ($file !== '' && is_file($file)) {
                    @unlink($file);
                }
                unset($pending[$path]);
            }
        }

        $this->session->data['ccp_review_pending_uploads'] = $pending;
    }

    private function cleanupGlobalOrphanUploads() {
        if (mt_rand(1, 100) > 2 || !$this->tableExists('codecart_pro_review_extra')) {
            return;
        }

        $dir = DIR_IMAGE . 'catalog/codecart_pro_reviews/';
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '*');
        if (!is_array($files)) {
            return;
        }

        $checked = 0;
        $cutoff = time() - 172800;

        foreach ($files as $file) {
            if ($checked >= 50) {
                break;
            }

            if (!is_file($file) || filemtime($file) >= $cutoff) {
                continue;
            }

            $name = basename($file);
            if (!preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$/i', $name)) {
                continue;
            }

            $checked++;
            $path = 'catalog/codecart_pro_reviews/' . $name;
            $query = $this->db->query("SELECT review_extra_id FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE images LIKE '%" . $this->db->escape($path) . "%' LIMIT 1");

            if (!$query->num_rows) {
                @unlink($file);
            }
        }
    }

    public function getExtra($args = array()) {
        $review_id = isset($args['review_id']) ? (int)$args['review_id'] : 0;
        $default = array('plus_text' => '', 'minus_text' => '', 'admin_reply' => '', 'images' => array(), 'real_buyer' => 0, 'votes_plus' => 0, 'votes_minus' => 0);

        if ($review_id <= 0) {
            return $default;
        }

        $items = $this->getExtras(array('review_ids' => array($review_id)));
        return isset($items[$review_id]) ? $items[$review_id] : $default;
    }

    public function getExtras($args = array()) {
        $result = array();
        $review_ids = isset($args['review_ids']) && is_array($args['review_ids']) ? $args['review_ids'] : array();
        $review_ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $review_ids)))), 0, 100);

        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !$review_ids || !$this->tableExists('codecart_pro_review_extra')) {
            return $result;
        }

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE review_id IN (" . implode(',', $review_ids) . ") ORDER BY review_extra_id ASC");
        $photos_enabled = (int)$this->config->get('theme_codecart_pro_reviews_photos_status');
        $admin_reply_enabled = (int)$this->config->get('theme_codecart_pro_reviews_admin_reply_status');
        $real_buyer_enabled = (int)$this->config->get('theme_codecart_pro_reviews_real_buyer_status');

        if ($photos_enabled) {
            $this->load->model('tool/image');
        }

        foreach ($query->rows as $row) {
            $review_id = (int)$row['review_id'];
            if ($review_id <= 0 || isset($result[$review_id])) {
                continue;
            }

            $item = array(
                'plus_text' => nl2br(htmlspecialchars((string)$row['plus_text'], ENT_QUOTES, 'UTF-8')),
                'minus_text' => nl2br(htmlspecialchars((string)$row['minus_text'], ENT_QUOTES, 'UTF-8')),
                'admin_reply' => $admin_reply_enabled ? nl2br(htmlspecialchars((string)$row['admin_reply'], ENT_QUOTES, 'UTF-8')) : '',
                'images' => array(),
                'real_buyer' => $real_buyer_enabled ? (int)$row['real_buyer'] : 0,
                'votes_plus' => (int)$row['votes_plus'],
                'votes_minus' => (int)$row['votes_minus']
            );

            $images = !empty($row['images']) ? json_decode($row['images'], true) : array();
            if ($photos_enabled && is_array($images)) {
                foreach ($images as $image) {
                    $image = trim((string)$image);
                    if ($this->isReviewImagePath($image) && is_file($this->reviewImageFile($image))) {
                        $item['images'][] = array(
                            'path' => 'image/' . $image,
                            'thumb' => $this->model_tool_image->resize($image, 120, 120)
                        );
                    }
                }
            }

            $result[$review_id] = $item;
        }

        return $result;
    }

    public function saveExtra($args = array()) {
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !$this->tableExists('codecart_pro_review_extra')) { return; }
        $review_id = isset($args['review_id']) ? (int)$args['review_id'] : 0;
        $product_id = isset($args['product_id']) ? (int)$args['product_id'] : 0;
        if (!$review_id || !$product_id) { return; }
        $plus = isset($this->request->post['ccp_plus']) ? utf8_substr(trim(strip_tags($this->request->post['ccp_plus'])), 0, 2000) : '';
        $minus = isset($this->request->post['ccp_minus']) ? utf8_substr(trim(strip_tags($this->request->post['ccp_minus'])), 0, 2000) : '';
        $this->cleanupPendingUploads();
        $pending = isset($this->session->data['ccp_review_pending_uploads']) && is_array($this->session->data['ccp_review_pending_uploads'])
            ? $this->session->data['ccp_review_pending_uploads']
            : array();
        $existing_images = array();
        $existing_row = $this->db->query("SELECT review_extra_id, images FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE review_id = '" . (int)$review_id . "' LIMIT 1");
        if ($existing_row->num_rows && !empty($existing_row->row['images'])) {
            $decoded_existing = json_decode($existing_row->row['images'], true);
            if (is_array($decoded_existing)) {
                foreach ($decoded_existing as $existing_image) {
                    if ($this->isReviewImagePath($existing_image)) {
                        $existing_images[(string)$existing_image] = true;
                    }
                }
            }
        }

        $images = array();
        if (!empty($this->request->post['ccp_images'])) {
            foreach (explode(',', (string)$this->request->post['ccp_images']) as $image) {
                $image = trim($image);
                if ($this->isReviewImagePath($image) && (isset($pending[$image]) || isset($existing_images[$image])) && is_file($this->reviewImageFile($image))) {
                    $images[] = $image;
                }
            }
        }
        $limit = min(10, max(0, (int)$this->config->get('theme_codecart_pro_reviews_photos_limit')));
        if ($limit) { $images = array_slice(array_unique($images), 0, $limit); } else { $images = array(); }
        $real_buyer = $this->isRealBuyer($product_id) ? 1 : 0;
        $exists = $existing_row;
        if ($exists->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_review_extra` SET product_id = '" . (int)$product_id . "', plus_text = '" . $this->db->escape($plus) . "', minus_text = '" . $this->db->escape($minus) . "', images = '" . $this->db->escape(json_encode($images)) . "', real_buyer = '" . (int)$real_buyer . "' WHERE review_id = '" . (int)$review_id . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_review_extra` SET review_id = '" . (int)$review_id . "', product_id = '" . (int)$product_id . "', plus_text = '" . $this->db->escape($plus) . "', minus_text = '" . $this->db->escape($minus) . "', images = '" . $this->db->escape(json_encode($images)) . "', real_buyer = '" . (int)$real_buyer . "', date_added = NOW()");
        }

        foreach ($images as $image) {
            unset($pending[$image]);
        }
        $this->session->data['ccp_review_pending_uploads'] = $pending;
    }

    private function isRealBuyer($product_id) {
        if (!$this->customer->isLogged()) { return false; }
        $query = $this->db->query("SELECT o.order_id FROM `" . DB_PREFIX . "order_product` op LEFT JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id) WHERE op.product_id = '" . (int)$product_id . "' AND o.customer_id = '" . (int)$this->customer->getId() . "' AND o.order_status_id > '0' LIMIT 1");
        return (bool)$query->num_rows;
    }

    public function upload() {
        $this->load->language('extension/theme/codecart_pro');
        $json = array();
        if (!$this->isPostRequest() || !$this->isTrustedPostOrigin()) { $json['error'] = $this->language->get('ccp_error_request'); }
        if (!$json && (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_reviews_photos_status'))) { $json['error'] = $this->language->get('ccp_error_upload_disabled'); }
        if (!$json && isset($this->session->data['ccp_review_upload_last']) && (time() - (int)$this->session->data['ccp_review_upload_last']) < 2) { $json['error'] = $this->language->get('ccp_error_upload_disabled'); }
        if (!$json) {
            $this->cleanupPendingUploads();
            $this->cleanupGlobalOrphanUploads();
            $pending_limit = min(10, max(1, (int)$this->config->get('theme_codecart_pro_reviews_photos_limit')));
            $pending_count = isset($this->session->data['ccp_review_pending_uploads']) && is_array($this->session->data['ccp_review_pending_uploads']) ? count($this->session->data['ccp_review_pending_uploads']) : 0;
            if ($pending_count >= $pending_limit) {
                $json['error'] = $this->language->get('ccp_error_upload_limit');
            }
        }
        if (empty($this->request->files['file']['name']) || !is_uploaded_file($this->request->files['file']['tmp_name'])) { $json['error'] = $this->language->get('ccp_error_upload_file'); }
        if (!$json) {
            $file = $this->request->files['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = array('jpg','jpeg','png','webp');
            $max = max(1, (int)$this->config->get('theme_codecart_pro_reviews_photo_max_size')) * 1024 * 1024;
            if (!in_array($ext, $allowed, true)) { $json['error'] = $this->language->get('ccp_error_upload_type'); }
            if ((int)$file['size'] > $max) { $json['error'] = $this->language->get('ccp_error_upload_size'); }
            $image_info = !$json ? @getimagesize($file['tmp_name']) : false;
            if (!$json && (!$image_info || empty($image_info[0]) || empty($image_info[1]) || ((int)$image_info[0] * (int)$image_info[1]) > 40000000)) { $json['error'] = $this->language->get('ccp_error_upload_image'); }
            $mime_map = array(IMAGETYPE_JPEG => array('jpg','jpeg'), IMAGETYPE_PNG => array('png'), IMAGETYPE_WEBP => array('webp'));
            if (!$json && (!isset($mime_map[$image_info[2]]) || !in_array($ext, $mime_map[$image_info[2]], true))) { $json['error'] = $this->language->get('ccp_error_upload_type'); }
            if (!$json) {
                $dir = DIR_IMAGE . 'catalog/codecart_pro_reviews/';
                if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                $name = date('Ymd_His') . '_' . token(12) . '.' . $ext;
                $target = $dir . $name;
                if (@move_uploaded_file($file['tmp_name'], $target)) {
                    $path = 'catalog/codecart_pro_reviews/' . $name;
                    $this->load->model('tool/image');
                    $this->session->data['ccp_review_upload_last'] = time();
                    if (!isset($this->session->data['ccp_review_pending_uploads']) || !is_array($this->session->data['ccp_review_pending_uploads'])) {
                        $this->session->data['ccp_review_pending_uploads'] = array();
                    }
                    $this->session->data['ccp_review_pending_uploads'][$path] = time();
                    $json['success'] = true;
                    $json['path'] = $path;
                    $json['thumb'] = $this->model_tool_image->resize($path, 120, 120);
                } else { $json['error'] = $this->language->get('ccp_error_upload_move'); }
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function vote() {
        $this->load->language('extension/theme/codecart_pro');
        $json = array();
        if (!$this->isPostRequest() || !$this->isTrustedPostOrigin()) { $json['error'] = $this->language->get('ccp_error_request'); }
        $review_id = isset($this->request->post['review_id']) ? (int)$this->request->post['review_id'] : 0;
        $type = isset($this->request->post['type']) && $this->request->post['type'] == 'minus' ? 'minus' : 'plus';
        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_reviews_votes_status') || !$review_id || !$this->tableExists('codecart_pro_review_extra')) { $json['error'] = $this->language->get('ccp_error_vote'); }
        if (!isset($this->session->data['ccp_review_votes'])) { $this->session->data['ccp_review_votes'] = array(); }
        if (!$json && in_array($review_id, $this->session->data['ccp_review_votes'])) { $json['error'] = $this->language->get('ccp_error_vote_repeat'); }
        if (!$json) {
            $review_exists = $this->db->query("SELECT review_id FROM `" . DB_PREFIX . "review` WHERE review_id = '" . (int)$review_id . "' AND status = '1' LIMIT 1");
            if (!$review_exists->num_rows) {
                $json['error'] = $this->language->get('ccp_error_vote');
            }
        }
        if (!$json) {
            $exists = $this->db->query("SELECT * FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE review_id = '" . (int)$review_id . "' LIMIT 1");
            if (!$exists->num_rows) { $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_review_extra` SET review_id = '" . (int)$review_id . "', date_added = NOW()"); }
            $field = $type == 'minus' ? 'votes_minus' : 'votes_plus';
            $this->db->query("UPDATE `" . DB_PREFIX . "codecart_pro_review_extra` SET `" . $field . "` = `" . $field . "` + 1 WHERE review_id = '" . (int)$review_id . "'");
            $this->session->data['ccp_review_votes'][] = $review_id;
            $q = $this->db->query("SELECT votes_plus, votes_minus FROM `" . DB_PREFIX . "codecart_pro_review_extra` WHERE review_id = '" . (int)$review_id . "' LIMIT 1");
            $json['success'] = true; $json['votes_plus'] = (int)$q->row['votes_plus']; $json['votes_minus'] = (int)$q->row['votes_minus'];
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
