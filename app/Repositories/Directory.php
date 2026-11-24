<?php
declare(strict_types=1);
namespace Binprof\Repositories;

use Binprof\Policies\Access;
use Binprof\Services\Records;
use function Binprof\{crypto,db,fail,id,today};

final class Directory
{
    public const FILTERS = [
        'unit'=>['Satuan','unit','unit_id'], 'category'=>['Kategori','category','category_id'],
        'profession'=>['Profesi','profession','profession_id'], 'rank'=>['Pangkat','rank','rank_id'],
        'grade'=>['Golongan','grade','grade_id'], 'dikum'=>['Dikum','dikum',null], 'dikmil'=>['Dikmil','dikmil',null],
        'award'=>['Penghargaan','award',null],
    ];

    private string $joins;
    private string $where;
    private array $args;
    private bool $hasForecastFilter = false;

    public function __construct(Access $user, array $filter, bool $activeOnly = false)
    {
        $user->require('read');
        $where = [];
        $this->args = [$user->id];
        $status = $activeOnly ? 'active' : ($filter['status'] ?? 'active');
        if ($status === 'all') {
            $where[] = "p.service_status<>'archived'";
        } elseif (is_string($status) && isset(Records::STATUS[$status])) {
            $where[] = 'p.service_status=?';
            $this->args[] = $status;
        } else {
            fail(422,'Filter status tidak valid.');
        }
        foreach (self::FILTERS as $key => [,$kind,$column]) {
            if (isset($filter[$key]) && $filter[$key] !== '') {
                $value = id($filter[$key]) ?: fail(422, 'Filter tidak valid.');
                if ($column) {
                    $where[] = 'p.' . $column . '=?';
                    $this->args[] = $value;
                } elseif ($kind === 'award') {
                    $where[] = 'EXISTS(SELECT 1 FROM personnel_awards a WHERE a.personnel_id=p.id AND a.type_id=?)';
                    $this->args[] = $value;
                } else {
                    $where[] = 'EXISTS(SELECT 1 FROM education_histories h WHERE h.personnel_id=p.id AND h.kind=? AND h.type_id=?)';
                    array_push($this->args,$kind,$value);
                }
            }
        }
        if ($q = Records::text($filter['q'] ?? null,160)) {
            $this->args[] = '%' . strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
            $where[] = "p.name LIKE ? ESCAPE '!' OR EXISTS (SELECT 1 FROM personnel_identifiers i WHERE i.personnel_id=p.id AND (" .
                $this->identifierFilter($q) . '))';
        }
        $group = $filter['group'] ?? '';
        if ($group === 'education') {
            $where[] = 'EXISTS (SELECT 1 FROM education_histories h WHERE h.personnel_id=p.id)';
        } elseif ($group === 'awards') {
            $where[] = 'EXISTS (SELECT 1 FROM personnel_awards a WHERE a.personnel_id=p.id)';
        }
        foreach (['from'=>'>=','to'=>'<='] as $key=>$operator) {
            if (!empty($filter[$key])) {
                if (!is_string($filter[$key]) || !Records::date($filter[$key])) {
                    fail(422, 'Rentang perkiraan tidak valid.');
                }
                $where[] = 'f.estimate_date ' . $operator . ' ?';
                $this->args[] = $filter[$key];
                $this->hasForecastFilter = true;
            }
        }
        if (!empty($filter['from']) && !empty($filter['to']) && $filter['from'] > $filter['to']) {
            fail(422, 'Tanggal akhir harus setelah tanggal awal.');
        }
        $this->joins = " FROM personnel p JOIN user_unit_scopes s ON p.unit_id=s.unit_id AND s.user_id=?
            JOIN units u ON u.id=p.unit_id JOIN personnel_categories c ON c.id=p.category_id
            LEFT JOIN retirement_forecasts f ON f.personnel_id=p.id AND f.validation_status='valid'";
        $this->where = ' WHERE (' . implode(') AND (',$where) . ')';
    }

    private function identifierFilter(string $q): string
    {
        $or = ['1=0'];
        foreach (db()->all('SELECT id FROM identifier_types') as $type) {
            $type = (int) $type['id'];
            $or[] = '(i.type_id=? AND i.digest=?)';
            array_push($this->args,$type,crypto()->identifier($type,strtoupper($q)));
        }
        return implode(' OR ',$or);
    }

    public function rows(int $limit, int $offset, mixed $sort, bool $export = false): array
    {
        $order = self::order($sort);
        return db()->all('SELECT p.id,p.name,u.name AS unit,c.name AS category,p.service_status, f.estimate_date' .
            ($export ? ',(SELECT cipher FROM personnel_identifiers WHERE personnel_id=p.id) AS identifier_cipher' : '') .
            $this->joins . $this->where . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?', [...$this->args,$limit,$offset]);
    }

    public function reportRows(HistoryReport $report, int $limit, int $offset, mixed $sort): array
    {
        [$join,$select,$condition,$values] = $report->query();
        $order = self::order($sort);
        return db()->all(
            'SELECT p.id AS person_id,p.name,u.name AS unit,t.name AS type_name,' . $select
            . $this->joins . $join . $this->where . $condition . ' ORDER BY ' . $order . ',h.id DESC LIMIT ? OFFSET ?',
            [...$this->args,...$values,$limit,$offset]
        );
    }

    private static function order(mixed $sort): string
    {
        return match ($sort) {
            'name_desc'=>'p.name DESC,p.id DESC', 'recent'=>'p.updated_at DESC,p.id DESC', default=>'p.name,p.id',
        };
    }

    public function aggregate(): array
    {
        // An explicit forecast filter already constrains the valid forecast rows.
        $retiring = $this->hasForecastFilter
            ? 'COUNT(f.estimate_date)'
            : 'SUM(CASE WHEN f.estimate_date BETWEEN ? AND ? THEN 1 ELSE 0 END)';
        $window = $this->hasForecastFilter ? []
            : [today(),(new \DateTimeImmutable(today()))->modify('+12 months')->format('Y-m-d')];
        $groups = db()->all("SELECT u.id,u.name,u.latitude,u.longitude,COUNT(*) AS total,
            SUM(c.code='MILITER') AS military,SUM(c.code='PNS') AS pns,
            $retiring AS retiring"
            . $this->joins . $this->where . ' GROUP BY u.id,u.name,u.latitude,u.longitude ORDER BY u.name',
            [...$window,...$this->args]);
        return array_map(static fn(array $group)=>[
            'id'=>(int) $group['id'], 'name'=>$group['name'],
            'latitude'=>$group['latitude'] === null ? null : (float) $group['latitude'],
            'longitude'=>$group['longitude'] === null ? null : (float) $group['longitude'],
            'total'=>(int) $group['total'], 'military'=>(int) $group['military'], 'pns'=>(int) $group['pns'],
            'retiring'=>(int) $group['retiring'],
        ],$groups);
    }

    public static function unitOptions(Access $user): array
    {
        return db()->all('SELECT u.id,u.name FROM units u JOIN user_unit_scopes s ON s.unit_id=u.id WHERE s.user_id=? ORDER BY u.name', [$user->id]);
    }
}
