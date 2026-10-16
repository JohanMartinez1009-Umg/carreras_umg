<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Acceso
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::intentar');
$routes->post('logout', 'Auth::salir');

// Consulta (secretaría y administradores)
$routes->group('consulta', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Consulta::index');
    $routes->get('carrera/(:num)', 'Consulta::ver/$1');
    $routes->post('carrera/(:num)/enviar', 'Consulta::enviar/$1');
});

// Archivos PDF: se sirven por controlador para que solo accedan usuarios con sesión.
$routes->get('archivo/(:num)/(:segment)', 'Consulta::archivo/$1/$2', ['filter' => 'auth']);

// Webhook de WhatsApp Business Cloud API: debe ser público (lo llama Meta, sin sesión).
// Su única protección es la validación de firma dentro del propio controlador.
$routes->get('whatsapp/webhook', 'WhatsAppWebhook::verificar');
$routes->post('whatsapp/webhook', 'WhatsAppWebhook::recibir');

// Administración (solo rol admin)
$routes->group('admin', ['filter' => 'admin'], static function ($routes) {
    $routes->get('/', 'Admin\Carreras::index');

    $routes->get('carreras', 'Admin\Carreras::index');
    $routes->get('carreras/nueva', 'Admin\Carreras::nueva');
    $routes->post('carreras', 'Admin\Carreras::crear');
    $routes->get('carreras/(:num)/editar', 'Admin\Carreras::editar/$1');
    $routes->post('carreras/(:num)', 'Admin\Carreras::actualizar/$1');
    $routes->post('carreras/(:num)/eliminar', 'Admin\Carreras::eliminar/$1');
    $routes->post('carreras/(:num)/archivo/(:segment)/eliminar', 'Admin\Carreras::eliminarArchivo/$1/$2');

    $routes->get('usuarios', 'Admin\Usuarios::index');
    $routes->get('usuarios/nuevo', 'Admin\Usuarios::nuevo');
    $routes->post('usuarios', 'Admin\Usuarios::crear');
    $routes->get('usuarios/(:num)/editar', 'Admin\Usuarios::editar/$1');
    $routes->post('usuarios/(:num)', 'Admin\Usuarios::actualizar/$1');
    $routes->post('usuarios/(:num)/eliminar', 'Admin\Usuarios::eliminar/$1');

    $routes->get('correo', 'Admin\Correo::index');
    $routes->post('correo', 'Admin\Correo::guardar');
    $routes->post('correo/probar', 'Admin\Correo::probar');

    $routes->get('whatsapp', 'Admin\WhatsApp::index');
    $routes->post('whatsapp', 'Admin\WhatsApp::guardar');
    $routes->post('whatsapp/probar', 'Admin\WhatsApp::probar');
});
