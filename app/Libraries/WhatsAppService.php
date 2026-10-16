<?php

namespace App\Libraries;

use App\Models\WhatsAppConfigModel;
use CURLFile;
use RuntimeException;

/**
 * Envía mensajes de WhatsApp (texto, lista interactiva, documentos) usando la
 * API de WhatsApp Business Cloud (Meta), con credenciales guardadas en BD
 * (panel de administración), siguiendo el mismo patrón que CorreoService.
 */
class WhatsAppService
{
    private const GRAPH_VERSION = 'v21.0';

    private array $config;

    public function __construct()
    {
        $this->config = model(WhatsAppConfigModel::class)->obtener();
    }

    public static function cifrar(string $texto): string
    {
        return CorreoService::cifrar($texto);
    }

    public static function descifrar(string $cifrado): string
    {
        return CorreoService::descifrar($cifrado);
    }

    public function enviarTexto(string $destino, string $texto): void
    {
        $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $destino,
            'type'              => 'text',
            'text'              => ['body' => $texto],
        ]);
    }

    /**
     * @param array<int, array{id: string, title: string, description: string}> $filas Máximo 10 en total.
     */
    public function enviarListaInteractiva(string $destino, string $encabezado, array $filas): void
    {
        $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $destino,
            'type'              => 'interactive',
            'interactive'       => [
                'type' => 'list',
                'body' => ['text' => $encabezado],
                'action' => [
                    'button'   => 'Ver opciones',
                    'sections' => [
                        ['title' => 'Carreras', 'rows' => $filas],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Sube un archivo a la API de medios de Meta y devuelve su media_id.
     */
    public function subirMedia(string $rutaAbsoluta, string $mimeType = 'application/pdf'): string
    {
        if (! is_file($rutaAbsoluta)) {
            throw new RuntimeException('El archivo a enviar no existe.');
        }

        $respuesta = $this->post('media', [
            'messaging_product' => 'whatsapp',
            'type'               => $mimeType,
            'file'               => new CURLFile($rutaAbsoluta, $mimeType),
        ], multipart: true);

        if (empty($respuesta['id'])) {
            throw new RuntimeException('No se pudo subir el archivo a WhatsApp.');
        }

        return (string) $respuesta['id'];
    }

    public function enviarDocumento(string $destino, string $mediaId, string $nombreArchivo): void
    {
        $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $destino,
            'type'              => 'document',
            'document'          => [
                'id'       => $mediaId,
                'filename' => $nombreArchivo,
            ],
        ]);
    }

    /**
     * @param array{ruta: string, nombre_original: string} $archivo
     */
    public function enviarPdfDeCarrera(string $destino, array $archivo): void
    {
        $ruta    = ArchivosCarrera::rutaAbsoluta($archivo['ruta']);
        $mediaId = $this->subirMedia($ruta);
        $this->enviarDocumento($destino, $mediaId, $archivo['nombre_original']);
    }

    /**
     * Valida el token llamando a un endpoint liviano (sin enviar mensajes).
     *
     * @throws RuntimeException si falta configuración o el token/phone_number_id no son válidos
     */
    public function probarConexion(): array
    {
        $phoneId = (string) ($this->config['phone_number_id'] ?? '');
        if ($phoneId === '' || empty($this->config['access_token'])) {
            throw new RuntimeException('Falta configurar el phone_number_id o el token de acceso.');
        }

        return $this->get($phoneId, ['fields' => 'id,display_phone_number']);
    }

    private function post(string $recurso, array $datos, bool $multipart = false): array
    {
        $phoneId = (string) ($this->config['phone_number_id'] ?? '');
        $token   = $this->token();
        if ($phoneId === '' || $token === '') {
            throw new RuntimeException('Falta configurar WhatsApp Business Cloud API (token o phone_number_id).');
        }

        $url     = 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . $phoneId . '/' . $recurso;
        $cliente = service('curlrequest', ['http_errors' => false]);

        $opciones = [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ];
        $opciones[$multipart ? 'multipart' : 'json'] = $datos;

        try {
            $respuesta = $cliente->post($url, $opciones);
        } catch (\Throwable $e) {
            log_message('error', 'Fallo al llamar a la API de WhatsApp ({recurso}): {msg}', [
                'recurso' => $recurso,
                'msg'     => $e->getMessage(),
            ]);

            throw new RuntimeException('No se pudo comunicar con WhatsApp. Revise la configuración.');
        }

        $cuerpo = json_decode((string) $respuesta->getBody(), true) ?? [];
        if ($respuesta->getStatusCode() >= 300) {
            log_message('error', 'Respuesta de error de WhatsApp ({recurso}): {cuerpo}', [
                'recurso' => $recurso,
                'cuerpo'  => (string) $respuesta->getBody(),
            ]);

            throw new RuntimeException('WhatsApp respondió con un error al intentar enviar el mensaje.');
        }

        return $cuerpo;
    }

    private function get(string $recurso, array $parametros = []): array
    {
        $token   = $this->token();
        if ($token === '') {
            throw new RuntimeException('Falta configurar el token de acceso de WhatsApp.');
        }

        $url     = 'https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . $recurso;
        $cliente = service('curlrequest', ['http_errors' => false]);

        try {
            $respuesta = $cliente->get($url, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'query'   => $parametros,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Fallo al llamar a la API de WhatsApp ({recurso}): {msg}', [
                'recurso' => $recurso,
                'msg'     => $e->getMessage(),
            ]);

            throw new RuntimeException('No se pudo comunicar con WhatsApp. Revise la configuración.');
        }

        $cuerpo = json_decode((string) $respuesta->getBody(), true) ?? [];
        if ($respuesta->getStatusCode() >= 300) {
            throw new RuntimeException('WhatsApp respondió con un error. Revise el token y el phone_number_id.');
        }

        return $cuerpo;
    }

    private function token(): string
    {
        $cifrado = (string) ($this->config['access_token'] ?? '');

        return $cifrado === '' ? '' : self::descifrar($cifrado);
    }
}
