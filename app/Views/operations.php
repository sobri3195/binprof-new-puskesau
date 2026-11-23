<?php use Binprof\Views\Screen as V; use function Binprof\e; ?>
<section class="card"><h2>Pengingat pensiun</h2>
<p class="muted">Cron menjalankan pemeriksaan terjadwal. Pemeriksaan manual hanya berjalan saat tombol ditekan.</p>
<?php V::form('operations',['action'=>'rules']); ?>
<?php V::field('months','Ambang bulan sebelum perkiraan','text',$rules['reminders'],true); ?>
<div class="actions"><button>Simpan ambang</button></div></form>
<?php V::form('operations',['action'=>'run']); ?><div class="actions"><button class="secondary">Periksa sekarang</button></div></form>
</section>
<section class="card"><h2>Hasil job</h2>
<div class="table-wrap"><table><thead><tr><th>Mulai</th><th>Hasil</th><th>Diproses</th><th>Gagal</th></tr></thead>
<tbody><?php foreach ($runs as $run): ?><tr><td><?= e(V::localTime($run['started_at'])) ?></td><td><?= e($run['reason'] ?? 'Belum tercatat') ?></td><td><?= e($run['processed']) ?></td><td><?= e($run['failures']) ?></td></tr><?php endforeach ?></tbody></table></div>
</section>
<section class="card"><h2>Mutasi dan status terjadwal</h2>
<ul class="list"><?php foreach ($scheduled as $entry): ?><li>Pengajuan #<?= e($entry['request_id']) ?> · <?= e($entry['effective_on']) ?> · <?= e(['scheduled'=>'Terjadwal','applied'=>'Diterapkan','conflict'=>'Perlu tinjau'][$entry['state']]) ?></li><?php endforeach ?></ul>
</section>
