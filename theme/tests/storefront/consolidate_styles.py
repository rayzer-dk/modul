from pathlib import Path
import re,json
import zipfile
p=Path('source/upload/catalog/view/theme/codecart_pro/stylesheet/codecart_pro.css');s=zipfile.ZipFile('baseline/Theme_CodeCart_Pro_v8.76.ocmod.zip').read('upload/catalog/view/theme/codecart_pro/stylesheet/codecart_pro.css').decode('utf-8')
removed=[]
def endblock(s,start):
 depth=1;i=start;quote=None
 while i<len(s):
  if quote:
   if s[i]=='\\': i+=2;continue
   if s[i]==quote:quote=None
  elif s.startswith('/*',i):i=s.index('*/',i)+2;continue
  elif s[i] in "\"'":quote=s[i]
  elif s[i]=='{':depth+=1
  elif s[i]=='}':
   depth-=1
   if depth==0:return i
  i+=1
 raise ValueError('unbalanced CSS')
def owned(sel):
 if re.search(r'\.ccp-header-(?:main-row(?:\.ccp-header-main-row-no-(?:contacts|phones))?|center|cart-slot)$',sel.strip()) and not 'dark' in sel:return True
 if re.search(r'\.ccp-product-card__actions$|\.ccp-card-qty$',sel.strip()) and not any(x in sel for x in ['ccp-hide','ccp-information','ccp-view-list']):return True
 if '.ccp-cart-popup__' in sel or re.search(r'\.ccp-cart-popup(?:\.|[ >:]|$)',sel):return True
 if re.search(r'\.ccp-gallery-main(?:[ .>:]|$)',sel) and '.ccp-thumb-nav' not in sel and '.ccp-gallery-zoom' not in sel:return True
 if re.search(r'\.ccp-product-card__(?:body|name|desc|price)(?: a)?$',sel.strip()) and not any(x in sel for x in ['ccp-hide','ccp-information','ccp-view-list']):return True
 return False
def walk(s):
 out=[];pos=0
 while pos<len(s):
  brace=s.find('{',pos)
  if brace<0:out.append(s[pos:]);break
  # Preserve comments preceding a selector independently.
  start=pos
  while True:
   m=re.match(r'\s*/\*.*?\*/',s[start:brace],re.S)
   if not m:break
   out.append(s[start:start+m.end()]);start+=m.end()
  head=s[start:brace];end=endblock(s,brace+1);body=s[brace+1:end]
  if head.lstrip().startswith('@'):
   if any(head.lstrip().startswith(x) for x in ['@media','@supports','@layer']):body=walk(body)
   out.append(head+'{'+body+'}')
  else:
   selectors=[x.strip() for x in head.split(',')];keep=[x for x in selectors if not owned(x)]
   if all(re.search(r'\.ccp-simple-(?:summary|checkout-side)$',x) for x in keep) and keep:
    body=re.sub(r'(?:^|;)\s*(?:position|top|bottom)\s*:[^;]+;?', ';',body,flags=re.I)
   removed.extend(x for x in selectors if owned(x))
   if keep:out.append('\n'+',\n'.join(keep)+'{'+body+'}')
  pos=end+1
 return ''.join(out)
