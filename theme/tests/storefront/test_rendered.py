"""Rendered storefront regressions against the disposable ocStore server."""
import re, unittest, urllib.request, sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1] / "lifecycle"))
from test_foundation import Lab
BASE = 'http://127.0.0.1:8871/index.php?route='
def page(route):
    return urllib.request.urlopen(BASE + route, timeout=30).read().decode('utf-8')
class Storefront(unittest.TestCase):
    def test_footer_has_no_checkout_trust_after_footer(self):
        html = page('common/home')
        self.assertNotIn('ccp-checkout-trust-panel', html.split('</footer>', 1)[1])
    def test_incomplete_admin_form_cannot_commit_settings(self):
        lab=Lab('ocstore3041',8871)
        original=lab.settings()
        try:
            lab.admin('extension/theme/codecart_pro',dict(original,theme_codecart_pro_primary_color='#f01234'))
            self.assertEqual(original,lab.settings(),'Incomplete/truncated form must preserve all saved settings')
        finally:
            lab.admin('extension/theme/codecart_pro',dict(original,ccp_settings_complete=1))
    def test_item_total_is_not_global_cart_total(self):
        import http.cookiejar, json, urllib.parse
        opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        def add(pid,qty):
            return json.loads(opener.open(BASE+'checkout/cart/add',urllib.parse.urlencode({'product_id':pid,'quantity':qty}).encode()).read())
        first=add(43,3);second=add(40,1)
        self.assertEqual({'quantity':3,'price':'500.00 грн.','total':'1500.00 грн.'},first['ccp_cart_item'])
        self.assertEqual({'quantity':1,'price':'101.00 грн.','total':'101.00 грн.'},second['ccp_cart_item'])
        self.assertIn('1601.00',second['total'])
    def test_anonymous_cart_item_respects_price_visibility(self):
        import json, http.cookiejar
        lab=Lab('ocstore3041',8871)
        original=lab.sql("SELECT setting_id,value FROM oc_setting WHERE store_id=0 AND `key`='config_customer_price';").splitlines()[1:]
        self.assertEqual(1,len(original),'Fixture must have exactly one store price-visibility setting')
        setting_id,value=original[0].split('\t',1)
        try:
            for private in (1,0):
                lab.sql("UPDATE oc_setting SET value='"+str(private)+"' WHERE setting_id="+str(int(setting_id))+";")
                opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
                result=json.loads(opener.open(BASE+'checkout/cart/add',b'product_id=43&quantity=1').read())
                self.assertIn('success',result)
                if private:
                    self.assertNotIn('ccp_cart_item',result,'Anonymous response must not disclose restricted item price or subtotal')
                else:
                    self.assertEqual('500.00 грн.',result['ccp_cart_item']['price'])
        finally:
            from test_foundation import text_sql
            lab.sql("UPDATE oc_setting SET value="+text_sql(value)+" WHERE setting_id="+str(int(setting_id))+";")
    def test_native_checkout_fallback_and_theme_checkout(self):
        import http.cookiejar
        lab=Lab('ocstore3041',8871);original=lab.settings()
        opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        opener.open(BASE+'checkout/cart/add',b'product_id=43&quantity=1').read()
        try:
            for status in (0,1):
                lab.save(dict(original,theme_codecart_pro_simple_checkout_status=status))
                html=opener.open(BASE+'checkout/checkout').read().decode()
                self.assertEqual(not status,'collapse-checkout-option' in html)
                self.assertEqual(bool(status),'ccp-simple-summary-product' in html)
                self.assertNotIn('Fatal error',html)
        finally: lab.save(original)
    def test_direct_product_link_without_category(self):
        self.assertIn('data-ccp-gallery-main', page('product/product&product_id=43'))
    def test_gallery_exposes_only_processed_working_sources(self):
        html = page('product/product&product_id=43&path=20')
        self.assertNotIn('data-ccp-original=', html)
        sources = re.findall(r'data-ccp-(?:main|large|thumb)="([^"]+)"', html)
        self.assertGreater(len(sources), 2)
        self.assertTrue(all('/image/cache/' in source for source in sources))
if __name__ == '__main__': unittest.main()
