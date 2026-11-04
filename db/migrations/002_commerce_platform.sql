-- MYCA Shop migration 002. Run ONCE on an existing shop DB after backup.
-- Does not modify original shop_products/shop_categories or magazine DB.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS shop_customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, full_name VARCHAR(190) NOT NULL DEFAULT '',
 mobile VARCHAR(25) DEFAULT NULL, mobile_verified_at DATETIME NULL,
 active TINYINT NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_customer_addresses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
 label VARCHAR(100) NOT NULL DEFAULT '', recipient VARCHAR(190) NOT NULL,
 phone VARCHAR(25) NOT NULL, province VARCHAR(100) NOT NULL,
 city VARCHAR(100) NOT NULL, postal_code VARCHAR(20) NOT NULL,
 street TEXT NOT NULL, building VARCHAR(80) NOT NULL DEFAULT '',
 is_default TINYINT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id) ON DELETE CASCADE,
 INDEX(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_variants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id VARCHAR(64) NOT NULL,
 sku VARCHAR(80) NOT NULL UNIQUE, title VARCHAR(190) NOT NULL,
 attributes_json TEXT NULL, price BIGINT UNSIGNED NOT NULL,
 compare_price BIGINT UNSIGNED NOT NULL DEFAULT 0, stock INT UNSIGNED NOT NULL DEFAULT 0,
 active TINYINT NOT NULL DEFAULT 1, weight_grams INT UNSIGNED DEFAULT 0,
 FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE CASCADE, INDEX(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_product_seo (
 product_id VARCHAR(64) PRIMARY KEY, seo_title VARCHAR(190) NOT NULL DEFAULT '',
 meta_description VARCHAR(320) NOT NULL DEFAULT '', seo_body MEDIUMTEXT NULL,
 faq_json TEXT NULL, canonical_url VARCHAR(500) NOT NULL DEFAULT '',
 og_image VARCHAR(500) NOT NULL DEFAULT '', robots_policy VARCHAR(30) NOT NULL DEFAULT 'noindex',
 brand VARCHAR(120) NOT NULL DEFAULT '', updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_seo_redirects (
 old_path VARCHAR(300) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 new_path VARCHAR(300) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_media (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id VARCHAR(64) DEFAULT NULL,
 media_type ENUM('image','video') NOT NULL, url TEXT NOT NULL,
 poster_url TEXT NULL, alt_text VARCHAR(250) NOT NULL DEFAULT '',
 title VARCHAR(190) NOT NULL DEFAULT '', sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 uploaded_by VARCHAR(190) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE SET NULL, INDEX(product_id,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_product_relations (
 product_id VARCHAR(64) NOT NULL, related_id VARCHAR(64) NOT NULL,
 relation_type ENUM('similar','complement','cross_sell') NOT NULL DEFAULT 'similar',
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(product_id,related_id,relation_type),
 FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE CASCADE,
 FOREIGN KEY(related_id) REFERENCES shop_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id VARCHAR(64) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED DEFAULT NULL,
 rating TINYINT UNSIGNED NOT NULL, title VARCHAR(190) NOT NULL DEFAULT '',
 body TEXT NOT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 admin_reply TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id) ON DELETE CASCADE,
 INDEX(product_id,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_questions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id VARCHAR(64) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL, question TEXT NOT NULL, answer TEXT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id) ON DELETE CASCADE,
 INDEX(product_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_wishlist (
 customer_id BIGINT UNSIGNED NOT NULL, product_id VARCHAR(64) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(customer_id,product_id),
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_carts (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 customer_id BIGINT UNSIGNED DEFAULT NULL, status ENUM('active','converted','expired') NOT NULL DEFAULT 'active',
 currency CHAR(3) NOT NULL DEFAULT 'IRT',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 converted_at DATETIME NULL,
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id) ON DELETE SET NULL,
 INDEX(status,updated_at), INDEX(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_cart_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cart_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 product_id VARCHAR(64) NOT NULL, variant_id BIGINT UNSIGNED DEFAULT NULL,
 quantity INT UNSIGNED NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_cart_product_variant(cart_id,product_id,variant_id),
 FOREIGN KEY(cart_id) REFERENCES shop_carts(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE CASCADE,
 FOREIGN KEY(variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_inventory_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id VARCHAR(64) NOT NULL,
 variant_id BIGINT UNSIGNED DEFAULT NULL,
 delta INT NOT NULL, reason VARCHAR(120) NOT NULL,
 actor VARCHAR(190) NOT NULL DEFAULT 'system',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES shop_products(id),
 FOREIGN KEY(variant_id) REFERENCES shop_variants(id),
 INDEX(product_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_inventory_reservations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED DEFAULT NULL,
 product_id VARCHAR(64) NOT NULL,
 variant_id BIGINT UNSIGNED DEFAULT NULL,
 quantity INT UNSIGNED NOT NULL,
 status ENUM('reserved','released','consumed') NOT NULL DEFAULT 'reserved',
 expires_at DATETIME NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES shop_products(id),
 FOREIGN KEY(variant_id) REFERENCES shop_variants(id),
 INDEX(status,expires_at),INDEX(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_number VARCHAR(40) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL, cart_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 order_status ENUM('pending','confirmed','cancelled','closed') NOT NULL DEFAULT 'pending',
 payment_status ENUM('not_started','pending','paid','failed','refunded') NOT NULL DEFAULT 'not_started',
 fulfillment_status ENUM('unfulfilled','picking','packed','shipped','delivered','delivery_failed','returned') NOT NULL DEFAULT 'unfulfilled',
 subtotal BIGINT UNSIGNED NOT NULL DEFAULT 0, discount_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
 shipping_total BIGINT UNSIGNED NOT NULL DEFAULT 0, grand_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
 currency CHAR(3) NOT NULL DEFAULT 'IRT', address_snapshot_json TEXT NOT NULL,
 shipping_method VARCHAR(100) NOT NULL DEFAULT 'manual', sandbox TINYINT NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id) ON DELETE RESTRICT,
 INDEX(customer_id,created_at), INDEX(order_status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL,
 product_id VARCHAR(64) DEFAULT NULL, variant_id BIGINT UNSIGNED DEFAULT NULL,
 sku VARCHAR(80) NOT NULL, name VARCHAR(190) NOT NULL, variant_title VARCHAR(190) NOT NULL DEFAULT '',
 quantity INT UNSIGNED NOT NULL, unit_price BIGINT UNSIGNED NOT NULL, line_total BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(order_id) REFERENCES shop_orders(id) ON DELETE CASCADE, INDEX(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_order_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL,
 dimension ENUM('order','payment','fulfillment','support') NOT NULL,
 from_status VARCHAR(40) NOT NULL DEFAULT '', to_status VARCHAR(40) NOT NULL,
 actor VARCHAR(190) NOT NULL DEFAULT 'system', note TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES shop_orders(id) ON DELETE CASCADE, INDEX(order_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL,
 gateway VARCHAR(80) NOT NULL DEFAULT 'sandbox', gateway_ref VARCHAR(190) DEFAULT NULL,
 amount BIGINT UNSIGNED NOT NULL, status ENUM('initiated','verified','failed','refunded') NOT NULL DEFAULT 'initiated',
 provider_event_id VARCHAR(190) UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_shipments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL,
 carrier VARCHAR(120) NOT NULL DEFAULT '', tracking_code VARCHAR(120) NOT NULL DEFAULT '',
 status VARCHAR(40) NOT NULL DEFAULT 'pending', shipped_at DATETIME NULL, delivered_at DATETIME NULL,
 FOREIGN KEY(order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_returns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL, reason TEXT NOT NULL,
 status ENUM('requested','reviewing','approved','rejected','received','closed') NOT NULL DEFAULT 'requested',
 refund_status ENUM('none','pending','completed') NOT NULL DEFAULT 'none',
 admin_note TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES shop_orders(id),
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_support_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED DEFAULT NULL, subject VARCHAR(190) NOT NULL, body TEXT NOT NULL,
 status ENUM('open','pending','resolved') NOT NULL DEFAULT 'open', admin_reply TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_pages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 title VARCHAR(190) NOT NULL, meta_description VARCHAR(320) NOT NULL DEFAULT '',
 draft_json MEDIUMTEXT NOT NULL, published_json MEDIUMTEXT NULL,
 published_at DATETIME NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_page_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, page_id BIGINT UNSIGNED NOT NULL,
 version_num INT UNSIGNED NOT NULL, blocks_json MEDIUMTEXT NOT NULL,
 editor VARCHAR(190) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_page_version(page_id,version_num),
 FOREIGN KEY(page_id) REFERENCES shop_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_coupons (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 discount_type ENUM('percent','fixed') NOT NULL,
 discount_value INT UNSIGNED NOT NULL, min_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
 usage_limit INT UNSIGNED NOT NULL DEFAULT 0, uses INT UNSIGNED NOT NULL DEFAULT 0,
 active TINYINT NOT NULL DEFAULT 0, starts_at DATETIME NULL, ends_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cart_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 customer_id BIGINT UNSIGNED DEFAULT NULL, event_name VARCHAR(60) NOT NULL,
 meta_json TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(event_name,created_at), INDEX(cart_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_marketing_consents (
 customer_id BIGINT UNSIGNED PRIMARY KEY, email_opt_in TINYINT NOT NULL DEFAULT 0,
 sms_opt_in TINYINT NOT NULL DEFAULT 0, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_notification_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
 cart_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 kind VARCHAR(50) NOT NULL, status ENUM('pending','sent','cancelled') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES shop_customers(id),
 INDEX(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Empty home page draft. Publish only through admin after adding blocks.
INSERT IGNORE INTO shop_pages (slug,title,draft_json) VALUES ('home','فروشگاه مایکا','[{"type":"hero","title":"لوازم مراقبتی برای خانواده","text":"انتخاب مطمئن برای مراقبت بهتر","image":""},{"type":"categories","title":"دسته‌بندی محصولات"},{"type":"products","title":"محصولات منتخب"},{"type":"magazine","title":"راهنمای انتخاب از مجله مایکا"}]');
