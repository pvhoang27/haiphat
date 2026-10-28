"""Export the local WordPress theme to a portable, offline HTML demo."""
from collections import deque
from html import escape
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit, urljoin, parse_qs, quote, unquote
from urllib.request import urlopen
from urllib.error import HTTPError
import hashlib
import json
import re
import shutil
import zipfile

ROOT = Path(__file__).resolve().parent.parent
DEST = ROOT / 'hai-phat-vina-demo'
BASE = 'http://127.0.0.1:8787'
THEME_ASSETS = '/wp-content/themes/hai-phat-vina/assets/'
VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'}


class Node:
    def __init__(self, tag='', attrs=None, raw=None):
        self.tag, self.attrs, self.raw, self.children = tag, dict(attrs or []), raw, []

    def walk(self):
        yield self
        for child in self.children:
            yield from child.walk()

    def has_class(self, cls):
        return cls in (self.attrs.get('class') or '').split()

    def text(self):
        if self.raw is not None:
            return re.sub(r'<[^>]*>', '', self.raw)
        return ''.join(child.text() for child in self.children)

    def render(self):
        if self.raw is not None:
            return self.raw
        children = ''.join(child.render() for child in self.children)
        if not self.tag:
            return children
        attrs = ''.join(' ' + key + ('' if value is None else '="' + escape(str(value), quote=True) + '"') for key, value in self.attrs.items())
        start = '<' + self.tag + attrs + '>'
        return start if self.tag in VOID else start + children + '</' + self.tag + '>'


class Document(HTMLParser):
    def __init__(self, html):
        super().__init__(convert_charrefs=False)
        self.root = Node()
        self.stack = [self.root]
        self.feed(html)

    def handle_starttag(self, tag, attrs):
        node = Node(tag, attrs)
        self.stack[-1].children.append(node)
        if tag not in VOID:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in VOID:
            self.handle_endtag(tag)

    def handle_endtag(self, tag):
        for i in range(len(self.stack) - 1, 0, -1):
            if self.stack[i].tag == tag:
                self.stack = self.stack[:i]
                return

    def handle_data(self, data):
        self.stack[-1].children.append(Node(raw=data))

    def handle_entityref(self, name):
        self.handle_data('&' + name + ';')

    def handle_charref(self, name):
        self.handle_data('&#' + name + ';')

    def handle_comment(self, data):
        self.stack[-1].children.append(Node(raw='<!--' + data + '-->'))

    def handle_decl(self, decl):
        self.stack[-1].children.append(Node(raw='<!' + decl + '>'))


def fetch(route):
    try:
        with urlopen(BASE + route, timeout=25) as response:
            return response.read()
    except HTTPError as error:
        if error.code == 404:
            return error.read()
        raise


def route_of(url, current='/'):
    absolute = urlsplit(urljoin(BASE + current, url))
    if absolute.netloc != urlsplit(BASE).netloc:
        return None
    if 's' in parse_qs(absolute.query):
        return '/?s=hpv-demo-search'
    if absolute.query:
        return None
    path = unquote(absolute.path)
    if path.startswith(('/wp-', '/feed', '/comments', '/xmlrpc')) or '/feed/' in path:
        return None
    return path or '/'


def filename(route):
    if route == '/':
        return 'index.html'
    if '?s=' in route:
        return 'tim-kiem.html'
    if route == '/hpv-demo-404/':
        return '404.html'
    return route.strip('/').replace('/', '--') + '.html'


