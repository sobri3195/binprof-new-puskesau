<?php use Binprof\Views\Screen as V; use Binprof\Services\Records; use function Binprof\e; ?>
<section class="card"><h2>Daftar akun</h2>
<ul class="list"><?php foreach (array_slice($rows,0,50) as $row): ?><li>
<?= (int) $row['id'] === $user->id ? e($row['username'] . ' (akun Anda)') : V::link($row['username'],'accounts',['id'=>$row['id']]) ?>
 · ID <?= e($row['id']) ?> · <?= $row['active'] ? 'Aktif' : 'Nonaktif' ?></li><?php endforeach ?></ul>
<?php V::more($rows,'accounts'); ?></section>
<?php if (!$edit || (int) $edit['id'] !== $user->id): ?>
<section class="card"><h2><?= $edit ? 'Ubah akun' : 'Buat akun' ?></h2>
<?php V::form('accounts',['id'=>$edit['id'] ?? '']); ?>
<div class="fields">
<?php V::field('username','Nama pengguna','text',$edit['username'] ?? '',true);
V::field('password',$edit ? 'Kata sandi baru (opsional)' : 'Kata sandi awal','password',null,!$edit);
V::field('active','Status',[1=>'Aktif',0=>'Nonaktif'],$edit['active'] ?? 1,true); ?>
</div>
<div class="fields">
<fieldset><legend>Peran</legend><?php foreach ($roles as $role): ?>
<label class="check"><input type="checkbox" name="roles[]" value="<?= e($role['code']) ?>" <?= in_array($role['code'],$selectedRoles,true) ? 'checked' : '' ?>><?= e($role['label']) ?></label>
<?php endforeach ?></fieldset>
<fieldset><legend>Cakupan satuan eksplisit</legend><?php foreach (Records::options('unit',false) as $unit): ?>
<label class="check"><input type="checkbox" name="units[]" value="<?= e($unit['id']) ?>" <?= in_array($unit['id'],$selectedUnits) ? 'checked' : '' ?>><?= e($unit['name']) ?></label>
<?php endforeach ?></fieldset>
<fieldset><legend>Modul verifikasi (peran Verifikator pusat)</legend><?php foreach (array_diff_key(Records::LABELS,['case'=>true]) as $module=>$label): ?>
<label class="check"><input type="checkbox" name="modules[]" value="<?= e($module) ?>" <?= in_array($module,$selectedModules,true) ? 'checked' : '' ?>><?= e($label) ?></label>
<?php endforeach ?></fieldset>
</div>
<div class="actions"><button>Simpan akun</button><?php if ($edit): ?><?= V::link('Tambah baru','accounts',[],'button secondary') ?><?php endif ?></div>
</form></section><?php endif ?>
