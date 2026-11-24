<?php use Binprof\Views\Screen as V; use function Binprof\e; ?>
<details class="card" open><summary>Cari dan filter</summary><?php V::filter($route,$user,$filter); ?></details>
<section class="card">
    <div class="section-heading"><h2>Hasil pencarian</h2><div class="actions">
        <?php if ($user->allows('write') && $route === 'personnel'): ?><?= V::link('Tambah personel','new',[],'button') ?><?php endif ?>
        <?php if ($user->allows('export')): ?>
            <?php V::form('export',$filter); ?><button class="secondary">Ekspor CSV</button></form>
        <?php endif ?>
    </div></div>
    <?php if (!$list): ?><p class="empty">Tidak ada personel sesuai filter.</p><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Nama</th><th>Satuan</th><th>Kategori</th><th>Status</th><th>Perkiraan pensiun</th></tr></thead>
    <tbody><?php foreach (array_slice($list,0,25) as $row): ?><tr>
        <td><?= V::link($row['name'],'person',['id'=>$row['id']]) ?></td>
        <td><?= e($row['unit']) ?></td><td><?= e($row['category']) ?></td><td><?= V::badge($row['service_status']) ?></td>
        <td><?= e($row['estimate_date'] ?? 'Belum ditetapkan') ?></td>
    </tr><?php endforeach ?></tbody></table></div>
    <?php endif ?>
    <div class="actions">
        <?php if ($page > 1): ?><?= V::link('Sebelumnya',$route,$filter + ['page'=>$page-1],'button secondary') ?><?php endif ?>
        <?php if (count($list) > 25): ?><?= V::link('Berikutnya',$route,$filter + ['page'=>$page+1],'button secondary') ?><?php endif ?>
    </div>
</section>
