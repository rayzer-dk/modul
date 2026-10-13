<?php
class ModelExtensionThemeCodecartProProduct extends Model {
    private function tableExists() {
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "codecart_pro_product_changelog") . "'");
        return (bool)$query->num_rows;
    }

    public function saveChangelog($product_id, $values) {
        $product_id = (int)$product_id;
        if ($product_id <= 0 || !$this->tableExists()) {
            return false;
        }

        $clean = array();
        foreach ((array)$values as $language_id => $changelog) {
            $language_id = (int)$language_id;
            $changelog = trim((string)$changelog);
            if ($language_id > 0 && $changelog !== '') {
                $clean[$language_id] = $changelog;
            }
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "codecart_pro_product_changelog` WHERE product_id = '" . $product_id . "'");
        if (!$clean) {
            return true;
        }

        $rows = array();
        foreach ($clean as $language_id => $changelog) {
            $rows[] = "('" . $product_id . "','" . (int)$language_id . "','" . $this->db->escape($changelog) . "',NOW())";
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "codecart_pro_product_changelog` (`product_id`,`language_id`,`changelog`,`date_modified`) VALUES " . implode(',', $rows));

        return true;
    }

    public function getChangelog($product_id) {
        $product_id = (int)$product_id;
        $result = array();
        if ($product_id <= 0 || !$this->tableExists()) {
            return $result;
        }

        $query = $this->db->query("SELECT language_id, changelog FROM `" . DB_PREFIX . "codecart_pro_product_changelog` WHERE product_id = '" . $product_id . "'");
        foreach ($query->rows as $row) {
            $result[(int)$row['language_id']] = (string)$row['changelog'];
        }

        return $result;
    }
}
