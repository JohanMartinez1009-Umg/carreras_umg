<?php

namespace App\Controllers;

use App\Libraries\WhatsAppService;
use App\Models\CarreraArchivoModel;
use App\Models\CarreraModel;
use App\Models\WhatsAppConfigModel;
use Throwable;

/**
 * Webhook público de WhatsApp Business Cloud API: recibe preguntas de ciudadanos
 * y responde automáticamente con el PDF (trifoliar) de la carrera correspondiente.
 *
 * Endpoint público y sin sesión por requisito de Meta: la única protección es la
 * validación de firma (X-Hub-Signature-256) en recibir(). Está exento del filtro
 * CSRF global (ver app/Config/Filters.php).
 */
class WhatsAppWebhook extends BaseController
{
    private const MAXIMO_OPCIONES_LISTA = 10;

    public function verificar()
    {
        $config = model(WhatsAppConfigModel::class)->obtener();

        $modo          = (string) $this->request->getGet('hub_mode');
        $token         = (string) $this->request->getGet('hub_verify_token');
        $challenge     = (string) $this->request->getGet('hub_challenge');
        $verifyToken   = (string) ($config['verify_token'] ?? '');

        if ($modo === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, $token)) {
            // Content-Type texto plano: Meta compara el cuerpo EXACTO con el challenge.
            // Si se deja el Content-Type por defecto (html), el filtro DebugToolbar le
            // inyecta su script en entornos de desarrollo y rompe la verificación.
            return $this->response->setStatusCode(200)->setContentType('text/plain')->setBody($challenge);
        }

        return $this->response->setStatusCode(403)->setContentType('text/plain')->setBody('Forbidden');
    }

    public function recibir()
    {
        $cuerpoCrudo = (string) $this->request->getBody();
        $config      = model(WhatsAppConfigModel::class)->obtener();

        if (! $this->firmaValida($cuerpoCrudo, $config)) {
            return $this->response->setStatusCode(403)->setContentType('text/plain')->setBody('Firma invalida');
        }

        // Cualquier fallo al procesar/responder se registra, pero SIEMPRE se devuelve 200:
        // si se devuelve un error, Meta reintenta la entrega del mismo mensaje y puede
        // provocar envíos duplicados del PDF.
        try {
            $this->procesar(json_decode($cuerpoCrudo, true));
        } catch (Throwable $e) {
            log_message('error', 'WhatsAppWebhook: {msg}', ['msg' => $e->getMessage()]);
        }

        return $this->response->setStatusCode(200)->setContentType('text/plain')->setBody('EVENT_RECEIVED');
    }

    private function firmaValida(string $cuerpoCrudo, array $config): bool
    {
        $firmaHeader = $this->request->getHeaderLine('X-Hub-Signature-256');
        $appSecret   = (string) ($config['app_secret'] ?? '');

        if ($firmaHeader === '' || $appSecret === '') {
            return false;
        }

        $secreto  = WhatsAppService::descifrar($appSecret);
        $esperada = 'sha256=' . hash_hmac('sha256', $cuerpoCrudo, $secreto);

        return hash_equals($esperada, $firmaHeader);
    }

    private function procesar(?array $payload): void
    {
        $valor = $payload['entry'][0]['changes'][0]['value'] ?? null;
        if (! is_array($valor) || empty($valor['messages'])) {
            // Sin "messages" (p. ej. acuses de recibo/lectura en "statuses"): no hay nada que responder.
            return;
        }

        $mensaje = $valor['messages'][0];
        $de      = (string) $mensaje['from'];
        $servicio = new WhatsAppService();

        $idSeleccionado = $mensaje['interactive']['list_reply']['id'] ?? null;
        if (($mensaje['type'] ?? '') === 'interactive' && $idSeleccionado !== null) {
            $this->enviarCarreraPorId($servicio, $de, (int) $idSeleccionado);

            return;
        }

        $texto = trim((string) ($mensaje['text']['body'] ?? ''));
        $this->buscarYResponder($servicio, $de, $texto);
    }

    private function enviarCarreraPorId(WhatsAppService $servicio, string $de, int $carreraId): void
    {
        $archivos = model(CarreraArchivoModel::class)->porCarrera($carreraId);

        if (! isset($archivos['principal'])) {
            $servicio->enviarTexto($de, 'Lo sentimos, todavía no hay trifoliar disponible para esa carrera.');

            return;
        }

        $servicio->enviarPdfDeCarrera($de, $archivos['principal']);
    }

    private function buscarYResponder(WhatsAppService $servicio, string $de, string $texto): void
    {
        $coincidencias = $this->buscarCarreras($texto);
        $total         = count($coincidencias);

        if ($total === 1) {
            $this->enviarCarreraPorId($servicio, $de, (int) $coincidencias[0]['id']);

            return;
        }

        if ($total >= 2 && $total <= self::MAXIMO_OPCIONES_LISTA) {
            $filas = array_map(static fn (array $c): array => [
                'id'          => (string) $c['id'],
                'title'       => mb_strimwidth($c['nombre'], 0, 24, '...'),
                'description' => mb_strimwidth($c['nombre'], 0, 72, '...'),
            ], $coincidencias);

            $servicio->enviarListaInteractiva($de, 'Encontramos varias carreras. Elija una:', $filas);

            return;
        }

        $servicio->enviarTexto(
            $de,
            'Escriba el nombre (o parte del nombre) de la carrera que le interesa. '
            . 'Por ejemplo: "Ingeniería en Sistemas".',
        );
    }

    /**
     * Compara el texto recibido contra carreras.nombre ignorando acentos/mayúsculas
     * (no se usa LIKE de MySQL por los problemas de collation con tildes).
     *
     * @return array<int, array{id: int, nombre: string, archivos: int}>
     */
    private function buscarCarreras(string $texto): array
    {
        $normalizar = static function (string $s): string {
            $sinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;

            return mb_strtolower(trim(preg_replace('/\s+/', ' ', $sinAcentos)));
        };

        $buscado = $normalizar($texto);
        if ($buscado === '') {
            return [];
        }

        $todas     = model(CarreraModel::class)->conArchivos();
        $resultado = [];
        foreach ($todas as $fila) {
            if ((int) ($fila['archivos'] ?? 0) === 0) {
                continue;
            }

            if (str_contains($normalizar($fila['nombre']), $buscado)) {
                $resultado[] = $fila;
            }
        }

        return $resultado;
    }
}
