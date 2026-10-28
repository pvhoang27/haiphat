"""HTTP integration checks for the disposable local WordPress instance."""
import json
import re
import uuid
import urllib.request
import urllib.parse
import urllib.error

BASE = 'http://127.0.0.1:8787'

def request(path, fields=None, redirect=True):
    url = path if path.startswith('http') else BASE + path
    data = urllib.parse.urlencode(fields).encode() if fields is not None else None
    req = urllib.request.Request(url, data=data)
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args):
            return None
    client = urllib.request.build_opener() if redirect else urllib.request.build_opener(NoRedirect())
    try:
        with client.open(req, timeout=20) as res:
            return res.status, res.read().decode(), res.headers
    except urllib.error.HTTPError as err:
        return err.code, err.read().decode(), err.headers

def check(ok, message):
    if not ok:
        raise AssertionError(message)
    print('PASS:', message)

for path in ['/', '/san-pham/', '/san-pham/mai-xep-luon-song/', '/nhom-san-pham/bat-hdpe/', '/gioi-thieu/', '/lien-he/', '/bao-gia/', '/ung-dung/', '/ung-dung/mai-che-quan-ca-phe/', '/kien-thuc/', '/chon-mai-che-cho-quan-ca-phe/', '/chinh-sach-bao-mat/']:
    code, body, _ = request(path)
    check(code == 200 and '<h1' in body and not re.search(r'Warning:|Notice:|Fatal error:', body), f'Page renders cleanly: {path}')

code, body, _ = request('/this-page-does-not-exist/')
check(code == 404 and 'Chúng ta tìm lại nhé' in body, 'Custom 404 page returns HTTP 404')
code, body, _ = request('/?s=HDPE&post_type=hpv_product')
check(code == 200 and 'Bạt HDPE lót hồ' in body and 'Màng chống thấm HDPE' in body, 'Product search returns matching products')
code, body, _ = request('/?s=QA-nothing-matches-uuid')
check(code == 200 and 'Chưa tìm thấy nội dung phù hợp' in body, 'Empty search provides a recovery path')
code, body, _ = request('/wp-json/wp/v2/hpv_request')
check(code == 404, 'Private customer requests have no public REST endpoint')
code, body, headers = request('/wp-admin/edit.php?post_type=hpv_request', redirect=False)
check(code == 302 and 'wp-login' in headers.get('Location', ''), 'Guest access to request admin requires login')
code, body, _ = request('/?s=Kh%C3%A1ch+ki%E1%BB%83m+th%E1%BB%AD+website')
check('Chưa tìm thấy nội dung phù hợp' in body, 'Guest search does not expose stored customer name')

code, body, _ = request('/wp-admin/admin-ajax.php', {'action': 'hpv_form_nonce'})
nonce = json.loads(body)['data']['nonce']
check(code == 200 and nonce, 'Guest can refresh the form nonce for cached pages')
base = {'action': 'hpv_quote', 'hpv_ajax': '1', 'hpv_nonce': nonce, 'hpv_token': str(uuid.uuid4()), 'hpv_name': 'QA HTTP Nguyễn Văn', 'hpv_phone': '0900000001', 'hpv_need': 'Mái xếp & mái hiên', 'hpv_location': 'Hà Đông', 'hpv_message': "Nhu cầu 5 × 8 m; ghi chú 'có viền' và đường dẫn C:\\bạt", 'hpv_consent': '1', 'company_website': ''}
code, body, _ = request('/wp-admin/admin-post.php', {**base, 'hpv_nonce': 'invalid'})
check(code == 403 and json.loads(body)['data']['status'] == 'expired', 'Invalid nonce rejected')
code, body, _ = request('/wp-admin/admin-post.php', {**base, 'hpv_phone': 'abc'})
check(code == 422, 'Invalid phone rejected on the server')
code, body, _ = request('/wp-admin/admin-post.php', {**base, 'hpv_consent': ''})
check(code == 422, 'Consent required on the server')
code, body, _ = request('/wp-admin/admin-post.php', {**base, 'company_website': 'spam'})
check(code == 422, 'Honeypot submission rejected')
malformed = {**base, 'hpv_name[]': 'malformed'}
malformed.pop('hpv_name')
code, body, _ = request('/wp-admin/admin-post.php', malformed)
check(code == 422, 'Array input rejected without a PHP fatal')

code, body, _ = request('/wp-admin/admin-post.php', base)
check(code == 200 and json.loads(body)['success'], 'Valid anonymous contact form saved')
code, body, _ = request('/wp-admin/admin-post.php', base)
check(code == 200 and json.loads(body)['success'], 'Retry with same submission token returns success')
code, body, _ = request('/wp-admin/admin-post.php', {**base, 'hpv_token': str(uuid.uuid4())})
check(code == 429 and json.loads(body)['data']['status'] == 'limited', 'Burst submission rate-limited')
plain = {**base, 'hpv_return': BASE + '/lien-he/'}
plain.pop('hpv_ajax')
code, body, headers = request('/wp-admin/admin-post.php', plain, redirect=False)
check(code == 302 and 'hpv_status=success' in headers.get('Location', ''), 'Non-JavaScript retry redirects to a real success page')
fallback = {**base, 'hpv_nonce': 'invalid', 'hpv_return': 'https://example.org/unsafe'}
fallback.pop('hpv_ajax')
code, body, headers = request('/wp-admin/admin-post.php', fallback, redirect=False)
check(code == 302 and headers.get('Location', '').startswith(BASE + '/lien-he/'), 'Non-JavaScript errors redirect safely to the contact page')
print('HTTP integration checks complete.')
