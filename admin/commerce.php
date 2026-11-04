<?php
declare(strict_types=1);
// Legacy entry: keep old bookmarked link working, without a second admin UI.
header('Cache-Control: no-store, private');
header('Location: /admin/', true, 302);
exit;
