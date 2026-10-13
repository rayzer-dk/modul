<?php
class ControllerExtensionModuleCodecartProReviews extends Controller {
    public function index() {
        if (!$this->isCodeCartProThemeActive()) { return ''; }
        if (!(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_reviews_block_status')) { return ''; }
        $this->load->language('extension/module/codecart_pro_reviews');
        $this->load->model('tool/image');
        $limit = min(50, max(1, (int)$this->config->get('theme_codecart_pro_reviews_limit')));
        $query = $this->db->query("SELECT r.review_id, r.product_id, r.author, r.text, r.rating, r.date_added, pd.name, p.image FROM `" . DB_PREFIX . "review` r LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (r.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') LEFT JOIN `" . DB_PREFIX . "product` p ON (r.product_id = p.product_id) WHERE r.status = '1' ORDER BY r.date_added DESC LIMIT " . (int)$limit);
        $data = array('heading_title' => $this->language->get('heading_title'), 'reviews' => array());
        foreach ($query->rows as $row) {
            $data['reviews'][] = array('author' => $row['author'], 'text' => utf8_substr(strip_tags(html_entity_decode($row['text'], ENT_QUOTES, 'UTF-8')), 0, 180), 'rating' => (int)$row['rating'], 'product' => $row['name'], 'date_added' => date($this->language->get('date_format_short'), strtotime($row['date_added'])), 'thumb' => $row['image'] ? $this->model_tool_image->resize($row['image'], 90, 90) : '', 'href' => $this->url->link('product/product', 'product_id=' . (int)$row['product_id']));
        }
        return $this->load->view('extension/module/codecart_pro_reviews', $data);
    }

    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        return ($config_theme === 'codecart_pro' || $directory === 'codecart_pro' || (string)$this->config->get('config_template') === 'codecart_pro');
    }

}
