<?php
declare(strict_types=1);
require_once __DIR__.'/app/platform.php';
if(cp_cfg('test_mode',true)){http_response_code(404);header('Content-Type:text/plain; charset=utf-8');echo 'Unavailable on staging';exit;}
try{$db=shop_db();$base=rtrim((string)cp_cfg('public_base_url',''),'/');if(!str_starts_with($base,'https://'))throw new RuntimeException('invalid site URL');$q=$db->query('SELECT slug,updated_at FROM shop_products WHERE published=1 ORDER BY id');header('Content-Type: application/xml; charset=utf-8');echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach($q->fetchAll() as $p){echo '<url><loc>'.htmlspecialchars($base.'/product/'.rawurlencode($p['slug']).'/',ENT_XML1).'</loc><lastmod>'.substr($p['updated_at'],0,10).'</lastmod></url>';}echo '</urlset>';}
catch(Throwable $e){http_response_code(503);error_log('[MYCA SHOP sitemap]'.$e->getMessage());}