result=walk(s)
canonical='''/* Canonical storefront components: shared light/dark tokens and responsive sizing.
   Replaces the historic duplicate popup, gallery canvas and card text rules. */
body.ccp-theme .ccp-header-compact-shop .ccp-header-main-row { display:grid; grid-template-columns:minmax(140px,220px) minmax(0,1fr) auto; align-items:center; gap:18px; padding:12px 0; }
body.ccp-theme .ccp-header-compact-shop .ccp-header-main-row:has(.ccp-header-contacts) { grid-template-columns:minmax(140px,200px) minmax(0,1fr) minmax(130px,190px) auto; }
body.ccp-theme .ccp-header-compact-shop .ccp-header-center { display:flex; align-items:center; gap:12px; min-width:0; }
body.ccp-theme .ccp-header-compact-shop .ccp-header-cart-slot { display:flex; align-items:center; justify-content:flex-end; grid-column:auto; grid-row:auto; width:auto; min-width:0; gap:8px; margin:0; }
body.ccp-theme .ccp-header-compact-shop .ccp-header-center .ccp-search-wrap { flex:1; min-width:0; }
body.ccp-theme .ccp-product-card__actions { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:auto; }
body.ccp-theme .ccp-product-card__actions .ccp-card-qty { width:90px; min-width:90px; max-width:90px; flex:0 0 90px; }
body.ccp-theme .ccp-product-card__actions .ccp-card-cart { flex:1 1 78px; width:auto; min-width:78px; }
body.ccp-theme .ccp-gallery-videos { display:flex; flex-wrap:wrap; gap:8px; padding:8px 0; }
body.ccp-theme { font-family:system-ui,-apple-system,"Segoe UI",Arial,sans-serif; }
body.ccp-theme .ccp-product-card__body { padding:12px; gap:6px; min-width:0; display:flex; flex-direction:column; flex:1; }
body.ccp-theme .ccp-product-card__name { margin:0; font-size:15px; line-height:1.4; font-weight:650; min-height:2.8em; overflow-wrap:anywhere; }
body.ccp-theme .ccp-product-card__name a { color:var(--ccp-text); display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:var(--ccp-title-lines,2); overflow:hidden; }
body.ccp-theme .ccp-product-card__desc { margin:0; color:var(--ccp-muted); font-size:13px; line-height:1.5; min-height:0; display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; }
body.ccp-theme .ccp-product-card__price { font-size:20px; font-weight:750; line-height:1.4; color:var(--ccp-price,var(--ccp-primary)); overflow-wrap:anywhere; }
body.ccp-theme #product-product .ccp-gallery-main { display:flex; align-items:center; justify-content:center; position:relative; width:100%; max-width:100%; aspect-ratio:4/3; height:auto; min-height:0; max-height:none; padding:0; margin:0; overflow:hidden; background:transparent; border:1px solid var(--ccp-border); border-radius:var(--ccp-radius); box-shadow:none; }
body.ccp-theme #product-product .ccp-gallery-ratio-1x1 .ccp-gallery-main { aspect-ratio:1; }
body.ccp-theme #product-product .ccp-gallery-ratio-16x9 .ccp-gallery-main { aspect-ratio:16/9; }
body.ccp-theme #product-product .ccp-gallery-main picture { display:block; width:100%; height:100%; }
body.ccp-theme #product-product .ccp-gallery-main img { display:block; width:100%; height:100%; max-width:100%; max-height:100%; object-fit:contain; object-position:center; padding:0; margin:0; background:transparent; transform:none; }
body.ccp-theme .ccp-cart-popup { display:none; position:fixed; inset:0; z-index:9999; overflow:auto; padding:24px 12px; background:rgba(15,23,42,.45); }
body.ccp-theme .ccp-cart-popup.is-visible { display:flex; align-items:flex-start; justify-content:center; }
body.ccp-theme .ccp-cart-popup__backdrop { position:fixed; inset:0; }
body.ccp-theme .ccp-cart-popup__dialog { position:relative; width:min(960px,100%); max-width:100%; margin:auto; background:var(--ccp-card); color:var(--ccp-text); border:1px solid var(--ccp-border); border-radius:var(--ccp-radius); box-shadow:0 24px 70px rgba(15,23,42,.25); }
body.ccp-theme .ccp-cart-popup__header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 22px; border-bottom:1px solid var(--ccp-border); }
body.ccp-theme .ccp-cart-popup__title { margin:0; font-size:23px; font-weight:750; color:var(--ccp-text); }
body.ccp-theme .ccp-cart-popup__close { width:40px; height:40px; border:1px solid var(--ccp-border); border-radius:10px; background:var(--ccp-surface-soft); color:var(--ccp-text); font-size:24px; flex-shrink:0; }
body.ccp-theme .ccp-cart-popup__body { padding:20px 22px; }
body.ccp-theme .ccp-cart-popup__product { display:grid; grid-template-columns:90px minmax(0,1fr) 100px 105px 110px; gap:16px; align-items:center; padding:16px; border:1px solid var(--ccp-border); border-radius:var(--ccp-radius); background:var(--ccp-surface-soft); }
body.ccp-theme .ccp-cart-popup__image-wrap { width:90px; height:80px; display:flex; align-items:center; justify-content:center; }
body.ccp-theme .ccp-cart-popup__image { width:100%; height:100%; object-fit:contain; }
body.ccp-theme .ccp-cart-popup__name { color:var(--ccp-primary); font-size:16px; font-weight:650; overflow-wrap:anywhere; line-height:1.45; }
body.ccp-theme .ccp-cart-popup__meta-label { color:var(--ccp-muted); font-size:12px; margin-bottom:5px; }
body.ccp-theme .ccp-cart-popup__qty { display:grid; grid-template-columns:28px 1fr 28px; border:1px solid var(--ccp-border); border-radius:10px; height:38px; overflow:hidden; }
body.ccp-theme .ccp-cart-popup__qty-btn,body.ccp-theme .ccp-cart-popup__qty-value { display:flex; align-items:center; justify-content:center; font-weight:700; color:var(--ccp-text); }
body.ccp-theme .ccp-cart-popup__qty-btn { background:var(--ccp-card); }
body.ccp-theme .ccp-cart-popup__price,body.ccp-theme .ccp-cart-popup__total { font-size:16px; font-weight:700; color:var(--ccp-text); overflow-wrap:anywhere; }
body.ccp-theme .ccp-cart-popup__footer-row { display:grid; grid-template-columns:minmax(0,1fr); gap:14px; padding-top:18px; margin-top:18px; border-top:1px solid var(--ccp-border); }
body.ccp-theme .ccp-cart-popup__summary { text-align:right; color:var(--ccp-text); font-size:18px; font-weight:700; }
body.ccp-theme .ccp-cart-popup__summary-value { color:var(--ccp-primary); }
body.ccp-theme .ccp-cart-popup__actions { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.4fr); gap:12px; margin-left:auto; width:min(640px,100%); }
/* Scope specificity deliberately includes the public popup ID used by brand overlays.
   Only text-sizing properties are protected; storefront colors/custom CSS still win. */
body.ccp-theme #ccp-cart-popup .ccp-cart-popup__continue,body.ccp-theme #ccp-cart-popup .ccp-cart-popup__checkout { display:flex; align-items:center; justify-content:center; width:100%; min-width:0; height:auto!important; min-height:46px; padding:10px 16px!important; white-space:normal!important; overflow-wrap:anywhere; line-height:1.4!important; font-size:15px!important; letter-spacing:normal; text-align:center; border-radius:var(--ccp-button-radius); font-weight:650; }
body.ccp-theme .ccp-cart-popup__continue { background:var(--ccp-card); border:1px solid var(--ccp-border); color:var(--ccp-primary); }
body.ccp-theme .ccp-cart-popup__checkout { background:var(--ccp-primary); border:1px solid var(--ccp-primary); color:#fff; }
body.ccp-theme :is(a,button,input,select,textarea):focus-visible { outline:3px solid var(--ccp-primary); outline-offset:3px; }
@media(min-width:1025px) {
 body.ccp-theme .ccp-simple-checkout-grid { align-items:start; }
 body.ccp-theme .ccp-simple-checkout-side { position:sticky; top:var(--ccp-checkout-sticky-top,16px); align-self:start; }
 body.ccp-theme .csp-flow .csp-cart-card { position:static!important; align-self:stretch!important; height:auto!important; }
 body.ccp-theme .csp-flow .csp-cart-card .csp-order-summary-panel { position:sticky!important; top:var(--ccp-checkout-sticky-top,16px)!important; align-self:start; }
 body.ccp-theme .ccp-simple-summary,body.ccp-theme .csp-order-summary-panel { max-height:calc(100dvh - var(--ccp-checkout-sticky-top,16px) - 16px); overflow-y:auto; overscroll-behavior:contain; }
}
@media(max-width:1024px) {
 body.ccp-theme .ccp-simple-checkout-side,body.ccp-theme .csp-flow .csp-cart-card,body.ccp-theme .csp-flow .csp-cart-card .csp-order-summary-panel { position:static!important; max-height:none; }
 body.ccp-theme .ccp-simple-summary,body.ccp-theme .csp-order-summary-panel { max-height:none; overflow:visible; }
}
@media(max-width:991px) {
 body.ccp-theme .ccp-header-compact-shop .ccp-header-main-row,body.ccp-theme .ccp-header-compact-shop .ccp-header-main-row:has(.ccp-header-contacts) { grid-template-columns:minmax(0,1fr) auto; gap:8px; padding:10px 0; }
 body.ccp-theme .ccp-header-compact-shop .ccp-header-center { display:none; }
}
@media(max-width:767px) {
 body.ccp-theme .ccp-product-card__actions .ccp-card-qty { width:70px; min-width:70px; max-width:70px; flex-basis:70px; }
 body.ccp-theme .ccp-cart-popup { padding:12px; }
 body.ccp-theme .ccp-cart-popup__header,body.ccp-theme .ccp-cart-popup__body { padding:14px; }
 body.ccp-theme .ccp-cart-popup__product { grid-template-columns:64px minmax(0,1fr); gap:10px; padding:12px; }
 body.ccp-theme .ccp-cart-popup__image-wrap { width:64px; height:64px; }
 body.ccp-theme .ccp-cart-popup__qty-wrap,body.ccp-theme .ccp-cart-popup__price-wrap,body.ccp-theme .ccp-cart-popup__total-wrap { grid-column:1/-1; display:flex; justify-content:space-between; align-items:center; gap:12px; }
 body.ccp-theme .ccp-cart-popup__actions { grid-template-columns:minmax(0,1fr); width:100%; }
 body.ccp-theme .ccp-cart-popup__summary { font-size:16px; overflow-wrap:anywhere; }
 body.ccp-theme .ccp-product-card__body { padding:10px; gap:5px; }
 body.ccp-theme .ccp-product-card__name { font-size:13px; }
 body.ccp-theme .ccp-product-card__price { font-size:17px; }
}
@media(prefers-reduced-motion:reduce) { body.ccp-theme *,body.ccp-theme *::before,body.ccp-theme *::after { scroll-behavior:auto!important; animation-duration:.01ms!important; transition-duration:.01ms!important; } }
'''
p.write_text(canonical+'\n'+result,encoding='utf-8')
Path('evidence/storefront-css-consolidation.json').write_text(json.dumps({'before_bytes':len(s.encode()),'after_bytes':len(p.read_bytes()),'removed_selectors':removed,'removed_selector_count':len(removed)},ensure_ascii=False,indent=2),encoding='utf-8')
print('CSS',len(s.encode()),'=>',len(p.read_bytes()),'removed selectors',len(removed))
