<?php

declare(strict_types=1);

/**
 * Massa sintética para desenvolvimento/homologação.
 *
 * Cria pessoas com CPF, nome e data de nascimento. A maioria fica SEM horário,
 * para permitir testar o login e o fluxo real de agendamento. Uma pequena parte
 * recebe horários previamente reservados para simular ocupação e horários lotados.
 *
 * Requer o banco previamente criado por database/schema.sql.
 * Este seed insere apenas dados de teste; não cria nem altera tabelas.
 *
 * NÃO execute este arquivo em produção.
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este seed deve ser executado somente pela linha de comando.\n");
    exit(1);
}

function syntheticCpf(int $base): string
{
    $nine = str_pad((string)$base, 9, '0', STR_PAD_LEFT);
    $digits = array_map('intval', str_split($nine));

    $sum = 0;
    for ($i = 0; $i < 9; $i++) $sum += $digits[$i] * (10 - $i);
    $d1 = (10 * $sum) % 11;
    if ($d1 === 10) $d1 = 0;
    $digits[] = $d1;

    $sum = 0;
    for ($i = 0; $i < 10; $i++) $sum += $digits[$i] * (11 - $i);
    $d2 = (10 * $sum) % 11;
    if ($d2 === 10) $d2 = 0;

    return $nine . $d1 . $d2;
}

$pdo = db();
$totalPeople = 60;
$base = 100000001;
$people = [];

for ($i = 1; $i <= $totalPeople; $i++) {
    do {
        $cpf = syntheticCpf($base++);
    } while (!validCpf($cpf));

    $year = 1960 + ($i % 45);
    $month = (($i * 3) % 12) + 1;
    $day = (($i * 7) % 28) + 1;

    $people[] = [
        'cpf' => $cpf,
        'name' => sprintf('Pessoa Teste %02d', $i),
        'birth_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
    ];
}

$scenarios = [
    ['2026-08-14', '07:00:00', 6], // lotado
    ['2026-08-14', '08:00:00', 5], // 1 vaga restante
    ['2026-08-14', '09:00:00', 3], // 3 vagas restantes
    ['2026-08-15', '07:00:00', 6], // lotado
    ['2026-08-16', '19:00:00', 4], // 2 vagas restantes
];

$insertPerson = $pdo->prepare(
    'INSERT INTO people (cpf, name, birth_date, active) VALUES (?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE name=VALUES(name), birth_date=VALUES(birth_date), active=1'
);
$findPerson = $pdo->prepare('SELECT id FROM people WHERE cpf=? LIMIT 1');
$findSlot = $pdo->prepare(
    'SELECT s.id FROM scheduling_slots s
     JOIN scheduling_days d ON d.id=s.scheduling_day_id
     WHERE d.service_date=? AND s.service_time=? LIMIT 1'
);
$insertAppointment = $pdo->prepare('INSERT IGNORE INTO appointments (slot_id, person_id) VALUES (?, ?)');

$pdo->beginTransaction();
try {
    foreach ($people as &$person) {
        $insertPerson->execute([$person['cpf'], $person['name'], $person['birth_date']]);
        $findPerson->execute([$person['cpf']]);
        $person['id'] = (int)$findPerson->fetchColumn();
    }
    unset($person);

    $personIndex = 0;
    foreach ($scenarios as [$date, $time, $quantity]) {
        $findSlot->execute([$date, $time]);
        $slotId = (int)$findSlot->fetchColumn();
        if ($slotId <= 0) throw new RuntimeException("Horário não encontrado: {$date} {$time}");

        for ($i = 0; $i < $quantity; $i++) {
            if (!isset($people[$personIndex])) throw new RuntimeException('Não há pessoas suficientes para os cenários de ocupação.');
            $insertAppointment->execute([$slotId, (int)$people[$personIndex]['id']]);
            $personIndex++;
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Erro ao executar seed: {$e->getMessage()}\n");
    exit(1);
}

$check = $pdo->prepare(
    "SELECT d.service_date,s.service_time FROM appointments a
     JOIN scheduling_slots s ON s.id=a.slot_id
     JOIN scheduling_days d ON d.id=s.scheduling_day_id
     WHERE a.person_id=? AND a.status='active' LIMIT 1"
);

$free = [];
$reserved = [];
foreach ($people as $person) {
    $check->execute([(int)$person['id']]);
    $appointment = $check->fetch();
    if ($appointment) {
        $reserved[] = $person + [
            'date' => $appointment['service_date'],
            'time' => substr($appointment['service_time'], 0, 5),
        ];
    } else {
        $free[] = $person;
    }
}

fwrite(STDOUT, "\n=======================================================================\n");
fwrite(STDOUT, "PESSOAS LIVRES PARA TESTAR LOGIN E FAZER NOVO AGENDAMENTO\n");
fwrite(STDOUT, "=======================================================================\n\n");
foreach ($free as $person) {
    fwrite(STDOUT, sprintf(
        "CPF %s | Nascimento %s | %s\n",
        $person['cpf'],
        formatDateBr($person['birth_date']),
        $person['name']
    ));
}

fwrite(STDOUT, "\n=======================================================================\n");
fwrite(STDOUT, "PESSOAS JA AGENDADAS - SOMENTE PARA SIMULAR OCUPACAO\n");
fwrite(STDOUT, "=======================================================================\n\n");
foreach ($reserved as $person) {
    fwrite(STDOUT, sprintf(
        "CPF %s | Nascimento %s | %s -> %s as %s\n",
        $person['cpf'],
        formatDateBr($person['birth_date']),
        $person['name'],
        $person['date'],
        $person['time']
    ));
}

fwrite(STDOUT, sprintf(
    "\nResumo: %d pessoas cadastradas, %d livres e %d ja agendadas.\n",
    count($people), count($free), count($reserved)
));
fwrite(STDOUT, "Acesse http://localhost:8080/ e use CPF + data de nascimento.\n");
fwrite(STDOUT, "Use somente em ambiente de desenvolvimento/homologacao.\n");
