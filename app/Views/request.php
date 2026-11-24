<?php
use Binprof\Views\Screen as V;
use Binprof\Controllers\Web;
use Binprof\Services\Records;
use function Binprof\{config,crypto,e};
$module = $row['module'];
$hidden = ['id'=>$row['id'],'revision'=>$selected['revision']];
$canWrite = $current && $canWrite;
$draft = $canWrite && $row['status'] === 'draft';
$draftForm = $hidden + ['draft_token'=>$draftToken];
?>
<section class="card">
    <div class="section-heading"><h2>Revisi <?= e($selected['revision']) ?></h2><?= V::badge($current ? $row['status'] : 'Riwayat revisi') ?></div>
    <?php if ($message): ?><p class="notice error" role="alert"><?= e($message) ?></p><?php endif ?>
    <?php if ($neighbors['previous'] || $neighbors['following']): ?>
        <nav class="actions" aria-label="Riwayat revisi">
            <?php if ($neighbors['previous']): ?><?= V::link('Revisi sebelumnya','request',['id'=>$row['id'],'revision'=>$neighbors['previous']]) ?><?php endif ?>
            <?php if ($neighbors['following']): ?><?= V::link('Revisi berikutnya','request',['id'=>$row['id'],'revision'=>$neighbors['following']]) ?><?php endif ?>
        </nav>
    <?php endif ?>
    <?php if ($decision): ?>
        <p class="notice"><?= V::badge($decision['decision']) ?> oleh <?= e($decision['username']) ?> · <?= e(V::localTime($decision['created_at'])) ?>
            <?= e(crypto()->open($decision['comment_cipher'],$context . ':decision')) ?></p>
    <?php endif ?>
    <?php if ($draft): ?>
        <?php if ($module === 'profile'): ?>
            <?php $missing = Records::missingProfileFields($values); ?>
            <p class="muted"><?= $missing ? 'Wajib sebelum diajukan: ' . e(implode(', ',$missing)) : 'Identitas wajib lengkap.' ?></p>
        <?php endif ?>
        <?php V::form('request',$draftForm); V::fields(Records::fields($module),$values); ?>
        <div class="actions">
            <button name="action" value="edit">Simpan perubahan</button>
            <?php if ($user->request($row,'submit')): ?><button class="secondary" name="action" value="save-submit">Simpan dan ajukan</button><?php endif ?>
        </div></form>
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
        <?= $canDownload
            ? V::link('Lampiran #'.$doc['id'],'document',['id'=>$doc['id']]) : 'Lampiran #' . e($doc['id']) ?>
        · <?= e($doc['mime']) ?> · <?= e(round($doc['size_bytes']/1024,1)) ?> KiB
        <?php if ($draft): ?>
            <?php V::form('upload',$hidden+['action'=>'detach','document_id'=>$doc['id']]); ?>
            <button class="secondary">Lepas lampiran #<?= e($doc['id']) ?></button></form>
        <?php endif ?>
    </li><?php endforeach ?></ul>
    <?php if ($draft): ?>
    <?php if ($neighbors['previous']): ?><p class="muted">Unggah lampiran untuk revisi ini. Berkas sebelumnya tersedia pada riwayat revisi.</p><?php endif ?>
    <?php V::form('upload',$hidden,true); ?>
        <label><span>PDF, JPG, atau PNG · maks. <?= e(round(config()['upload_bytes']/1048576,2)) ?> MiB</span>
            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label>
        <?php if ($documents): ?>
            <?php V::field('replace_id','Ganti lampiran (opsional)',
                array_combine(array_column($documents,'id'),array_map(static fn($doc)=>'Lampiran #' . $doc['id'],$documents))); ?>
        <?php endif ?>
        <div class="actions"><button class="secondary">Unggah</button></div></form>
    <?php endif ?>
</section>
<?php if ($draft || ($canWrite && in_array($row['status'],['submitted','returned'],true)) || ($current && $row['status'] === 'submitted' && $user->request($row,'verify'))): ?>
<section class="card"><h2>Tindakan pengajuan</h2>
<?php V::form('request',$draftForm + ($rebasePlan ? ['base_token'=>$rebasePlan['token']] : [])); ?>
    <?php if ($current && $row['status'] === 'submitted' && $user->request($row,'verify')): ?>
        <?php V::field('comment','Catatan pemeriksaan (wajib saat dikembalikan)','textarea',$comment); ?>
        <div class="actions"><button name="action" value="approve">Setujui</button><button class="secondary" name="action" value="return">Kembalikan</button></div>
    <?php elseif ($canWrite): ?>
        <?php if ($rebasePlan['conflicts'] ?? []): ?>
            <?php
            $fields = array_keys(Records::fields($module));
            $latest = array_combine($fields,array_column(Web::displayPairs($module,$rebasePlan['current']),1));
            $proposal = array_combine($fields,array_column(Web::displayPairs($module,$rebasePlan['merged']),1));
            ?>
            <p class="notice">Pilih nilai pada setiap kolom yang berubah di data terbit dan di usulan.</p>
            <div class="fields">
                <?php foreach ($rebasePlan['conflicts'] as $field=>$label): ?>
                    <?php V::field('resolution[' . $field . ']',$label,[
                        'current'=>'Pertahankan data terbit: ' . ($latest[$field] ?? '—'),
                        'proposal'=>'Gunakan usulan: ' . ($proposal[$field] ?? '—'),
                    ]); ?>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <div class="actions">
        <?php if ($draft): ?><button class="secondary" name="action" value="rebase">Perbarui versi dasar</button><?php endif ?>
        <?php if ($row['status'] === 'returned'): ?><button name="action" value="revise">Buat revisi baru</button><?php endif ?>
        <button class="secondary" name="action" value="cancel">Batalkan pengajuan</button></div>
    <?php endif ?>
</form></section><?php endif ?>
