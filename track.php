<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';require __DIR__.'/app/deposit_flow.php';require __DIR__.'/app/deposit_actions.php';date_default_timezone_set('Asia/Manila');
ini_set('display_errors','0');ini_set('session.use_strict_mode','1');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
session_name('eventease_sid');session_set_cookie_params(['path'=>'/eventease/','httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);session_start();
function trackReply(int $status,array $data): never{http_response_code($status);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')trackReply(405,['ok'=>false,'message'=>'Use the reservation tracking form.']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)trackReply(413,['ok'=>false,'message'=>'Request too large.']);
$data=json_decode((string)file_get_contents('php://input',false,null,0,4097),true);
if(!is_array($data)||!isset($_SESSION['csrf'])||!is_string($data['csrf_token']??null)||!hash_equals($_SESSION['csrf'],$data['csrf_token']))trackReply(403,['ok'=>false,'message'=>'Refresh the page and try again.']);
$now=time();$attempts=array_values(array_filter((array)($_SESSION['track_attempts']??[]),static fn($t)=>is_int($t)&&$now-$t<600));
if(count($attempts)>=8)trackReply(429,['ok'=>false,'message'=>'Too many lookup attempts. Try again later.']);
$attempts[]=$now;$_SESSION['track_attempts']=$attempts;
$ref=is_string($data['reference']??null)?strtoupper(trim($data['reference'])):'';
$phone=is_string($data['phone']??null)?trim($data['phone']):'';
if(!preg_match('/^EE-[0-9]{6}-[A-F0-9]{10}$/D',$ref)||!preg_match('/^09[0-9]{9}$/D',$phone))trackReply(422,['ok'=>false,'message'=>'Enter your booking reference and 11-digit contact number.']);
try{
 $db=eventease_pdo();
 $q=$db->prepare('SELECT r.id,r.venue_id,r.approved_at,r.reference_code,r.event_type,r.start_at,r.end_at,r.status,r.guest_count,r.total_amount,r.down_payment_required,v.name AS venue_name,p.name AS package_name,COALESCE((SELECT SUM(x.amount) FROM payments x WHERE x.reservation_id=r.id),0) AS paid FROM reservations r JOIN venues v ON v.id=r.venue_id LEFT JOIN packages p ON p.id=r.package_id WHERE r.reference_code=? AND r.customer_phone=? LIMIT 1');
 $q->execute([$ref,$phone]);$r=$q->fetch();
 if(!$r)trackReply(404,['ok'=>false,'message'=>'No matching reservation. Check your reference and contact number.']);
 $action=$data['action']??'lookup';if(!in_array($action,['lookup','submit_reference'],true))trackReply(422,['ok'=>false,'message'=>'Invalid action.']);
 if($action==='submit_reference'){ $tx=is_string($data['gcash_reference']??null)?trim($data['gcash_reference']):'';eeSubmitReference($db,$r,$phone,$tx);$q->execute([$ref,$phone]);$r=$q->fetch(); }
 $info=eeDepositInfo($db,$r);$r['deposit_state']=$info['state'];$r['deposit_deadline']=$info['deadline'];$r['gcash_number']=$info['state']==='awaiting_payment'?$info['number']:'';unset($r['id'],$r['venue_id'],$r['approved_at']);
 $r['remaining']=number_format(max(0,(float)$r['total_amount']-(float)$r['paid']),2,'.','');
 trackReply(200,['ok'=>true,'reservation'=>$r]);
}catch(Throwable $ex){if($ex instanceof RuntimeException&&!($ex instanceof PDOException))trackReply(409,['ok'=>false,'message'=>$ex->getMessage()]);error_log('EventEase lookup: '.$ex->getMessage());trackReply(503,['ok'=>false,'message'=>'Tracking is temporarily unavailable.']);}
