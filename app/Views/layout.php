<?php
declare(strict_types=1);
use Binprof\Views\Screen as V;
use function Binprof\{asset,e,url};
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · BINPROFKES</title>
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <?php if ($template === 'dashboard'): ?>
        <link rel="stylesheet" href="<?= e(asset('vendor/leaflet/leaflet.css')) ?>">
        <script defer src="<?= e(asset('vendor/leaflet/leaflet.js')) ?>"></script>
        <script defer src="<?= e(asset('map.js')) ?>"></script>
    <?php endif; ?>
</head>
<body class="<?= $user ? 'app' : 'guest' ?>">
<a class="skip" href="#content">Lewati ke isi</a>
<?php if ($user): ?>
<aside class="sidebar">
    <a class="brand" href="<?= e(url()) ?>"><span aria-hidden="true" class="brand-mark">+</span><span>BINPROFKES<small>Administrasi Personel</small></span></a>
    <details class="mobile-nav"><summary>Navigasi</summary><nav aria-label="Navigasi seluler">
        <?php foreach ($menu as $route=>$label): ?><?= V::link($label,$route,[],$route === $currentRoute ? 'selected' : '') ?><?php endforeach; ?>
    </nav></details>
    <nav class="desktop-nav" aria-label="Navigasi utama">
        <?php foreach ($menu as $route=>$label): ?><?= V::link($label,$route,[],$route === $currentRoute ? 'selected' : '') ?><?php endforeach; ?>
    </nav>
    <div class="signout"><?php V::form('logout'); ?><button class="secondary">Keluar</button></form></div>
</aside>
<?php endif ?>
<main id="content">
<?php if ($user): ?>
    <header class="topline"><?= V::link('BINPROFKES','dashboard') ?><span aria-hidden="true"> / </span><?= e($title) ?></header>
    <section class="page-heading"><h1><?= e($title) ?></h1></section>
<?php endif ?>
<?php if (!empty($_SESSION['flash'])): ?>
    <p class="notice" role="status"><?= e($_SESSION['flash']) ?></p>
    <?php unset($_SESSION['flash']); ?>
<?php endif ?>
<?php require __DIR__ . '/' . $template . '.php'; ?>
</main>
</body>
</html>
