<?php

declare(strict_types=1);

/**
 * Seed de CPFs sintéticos para desenvolvimento/homologação.
 *
 * Cria uma lista de pessoas autorizadas a agendar. A maioria fica SEM horário,
 * para permitir testar o fluxo real de agendamento. Uma pequena parte recebe
 * horários previamente reservados apenas para simular ocupação, poucas vagas
 * restantes e horários lotados.
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
    for ($i = 0; $i < 9; $i++) {
        $sum += $digits[$i] * (10 - $i);
    }
    $d1 = (10 * $sum) % 11;
    if ($d1 === 10) $d1 = 0;
    $digits[] = $d1;

    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += $digits[$i] * (11 - $i);
    }
    $d2 = (10 * $sum) % 11;
    if ($d2 === 10) $d2 = 0;

    return $nine . $d1 . $d2;
}

$pdo = db();

// 60 pessoas de teste cadastradas. Somente 24 são usadas para simular ocupação.
$totalPeople = 60;
$base = 100000001;
$cpfs = [];

for ($i = 1; $i <= $totalPeople; $i++) {
    do {
        $cpf = syntheticCpf($base++);
    } while (!validCpf($cpf));

    $cpfs[] = [
        'cpf' => $cpf,
        'name' => sprintf('Pessoa Teste %02d', $i),
    ];
}

// Cenários visuais de ocupação.
$scenarios = [
    ['2026-08-14', '07:00:00', 6], // lotado
    ['2026-08-14', '08:00:00', 5], // 1 vaga restante
    ['2026-08-14', '09:00:00', 3], // 3 vagas restantes
    ['2026-08-15', '07:00:00', 6], // lotado
    ['2026-08-16', '19:00:00', 4], // 2 vagas restantes
];

$insertAuthorized = $pdo->prepare(
    "INSERT INTO authorized_subjects (subject_type, subject_value, display_name, active)
     VALUES ('cpf', ?, ?, 1)
     ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), active=1"
);

$findSlot = $pdo->prepare(
    "SELECT s.id
       FROM scheduling_slots s
       JOIN scheduling_days d ON d.id = s.scheduling_day_id
      WHERE d.service_date = ? AND s.service_time = ?
      LIMIT 1"
);

$insertAppointment = $pdo->prepare(
    "INSERT IGNORE INTO appointments (slot_id, subject_type, subject_value)
     VALUES (?, 'cpf', ?)"
);

$pdo->beginTransaction();
try {
    foreach ($cpfs as $person) {
        $insertAuthorized->execute([$person['cpf'], $person['name']]);
    }

    $cpfIndex = 0;
    foreach ($scenarios as [$date, $time, $quantity]) {
        $findSlot->execute([$date, $time]);
        $slotId = $findSlot->fetchColumn();
        if (!$slotId) {
            throw new RuntimeException("Horário não encontrado: {$date} {$time}");
        }

        for ($i = 0; $i < $quantity; $i++) {
            if (!isset($cpfs[$cpfIndex])) {
                throw new RuntimeException('Não há CPFs suficientes para montar os cenários de ocupação.');
            }
            $insertAppointment->execute([(int)$slotId, $cpfs[$cpfIndex]['cpf']]);
            $cpfIndex++;
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Erro ao executar seed: {$e->getMessage()}\n");
    exit(1);
}

// Classifica pelo estado REAL do banco, inclusive se o seed já tiver sido executado antes.
$check = $pdo->prepare(
    "SELECT d.service_date, s.service_time
       FROM appointments a
       JOIN scheduling_slots s ON s.id=a.slot_id
       JOIN scheduling_days d ON d.id=s.scheduling_day_id
      WHERE a.subject_type='cpf' AND a.subject_value=? AND a.status='active'
      LIMIT 1"
);

$free = [];
$reserved = [];
foreach ($cpfs as $person) {
    $check->execute([$person['cpf']]);
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

fwrite(STDOUT, "\n============================================================\n");
fwrite(STDOUT, "CPFs CADASTRADOS E LIVRES PARA FAZER NOVO AGENDAMENTO\n");
fwrite(STDOUT, "============================================================\n\n");
foreach ($free as $person) {
    fwrite(STDOUT, sprintf("%s  |  %s\n", $person['cpf'], $person['name']));
}

fwrite(STDOUT, "\n============================================================\n");
fwrite(STDOUT, "CPFs JA AGENDADOS - SOMENTE PARA SIMULAR OCUPACAO\n");
fwrite(STDOUT, "============================================================\n\n");
foreach ($reserved as $person) {
    fwrite(STDOUT, sprintf(
        "%s  |  %s  ->  %s as %s\n",
        $person['cpf'],
        $person['name'],
        $person['date'],
        $person['time']
    ));
}

fwrite(STDOUT, sprintf(
    "\nResumo: %d CPFs cadastrados, %d livres para agendar e %d ja reservados.\n",
    count($cpfs), count($free), count($reserved)
));
fwrite(STDOUT, "Use somente em ambiente de desenvolvimento/homologacao.\n");
