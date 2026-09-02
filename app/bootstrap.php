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

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function appBasePath(): string
{
    static $base = null;
    if ($base !== null) return $base;

    $value = trim((string)env('APP_BASE_PATH', ''));
    if ($value === '' || $value === '/') return $base = '';

    return $base = '/' . trim($value, '/');
}

function appPath(string $path = ''): string
{
    $base = appBasePath();
    $path = trim($path);

    if ($path === '' || $path === '/') {
        return $base === '' ? '/' : $base . '/';
    }

    $path = ltrim($path, '/');

    // Na Hostinger o projeto inteiro fica em /agendamento e os arquivos públicos
    // permanecem em /public. Assets são servidos diretamente dessa pasta, sem rewrite.
    if (str_starts_with($path, 'assets/')) {
        $path = 'public/' . $path;
    }

    return $base . '/' . $path;
}

date_default_timezone_set((string)env('APP_TIMEZONE', 'America/Sao_Paulo'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    $httpsByServer = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $httpsByConfig = str_starts_with((string)env('APP_URL', ''), 'https://');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => $httpsByServer || $httpsByConfig,
        'samesite' => 'Lax',
        'path' => appBasePath() ?: '/',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_DATABASE', 'agendamento')
    );

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

function normalizeBirthDate(string $value): ?string
{
    $value = trim($value);
    $formats = ['Y-m-d', 'd/m/Y'];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if (!$date || $date->format($format) !== $value) continue;

        $normalized = $date->format('Y-m-d');
        if ($normalized < '1900-01-01' || $normalized > date('Y-m-d')) return null;
        return $normalized;
    }

    return null;
}

function currentBookingPerson(PDO $pdo): ?array
{
    $personId = (int)($_SESSION['booking_person_id'] ?? 0);
    if ($personId <= 0) return null;

    $stmt = $pdo->prepare('SELECT id, cpf, name, birth_date FROM people WHERE id=? AND active=1 LIMIT 1');
    $stmt->execute([$personId]);
    $person = $stmt->fetch() ?: null;

    if (!$person) unset($_SESSION['booking_person_id']);
    return $person;
}

function loginBookingPerson(int $personId): void
{
    session_regenerate_id(true);
    $_SESSION['booking_person_id'] = $personId;
    unset($_SESSION['csrf']);
}

function logoutBookingPerson(): void
{
    unset($_SESSION['booking_person_id']);
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
}

function adminLoggedIn(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function requireAdmin(): void
{
    if (!adminLoggedIn()) {
        header('Location: ' . appPath('admin/login.php'));
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
