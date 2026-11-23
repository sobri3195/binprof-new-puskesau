<?php use Binprof\Views\Screen as V; use Binprof\Repositories\Directory; use function Binprof\e; ?>
<section class="card">
    <?php if (!empty($message)): ?><p class="notice error" role="alert"><?= e($message) ?></p><?php endif ?>
    <?php V::form('new',['module'=>$module,'person'=>$personId,'id'=>$item]); ?>
    <?php if (!$personId && !$item): ?>
        <?php V::field('unit','Satuan penanggung jawab',array_column(Directory::unitOptions($user),'name','id'),$_POST['unit'] ?? null,true); ?>
    <?php endif ?>
    <?php V::fields($fields,$values); ?>
    <div class="actions"><button>Simpan draf</button></div></form>
</section>
