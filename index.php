<?php
declare(strict_types=1);
// Safe fallback: preserve the approved 2026-11-03 frontend until a page is published.
require_once __DIR__.'/app/view.php';
try{
    $db=shop_db();$page=cp_page($db,'home');
    if(!$page||$page['published_json']===null){readfile(__DIR__.'/index.html');exit;}
    cp_public_header($page['title'],$page['meta_description'],'','noindex,nofollow');
    cp_blocks_render($db,$page['blocks']);cp_public_footer();
}catch(Throwable $e){error_log('[MYCA SHOP home] '.$e->getMessage());readfile(__DIR__.'/index.html');}
