<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = db();
$loginError = '';
$message = '';
$error = '';
$person = currentBookingPerson($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'login' && !$person) {
        verifyCsrf();
        $cpf = normalizeCpf((string)($_POST['cpf'] ?? ''));
        $birthDate = normalizeBirthDate((string)($_POST['birth_date'] ?? ''));

        if (!validCpf($cpf) || !$birthDate) {
            $loginError = 'Cadastro não encontrado. Confira o CPF e a data de nascimento informados.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM people WHERE cpf=? AND birth_date=? AND active=1 LIMIT 1');
            $stmt->execute([$cpf, $birthDate]);
            $personId = (int)($stmt->fetchColumn() ?: 0);

            if ($personId <= 0) {
                $loginError = 'Cadastro não encontrado. Confira o CPF e a data de nascimento informados.';
            } else {
                loginBookingPerson($personId);
                header('Location: ' . appPath());
                exit;
            }
        }
    } elseif ($action === 'book' && $person) {
        verifyCsrf();
        $slotId = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);

        if (!$slotId) {
            $error = 'Horário inválido. Atualize a página e tente novamente.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('SELECT id FROM people WHERE id=? AND active=1 LIMIT 1 FOR UPDATE');
                $stmt->execute([(int)$person['id']]);
                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('Seu cadastro não está disponível para realizar o agendamento.');
                }

                $stmt = $pdo->prepare("SELECT id FROM appointments WHERE person_id=? AND status='active' ORDER BY id DESC LIMIT 1 FOR UPDATE");
                $stmt->execute([(int)$person['id']]);
                if ($stmt->fetchColumn()) {
                    throw new RuntimeException('Seu agendamento já foi confirmado e não pode ser alterado ou excluído.');
                }

                $stmt = $pdo->prepare("SELECT s.id, s.capacity, s.active, d.active AS day_active, d.service_date, s.service_time
                                       FROM scheduling_slots s
                                       JOIN scheduling_days d ON d.id=s.scheduling_day_id
                                       WHERE s.id=? FOR UPDATE");
                $stmt->execute([$slotId]);
                $slot = $stmt->fetch();

                if (!$slot || !(int)$slot['active'] || !(int)$slot['day_active']) {
                    throw new RuntimeException('Este horário não está mais disponível.');
                }

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE slot_id=? AND status='active'");
                $stmt->execute([$slotId]);
                $used = (int)$stmt->fetchColumn();

                if ($used >= (int)$slot['capacity']) {
                    throw new RuntimeException('As vagas desse horário acabaram. Escolha outro horário disponível.');
                }

                $stmt = $pdo->prepare('INSERT INTO appointments (slot_id, person_id) VALUES (?, ?)');
                $stmt->execute([$slotId, (int)$person['id']]);
                $pdo->commit();

                $message = 'Agendamento confirmado para ' . formatDateBr($slot['service_date']) . ' às ' . substr($slot['service_time'], 0, 5) . '.';
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();

                if ($ex instanceof RuntimeException) {
                    $error = $ex->getMessage();
                } elseif ($ex instanceof PDOException && $ex->getCode() === '23000') {
                    $error = 'Seu agendamento já foi confirmado e não pode ser alterado ou excluído.';
                } else {
                    $error = 'Não foi possível gravar o agendamento. Tente novamente.';
                }
            }
        }
    }
}

$person = currentBookingPerson($pdo);
$current = null;
$days = [];

