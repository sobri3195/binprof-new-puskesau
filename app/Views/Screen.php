<?php
declare(strict_types=1);
namespace Binprof\Views;

use Binprof\Controllers\Web;
use Binprof\Policies\Access;
use Binprof\Repositories\Directory;
use Binprof\Services\{Admin,Changes,Records};
use function Binprof\{asset,csrfField,e,url};

final class Screen
{
    public static function render(string $template, string $title, ?Access $user, array $data): void
    {
        extract($data,EXTR_SKIP);
        $menu = $user ? self::menu($user) : [];
        $currentRoute = $_GET['r'] ?? trim((string) parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
        require __DIR__ . '/layout.php';
    }

    private static function menu(Access $actor): array
    {
        $menu = [];
        if ($actor->allows('read')) {
            $menu += ['dashboard'=>'Dashboard','personnel'=>'Personel','education'=>'Pendidikan','awards'=>'Penghargaan',
                'retirement'=>'Perkiraan pensiun'];
        }
        $menu += ['requests'=>'Pengajuan','notifications'=>'Notifikasi','cases'=>'Kasus ditugaskan'];
        if ($actor->account['case_manager']) {
            $menu['case-grants'] = 'Penugasan kasus';
        }
        if ($actor->allows('admin')) {
            $menu += ['master'=>'Master data','accounts'=>'Akun dan cakupan','operations'=>'Operasional'];
        }
        $menu['password'] = 'Ubah kata sandi';
        return $menu;
    }

    public static function form(string $route, array $hidden = [], bool $file = false): void
    {
        echo '<form method="post" action="' . e(url($route)) . '"' . ($file ? ' enctype="multipart/form-data"' : '') . '>' . csrfField();
        foreach ($hidden as $name=>$value) {
            echo '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
        }
    }

    public static function getForm(string $route): void
    {
        $target = url($route);
        echo '<form method="get" action="' . e(explode('?',$target,2)[0]) . '">';
        if (str_contains($target,'?r=')) {
            echo '<input type="hidden" name="r" value="' . e($route) . '">';
        }
    }

    public static function field(string $key, string $label, string|array $type, mixed $value = null, bool $required = false): void
    {
        $value = is_scalar($value) ? (string) $value : '';
        $marker = $required ? ' required' : '';
        echo '<label><span>' . e($label) . '</span>';
        if ($type === 'textarea') {
            echo '<textarea name="' . e($key) . '" rows="3"' . $marker . '>' . e($value) . '</textarea>';
        } elseif (in_array($type,['text','date','password','number','year'],true)) {
            echo '<input type="' . e($type === 'year' ? 'number' : $type) . '" name="' . e($key) .
                '" value="' . e($value) . '"' . $marker . ($type === 'password' ? ' autocomplete="new-password"' : '') . '>';
        } else {
            $options = is_array($type) ? $type : array_column(Records::options($type),'name','id');
            echo '<select name="' . e($key) . '"' . $marker . '><option value="">— Pilih —</option>';
            foreach ($options as $id=>$name) {
                echo '<option value="' . e($id) . '"' . ((string) $id === $value ? ' selected' : '') . '>' . e($name) . '</option>';
            }
            echo '</select>';
        }
        echo '</label>';
    }

    public static function fields(array $fields, array $values): void
    {
        echo '<div class="fields">';
        foreach ($fields as $key=>[$label,$type]) {
            self::field($key,$label,$type,$values[$key] ?? null);
        }
        echo '</div>';
    }

    public static function link(string $label, string $route, array $params = [], string $class = ''): string
    {
        return '<a class="' . e($class) . '" href="' . e(url($route,$params)) . '">' . e($label) . '</a>';
    }

    public static function filter(string $route, Access $user, array $filter): void
    {
        self::getForm($route);
        echo '<div class="fields filters">';
        self::field('q','Cari nama atau nomor kedinasan','text',$filter['q'] ?? '');
        foreach (Directory::FILTERS as $key=>[$label,$kind]) {
            $options = $kind === 'unit' ? array_column(Directory::unitOptions($user),'name','id')
                : array_column(Records::options($kind,false),'name','id');
            self::field($key,$label,$options,$filter[$key] ?? '');
        }
        self::field('from','Perkiraan mulai','date',$filter['from'] ?? '');
        self::field('to','Perkiraan sampai','date',$filter['to'] ?? '');
        if ($route !== 'dashboard') {
            self::field('status','Status',['all'=>'Semua tanpa arsip'] + Records::STATUS,$filter['status'] ?? 'active');
            self::field('sort','Urutan',['name'=>'Nama A–Z','name_desc'=>'Nama Z–A','recent'=>'Pembaruan terbaru'],$filter['sort'] ?? 'name');
        }
        echo '</div><div class="actions"><button>Terapkan filter</button>' . self::link('Reset',$route,[],'button secondary') . '</div></form>';
    }

    public static function more(array $rows, string $route, array $args = []): void
    {
        if (count($rows) === 51) {
            echo self::link('Halaman berikutnya',$route,$args + ['before'=>$rows[49]['id']],'button secondary');
        }
    }

    public static function pairs(string $module, array $values): void
    {
        echo '<dl class="pairs">';
        foreach (Web::displayPairs($module,$values) as [$label,$value]) {
            echo '<dt>' . e($label) . '</dt><dd>' . nl2br(e($value ?? '—')) . '</dd>';
        }
        echo '</dl>';
    }

    public static function badge(string $value): string
    {
        return '<span class="badge">' . e(Changes::STATUS[$value] ?? Records::STATUS[$value] ?? $value) . '</span>';
    }

    public static function localTime(string $utc): string
    {
        return (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone(\Binprof\config()['timezone']))->format('d/m/Y H:i T');
    }
}
