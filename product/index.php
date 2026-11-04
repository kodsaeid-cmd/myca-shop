<?php
declare(strict_types=1);
require_once __DIR__.'/../app/view.php';
try{
 $path=trim((string)(parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)??''),'/');
 $slug=(string)($_GET['slug']??basename($path));
 if($slug==='product'||$slug==='index.php')$slug='';
 if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))throw new RuntimeException('missing');
 $db=shop_db();$p=cp_product($db,$slug);
 if(!$p){$q=$db->prepare('SELECT new_path FROM shop_seo_redirects WHERE old_path=?');$q->execute(['/product/'.$slug.'/']);$to=$q->fetchColumn();if($to&&str_starts_with((string)$to,'/product/')){header('Location: '.(string)$to,true,301);exit;}throw new RuntimeException('missing');}
}catch(Throwable $e){$missing=$e instanceof RuntimeException&&$e->getMessage()==='missing';http_response_code($missing?404:503);cp_public_header($missing?'کالا پیدا نشد':'سرویس فروشگاه در دسترس نیست');echo '<section class="empty"><h1>'.($missing?'کالا پیدا نشد':'ارتباط با فروشگاه موقتاً برقرار نیست').'</h1><a href="/">بازگشت به فروشگاه</a></section>';cp_public_footer();exit;}
$base=rtrim((string)cp_cfg('public_base_url','https://shop.mycablog.dandoonii.ir'),'/');
$canonical=$base.'/product/'.rawurlencode($p['slug']).'/';
if(!empty($p['canonical_url']) && str_starts_with((string)$p['canonical_url'],'https://'))$canonical=(string)$p['canonical_url'];
$title=$p['seo_title']?:$p['name'];$desc=$p['meta_description']?:cp_substr((string)$p['description'],0,155);
$robots=cp_cfg('test_mode',true)?'noindex,nofollow':($p['robots_policy']??'noindex');
$schema=['@context'=>'https://schema.org','@type'=>'Product','name'=>$p['name'],'sku'=>$p['sku'],'description'=>cp_substr((string)$p['description'],0,1000),'url'=>$canonical];
if($p['image_url'])$schema['image']=[$p['image_url']];
if(!cp_cfg('test_mode',true)){$schema['offers']=['@type'=>'Offer','priceCurrency'=>'IRR','price'=>(int)$p['price']*10,'availability'=>(int)$p['stock']>0?'https://schema.org/InStock':'https://schema.org/OutOfStock','url'=>$canonical];}
if((int)$p['rating']['votes']>0)$schema['aggregateRating']=['@type'=>'AggregateRating','ratingValue'=>(float)$p['rating']['average'],'reviewCount'=>(int)$p['rating']['votes']];
cp_public_header($title,$desc,$canonical,$robots,$schema);
?>
<nav class="breadcrumbs"><a href="/">فروشگاه</a> / <a href="/collection/<?=cp_h($p['category_slug'])?>/"><?=cp_h($p['category_name'])?></a> / <?=cp_h($p['name'])?></nav>
<section class="product-detail">
 <div class="gallery-col"><div class="featured-image"><?=cp_img($p['image_url'],$p['name'])?></div>
 <div class="media-thumbs"><?php foreach($p['media'] as $m): ?><div class="media-thumb"><?php if($m['media_type']==='image'):?><?=cp_img($m['url'],$m['alt_text']?:$p['name'])?><?php else:?><span>▶ <?=cp_h($m['title']?:'ویدیو')?></span><?php endif;?></div><?php endforeach;?></div></div>
 <div class="product-summary"><span class="eyebrow"><?=cp_h($p['category_name'])?></span><h1><?=cp_h($p['name'])?></h1><p class="muted">کد کالا: <?=cp_h($p['sku'])?><?php if($p['brand']): ?> | برند: <?=cp_h($p['brand'])?><?php endif;?></p>
 <?php if((int)$p['rating']['votes']):?><p class="rating">★ <?=cp_h($p['rating']['average'])?> از ۵ · <?=cp_h($p['rating']['votes'])?> نظر تأییدشده</p><?php endif; ?>
 <p><?=cp_h($p['description'])?></p><div class="buy-panel">
 <div class="buy-price"><?=cp_money($p['price'])?></div>
 <p class="muted"><?=((int)$p['stock']>0)?'موجود در انبار':'ناموجود'?></p>
 <?php if($p['variants']):?><label for="variant">انتخاب مدل / تنوع</label><select id="variant" class="full-input"><option value="">محصول پایه</option><?php foreach($p['variants'] as $v):?><option value="<?=cp_h($v['id'])?>"><?=cp_h($v['title'])?> — <?=cp_money($v['price'])?> (<?=cp_h($v['stock'])?> عدد)</option><?php endforeach;?></select><?php endif;?>
 <button class="primary-btn add-to-cart" data-product="<?=cp_h($p['id'])?>" <?=((int)$p['stock']<1)?'disabled':''?>>افزودن به سبد خرید</button>
 <button class="light-btn save-wishlist" data-product="<?=cp_h($p['id'])?>">♡ افزودن به علاقه‌مندی‌ها</button>
 <small class="muted">محیط تست مایکا؛ ثبت پرداخت واقعی فعال نیست.</small>
 </div></div>
