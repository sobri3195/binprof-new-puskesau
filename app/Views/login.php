<?php use Binprof\Views\Screen as V; use function Binprof\e; ?>
<div class="login-shell">
    <section class="login-brand">
        <span class="brand-mark" aria-hidden="true">+</span>
        <h1>BINPROFKES</h1>
        <p>Sistem administrasi personel</p>
    </section>
    <section class="login-details">
        <h2>Masuk ke akun</h2>
        <?php if (!empty($message)): ?><p class="notice error" role="alert"><?= e($message) ?></p><?php endif ?>
        <?php V::form('login'); ?>
        <label><span>Nama pengguna</span><input autocomplete="username" name="username" value="<?= e(is_string($_POST['username'] ?? null) ? $_POST['username'] : '') ?>" required></label>
        <label><span>Kata sandi</span><input type="password" autocomplete="current-password" name="password" required></label>
        <button>Masuk</button>
        </form>
    </section>
</div>
