<?php
declare(strict_types=1);
// CLI-only dry-run/outbox creation. This DOES NOT send email or SMS.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/platform.php';
try {
    if(!cp_cfg('enable_marketing',false)){fwrite(STDOUT,"Marketing recovery is disabled. No outbox messages created.\n");exit(0);}
    $db=shop_db();
    $q=$db->query('SELECT c.id,c.customer_id FROM shop_carts c JOIN shop_marketing_consents m ON m.customer_id=c.customer_id WHERE c.status="active" AND c.updated_at<DATE_SUB(NOW(),INTERVAL 6 HOUR) AND c.updated_at>DATE_SUB(NOW(),INTERVAL 14 DAY) AND (m.email_opt_in=1 OR m.sms_opt_in=1) AND EXISTS(SELECT 1 FROM shop_cart_items i WHERE i.cart_id=c.id) AND NOT EXISTS(SELECT 1 FROM shop_notification_outbox n WHERE n.cart_id=c.id AND n.kind="abandoned_cart" AND n.created_at>DATE_SUB(NOW(),INTERVAL 7 DAY)) LIMIT 100');
    $insert=$db->prepare('INSERT INTO shop_notification_outbox(customer_id,cart_id,kind) VALUES(?, ?, "abandoned_cart")');$created=0;
    foreach($q->fetchAll() as $c){$insert->execute([$c['customer_id'],$c['id']]);$created++;}
    fwrite(STDOUT,"Queued $created consented recovery candidates for provider integration. No messages sent.\n");
}catch(Throwable $e){error_log('[MYCA SHOP abandoned-job] '.$e->getMessage());fwrite(STDERR,"Job failed; see PHP error log.\n");exit(1);}
