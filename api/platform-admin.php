<?php
declare(strict_types=1);
require_once __DIR__.'/../app/platform.php';
$action=(string)($_GET['action']??'dashboard');
try {
 $admin=cp_admin();
 if(!cp_https()) shop_error('برای عملیات مدیریتی HTTPS معتبر الزامی است.',403);
 $db=shop_db();$actor=(string)$admin['email'];
 if($_SERVER['REQUEST_METHOD']==='GET'){
  if($action==='dashboard'){
   $stats=[];
   foreach(['products'=>'SELECT COUNT(*) FROM shop_products','customers'=>'SELECT COUNT(*) FROM shop_customers',
      'orders'=>'SELECT COUNT(*) FROM shop_orders','pending_orders'=>'SELECT COUNT(*) FROM shop_orders WHERE order_status="pending"',
      'reviews_pending'=>'SELECT COUNT(*) FROM shop_reviews WHERE status="pending"',
      'abandoned_carts'=>'SELECT COUNT(*) FROM shop_carts WHERE status="active" AND updated_at < DATE_SUB(NOW(),INTERVAL 24 HOUR) AND EXISTS(SELECT 1 FROM shop_cart_items WHERE cart_id=shop_carts.id)',
      'sandbox_order_total'=>'SELECT COALESCE(SUM(grand_total),0) FROM shop_orders WHERE sandbox=1'] as $k=>$sql) $stats[$k]=(int)$db->query($sql)->fetchColumn();
   shop_json(['ok'=>true,'stats'=>$stats]);
  }
  if($action==='orders'){$q=$db->query('SELECT o.id,o.order_number,o.order_status,o.payment_status,o.fulfillment_status,o.grand_total,o.currency,o.sandbox,o.created_at,c.email FROM shop_orders o JOIN shop_customers c ON c.id=o.customer_id ORDER BY o.id DESC LIMIT 200');shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='customers'){$q=$db->query('SELECT id,email,full_name,mobile,created_at FROM shop_customers ORDER BY id DESC LIMIT 200');shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='reviews'||$action==='questions'){
   $table=$action==='reviews'?'shop_reviews':'shop_questions';$q=$db->query("SELECT r.*,p.name product_name,c.email customer_email FROM $table r JOIN shop_products p ON p.id=r.product_id JOIN shop_customers c ON c.id=r.customer_id ORDER BY r.id DESC LIMIT 200");shop_json(['ok'=>true,'items'=>$q->fetchAll()]);
  }
  if($action==='returns'){$q=$db->query('SELECT r.*,o.order_number FROM shop_returns r JOIN shop_orders o ON o.id=r.order_id ORDER BY r.id DESC LIMIT 200');shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='tickets'){$q=$db->query('SELECT t.*,c.email FROM shop_support_tickets t JOIN shop_customers c ON c.id=t.customer_id ORDER BY t.id DESC LIMIT 200');shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='coupons'){$q=$db->query('SELECT * FROM shop_coupons ORDER BY id DESC LIMIT 200');shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='pages'){$q=$db->query('SELECT id,slug,title,meta_description,published_at,updated_at FROM shop_pages ORDER BY id DESC');shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='page'){$slug=shop_slug($_GET['slug']??'home');$p=cp_page($db,$slug,false);if(!$p)shop_error('صفحه پیدا نشد.',404);shop_json(['ok'=>true,'page'=>$p]);}
  if($action==='page.versions'){$slug=shop_slug($_GET['slug']??'home');$q=$db->prepare('SELECT v.id,v.version_num,v.editor,v.created_at FROM shop_page_versions v JOIN shop_pages p ON p.id=v.page_id WHERE p.slug=? ORDER BY v.version_num DESC LIMIT 30');$q->execute([$slug]);shop_json(['ok'=>true,'versions'=>$q->fetchAll()]);}
  if($action==='variants'){$id=cp_text($_GET['product_id']??'',64,true);$q=$db->prepare('SELECT * FROM shop_variants WHERE product_id=? ORDER BY id DESC');$q->execute([$id]);shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='seo'){$id=cp_text($_GET['product_id']??'',64,true);$q=$db->prepare('SELECT * FROM shop_product_seo WHERE product_id=?');$q->execute([$id]);shop_json(['ok'=>true,'seo'=>$q->fetch()?:null]);}
  if($action==='media'){$id=cp_text($_GET['product_id']??'',64,true);$q=$db->prepare('SELECT * FROM shop_media WHERE product_id=? ORDER BY sort_order,id');$q->execute([$id]);shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='abandoned'){
   $q=$db->query('SELECT c.id,c.customer_id,c.updated_at,COUNT(i.id) items, COALESCE(SUM(i.quantity*p.price),0) estimated_total FROM shop_carts c JOIN shop_cart_items i ON i.cart_id=c.id JOIN shop_products p ON p.id=i.product_id WHERE c.status="active" AND c.updated_at<DATE_SUB(NOW(),INTERVAL 24 HOUR) GROUP BY c.id,c.customer_id,c.updated_at ORDER BY c.updated_at DESC LIMIT 150');
   shop_json(['ok'=>true,'items'=>$q->fetchAll(),'note'=>'ارزش سبد تخمینی است و قیمت واقعی در زمان تسویه دوباره محاسبه می‌شود.']);
  }
  if($action==='shipments'){$q=$db->query('SELECT s.*,o.order_number FROM shop_shipments s JOIN shop_orders o ON o.id=s.order_id ORDER BY s.id DESC LIMIT 200');shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
  if($action==='inventory'){$products=$db->query('SELECT id,name,sku,stock FROM shop_products ORDER BY name LIMIT 300')->fetchAll();$movements=$db->query('SELECT m.id,m.product_id,m.variant_id,m.delta,m.reason,m.actor,m.created_at FROM shop_inventory_movements m ORDER BY m.id DESC LIMIT 50')->fetchAll();shop_json(['ok'=>true,'products'=>$products,'movements'=>$movements]);}
  shop_error('گزارش پیدا نشد.',404);
 }
 shop_method('POST');shop_csrf();$v=shop_input();
 if($action==='seo.save'){
  $id=cp_text($v['product_id']??'',64,true);$q=$db->prepare('SELECT id FROM shop_products WHERE id=?');$q->execute([$id]);if(!$q->fetch())shop_error('محصول پیدا نشد.',404);
  $canonical=cp_text($v['canonical_url']??'',500);
  if($canonical!=='' && !preg_match('~^https://[a-z0-9.-]*dandoonii\.ir(?:/|$)~i',$canonical))shop_error('Canonical باید آدرس HTTPS معتبر از دامنه مایکا باشد.');
  $params=[$id,cp_text($v['seo_title']??'',190),cp_text($v['meta_description']??'',320),cp_text($v['seo_body']??'',30000),
   cp_text($v['faq_json']??'',6000),$canonical,shop_url($v['og_image']??''),
   in_array($v['robots_policy']??'', ['index','noindex'],true)?$v['robots_policy']:'noindex',cp_text($v['brand']??'',120)];
  $db->prepare('INSERT INTO shop_product_seo(product_id,seo_title,meta_description,seo_body,faq_json,canonical_url,og_image,robots_policy,brand) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE seo_title=VALUES(seo_title),meta_description=VALUES(meta_description),seo_body=VALUES(seo_body),faq_json=VALUES(faq_json),canonical_url=VALUES(canonical_url),og_image=VALUES(og_image),robots_policy=VALUES(robots_policy),brand=VALUES(brand)')->execute($params);
  shop_audit($db,$actor,'save','seo',$id);shop_json(['ok'=>true]);
 }
 if($action==='variant.save'){
  $id=cp_int($v['id']??0,0);$pid=cp_text($v['product_id']??'',64,true);
  $data=[$pid,cp_text($v['sku']??'',80,true),cp_text($v['title']??'',190,true),cp_text($v['attributes_json']??'{}',3000),shop_unsigned($v['price']??0,'قیمت'),shop_unsigned($v['compare_price']??0,'قیمت قبلی'),cp_int($v['stock']??0,0,1000000),empty($v['active'])?0:1,cp_int($v['weight_grams']??0,0,200000)];
  if($id){$q=$db->prepare('UPDATE shop_variants SET product_id=?,sku=?,title=?,attributes_json=?,price=?,compare_price=?,stock=?,active=?,weight_grams=? WHERE id=?');$q->execute([...$data,$id]);if(!$q->rowCount())shop_error('تنوع پیدا نشد یا بدون تغییر بود.',404);}
  else {$db->prepare('INSERT INTO shop_variants(product_id,sku,title,attributes_json,price,compare_price,stock,active,weight_grams) VALUES(?,?,?,?,?,?,?,?,?)')->execute($data);$id=(int)$db->lastInsertId();}
  shop_audit($db,$actor,'save','variant',(string)$id);shop_json(['ok'=>true,'id'=>$id]);
 }
 if($action==='media.save'){
  $id=cp_int($v['id']??0,0);$pid=cp_text($v['product_id']??'',64,true);$type=in_array($v['media_type']??'',['image','video'],true)?$v['media_type']:'image';
  $data=[$pid,$type,shop_url($v['url']??'','آدرس رسانه'),shop_url($v['poster_url']??''),cp_text($v['alt_text']??'',250),cp_text($v['title']??'',190),cp_int($v['sort_order']??0,0,500)];
  if($data[2]==='')shop_error('آدرس رسانه الزامی است.');
  if($id){$q=$db->prepare('UPDATE shop_media SET product_id=?,media_type=?,url=?,poster_url=?,alt_text=?,title=?,sort_order=? WHERE id=?');$q->execute([...$data,$id]);}
  else {$db->prepare('INSERT INTO shop_media(product_id,media_type,url,poster_url,alt_text,title,sort_order,uploaded_by) VALUES(?,?,?,?,?,?,?,?)')->execute([...$data,$actor]);$id=(int)$db->lastInsertId();}
  shop_audit($db,$actor,'save','media',(string)$id);shop_json(['ok'=>true,'id'=>$id]);
 }
 if($action==='media.delete'){$id=cp_int($v['id']??0,1);$db->prepare('DELETE FROM shop_media WHERE id=?')->execute([$id]);shop_audit($db,$actor,'delete','media',(string)$id);shop_json(['ok'=>true]);}
 if($action==='relations.save'){
  $id=cp_text($v['product_id']??'',64,true);$related=$v['related_ids']??[];if(!is_array($related)||count($related)>24)shop_error('محصولات مرتبط نامعتبر است.');
  $db->beginTransaction();$db->prepare('DELETE FROM shop_product_relations WHERE product_id=? AND relation_type="similar"')->execute([$id]);
  $q=$db->prepare('INSERT INTO shop_product_relations(product_id,related_id,relation_type,sort_order) VALUES(?, ?, "similar", ?)');
  foreach(array_unique($related) as $order=>$rid){if(!is_string($rid)||$rid===$id||!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$rid))continue;$q->execute([$id,$rid,$order]);}
  shop_audit($db,$actor,'save','relations',$id);$db->commit();shop_json(['ok'=>true]);
 }
 if($action==='review.moderate'){
  $id=cp_int($v['id']??0,1);$status=in_array($v['status']??'', ['approved','rejected','pending'],true)?$v['status']:'pending';
  $db->prepare('UPDATE shop_reviews SET status=?,admin_reply=? WHERE id=?')->execute([$status,cp_text($v['admin_reply']??'',4000),$id]);shop_audit($db,$actor,'moderate','review',(string)$id);shop_json(['ok'=>true]);
 }
 if($action==='question.moderate'){
  $id=cp_int($v['id']??0,1);$status=in_array($v['status']??'',['approved','rejected','pending'],true)?$v['status']:'pending';
  $db->prepare('UPDATE shop_questions SET status=?,answer=? WHERE id=?')->execute([$status,cp_text($v['answer']??'',4000),$id]);shop_audit($db,$actor,'moderate','question',(string)$id);shop_json(['ok'=>true]);
 }
 if($action==='page.save'){
  $slug=shop_slug($v['slug']??'');$title=cp_text($v['title']??'',190,true);$blocks=cp_blocks_validate($v['blocks']??[]);
  $db->prepare('INSERT INTO shop_pages(slug,title,meta_description,draft_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),meta_description=VALUES(meta_description),draft_json=VALUES(draft_json)')->execute([$slug,$title,cp_text($v['meta_description']??'',320),cp_encode($blocks)]);
  shop_audit($db,$actor,'save','page',$slug);shop_json(['ok'=>true]);
 }
 if($action==='page.restore'){$slug=shop_slug($v['slug']??'');$version=cp_int($v['version']??0,1);$q=$db->prepare('SELECT v.blocks_json,p.id FROM shop_page_versions v JOIN shop_pages p ON p.id=v.page_id WHERE p.slug=? AND v.version_num=?');$q->execute([$slug,$version]);$old=$q->fetch();if(!$old)shop_error('نسخه موردنظر یافت نشد.',404);$db->prepare('UPDATE shop_pages SET draft_json=? WHERE id=?')->execute([$old['blocks_json'],$old['id']]);shop_audit($db,$actor,'restore','page',$slug);shop_json(['ok'=>true,'draft_only'=>true]);}
 if($action==='page.publish'){
  $slug=shop_slug($v['slug']??'');$db->beginTransaction();$q=$db->prepare('SELECT id,draft_json FROM shop_pages WHERE slug=? FOR UPDATE');$q->execute([$slug]);$p=$q->fetch();if(!$p)shop_error('صفحه پیدا نشد.',404);
  $blocks=cp_blocks_validate(cp_decode($p['draft_json']));$num=(int)$db->query('SELECT COALESCE(MAX(version_num),0)+1 FROM shop_page_versions WHERE page_id='.(int)$p['id'])->fetchColumn();
  $db->prepare('INSERT INTO shop_page_versions(page_id,version_num,blocks_json,editor) VALUES(?,?,?,?)')->execute([$p['id'],$num,cp_encode($blocks),$actor]);
  $db->prepare('UPDATE shop_pages SET published_json=?,published_at=NOW() WHERE id=?')->execute([cp_encode($blocks),$p['id']]);
  shop_audit($db,$actor,'publish','page',$slug);$db->commit();shop_json(['ok'=>true,'version'=>$num]);
 }
 if($action==='coupon.save'){
  $code=strtoupper(cp_text($v['code']??'',50,true));if(!preg_match('/^[A-Z0-9_-]{3,50}$/',$code))shop_error('کد تخفیف نامعتبر است.');
  $type=in_array($v['type']??'',['fixed','percent'],true)?$v['type']:'fixed';$val=cp_int($v['value']??0,1,999999999);if($type==='percent'&&$val>100)shop_error('درصد تخفیف نمی‌تواند بیش از ۱۰۰ باشد.');
  $db->prepare('INSERT INTO shop_coupons(code,discount_type,discount_value,min_total,usage_limit,active) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE discount_type=VALUES(discount_type),discount_value=VALUES(discount_value),min_total=VALUES(min_total),usage_limit=VALUES(usage_limit),active=VALUES(active)')->execute([$code,$type,$val,cp_int($v['min_total']??0,0,1000000000),cp_int($v['usage_limit']??0,0),empty($v['active'])?0:1]);
  shop_audit($db,$actor,'save','coupon',$code);shop_json(['ok'=>true]);
 }
 if($action==='inventory.adjust'){
  $pid=cp_text($v['product_id']??'',64,true);$vid=isset($v['variant_id'])&&$v['variant_id']!==''?cp_int($v['variant_id'],1):null;
  $delta=cp_int($v['delta']??0,-100000,100000);if($delta===0)shop_error('میزان تغییر موجودی باید غیرصفر باشد.');$reason=cp_text($v['reason']??'',120,true);
  $db->beginTransaction();
  if($vid!==null){$q=$db->prepare('SELECT stock FROM shop_variants WHERE id=? AND product_id=? FOR UPDATE');$q->execute([$vid,$pid]);$stock=$q->fetchColumn();}
  else{$q=$db->prepare('SELECT stock FROM shop_products WHERE id=? FOR UPDATE');$q->execute([$pid]);$stock=$q->fetchColumn();}
  if($stock===false)shop_error('کالا یا تنوع انتخابی پیدا نشد.',404);
  $new=(int)$stock+$delta;if($new<0)shop_error('موجودی نمی‌تواند منفی شود.',409);
  if($vid!==null)$db->prepare('UPDATE shop_variants SET stock=? WHERE id=? AND product_id=?')->execute([$new,$vid,$pid]);
  else $db->prepare('UPDATE shop_products SET stock=? WHERE id=?')->execute([$new,$pid]);
  $db->prepare('INSERT INTO shop_inventory_movements(product_id,variant_id,delta,reason,actor) VALUES(?,?,?,?,?)')->execute([$pid,$vid,$delta,$reason,$actor]);
  shop_audit($db,$actor,'adjust','inventory',$pid);$db->commit();shop_json(['ok'=>true,'stock'=>$new]);
 }
 if($action==='order.fulfillment'){
  $id=cp_int($v['id']??0,1);$state=$v['status']??'';$states=['unfulfilled','picking','packed','shipped','delivered','delivery_failed','returned'];
  if(!in_array($state,$states,true))shop_error('وضعیت ارسال نامعتبر است.');$db->beginTransaction();
  $q=$db->prepare('SELECT fulfillment_status FROM shop_orders WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetchColumn();if($old===false)shop_error('سفارش پیدا نشد.',404);
  $db->prepare('UPDATE shop_orders SET fulfillment_status=? WHERE id=?')->execute([$state,$id]);
  $db->prepare('INSERT INTO shop_order_events(order_id,dimension,from_status,to_status,actor,note) VALUES(?,"fulfillment",?,?,?,?)')->execute([$id,$old,$state,$actor,cp_text($v['note']??'',1000)]);
  shop_audit($db,$actor,'status','order',(string)$id);$db->commit();shop_json(['ok'=>true]);
 }
 if($action==='shipment.save'){
  $id=cp_int($v['order_id']??0,1);$carrier=cp_text($v['carrier']??'',120,true);$track=cp_text($v['tracking_code']??'',120,true);
  $db->prepare('INSERT INTO shop_shipments(order_id,carrier,tracking_code,status,shipped_at) VALUES(?,?,?,"shipped",NOW())')->execute([$id,$carrier,$track]);
  shop_audit($db,$actor,'save','shipment',(string)$id);shop_json(['ok'=>true]);
 }
 if($action==='return.update'){
  $id=cp_int($v['id']??0,1);$state=in_array($v['status']??'',['requested','reviewing','approved','rejected','received','closed'],true)?$v['status']:'reviewing';
  $db->prepare('UPDATE shop_returns SET status=?,admin_note=? WHERE id=?')->execute([$state,cp_text($v['admin_note']??'',2000),$id]);
  shop_audit($db,$actor,'status','return',(string)$id);shop_json(['ok'=>true]);
 }
 if($action==='ticket.reply'){
  $id=cp_int($v['id']??0,1);$status=in_array($v['status']??'',['open','pending','resolved'],true)?$v['status']:'pending';
  $db->prepare('UPDATE shop_support_tickets SET admin_reply=?,status=? WHERE id=?')->execute([cp_text($v['reply']??'',3000,true),$status,$id]);
  shop_audit($db,$actor,'reply','ticket',(string)$id);shop_json(['ok'=>true]);
 }
 shop_error('عملیات مدیریتی پیدا نشد.',404);
} catch(Throwable $e){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();shop_exception($e);}
