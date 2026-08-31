<?php

declare(strict_types=1);
require dirname(__DIR__,2) . '/app/bootstrap.php';
requireAdmin();
$pdo=db();
$message='';$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    $action=(string)($_POST['action']??'');
    try{
        if($action==='add_subject'){
            $type=(string)($_POST['subject_type']??'cpf');
            $value=trim((string)($_POST['subject_value']??''));
            $name=trim((string)($_POST['display_name']??''));
            if(!in_array($type,['cpf','id'],true)) throw new RuntimeException('Tipo de identificador inválido.');
            if($type==='cpf'){
                $value=normalizeCpf($value);
                if(!validCpf($value)) throw new RuntimeException('CPF inválido.');
            }elseif(!preg_match('/^[A-Za-z0-9._-]{1,100}$/',$value)){
                throw new RuntimeException('ID inválido. Use somente letras, números, ponto, hífen ou underline.');
            }
            $stmt=$pdo->prepare("INSERT INTO authorized_subjects(subject_type,subject_value,display_name,active) VALUES(?,?,?,1)
                                 ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),active=1");
            $stmt->execute([$type,$value,$name!==''?$name:null]);
            $message='Pessoa cadastrada/reativada para agendamento.';
        }elseif($action==='toggle_subject'){
            $stmt=$pdo->prepare('UPDATE authorized_subjects SET active=IF(active=1,0,1) WHERE id=?');
            $stmt->execute([(int)$_POST['subject_id']]);
            $message='Autorização atualizada.';
        }elseif($action==='add_day'){
            $date=(string)($_POST['service_date']??'');
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new RuntimeException('Data inválida.');
            $stmt=$pdo->prepare('INSERT INTO scheduling_days(service_date,active) VALUES(?,1) ON DUPLICATE KEY UPDATE active=1');$stmt->execute([$date]);
            $message='Data adicionada/reativada.';
        }elseif($action==='toggle_day'){
            $stmt=$pdo->prepare('UPDATE scheduling_days SET active=IF(active=1,0,1) WHERE id=?');$stmt->execute([(int)$_POST['day_id']]);$message='Data atualizada.';
        }elseif($action==='add_slot'){
            $dayId=(int)($_POST['day_id']??0);$time=(string)($_POST['service_time']??'');$capacity=(int)($_POST['capacity']??6);
            if(!$dayId||!preg_match('/^\d{2}:\d{2}$/',$time)||$capacity<1||$capacity>999) throw new RuntimeException('Dados do horário inválidos.');
            $stmt=$pdo->prepare('INSERT INTO scheduling_slots(scheduling_day_id,service_time,capacity,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE capacity=VALUES(capacity),active=1');$stmt->execute([$dayId,$time.':00',$capacity]);$message='Horário adicionado/atualizado.';
        }elseif($action==='toggle_slot'){
            $stmt=$pdo->prepare('UPDATE scheduling_slots SET active=IF(active=1,0,1) WHERE id=?');$stmt->execute([(int)$_POST['slot_id']]);$message='Horário atualizado.';
        }elseif($action==='capacity'){
            $capacity=(int)($_POST['capacity']??0);if($capacity<1||$capacity>999)throw new RuntimeException('Capacidade inválida.');
            $stmt=$pdo->prepare('UPDATE scheduling_slots SET capacity=? WHERE id=?');$stmt->execute([$capacity,(int)$_POST['slot_id']]);$message='Capacidade atualizada.';
        }
    }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Não foi possível executar a operação.';}
}

