<?php
declare(strict_types=1);
namespace Binprof\Controllers;

use Binprof\Policies\Access;
use Binprof\Problem;
use Binprof\Repositories\Directory;
use Binprof\Services\{Admin,Auth,Changes,Documents,Inbox,Jobs,Records};
use function Binprof\{audit,config,configPath,crypto,csrf,db,fail,id,url};

final class Web
{
    private const GET = [
        'dashboard','personnel','person','map','requests','request','new','cases','case','document',
        'notifications','master','accounts','case-grants','operations','login','education','awards','retirement','password',
    ];
    private const POST = [
        'login','logout','new','request','upload','master','accounts','case-grants','operations','notifications','export','case-export','password',
    ];

    public static function run(): void
    {
        ini_set('display_errors','0');
        ini_set('zend.exception_ignore_args','1');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: private, no-store');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
        $user = null;
        try {
            $c = config();
            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            if ($c['require_https'] && !$secure) {
                fail(403,'Aplikasi memerlukan HTTPS.');
            }
            if ($secure) {
                header('Strict-Transport-Security: max-age=31536000');
            }
            $imageOrigin = '';
            if ($c['tiles']) {
                if (!preg_match('~^(https://[a-z0-9.-]+)(?:/[^\s]*)$~iD',$c['tiles'],$match)
                    || !$c['tile_attribution']) {
                    throw new \RuntimeException('Invalid tile provider configuration');
                }
                $imageOrigin = ' ' . $match[1];
            }
            header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:$imageOrigin; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
            $storage = realpath($c['storage']);
            $webroot = realpath($_SERVER['DOCUMENT_ROOT']);
            $keyring = realpath($c['keys']);
            $privateConfig = realpath(configPath());
            if (!$storage || !$webroot || !$keyring || !$privateConfig || self::inside($storage,$webroot)
                || self::inside($keyring,$webroot) || self::inside($privateConfig,$webroot)
                || self::inside(dirname(__DIR__,2) . '/app',$webroot)) {
                throw new \RuntimeException('Private data location check failed');
            }
            session_name('binprof_session');
            session_save_path($storage . '/sessions');
            session_set_cookie_params(['path'=>($c['base_path'] ?: '/') . ($c['base_path'] ? '/' : ''),
                'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
            ini_set('session.use_strict_mode','1');
            ini_set('session.use_only_cookies','1');
            session_start();
            $route = self::route();
            $post = $_SERVER['REQUEST_METHOD'] === 'POST';
            if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'],true)
                || !in_array($route,$post ? self::POST : self::GET,true)) {
                fail(404,'Halaman tidak ditemukan.');
            }
            if ($post && (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(),$_POST['csrf']))) {
                fail(403,'Permintaan kedaluwarsa. Muat ulang formulir.');
            }
            $user = Auth::current();
            if ($route === 'login') {
                if ($user) {
                    self::redirect('dashboard');
                }
                if ($post) {
                    Auth::login($_POST['username'] ?? null,$_POST['password'] ?? null,$_SERVER['REMOTE_ADDR'] ?? '');
                    self::redirect('dashboard');
                }
                self::view('login','Masuk');
                return;
            }
            if (!$user) {
                self::redirect('login');
            }
            if ($post) {
                self::post($route,$user,$_POST);
            }
            self::get($route,$user);
        } catch (\Throwable $error) {
            $status = $error instanceof Problem ? $error->getCode() : 500;
            $message = $status !== 500 ? $error->getMessage() : 'Layanan sedang bermasalah. Hubungi administrator.';
            if ($error instanceof \PDOException && in_array((int) ($error->errorInfo[1] ?? 0),[1062,1205,1213],true)) {
                $status = 409;
                $message = 'Data bertentangan atau sedang diproses. Muat ulang dan periksa data.';
            } elseif ($status === 500) {
                error_log('BINPROF web failure ' . get_class($error) . ' ' . basename($error->getFile()) . ':' . $error->getLine());
            }
            http_response_code($status);
            if (!isset($c)) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $message;
                return;
            }
            if ($status < 500 && ($route ?? '') === 'login') {
                self::view('login','Masuk',null,['message'=>$message]);
            } elseif ($status < 500 && isset($route) && $route === 'new' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $user && $status === 422) {
                try {
                    self::newForm($user,$_POST,['message'=>$message]);
                } catch (Problem) {
                    self::view('error','Permintaan gagal',$user,['message'=>$message]);
                }
            } else {
                self::view('error','Permintaan gagal',$user,['message'=>$message]);
            }
        }
    }

    private static function inside(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path,$root . '/');
    }

    private static function route(): string
    {
        if (isset($_GET['r'])) {
            return is_string($_GET['r']) ? $_GET['r'] : '';
        }
        $path = (string) parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
        $prefix = rtrim(config()['base_path'],'/');
        $route = trim(substr($path,strlen($prefix)),'/');
        return in_array($route,['','index.php'],true) ? 'dashboard' : $route;
    }

    private static function redirect(string $route, array $params = []): never
    {
        header('Location: ' . url($route,$params),true,303);
        exit;
    }

    private static function post(string $route, Access $user, array $input): never
    {
        $item = id($input['id'] ?? '');
        $action = is_string($input['action'] ?? null) ? $input['action'] : '';
        switch ($route) {
            case 'password':
                Auth::changePassword($user,$input['old'] ?? null,$input['new'] ?? null);
                $_SESSION['flash'] = 'Kata sandi diperbarui. Silakan masuk kembali.';
                self::redirect('login');
            case 'logout':
                Auth::logout();
                self::redirect('login');
            case 'new':
                $module = is_string($input['module'] ?? null) ? $input['module'] : '';
                $id = Changes::create($user,$module,id($input['person'] ?? ''),id($input['unit'] ?? ''),$item,$input);
                self::redirect('request',['id'=>$id]);
            case 'request':
                Changes::mutate($user,$item,id($input['revision'] ?? ''),$action,$input);
                self::redirect('request',['id'=>$item]);
            case 'upload':
                Documents::upload($user,$item,id($input['revision'] ?? ''),$_FILES['document'] ?? []);
                self::redirect('request',['id'=>$item]);
            case 'master':
                $kind = is_string($input['kind'] ?? null) ? $input['kind'] : '';
                Admin::master($user,$kind,$item,$input);
                self::redirect('master',['kind'=>$kind]);
            case 'accounts':
                Admin::account($user,$item,$input);
                self::redirect('accounts');
            case 'case-grants':
                if (!in_array($action,['give','revoke'],true)) {
                    fail(422,'Pilih tindakan penugasan.');
                }
                Admin::grant($user,id($input['target'] ?? ''),$item,Records::text($input['capability'] ?? null,30) ?? '',
                    $action === 'give',Records::text($input['expires'] ?? null,10));
                $_SESSION['flash'] = 'Penugasan berhasil diperbarui.';
                self::redirect('case-grants');
            case 'operations':
                $user->require('jobs');
                if ($action === 'rules') {
                    Jobs::updateRules($user->id,$input['months'] ?? null);
                } elseif ($action === 'run') {
                    $result = Jobs::run();
                    $_SESSION['flash'] = 'Hasil pemeriksaan: ' . $result['state'] . ' · baris diproses ' . $result['processed'];
                } else {
                    fail(422,'Pilih tindakan operasional.');
                }
                self::redirect('operations');
            case 'notifications':
                Inbox::markRead($user,$item);
                self::redirect('notifications');
            case 'export':
                $user->require('export');
                $filter = self::filters($input);
                $directory = new Directory($user,$filter);
                $rows = $directory->rows(5001,0,$filter['sort'] ?? null,true);
                if (count($rows) > 5000) {
                    fail(422,'Persempit filter. Ekspor dibatasi 5.000 baris.');
                }
                audit($user->id,'personnel.export');
                self::csv('personel.csv',['ID','Nomor kedinasan','Nama','Satuan','Kategori','Status','Perkiraan'],
                    array_map(static fn($row)=>[$row['id'],crypto()->open($row['identifier_cipher'],'identifier:' . $row['id']),
                        $row['name'],$row['unit'],$row['category'],Records::STATUS[$row['service_status']],$row['estimate_date']],$rows));
            case 'case-export':
                $user->caseRequire($item,'read','export');
                $row = db()->one('SELECT content_cipher FROM disciplinary_cases WHERE id=? AND version>0', [$item])
                    ?? fail(404,'Kasus terverifikasi tidak ditemukan.');
                $data = crypto()->open($row['content_cipher'],'case:' . $item);
                audit($user->id,'case.export',$item);
                self::csv('kasus-' . $item . '.csv',['Kolom','Nilai'],self::displayPairs('case',$data));
        }
        fail(404,'Halaman tidak ditemukan.');
    }

    private static function csv(string $file, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        $stream = fopen('php://output','wb');
        fputcsv($stream,$headers,',','"','');
        foreach ($rows as $row) {
            fputcsv($stream,array_map(static function ($value): string {
                $value = (string) $value;
                return preg_match('/^(?:[\s\p{Cf}]*[=+@-]|[\t\r\n])/u',$value) ? "'" . $value : $value;
            },$row),',','"','');
        }
        fclose($stream);
        exit;
    }

    public static function filters(array $input): array
    {
        return array_intersect_key($input,array_fill_keys([...array_keys(Directory::FILTERS),'q','status','from','to','sort','group'],true));
    }

    private static function get(string $route, Access $user): void
    {
        $id = id($_GET['id'] ?? '');
        $before = id($_GET['before'] ?? '') ?: PHP_INT_MAX;
        $filter = self::filters($_GET);
        $page = min(id($_GET['page'] ?? '') ?: 1,10000);
        switch ($route) {
            case 'password':
                self::view('password','Ubah kata sandi',$user);
                break;
            case 'dashboard':
                if (!$user->allows('read')) {
                    self::redirect($user->allows('admin') ? 'operations' : 'cases');
                }
                $directory = new Directory($user,$filter,true);
                $groups = $directory->aggregate();
                self::view('dashboard','Dashboard pimpinan',$user,compact('filter','groups') + [
                    'inbox'=>Inbox::rows($user,PHP_INT_MAX,4), 'pending'=>Changes::queue($user,PHP_INT_MAX,true),
                ]);
                break;
            case 'map':
                $groups = (new Directory($user,$filter,true))->aggregate();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['units'=>$groups,'as_of'=>\Binprof\today()],JSON_THROW_ON_ERROR);
                break;
            case 'personnel':
            case 'retirement':
            case 'education':
            case 'awards':
                if ($route === 'retirement') {
                    $filter['from'] ??= \Binprof\today();
                    $filter['to'] ??= (new \DateTimeImmutable(\Binprof\today()))->modify('+12 months')->format('Y-m-d');
                }
                if (in_array($route,['education','awards'],true)) {
                    $filter['group'] = $route;
                }
                $directory = new Directory($user,$filter);
                $list = $directory->rows(26,($page-1)*25,$filter['sort'] ?? null);
                $title = ['personnel'=>'Personel','retirement'=>'Perkiraan pensiun','education'=>'Pendidikan','awards'=>'Penghargaan'][$route];
                self::view('personnel',$title,$user,compact('list','page','filter','route'));
                break;
            case 'person':
                self::person($user,$id);
                break;
            case 'new':
                self::newForm($user,$_GET);
                break;
            case 'requests':
                $pendingOnly = ($_GET['pending'] ?? '') === '1';
                $rows = Changes::queue($user,$before,$pendingOnly);
                self::view('requests','Pengajuan dan verifikasi',$user,compact('rows','pendingOnly'));
                break;
            case 'request':
                self::request($user,$id);
                break;
            case 'document':
                Documents::download($user,$id);
            case 'cases':
                $cases = db()->all("SELECT c.id,c.version,u.name AS unit,c.content_cipher FROM disciplinary_cases c
                    JOIN units u ON u.id=c.unit_id JOIN case_access_grants g ON g.case_id=c.id AND g.user_id=? AND g.capability='read'
                    WHERE c.id<? AND (g.expires_on IS NULL OR g.expires_on>=?) ORDER BY c.id DESC LIMIT 51",[$user->id,$before,\Binprof\today()]);
                audit($user->id,'case.list');
                self::view('cases','Kasus ditugaskan',$user,compact('cases'));
                break;
            case 'case':
                self::caseDetail($user,$id);
                break;
            case 'notifications':
                $rows = Inbox::rows($user,$before);
                self::view('notifications','Notifikasi',$user,compact('rows'));
                break;
            case 'master':
                $user->require('admin');
                $kind = is_string($_GET['kind'] ?? null) ? $_GET['kind'] : 'unit';
                [$table] = Admin::MASTER[$kind] ?? fail(404,'Master tidak ditemukan.');
                $where = $table === 'reference_values' ? 'kind=? AND ' : '';
                $args = $table === 'reference_values' ? [$kind] : [];
                $rows = db()->all("SELECT * FROM $table WHERE {$where}id<? ORDER BY id DESC LIMIT 51",[...$args,$before]);
                $edit = $id ? db()->one("SELECT * FROM $table WHERE {$where}id=?",[...$args,$id]) : [];
                self::view('master','Master data',$user,compact('kind','rows','edit'));
                break;
            case 'accounts':
                $user->require('admin');
                $rows = db()->all('SELECT id,username,active FROM users WHERE id<? ORDER BY id DESC LIMIT 51',[$before]);
                $edit = $id ? db()->one('SELECT id,username,active FROM users WHERE id=?',[$id]) : [];
                $roles = db()->all('SELECT * FROM roles ORDER BY label');
                $selectedRoles = $id ? array_column(db()->all('SELECT role_code FROM user_roles WHERE user_id=?',[$id]),'role_code') : [];
                $selectedUnits = $id ? array_column(db()->all('SELECT unit_id FROM user_unit_scopes WHERE user_id=?',[$id]),'unit_id') : [];
                $selectedModules = $id ? array_column(db()->all('SELECT module FROM user_verification_modules WHERE user_id=?',[$id]),'module') : [];
                self::view('accounts','Akun dan cakupan',$user,compact('rows','roles','edit','selectedRoles','selectedUnits','selectedModules'));
                break;
            case 'case-grants':
                if (!$user->account['case_manager']) {
                    fail(403,'Kewenangan pengelola penugasan kasus diperlukan.');
                }
                self::view('case-grants','Penugasan kasus',$user);
                break;
            case 'operations':
                $user->require('jobs');
                $rules = Jobs::rules();
                $runs = db()->all('SELECT * FROM job_runs ORDER BY id DESC LIMIT 20');
                $scheduled = db()->all('SELECT request_id,effective_on,state FROM scheduled_service ORDER BY request_id DESC LIMIT 20');
                self::view('operations','Operasional sistem',$user,compact('rules','runs','scheduled'));
                break;
        }
    }

    private static function person(Access $user, int $id): void
    {
        $person = $user->person($id);
        [, $profile] = Records::current(['module'=>'profile','personnel_id'=>$id]);
        $assignments = db()->all('SELECT s.*,u.name AS unit FROM service_assignments s JOIN units u ON u.id=s.unit_id
            WHERE s.personnel_id=? ORDER BY s.start_on DESC',[$id]);
        $education = db()->all('SELECT h.*,r.name AS label FROM education_histories h JOIN reference_values r ON r.id=h.type_id
            WHERE h.personnel_id=? ORDER BY h.id DESC',[$id]);
        $awards = db()->all('SELECT a.*,r.name AS label, (SELECT COUNT(*) FROM personnel_awards x WHERE x.personnel_id=a.personnel_id
            AND x.type_id=a.type_id AND x.awarded_on=a.awarded_on) AS matches FROM personnel_awards a
            JOIN reference_values r ON r.id=a.type_id WHERE a.personnel_id=? ORDER BY a.awarded_on DESC',[$id]);
        $forecast = db()->one('SELECT f.*,u.username FROM retirement_forecasts f JOIN users u ON u.id=f.verified_by WHERE f.personnel_id=?',[$id]);
        $documents = $user->allows('download') ? db()->all("SELECT d.id,r.module FROM documents d JOIN change_requests r
            ON r.id=d.request_id AND r.revision=d.revision WHERE r.personnel_id=? AND r.status='approved' ORDER BY d.id DESC",[$id]) : [];
        $history = db()->all("SELECT r.id,r.module,a.created_at,u.username FROM change_requests r
            JOIN approval_actions a ON a.request_id=r.id AND a.revision=r.revision AND a.decision='approved'
            JOIN users u ON u.id=a.actor_id WHERE r.personnel_id=? ORDER BY a.created_at DESC LIMIT 50",[$id]);
        $relatedCases = db()->all("SELECT c.id FROM case_personnel cp JOIN disciplinary_cases c ON c.id=cp.case_id
            JOIN case_access_grants g ON g.case_id=c.id AND g.user_id=? AND g.capability='read'
            WHERE cp.personnel_id=? AND c.version>0 AND (g.expires_on IS NULL OR g.expires_on>=?) ORDER BY c.id DESC LIMIT 50",
            [$user->id,$id,\Binprof\today()]);
        self::view('person',$person['name'],$user,compact('person','profile','assignments','education','awards','forecast','documents','history','relatedCases'));
    }

    private static function caseDetail(Access $user, int $id): void
    {
        $user->caseRequire($id,'read');
        $case = db()->one('SELECT * FROM disciplinary_cases WHERE id=?',[$id]) ?? fail(404,'Catatan tidak ditemukan.');
        $data = $case['content_cipher'] ? crypto()->open($case['content_cipher'],'case:' . $id) : [];
        $parties = db()->all('SELECT p.id,p.name,cp.role_cipher FROM case_personnel cp JOIN personnel p ON p.id=cp.personnel_id WHERE cp.case_id=?',[$id]);
        $events = db()->all('SELECT * FROM case_events WHERE case_id=? ORDER BY version DESC LIMIT 50',[$id]);
        $documents = $user->caseCan($id,'download') ? db()->all("SELECT d.id FROM documents d JOIN change_requests r
            ON r.id=d.request_id AND r.revision=d.revision WHERE r.case_id=? AND r.status='approved' ORDER BY d.id DESC",[$id]) : [];
        audit($user->id,'case.read',$id);
        self::view('case',$data['title'] ?? 'Kasus belum terverifikasi',$user,compact('id','data','parties','events','documents'));
    }

    private static function newForm(Access $user, array $input, array $extra = []): void
    {
        $module = is_string($input['module'] ?? null) ? $input['module'] : 'profile';
        $fields = Records::fields($module);
        $personId = id($input['person'] ?? '');
        $item = id($input['id'] ?? '');
        $values = [];
        if ($module === 'case') {
            if ($item) {
                $user->caseRequire($item,'read','write');
                [, $values] = Records::current(['module'=>'case','case_id'=>$item]);
                audit($user->id,'case.read',$item);
            } elseif (!$user->account['case_creator']) {
                fail(403,'Kewenangan membuat kasus diperlukan.');
            }
        } else {
            $user->require('write');
            if ($personId) {
                $user->person($personId);
                [, $values] = Records::current(['module'=>$module,'personnel_id'=>$personId,
                    'education_id'=>in_array($module,['dikum','dikmil'],true) ? $item : null,'award_id'=>$module === 'award' ? $item : null]);
                if ($module === 'service') {
                    $values['effective_on'] = \Binprof\today();
                }
            } elseif ($module !== 'profile') {
                fail(404,'Pilih personel terlebih dahulu.');
            }
        }
        if ($extra) {
            $values = array_replace($values,array_intersect_key($input,$fields));
        }
        self::view('new','Draf ' . Records::LABELS[$module],$user,compact('module','fields','personId','item','values') + $extra);
    }

    private static function request(Access $user, int $id): void
    {
        $row = Changes::row($id);
        Changes::canReview($user,$row,'read');
        $number = id($_GET['revision'] ?? '') ?: (int) $row['revision'];
        if ($number > (int) $row['revision']) {
            fail(404,'Revisi tidak ditemukan.');
        }
        $current = $number === (int) $row['revision'];
        $selected = array_replace($row,['revision'=>$number]);
        $revision = Changes::revision($selected);
        if (!$revision || (!$revision['submitted_at'] && (int) $row['owner_id'] !== $user->id)) {
            fail(404,'Revisi tidak ditemukan.');
        }
        $context = Changes::context($selected);
        $values = crypto()->open($revision['payload_cipher'],$context);
        $before = crypto()->open($revision['before_cipher'],$context . ':before');
        $decision = db()->one('SELECT a.*,u.username FROM approval_actions a JOIN users u ON u.id=a.actor_id WHERE request_id=? AND revision=?',[$id,$number]);
        $documents = db()->all('SELECT id,mime,size_bytes FROM documents WHERE request_id=? AND revision=?',[$id,$number]);
        if ($row['module'] === 'case') {
            audit($user->id,'case.revision.read',(int) $row['case_id']);
        }
        self::view('request',Records::LABELS[$row['module']] . ' · Pengajuan #' . $id,$user,
            compact('row','selected','revision','context','values','before','decision','documents','current'));
    }

    public static function displayPairs(string $module, array $values): array
    {
        $pairs = [];
        foreach (Records::fields($module) as $key=>[$label,$type]) {
            $value = $values[$key] ?? null;
            if (is_array($type)) {
                $value = $type[$value] ?? $value;
            } elseif ($value && is_numeric($value) && !in_array($type,['text','textarea','year','date'],true)) {
                $value = array_column(Records::options($type,false),'name','id')[$value] ?? $value;
            }
            $pairs[$key] = [$label,$value];
        }
        return array_values($pairs);
    }

    private static function view(string $template, string $title, ?Access $user = null, array $data = []): void
    {
        ob_start();
        try {
            \Binprof\Views\Screen::render($template,$title,$user,$data);
            $body = ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
        echo $body;
    }
}
