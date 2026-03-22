<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class SettingsModel extends BaseModel
{
    private const TABLE = 'site_settings';

    public function __construct(PDO $db)
    {
        parent::__construct($db);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        $statement = $this->db->query('SELECT * FROM ' . self::TABLE . ' LIMIT 1');
        $row = $statement->fetch();

        if (!$row) {
            $now = $this->timestamp();
            $this->insert(self::TABLE, [
                'admin_enabled' => 0,
                'admin_username' => null,
                'admin_password_hash' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $statement = $this->db->query('SELECT * FROM ' . self::TABLE . ' LIMIT 1');
            $row = $statement->fetch();
        }

        return $row ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicSettings(): array
    {
        $row = $this->getRaw();
        return $this->format($row, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSecureSettings(): array
    {
        $row = $this->getRaw();
        return $this->format($row, true);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateAdminSettings(array $payload): array
    {
        $row = $this->getRaw();
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return $this->getSecureSettings();
        }

        $updates = [
            'updated_at' => $this->timestamp(),
        ];

        if (array_key_exists('enabled', $payload)) {
            $updates['admin_enabled'] = $payload['enabled'] ? 1 : 0;
        }

        if (array_key_exists('username', $payload)) {
            $updates['admin_username'] = $payload['username'];
        }

        if (array_key_exists('passwordHash', $payload)) {
            $updates['admin_password_hash'] = $payload['passwordHash'];
        }

        if (!empty($updates)) {
            $this->updateById(self::TABLE, $id, $updates);
        }

        return $this->getSecureSettings();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function format(array $row, bool $includeSensitive): array
    {
        $data = [
            'enabled' => (bool) ($row['admin_enabled'] ?? false),
        ];

        if ($includeSensitive) {
            $data['username'] = $row['admin_username'] ?? null;
            $data['hasPassword'] = !empty($row['admin_password_hash']);
        }

        return $data;
    }
}
