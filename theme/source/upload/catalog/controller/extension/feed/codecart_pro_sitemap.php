<?php
class ControllerExtensionFeedCodecartProSitemap extends Controller {
    private function isCodeCartProThemeActive() {
        $config_theme = (string)$this->config->get('config_theme');
        $directory_key = $config_theme ? 'theme_' . $config_theme . '_directory' : '';
        $directory = $directory_key ? (string)$this->config->get($directory_key) : '';
        $config_template = (string)$this->config->get('config_template');
        $template_directory = (string)$this->config->get('template_directory');

        return ($config_theme === 'codecart_pro' || $config_theme === 'theme_codecart_pro' || $directory === 'codecart_pro' || $config_template === 'codecart_pro' || strpos($template_directory, 'codecart_pro/') === 0);
    }

    public function index() {
        $this->response->addHeader('Content-Type: application/xml; charset=utf-8');

        if (!$this->isCodeCartProThemeActive() || !(int)$this->config->get('theme_codecart_pro_status') || !(int)$this->config->get('theme_codecart_pro_blog_status') || !(int)$this->config->get('theme_codecart_pro_blog_sitemap_status')) {
            $this->response->setOutput("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\"></urlset>");
            return;
        }

        $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $output .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n";
        $output .= "  <url>\n";
        $output .= "    <loc>" . $this->xml($this->url->link('extension/module/codecart_pro_blog', '', true)) . "</loc>\n";
        $output .= $this->buildAlternateXml(0);
        $output .= "    <changefreq>weekly</changefreq>\n";
        $output .= "    <priority>0.70</priority>\n";
        $output .= "  </url>\n";

        if ($this->tableExists('codecart_pro_blog_article')) {
            $ccp_date_where = $this->columnExists('codecart_pro_blog_article', 'date_available') ? " AND (`date_available` IS NULL OR `date_available` <= CURDATE())" : '';
            $ccp_order = array();
            if ($this->columnExists('codecart_pro_blog_article', 'sort_order')) { $ccp_order[] = "`sort_order` ASC"; }
            $ccp_order[] = $this->columnExists('codecart_pro_blog_article', 'date_added') ? "`date_added` DESC" : "`article_id` DESC";
            $query = $this->db->query("SELECT article_id, date_added, date_modified FROM `" . DB_PREFIX . "codecart_pro_blog_article` WHERE status = '1'" . $ccp_date_where . " ORDER BY " . implode(', ', $ccp_order));
            foreach ($query->rows as $row) {
                $lastmod = !empty($row['date_modified']) ? $row['date_modified'] : $row['date_added'];
                $output .= "  <url>\n";
                $output .= "    <loc>" . $this->xml($this->url->link('extension/module/codecart_pro_blog/article', 'ccp_article_id=' . (int)$row['article_id'], true)) . "</loc>\n";
                $output .= $this->buildAlternateXml((int)$row['article_id']);
                if ($lastmod && $lastmod != '0000-00-00 00:00:00') { $output .= "    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>\n"; }
                $output .= "    <changefreq>monthly</changefreq>\n";
                $output .= "    <priority>0.60</priority>\n";
                $output .= "  </url>\n";
            }
        }

        $output .= "</urlset>";
        $this->response->setOutput($output);
    }


    private function buildAlternateXml($article_id = 0) {
        if (!(int)$this->config->get('theme_codecart_pro_blog_sitemap_hreflang_status')) { return ''; }
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $out = '';
        foreach ($languages as $language) {
            if (empty($language['status'])) { continue; }
            $hreflang = strtolower(str_replace('_', '-', (string)$language['code']));
            $href = $this->buildBlogHref((int)$language['language_id'], (string)$language['code'], (int)$article_id);
            if ($href) { $out .= "    <xhtml:link rel=\"alternate\" hreflang=\"" . $this->xml($hreflang) . "\" href=\"" . $this->xml($href) . "\" />\n"; }
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
        $keyword = $this->getSeoKeyword('ccp_blog=1', $language_id);
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

    private function columnExists($table, $column) {
        if (!$this->tableExists($table)) { return false; }
        $q = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $this->db->escape($table) . "` LIKE '" . $this->db->escape($column) . "'");
        return (bool)$q->num_rows;
    }

    private function tableExists($table) {
        $q = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$q->num_rows;
    }

    private function xml($value) {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
