"""Package all exported pages, images, fonts and interactions in one offline HTML."""
from pathlib import Path
import base64
import json
import mimetypes
import posixpath
import re

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / 'hai-phat-vina-demo'
OUTPUT = ROOT / 'HAI-PHAT-VINA-DEMO.html'
pages = {p.name: p.read_text(encoding='utf-8') for p in sorted(SOURCE.glob('*.html'))}
assets = {}


def data_url(name):
    name = name.split('?')[0]
    if name in assets:
        return assets[name]
    path = SOURCE / name
    if not path.is_file():
        raise FileNotFoundError(name)
    mime = mimetypes.guess_type(name)[0] or 'application/octet-stream'
    if name.endswith('.css'):
        content = path.read_text(encoding='utf-8')
        def replace_url(match):
            target = match.group(1).strip(' \t\r\n\"\'')
            if target.startswith(('data:', '#', 'http:', 'https:')):
                return match.group(0)
            relative = posixpath.normpath(posixpath.join(posixpath.dirname(name), target))
            return 'url("' + data_url(relative) + '")'
        content = re.sub(r'url\(([^)]+)\)', replace_url, content).encode('utf-8')
    elif name == 'assets/js/demo.js':
        content = (ROOT / 'dev/demo-assets/demo.js').read_bytes()
    else:
        content = path.read_bytes()
    uri = 'data:' + mime + ';base64,' + base64.b64encode(content).decode('ascii')
    assets[name] = uri
    return uri


for html in pages.values():
    for name in re.findall(r'(?:src|href)=[\"\'](assets/[^\"\'?#]+)', html):
        data_url(name)
for image in (SOURCE / 'assets/images').glob('*'):
    if image.is_file():
        data_url(image.relative_to(SOURCE).as_posix())

payload = json.dumps({'pages': pages, 'assets': assets}, ensure_ascii=False).replace('<', '\\u003c')
shell = '''<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="icon" href="data:,"><title>Hải Phát Vina — Demo</title>
<style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#eff4ef}iframe{position:fixed;inset:0;width:100%;height:100%;border:0;background:#fff}#loading{position:absolute;inset:0;display:grid;place-items:center;font:16px Arial,sans-serif;color:#163e35}</style></head>
<body><div id="loading" role="status">Đang mở Hải Phát Vina…</div><iframe id="website" title="Website Hải Phát Vina — bản xem thử"></iframe>
<noscript><p>Bật JavaScript để xem demo tương tác.</p></noscript>
<script type="application/json" id="demo-data">__PAYLOAD__</script>
<script>
(() => {
 'use strict';
 const {pages,assets}=JSON.parse(document.getElementById('demo-data').textContent);
 const frame=document.getElementById('website');
 const safeJSON=(value)=>JSON.stringify(value).replace(/</g,'\\u003c');
 const images=Object.fromEntries(Object.entries(assets).filter(([path])=>path.startsWith('assets/images/')));
 function render(){
  let target='index.html';
  try{target=decodeURIComponent(location.hash.slice(1))||target;}catch(_){}
  const [path,anchor='']=target.split('#');
  const [requested,query='']=path.split('?');
  const page=Object.prototype.hasOwnProperty.call(pages,requested)?requested:'404.html';
  let html=pages[page].replace(/\\b(src|href)=["'](assets\\/[^"'?#]+)(?:\\?[^"']*)?["']/g,(all,attr,path)=>assets[path]?`${attr}="${assets[path]}"`:all);
  const bootstrap='<script>window.hpvDemoQuery='+safeJSON(query)+';window.hpvDemoAssets='+safeJSON(images)+';window.hpvDemoDocumentUrl='+safeJSON(location.href)+';window.addEventListener("load",()=>{const target=document.getElementById('+safeJSON(anchor)+');if(target)target.scrollIntoView({behavior:"instant"});});<'+ '/script>';
  html=html.replace('</head>',bootstrap+'</head>');
  const title=pages[page].match(/<title>([\\s\\S]*?)<\\/title>/i);
  document.title=title?new DOMParser().parseFromString(title[1],'text/html').body.textContent:'Hải Phát Vina';
  frame.srcdoc=html;
 }
 window.addEventListener('message',(event)=>{
  if(event.source!==frame.contentWindow||event.data?.type!=='hpv-demo-navigate'||typeof event.data.target!=='string')return;
  const target=event.data.target;
  if(!Object.prototype.hasOwnProperty.call(pages,target.split(/[?#]/)[0]))return;
  const hash='#'+encodeURIComponent(target);
  if(location.hash===hash)render();else location.hash=hash;
 });
 window.addEventListener('hashchange',render);
 frame.addEventListener('load',()=>{document.getElementById('loading').hidden=true;});
 render();
})();
</script></body></html>'''
OUTPUT.write_text(shell.replace('__PAYLOAD__', payload), encoding='utf-8')
print(json.dumps({'file': str(OUTPUT), 'pages': len(pages), 'assets': len(assets), 'bytes': OUTPUT.stat().st_size}, ensure_ascii=False))
