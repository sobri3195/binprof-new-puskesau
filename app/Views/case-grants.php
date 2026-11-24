<?php use Binprof\Views\Screen as V; use Binprof\Services\Admin; ?>
<section class="card"><h2>Atur penugasan per kasus</h2>
<?php V::form('case-grants'); ?><div class="fields">
<?php V::field('id','ID kasus','number',null,true);
V::field('target','ID akun penerima','number',null,true);
V::field('capability','Tindakan',array_combine(Admin::CASE_CAPS,['Baca','Tulis','Ajukan','Verifikasi','Unduh','Ekspor']),null,true);
V::field('expires','Berlaku sampai','date'); ?>
</div><div class="actions"><button name="action" value="give">Beri izin</button><button class="secondary" name="action" value="revoke">Cabut izin</button></div>
</form></section>
