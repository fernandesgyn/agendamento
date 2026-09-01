<?php

declare(strict_types=1);
require dirname(__DIR__,2) . '/app/bootstrap.php';
requireAdmin();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="agendamentos-'.date('Ymd-His').'.csv"');
$out=fopen('php://output','wb');
fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['Data','Hora','Nome','CPF','Data de nascimento','Agendado em'],';');
$sql="SELECT d.service_date,s.service_time,p.name,p.cpf,p.birth_date,a.booked_at
      FROM appointments a
      JOIN people p ON p.id=a.person_id
      JOIN scheduling_slots s ON s.id=a.slot_id
      JOIN scheduling_days d ON d.id=s.scheduling_day_id
      WHERE a.status='active'
      ORDER BY d.service_date,s.service_time,p.name";
foreach(db()->query($sql) as $row){
    fputcsv($out,[
        formatDateBr($row['service_date']),
        substr($row['service_time'],0,5),
        $row['name'],
        $row['cpf'],
        formatDateBr($row['birth_date']),
        (new DateTimeImmutable($row['booked_at']))->format('d/m/Y H:i')
    ],';');
}
fclose($out);
