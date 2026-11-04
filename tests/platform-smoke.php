<?php
declare(strict_types=1);
require_once __DIR__.'/../app/platform.php';
function assert_it(bool $condition,string $msg): void {if(!$condition){fwrite(STDERR,"FAILED: $msg\n");exit(1);}}
assert_it(cp_h('<script>')==='&lt;script&gt;','escape');
$blocks=cp_blocks_validate([['type'=>'hero','title'=>'مراقبت سالمندان','text'=>'خرید مطمئن','image'=>'','link'=>'/product/x/']]);
assert_it(count($blocks)===1&&$blocks[0]['type']==='hero','blocks');
assert_it($blocks[0]['link']==='/product/x/','internal link');
assert_it(cp_decode('[{"type":"faq"}]')[0]['type']==='faq','json decode');
assert_it(cp_text('مایکا',10)==='مایکا','utf8');
echo "Platform PHP smoke tests: OK\n";
