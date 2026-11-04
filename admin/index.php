<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
?><!doctype html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>مرکز یکپارچه مدیریت فروشگاه | MYCA</title><meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="/assets/platform.css?v=5">
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>.admin-grid{display:grid;grid-template-columns:235px minmax(0,1fr);gap:22px;margin-top:26px;min-height:65vh}.admin-side{background:white;border:1px solid #e2eaf0;border-radius:19px;padding:13px;height:max-content;display:flex;flex-direction:column;gap:4px;position:sticky;top:20px}.admin-side button{border:0;background:white;padding:11px 12px;border-radius:10px;color:#062e5c;text-align:right;cursor:pointer}.admin-side button:hover,.admin-side button.active{background:#eaf7fb;color:#005474}.admin-side h3{padding:8px}.admin-top{display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:19px}.stat-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.stat-card{border:1px solid #e2eaf0;border-radius:16px;background:#f5f8fb;padding:20px}.stat-card strong{display:block;font-size:24px}.builder-grid{display:grid;grid-template-columns:260px minmax(0,1fr);gap:15px}.block-edit{border:1px solid #dbe7ee;background:#fff;border-radius:13px;padding:13px;margin:10px 0}.block-edit select,.block-edit input,.block-edit textarea{width:100%;border:1px solid #d6e1ea;border-radius:9px;padding:8px;margin:5px 0}.block-edit textarea{min-height:70px}.admin-select{width:100%;padding:10px;border:1px solid #d6e1ea;border-radius:12px;background:white}.admin-input{width:100%;border:1px solid #d6e1ea;border-radius:11px;padding:10px;margin:7px 0}.admin-form{max-width:700px}.admin-buttons{display:flex;gap:7px;flex-wrap:wrap;margin-top:14px}.stat-card small{color:#66758a}.admin-help{color:#66758a;font-size:12px}@media(max-width:770px){.admin-grid{display:block}.admin-side{position:static;flex-direction:row;overflow:auto;white-space:nowrap;margin-bottom:15px}.stat-cards{grid-template-columns:1fr 1fr}.builder-grid{grid-template-columns:1fr}}
#admin-workspace[hidden],#admin-login-stage[hidden]{display:none!important}
.admin-login-stage{min-height:65vh;display:flex;align-items:center;justify-content:center;padding:38px 18px}
.admin-login-card{width:min(440px,100%);padding:30px;background:#fff;border-radius:21px;box-shadow:0 12px 35px #062e5c10;border:1px solid #e2eaf0}
.admin-login-card img{height:54px;max-width:145px;object-fit:contain}
.admin-login-card h1{font-size:24px;margin:15px 0 8px}
.admin-login-card label{display:block;margin:14px 0;color:#062e5c;font-size:13px;font-weight:700}
.admin-login-card .admin-input{display:block;width:100%}
.admin-login-card .primary-btn{width:100%;margin:15px 0 6px}
.admin-login-card .notice{font-size:12px}
.admin-login-error{color:#ad2136;font-size:12px;line-height:1.9;min-height:20px}
.admin-file-field{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.admin-file-field input[type=file]{max-width:210px}
.admin-panel-table{overflow-x:auto}
.admin-panel-table table{width:100%;border-collapse:collapse;min-width:690px}
.admin-panel-table th,.admin-panel-table td{padding:12px;text-align:right;border-bottom:1px solid #e2eaf0}
.admin-panel-table th{background:#eaf7fb}
.admin-panel-table .thumb{width:54px;height:54px;object-fit:contain;border-radius:9px;vertical-align:middle}
.admin-panel-table td button{padding:7px 10px;margin:2px}
.admin-editor{margin:16px 0;border:1px solid #cde4ee;background:#f9fdff;padding:20px;border-radius:16px}
.admin-editor-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.admin-editor label{display:block;font-size:12px;font-weight:700}
.admin-editor textarea,.admin-editor select,.admin-editor input:not([type=checkbox]):not([type=file]){width:100%;padding:10px;border-radius:10px;border:1px solid #d6e1ea;background:white;min-height:42px}
.admin-editor textarea{min-height:90px}
.admin-editor .wide{grid-column:1/-1}
.admin-editor-actions{display:flex;gap:9px;margin-top:16px}
.admin-error{background:#fff0f2;color:#a51b35;border-radius:12px;padding:12px;margin:12px 0}
@media(max-width:770px){.admin-editor-grid{grid-template-columns:1fr}.head-inner{flex-wrap:wrap}.admin-panel-table{max-width:100%}}
</style></head><body>
<div class="top-strip">مرکز یکپارچه مدیریت فروشگاه مایکا · نسخه آزمایشی</div>
<header class="myca-head"><div class="wrap head-inner"><a href="/" class="brand"><img src="/assets/myca-logo.svg" alt="MYCA"><b>مدیریت فروشگاه مایکا</b></a>
<nav aria-label="پیوندهای مدیریت"><a href="/">ویترین فروشگاه</a><a href="/storefront.php">پیش‌نمایش لندینگ</a></nav>
<div id="admin-account-menu" hidden><span id="admin-session-email"></span> <button type="button" class="light-btn" id="admin-logout">خروج از حساب</button></div></div></header>
<section id="admin-login-stage" class="admin-login-stage"><div class="admin-login-card"><p>در حال بررسی نشست مدیریت...</p></div></section>
<main id="admin-workspace" class="wrap admin-grid" hidden>
<aside class="admin-side" id="admin-side"><h3>مدیریت فروشگاه</h3><button type="button" data-tab="dashboard" class="active">نمای کلی</button><button type="button" data-tab="products">محصولات</button><button type="button" data-tab="categories">دسته‌بندی‌ها</button><button type="button" data-tab="pages">صفحه‌ساز لندینگ</button><button type="button" data-tab="seo">سئو و محتوای محصولات</button><button type="button" data-tab="media">گالری و ویدیو</button><button type="button" data-tab="variants">تنوع و SKU</button><button type="button" data-tab="orders">سفارش‌ها و ارسال</button><button type="button" data-tab="inventory">انبار و موجودی</button><button type="button" data-tab="customers">مشتریان</button><button type="button" data-tab="reviews">نظرات کاربران</button><button type="button" data-tab="questions">پرسش و پاسخ</button><button type="button" data-tab="abandoned">سبدهای رهاشده</button><button type="button" data-tab="coupons">کوپن‌ها</button><button type="button" data-tab="returns">مرجوعی‌ها</button><button type="button" data-tab="tickets">پشتیبانی</button></aside>
<section id="admin-panel" class="panel"><p class="placeholder">در حال دریافت اطلاعات...</p></section>
</main>
<footer class="myca-foot"><div class="wrap">MYCA Shop · پشتیبانی ۴۱۱۲۴ · نسخه توسعه و تست، بدون پرداخت واقعی</div></footer>
<script defer src="/assets/platform-admin.js?v=5"></script></body></html>
