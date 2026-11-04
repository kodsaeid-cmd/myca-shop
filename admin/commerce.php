<?php
declare(strict_types=1);
// Consolidated management URL: legacy bookmarks redirect to the unified entry.
header('Cache-Control: no-store, private');
header('Location: /admin/', true, 302);
exit;
