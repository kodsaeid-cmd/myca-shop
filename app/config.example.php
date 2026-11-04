<?php
declare(strict_types=1);
// Copy to app/config.local.php and fill with cPanel MySQL details.
// Never commit config.local.php to Git.
return [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'YOUR_CPANEL_DB_NAME',
    'db_user' => 'YOUR_CPANEL_DB_USER',
    'db_password' => 'REPLACE_WITH_SECRET',
    'session_name' => 'myca_shop_admin',
];