if ($person) {
    $stmt = $pdo->prepare("SELECT a.id, d.service_date, s.service_time
                           FROM appointments a
                           JOIN scheduling_slots s ON s.id=a.slot_id
                           JOIN scheduling_days d ON d.id=s.scheduling_day_id
                           WHERE a.person_id=? AND a.status='active'
                           ORDER BY a.id DESC LIMIT 1");
    $stmt->execute([(int)$person['id']]);
    $current = $stmt->fetch() ?: null;

    if (!$current) {
        $rows = $pdo->query("SELECT d.service_date, s.id AS slot_id, s.service_time, s.capacity,
                            (SELECT COUNT(*) FROM appointments a WHERE a.slot_id=s.id AND a.status='active') AS used
                            FROM scheduling_days d
                            JOIN scheduling_slots s ON s.scheduling_day_id=d.id
                            WHERE d.active=1 AND s.active=1
                            ORDER BY d.service_date, s.service_time")->fetchAll();

        foreach ($rows as $row) {
            $left = max(0, (int)$row['capacity'] - (int)$row['used']);
            if ($left === 0) continue;

            $date = $row['service_date'];
            $timeKey = substr($row['service_time'], 0, 5);

            if (!isset($days[$date])) {
                $days[$date] = ['available' => 0, 'slots' => []];
            }

            $row['left'] = $left;
            $days[$date]['slots'][$timeKey] = $row;
        }

        foreach ($days as $date => &$day) {
            ksort($day['slots']);
            $day['available'] = array_sum(array_map(
                static fn(array $slot): int => (int)$slot['left'],
                $day['slots']
            ));
            if ($day['available'] <= 0) unset($days[$date]);
        }
        unset($day);
    }
}

$firstDate = $days ? array_key_first($days) : null;
$assetVersion = max(
    (int)@filemtime(__DIR__ . '/assets/app.css'),
    (int)@filemtime(__DIR__ . '/assets/app.js')
);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#00862F">
<title><?= e(env('APP_NAME', 'Agendamento AGEHAB')) ?></title>
<link rel="stylesheet" href="<?= e(appPath('assets/app.css')) ?>?v=<?= $assetVersion ?>">
<script src="<?= e(appPath('assets/app.js')) ?>?v=<?= $assetVersion ?>" defer></script>
</head>
<body>
<header class="topbar">
  <div class="brand"><img src="<?= e(appPath('assets/logo.svg')) ?>" alt="AGEHAB"></div>
</header>

<main class="container">
<?php if (!$person): ?>
  <section class="hero login-hero">
    <span class="eyebrow">ACESSO AO AGENDAMENTO</span>
    <h1>Identifique-se para continuar</h1>
    <p>Informe seu CPF e sua data de nascimento para acessar as datas e horários disponíveis.</p>
  </section>

  <?php if ($loginError): ?><div class="alert error"><?= e($loginError) ?></div><?php endif; ?>

  <section class="public-login-card" aria-labelledby="login-title">
    <div class="login-symbol">✓</div>
    <h2 id="login-title">Acessar agendamento</h2>
    <p>Use os mesmos dados do seu cadastro.</p>
    <form method="post" autocomplete="on">
      <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="login">

      <label for="cpf">CPF</label>
      <input id="cpf" name="cpf" type="text" inputmode="numeric" autocomplete="username" maxlength="14" placeholder="000.000.000-00" required autofocus>

      <label for="birth_date">Data de nascimento</label>
      <input id="birth_date" name="birth_date" type="text" inputmode="numeric" autocomplete="bday" maxlength="10" placeholder="dd/mm/aaaa" required>

      <button class="primary login-submit" type="submit">ENTRAR</button>
    </form>
    <small>Se seus dados não forem localizados, o sistema informará que não existe cadastro disponível para agendamento.</small>
  </section>
<?php else: ?>
  <div class="session-bar">
    <div><small>AGENDAMENTO PARA</small><strong><?= e($person['name']) ?></strong></div>
    <form method="post" action="<?= e(appPath('logout.php')) ?>"><input type="hidden" name="_token" value="<?= e(csrfToken()) ?>"><button type="submit" class="session-logout">Sair</button></form>
  </div>

  <section class="hero">
    <span class="eyebrow">ATENDIMENTO PRESENCIAL</span>
    <?php if ($current): ?>
      <h1>Agendamento confirmado</h1>
      <p>Confira abaixo a data e o horário do seu atendimento.</p>
    <?php else: ?>
      <h1>Escolha sua data e horário</h1>
      <p>Escolha o dia e depois toque no horário desejado. Após a confirmação, o agendamento não poderá ser alterado.</p>
    <?php endif; ?>
  </section>

  <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

  <?php if ($current): ?>
    <section class="current">
      <strong>AGENDAMENTO CONFIRMADO</strong>
      <span><?= e(formatDateBr($current['service_date'])) ?> às <?= e(substr($current['service_time'], 0, 5)) ?></span>
      <small>Este agendamento é definitivo. Não é possível alterar a data, o horário ou excluir pelo acesso público.</small>
    </section>

    <section class="pending-card" aria-labelledby="pending-title">
      <div class="pending-icon">!</div>
      <div class="pending-content">
        <strong id="pending-title">Conferir / Visualizar Pendências Documentais</strong>
        <span>Consulte se existem pendências documentais relacionadas ao seu atendimento.</span>
        <a href="https://palladioweb.agehab.go.gov.br/noautentic/LoginVisualizacaoPendencias.aspx" target="_blank" rel="noopener noreferrer">Acesse Aqui</a>
      </div>
    </section>
  <?php elseif (!$days): ?>
    <div class="empty">No momento não existem datas e horários disponíveis para agendamento.</div>
  <?php else: ?>
    <div class="section-title">
      <span class="step-number">1</span>
      <div><small>PRIMEIRO PASSO</small><h2>Escolha o dia</h2></div>
    </div>

    <div class="date-grid">
      <?php foreach ($days as $date => $day): ?>
        <?php $dateLabel = formatDateBr($date); ?>
        <button type="button" class="date-card <?= $date === $firstDate ? 'selected' : '' ?>" data-date="<?= e($date) ?>" data-date-label="<?= e($dateLabel) ?>" aria-pressed="<?= $date === $firstDate ? 'true' : 'false' ?>">
          <span class="date-main">
            <small><?= e(weekdayBr($date)) ?></small>
            <strong><?= e($dateLabel) ?></strong>
            <span>Toque para selecionar</span>
          </span>
          <span class="date-vacancies"><strong><?= (int)$day['available'] ?></strong><small><?= (int)$day['available'] === 1 ? 'VAGA DISPONÍVEL' : 'VAGAS DISPONÍVEIS' ?></small></span>
        </button>
      <?php endforeach; ?>
    </div>

    <section class="slot-section">
      <div class="section-title">
        <span class="step-number">2</span>
        <div><small>SEGUNDO PASSO</small><h2>Escolha o horário</h2></div>
      </div>
      <p id="selectedDayLabel" class="selected-day-label"><?= e(formatDateBr((string)$firstDate)) ?></p>
      <p class="slot-help muted">Os horários sem vagas não são exibidos.</p>

      <div class="slot-grid">
        <?php foreach ($days as $date => $day): ?>
          <?php foreach ($day['slots'] as $timeKey => $slot): ?>
            <button type="button" class="slot" data-slot-id="<?= (int)$slot['slot_id'] ?>" data-slot-date="<?= e($date) ?>" data-date-label="<?= e(formatDateBr($date)) ?>" data-time="<?= e($timeKey) ?>" <?= $date !== $firstDate ? 'hidden' : '' ?>>
              <span class="slot-time"><?= e($timeKey) ?></span>
              <span class="slot-vacancies"><b><?= (int)$slot['left'] ?></b> <?= (int)$slot['left'] === 1 ? 'vaga restante' : 'vagas restantes' ?></span>
            </button>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </section>

    <div id="confirmModal" class="modal" hidden>
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <button type="button" class="modal-close" data-close aria-label="Fechar">×</button>
        <div class="modal-icon">✓</div>
        <h2 id="modal-title">Confirmar agendamento?</h2>
        <p>Confira com atenção. Depois de confirmar, você <strong>não poderá alterar ou excluir</strong> o agendamento.</p>
        <strong id="modalChoice" class="modal-choice"></strong>
        <form method="post">
          <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="action" value="book">
          <input id="modalSlotId" type="hidden" name="slot_id" value="">
          <button class="primary" type="submit">SIM, CONFIRMAR</button>
          <button class="secondary" type="button" data-close>VOLTAR E CONFERIR</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
