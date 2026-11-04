-- MYCA Shop Sprint 01 | MySQL/MariaDB, utf8mb4
-- Import into a NEW, EMPTY database dedicated to MYCA Shop. Never import into magazine DB.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS shop_admins (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role VARCHAR(30) NOT NULL DEFAULT 'admin',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS shop_login_attempts (
 fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 blocked_until DATETIME NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shop_categories (
 id VARCHAR(64) NOT NULL PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 parent_id VARCHAR(64) DEFAULT NULL,
 emoji VARCHAR(30) NOT NULL DEFAULT '',
 image_url TEXT NULL,
 description TEXT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_shop_category_parent FOREIGN KEY (parent_id) REFERENCES shop_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS shop_products (
 id VARCHAR(64) NOT NULL PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 sku VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 category_id VARCHAR(64) NOT NULL,
 price BIGINT UNSIGNED NOT NULL DEFAULT 0,
 compare_price BIGINT UNSIGNED NOT NULL DEFAULT 0,
 stock INT UNSIGNED NOT NULL DEFAULT 0,
 emoji VARCHAR(30) NOT NULL DEFAULT '',
 image_url TEXT NULL,
 gallery_json TEXT NULL,
 video_url TEXT NULL,
 description TEXT NULL,
 specs TEXT NULL,
 published TINYINT(1) NOT NULL DEFAULT 0,
 featured TINYINT(1) NOT NULL DEFAULT 0,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_shop_product_catalog (published,category_id,featured),
 CONSTRAINT fk_shop_product_category FOREIGN KEY (category_id) REFERENCES shop_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS shop_audit_log (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 actor VARCHAR(190) NOT NULL,
 action_name VARCHAR(30) NOT NULL,
 entity_type VARCHAR(30) NOT NULL,
 entity_id VARCHAR(100) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_shop_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- All initial catalog items are DEMO values, NOT verified commercial offers.
-- Seed only on an empty shop; INSERT IGNORE protects existing rows.
INSERT IGNORE INTO shop_categories (id,name,slug,parent_id,emoji,image_url,description,active,sort_order) VALUES ('elder','مراقبت سالمندان','elderly',NULL,'👴','','ملزومات مراقبت و راحتی سالمندان',1,1);
INSERT IGNORE INTO shop_categories (id,name,slug,parent_id,emoji,image_url,description,active,sort_order) VALUES ('mobility','کمک‌حرکتی','mobility','elder','🦽','','واکر، عصا و ویلچر',1,2);
INSERT IGNORE INTO shop_categories (id,name,slug,parent_id,emoji,image_url,description,active,sort_order) VALUES ('hygiene','بهداشت سالمندان','elderly-hygiene','elder','🧴','','پوشینه و ملزومات بهداشت فردی',1,3);
INSERT IGNORE INTO shop_categories (id,name,slug,parent_id,emoji,image_url,description,active,sort_order) VALUES ('care','تجهیزات مراقبتی','care-equipment','elder','🛏️','','تجهیزات نگهداری و آسایش در خانه',1,4);
INSERT IGNORE INTO shop_categories (id,name,slug,parent_id,emoji,image_url,description,active,sort_order) VALUES ('child','مراقبت کودک','baby',NULL,'🧸','','لوازم کاربردی مراقبت از کودک',1,5);
INSERT IGNORE INTO shop_categories (id,name,slug,parent_id,emoji,image_url,description,active,sort_order) VALUES ('feeding','تغذیه کودک','baby-feeding','child','🍼','','شیشه شیر و لوازم تغذیه',1,6);
INSERT IGNORE INTO shop_categories (id,name,slug,parent_id,emoji,image_url,description,active,sort_order) VALUES ('baby-hygiene','بهداشت کودک','baby-hygiene','child','👶','','پوشک و بهداشت کودک',1,7);
INSERT IGNORE INTO shop_categories (id,name,slug,parent_id,emoji,image_url,description,active,sort_order) VALUES ('safety','ایمنی منزل','home-safety',NULL,'🏡','','لوازم ایمنی برای خانواده',1,8);
INSERT IGNORE INTO shop_products (id,name,slug,sku,category_id,price,compare_price,stock,emoji,image_url,gallery_json,video_url,description,specs,published,featured) VALUES ('p1','پوشینه بزرگسال سایز بزرگ، بسته ۱۴ عددی','adult-diaper-l','MY-EL-001','hygiene',395000,435000,34,'📦','','','','جذب مناسب و طراحی راحت برای استفاده روزانه سالمندان.','سایز: بزرگ | تعداد: ۱۴ عدد | گروه: بهداشت سالمند',1,1);
INSERT IGNORE INTO shop_products (id,name,slug,sku,category_id,price,compare_price,stock,emoji,image_url,gallery_json,video_url,description,specs,published,featured) VALUES ('p2','واکر تاشو آلومینیومی قابل تنظیم','foldable-walker','MY-MO-002','mobility',1290000,0,12,'🦯','','','','واکر سبک و قابل تنظیم برای کمک به راه رفتن در خانه.','جنس: آلومینیوم | قابلیت: تاشو | ارتفاع: تنظیم‌پذیر',1,1);
INSERT IGNORE INTO shop_products (id,name,slug,sku,category_id,price,compare_price,stock,emoji,image_url,gallery_json,video_url,description,specs,published,featured) VALUES ('p3','تشک مواج سلولی خانگی','air-mattress','MY-CA-003','care',2650000,2850000,6,'🛏️','','','','تشک کمکی برای مدیریت فشار در مراقبت‌های طولانی.','نوع: سلولی | اقلام: تشک و پمپ | کاربرد: مراقبت در منزل',1,1);
INSERT IGNORE INTO shop_products (id,name,slug,sku,category_id,price,compare_price,stock,emoji,image_url,gallery_json,video_url,description,specs,published,featured) VALUES ('p4','صندلی حمام ضدلغزش با ارتفاع تنظیمی','shower-chair','MY-SA-004','safety',1780000,0,9,'🪑','','','','کمک به نشستن ایمن‌تر هنگام استحمام.','پایه: ضدلغزش | ارتفاع: تنظیمی | گروه: ایمنی منزل',1,1);
INSERT IGNORE INTO shop_products (id,name,slug,sku,category_id,price,compare_price,stock,emoji,image_url,gallery_json,video_url,description,specs,published,featured) VALUES ('p5','پوشک کودک سایز ۳، بسته ۳۴ عددی','baby-diaper-3','MY-CH-005','baby-hygiene',325000,360000,55,'👶','','','','پوشک نرم و مناسب استفاده روزمره.','سایز: ۳ | تعداد: ۳۴ عدد',1,0);
INSERT IGNORE INTO shop_products (id,name,slug,sku,category_id,price,compare_price,stock,emoji,image_url,gallery_json,video_url,description,specs,published,featured) VALUES ('p6','شیشه شیر ضدنفخ ۲۶۰ میلی‌لیتر','baby-bottle','MY-FE-006','feeding',459000,0,21,'🍼','','','','شیشه شیر با طراحی ضدنفخ و نظافت آسان.','حجم: ۲۶۰ میلی‌لیتر | نوع: ضدنفخ',1,0);
INSERT IGNORE INTO shop_products (id,name,slug,sku,category_id,price,compare_price,stock,emoji,image_url,gallery_json,video_url,description,specs,published,featured) VALUES ('p7','دستگاه بخور سرد اتاق کودک','humidifier','MY-CH-007','child',1570000,1750000,7,'💨','','','','بخور سرد کم‌صدا مناسب استفاده در اتاق.','نوع: سرد | ویژگی: کم‌صدا',1,0);
INSERT IGNORE INTO shop_products (id,name,slug,sku,category_id,price,compare_price,stock,emoji,image_url,gallery_json,video_url,description,specs,published,featured) VALUES ('p8','محافظ لبه تخت کودک','bed-guard','MY-SA-008','safety',690000,0,16,'🧸','','','','برای افزایش ایمنی فضای خواب کودک.','گروه: ایمنی کودک | کاربرد: محافظ کناره تخت',1,0);
