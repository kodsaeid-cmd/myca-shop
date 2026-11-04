<?php
declare(strict_types=1);
require_once __DIR__.'/../app/platform.php';
$action=(string)($_GET['action']??'session');
try {
 $db=shop_db();
 if($action==='session') { shop_method('GET');cp_customer_session();shop_json(['ok'=>true,'csrf'=>$_SESSION['cp_csrf'],'logged_in'=>cp_customer_id()>0,'features'=>['auth'=>cp_https()&&cp_cfg('enable_customer_auth',false),'checkout'=>cp_https()&&cp_cfg('enable_checkout',false)]]); }
 if($action==='product') {shop_method('GET');$slug=shop_slug($_GET['slug']??'');$p=cp_product($db,$slug);if(!$p)shop_error('کالا پیدا نشد.',404);shop_json(['ok'=>true,'product'=>$p]);}
 if($action==='search') {shop_method('GET');$term=cp_text($_GET['q']??'',100);if(cp_strlen($term)<2)shop_json(['ok'=>true,'items'=>[]]);$q=$db->prepare('SELECT id,name,slug,price,image_url,stock FROM shop_products WHERE published=1 AND (name LIKE ? OR sku LIKE ?) ORDER BY featured DESC LIMIT 24');$like='%'.$term.'%';$q->execute([$like,$like]);shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
 if($action==='page') {shop_method('GET');$slug=shop_slug($_GET['slug']??'home');$page=cp_page($db,$slug);shop_json(['ok'=>true,'page'=>$page&&$page['published_json']!==null?$page:null]);}
 if($action==='cart') {shop_method('GET');shop_json(['ok'=>true,'cart'=>cp_cart($db)]);}
 if($action==='register'||$action==='login') {
   shop_method('POST');cp_sensitive_allowed();cp_csrf();$v=shop_input();$email=strtolower(cp_text($v['email']??'',190,true));$password=(string)($v['password']??'');
   if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12||strlen($password)>200)shop_error('ایمیل یا رمز عبور نامعتبر است (حداقل ۱۲ کاراکتر).');
   $key=hash('sha256','customer|'.$email.'|'.($_SERVER['REMOTE_ADDR']??''));
   $q=$db->prepare('SELECT attempts,blocked_until FROM shop_login_attempts WHERE fingerprint=?');$q->execute([$key]);$attempt=$q->fetch();
   if($attempt&&$attempt['blocked_until']&&strtotime($attempt['blocked_until'])>time())shop_error('ورود موقتاً محدود شده است.',429);
   if($action==='register') {
     $name=cp_text($v['full_name']??'',190,true);
     try{$q=$db->prepare('INSERT INTO shop_customers(email,password_hash,full_name) VALUES(?,?,?)');$q->execute([$email,password_hash($password,PASSWORD_DEFAULT),$name]);}
     catch(PDOException $e){if($e->getCode()==='23000')shop_error('این ایمیل قبلاً ثبت شده است.',409);throw $e;}
     $id=(int)$db->lastInsertId();
   } else {
     $q=$db->prepare('SELECT id,password_hash FROM shop_customers WHERE email=? AND active=1');$q->execute([$email]);$u=$q->fetch();
     if(!$u||!password_verify($password,$u['password_hash'])) {
       $db->prepare('INSERT INTO shop_login_attempts(fingerprint,attempts,blocked_until) VALUES(?,1,NULL) ON DUPLICATE KEY UPDATE attempts=attempts+1,blocked_until=IF(attempts>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),blocked_until)')->execute([$key]);
       shop_error('ایمیل یا رمز عبور نادرست است.',401);
     }$id=(int)$u['id'];
   }
   $db->prepare('DELETE FROM shop_login_attempts WHERE fingerprint=?')->execute([$key]);
   cp_customer_session();session_regenerate_id(true);$_SESSION['cp_customer_id']=$id;$_SESSION['cp_csrf']=bin2hex(random_bytes(32));
   $cart=cp_cart($db);$db->prepare('UPDATE shop_carts SET customer_id=? WHERE id=?')->execute([$id,$cart['id']]);
   shop_json(['ok'=>true,'csrf'=>$_SESSION['cp_csrf']]);
 }
 if($action==='logout') {shop_method('POST');cp_csrf();setcookie('myca_guest_cart','',['expires'=>time()-3600,'path'=>'/','secure'=>cp_https(),'httponly'=>true,'samesite'=>'Lax']);$_SESSION=[];session_regenerate_id(true);session_destroy();shop_json(['ok'=>true]);}
 if($action==='cart.add') {
   shop_method('POST');cp_csrf();$v=shop_input();$id=cp_text($v['product_id']??'',64,true);$qty=cp_int($v['quantity']??1,1,20);
   $variant=isset($v['variant_id'])&&$v['variant_id']!==null&&$v['variant_id']!==''?cp_int($v['variant_id'],1):null;
   $q=$db->prepare('SELECT price,stock,published FROM shop_products WHERE id=?');$q->execute([$id]);$p=$q->fetch();if(!$p||!$p['published'])shop_error('کالا برای خرید در دسترس نیست.',404);
   $stock=(int)$p['stock'];
   if($variant!==null){$q=$db->prepare('SELECT stock FROM shop_variants WHERE id=? AND product_id=? AND active=1');$q->execute([$variant,$id]);$vr=$q->fetch();if(!$vr)shop_error('تنوع انتخابی نامعتبر است.',404);$stock=(int)$vr['stock'];}
   $cart=cp_cart($db);$db->beginTransaction();$lock=$db->prepare('SELECT id FROM shop_carts WHERE id=? FOR UPDATE');$lock->execute([$cart['id']]);$q=$db->prepare('SELECT id,quantity FROM shop_cart_items WHERE cart_id=? AND product_id=? AND variant_id <=> ? LIMIT 1 FOR UPDATE');$q->execute([$cart['id'],$id,$variant]);$old=$q->fetch();$new=$qty+(int)($old['quantity']??0);
   if($new>$stock)shop_error('تعداد انتخابی از موجودی بیشتر است.',409);
   if($old)$db->prepare('UPDATE shop_cart_items SET quantity=? WHERE id=?')->execute([$new,$old['id']]);
   else $db->prepare('INSERT INTO shop_cart_items(cart_id,product_id,variant_id,quantity) VALUES(?,?,?,?)')->execute([$cart['id'],$id,$variant,$qty]);
   $db->commit();cp_record_event($db,'add_to_cart',['product_id'=>$id,'qty'=>$qty]);shop_json(['ok'=>true,'cart'=>cp_cart($db)]);
 }
 if($action==='cart.update') {
   shop_method('POST');cp_csrf();$v=shop_input();$item=cp_int($v['item_id']??0,1);$qty=cp_int($v['quantity']??0,0,20);
   $cart=cp_cart($db);$q=$db->prepare('SELECT id FROM shop_cart_items WHERE id=? AND cart_id=?');$q->execute([$item,$cart['id']]);if(!$q->fetch())shop_error('قلم سبد پیدا نشد.',404);
   if($qty===0)$db->prepare('DELETE FROM shop_cart_items WHERE id=?')->execute([$item]);
   else $db->prepare('UPDATE shop_cart_items SET quantity=? WHERE id=?')->execute([$qty,$item]);
   cp_record_event($db,'cart_updated');shop_json(['ok'=>true,'cart'=>cp_cart($db)]);
 }
 if($action==='me'||$action==='addresses'||$action==='orders'||$action==='wishlist'||$action==='returns'||$action==='tickets'||$action==='my.reviews') {
   shop_method('GET');$id=cp_require_customer();
   if($action==='me') {$q=$db->prepare('SELECT id,email,full_name,mobile,created_at FROM shop_customers WHERE id=? AND active=1');$q->execute([$id]);shop_json(['ok'=>true,'customer'=>$q->fetch()]);}
   if($action==='addresses'){$q=$db->prepare('SELECT * FROM shop_customer_addresses WHERE customer_id=? ORDER BY is_default DESC,id DESC');$q->execute([$id]);shop_json(['ok'=>true,'addresses'=>$q->fetchAll()]);}
   if($action==='orders'){$q=$db->prepare('SELECT id,order_number,order_status,payment_status,fulfillment_status,grand_total,currency,created_at FROM shop_orders WHERE customer_id=? ORDER BY id DESC LIMIT 80');$q->execute([$id]);shop_json(['ok'=>true,'orders'=>$q->fetchAll()]);}
   if($action==='my.reviews'){$q=$db->prepare('SELECT r.id,r.rating,r.title,r.body,r.status,r.created_at,p.name,p.slug FROM shop_reviews r JOIN shop_products p ON p.id=r.product_id WHERE r.customer_id=? ORDER BY r.id DESC');$q->execute([$id]);shop_json(['ok'=>true,'reviews'=>$q->fetchAll()]);}
   if($action==='wishlist'){$q=$db->prepare('SELECT p.id,p.name,p.slug,p.price,p.image_url FROM shop_wishlist w JOIN shop_products p ON p.id=w.product_id WHERE w.customer_id=? AND p.published=1 ORDER BY w.created_at DESC');$q->execute([$id]);shop_json(['ok'=>true,'items'=>$q->fetchAll()]);}
   if($action==='returns'){$q=$db->prepare('SELECT r.id,r.order_id,r.reason,r.status,r.refund_status,r.created_at FROM shop_returns r WHERE r.customer_id=? ORDER BY r.id DESC');$q->execute([$id]);shop_json(['ok'=>true,'returns'=>$q->fetchAll()]);}
   $q=$db->prepare('SELECT id,order_id,subject,body,status,admin_reply,created_at FROM shop_support_tickets WHERE customer_id=? ORDER BY id DESC');$q->execute([$id]);shop_json(['ok'=>true,'tickets'=>$q->fetchAll()]);
 }
 if($action==='order.detail') {shop_method('GET');$id=cp_require_customer();$number=cp_text($_GET['number']??'',40,true);$q=$db->prepare('SELECT * FROM shop_orders WHERE order_number=? AND customer_id=?');$q->execute([$number,$id]);$o=$q->fetch();if(!$o)shop_error('سفارش پیدا نشد.',404);$i=$db->prepare('SELECT name,sku,variant_title,quantity,unit_price,line_total FROM shop_order_items WHERE order_id=?');$i->execute([$o['id']]);$e=$db->prepare('SELECT dimension,to_status,created_at,note FROM shop_order_events WHERE order_id=? ORDER BY id');$e->execute([$o['id']]);$s=$db->prepare('SELECT carrier,tracking_code,status,shipped_at,delivered_at FROM shop_shipments WHERE order_id=?');$s->execute([$o['id']]);shop_json(['ok'=>true,'order'=>$o,'items'=>$i->fetchAll(),'events'=>$e->fetchAll(),'shipments'=>$s->fetchAll()]);}
 if($action==='profile.save') {shop_method('POST');$id=cp_require_customer();cp_csrf();$v=shop_input();$db->prepare('UPDATE shop_customers SET full_name=? WHERE id=?')->execute([cp_text($v['full_name']??'',190,true),$id]);shop_json(['ok'=>true]);}
 if($action==='address.delete') {shop_method('POST');$id=cp_require_customer();cp_csrf();$v=shop_input();$addr=cp_int($v['id']??0,1);$db->prepare('DELETE FROM shop_customer_addresses WHERE id=? AND customer_id=?')->execute([$addr,$id]);shop_json(['ok'=>true]);}
 if($action==='address.default') {shop_method('POST');$id=cp_require_customer();cp_csrf();$v=shop_input();$addr=cp_int($v['id']??0,1);$q=$db->prepare('SELECT id FROM shop_customer_addresses WHERE id=? AND customer_id=?');$q->execute([$addr,$id]);if(!$q->fetch())shop_error('آدرس پیدا نشد.',404);$db->beginTransaction();$db->prepare('UPDATE shop_customer_addresses SET is_default=0 WHERE customer_id=?')->execute([$id]);$db->prepare('UPDATE shop_customer_addresses SET is_default=1 WHERE id=? AND customer_id=?')->execute([$addr,$id]);$db->commit();shop_json(['ok'=>true]);}
 if($action==='address.save') {
   shop_method('POST');$id=cp_require_customer();cp_csrf();$v=shop_input();$addressId=cp_int($v['id']??0,0);
   $values=[cp_text($v['label']??'',100),cp_text($v['recipient']??'',190,true),cp_text($v['phone']??'',25,true),cp_text($v['province']??'',100,true),cp_text($v['city']??'',100,true),cp_text($v['postal_code']??'',20,true),cp_text($v['street']??'',1000,true),cp_text($v['building']??'',80)];
   if($addressId){$chk=$db->prepare('SELECT id FROM shop_customer_addresses WHERE id=? AND customer_id=?');$chk->execute([$addressId,$id]);if(!$chk->fetch())shop_error('آدرس پیدا نشد.',404);$q=$db->prepare('UPDATE shop_customer_addresses SET label=?,recipient=?,phone=?,province=?,city=?,postal_code=?,street=?,building=? WHERE id=? AND customer_id=?');$q->execute([...$values,$addressId,$id]);}
   else{$q=$db->prepare('INSERT INTO shop_customer_addresses(label,recipient,phone,province,city,postal_code,street,building,customer_id) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute([...$values,$id]);}
   shop_json(['ok'=>true]);
 }
 if($action==='wishlist.toggle') {
   shop_method('POST');$id=cp_require_customer();cp_csrf();$v=shop_input();$product=cp_text($v['product_id']??'',64,true);
   $q=$db->prepare('SELECT product_id FROM shop_wishlist WHERE product_id=? AND customer_id=?');$q->execute([$product,$id]);
   if($q->fetch()){$db->prepare('DELETE FROM shop_wishlist WHERE product_id=? AND customer_id=?')->execute([$product,$id]);$saved=false;}
   else{$db->prepare('INSERT INTO shop_wishlist(customer_id,product_id) VALUES(?,?)')->execute([$id,$product]);$saved=true;}
   shop_json(['ok'=>true,'saved'=>$saved]);
 }
 if($action==='review.submit'||$action==='question.submit') {
   shop_method('POST');$id=cp_require_customer();cp_csrf();$v=shop_input();$product=cp_text($v['product_id']??'',64,true);
   if($action==='review.submit'){
     $rating=cp_int($v['rating']??0,1,5);$body=cp_text($v['body']??'',3000,true);$title=cp_text($v['title']??'',190);
     if(!cp_cfg('test_mode',true)){$q=$db->prepare('SELECT COUNT(*) FROM shop_order_items i JOIN shop_orders o ON o.id=i.order_id WHERE i.product_id=? AND o.customer_id=? AND o.payment_status="paid"');$q->execute([$product,$id]);if(!(int)$q->fetchColumn())shop_error('فقط خریداران تأییدشده می‌توانند نظر ثبت کنند.',403);}
     $db->prepare('INSERT INTO shop_reviews(product_id,customer_id,rating,title,body) VALUES(?,?,?,?,?)')->execute([$product,$id,$rating,$title,$body]);
   } else $db->prepare('INSERT INTO shop_questions(product_id,customer_id,question) VALUES(?,?,?)')->execute([$product,$id,cp_text($v['question']??'',1500,true)]);
   shop_json(['ok'=>true,'status'=>'pending']);
 }
 if($action==='order.preview') {
   shop_method('POST');$id=cp_require_customer();cp_csrf();$cart=cp_cart($db);
   shop_json(['ok'=>true,'subtotal'=>$cart['subtotal'],'currency'=>'IRT','items'=>$cart['items'],'checkout_enabled'=>(bool)cp_cfg('enable_checkout',false)]);
 }
 if($action==='checkout.create') {
   shop_method('POST');$id=cp_require_customer();cp_csrf();
   if(!cp_cfg('test_mode',true)||!cp_cfg('enable_checkout',false))shop_error('ثبت سفارش در این محیط فقط با پرداخت واقعی و پس از فعال‌سازی درگاه امکان‌پذیر است.',403);
   $v=shop_input();$address=cp_int($v['address_id']??0,1);$q=$db->prepare('SELECT * FROM shop_customer_addresses WHERE id=? AND customer_id=?');$q->execute([$address,$id]);$addr=$q->fetch();if(!$addr)shop_error('نشانی معتبر نیست.',404);
   $db->beginTransaction();
   $cart=cp_cart($db);if(!$cart['items'])shop_error('سبد خرید خالی است.');
   // Lock the product rows so that every quantity and price is checked against server state.
   $items=[];$subtotal=0;
   foreach($cart['items'] as $line){
     $q=$db->prepare('SELECT id,name,sku,price,stock,published FROM shop_products WHERE id=? FOR UPDATE');$q->execute([$line['product_id']]);$p=$q->fetch();
     if(!$p||!$p['published'])shop_error('یکی از کالاها دیگر قابل خرید نیست.',409);
     $sku=$p['sku'];$price=(int)$p['price'];$stock=(int)$p['stock'];$title='';
     if($line['variant_id']!==null){$q=$db->prepare('SELECT sku,title,price,stock,active FROM shop_variants WHERE id=? AND product_id=? FOR UPDATE');$q->execute([$line['variant_id'],$p['id']]);$var=$q->fetch();if(!$var||!$var['active'])shop_error('تنوع کالا قابل خرید نیست.',409);$sku=$var['sku'];$price=(int)$var['price'];$stock=(int)$var['stock'];$title=$var['title'];}
     if((int)$line['quantity']>$stock)shop_error('موجودی یکی از اقلام کافی نیست.',409);
     $items[]=['product_id'=>$p['id'],'variant_id'=>$line['variant_id'],'sku'=>$sku,'name'=>$p['name'],'variant_title'=>$title,'quantity'=>(int)$line['quantity'],'price'=>$price,'total'=>$price*(int)$line['quantity']];$subtotal+=$price*(int)$line['quantity'];
   }
   // Sandbox order only, no stock deduction, payment, shipping or real invoice.
   $number='MYT-'.strtoupper(bin2hex(random_bytes(6)));
   $q=$db->prepare('INSERT INTO shop_orders(order_number,customer_id,cart_id,subtotal,grand_total,address_snapshot_json,sandbox) VALUES(?,?,?,?,?,?,1)');
   $q->execute([$number,$id,$cart['id'],$subtotal,$subtotal,cp_encode($addr)]);$orderId=(int)$db->lastInsertId();
   $q=$db->prepare('INSERT INTO shop_order_items(order_id,product_id,variant_id,sku,name,variant_title,quantity,unit_price,line_total) VALUES(?,?,?,?,?,?,?,?,?)');
   foreach($items as $item)$q->execute([$orderId,$item['product_id'],$item['variant_id'],$item['sku'],$item['name'],$item['variant_title'],$item['quantity'],$item['price'],$item['total']]);
   $db->prepare('INSERT INTO shop_order_events(order_id,dimension,to_status,actor,note) VALUES(?,"order","pending","system","سفارش آزمایشی؛ بدون تراکنش مالی")')->execute([$orderId]);
   $db->prepare('UPDATE shop_carts SET status="converted",converted_at=NOW() WHERE id=?')->execute([$cart['id']]);unset($_SESSION['cp_cart_id']);setcookie('myca_guest_cart','',['expires'=>time()-3600,'path'=>'/','secure'=>cp_https(),'httponly'=>true,'samesite'=>'Lax']);
   $db->commit();shop_json(['ok'=>true,'order_number'=>$number,'sandbox'=>true,'paid'=>false]);
 }
 if($action==='return.request'||$action==='ticket.create') {
   shop_method('POST');$id=cp_require_customer();cp_csrf();$v=shop_input();$order=isset($v['order_id'])&&$v['order_id']!==''?cp_int($v['order_id'],1):null;
   if($order!==null){$q=$db->prepare('SELECT id FROM shop_orders WHERE id=? AND customer_id=?');$q->execute([$order,$id]);if(!$q->fetch())shop_error('سفارش متعلق به شما نیست.',403);}
   if($action==='return.request'){
     if($order===null)shop_error('سفارش را انتخاب کنید.');
     $db->prepare('INSERT INTO shop_returns(order_id,customer_id,reason) VALUES(?,?,?)')->execute([$order,$id,cp_text($v['reason']??'',2000,true)]);
   } else $db->prepare('INSERT INTO shop_support_tickets(customer_id,order_id,subject,body) VALUES(?,?,?,?)')->execute([$id,$order,cp_text($v['subject']??'',190,true),cp_text($v['body']??'',3000,true)]);
   shop_json(['ok'=>true]);
 }
 if($action==='consent.save') {shop_method('POST');$id=cp_require_customer();cp_csrf();$v=shop_input();$db->prepare('INSERT INTO shop_marketing_consents(customer_id,email_opt_in,sms_opt_in) VALUES(?,?,?) ON DUPLICATE KEY UPDATE email_opt_in=VALUES(email_opt_in),sms_opt_in=VALUES(sms_opt_in)')->execute([$id,empty($v['email'])?0:1,empty($v['sms'])?0:1]);shop_json(['ok'=>true]);}
 shop_error('مسیر API پیدا نشد.',404);
} catch(Throwable $e){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();shop_exception($e);}
