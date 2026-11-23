<?php
use Binprof\Views\Screen as V;
use Binprof\Services\Inbox;
use function Binprof\{asset,config,e,today};
$without = array_filter($groups,static fn($group)=>$group['latitude'] === null);
$stats = ['Personel aktif'=>array_sum(array_column($groups,'total')),'Militer'=>array_sum(array_column($groups,'military')),
    'PNS'=>array_sum(array_column($groups,'pns')),'Satuan'=>count($groups),'Perkiraan ≤12 bulan'=>array_sum(array_column($groups,'retiring'))];
?>
<p class="muted">Personel aktif terverifikasi dalam cakupan Anda · <?= e(today()) ?> · Dimuat <?= e(V::localTime(gmdate('Y-m-d H:i:s'))) ?></p>
<div class="stats"><?php foreach ($stats as $label=>$number): ?>
    <div class="stat"><span><?= e($label) ?></span><strong><?= e(number_format($number,0,',','.')) ?></strong></div>
<?php endforeach; ?></div>
<details class="card" <?= $filter ? 'open' : '' ?>><summary>Filter sebaran</summary><?php V::filter('dashboard',$user,$filter); ?></details>
<div class="dashboard-grid">
    <section class="card map-card">
        <div class="section-heading"><div><h2>Sebaran satuan</h2><p class="muted">Lokasi satuan berdasarkan koordinat yang tercatat.</p></div>
            <button class="secondary" id="map-fullscreen" type="button" hidden>Layar penuh</button></div>
        <div id="map" aria-label="Peta sebaran satuan" data-outline="<?= e(asset('region.geojson')) ?>"
            data-tiles="<?= e(config()['tiles']) ?>" data-attribution="<?= e(config()['tile_attribution']) ?>"></div>
        <p id="map-status" class="muted" role="status">Tabel satuan tersedia di bawah peta.</p>
        <script type="application/json" id="map-data"><?= json_encode(array_map(static fn($g)=>$g + ['detail'=>\Binprof\url('personnel',array_replace($filter,['unit'=>$g['id'],'status'=>'active']))],$groups),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
    </section>
    <aside class="card">
        <h2>Tindak lanjut</h2>
        <?php if (!$inbox): ?><p class="muted">Belum ada notifikasi yang dapat dibuka.</p><?php endif ?>
        <ul class="list"><?php foreach ($inbox as $item): ?><li><?= V::link(Inbox::label($item['kind']),$item['personnel_id'] ? 'person' : 'request',['id'=>$item['personnel_id'] ?: $item['request_id']]) ?></li><?php endforeach ?></ul>
        <div class="actions"><?= V::link('Notifikasi','notifications',[],'button secondary') ?><?= V::link('Pengajuan diajukan' . ($pending ? ' (' . min(50,count($pending)) . (count($pending) > 50 ? '+' : '') . ')' : ''),'requests',['pending'=>'1'],'button secondary') ?></div>
    </aside>
</div>
<section class="card">
    <div class="section-heading"><div><h2>Daftar satuan</h2><p class="muted"><?= count($without) ?> satuan / <?= array_sum(array_column($without,'total')) ?> personel tanpa koordinat.</p></div>
        <?= V::link('Lihat personel','personnel',array_replace($filter,['status'=>'active']),'button secondary') ?></div>
    <?php if (!$groups): ?><p class="empty">Belum ada personel aktif yang sesuai filter Anda.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Satuan</th><th>Personel</th><th>Militer</th><th>PNS</th><th>Lokasi</th></tr></thead>
    <tbody><?php foreach ($groups as $group): ?><tr>
        <td><?= V::link($group['name'],'personnel',array_replace($filter,['unit'=>$group['id'],'status'=>'active'])) ?></td>
        <td><?= $group['total'] ?></td><td><?= $group['military'] ?></td><td><?= $group['pns'] ?></td>
        <td><?= $group['latitude'] === null ? 'Belum tercatat' : 'Tercatat' ?></td>
    </tr><?php endforeach ?></tbody></table></div>
    <?php endif ?>
</section>
