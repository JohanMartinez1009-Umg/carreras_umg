<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWhatsAppConfig extends Migration
{
    public function up(): void
    {
        // Tabla de una sola fila (id = 1) con las credenciales de WhatsApp Business Cloud API.
        $this->forge->addField([
            'id'              => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'auto_increment' => true],
            'phone_number_id' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'access_token'    => ['type' => 'TEXT', 'null' => true],
            'app_secret'      => ['type' => 'TEXT', 'null' => true],
            'verify_token'    => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('whatsapp_config');

        // Se inserta la fila id=1 aquí (no en el seeder) para que también quede lista
        // en instalaciones ya desplegadas, que no vuelven a correr el seeder.
        $this->db->table('whatsapp_config')->insert([
            'id'         => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('whatsapp_config', true);
    }
}
