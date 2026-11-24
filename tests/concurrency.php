<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cli/common.php';

use Binprof\Policies\Access;
use Binprof\Problem;
use Binprof\Services\Changes;
use function Binprof\{db,id};

cli(static function (): int {
    [,$mode,$arg] = array_pad($_SERVER['argv'],3,'');
    if ($mode === 'prepare') {
        $user = Access::load(3);
        $request = Changes::create($user,'award',1,0,0,[
            'type_id'=>'4','issuer'=>'DATA DEMO Uji konkurensi','awarded_on'=>'2021-01-01',
        ]);
        Changes::mutate($user,$request,1,'submit',[]);
        echo $request;
    } elseif ($mode === 'approve') {
        try {
            Changes::mutate(Access::load(4),id($arg),1,'approve',[]);
            echo 'approved';
        } catch (Problem $error) {
            if ($error->getCode() !== 409) {
                throw $error;
            }
            echo 'conflict';
        }
    } elseif ($mode === 'check') {
        $count = db()->one('SELECT COUNT(*) AS n FROM approval_actions WHERE request_id=? AND decision=?',[id($arg),'approved']);
        $awards = db()->one('SELECT COUNT(*) AS n FROM personnel_awards WHERE issuer=?',['DATA DEMO Uji konkurensi']);
        if ($count['n'] !== 1 || $awards['n'] !== 1) {
            throw new RuntimeException('Concurrent writes did not preserve one decision');
        }
    } else {
        throw new RuntimeException('Unknown test mode');
    }
    return 0;
});
