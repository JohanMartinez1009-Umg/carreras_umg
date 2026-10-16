<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\WhatsAppService;
use App\Models\WhatsAppConfigModel;
use RuntimeException;

class WhatsApp extends BaseController
{
    public function index()
    {
        return view('admin/whatsapp', [
            'config' => model(WhatsAppConfigModel::class)->obtener(),
        ]);
    }

    public function guardar()
    {
        $reglas = [
            'phone_number_id' => [
                'label'  => 'Phone Number ID',
                'rules'  => 'required|max_length[32]',
                'errors' => ['required' => 'Indique el Phone Number ID de WhatsApp Business.'],
            ],
            'verify_token' => [
                'label'  => 'Verify Token',
                'rules'  => 'required|max_length[190]',
                'errors' => ['required' => 'Indique un Verify Token (usted lo define) para el webhook.'],
            ],
        ];

        if (! $this->validate($reglas)) {
            return redirect()->back()->withInput()->with('errores', $this->validator->getErrors());
        }

        $datos = [
            'phone_number_id' => trim((string) $this->request->getPost('phone_number_id')),
            'verify_token'    => trim((string) $this->request->getPost('verify_token')),
        ];

        // El token de acceso y el app secret se guardan cifrados y solo se cambian si se escribe un valor nuevo.
        foreach (['access_token', 'app_secret'] as $campo) {
            $valor = (string) $this->request->getPost($campo);
            if ($valor !== '') {
                try {
                    $datos[$campo] = WhatsAppService::cifrar($valor);
                } catch (\Throwable $e) {
                    log_message('error', 'No se pudo cifrar {campo}: {msg}', ['campo' => $campo, 'msg' => $e->getMessage()]);

                    return redirect()->back()->withInput()->with('error', 'No se pudo proteger las credenciales. Revise la clave de cifrado (encryption.key) en .env.');
                }
            }
        }

        model(WhatsAppConfigModel::class)->update(1, $datos);

        return redirect()->to('/admin/whatsapp')->with('ok', 'Configuración de WhatsApp guardada.');
    }

    public function probar()
    {
        try {
            $respuesta = (new WhatsAppService())->probarConexion();
        } catch (RuntimeException $e) {
            return redirect()->to('/admin/whatsapp')->with('error', $e->getMessage());
        }

        $numero = $respuesta['display_phone_number'] ?? '(sin número)';

        return redirect()->to('/admin/whatsapp')->with('ok', 'Conexión correcta. Número configurado: ' . $numero . '.');
    }
}
