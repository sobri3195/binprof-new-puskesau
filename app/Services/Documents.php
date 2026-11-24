<?php
declare(strict_types=1);
namespace Binprof\Services;

use Binprof\Policies\Access;
use function Binprof\{audit,config,crypto,db,fail};

final class Documents
{
    public static function upload(Access $user, int $request, int $revision, array $upload): void
    {
        $path = null;
        $created = false;
        try {
            db()->transaction(static function () use ($user,$request,$revision,$upload,&$path,&$created): void {
                $row = Changes::row($request,true);
                $actor = Access::load($user->id,true) ?? fail(403, 'Akun tidak aktif.');
                if ($actor->account['session_version'] !== $user->account['session_version']) {
                    fail(403, 'Silakan masuk kembali.');
                }
                if ($row['personnel_id']) {
                    $row['current_unit'] = db()->one('SELECT unit_id FROM personnel WHERE id=? FOR UPDATE',[(int) $row['personnel_id']])['unit_id'];
                }
                Changes::canReview($actor,$row,'write');
                if ($row['status'] !== 'draft' || $revision !== (int) $row['revision']) {
                    fail(409, 'Hanya revisi draf saat ini yang dapat menerima berkas.');
                }
                if ((int) db()->one('SELECT COUNT(*) AS n FROM documents WHERE request_id=? AND revision=?', [$request,$revision])['n'] >= 10) {
                    fail(422, 'Maksimal 10 lampiran per revisi.');
                }
                [$mime,$bytes] = self::inspect($upload);
                $key = bin2hex(random_bytes(24));
                $path = self::path($key);
                $encrypted = crypto()->encrypt($bytes,'document:' . $key);
                $handle = fopen($path, 'xb');
                if (!$handle) {
                    throw new \RuntimeException('Cannot create private document');
                }
                $created = true;
                try {
                    if (!chmod($path,0600) || fwrite($handle,$encrypted) !== strlen($encrypted)) {
                        throw new \RuntimeException('Cannot save private document');
                    }
                    fflush($handle);
                    fsync($handle);
                } finally {
                    fclose($handle);
                }
                db()->run('INSERT INTO documents(request_id,revision,storage_key,mime,size_bytes,checksum) VALUES (?,?,?,?,?,?)',
                    [$request,$revision,$key,$mime,strlen($bytes),hash('sha256',$bytes)]);
                audit($actor->id,'document.upload',db()->lastId());
            });
        } catch (\Throwable $error) {
            if ($created && is_file($path)) {
                // A lost response to COMMIT can leave the final outcome uncertain. Keep the file
                // if a committed reference exists or the database cannot confirm the outcome.
                try {
                    if (!db()->one('SELECT 1 FROM documents WHERE storage_key=?',[basename($path)])) {
                        unlink($path);
                    }
                } catch (\PDOException) {
                    error_log('BINPROF document storage requires reconciliation');
                }
            }
            throw $error;
        }
    }

    private static function inspect(array $upload): array
    {
        $temp = $upload['tmp_name'] ?? '';
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($temp) || !is_uploaded_file($temp)
            || filesize($temp) <= 0 || filesize($temp) > config()['upload_bytes']) {
            fail(422, 'Berkas tidak diterima. Periksa batas ukuran unggahan.');
        }
        $name = $upload['name'] ?? '';
        if (!is_string($name) || !preg_match('/^[\p{L}\p{N}_ -]{1,120}\.(pdf|jpe?g|png)$/iuD',$name,$match)) {
            fail(422, 'Gunakan nama berkas sederhana dengan ekstensi PDF, JPG, atau PNG.');
        }
        $expected = match (strtolower($match[1])) {
            'pdf'=>'application/pdf','jpg','jpeg'=>'image/jpeg','png'=>'image/png',
        };
        $bytes = file_get_contents($temp);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temp);
        if ($mime !== $expected || $bytes === false) {
            fail(422, 'Format dan isi berkas tidak cocok.');
        }
        if ($mime === 'application/pdf') {
            if (!preg_match('/^%PDF-[12]\.\d/',$bytes) || !preg_match('/%%EOF\s*\z/',substr($bytes,-1024))) {
                fail(422, 'Struktur dasar PDF tidak valid.');
            }
        } else {
            $image = @getimagesizefromstring($bytes);
            $trailer = $mime === 'image/png' ? "\x00\x00\x00\x00IEND\xae\x42\x60\x82" : "\xff\xd9";
            if (!$image || $image['mime'] !== $mime || $image[0]*$image[1] > 24000000 || !str_ends_with($bytes,$trailer)) {
                fail(422, 'Struktur atau dimensi gambar tidak valid.');
            }
        }
        return [$mime,$bytes];
    }

    private static function path(string $key): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/D',$key)) {
            throw new \RuntimeException('Invalid document key');
        }
        return config()['storage'] . '/documents/' . $key;
    }

    private static function bytes(array $doc): string
    {
        $path = self::path($doc['storage_key']);
        if (!is_file($path) || is_link($path)) {
            fail(409, 'Berkas privat tidak tersedia.');
        }
        $bytes = crypto()->decrypt((string) file_get_contents($path),'document:' . $doc['storage_key']);
        if (strlen($bytes) !== (int) $doc['size_bytes'] || !hash_equals($doc['checksum'], hash('sha256',$bytes))) {
            fail(409, 'Integritas berkas tidak dapat diperiksa.');
        }
        return $bytes;
    }

    public static function checkRevision(int $request, int $revision): void
    {
        foreach (db()->all('SELECT * FROM documents WHERE request_id=? AND revision=?', [$request,$revision]) as $doc) {
            self::bytes($doc);
        }
    }

    public static function canDownload(Access $user, array $request, array $revision): bool
    {
        $approved = (int) $request['revision'] === (int) $revision['revision'] && $request['status'] === 'approved';
        if ($request['module'] === 'case') {
            $case = (int) $request['case_id'];
            return $user->caseCan($case,'download')
                && ($approved ? $user->caseCan($case,'read') : $user->revision($request,$revision));
        }
        if ($approved) {
            return $user->allows('read') && $user->allows('download') && $user->unit((int) $request['current_unit']);
        }
        return $user->revision($request,$revision);
    }

    public static function download(Access $user, int $id): never
    {
        $doc = db()->one('SELECT * FROM documents WHERE id=?', [$id]) ?? fail(404, 'Lampiran tidak ditemukan.');
        $row = Changes::row((int) $doc['request_id']);
        $rev = db()->one('SELECT revision,submitted_at FROM change_request_revisions WHERE request_id=? AND revision=?',
            [(int) $doc['request_id'],(int) $doc['revision']]);
        if (!$rev || !self::canDownload($user,$row,$rev)) {
            fail(404, 'Lampiran tidak ditemukan.');
        }
        $bytes = self::bytes($doc);
        audit($user->id,'document.download',$id);
        $extension = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$doc['mime']];
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="lampiran-' . $id . '.' . $extension . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }
}
