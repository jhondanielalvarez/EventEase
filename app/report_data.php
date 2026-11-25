<?php
declare(strict_types=1);

/** Report dates use the resort's local calendar (Asia/Manila). */
function reportMonth(mixed $requested): string {
    if (is_string($requested) && preg_match('/^20[0-9]{2}-(?:0[1-9]|1[0-2])$/D', $requested)) return $requested;
    return date('Y-m');
}
function reportRows(PDO $db, string $sql, array $params): array {
    $q=$db->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function reportScalar(PDO $db, string $sql, array $params): mixed {
    $q=$db->prepare($sql);$q->execute($params);return $q->fetchColumn();
}
function monthlyReport(PDO $db, string $month): array {
    $month=reportMonth($month);
    $from=$month.'-01 00:00:00';
    $to=(new DateTimeImmutable($from))->modify('+1 month')->format('Y-m-d H:i:s');
    $range=[$from,$to];
    $status=array_fill_keys(['pending','confirmed','paid','completed','cancelled','rejected'],0);
    foreach(reportRows($db,'SELECT status, COUNT(*) AS n FROM reservations WHERE start_at>=? AND start_at<? GROUP BY status',$range) as $r) {
        if(array_key_exists($r['status'],$status))$status[$r['status']]=(int)$r['n'];
    }
    $newRequests=(int)reportScalar($db,'SELECT COUNT(*) FROM reservations WHERE created_at>=? AND created_at<?',$range);
    $payments=reportRows($db,'SELECT method, COUNT(*) AS n, COALESCE(SUM(amount),0) AS amount FROM payments WHERE received_at>=? AND received_at<? GROUP BY method ORDER BY method',$range);
    $received=(string)reportScalar($db,'SELECT COALESCE(SUM(amount),0) FROM payments WHERE received_at>=? AND received_at<?',$range);
    $booked=(string)reportScalar($db,"SELECT COALESCE(SUM(total_amount),0) FROM reservations WHERE start_at>=? AND start_at<? AND status IN ('confirmed','paid','completed')",$range);
    $eventTypes=reportRows($db,"SELECT event_type, COUNT(*) AS n FROM reservations WHERE start_at>=? AND start_at<? AND status IN ('confirmed','paid','completed') GROUP BY event_type ORDER BY n DESC,event_type ASC",$range);
    $venues=reportRows($db,"SELECT v.name, COUNT(*) AS n FROM reservations r JOIN venues v ON v.id=r.venue_id WHERE r.start_at>=? AND r.start_at<? AND r.status IN ('confirmed','paid','completed') GROUP BY v.id,v.name ORDER BY n DESC,v.name ASC",$range);
    $events=reportRows($db,'SELECT r.reference_code,r.event_type,r.start_at,r.end_at,r.status,r.total_amount,v.name AS venue_name FROM reservations r JOIN venues v ON v.id=r.venue_id WHERE r.start_at>=? AND r.start_at<? ORDER BY r.start_at,r.id',$range);
    $receipts=reportRows($db,'SELECT p.id,p.received_at,p.method,p.amount,r.reference_code FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE p.received_at>=? AND p.received_at<? ORDER BY p.received_at,p.id',$range);
    return compact('month','from','to','status','newRequests','payments','received','booked','eventTypes','venues','events','receipts');
}
