<?php

declare(strict_types=1);

function loadEnv(string $path): void
{
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}

loadEnv(dirname(__DIR__) . '/.env');

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), env('DB_DATABASE', 'agendamento'));

    $pdo = new PDO($dsn, env('DB_USERNAME', 'root'), env('DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verifyCsrf(): void
{
    $token = $_POST['_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function normalizeCpf(string $cpf): string
{
    return preg_replace('/\D+/', '', $cpf) ?? '';
}

function validCpf(string $cpf): bool
{
    $cpf = normalizeCpf($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($c = 0; $c < $t; $c++) $sum += (int)$cpf[$c] * (($t + 1) - $c);
        $digit = ((10 * $sum) % 11) % 10;
        if ((int)$cpf[$t] !== $digit) return false;
    }
    return true;
}

function subjectFromRequest(): array
{
    if (!empty($_GET['id'])) {
        $value = trim((string)$_GET['id']);
        if (!preg_match('/^[A-Za-z0-9._-]{1,100}$/', $value)) return ['', '', 'Identificador inválido.'];
        return ['id', $value, ''];
    }
    if (!empty($_GET['cpf'])) {
        $value = normalizeCpf((string)$_GET['cpf']);
        if (!validCpf($value)) return ['', '', 'CPF inválido.'];
        return ['cpf', $value, ''];
    }
    return ['', '', 'O link de agendamento não contém um identificador válido.'];
}

function authorizedSubject(PDO $pdo, string $type, string $value): ?array
{
    $stmt = $pdo->prepare("SELECT id, subject_type, subject_value, display_name
                           FROM authorized_subjects
                           WHERE subject_type=? AND subject_value=? AND active=1
                           LIMIT 1");
    $stmt->execute([$type, $value]);
    return $stmt->fetch() ?: null;
}

function adminLoggedIn(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function requireAdmin(): void
{
    if (!adminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function formatDateBr(string $date): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}

function weekdayBr(string $date): string
{
    $names = [0=>'Domingo',1=>'Segunda-feira',2=>'Terça-feira',3=>'Quarta-feira',4=>'Quinta-feira',5=>'Sexta-feira',6=>'Sábado'];
    $dt = new DateTimeImmutable($date);
    return $names[(int)$dt->format('w')];
}
