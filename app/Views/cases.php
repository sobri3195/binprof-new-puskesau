<?php use Binprof\Views\Screen as V; use function Binprof\{crypto,e}; ?>
<section class="card"><div class="section-heading"><h2>Daftar penugasan</h2>
<?php if ($user->account['case_creator']): ?><?= V::link('Buat kasus','new',['module'=>'case'],'button') ?><?php endif ?></div>
<?php if (!$cases): ?><p class="empty">Tidak ada penugasan yang dapat dibuka.</p><?php endif ?>
<ul class="list"><?php foreach (array_slice($cases,0,50) as $case): ?>
<?php $data = $case['content_cipher'] ? crypto()->open($case['content_cipher'],'case:' . $case['id']) : []; ?>
<li><?= V::link('#' . $case['id'] . ' · ' . ($data['title'] ?? 'Belum terverifikasi'),'case',['id'=>$case['id']]) ?> · <?= e($case['unit']) ?></li>
<?php endforeach ?></ul><?php V::more($cases,'cases'); ?>
</section>
