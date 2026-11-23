<?php use Binprof\Views\Screen as V; use Binprof\Services\Records; use function Binprof\e; ?>
<section class="card"><div class="section-heading"><h2><?= $pendingOnly ? 'Pengajuan diajukan' : 'Pengajuan yang dapat Anda akses' ?></h2>
<?= $pendingOnly ? V::link('Semua status','requests',[],'button secondary') : V::link('Status diajukan','requests',['pending'=>'1'],'button secondary') ?></div>
<?php if (!$rows): ?><p class="empty">Belum ada pengajuan.</p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Pengajuan</th><th>Modul</th><th>Status</th><th>Dibuat</th></tr></thead><tbody>
<?php foreach (array_slice($rows,0,50) as $row): ?><tr><td><?= V::link('#'.$row['id'],'request',['id'=>$row['id']]) ?></td><td><?= e(Records::LABELS[$row['module']]) ?></td><td><?= V::badge($row['status']) ?></td><td><?= e(V::localTime($row['created_at'])) ?></td></tr><?php endforeach ?>
</tbody></table></div><?php V::more($rows,'requests',$pendingOnly ? ['pending'=>'1'] : []); ?>
<?php endif ?></section>
