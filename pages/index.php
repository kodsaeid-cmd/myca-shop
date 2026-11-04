<?php
declare(strict_types=1);
require_once __DIR__.'/../app/view.php';
try{$slug=shop_slug($_GET['slug']??'home');$db=shop_db();$page=cp_page($db,$slug);if(!$page||$page['published_json']===null)throw new RuntimeException();}catch(Throwable $e){http_response_code(404);cp_public_header('صفحه پیدا نشد');echo '<p class="empty">این صفحه هنوز منتشر نشده است.</p>';cp_public_footer();exit;}
cp_public_header($page['title'],$page['meta_description'],'','noindex,nofollow');cp_blocks_render($db,$page['blocks']);cp_public_footer();
