<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

final class PortfolioHomeOrderModel extends BaseModel
{
    public function exists(): bool
    {
        try {
            $statement = $this->db->query("SHOW TABLES LIKE 'portfolio_home_order'");
            return (bool) $statement?->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<int, int>
     */
    public function listPortfolioIds(): array
    {
        if (!$this->exists()) {
            return [];
        }

        try {
            $statement = $this->db->query('SELECT portfolio_id FROM portfolio_home_order ORDER BY sort_order ASC, id ASC');
            if (!$statement) {
                return [];
            }

            return array_values(array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []));
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<int, int> $portfolioIds
     */
    public function replaceOrder(array $portfolioIds): void
    {
        if (!$this->exists()) {
            return;
        }

        $normalized = [];
        foreach ($portfolioIds as $portfolioId) {
            $parsed = (int) $portfolioId;
            if ($parsed > 0) {
                $normalized[$parsed] = $parsed;
            }
        }

        $orderedIds = array_values($normalized);
        $now = $this->timestamp();

        $this->db->beginTransaction();

        try {
            $this->db->exec('DELETE FROM portfolio_home_order');

            if ($orderedIds) {
                $statement = $this->db->prepare(
                    'INSERT INTO portfolio_home_order (portfolio_id, sort_order, created_at, updated_at) '
                    . 'VALUES (:portfolio_id, :sort_order, :created_at, :updated_at)'
                );

                foreach ($orderedIds as $index => $portfolioId) {
                    $statement->execute([
                        'portfolio_id' => $portfolioId,
                        'sort_order' => $index,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}