<?php
declare(strict_types=1);
require_once __DIR__.'/app/view.php';
try{$db=shop_db();$page=cp_page($db,'home');}catch(Throwable $e){http_response_code(503);cp_public_header('فروشگاه مایکا');echo '<p class="empty">نسخه جدید فروشگاه هنوز به پایگاه داده متصل نیست.</p>';cp_public_footer();exit;}
cp_public_header($page['title']??'فروشگاه مایکا',$page['meta_description']??'لوازم مراقبت سالمندان و کودکان','', 'noindex,nofollow');
$blocks=($page && $page['published_json']!==null)?$page['blocks']:[['type'=>'hero','title'=>'همراه خانواده در مراقبت بهتر','text'=>'محصولات مراقبتی برای سالمندان و کودکان'],['type'=>'categories','title'=>'دسته‌بندی محصولات'],['type'=>'products','title'=>'محصولات منتخب']];
cp_blocks_render($db,$blocks);cp_public_footer();
