<?php
use Binprof\Services\{LegacyImport,Records};
use Binprof\Views\Screen as V;
use function Binprof\e;
?>
<section class="card">
    <?php V::getForm('imports'); ?>
    <?php V::field('module','Modul impor',LegacyImport::MODULES,$module,true); ?>
    <div class="actions"><button>Pilih modul</button></div></form>
</section>

<?php if ($preview): ?>
<section class="card" aria-labelledby="import-preview">
    <h2 id="import-preview">Hasil pratinjau</h2>
    <p><?= count($preview['rows']) ?> baris valid · <?= count($preview['errors']) ?> baris perlu diperbaiki.</p>
    <?php if ($preview['errors']): ?>
    <ul><?php foreach ($preview['errors'] as $number=>$error): ?>
        <li>Baris <?= e($number) ?>. <?= e($error) ?></li>
    <?php endforeach ?></ul>
    <p>Perbaiki sumber dan unggah kembali sebelum membuat draf.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th scope="col">Baris</th><th scope="col"><?= e(LegacyImport::MODULES[$module]) ?></th></tr></thead>
        <tbody><?php foreach ($preview['rows'] as $row): ?><tr>
            <th scope="row"><?= e($row['number']) ?></th>
            <td><?= e($row['values']['name'] ?? $row['values']['institution'] ?? $row['values']['issuer']) ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div>
    <p>Simpan dalam 10 menit. Draf perlu diajukan dan disetujui melalui alur verifikasi.</p>
    <?php V::form('imports',['action'=>'commit','token'=>$preview['token']]); ?>
    <div class="actions"><button>Buat draf impor</button></div></form>
    <?php endif ?>
</section>
<?php endif ?>

<section class="card">
    <h2>Unggah CSV dan petakan kolom</h2>
    <p>Gunakan CSV UTF-8 dengan judul kolom unik, maksimal 100 baris data dan 128 KiB. Masukkan judul dari CSV pada setiap kolom yang ingin diimpor. Gunakan tanggal YYYY-MM-DD dan simpan nomor berawalan nol sebagai teks.</p>
    <?php if (!$units): ?>
    <p class="empty">Belum ada satuan aktif dalam cakupan akun ini.</p>
    <?php else: ?>
    <?php V::form('imports',['action'=>'preview','module'=>$module],true); ?>
    <div class="fields">
        <?php V::field('unit_id','Satuan tujuan',$units,$input['unit_id'] ?? null,true); ?>
        <?php V::field('separator','Pemisah CSV',['comma'=>'Koma','semicolon'=>'Titik koma'],$input['separator'] ?? 'comma',true); ?>
        <label><span>Berkas CSV</span><input type="file" name="document" accept=".csv,text/csv" required></label>
    </div>
    <fieldset>
        <legend>Pemetaan judul kolom CSV</legend>
        <div class="fields"><?php foreach ($fields as $key=>[$label,$kind]): ?>
            <?php V::field('mapping[' . $key . ']',$label,'text',$input['mapping'][$key] ?? null,$fields[$key][2] ?? false); ?>
        <?php endforeach ?></div>
    </fieldset>
    <details>
        <summary>ID master dan kode nilai</summary>
        <dl class="pairs"><?php foreach ($fields as [$label,$kind]):
            $options = is_array($kind) ? $kind : array_column(Records::options($kind),'name','id');
            if (!$options) { continue; }
        ?>
            <dt><?= e($label) ?></dt>
            <dd><?= e(implode(', ',array_map(static fn($id,$name)=>$id . ' — ' . $name,array_keys($options),$options))) ?></dd>
        <?php endforeach ?></dl>
    </details>
    <div class="actions"><button>Periksa CSV</button></div></form>
    <?php endif ?>
</section>
