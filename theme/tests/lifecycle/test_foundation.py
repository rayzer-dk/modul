"""Public HTTP lifecycle regressions against disposable local installations only."""
from pathlib import Path
import http.cookiejar, json, re, secrets, shutil, subprocess, unittest, urllib.parse, urllib.request
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
SECRET = json.loads((ROOT / '.env.audit.json').read_text())
BIN = ROOT.parent / 'audit_codecart_1_2_5/runtime/mariadb/mariadb-10.11.16-winx64/bin/mariadb.exe'
PHP = ROOT.parent / 'audit_codecart_1_2_5/runtime/php8.1/php.exe'

def text_sql(value):
    return '(CONVERT(0x' + str(value).encode().hex() + ' USING utf8mb4) COLLATE utf8mb4_unicode_ci)'

def pairs(value, prefix=''):
    if isinstance(value, dict):
        return [p for k, v in value.items() for p in pairs(v, prefix+'['+str(k)+']' if prefix else str(k))]
    if isinstance(value, list):
        return [p for k, v in enumerate(value) for p in pairs(v, prefix+'['+str(k)+']')]
    return [(prefix, str(value))]

class Lab:
    def __init__(self, name, port):
        self.name, self.database = name, 'theme_' + name
        self.web = ROOT / 'runtime/lab' / name
        self.base = 'http://127.0.0.1:' + str(port) + '/'
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        url, page = self.request('common/login', {'username': 'auditadmin', 'password': SECRET['admin_password']})
        self.token = urllib.parse.parse_qs(urllib.parse.urlsplit(url).query).get('user_token', [''])[0]
        if not self.token:
            match = re.search(r'user_token=([A-Za-z0-9]+)', page)
            self.token = match.group(1) if match else ''
        if not self.token: raise RuntimeError('Synthetic lab authentication failed')

    def sql(self, sql):
        run = subprocess.run([str(BIN), '-h127.0.0.1', '-P33078', '-uroot', '-p'+SECRET['database_password'], '--default-character-set=utf8mb4', '--batch', self.database], input=sql.encode(), capture_output=True, timeout=30, creationflags=subprocess.CREATE_NO_WINDOW)
        if run.returncode: raise RuntimeError(run.stderr.decode(errors='replace').replace(SECRET['database_password'], '[REDACTED]'))
        return run.stdout.decode(errors='replace')

    def request(self, route, data=None, authenticated=False):
        url = self.base + 'admin/index.php?route=' + route
        if authenticated: url += '&user_token=' + self.token
        with self.opener.open(url, None if data is None else urllib.parse.urlencode(pairs(data)).encode(), timeout=60) as response:
            return response.geturl(), response.read().decode('utf-8', errors='replace')

    def admin(self, route, data=None): return self.request(route, data, True)[1]
    def settings(self): return json.loads(self.admin('extension/theme/codecart_pro/exportSettings'))['settings']
    def save(self, settings): return self.admin('extension/theme/codecart_pro', dict(settings, ccp_settings_complete=1))
    def snapshot(self): return self.sql("SELECT store_id,code,`key`,value,serialized FROM oc_setting ORDER BY setting_id;")
    def deploy(self):
        shutil.copytree(ROOT / 'source/upload', self.web, dirs_exist_ok=True)
        xml = (ROOT / 'source/install.xml').read_text(encoding='utf-8')
        meta = ET.fromstring(xml)
        self.sql('UPDATE oc_modification SET xml='+text_sql(xml)+' WHERE code='+text_sql(meta.findtext('code'))+';')
        self.admin('marketplace/modification/refresh', {})

