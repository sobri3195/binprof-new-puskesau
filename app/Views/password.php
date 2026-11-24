<?php use Binprof\Views\Screen as V; ?>
<section class="card">
<?php V::form('password'); ?>
<div class="fields">
<label><span>Kata sandi saat ini</span><input type="password" name="old" autocomplete="current-password" required></label>
<?php V::field('new','Kata sandi baru (14–72 byte)','password',null,true); ?>
</div><div class="actions"><button>Simpan dan masuk ulang</button></div>
</form>
</section>
