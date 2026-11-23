<?php
declare(strict_types=1);

namespace Binprof;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, __NAMESPACE__ . '\\')) {
        require __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen(__NAMESPACE__) + 1)) . '.php';
    }
});

function configPath(): string
{
    return getenv('BINPROF_CONFIG') ?: dirname(__DIR__) . '/config/local.php';
}

function config(): array
{
    static $config;
    return $config ??= array_replace(require dirname(__DIR__) . '/config/example.php',
        require configPath());
}

function db(): Repositories\Database
{
    static $db;
    return $db ??= new Repositories\Database(config());
}

function crypto(): Services\Crypto
{
    static $crypto;
    return $crypto ??= new Services\Crypto(config()['keys']);
}

function today(): string
{
    return (new \DateTimeImmutable('now', new \DateTimeZone(config()['timezone'])))->format('Y-m-d');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = 'dashboard', array $params = []): string
{
    $config = config();
    $prefix = rtrim($config['base_path'], '/');
    if ($config['clean_urls']) {
        return $prefix . '/' . $route . ($params ? '?' . http_build_query($params) : '');
    }
    return $prefix . '/index.php?' . http_build_query(['r' => $route] + $params);
}

function asset(string $file): string
{
    return rtrim(config()['base_path'], '/') . '/assets/' . $file;
}

function fail(int $status, string $message): never
{
    throw new Problem($message, $status);
}

function id(mixed $value): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
    return $number === false ? 0 : $number;
}

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
}

function audit(?int $actor, string $event, ?int $object = null): void
{
    static $token;
    $token ??= bin2hex(random_bytes(12));
    db()->run('INSERT INTO audit_logs(actor_id,event,object_id,request_token) VALUES (?,?,?,?)',
        [$actor, $event, $object, $token]);
}
