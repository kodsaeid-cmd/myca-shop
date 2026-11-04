<?php
declare(strict_types=1);
// One real homepage for the shop. CMS-published home or safe DB-based preview.
// Never depend on index.html: some cPanel installs omit or block that legacy file.
require __DIR__ . '/storefront.php';