$days=$pdo->query('SELECT * FROM scheduling_days ORDER BY service_date')->fetchAll();
$rows=$pdo->query("SELECT d.id day_id,d.service_date,d.active day_active,s.id slot_id,s.service_time,s.capacity,s.active slot_active,COUNT(a.id) used
FROM scheduling_days d LEFT JOIN scheduling_slots s ON s.scheduling_day_id=d.id LEFT JOIN appointments a ON a.slot_id=s.id AND a.status='active'
GROUP BY d.id,d.service_date,d.active,s.id,s.service_time,s.capacity,s.active ORDER BY d.service_date,s.service_time")->fetchAll();
$appointments=$pdo->query("SELECT a.subject_type,a.subject_value,d.service_date,s.service_time,a.booked_at FROM appointments a JOIN scheduling_slots s ON s.id=a.slot_id JOIN scheduling_days d ON d.id=s.scheduling_day_id WHERE a.status='active' ORDER BY d.service_date,s.service_time,a.booked_at")->fetchAll();
$subjects=$pdo->query("SELECT au.id,au.subject_type,au.subject_value,au.display_name,au.active,
                             a.id appointment_id,d.service_date,s.service_time,a.booked_at
                      FROM authorized_subjects au
                      LEFT JOIN appointments a ON a.subject_type=au.subject_type AND a.subject_value=au.subject_value AND a.status='active'
                      LEFT JOIN scheduling_slots s ON s.id=a.slot_id
                      LEFT JOIN scheduling_days d ON d.id=s.scheduling_day_id
                      ORDER BY (a.id IS NULL) DESC, au.display_name, au.subject_value")->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Painel · Agendamento</title><link rel="stylesheet" href="/assets/app.css"></head><body>
<div class="admin-wrap"><div class="admin-nav"><div><h1 style="margin-bottom:4px">Painel de Agendamento</h1><span class="muted">Pessoas, datas, horários, vagas e participantes</span></div><div class="actions"><a href="/admin/export.php">Exportar CSV</a><a href="/admin/logout.php">Sair</a></div></div>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>

<section class="panel"><h2>Cadastrar pessoa para agendamento</h2><p class="muted">Somente CPFs/IDs cadastrados aqui poderão utilizar o link público.</p><form class="form-grid" method="post"><input type="hidden" name="_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="add_subject"><select name="subject_type"><option value="cpf">CPF</option><option value="id">ID</option></select><input type="text" name="subject_value" placeholder="CPF ou ID" required><input type="text" name="display_name" placeholder="Nome (opcional)"><button class="primary" type="submit">Cadastrar pessoa</button></form></section>

<section class="panel"><h2>Pessoas autorizadas</h2><p class="muted">Use um registro com status <strong>LIVRE</strong> para testar um novo agendamento.</p><div class="table-wrap"><table class="admin-table"><thead><tr><th>Nome</th><th>Tipo</th><th>CPF / ID</th><th>Situação</th><th>Agendamento</th><th>Autorização</th><th>Ação</th></tr></thead><tbody>
<?php foreach($subjects as $s):?><tr><td><?=e($s['display_name']?:'—')?></td><td><?=e(strtoupper($s['subject_type']))?></td><td><?=e($s['subject_value'])?></td><td><span class="badge <?=isset($s['appointment_id'])?'badge-booked':'badge-free'?>"><?=isset($s['appointment_id'])?'AGENDADO':'LIVRE'?></span></td><td><?=isset($s['appointment_id'])?e(formatDateBr($s['service_date']).' às '.substr($s['service_time'],0,5)):'Ainda não escolheu horário'?></td><td><?=((int)$s['active']===1)?'Ativa':'Inativa'?></td><td><form method="post"><input type="hidden" name="_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="toggle_subject"><input type="hidden" name="subject_id" value="<?=(int)$s['id']?>"><button class="small-btn"><?=((int)$s['active']===1)?'Desativar':'Ativar'?></button></form></td></tr><?php endforeach;?><?php if(!$subjects):?><tr><td colspan="7">Nenhuma pessoa cadastrada para agendamento.</td></tr><?php endif;?></tbody></table></div></section>

<section class="panel"><h2>Adicionar data</h2><form class="form-grid" method="post"><input type="hidden" name="_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="add_day"><input type="date" name="service_date" required><button class="primary" type="submit">Adicionar data</button></form></section>
<section class="panel"><h2>Adicionar ou alterar horário</h2><form class="form-grid" method="post"><input type="hidden" name="_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="add_slot"><select name="day_id" required><?php foreach($days as $d):?><option value="<?=(int)$d['id']?>"><?=e(formatDateBr($d['service_date']))?></option><?php endforeach;?></select><input type="time" name="service_time" required><input type="number" name="capacity" min="1" max="999" value="6" required><button class="primary" type="submit">Salvar horário</button></form></section>
<section class="panel"><h2>Configuração e ocupação</h2><div class="table-wrap"><table class="admin-table"><thead><tr><th>Data</th><th>Horário</th><th>Ocupação</th><th>Capacidade</th><th>Status</th><th>Ações</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=e(formatDateBr($r['service_date']))?></td><td><?=isset($r['service_time'])?e(substr($r['service_time'],0,5)):'—'?></td><td><?=isset($r['slot_id'])?(int)$r['used'].' / '.(int)$r['capacity']:'—'?></td><td><?php if(isset($r['slot_id'])):?><form class="actions" method="post"><input type="hidden" name="_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="capacity"><input type="hidden" name="slot_id" value="<?=(int)$r['slot_id']?>"><input style="width:75px;padding:7px" type="number" min="1" max="999" name="capacity" value="<?=(int)$r['capacity']?>"><button class="small-btn">Salvar</button></form><?php endif;?></td><td><span class="badge"><?=($r['day_active']&&($r['slot_active']??false))?'Ativo':'Inativo'?></span></td><td class="actions"><form method="post"><input type="hidden" name="_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="toggle_day"><input type="hidden" name="day_id" value="<?=(int)$r['day_id']?>"><button class="small-btn">Ativar/desativar data</button></form><?php if(isset($r['slot_id'])):?><form method="post"><input type="hidden" name="_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="toggle_slot"><input type="hidden" name="slot_id" value="<?=(int)$r['slot_id']?>"><button class="small-btn">Ativar/desativar horário</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="panel"><h2>Agendamentos ativos</h2><div class="table-wrap"><table class="admin-table"><thead><tr><th>Data</th><th>Hora</th><th>Tipo</th><th>Identificador</th><th>Gravado em</th></tr></thead><tbody><?php foreach($appointments as $a):?><tr><td><?=e(formatDateBr($a['service_date']))?></td><td><?=e(substr($a['service_time'],0,5))?></td><td><?=e(strtoupper($a['subject_type']))?></td><td><?=e($a['subject_value'])?></td><td><?=e((new DateTimeImmutable($a['booked_at']))->format('d/m/Y H:i'))?></td></tr><?php endforeach;?><?php if(!$appointments):?><tr><td colspan="5">Nenhum agendamento ativo.</td></tr><?php endif;?></tbody></table></div></section></div></body></html>
