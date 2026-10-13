from pathlib import Path
import sys, unittest, json, shutil, urllib.request
ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/'tests/lifecycle'))
from test_foundation import Lab,pairs,text_sql
class AdminRuntime(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  cls.lab=Lab('opencart3051',8872)
  for sub in ('view/stylesheet/codecart_pro','view/javascript/codecart_pro','view/template/extension/theme','view/template/extension/module','language'):
   shutil.copytree(ROOT/'source/upload/admin'/sub,cls.lab.web/'admin'/sub,dirs_exist_ok=True)
 @classmethod
 def tearDownClass(cls):
  (ROOT/'evidence/validation/admin-runtime.json').write_text(json.dumps({'platform':'OpenCart 3.0.5.1','public_http':True,'fixtures':'synthetic','checks':['page-local assets and native routes','last marker and standard save active tab','settings JSON multipart roundtrip','blog standard form create/reopen','review standard form save/reopen'],'runtime_credentials_logged':False},indent=2),encoding='utf-8')
 def test_page_local_assets_and_native_pages(self):
  for route in ('extension/theme/codecart_pro','extension/module/codecart_pro_blog','extension/module/codecart_pro_review','extension/module/codecart_pro_blog/form','extension/module/codecart_pro_blog/categoryForm'):
   page=self.lab.admin(route)
   self.assertTrue('theme_admin.css' in page,route)
   self.assertFalse('Fatal error' in page,route)
   self.assertFalse('ccp-admin-opencart-toggle' in page,route)
  for route in ('common/dashboard','catalog/product','sale/order'):
   page=self.lab.admin(route)
   self.assertFalse('theme_admin.css' in page,route)
   self.assertFalse('theme_admin_ux.js' in page,route)
 def test_last_marker_and_standard_save_active_tab(self):
  page=self.lab.admin('extension/theme/codecart_pro')
  form=page.split('id="form-codecart-pro"',1)[1].split('</form>',1)[0]
  self.assertRegex(form,r'<input type="hidden" name="ccp_settings_complete" value="1" />\s*$')
  original=self.lab.settings()
  try:
   posted=dict(original,ccp_settings_complete='1',ccp_active_tab='tab-header',ccp_scroll_top='235',theme_codecart_pro_footer_signature={1:'Admin persistence fixture'})
   url,response=self.lab.request('extension/theme/codecart_pro',posted,True)
   self.assertIn('ccp_tab=tab-header',url)
   self.assertIn('ccp_scroll=235',url)
   self.assertEqual('Admin persistence fixture',self.lab.settings()['theme_codecart_pro_footer_signature']['1'])
  finally:self.lab.save(original)
 def test_json_import_export_roundtrip(self):
  original=self.lab.settings()
  try:
   content=json.dumps({'settings':dict(original,theme_codecart_pro_footer_signature={1:'Admin import fixture'})}).encode()
   boundary='CodeCartAdminFixtureBoundary'
   body=(f'--{boundary}\r\nContent-Disposition: form-data; name="ccp_import_file"; filename="settings.json"\r\nContent-Type: application/json\r\n\r\n'.encode()+content+f'\r\n--{boundary}--\r\n'.encode())
   req=urllib.request.Request(self.lab.base+'admin/index.php?route=extension/theme/codecart_pro/importSettings&user_token='+self.lab.token,data=body,headers={'Content-Type':'multipart/form-data; boundary='+boundary})
   with self.lab.opener.open(req,timeout=60) as r:page=r.read().decode()
   self.assertFalse('Fatal error' in page)
   self.assertEqual('Admin import fixture',self.lab.settings()['theme_codecart_pro_footer_signature']['1'])
  finally:self.lab.save(original)
 def test_blog_standard_form_create_reopen(self):
  slug='admin-ui-synthetic-fixture';aid=0
  try:
   page=self.lab.admin('extension/module/codecart_pro_blog/form',{'article':{'keyword':slug,'status':0,'sort_order':7,'date_available':'2026-10-12'},'description':{1:{'title':'Admin UI synthetic article','description':'<p>Local UI fixture</p>','meta_title':'Admin test'}}})
   rows=self.lab.sql('SELECT article_id FROM oc_codecart_pro_blog_article WHERE keyword='+text_sql(slug)+';').splitlines()
   self.assertTrue(len(rows)>1,'Blog public form must create a real draft')
   aid=int(rows[1]);page=self.lab.admin('extension/module/codecart_pro_blog/form&article_id='+str(aid))
   self.assertIn('Admin UI synthetic article',page)
   self.assertIn('Local UI fixture',page)
  finally:
   if aid:
    self.lab.admin('extension/module/codecart_pro_blog/delete',{'selected':[aid]})
 def test_review_standard_form_save_reopen(self):
  author='Admin UI synthetic review';rid=0
  try:
   self.lab.sql('INSERT INTO oc_review SET product_id=43,customer_id=0,author='+text_sql(author)+",text='Original local review',rating=4,status=0,date_added=NOW(),date_modified=NOW();")
   rid=int(self.lab.sql('SELECT MAX(review_id) FROM oc_review WHERE author='+text_sql(author)+';').splitlines()[1])
   self.lab.admin('extension/module/codecart_pro_review/edit&review_id='+str(rid),{'author':author,'text':'Edited local review persists through standard save','rating':5,'status':1,'plus_text':'Clear layout','minus_text':'','admin_reply':'Synthetic local reply','real_buyer':0})
   page=self.lab.admin('extension/module/codecart_pro_review/edit&review_id='+str(rid))
   self.assertIn('Edited local review persists through standard save',page)
   self.assertIn('Synthetic local reply',page)
   self.assertIn('Clear layout',page)
  finally:
   if rid:self.lab.sql('DELETE FROM oc_review WHERE review_id='+str(rid)+'; DELETE FROM oc_codecart_pro_review_extra WHERE review_id='+str(rid)+';')
if __name__=='__main__':unittest.main()
