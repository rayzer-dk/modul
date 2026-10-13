<?php
class ModelExtensionThemeCodecartProProduct extends Model {
    private function tableExists($table) {
        $allowed = array('codecart_pro_product_changelog');
        if (!in_array($table, $allowed, true)) {
            return false;
        }

        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$query->num_rows;
    }

    public function getChangelog($product_id, $language_id) {
        $product_id = (int)$product_id;
        $language_id = (int)$language_id;

        if ($product_id <= 0 || $language_id <= 0 || !$this->tableExists('codecart_pro_product_changelog')) {
            return '';
        }

        $query = $this->db->query("SELECT changelog FROM `" . DB_PREFIX . "codecart_pro_product_changelog` WHERE product_id = '" . $product_id . "' AND language_id = '" . $language_id . "' LIMIT 1");
        return $query->num_rows ? (string)$query->row['changelog'] : '';
    }

    public function findReviewId($product_id, $author) {
        $product_id = (int)$product_id;
        $author = trim((string)$author);

        if ($product_id <= 0 || $author === '') {
            return 0;
        }

        $query = $this->db->query("SELECT review_id FROM `" . DB_PREFIX . "review` WHERE product_id = '" . $product_id . "' AND author = '" . $this->db->escape($author) . "' ORDER BY review_id DESC LIMIT 1");
        return $query->num_rows ? (int)$query->row['review_id'] : 0;
    }
}
