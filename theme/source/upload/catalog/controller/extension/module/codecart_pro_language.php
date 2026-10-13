<?php
class ControllerExtensionModuleCodecartProLanguage extends Controller {
    public function product() {
        $this->load->language('extension/theme/codecart_pro');

        $keys = array(
            'ccp_text_delivery',
            'ccp_text_warranty',
            'ccp_text_trust_badges_default',
            'ccp_text_delivery_body_default',
            'ccp_text_faq',
            'ccp_text_share',
            'ccp_button_quick_order',
            'ccp_text_home',
            'ccp_text_catalog_short',
            'ccp_text_cart_short',
            'ccp_text_minus',
            'ccp_text_plus',
            'ccp_text_sale_ends',
            'ccp_text_days',
            'ccp_text_hours',
            'ccp_text_minutes',
            'ccp_text_seconds',
            'ccp_text_specifications',
            'ccp_text_copy_link',
            'ccp_text_in_stock',
            'ccp_text_quantity',
            'ccp_text_new',
            'ccp_text_top',
            'ccp_entry_review_plus',
            'ccp_entry_review_minus',
            'ccp_entry_review_photos',
            'ccp_text_review_photos_help',
            'ccp_entry_review_recommend'
        );

        $data = array();
        foreach ($keys as $key) {
            $value = $this->language->get($key);
            $data[$key] = ($value !== $key) ? $value : '';
        }

        return $data;
    }
}
