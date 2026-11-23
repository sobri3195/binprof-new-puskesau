<?php use Binprof\Views\Screen as V; use Binprof\Services\Inbox; use function Binprof\e; ?>
<section class="card"><h2>Notifikasi Anda</h2>
<?php if (!$rows): ?><p class="empty">Belum ada notifikasi yang dapat dibuka.</p><?php endif ?>
<ul class="list"><?php foreach (array_slice($rows,0,50) as $item): ?><li class="section-heading">
<div><?= V::link(Inbox::label($item['kind']),$item['personnel_id'] ? 'person' : 'request',['id'=>$item['personnel_id'] ?: $item['request_id']]) ?>
<small class="muted"><?= e(V::localTime($item['created_at'])) ?></small></div>
<?php if (!$item['read_at']): ?><?php V::form('notifications',['id'=>$item['id']]); ?><button class="secondary">Tandai dibaca</button></form><?php endif ?></li>
<?php endforeach ?></ul><?php V::more($rows,'notifications'); ?>
</section>
