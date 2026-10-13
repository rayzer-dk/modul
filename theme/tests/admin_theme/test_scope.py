from pathlib import Path
import re,unittest
ROOT=Path(__file__).resolve().parents[2]
class ScopeContract(unittest.TestCase):
    def test_no_native_admin_chrome_restyling(self):
        css=(ROOT/'source/upload/admin/view/stylesheet/codecart_pro/theme_admin.css').read_text(encoding='utf-8')
        self.assertNotRegex(css,r'(?:html|body)[^{]*#(?:column-left|content)', 'Theme pages must not restyle native admin chrome')
    def test_reduced_motion_and_keyboard_focus(self):
        css=(ROOT/'source/upload/admin/view/stylesheet/codecart_pro/theme_admin.css').read_text(encoding='utf-8')
        self.assertIn('prefers-reduced-motion',css)
        self.assertIn(':focus-visible',css)
if __name__=='__main__':unittest.main()
