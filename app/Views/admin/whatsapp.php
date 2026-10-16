<?= $this->extend('layout') ?>

<?= $this->section('titulo') ?>Configuración de WhatsApp<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<?php
    $valor = fn (string $campo) => old($campo) ?? ($config[$campo] ?? '');
    $tieneToken  = ! empty($config['access_token']);
    $tieneSecret = ! empty($config['app_secret']);
?>
<h1>WhatsApp Business Cloud API</h1>
<p class="suave" style="margin-bottom:24px">
    Credenciales para recibir preguntas por WhatsApp y responder automáticamente con el PDF de la
    carrera. Se obtienen en el panel de Meta for Developers, dentro del producto "WhatsApp".
</p>

<div class="tarjeta" style="margin-bottom:20px">
    <h2>URL del webhook</h2>
    <p class="suave pequeno">Péguela en Meta for Developers, junto con el Verify Token de abajo.</p>
    <code><?= esc(site_url('whatsapp/webhook')) ?></code>
</div>

<form class="tarjeta" method="post" action="<?= site_url('admin/whatsapp') ?>" autocomplete="off">
    <?= csrf_field() ?>

    <div class="campo">
        <label for="phone_number_id">Phone Number ID</label>
        <input type="text" id="phone_number_id" name="phone_number_id" required maxlength="32"
               value="<?= esc($valor('phone_number_id')) ?>">
    </div>

    <div class="campo">
        <label for="access_token">Token de acceso</label>
        <input type="password" id="access_token" name="access_token" maxlength="512" autocomplete="new-password"
               placeholder="<?= $tieneToken ? '•••••••• (sin cambios)' : '' ?>">
        <div class="campo-ayuda">
            <?= $tieneToken ? 'Guardado cifrado. Escriba uno nuevo solo si desea cambiarlo.' : 'Token permanente del usuario de sistema. Se guardará cifrado.' ?>
        </div>
    </div>

    <div class="campo">
        <label for="app_secret">App Secret</label>
        <input type="password" id="app_secret" name="app_secret" maxlength="190" autocomplete="new-password"
               placeholder="<?= $tieneSecret ? '•••••••• (sin cambios)' : '' ?>">
        <div class="campo-ayuda">
            <?= $tieneSecret ? 'Guardado cifrado. Se usa para validar que los mensajes vienen de Meta.' : 'Se usa para validar la firma de los mensajes entrantes. Se guardará cifrado.' ?>
        </div>
    </div>

    <div class="campo">
        <label for="verify_token">Verify Token</label>
        <input type="text" id="verify_token" name="verify_token" required maxlength="190"
               value="<?= esc($valor('verify_token')) ?>">
        <div class="campo-ayuda">Usted lo define. Debe coincidir con el que registre en Meta for Developers.</div>
    </div>

    <div class="acciones-form">
        <button class="btn btn-primario" type="submit">Guardar configuración</button>
    </div>
</form>

<form class="tarjeta" method="post" action="<?= site_url('admin/whatsapp/probar') ?>">
    <?= csrf_field() ?>
    <h2>Probar conexión</h2>
    <p class="suave pequeno">Verifica el token y el Phone Number ID sin enviar ningún mensaje.</p>
    <button class="btn btn-secundario btn-bloque" type="submit">Probar conexión</button>
</form>
<?= $this->endSection() ?>
