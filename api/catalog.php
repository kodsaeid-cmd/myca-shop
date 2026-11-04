<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
shop_method('GET');
try {
    $db = shop_db();
    $categories = $db->query('SELECT id, name, slug, COALESCE(parent_id,\'\') AS parent, emoji, image_url AS image, description, active, sort_order AS `order` FROM shop_categories WHERE active=1 ORDER BY sort_order, id')->fetchAll();
    $products = $db->query('SELECT p.id, p.name, p.slug, p.sku, p.category_id AS category, p.price, p.compare_price AS comparePrice, p.stock, p.emoji, p.image_url AS image, p.gallery_json AS gallery, p.video_url AS video, p.description, p.specs, p.published, p.featured FROM shop_products p JOIN shop_categories c ON c.id=p.category_id WHERE p.published=1 AND c.active=1 ORDER BY p.featured DESC, p.updated_at DESC, p.id')->fetchAll();
    foreach ($categories as &$c) $c['active'] = (bool)$c['active'];
    unset($c);
    foreach ($products as &$p) {
        $p['price'] = (int)$p['price']; $p['comparePrice'] = (int)$p['comparePrice'];
        $p['stock'] = (int)$p['stock']; $p['published'] = (bool)$p['published']; $p['featured'] = (bool)$p['featured'];
    }
    unset($p);
    shop_json(['ok' => true, 'categories' => $categories, 'products' => $products]);
} catch (Throwable $e) { shop_exception($e); }
