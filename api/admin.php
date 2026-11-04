<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
$action = (string)($_GET['action'] ?? 'session');
try {
    shop_session();
    if ($action === 'session') {
        shop_method('GET');
        shop_json(['ok' => true, 'authenticated' => isset($_SESSION['shop_admin']['id']),
            'admin' => $_SESSION['shop_admin'] ?? null, 'csrf' => $_SESSION['shop_csrf']]);
    }
    if ($action === 'login') {
        shop_method('POST'); shop_csrf();
        $data = shop_input();
        $email = strtolower(shop_text($data['email'] ?? '', 190, 'ایمیل', true));
        $pass = (string)($data['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) > 4096) shop_error('ایمیل یا رمز عبور اشتباه است.', 401);
        $db = shop_db();
        $fingerprint = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $stmt = $db->prepare('SELECT attempts, blocked_until FROM shop_login_attempts WHERE fingerprint=?');
        $stmt->execute([$fingerprint]); $attempt = $stmt->fetch();
        if ($attempt && $attempt['blocked_until'] && strtotime($attempt['blocked_until']) > time()) shop_error('ورود موقتاً محدود شده؛ بعداً دوباره تلاش کنید.', 429);
        $stmt = $db->prepare('SELECT id, email, password_hash, role FROM shop_admins WHERE email=? AND active=1 LIMIT 1');
        $stmt->execute([$email]); $admin = $stmt->fetch();
        if (!$admin || !password_verify($pass, $admin['password_hash'])) {
            $stmt = $db->prepare('INSERT INTO shop_login_attempts (fingerprint, attempts, blocked_until) VALUES (?,1,NULL) ON DUPLICATE KEY UPDATE attempts=IF(blocked_until IS NOT NULL AND blocked_until < NOW(),1,attempts+1), blocked_until=IF(attempts>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL)');
            $stmt->execute([$fingerprint]);
            shop_error('ایمیل یا رمز عبور اشتباه است.', 401);
        }
        $db->prepare('DELETE FROM shop_login_attempts WHERE fingerprint=?')->execute([$fingerprint]);
        session_regenerate_id(true);
        $_SESSION['shop_csrf'] = bin2hex(random_bytes(32));
        $_SESSION['shop_admin'] = ['id' => (int)$admin['id'], 'email' => $admin['email'], 'role' => $admin['role']];
        $db->prepare('UPDATE shop_admins SET last_login_at=NOW() WHERE id=?')->execute([$admin['id']]);
        shop_json(['ok' => true, 'admin' => $_SESSION['shop_admin'], 'csrf' => $_SESSION['shop_csrf']]);
    }
    shop_require_admin();
    if ($action === 'logout') {
        shop_method('POST'); shop_csrf();
        $_SESSION = []; session_regenerate_id(true); session_destroy();
        shop_json(['ok' => true]);
    }
    if ($action === 'bootstrap') {
        shop_method('GET');
        $db = shop_db();
        $cats = $db->query('SELECT id, name, slug, COALESCE(parent_id,\'\') parent, emoji, image_url image, description, active, sort_order AS `order` FROM shop_categories ORDER BY sort_order, id')->fetchAll();
        $pros = $db->query('SELECT id,name,slug,sku,category_id category,price,compare_price comparePrice,stock,emoji,image_url image,gallery_json gallery,video_url video,description,specs,published,featured FROM shop_products ORDER BY updated_at DESC,id')->fetchAll();
        foreach ($cats as &$c) $c['active'] = (bool)$c['active']; unset($c);
        foreach ($pros as &$p) {
            $p['price'] = (int)$p['price']; $p['comparePrice'] = (int)$p['comparePrice']; $p['stock'] = (int)$p['stock'];
            $p['published'] = (bool)$p['published']; $p['featured'] = (bool)$p['featured'];
        } unset($p);
        shop_json(['ok' => true, 'categories' => $cats, 'products' => $pros]);
    }
    shop_csrf();
    $db = shop_db();
    $actor = (string)$_SESSION['shop_admin']['email'];
    if ($action === 'category.save') {
        shop_method('POST'); $v = shop_input();
        $id = shop_text($v['id'] ?? '', 64, 'شناسه');
        if ($id !== '' && !preg_match('/^c?[a-zA-Z0-9_-]{1,63}$/D', $id)) shop_error('شناسه دسته نامعتبر است.');
        $id = $id ?: 'c' . bin2hex(random_bytes(8));
        $name = shop_text($v['name'] ?? '', 190, 'نام', true);
        $slug = shop_slug($v['slug'] ?? '');
        $parent = shop_text($v['parent'] ?? '', 64, 'دسته والد');
        if ($parent === $id) shop_error('دسته نمی‌تواند والد خودش باشد.');
        if ($parent !== '') {
            $cursor = $parent;
            for ($i = 0; $i < 60 && $cursor !== ''; $i++) {
                if ($cursor === $id) shop_error('چرخه در دسته‌بندی‌ها مجاز نیست.');
                $q = $db->prepare('SELECT parent_id FROM shop_categories WHERE id=?'); $q->execute([$cursor]);
                $row = $q->fetch(); if (!$row) shop_error('دسته والد وجود ندارد.');
                $cursor = (string)($row['parent_id'] ?? '');
            }
            if ($cursor !== '') shop_error('عمق دسته‌بندی بیش از حد است.');
        }
        $payload = [$id, $name, $slug, $parent ?: null,
            shop_text($v['emoji'] ?? '', 30, 'نماد'), shop_url($v['image'] ?? ''),
            shop_text($v['description'] ?? '', 5000, 'توضیحات'), !empty($v['active']) ? 1 : 0,
            shop_unsigned($v['order'] ?? 0, 'ترتیب نمایش', 1000000)];
        $db->beginTransaction();
        $q = $db->prepare('SELECT id FROM shop_categories WHERE id=? FOR UPDATE'); $q->execute([$id]);
        if ($q->fetch()) {
            $q = $db->prepare('UPDATE shop_categories SET name=?,slug=?,parent_id=?,emoji=?,image_url=?,description=?,active=?,sort_order=? WHERE id=?');
            $q->execute(array_merge(array_slice($payload, 1), [$id]));
        } else {
            $q = $db->prepare('INSERT INTO shop_categories (id,name,slug,parent_id,emoji,image_url,description,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
            $q->execute($payload);
        }
        shop_audit($db, $actor, 'save', 'category', $id);
        $db->commit(); shop_json(['ok' => true, 'id' => $id]);
    }
    if ($action === 'category.delete') {
        shop_method('POST'); $v = shop_input(); $id = shop_text($v['id'] ?? '', 64, 'شناسه', true);
        $db->beginTransaction();
        $q = $db->prepare('DELETE FROM shop_categories WHERE id=?'); $q->execute([$id]);
        if (!$q->rowCount()) shop_error('دسته پیدا نشد.', 404);
        shop_audit($db, $actor, 'delete', 'category', $id);
        $db->commit(); shop_json(['ok' => true]);
    }
    if ($action === 'product.save') {
        shop_method('POST'); $v = shop_input();
        $id = shop_text($v['id'] ?? '', 64, 'شناسه');
        if ($id !== '' && !preg_match('/^p?[a-zA-Z0-9_-]{1,63}$/D', $id)) shop_error('شناسه محصول نامعتبر است.');
        $id = $id ?: 'p' . bin2hex(random_bytes(8));
        $category = shop_text($v['category'] ?? '', 64, 'دسته', true);
        $q = $db->prepare('SELECT id FROM shop_categories WHERE id=?'); $q->execute([$category]);
        if (!$q->fetch()) shop_error('دسته انتخابی وجود ندارد.');
        $galleryRaw = shop_text($v['gallery'] ?? '', 5000, 'گالری');
        $gallery = [];
        foreach (array_filter(array_map('trim', explode(',', $galleryRaw))) as $url) {
            if (count($gallery) >= 12) shop_error('گالری حداکثر ۱۲ تصویر دارد.');
            $gallery[] = shop_url($url, 'آدرس گالری');
        }
        $payload = [$id,shop_text($v['name'] ?? '', 190, 'نام محصول', true),shop_slug($v['slug'] ?? ''),
            shop_text($v['sku'] ?? '', 80, 'SKU', true), $category,
            shop_unsigned($v['price'] ?? 0, 'قیمت'),shop_unsigned($v['comparePrice'] ?? 0, 'قیمت قبلی'),
            shop_unsigned($v['stock'] ?? 0, 'موجودی',100000000),shop_text($v['emoji'] ?? '', 30, 'نماد'),
            shop_url($v['image'] ?? ''),implode(',', $gallery),shop_url($v['video'] ?? '', 'آدرس ویدیو'),
            shop_text($v['description'] ?? '', 20000, 'توضیحات'),shop_text($v['specs'] ?? '', 12000, 'مشخصات'),
            !empty($v['published']) ? 1 : 0,!empty($v['featured']) ? 1 : 0];
        $db->beginTransaction();
        $q = $db->prepare('SELECT id FROM shop_products WHERE id=? FOR UPDATE'); $q->execute([$id]);
        if ($q->fetch()) {
            $q=$db->prepare('UPDATE shop_products SET name=?,slug=?,sku=?,category_id=?,price=?,compare_price=?,stock=?,emoji=?,image_url=?,gallery_json=?,video_url=?,description=?,specs=?,published=?,featured=? WHERE id=?');
            $q->execute(array_merge(array_slice($payload,1),[$id]));
        } else {
            $q=$db->prepare('INSERT INTO shop_products (id,name,slug,sku,category_id,price,compare_price,stock,emoji,image_url,gallery_json,video_url,description,specs,published,featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute($payload);
        }
        shop_audit($db,$actor,'save','product',$id);
        $db->commit(); shop_json(['ok'=>true,'id'=>$id]);
    }
    if ($action === 'product.delete') {
        shop_method('POST'); $v=shop_input(); $id=shop_text($v['id']??'',64,'شناسه',true);
        $db->beginTransaction();
        $q=$db->prepare('DELETE FROM shop_products WHERE id=?'); $q->execute([$id]);
        if (!$q->rowCount()) shop_error('محصول پیدا نشد.',404);
        shop_audit($db,$actor,'delete','product',$id);
        $db->commit(); shop_json(['ok'=>true]);
    }
    if ($action === 'media.upload') {
        shop_method('POST');
        if (empty($_FILES['image']) || !is_array($_FILES['image'])) shop_error('یک تصویر انتخاب کنید.');
        $file=$_FILES['image'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>4*1024*1024 || ($file['size']??0)<1) shop_error('حجم تصویر باید کمتر از ۴ مگابایت باشد.');
        if (!is_uploaded_file($file['tmp_name'])) shop_error('فایل آپلودشده معتبر نیست.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        if (!isset($extensions[$mime])) shop_error('فرمت تصویر مجاز نیست.');
        $dimensions=@getimagesize($file['tmp_name']);
        if (!$dimensions || $dimensions[0]>8000 || $dimensions[1]>8000 || $dimensions[0]<1 || $dimensions[1]<1) shop_error('ابعاد تصویر معتبر نیست.');
        $dest=__DIR__.'/../uploads/';
        if (!is_dir($dest) || !is_writable($dest)) shop_error('پوشه آپلود قابل نوشتن نیست.',503);
        $name=bin2hex(random_bytes(16)).'.'.$extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'],$dest.$name)) shop_error('ذخیره تصویر با خطا مواجه شد.',503);
        @chmod($dest.$name,0644);
        shop_audit($db,$actor,'upload','media',$name);
        shop_json(['ok'=>true,'url'=>'/uploads/'.$name]);
    }
    shop_error('عملیات پیدا نشد.',404);
} catch (Throwable $e) { if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack(); shop_exception($e); }
