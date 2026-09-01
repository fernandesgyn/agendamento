<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

verifyCsrf();
logoutBookingPerson();
header('Location: /');
exit;
