<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

[$subjectType, $subjectValue, $subjectError] = subjectFromRequest();
$message = '';
$error = $subjectError;
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    verifyCsrf();
    $postedType = (string)($_POST['subject_type'] ?? '');
    $postedValue = (string)($_POST['subject_value'] ?? '');
    $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);

    if ($postedType !== $subjectType || !hash_equals($subjectValue, $postedValue) || !$slotId) {
        $error = 'Dados do agendamento inválidos. Atualize a página e tente novamente.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT s.id, s.capacity, s.active, d.active day_active, d.service_date, s.service_time
                                   FROM scheduling_slots s
                                   JOIN scheduling_days d ON d.id=s.scheduling_day_id
                                   WHERE s.id=? FOR UPDATE");
            $stmt->execute([$slotId]);
            $slot = $stmt->fetch();
            if (!$slot || !$slot['active'] || !$slot['day_active']) {
                throw new RuntimeException('Este horário não está mais disponível.');
            }

            $stmt = $pdo->prepare("SELECT id, slot_id FROM appointments
                                   WHERE subject_type=? AND subject_value=? AND status='active'
                                   ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([$subjectType, $subjectValue]);
            $existing = $stmt->fetch();

            if ($existing && (int)$existing['slot_id'] === (int)$slotId) {
                $pdo->commit();
                $message = 'Seu agendamento já está confirmado para esse horário.';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE slot_id=? AND status='active'");
                $stmt->execute([$slotId]);
                $used = (int)$stmt->fetchColumn();
                if ($used >= (int)$slot['capacity']) {
                    throw new RuntimeException('As vagas desse horário acabaram. Escolha outro horário disponível.');
                }

                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE appointments SET status='cancelled', cancelled_at=NOW() WHERE id=?");
                    $stmt->execute([$existing['id']]);
                }

                $stmt = $pdo->prepare("INSERT INTO appointments (slot_id, subject_type, subject_value) VALUES (?,?,?)");
                $stmt->execute([$slotId, $subjectType, $subjectValue]);
                $pdo->commit();
                $message = ($existing ? 'Agendamento alterado' : 'Agendamento confirmado') . ' para ' . formatDateBr($slot['service_date']) . ' às ' . substr($slot['service_time'], 0, 5) . '.';
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $ex instanceof RuntimeException ? $ex->getMessage() : 'Não foi possível gravar o agendamento. Tente novamente.';
        }
    }
}

$current = null;
$days = [];
if (!$subjectError) {
    $stmt = $pdo->prepare("SELECT a.id, d.service_date, s.service_time
                           FROM appointments a
                           JOIN scheduling_slots s ON s.id=a.slot_id
                           JOIN scheduling_days d ON d.id=s.scheduling_day_id
                           WHERE a.subject_type=? AND a.subject_value=? AND a.status='active'
                           ORDER BY a.id DESC LIMIT 1");
    $stmt->execute([$subjectType, $subjectValue]);
    $current = $stmt->fetch() ?: null;

    $rows = $pdo->query("SELECT d.id day_id, d.service_date, s.id slot_id, s.service_time, s.capacity,
                        (SELECT COUNT(*) FROM appointments a WHERE a.slot_id=s.id AND a.status='active') used
                        FROM scheduling_days d
                        JOIN scheduling_slots s ON s.scheduling_day_id=d.id
                        WHERE d.active=1 AND s.active=1
                        ORDER BY d.service_date, s.service_time")->fetchAll();
    foreach ($rows as $row) {
        if ((int)$row['used'] >= (int)$row['capacity']) continue;
        $days[$row['service_date']][] = $row;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#005b45">
<title><?= e(env('APP_NAME','Agendamento AGEHAB')) ?></title>
<link rel="stylesheet" href="/assets/app.css">
<script src="/assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
  <div class="brand"><img src="https://aluguelsocial.agehab.go.gov.br/img/agehab.svg" alt="AGEHAB"><span>Agendamento de Atendimento</span></div>
</header>
<main class="container">
  <section class="hero">
    <span class="eyebrow">ATENDIMENTO PRESENCIAL</span>
    <h1>Escolha a melhor data e horário</h1>
    <p>Selecione uma data e depois um dos horários disponíveis. Horários lotados não são exibidos.</p>
  </section>

  <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

  <?php if (!$subjectError): ?>
    <?php if ($current): ?>
      <div class="current"><strong>Seu agendamento atual</strong><span><?= e(formatDateBr($current['service_date'])) ?> às <?= e(substr($current['service_time'],0,5)) ?></span><small>Ao escolher outro horário, o agendamento atual será substituído.</small></div>
    <?php endif; ?>

    <?php if (!$days): ?>
      <div class="empty"><h2>Não há horários disponíveis</h2><p>As vagas disponíveis para agendamento foram preenchidas ou desativadas.</p></div>
    <?php else: ?>
      <div class="steps"><span class="active">1</span><b>Data</b><i></i><span>2</span><b>Horário</b></div>
      <div class="date-grid" id="dateGrid">
        <?php $first=true; foreach ($days as $date=>$slots): ?>
          <button class="date-card<?= $first?' selected':'' ?>" type="button" data-date="<?= e($date) ?>">
            <small><?= e(weekdayBr($date)) ?></small><strong><?= e((new DateTimeImmutable($date))->format('d')) ?></strong><span><?= e((new DateTimeImmutable($date))->format('m/Y')) ?></span>
          </button>
        <?php $first=false; endforeach; ?>
      </div>

      <section class="slot-section">
        <h2>Horários disponíveis</h2>
        <p class="muted">Toque no horário desejado. A confirmação será solicitada antes da gravação.</p>
        <?php $first=true; foreach ($days as $date=>$slots): ?>
          <div class="slot-grid" data-slots-for="<?= e($date) ?>"<?= $first?'':' hidden' ?>>
            <?php foreach ($slots as $slot): $left=(int)$slot['capacity']-(int)$slot['used']; ?>
              <button class="slot" type="button" data-slot-id="<?= (int)$slot['slot_id'] ?>" data-date-label="<?= e(formatDateBr($date)) ?>" data-time="<?= e(substr($slot['service_time'],0,5)) ?>">
                <strong><?= e(substr($slot['service_time'],0,5)) ?></strong><small><?= $left ?> <?= $left===1?'vaga':'vagas' ?></small>
              </button>
            <?php endforeach; ?>
          </div>
        <?php $first=false; endforeach; ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</main>

<div class="modal" id="confirmModal" hidden>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <button class="modal-close" type="button" data-close aria-label="Fechar">×</button>
    <div class="modal-icon">✓</div><h2 id="modalTitle">Confirmar agendamento?</h2>
    <p>Você deseja agendar o atendimento para:</p><strong class="modal-choice" id="modalChoice"></strong>
    <form method="post">
      <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="subject_type" value="<?= e($subjectType) ?>">
      <input type="hidden" name="subject_value" value="<?= e($subjectValue) ?>">
      <input type="hidden" name="slot_id" id="modalSlotId">
      <button class="primary" type="submit">Sim, confirmar</button>
      <button class="secondary" type="button" data-close>Voltar</button>
    </form>
  </div>
</div>
<footer>AGEHAB · Agência Goiana de Habitação</footer>
</body></html>
