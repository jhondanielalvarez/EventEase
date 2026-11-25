<?php
declare(strict_types=1);
function eeHoldCondition(string $a='r'): string {
 return "($a.status IN ('confirmed','paid') OR ($a.status='pending' AND $a.approved_at IS NOT NULL AND $a.start_at>NOW() AND (DATE_ADD($a.approved_at,INTERVAL 24 HOUR)>NOW() OR EXISTS(SELECT 1 FROM reservation_history h WHERE h.reservation_id=$a.id AND h.action='gcash_reference_submitted' AND h.id>COALESCE((SELECT MAX(hr.id) FROM reservation_history hr WHERE hr.reservation_id=$a.id AND hr.action='deposit_requested'),0)))))";
}
function eeDepositInfo(PDO $db,array $r): array{
 $q=$db->prepare("SELECT id,details FROM reservation_history WHERE reservation_id=? AND action='deposit_requested' ORDER BY id DESC LIMIT 1");$q->execute([$r['id']]);$h=$q->fetch();
 if(!$h||!$r['approved_at']||$r['status']!=='pending')return ['state'=>'review','number'=>'','reference'=>'','deadline'=>''];
 $info=json_decode((string)$h['details'],true)?:[];
 $q=$db->prepare("SELECT details FROM reservation_history WHERE reservation_id=? AND action='gcash_reference_submitted' AND id>? ORDER BY id DESC LIMIT 1");$q->execute([$r['id'],$h['id']]);$ref=(string)$q->fetchColumn();
 $deadline=min(strtotime($r['approved_at'])+86400,strtotime($r['start_at']));
 $state=$ref!==''?'verification':($deadline>time()?'awaiting_payment':'expired');
 if(strtotime($r['start_at'])<=time())$state=$ref!==''?'verification_expired':'expired';
 return ['state'=>$state,'number'=>(string)($info['number']??''),'reference'=>$ref,'deadline'=>date('Y-m-d H:i:s',$deadline)];
}
function eeGcash(PDO $db,bool $lock=false): string {
 // A stable lock row serializes reference checks even when GCash settings are blank.
 if($lock){if(!$db->inTransaction())throw new LogicException('Reference lock requires a transaction.');$db->exec("INSERT INTO app_settings(setting_key,setting_value) VALUES ('payment_reference_lock','internal') ON DUPLICATE KEY UPDATE setting_value=setting_value");}
 $q=$db->query("SELECT setting_value FROM app_settings WHERE setting_key='resort_gcash'".($lock?' FOR UPDATE':''));
 $n=(string)$q->fetchColumn();return preg_match('/^09[0-9]{9}$/D',$n)?$n:'';
}
function eeReferenceUsed(PDO $db,string $ref,int $except=0): bool{
 $q=$db->prepare("SELECT id FROM reservation_history WHERE action='gcash_reference_submitted' AND details=? AND reservation_id<>? LIMIT 1");$q->execute([$ref,$except]);if($q->fetchColumn())return true;
 $q=$db->prepare("SELECT id FROM payments WHERE method='e_wallet' AND payment_reference=? LIMIT 1");$q->execute([$ref]);return (bool)$q->fetchColumn();
}
