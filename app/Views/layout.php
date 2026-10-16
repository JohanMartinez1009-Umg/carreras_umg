<?php
    // El tema se guarda en una cookie (no localStorage) para que el servidor pueda
    // aplicarlo desde el primer render de cada página, sin depender de JavaScript
    // ni de que el navegador permita almacenamiento local.
    $tema = $_COOKIE['tema'] ?? null;
    $temaAtributo = $tema === 'oscuro' ? ' data-theme="dark"' : ($tema === 'claro' ? ' data-theme="light"' : '');
?>
<!doctype html>
<html lang="es"<?= $temaAtributo ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($this->renderSection('titulo')) ?> · Carreras UMG</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/logo-umg.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body>
<div class="cinta-umg" aria-hidden="true"></div>
<?php $esAdmin = session()->get('rol') === 'admin'; ?>

<header class="barra">
    <a class="barra-marca" href="<?= site_url('consulta') ?>">
        <img class="barra-logo" src="<?= base_url('assets/img/logo-umg.png') ?>" alt="" width="36" height="36">
        Carreras UMG
    </a>
    <button class="btn-hamburguesa" type="button" id="btn-menu" aria-expanded="false" aria-controls="menu-principal" aria-label="Abrir menú">
        <span></span><span></span><span></span>
    </button>
    <nav class="barra-acciones" id="menu-principal" aria-label="Navegación principal">
        <span class="barra-usuario"><?= esc(session()->get('nombre') ?? '') ?></span>
        <a class="btn btn-pequeno btn-secundario" href="<?= site_url('consulta') ?>">Consulta</a>
        <?php if ($esAdmin): ?>
            <a class="btn btn-pequeno btn-secundario" href="<?= site_url('admin/carreras') ?>">Administrar carreras</a>
            <a class="btn btn-pequeno btn-secundario" href="<?= site_url('admin/usuarios') ?>">Usuarios</a>
            <a class="btn btn-pequeno btn-secundario" href="<?= site_url('admin/correo') ?>">Correo</a>
            <a class="btn btn-pequeno btn-secundario" href="<?= site_url('admin/whatsapp') ?>">WhatsApp</a>
        <?php endif; ?>
        <form method="post" action="<?= site_url('logout') ?>" style="margin:0">
            <?= csrf_field() ?>
            <button class="btn btn-pequeno btn-peligro" type="submit">Salir</button>
        </form>
    </nav>
    <button class="tema-boton" type="button" data-tema aria-label="Cambiar tema"></button>
</header>

<main class="contenido">
    <?php if ($ok = session()->getFlashdata('ok')): ?>
        <div class="aviso aviso-ok" role="status"><?= esc($ok) ?></div>
    <?php endif; ?>

    <?php if ($error = session()->getFlashdata('error')): ?>
        <div class="aviso aviso-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <?php if ($errores = session()->getFlashdata('errores')): ?>
        <div class="aviso aviso-error" role="alert">
            <ul>
                <?php foreach ($errores as $mensaje): ?>
                    <li><?= esc($mensaje) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?= $this->renderSection('contenido') ?>
</main>

<script src="<?= base_url('assets/js/app.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/app.js') ?>" defer></script>
</body>
</html>