</section>
<div class="detail-tabs">
<section class="content-panel"><h2>معرفی و مشخصات</h2><p><?=nl2br(cp_h($p['specs']))?></p><p><?=nl2br(cp_h($p['seo_body']?:$p['description']))?></p></section>
<?php if($p['media']):?><section class="content-panel"><h2>تصاویر و ویدیوهای بررسی کالا</h2><div class="video-grid"><?php foreach($p['media'] as $m):?><?php if($m['media_type']==='video'):?><div><video controls preload="metadata" <?php if($m['poster_url']):?>poster="<?=cp_h($m['poster_url'])?>"<?php endif;?> src="<?=cp_h($m['url'])?>"></video><p><?=cp_h($m['title'])?></p></div><?php endif;?><?php endforeach;?></div></section><?php endif;?>
<section class="content-panel"><h2>نقد و بررسی مشتریان</h2><?php if(!$p['reviews']):?><p class="muted">هنوز نظر تأییدشده‌ای برای این کالا ثبت نشده است.</p><?php endif;?><?php foreach($p['reviews'] as $r):?><article class="review"><strong><?=str_repeat('★',(int)$r['rating'])?> <?=cp_h($r['title'])?></strong><p><?=cp_h($r['body'])?></p><?php if($r['admin_reply']):?><blockquote><?=cp_h($r['admin_reply'])?></blockquote><?php endif;?></article><?php endforeach;?><div class="review-form" data-product="<?=cp_h($p['id'])?>"><h3>نظر خود را بنویسید</h3><p class="muted">نظرات پس از بررسی منتشر می‌شوند. ورود با اتصال امن ضروری است.</p><label>امتیاز <select class="full-input" name="rating"><option value="5">۵ ستاره</option><option value="4">۴ ستاره</option><option value="3">۳ ستاره</option><option value="2">۲ ستاره</option><option value="1">۱ ستاره</option></select></label><input class="full-input" name="title" placeholder="عنوان نظر"><textarea name="body" placeholder="تجربه واقعی شما از محصول" class="full-input"></textarea><button class="light-btn submit-review">ثبت نظر</button></div></section>
<section class="content-panel"><h2>پرسش و پاسخ</h2><?php foreach($p['questions'] as $q):?><article class="review"><strong><?=cp_h($q['question'])?></strong><p><?=cp_h($q['answer']?:'در انتظار پاسخ')?></p></article><?php endforeach;?><div class="question-form" data-product="<?=cp_h($p['id'])?>"><textarea class="full-input" placeholder="سؤال خود را درباره کالا بنویسید"></textarea><button class="light-btn submit-question">ارسال پرسش</button></div></section>
<?php if(!empty($p['faq_json'])):?><section class="content-panel"><h2>پرسش‌های متداول</h2><?php foreach(cp_decode($p['faq_json']) as $f):?><?php if(is_array($f)):?><details><summary><?=cp_h($f['question']??'')?></summary><p><?=cp_h($f['answer']??'')?></p></details><?php endif;?><?php endforeach;?></section><?php endif;?>
</div>
<?php if($p['related']):?><section class="block-section"><div class="block-heading"><h2>کالاهای مشابه و پیشنهادی</h2></div><div class="product-grid"><?php foreach($p['related'] as $r)echo cp_card($r);?></div></section><?php endif;?>
<?php cp_public_footer(); ?>
