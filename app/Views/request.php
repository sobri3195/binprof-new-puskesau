<?php
use Binprof\Views\Screen as V;
use Binprof\Controllers\Web;
use Binprof\Services\Records;
use function Binprof\{crypto,e};
$module = $row['module'];
$hidden = ['id'=>$row['id'],'revision'=>$selected['revision']];
$owner = $user->id === (int) $row['owner_id'];
$canWrite = $current && $user->request($row,'write');
$draft = $canWrite && $row['status'] === 'draft';
$caseAllowedDownload = $module !== 'case' || $user->caseCan((int) $row['case_id'],'download');
?>
<section class="card">
    <div class="section-heading"><h2>Revisi <?= e($selected['revision']) ?></h2><?= V::badge($current ? $row['status'] : 'Riwayat revisi') ?></div>
    <?php if ((int) $row['revision'] > 1): ?><div class="actions">
    <?php if ((int) $selected['revision'] > 1): ?><?= V::link('Revisi sebelumnya','request',['id'=>$row['id'],'revision'=>$selected['revision']-1]) ?><?php endif ?>
    <?php if (!$current): ?><?= V::link('Revisi terbaru','request',['id'=>$row['id']]) ?><?php endif ?>
    </div><?php endif ?>
    <?php if ($decision): ?>
        <p class="notice"><?= V::badge($decision['decision']) ?> oleh <?= e($decision['username']) ?> · <?= e(V::localTime($decision['created_at'])) ?>
            <?= e(crypto()->open($decision['comment_cipher'],$context . ':decision')) ?></p>
    <?php endif ?>
    <?php if ($draft): ?>
        <?php V::form('request',$hidden+['action'=>'edit']); V::fields(Records::fields($module),$values); ?>
        <div class="actions"><button>Simpan perubahan</button></div></form>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Kolom</th><th>Versi dasar</th><th>Usulan</th></tr></thead><tbody>
        <?php $prior = Web::displayPairs($module,$before); ?>
        <?php foreach (Web::displayPairs($module,$values) as $i=>[$label,$value]): ?>
            <tr class="<?= ($prior[$i][1] ?? null) !== $value ? 'changed' : '' ?>"><th scope="row"><?= e($label) ?></th><td><?= nl2br(e($prior[$i][1] ?? '—')) ?></td><td><?= nl2br(e($value ?? '—')) ?></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
    <?php endif ?>
</section>
<section class="card"><h2>Lampiran revisi</h2>
    <?php if (!$documents): ?><p class="muted">Belum ada lampiran pada revisi ini.</p><?php endif ?>
    <ul class="list"><?php foreach ($documents as $doc): ?><li>
        <?= $caseAllowedDownload && ($row['status'] !== 'approved' || !$current || $module === 'case' || $user->allows('download'))
            ? V::link('Lampiran #'.$doc['id'],'document',['id'=>$doc['id']]) : 'Lampiran #' . e($doc['id']) ?>
        · <?= e($doc['mime']) ?> · <?= e(round($doc['size_bytes']/1024,1)) ?> KiB
    </li><?php endforeach ?></ul>
    <?php if ($draft): ?>
    <?php V::form('upload',$hidden,true); ?>
        <label><span>Tambah PDF, JPG, atau PNG</span><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label>
        <div class="actions"><button class="secondary">Unggah</button></div></form>
    <?php endif ?>
</section>
<?php if ($draft || ($canWrite && in_array($row['status'],['submitted','returned'],true)) || ($current && $row['status'] === 'submitted' && $user->request($row,'verify'))): ?>
<section class="card"><h2>Tindakan pengajuan</h2>
<?php V::form('request',$hidden); ?>
    <?php if ($current && $row['status'] === 'submitted' && $user->request($row,'verify')): ?>
        <?php V::field('comment','Catatan pemeriksaan (wajib saat dikembalikan)','textarea'); ?>
        <div class="actions"><button name="action" value="approve">Setujui</button><button class="secondary" name="action" value="return">Kembalikan</button></div>
    <?php elseif ($owner && $canWrite): ?>
        <div class="actions">
        <?php if ($draft && $user->request($row,'submit')): ?><button name="action" value="submit">Ajukan untuk verifikasi</button><button class="secondary" name="action" value="rebase">Perbarui versi dasar</button><?php endif ?>
        <?php if ($row['status'] === 'returned'): ?><button name="action" value="revise">Buat revisi baru</button><?php endif ?>
        <button class="secondary" name="action" value="cancel">Batalkan pengajuan</button></div>
    <?php endif ?>
</form></section><?php endif ?>
