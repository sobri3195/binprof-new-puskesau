<?php
declare(strict_types=1);
namespace Binprof\Policies;

use function Binprof\{db,fail,today};

final class Access
{
    private function __construct(public readonly int $id, public readonly array $account, private array $roles) {}

    public static function load(int $id, bool $lock = false): ?self
    {
        $account = db()->one('SELECT id,active,session_version,case_manager,case_creator,case_cross_unit FROM users
            WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$account || !$account['active']) {
            return null;
        }
        return new self($id, $account, array_column(db()->all('SELECT role_code FROM user_roles WHERE user_id=?', [$id]), 'role_code'));
    }

    public function allows(string $action): bool
    {
        $roles = match ($action) {
            'admin','jobs' => ['admin'],
            'read' => ['operator','verifier','leader'],
            'write','submit' => ['operator'],
            'verify' => ['verifier'],
            'export' => ['exporter'],
            'download' => ['downloader'],
            default => [],
        };
        return (bool) array_intersect($roles, $this->roles);
    }

    public function require(string $action): void
    {
        if (!$this->allows($action)) {
            fail(403, 'Tindakan ini tidak tersedia untuk akun Anda.');
        }
    }

    public function unit(int $id): bool
    {
        return (bool) db()->one('SELECT 1 FROM user_unit_scopes WHERE user_id=? AND unit_id=?', [$this->id, $id]);
    }

    public function person(int $id, bool $lock = false): array
    {
        $this->require('read');
        $row = db()->one('SELECT p.* FROM personnel p JOIN user_unit_scopes s ON s.unit_id=p.unit_id AND s.user_id=?
            WHERE p.id=?' . ($lock ? ' FOR UPDATE' : ''), [$this->id, $id]);
        return $row ?? fail(404, 'Personel tidak ditemukan.');
    }

    public function caseCan(int $id, string $capability): bool
    {
        return (bool) db()->one('SELECT 1 FROM case_access_grants WHERE case_id=? AND user_id=? AND capability=?
            AND (expires_on IS NULL OR expires_on >= ?)', [$id, $this->id, $capability, today()]);
    }

    public function caseRequire(int $id, string ...$capabilities): void
    {
        foreach ($capabilities as $capability) {
            if (!$this->caseCan($id, $capability)) {
                fail(404, 'Catatan tidak ditemukan.');
            }
        }
    }

    public function verifies(string $module): bool
    {
        return $this->allows('verify') && (bool) db()->one(
            'SELECT 1 FROM user_verification_modules WHERE user_id=? AND module=?', [$this->id,$module]);
    }

    public function revision(array $request, array $revision): bool
    {
        return $this->request($request,'write')
            || ($revision['submitted_at'] !== null && $this->request($request,'verify'));
    }

    public function request(array $row, string $action): bool
    {
        $owner = (int) $row['owner_id'] === $this->id;
        $status = $row['status'];
        if ($row['module'] === 'case') {
            $case = (int) $row['case_id'];
            if (!$this->caseCan($case, 'read')) {
                return false;
            }
            return match ($action) {
                'read' => ($owner && $this->caseCan($case, 'write'))
                    || (in_array($status, ['submitted','returned','approved'], true) && $this->caseCan($case, 'verify')),
                'write','submit' => $owner && $this->caseCan($case, $action),
                'verify' => !$owner && $this->caseCan($case, 'verify'),
                default => false,
            };
        }
        if (!$this->unit((int) $row['unit_id'])) {
            return false;
        }
        if ($row['personnel_id'] && !$this->unit((int) $row['current_unit'])) {
            return false;
        }
        return match ($action) {
            'read' => ($owner && $this->allows('write'))
                || (in_array($status, ['submitted','returned','approved'], true) && $this->verifies($row['module'])),
            'write','submit' => $owner && $this->allows($action),
            'verify' => !$owner && $this->verifies($row['module']),
            default => false,
        };
    }
}
