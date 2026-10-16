<?php

namespace App\Models;

use CodeIgniter\Model;

class WhatsAppConfigModel extends Model
{
    protected $table            = 'whatsapp_config';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $useAutoIncrement = false;
    protected $allowedFields    = [
        'phone_number_id', 'access_token', 'app_secret', 'verify_token',
    ];

    /**
     * La configuración es una única fila (id = 1).
     */
    public function obtener(): array
    {
        return $this->find(1) ?? [];
    }
}
