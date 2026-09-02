<?php

declare(strict_types=1);
require dirname(__DIR__,2) . '/app/bootstrap.php';

if (adminLoggedIn()) { header('Location: ' . appPath('admin/')); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $user=(string)($_POST['user']??'');
    $password=(string)($_POST['password']??'');
    if (hash_equals((string)env('ADMIN_USER','admin'),$user) && hash_equals((string)env('ADMIN_PASSWORD','troque-esta-senha'),$password)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in']=true;
        header('Location: ' . appPath('admin/'));
        exit;
    }
    $error='Usuário ou senha inválidos.';
}
$assetVersion=(int)@filemtime(dirname(__DIR__) . '/assets/app.css');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administração · Agendamento</title><link rel="stylesheet" href="<?=e(appPath('assets/app.css'))?>?v=<?=$assetVersion?>"></head><body>
<div class="login-card"><div style="background:#00862F;border-radius:16px;padding:12px;text-align:center"><img src="<?=e(appPath('assets/logo.svg'))?>" alt="AGEHAB" style="width:120px;max-height:70px;object-fit:contain"></div><h1>Administração</h1><p class="muted">Entre para configurar datas, horários e consultar os agendamentos.</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form method="post"><input type="hidden" name="_token" value="<?=e(csrfToken())?>"><label>Usuário</label><input name="user" autocomplete="username" required><label>Senha</label><input type="password" name="password" autocomplete="current-password" required><button class="primary" style="margin-top:18px" type="submit">Entrar</button></form></div></body></html>
