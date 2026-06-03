<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkflowTaskStatusModel
{
    private PDO $conn;
    private string $lastError = '';

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function listActive(): array
    {
        $sql = "SELECT StatusID, Code, Name
                FROM dbo.tblWorkflowTaskStatuses
                WHERE IsActive = 1
                ORDER BY Name ASC";
        $stmt = $this->conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out  = [];
        foreach ($rows as $r) {
            $out[(int) $r['StatusID']] = (string) $r['Name'];
        }
        return $out;
    }

    public function findById(int $id): ?array
    {
        try {
            $sql = "SELECT StatusID, Code, Name, IsActive
                    FROM dbo.tblWorkflowTaskStatuses
                    WHERE StatusID = :id";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':id', $id, PDO::PARAM_INT);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function findNameById(int $id): ?string
    {
        $row = $this->findById($id);
        return $row['Name'] ?? null;
    }

    public function findIdByCode(string $code): ?int
    {
        try {
            $sql = "SELECT TOP 1 StatusID
                    FROM dbo.tblWorkflowTaskStatuses
                    WHERE UPPER(Code) = UPPER(:code)
                    ORDER BY StatusID ASC";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':code', $code);
            $st->execute();
            $id = $st->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function findOpenStatusId(): ?int
    {
        return $this->findIdByCode('OPEN');
    }

    public function findCompletedStatusId(): ?int
    {
        return $this->findIdByCode('COMPLETED');
    }

    public function isClosedStatusId(int $id): bool
    {
        $row = $this->findById($id);
        if (!$row) {
            return false;
        }

        $code = strtoupper(trim((string) ($row['Code'] ?? '')));
        $name = strtoupper(trim((string) ($row['Name'] ?? '')));
        return in_array($code, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
            || in_array($name, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true);
    }
}
