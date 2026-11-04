#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
find app api admin scripts -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/admin.js
node - <<'JS'
const fs=require('fs');const h=fs.readFileSync('index.html','utf8');
const code=h.split('<script>')[1]?.split('</script>')[0];
if(!code)throw Error('Storefront JS not found');
require('node:vm').Script && new (require('node:vm').Script)(code);
if(!code.includes("fetch('/api/catalog.php'"))throw Error('Server-backed catalog missing');
if(!code.includes("window.location.assign('/admin/')"))throw Error('Admin route not protected');
if(!code.includes("ثبت سفارش و پرداخت واقعی هنوز فعال نشده است"))throw Error('Demo checkout must stay disabled');
console.log('MYCA Sprint 01 static checks: passed');
JS
