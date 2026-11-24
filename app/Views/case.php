<?php use Binprof\Views\Screen as V; use function Binprof\{crypto,e}; ?>
<section class="card"><div class="section-heading"><h2>Kasus #<?= e($id) ?></h2><div class="actions">
<?php if ($user->caseCan($id,'write')): ?><?= V::link('Ajukan perubahan','new',['module'=>'case','id'=>$id],'button secondary') ?><?php endif ?>
<?php if ($data && $user->caseCan($id,'export')): ?><?php V::form('case-export',['id'=>$id]); ?><?php V::exportButtons(); ?></form><?php endif ?>
</div></div>
<?php if (!$data): ?><p class="muted">Belum ada revisi terverifikasi.</p><?php else: ?><?php V::pairs('case',$data); ?><?php endif ?>
</section>
<section class="card"><h2>Pihak terkait</h2>
<ul class="list"><?php foreach ($parties as $party): ?><li><?= e($party['name']) ?> · <?= e(crypto()->open($party['role_cipher'],'party:' . $id . ':' . $party['id'])) ?></li><?php endforeach ?></ul>
</section>
<section class="card"><h2>Perkembangan dan keputusan</h2>
<?php foreach ($events as $event): ?>
<details class="entry"><summary>Versi <?= e($event['version']) ?> · <?= e(V::localTime($event['created_at'])) ?></summary>
<?php V::pairs('case',crypto()->open($event['content_cipher'],'event:' . $id . ':' . $event['version'])); ?></details>
<?php endforeach ?>
</section>
<?php if ($documents): ?><section class="card"><h2>Lampiran</h2><ul class="list">
<?php foreach ($documents as $doc): ?><li><?= V::link('Lampiran #' . $doc['id'],'document',['id'=>$doc['id']]) ?></li><?php endforeach ?>
</ul></section><?php endif ?>