class Foundation(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.labs = [Lab('ocstore3041', 8871), Lab('opencart3051', 8872)]
        for lab in cls.labs: lab.deploy()

    def test_get_never_rewrites_settings(self):
        for lab in self.labs:
            with self.subTest(platform=lab.name):
                original = lab.settings()
                try:
                    lab.sql("UPDATE oc_setting SET value='869' WHERE `key` IN ('theme_codecart_pro_schema_version','theme_codecart_pro_blog_schema_version');")
                    before = lab.snapshot()
                    page = lab.admin('extension/theme/codecart_pro')
                    self.assertNotIn('Fatal error', page)
                    self.assertEqual(before, lab.snapshot(), 'Ordinary GET must not mutate saved settings')
                finally: lab.save(original)

    def test_off_save_preserves_pending_schema_upgrade(self):
        for lab in self.labs:
            with self.subTest(platform=lab.name):
                original = lab.settings()
                try:
                    lab.sql("UPDATE oc_setting SET value='869' WHERE `key` IN ('theme_codecart_pro_schema_version','theme_codecart_pro_blog_schema_version');")
                    lab.save(dict(original, theme_codecart_pro_status=0))
                    self.assertEqual('869', str(lab.settings()['theme_codecart_pro_schema_version']))
                    lab.save(dict(original, theme_codecart_pro_status=1))
                    self.assertEqual('870', str(lab.settings()['theme_codecart_pro_schema_version']))
                finally: lab.save(original)

    def test_failed_required_ddl_keeps_settings_and_allows_explicit_retry(self):
        for lab in self.labs:
            for action in ('save', 'install'):
                with self.subTest(platform=lab.name, action=action):
                    original = lab.settings()
                    config_path = lab.web / 'admin/config.php'
                    config_bytes = config_path.read_bytes()
                    password = secrets.token_hex(24)
                    table = 'oc_codecart_pro_quick_order'
                    backup = 'oc_foundation_ddl_quick_order_backup'
                    renamed = False
                    try:
                        lab.sql('RENAME TABLE '+table+' TO '+backup+'; CREATE TABLE '+table+' LIKE '+backup+'; ALTER TABLE '+table+' DROP COLUMN product_option_data;')
                        renamed = True
                        lab.sql("UPDATE oc_setting SET value='869' WHERE `key` IN ('theme_codecart_pro_schema_version','theme_codecart_pro_blog_schema_version');")
                        before = lab.snapshot()
                        lab.sql("CREATE USER IF NOT EXISTS 'foundation_ddl'@'127.0.0.1' IDENTIFIED BY '"+password+"'; ALTER USER 'foundation_ddl'@'127.0.0.1' IDENTIFIED BY '"+password+"'; GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,INDEX ON "+lab.database+".* TO 'foundation_ddl'@'127.0.0.1';")
                        restricted = config_bytes.decode()
                        restricted = re.sub(r"(define\('DB_USERNAME',\s*)'[^']*'", r"\1'foundation_ddl'", restricted)
                        restricted = re.sub(r"(define\('DB_PASSWORD',\s*)'[^']*'", lambda m: m.group(1)+"'"+password+"'", restricted)
                        config_path.write_text(restricted, encoding='utf-8')
                        posted = dict(original, theme_codecart_pro_status=1, theme_codecart_pro_primary_color='#abcdef')
                        if action == 'save': lab.save(posted)
                        else: lab.admin('extension/extension/theme/install&extension=codecart_pro', {})
                        self.assertEqual(before, lab.snapshot(), 'Failed mandatory DDL must preserve settings and old schema marker')
                        lab.sql("GRANT ALTER ON "+lab.database+".* TO 'foundation_ddl'@'127.0.0.1';")
                        if action == 'save': lab.save(posted)
                        else: lab.admin('extension/extension/theme/install&extension=codecart_pro', {})
                        self.assertEqual('870', str(lab.settings()['theme_codecart_pro_schema_version']))
                        self.assertTrue('product_option_data' in lab.sql('SHOW COLUMNS FROM '+table+" LIKE 'product_option_data';"))
                    finally:
                        config_path.write_bytes(config_bytes)
                        lab.sql("DROP USER IF EXISTS 'foundation_ddl'@'127.0.0.1';")
                        if renamed: lab.sql('DROP TABLE '+table+'; RENAME TABLE '+backup+' TO '+table+';')
                        lab.save(original)

    def test_save_and_reinstall_preserve_custom_settings(self):
        sentinel = {'theme_codecart_pro_container_width': 1420, 'theme_codecart_pro_category_columns_desktop': 4, 'theme_codecart_pro_category_columns_tablet': 2, 'theme_codecart_pro_primary_color': '#123456', 'theme_codecart_pro_custom_css': '/* ccp-information-mode-manual customer rule */ .example {color:red;}'}
        for lab in self.labs:
            with self.subTest(platform=lab.name):
                original = lab.settings()
                try:
                    settings = dict(original, **sentinel)
                    settings['theme_codecart_pro_status'] = 1
                    lab.save(settings)
                    saved = lab.settings()
                    self.assertGreaterEqual(int(saved['theme_codecart_pro_schema_version']), 870, 'Save must not downgrade schema')
                    for k, v in sentinel.items(): self.assertEqual(str(v), str(saved[k]), k)
                    lab.admin('extension/extension/theme/install&extension=codecart_pro', {})
                    saved = lab.settings()
                    for k, v in sentinel.items(): self.assertEqual(str(v), str(saved[k]), k)
                finally: lab.save(original)

    def test_native_ocmod_preserves_existing_isset(self):
        lab = self.labs[0]
        relative = Path('catalog/controller/extension/module/featured_product.php')
        target = lab.web / relative
        original = target.read_bytes()
        fixture = "<?php class ControllerExtensionModuleFeaturedProduct extends Controller { public function index() { if (isset($this->request->get['path'])) { return $this->request->get['path']; } return ''; } }"
        try:
            target.write_text(fixture, encoding='utf-8')
            lab.admin('marketplace/modification/refresh', {})

            modified = lab.web / 'system/storage/modification' / relative
            # ocStore stores its configured modification directory outside system/storage.
            config = (lab.web / 'config.php').read_text()
            storage = re.search(r"define\('DIR_STORAGE',\s*'([^']+)'\)", config)
            if storage: modified = Path(storage.group(1)) / 'modification' / relative
            run = subprocess.run([str(PHP), '-l', str(modified if modified.exists() else target)], capture_output=True, timeout=20)
            self.assertEqual(0, run.returncode, run.stdout.decode(errors='replace') + run.stderr.decode(errors='replace'))
        finally:
            target.write_bytes(original)
            lab.admin('marketplace/modification/refresh', {})

    def test_existing_store_text_is_not_rewritten_in_form(self):
        for lab in self.labs:
            with self.subTest(platform=lab.name):
                original = lab.settings()
                try:
                    settings = dict(original)
                    settings['theme_codecart_pro_home_benefits_text'] = {'1': 'fa-truck|Delivery by customer agreement AUDIT_SENTINEL'}
                    lab.save(settings)
                    page = lab.admin('extension/theme/codecart_pro')
                    self.assertTrue('Delivery by customer agreement AUDIT_SENTINEL' in page, 'Existing customer text must remain visible in its settings field')
                finally: lab.save(original)

    def test_enable_disable_runtime_events_are_idempotent(self):
        for lab in self.labs:
            with self.subTest(platform=lab.name):
                original = lab.settings()
                try:
                    enabled = dict(original, theme_codecart_pro_status=1)
                    lab.save(enabled)
                    lab.save(enabled)
                    self.assertEqual('total\n5\n', lab.sql("SELECT COUNT(*) AS total FROM oc_event WHERE code LIKE 'codecart_pro_%';"))
                    lab.save(dict(enabled, theme_codecart_pro_status=0))
                    self.assertEqual('total\n0\n', lab.sql("SELECT COUNT(*) AS total FROM oc_event WHERE code LIKE 'codecart_pro_%';"))
                finally: lab.save(original)

    def test_uninstall_and_fresh_install_keep_data_and_return_default_theme(self):
        for lab in self.labs:
            with self.subTest(platform=lab.name):
                original = lab.settings()
                config_before = lab.sql("SELECT `key`,value FROM oc_setting WHERE code='config' AND `key` IN ('config_theme','config_template');")
                try:
                    settings = dict(original, theme_codecart_pro_uninstall_keep_data=1)
                    lab.save(settings)
                    lab.sql("INSERT INTO oc_codecart_pro_subscriber SET email='foundation-fixture@example.invalid',name='Synthetic lifecycle';")
                    lab.admin('extension/extension/theme/uninstall&extension=codecart_pro', {})
                    self.assertNotIn('codecart_pro', lab.sql("SELECT value FROM oc_setting WHERE code='config' AND `key`='config_theme';"))
                    self.assertEqual('total\n0\n', lab.sql("SELECT COUNT(*) AS total FROM oc_event WHERE code LIKE 'codecart_pro_%';"))
                    self.assertEqual('total\n1\n', lab.sql("SELECT COUNT(*) AS total FROM oc_codecart_pro_subscriber WHERE email='foundation-fixture@example.invalid';"))
                    lab.admin('extension/extension/theme/install&extension=codecart_pro', {})
                    self.assertEqual('0', str(lab.settings()['theme_codecart_pro_status']))
                finally:
                    lab.admin('extension/extension/theme/install&extension=codecart_pro', {})
                    lab.save(original)
                    for row in config_before.splitlines()[1:]:
                        key, value = row.split('\t')
                        lab.sql('UPDATE oc_setting SET value='+text_sql(value)+' WHERE code=\'config\' AND `key`='+text_sql(key)+';')
                    lab.sql("DELETE FROM oc_codecart_pro_subscriber WHERE email='foundation-fixture@example.invalid';")

if __name__ == '__main__': unittest.main()
