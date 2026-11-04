#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find . -type f -name '*.php' -print0)
for f in assets/admin.js assets/platform.js assets/platform-admin.js; do node --check "$f"; done
php tests/platform-smoke.php
python3 - <<'PY'
from pathlib import Path
import re
s=Path('index.html').read_text(encoding='utf-8')
m=re.findall(r'<script(?:\s[^>]*)?>(.*?)</script>',s,re.S)
assert m,'Missing legacy storefront script'
Path('/tmp/myca-legacy-storefront.js').write_text('\n'.join(m),encoding='utf-8')
assert 'api/platform.php?action=cart.add' in s
assert '/product/' in s
assert 'go(\'cart\')' in s or "v==='cart'" in s
schema=Path('db/migrations/002_commerce_platform.sql').read_text()
for table in ['shop_customers','shop_carts','shop_order_items','shop_reviews','shop_pages','shop_order_events','shop_payments']:
    assert f'CREATE TABLE IF NOT EXISTS {table} (' in schema,table
print('Source/SQL static assertions: OK')
PY
node --check /tmp/myca-legacy-storefront.js
echo 'All commerce platform static checks: PASS'
