<?php
use Binprof\Views\Screen as V;
use function Binprof\e;
$params = $filter + $report->filters;
?>
<details class="card" open>
    <summary>Cari dan filter</summary>
    <?php V::filter($report->route,$user,$filter,$report); ?>
</details>
<section class="card">
    <div class="section-heading">
        <div><h2>Riwayat terverifikasi</h2><p class="muted">Satu baris per catatan riwayat.</p></div>
        <?php if ($user->allows('export')): ?>
            <?php V::form('export',$params + ['report'=>$report->route]); ?><?php V::exportButtons(); ?></form>
        <?php endif ?>
    </div>
    <?php if (!$list): ?>
        <p class="empty">Tidak ada riwayat sesuai filter.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><?php foreach ($report->columns() as $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach ?></tr></thead>
            <tbody><?php foreach (array_slice($list,0,25) as $record): ?>
                <?php $row = $report->row($record); ?>
                <tr>
                    <th scope="row"><?= V::link(array_shift($row),'person',['id'=>$record['person_id']]) ?></th>
                    <?php foreach ($row as $value): ?><td><?= e($value ?? '—') ?></td><?php endforeach ?>
                </tr>
            <?php endforeach ?></tbody>
        </table></div>
    <?php endif ?>
    <div class="actions">
        <?php if ($page > 1): ?><?= V::link('Sebelumnya',$report->route,$params + ['page'=>$page-1],'button secondary') ?><?php endif ?>
        <?php if (count($list) > 25): ?><?= V::link('Berikutnya',$report->route,$params + ['page'=>$page+1],'button secondary') ?><?php endif ?>
    </div>
</section>
