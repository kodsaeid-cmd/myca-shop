<?php
declare(strict_types=1);
require_once __DIR__.'/platform.php';
function cp_money(int|float|string $amount): string {return number_format((int)$amount,0,'.',',').' تومان';}
function cp_public_header(string $title, string $description='',string $canonical='',string $robots='noindex,nofollow',array $schema=[]): void {
 $title=cp_h($title);$description=cp_h($description);$canonical=cp_h($canonical);$robots=cp_h($robots);
 echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#005474"><title>'.$title.' | فروشگاه مایکا</title><meta name="description" content="'.$description.'"><meta name="robots" content="'.$robots.'">';
 if($canonical!=='')echo '<link rel="canonical" href="'.$canonical.'">';
 echo '<meta property="og:type" content="website"><meta property="og:title" content="'.$title.' | فروشگاه مایکا"><meta property="og:description" content="'.$description.'">';
 echo '<link rel="stylesheet" href="/assets/platform.css?v=3"><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
 if($schema)echo '<script type="application/ld+json">'.str_replace('</','<\/',cp_encode($schema)).'</script>';
 echo '</head><body><div class="top-strip">دانش برای مراقبت بهتر، آرامش برای زندگی · پشتیبانی ۴۱۱۲۴</div><header class="myca-head"><div class="wrap head-inner"><a href="/" class="brand"><img src="/assets/myca-logo.svg" alt="MYCA"><b>فروشگاه مایکا</b></a><nav aria-label="ناوبری اصلی"><a href="/">خانه</a><a href="/storefront.php">فروشگاه</a><a href="/collection/index.php?slug=elderly">مراقبت سالمند</a><a href="/collection/index.php?slug=baby">کودک و خانواده</a><a href="https://mycablog.dandoonii.ir/" rel="noopener">مجله مایکا</a></nav><div class="head-actions"><a href="/account/">حساب من</a><a class="outlined" href="/cart/">سبد خرید</a></div></div></header><main class="wrap" id="main-content">';
}
function cp_public_footer(): void {echo '</main><footer class="myca-foot"><div class="wrap">MYCA · My Care — دانش برای مراقبت بهتر · پشتیبانی ۴۱۱۲۴ <span> | نسخه توسعه و تست — بدون پرداخت واقعی</span></div></footer><script defer src="/assets/platform.js?v=3"></script></body></html>';}
function cp_img(?string $url,string $alt): string {
 $url=(string)$url;
 if($url!=='' && (preg_match('~^https://~i',$url)||preg_match('~^/uploads/[a-f0-9]{32}\.(jpg|png|webp|gif)$~',$url)))return '<img loading="lazy" src="'.cp_h($url).'" alt="'.cp_h($alt).'">';
 return '<span class="image-placeholder" aria-hidden="true">MYCA</span>';
}
function cp_card(array $p): string {
 $link='/product/'.rawurlencode((string)$p['slug']).'/';
 return '<a class="product-card" href="'.cp_h($link).'"><div class="product-media">'.cp_img($p['image_url']??'',$p['name']??'').'</div><div class="product-card-content"><small>فروشگاه مایکا</small><h3>'.cp_h($p['name']).'</h3><strong>'.cp_money($p['price']).'</strong><span class="view-text">مشاهده جزئیات ←</span></div></a>';
}
function cp_blocks_render(PDO $db,array $blocks): void {
 foreach($blocks as $b){if(!is_array($b))continue;$type=$b['type']??'';$title=cp_h($b['title']??'');$text=cp_h($b['text']??'');
  if($type==='hero'){echo '<section class="feature-hero"><div class="feature-text"><span class="eyebrow">MYCA · MY CARE</span><h1>'.$title.'</h1><p>'.$text.'</p><a href="/collection/index.php?slug=elderly" class="primary-btn">مشاهده محصولات مراقبتی</a></div><div class="feature-art">'.cp_img($b['image']??'','لوازم مراقبتی').'</div></section>';continue;}
  if($type==='categories'){$q=$db->query('SELECT name,slug,emoji,image_url FROM shop_categories WHERE active=1 AND parent_id IS NULL ORDER BY sort_order LIMIT 8');echo '<section class="block-section"><div class="block-heading"><h2>'.$title.'</h2></div><div class="category-grid">';foreach($q->fetchAll() as $c)echo '<a class="category-item" href="/collection/'.cp_h($c['slug']).'/"><span class="category-icon">'.cp_h($c['emoji']?:'✦').'</span><b>'.cp_h($c['name']).'</b></a>';echo '</div></section>';continue;}
  if($type==='products'){
   $ids=$b['product_ids']??[];
   if(is_array($ids)&&$ids){$ids=array_slice(array_values(array_filter($ids,'is_string')),0,16);$marks=implode(',',array_fill(0,count($ids),'?'));$q=$db->prepare("SELECT id,name,slug,price,image_url FROM shop_products WHERE published=1 AND id IN ($marks) LIMIT 16");$q->execute($ids);}
   else $q=$db->query('SELECT id,name,slug,price,image_url FROM shop_products WHERE published=1 ORDER BY featured DESC,updated_at DESC LIMIT 8');
   echo '<section class="block-section"><div class="block-heading"><h2>'.$title.'</h2></div><div class="product-grid">';foreach($q->fetchAll() as $p)echo cp_card($p);echo '</div></section>';continue;
  }
  if($type==='magazine'){echo '<section class="magazine-promo"><span>MYCA MAGAZINE</span><h2>'.$title.'</h2><p>'.$text.'</p><a href="https://mycablog.dandoonii.ir/">مطالعه راهنماهای مجله مایکا ←</a></section>';continue;}
  if($type==='banner'||$type==='text')echo '<section class="block-section content-panel"><h2>'.$title.'</h2><p>'.nl2br($text).'</p>'.(!empty($b['link'])?'<a class="primary-btn" href="'.cp_h($b['link']).'">مشاهده بیشتر</a>':'').'</section>';
  if($type==='faq')echo '<section class="block-section content-panel"><h2>'.$title.'</h2><p>'.nl2br($text).'</p></section>';
 }
}
