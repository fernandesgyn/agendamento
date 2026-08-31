<?php

declare(strict_types=1);

/**
 * Seed de CPFs sintéticos para desenvolvimento/homologação.
 *
 * Os números são gerados localmente a partir de bases sequenciais e recebem
 * os dígitos verificadores calculados pelo algoritmo do CPF. Nenhum CPF é
 * armazenado no repositório e nenhum dado é obtido de pessoas reais.
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

// Cenários para validar visualmente capacidade e desaparecimento de horários.
$scenarios = [
    ['2026-08-14', '07:00:00', 6], // lotado
    ['2026-08-14', '08:00:00', 5], // 1 vaga restante
    ['2026-08-14', '09:00:00', 3], // 3 vagas restantes
    ['2026-08-15', '07:00:00', 6], // lotado
    ['2026-08-16', '19:00:00', 4], // 2 vagas restantes
];

$findSlot = $pdo->prepare(
    "SELECT s.id
       FROM scheduling_slots s
       JOIN scheduling_days d ON d.id = s.scheduling_day_id
      WHERE d.service_date = ? AND s.service_time = ?
      LIMIT 1"
);

$insert = $pdo->prepare(
    "INSERT IGNORE INTO appointments (slot_id, subject_type, subject_value)
     VALUES (?, 'cpf', ?)"
);

$base = 100000001;
$generated = [];

$pdo->beginTransaction();
try {
    foreach ($scenarios as [$date, $time, $quantity]) {
        $findSlot->execute([$date, $time]);
        $slotId = $findSlot->fetchColumn();
        if (!$slotId) {
            throw new RuntimeException("Horário não encontrado: {$date} {$time}");
        }

        for ($i = 0; $i < $quantity; $i++) {
            do {
                $cpf = syntheticCpf($base++);
            } while (!validCpf($cpf));

            $insert->execute([(int)$slotId, $cpf]);
            $generated[] = [
                'cpf' => $cpf,
                'date' => $date,
                'time' => substr($time, 0, 5),
            ];
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Erro ao executar seed: {$e->getMessage()}\n");
    exit(1);
}

fwrite(STDOUT, "Seed concluído. CPFs sintéticos gerados:\n\n");
foreach ($generated as $row) {
    fwrite(STDOUT, sprintf("%s  ->  %s às %s\n", $row['cpf'], $row['date'], $row['time']));
}

fwrite(STDOUT, "\nUse somente em ambiente de desenvolvimento/homologação.\n");
