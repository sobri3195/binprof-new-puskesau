<?php
use Binprof\Views\Screen as V;
use Binprof\Services\Records;
use function Binprof\{crypto,e,today};
$p = ['person'=>$person['id']];
$edit = $user->allows('write');
?>
<nav class="anchor-nav" aria-label="Bagian profil"><a href="#identitas">Identitas</a><a href="#kedinasan">Kedinasan</a><a href="#pendidikan">Pendidikan</a><a href="#penghargaan">Penghargaan</a><a href="#pensiun">Pensiun</a><a href="#dokumen">Dokumen dan riwayat</a></nav>
<section class="card" id="identitas">
    <div class="section-heading"><div><h2>Identitas</h2><p class="muted">ID <?= e($person['id']) ?> · Versi <?= e($person['version']) ?> · <?= V::badge($person['service_status']) ?></p></div>
    <?php if ($edit): ?><?= V::link('Ajukan koreksi','new',$p+['module'=>'profile'],'button secondary') ?><?php endif ?></div>
    <?php V::pairs('profile',$profile); ?>
</section>
<section class="card" id="kedinasan"><div class="section-heading"><h2>Riwayat kedinasan</h2>
    <?php if ($edit): ?><?= V::link('Ajukan mutasi / status','new',$p+['module'=>'service'],'button secondary') ?><?php endif ?></div>
<div class="table-wrap"><table><thead><tr><th>Satuan</th><th>Jabatan</th><th>Status</th><th>Mulai</th><th>Sampai sebelum</th></tr></thead>
<tbody><?php foreach ($assignments as $item): ?><tr><td><?= e($item['unit']) ?></td><td><?= e($item['position_title'] ?? '—') ?></td><td><?= V::badge($item['service_status']) ?></td><td><?= e($item['start_on']) ?></td><td><?= e($item['end_before'] ?? 'Sekarang') ?></td></tr><?php endforeach ?></tbody></table></div>
</section>
<section class="card" id="pendidikan">
<?php foreach (['dikum','dikmil'] as $module): ?>
    <div class="section-heading"><h2><?= e(Records::LABELS[$module]) ?></h2>
        <?php if ($edit): ?><?= V::link('Tambah riwayat','new',$p+['module'=>$module],'button secondary') ?><?php endif ?></div>
    <?php $items = array_filter($education,static fn($row)=>$row['kind'] === $module); ?>
    <?php if (!$items): ?><p class="empty">Belum ada riwayat terverifikasi.</p><?php endif ?>
    <?php foreach ($items as $item): ?>
        <?php $item['certificate'] = $item['certificate_cipher'] ? crypto()->open($item['certificate_cipher'],'certificate:' . $item['id']) : null; ?>
        <details class="entry"><summary><?= e($item['label'] . ' · ' . $item['institution']) ?></summary>
            <?php V::pairs($module,$item); ?>
            <?php if ($edit): ?><?= V::link('Ajukan koreksi','new',$p+['module'=>$module,'id'=>$item['id']]) ?><?php endif ?>
        </details>
    <?php endforeach ?>
<?php endforeach ?>
</section>
<section class="card" id="penghargaan"><div class="section-heading"><h2>Penghargaan</h2>
    <?php if ($edit): ?><?= V::link('Tambah penghargaan','new',$p+['module'=>'award'],'button secondary') ?><?php endif ?></div>
    <?php if (!$awards): ?><p class="empty">Belum ada penghargaan terverifikasi.</p><?php endif ?>
    <?php foreach ($awards as $item): ?>
        <details class="entry"><summary><?= e($item['label'] . ' · ' . $item['awarded_on']) ?></summary>
            <?php if ((int) $item['matches'] > 1): ?><p class="notice">Potensi duplikasi karena jenis dan tanggal sama.</p><?php endif ?>
            <?php V::pairs('award',$item); ?>
            <?php if ($edit): ?><?= V::link('Ajukan koreksi','new',$p+['module'=>'award','id'=>$item['id']]) ?><?php endif ?>
        </details>
    <?php endforeach ?>
</section>
<section class="card" id="pensiun"><div class="section-heading"><h2>Perkiraan pensiun</h2>
    <?php if ($edit): ?><?= V::link('Ajukan perkiraan','new',$p+['module'=>'retirement'],'button secondary') ?><?php endif ?></div>
    <?php if (!$forecast): ?><p class="empty">Perkiraan belum ditetapkan.</p><?php else: ?>
        <?php if ($forecast['validation_status'] === 'review'): ?><p class="notice">Perlu tinjau ulang karena data dasar berubah.</p><?php endif ?>
        <p><strong><?= e($forecast['estimate_date']) ?></strong> · <?= e($forecast['source']) ?></p>
        <?php $days = (new DateTimeImmutable(today()))->diff(new DateTimeImmutable($forecast['estimate_date'])); ?>
        <p class="muted"><?= $days->invert ? 'Tanggal telah lewat. Perlu konfirmasi.' : e($days->days) . ' hari menuju perkiraan.' ?></p>
        <p class="muted">Diverifikasi oleh <?= e($forecast['username']) ?> · <?= e(V::localTime($forecast['updated_at'])) ?></p>
    <?php endif ?>
</section>
<?php if ($relatedCases): ?>
<section class="card"><h2>Kasus dengan penugasan aktif</h2><ul class="list">
<?php foreach ($relatedCases as $related): ?><li><?= V::link('Kasus #' . $related['id'],'case',['id'=>$related['id']]) ?></li><?php endforeach ?>
</ul></section>
<?php endif ?>
<section class="card" id="dokumen"><h2>Dokumen dan riwayat perubahan</h2>
    <?php if (!$documents): ?><p class="muted">Tidak ada lampiran yang dapat diunduh dengan izin Anda.</p><?php endif ?>
    <ul class="list"><?php foreach ($documents as $doc): ?><li><?= V::link(Records::LABELS[$doc['module']] . ' · Lampiran #' . $doc['id'],'document',['id'=>$doc['id']]) ?></li><?php endforeach ?></ul>
    <ul class="list"><?php foreach ($history as $entry): ?><li><?= e(Records::LABELS[$entry['module']]) ?> · #<?= e($entry['id']) ?> · <?= e($entry['username']) ?> · <?= e(V::localTime($entry['created_at'])) ?></li><?php endforeach ?></ul>
</section>
