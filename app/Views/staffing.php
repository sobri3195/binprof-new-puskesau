<?php
use Binprof\Services\Records;
use Binprof\Views\Screen as V;
use function Binprof\e;
?>
<section class="card">
    <?php V::getForm('staffing'); ?>
    <label><span>Bulan kebutuhan</span><input type="month" name="period" value="<?= e($month) ?>" required></label>
    <div class="actions"><button>Tampilkan</button></div></form>
</section>

<?php if ($user->allows('admin')): ?>
<section class="card">
    <h2>Master kebutuhan jabatan</h2>
    <p class="muted">Nama jabatan harus sama dengan riwayat kedinasan. Simpan kembali satuan, bulan dan nama yang sama untuk memperbarui jumlah.</p>
    <?php V::form('staffing',['period'=>$month]); ?>
    <div class="fields">
        <?php V::field('unit_id','Satuan',array_column(Records::options('unit',false),'name','id'),null,true); ?>
        <?php V::field('position_title','Jabatan','text',null,true); ?>
        <?php V::field('required_count','Jumlah kebutuhan','number',null,true); ?>
    </div>
    <div class="actions"><button>Simpan kebutuhan</button></div></form>
</section>
<?php endif ?>

<section class="card">
    <h2>Terisi dan kebutuhan jabatan</h2>
    <?php if ($user->allows('read') && $asOf): ?>
        <p class="muted">Personel aktif terverifikasi dalam cakupan satuan pada <?= e($asOf) ?>.</p>
    <?php elseif (!$asOf): ?>
        <p class="muted">Jumlah terisi tersedia mulai bulan acuan.</p>
    <?php endif ?>
    <?php if (!$rows): ?><p class="empty">Belum ada target kebutuhan pada bulan dan cakupan ini.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th scope="col">Satuan</th><th scope="col">Jabatan</th><th scope="col">Kebutuhan</th>
        <?php if ($user->allows('read')): ?><th scope="col">Terisi</th><th scope="col">Kekurangan</th><th scope="col">Kelebihan</th><?php endif ?>
        </tr></thead>
        <tbody><?php foreach (array_slice($rows,0,50) as $row):
            $delta = $row['filled'] === null ? null : (int) $row['required_count'] - (int) $row['filled'];
        ?><tr>
            <td><?= e($row['unit']) ?></td><th scope="row"><?= e($row['position_title']) ?></th><td><?= e($row['required_count']) ?></td>
            <?php if ($user->allows('read')): ?>
            <td><?= e($row['filled'] ?? '—') ?></td><td><?= e($delta === null ? '—' : max(0,$delta)) ?></td>
            <td><?= e($delta === null ? '—' : max(0,-$delta)) ?></td>
            <?php endif ?>
        </tr><?php endforeach ?></tbody>
    </table></div>
    <?php endif ?>
    <div class="actions">
        <?php if ($page > 1): ?><?= V::link('Sebelumnya','staffing',['period'=>$month,'page'=>$page-1],'button secondary') ?><?php endif ?>
        <?php if (count($rows) > 50): ?><?= V::link('Berikutnya','staffing',['period'=>$month,'page'=>$page+1],'button secondary') ?><?php endif ?>
    </div>
</section>
