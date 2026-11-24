<?php
declare(strict_types=1);
namespace Binprof\Repositories;

use Binprof\Services\Records;
use function Binprof\{fail,id};

final class HistoryReport
{
    private const EDUCATION_KIND = ['dikum'=>'Dikum','dikmil'=>'Dikmil'];
    private const EDUCATION_STATUS = ['ongoing'=>'Berlangsung','completed'=>'Selesai','not_applicable'=>'Tidak berlaku'];
    public const PARAMETERS = [
        'education'=>['history_kind','history_type','history_q','history_status','completion_year'],
        'awards'=>['history_type','history_q','award_from','award_to'],
    ];

    public readonly array $filters;

    public function __construct(public readonly string $route, array $input)
    {
        $parameters = self::PARAMETERS[$route] ?? fail(404,'Rekap tidak ditemukan.');
        $this->filters = array_intersect_key($input,array_fill_keys($parameters,true));
    }

    public function title(): string
    {
        return $this->route === 'education' ? 'Rekap pendidikan' : 'Rekap penghargaan';
    }

    public function fields(): array
    {
        if ($this->route === 'awards') {
            return [
                'history_type'=>['Jenis penghargaan',array_column(Records::options('award',false),'name','id')],
                'history_q'=>['Cari pemberi','text'],
                'award_from'=>['Penghargaan sejak','date'],
                'award_to'=>['Penghargaan sampai','date'],
            ];
        }

        $types = [];
        foreach (self::EDUCATION_KIND as $kind=>$label) {
            foreach (Records::options($kind,false) as $type) {
                $types[$type['id']] = $label . ' · ' . $type['name'];
            }
        }
        return [
            'history_kind'=>['Kelompok pendidikan',self::EDUCATION_KIND],
            'history_type'=>['Jenis / jenjang',$types],
            'history_q'=>['Cari lembaga atau program studi','text'],
            'history_status'=>['Status pendidikan',self::EDUCATION_STATUS],
            'completion_year'=>['Tahun selesai','year'],
        ];
    }

    public function query(): array
    {
        $education = $this->route === 'education';
        $table = $education ? 'education_histories' : 'personnel_awards';
        $join = " JOIN $table h ON h.personnel_id=p.id JOIN reference_values t ON t.id=h.type_id";
        $select = $education
            ? 'h.kind,h.institution,h.program,h.cohort,h.status,h.start_on,h.start_year,h.end_on,h.end_year'
            : 'h.issuer,h.level_name,h.awarded_on';
        $conditions = [];
        $args = [];

        if (($type = $this->value('history_type',10)) !== null) {
            $conditions[] = 'h.type_id=?';
            $args[] = id($type) ?: fail(422,'Jenis riwayat tidak valid.');
        }
        if (($keyword = $this->value('history_q',160)) !== null) {
            $like = '%' . strtr($keyword,['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
            if ($education) {
                $conditions[] = "(h.institution LIKE ? ESCAPE '!' OR h.program LIKE ? ESCAPE '!')";
                array_push($args,$like,$like);
            } else {
                $conditions[] = "h.issuer LIKE ? ESCAPE '!'";
                $args[] = $like;
            }
        }

        if ($education) {
            if (($kind = $this->value('history_kind',10)) !== null) {
                $conditions[] = 'h.kind=?';
                $args[] = isset(self::EDUCATION_KIND[$kind]) ? $kind : fail(422,'Kelompok pendidikan tidak valid.');
            }
            if (($status = $this->value('history_status',20)) !== null) {
                $conditions[] = 'h.status=?';
                $args[] = isset(self::EDUCATION_STATUS[$status]) ? $status : fail(422,'Status pendidikan tidak valid.');
            }
            if (($year = $this->value('completion_year',4)) !== null) {
                $conditions[] = 'COALESCE(h.end_year,YEAR(h.end_on))=?';
                $args[] = preg_match('/^(18|19|20|21)\d{2}$/D',$year) ? (int) $year : fail(422,'Tahun selesai tidak valid.');
            }
        } else {
            $from = $this->value('award_from',10);
            $to = $this->value('award_to',10);
            if (($from !== null && !Records::date($from)) || ($to !== null && !Records::date($to))
                || ($from !== null && $to !== null && $from > $to)) {
                fail(422,'Rentang tanggal penghargaan tidak valid.');
            }
            if ($from !== null) {
                $conditions[] = 'h.awarded_on>=?';
                $args[] = $from;
            }
            if ($to !== null) {
                $conditions[] = 'h.awarded_on<=?';
                $args[] = $to;
            }
        }

        return [$join,$select,$conditions ? ' AND ' . implode(' AND ',$conditions) : '',$args];
    }

    private function value(string $key, int $limit): ?string
    {
        return Records::text($this->filters[$key] ?? null,$limit);
    }

    public function columns(): array
    {
        $common = ['name'=>'Nama personel','unit'=>'Satuan','type_name'=>'Jenis / jenjang'];
        return $this->route === 'education'
            ? $common + ['kind'=>'Kelompok','institution'=>'Lembaga','program'=>'Program studi','cohort'=>'Angkatan',
                'start'=>'Mulai','end'=>'Selesai','status'=>'Status pendidikan']
            : $common + ['issuer'=>'Pemberi','level_name'=>'Tingkat','awarded_on'=>'Tanggal'];
    }

    public function row(array $record): array
    {
        if ($this->route === 'education') {
            $record['kind'] = self::EDUCATION_KIND[$record['kind']];
            $record['status'] = self::EDUCATION_STATUS[$record['status']];
            $record['start'] = $record['start_on'] ?? $record['start_year'];
            $record['end'] = $record['end_on'] ?? $record['end_year'];
        }
        return array_map(static fn(string $field)=>$record[$field],array_keys($this->columns()));
    }
}