def main():
    DEST.mkdir(exist_ok=True)
    shutil.copytree(ROOT / 'hai-phat-vina/assets', DEST / 'assets', dirs_exist_ok=True)
    queue = deque(['/', '/san-pham/', '/lien-he/', '/bao-gia/', '/gioi-thieu/', '/kien-thuc/', '/ung-dung/', '/?s=hpv-demo-search', '/hpv-demo-404/'])
    documents = {}
    while queue:
        route = queue.popleft()
        if route in documents:
            continue
        document = Document(fetch(route).decode('utf-8'))
        documents[route] = document
        if len(documents) > 65:
            raise RuntimeError('Unexpected number of public demo pages')
        for node in document.root.walk():
            if node.tag == 'a' and node.attrs.get('href'):
                target = route_of(node.attrs['href'], route)
                if target and target not in documents and target not in queue:
                    queue.append(target)
    pages = {route: filename(route) for route in documents}
    downloaded = {}

    def asset_url(value, current):
        if not value or value.startswith(('data:', '#', 'blob:')):
            return value
        url = urlsplit(urljoin(BASE + current, value))
        if url.netloc != urlsplit(BASE).netloc:
            return value
        if url.path.startswith(THEME_ASSETS):
            return 'assets/' + url.path[len(THEME_ASSETS):]
        if url.path.endswith('/style.css') and 'themes/hai-phat-vina/' in url.path:
            return None
        if url.path not in downloaded:
            suffix = Path(url.path).suffix or '.bin'
            path = 'assets/wordpress/' + hashlib.sha256(url.path.encode()).hexdigest()[:12] + suffix
            target = DEST / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(fetch(url.path + ('?' + url.query if url.query else '')))
            downloaded[url.path] = path
        return downloaded[url.path]

    def page_url(value, current):
        if not value or value.startswith(('#', 'tel:', 'mailto:', 'javascript:')):
            return value
        url = urlsplit(urljoin(BASE + current, value))
        if url.netloc != urlsplit(BASE).netloc:
            return value
        route = route_of(value, current)
        target = pages.get(route, '404.html')
        if route == current and url.fragment:
            return '#' + url.fragment
        if '?s=' in (route or ''):
            target += '?q=' + quote(parse_qs(url.query).get('s', [''])[0])
        return target + ('#' + url.fragment if url.fragment else '')

    index = []
    for route, document in documents.items():
        root = document.root
        nodes = list(root.walk())
        body = next(node for node in nodes if node.tag == 'body')
        body.attrs['data-demo-page'] = pages[route]
        title_node = next((node for node in nodes if node.tag == 'h1'), None)
        title = title_node.text().strip() if title_node else 'Hải Phát Vina'
        if '?s=' in route and title_node:
            title_node.children = [Node(raw='Tìm kiếm sản phẩm & bài viết')]
        for node in nodes:
            if node.tag == 'script':
                src = node.attrs.get('src', '')
                if '/assets/js/main.js' in src:
                    node.attrs = {'src': 'assets/js/main.js', 'defer': None}
                else:
                    node.tag, node.attrs, node.children = '', {}, []
                    continue
            elif node.tag == 'link':
                rel = node.attrs.get('rel', '').split()
                if 'stylesheet' in rel:
                    local = asset_url(node.attrs.get('href'), route)
                    if local:
                        node.attrs['href'] = local
                    else:
                        node.tag, node.attrs = '', {}
                else:
                    node.tag, node.attrs = '', {}
            elif node.tag == 'meta' and node.attrs.get('name') == 'generator':
                node.tag, node.attrs = '', {}
            elif node.tag == 'a':
                node.attrs['href'] = page_url(node.attrs.get('href'), route)
            elif node.tag in ('img', 'source'):
                if 'src' in node.attrs:
                    node.attrs['src'] = asset_url(node.attrs['src'], route)
                node.attrs.pop('srcset', None)
            elif node.tag == 'form':
                if 'data-quote-form' in node.attrs:
                    node.attrs['data-demo-form'] = ''
                    node.attrs['action'] = 'lien-he.html#contact'
                    node.attrs['method'] = 'get'
                    node.children = [child for child in node.children if not (child.tag == 'input' and child.attrs.get('type') == 'hidden')]
                elif node.attrs.get('role') == 'search':
                    node.attrs['action'] = 'tim-kiem.html'
                    node.attrs['data-demo-search'] = ''
            elif node.tag == 'noscript':
                node.children = [Node(raw='<p>Bản demo không gửi dữ liệu. Bật JavaScript để thử các thao tác tương tác.</p>')]
            if 'data-copy-article' in node.attrs:
                node.attrs['data-copy-article'] = pages[route]
            if node.has_class('topbar-inner'):
                label = Node('span', [('class', 'demo-label')])
                label.children = [Node(raw='Bản xem thử')]
                node.children.append(label)
        kind = 'Sản phẩm' if route.startswith('/san-pham/') and route.count('/') > 2 else ('Ứng dụng' if route.startswith('/ung-dung/') and route.count('/') > 2 else None)
        if not kind and any(node.has_class('article-header') for node in nodes) and not route.startswith('/ung-dung/'):
            kind = 'Cẩm nang'
        if kind:
            relevant = next((node for node in nodes if node.has_class('product-lead')), None)
            if not relevant:
                header = next((node for node in nodes if node.has_class('article-header')), None)
                relevant = next((node for node in header.walk() if node.tag == 'p'), None) if header else None
            main = next(node for node in nodes if node.tag == 'main')
            image = next((node.attrs.get('src') for node in main.walk() if node.tag == 'img'), '')
            index.append({'title': title, 'url': pages[route], 'summary': relevant.text().strip() if relevant else '', 'image': image, 'kind': kind, 'postType': 'hpv_product' if kind == 'Sản phẩm' else ('hpv_project' if kind == 'Ứng dụng' else 'post')})
        body.children.extend([
            Node('script', [('src', 'assets/js/demo-data.js'), ('defer', None)]),
            Node('script', [('src', 'assets/js/demo.js'), ('defer', None)]),
        ])
        css = Node('link', [('rel', 'stylesheet'), ('href', 'assets/css/demo.css')])
        next(node for node in nodes if node.tag == 'head').children.append(css)
        (DEST / pages[route]).write_text(root.render(), encoding='utf-8')

    (DEST / 'assets/js/demo-data.js').write_text('window.hpvDemoSearch=' + json.dumps(index, ensure_ascii=False) + ';\nwindow.hpvDemoPages=' + json.dumps(list(pages.values()), ensure_ascii=False) + ';', encoding='utf-8')
    for name in ('demo.js', 'demo.css'):
        source = ROOT / 'dev/demo-assets' / name
        shutil.copy2(source, DEST / ('assets/js' if name.endswith('.js') else 'assets/css') / name)
    (DEST / 'DOC-TOI-TRUOC.txt').write_text('HẢI PHÁT VINA — BẢN DEMO GIAO DIỆN\n\nMở file index.html bằng Chrome, Edge, Firefox hoặc Safari.\nKhông cần hosting, WordPress, cài thư viện hay chạy lệnh.\nCó thể xem trang chủ, sản phẩm, ứng dụng, cẩm nang, báo giá và liên hệ.\nTìm kiếm và lọc sản phẩm hoạt động ngay trong trình duyệt.\nForm chỉ minh họa thao tác, không lưu hoặc gửi thông tin.\nPhần đăng bài/quản trị chạy trong bản WordPress, không có trong demo HTML.\nẢnh đi kèm là minh họa do AI tạo.\n\nĐể cài website thật khi có hosting, dùng file theme hai-phat-vina.zip đã giao riêng.\n', encoding='utf-8')
    archive = ROOT / 'hai-phat-vina-demo.zip'
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
        for path in sorted(DEST.rglob('*')):
            if path.is_file():
                bundle.write(path, path.relative_to(ROOT))
    print(json.dumps({'pages': len(pages), 'searchableItems': len(index), 'entry': str(DEST / 'index.html'), 'zip': str(archive), 'bytes': archive.stat().st_size}, ensure_ascii=False))


if __name__ == '__main__':
    main()
