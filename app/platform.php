<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// Sprint 02-06 modular commerce foundation. MySQL remains the source of truth.
function cp_cfg(string $key, mixed $fallback = null): mixed { return shop_config()[$key] ?? $fallback; }
function cp_https(): bool {
    // Intentionally do not trust arbitrary X-Forwarded-Proto sent by visitors.
    return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
}
function cp_sensitive_allowed(): void {
    if (!cp_https()) shop_error('برای ورود و ثبت اطلاعات شخصی، گواهی HTTPS معتبر الزامی است.', 403);
    if (!cp_cfg('enable_customer_auth', false)) shop_error('حساب مشتری هنوز در محیط آزمایشی فعال نشده است.', 403);
}
function cp_customer_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('myca_shop_customer');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>cp_https(), 'httponly'=>true, 'samesite'=>'Lax']);
    session_start();
    if (!isset($_SESSION['cp_csrf'])) $_SESSION['cp_csrf'] = bin2hex(random_bytes(32));
}
function cp_customer_id(): int { cp_customer_session(); return (int)($_SESSION['cp_customer_id'] ?? 0); }
function cp_require_customer(): int {
    cp_sensitive_allowed(); $id=cp_customer_id();
    if ($id === 0) shop_error('برای ادامه وارد حساب شوید.',401);
    return $id;
}
function cp_csrf(): void {
    cp_customer_session(); $token=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
    if (!is_string($token) || !hash_equals((string)$_SESSION['cp_csrf'],$token)) shop_error('نشست نامعتبر است؛ صفحه را تازه‌سازی کنید.',419);
}
function cp_admin(): array {
    $a=shop_require_admin();
    if (!in_array($a['role']??'', ['admin','superadmin'], true)) shop_error('دسترسی لازم را ندارید.',403);
    return $a;
}
function cp_encode(mixed $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR); }
function cp_decode(?string $json): array {
    $v=json_decode($json?:'[]',true);
    return is_array($v)?$v:[];
}
function cp_h(mixed $s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function cp_strlen(string $v): int { return function_exists('mb_strlen') ? mb_strlen($v,'UTF-8') : preg_match_all('/./us',$v); }
function cp_substr(string $v,int $start,int $len): string { if(function_exists('mb_substr'))return mb_substr($v,$start,$len,'UTF-8');return implode('',array_slice(preg_split('//u',$v,-1,PREG_SPLIT_NO_EMPTY)?:[],$start,$len)); }
function cp_text(mixed $v, int $max=500, bool $required=false): string {
    if (!is_string($v) && !is_numeric($v)) shop_error('مقدار ورودی نامعتبر است.');
    $v=trim((string)$v);
    if (($required&&$v==='') || cp_strlen($v)>$max || !preg_match('//u',$v)) shop_error('متن واردشده معتبر نیست.');
    return $v;
}
function cp_int(mixed $v, int $min=0, int $max=1000000): int {
    $n=filter_var($v,FILTER_VALIDATE_INT);
    if ($n===false || $n<$min || $n>$max) shop_error('مقدار عددی معتبر نیست.');
    return $n;
}
function cp_product(PDO $db, string $slug): ?array {
    $s=$db->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug, s.seo_title,s.meta_description,s.seo_body,s.faq_json,s.canonical_url,s.og_image,s.robots_policy,s.brand FROM shop_products p JOIN shop_categories c ON c.id=p.category_id LEFT JOIN shop_product_seo s ON s.product_id=p.id WHERE p.slug=? AND p.published=1 AND c.active=1 LIMIT 1');
    $s->execute([$slug]); $p=$s->fetch();
    if (!$p) return null;
    $p['variants']=$db->prepare('SELECT id,sku,title,attributes_json,price,compare_price,stock,active,weight_grams FROM shop_variants WHERE product_id=? AND active=1 ORDER BY id');
    $p['variants']->execute([$p['id']]); $p['variants']=$p['variants']->fetchAll();
    $m=$db->prepare('SELECT id,media_type,url,poster_url,alt_text,title,sort_order FROM shop_media WHERE product_id=? ORDER BY sort_order,id');
    $m->execute([$p['id']]); $p['media']=$m->fetchAll();
    $r=$db->prepare('SELECT r.id,r.rating,r.title,r.body,r.admin_reply,r.created_at,c.full_name FROM shop_reviews r JOIN shop_customers c ON c.id=r.customer_id WHERE r.product_id=? AND r.status="approved" ORDER BY r.created_at DESC LIMIT 30');
    $r->execute([$p['id']]); $p['reviews']=$r->fetchAll();
    $q=$db->prepare('SELECT id,question,answer,created_at FROM shop_questions WHERE product_id=? AND status="approved" ORDER BY created_at DESC LIMIT 20');
    $q->execute([$p['id']]); $p['questions']=$q->fetchAll();
    $rel=$db->prepare('SELECT p.id,p.slug,p.name,p.price,p.image_url,p.stock FROM shop_products p WHERE p.published=1 AND p.id<>? AND (p.category_id=? OR EXISTS(SELECT 1 FROM shop_product_relations r WHERE r.product_id=? AND r.related_id=p.id)) ORDER BY p.featured DESC,p.updated_at DESC LIMIT 6');
    $rel->execute([$p['id'],$p['category_id'],$p['id']]); $p['related']=$rel->fetchAll();
    $rating=$db->prepare('SELECT COUNT(*) AS votes, ROUND(AVG(rating),1) AS average FROM shop_reviews WHERE product_id=? AND status="approved"');
    $rating->execute([$p['id']]); $p['rating']=$rating->fetch();
    return $p;
}
function cp_cart(PDO $db): array {
    cp_customer_session();
    $id=(string)($_SESSION['cp_cart_id']??($_COOKIE['myca_guest_cart']??''));
    if ($id!=='' && !preg_match('/^[a-f0-9]{32}$/D',$id)) $id='';
    if($id===''){ $user=cp_customer_id();
        if($user){$q=$db->prepare('SELECT id FROM shop_carts WHERE customer_id=? AND status="active" ORDER BY updated_at DESC LIMIT 1');$q->execute([$user]);$id=(string)($q->fetchColumn()?:'');}
    }
    if ($id==='') $id=bin2hex(random_bytes(16));
    $_SESSION['cp_cart_id']=$id;
    if(!headers_sent())setcookie('myca_guest_cart',$id,['expires'=>time()+30*86400,'path'=>'/','secure'=>cp_https(),'httponly'=>true,'samesite'=>'Lax']);
    $s=$db->prepare('SELECT id,customer_id FROM shop_carts WHERE id=? AND status="active"'); $s->execute([$id]);
    if (!$s->fetch()) {
        $id=bin2hex(random_bytes(16)); $_SESSION['cp_cart_id']=$id;
        if(!headers_sent())setcookie('myca_guest_cart',$id,['expires'=>time()+30*86400,'path'=>'/','secure'=>cp_https(),'httponly'=>true,'samesite'=>'Lax']);
        $db->prepare('INSERT INTO shop_carts(id,customer_id) VALUES(?,?)')->execute([$id,cp_customer_id() ?: null]);
    }
    $items=$db->prepare('SELECT i.id,i.product_id,i.variant_id,i.quantity,p.slug,p.name,p.image_url,p.published, p.price AS base_price,p.stock AS base_stock, v.title AS variant_title,v.sku AS variant_sku,v.price AS variant_price,v.stock AS variant_stock,v.active AS variant_active FROM shop_cart_items i JOIN shop_products p ON p.id=i.product_id LEFT JOIN shop_variants v ON v.id=i.variant_id WHERE i.cart_id=? ORDER BY i.id');
    $items->execute([$id]); $lines=[];$total=0;
    foreach($items->fetchAll() as $line){
        $price=$line['variant_id']!==null?(int)$line['variant_price']:(int)$line['base_price'];
        $stock=$line['variant_id']!==null?(int)$line['variant_stock']:(int)$line['base_stock'];
        $allowed=(bool)$line['published'] && ($line['variant_id']===null||((int)$line['variant_active']===1));
        $line['price']=$price; $line['available_stock']=$stock; $line['purchasable']=$allowed && $stock >= (int)$line['quantity'];
        $line['line_total']=$price*(int)$line['quantity']; $total += $line['line_total'];
        $lines[]=$line;
    }
    return ['id'=>$id,'items'=>$lines,'subtotal'=>$total,'currency'=>'IRT'];
}
function cp_record_event(PDO $db, string $event, array $meta=[]): void {
    cp_customer_session(); $cart=$_SESSION['cp_cart_id']??null;
    $stmt=$db->prepare('INSERT INTO shop_events(cart_id,customer_id,event_name,meta_json) VALUES(?,?,?,?)');
    $stmt->execute([$cart,cp_customer_id()?:null,$event,cp_encode($meta)]);
    if($cart) $db->prepare('UPDATE shop_carts SET updated_at=NOW() WHERE id=?')->execute([$cart]);
}
function cp_page(PDO $db,string $slug,bool $published=true): ?array {
    $q=$db->prepare('SELECT id,slug,title,meta_description,draft_json,published_json,published_at FROM shop_pages WHERE slug=? LIMIT 1');
    $q->execute([$slug]);$page=$q->fetch(); if(!$page)return null;
    $page['blocks']=cp_decode($published?$page['published_json']:$page['draft_json']);
    return $page;
}
function cp_blocks_validate(mixed $input): array {
    if(!is_array($input)||count($input)>40)shop_error('ساختار صفحه معتبر نیست.');
    $allowed=['hero','categories','products','magazine','banner','text','faq'];$res=[];
    foreach($input as $b){
        if(!is_array($b)||!in_array($b['type']??'',$allowed,true))shop_error('نوع بلوک نامعتبر است.');
        $res[]=['type'=>$b['type'],'title'=>cp_text($b['title']??'',190),'text'=>cp_text($b['text']??'',4000),
          'image'=>shop_url($b['image']??''),'link'=>cp_block_link($b['link']??''),
          'product_ids'=>array_slice(array_values(array_filter($b['product_ids']??[],fn($x)=>is_string($x)&&preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$x))),0,16)];
    }
    return $res;
}
function cp_block_link(mixed $value): string {
    $v=cp_text($value,500); if($v==='')return '';
    if(str_starts_with($v,'/')&&!str_starts_with($v,'//'))return $v;
    if(preg_match('~^https://(?:[a-z0-9-]+\.)?dandoonii\.ir(?:/|$)~i',$v))return $v;
    shop_error('پیوند بلوک باید داخلی یا متعلق به دامنه مایکا باشد.');
}
