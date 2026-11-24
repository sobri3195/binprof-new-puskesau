<?php use Binprof\Views\Screen as V; use Binprof\Services\Admin; use function Binprof\e; ?>
<nav class="anchor-nav" aria-label="Jenis master"><?php foreach(Admin::MASTER as $key=>[,$label]): ?><?= V::link($label,'master',['kind'=>$key]) ?><?php endforeach ?></nav>
<section class="card">
<h2><?= e(Admin::MASTER[$kind][1]) ?></h2>
<?php if (!$rows): ?><p class="empty">Belum ada data master.</p><?php endif ?>
<ul class="list"><?php foreach (array_slice($rows,0,50) as $row): ?>
<li><?= V::link($row['code'] . ' · ' . $row['name'],'master',['kind'=>$kind,'id'=>$row['id']]) ?> · <?= $row['active'] ? 'Aktif' : 'Nonaktif' ?></li>
<?php endforeach ?></ul><?php V::more($rows,'master',['kind'=>$kind]); ?>
</section>
<section class="card"><h2><?= $edit ? 'Ubah master' : 'Tambah master' ?></h2>
<?php if ($message): ?><p class="notice error" role="alert"><?= e($message) ?></p><?php endif ?>
<?php V::form('master',['kind'=>$kind,'id'=>$edit['id'] ?? '']); ?>
<div class="fields">
<?php
V::field('code','Kode','text',$values['code'] ?? '',true);
V::field('name','Nama','text',$values['name'] ?? '',true);
V::field('active','Status',[1=>'Aktif',0=>'Nonaktif'],$values['active'] ?? 1,true);
if ($kind === 'unit') {
    V::field('parent_id','Satuan induk','unit',$values['parent_id'] ?? null);
    V::field('latitude','Lintang','text',$values['latitude'] ?? '');
    V::field('longitude','Bujur','text',$values['longitude'] ?? '');
}
if ($kind === 'category') {
    V::field('identifier_type_id','Jenis nomor kedinasan','identifier',$values['identifier_type_id'] ?? null,true);
    V::field('dikmil','Dikmil berlaku',[1=>'Ya',0=>'Tidak'],$values['dikmil'] ?? 0,true);
}
if ($kind === 'facility') {
    V::field('unit_id','Satuan','unit',$values['unit_id'] ?? null,true);
}
?>
</div><div class="actions"><button>Simpan</button><?php if ($edit): ?><?= V::link('Tambah baru','master',['kind'=>$kind],'button secondary') ?><?php endif ?></div></form>
</section>
