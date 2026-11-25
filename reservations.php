<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';require __DIR__ . '/app/deposit_flow.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', '0'); ini_set('session.use_strict_mode','1');
session_name('eventease_sid');
session_set_cookie_params(['path'=>'/eventease/','httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off']);
session_start();

function respond(int $code, array $payload): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function bad(string $message, int $code=422): never { respond($code,['ok'=>false,'message'=>$message]); }
function validDate(string $s): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$s)) return false;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);
    return $d!==false && $d->format('Y-m-d')===$s;
}
function validTime(string $s): bool { return (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$s); }
function cents($v): int { return (int)round((float)$v*100); }
function price(int $v): string { return number_format($v/100,2,'.',''); }
function validId($v): int { return is_scalar($v) && preg_match('/^[1-9][0-9]{0,17}$/D',(string)$v) ? (int)$v : 0; }

$method=$_SERVER['REQUEST_METHOD']??'GET';
$action=(string)($_GET['action']??'');
if ($method==='GET' && $action==='csrf') {
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    respond(200,['ok'=>true,'csrf_token'=>$_SESSION['csrf']]);
}
try { $pdo=eventease_pdo(); }
catch (Throwable $e) { error_log('EventEase API DB: '.$e->getMessage()); bad('Reservation service unavailable.',503); }
if ($method==='GET' && $action==='availability') {
    $venueId=validId($_GET['venue_id']??null); $date=(string)($_GET['date']??'');
    if (!$venueId || !validDate($date)) bad('Select a valid venue and date.');
    $q=$pdo->prepare('SELECT id FROM venues WHERE id=? AND is_active=1');
    $q->execute([$venueId]);
    if (!$q->fetchColumn()) bad('Venue is unavailable.',404);
    $q=$pdo->prepare("SELECT DATE_FORMAT(start_at,'%H:%i') AS start_time,DATE_FORMAT(end_at,'%H:%i') AS end_time FROM reservations r WHERE r.venue_id=? AND ".eeHoldCondition('r')." AND r.start_at < DATE_ADD(?,INTERVAL 1 DAY) AND r.end_at > ? ORDER BY r.start_at");
    $q->execute([$venueId,$date,$date]);
    respond(200,['ok'=>true,'booked'=>$q->fetchAll()]);
}
if ($method!=='POST' || $action!=='submit') bad('Unsupported request.',405);
if (stripos((string)($_SERVER['CONTENT_TYPE']??''),'application/json')!==0) bad('Expected JSON.',415);
if ((int)($_SERVER['CONTENT_LENGTH']??0)>16000) bad('Request is too large.',413);
$body=json_decode((string)file_get_contents('php://input',false,null,0,16001),true);
if (!is_array($body)) bad('Invalid request.');
if (!isset($_SESSION['csrf']) || !is_string($body['csrf_token']??null) || !hash_equals($_SESSION['csrf'],$body['csrf_token'])) bad('Session expired. Reload and try again.',403);
$now=time();
$_SESSION['requests']=array_values(array_filter((array)($_SESSION['requests']??[]),static fn($x)=>is_int($x) && $now-$x<600));
if (count($_SESSION['requests'])>=5) bad('Too many requests. Try again later.',429);
$_SESSION['requests'][]=$now;

foreach (['name','phone','email','event_type','date','start','end'] as $key) { if (isset($body[$key]) && !is_string($body[$key])) bad('Invalid reservation field.'); }
$name=trim((string)($body['name']??''));$phone=trim((string)($body['phone']??''));
$email=trim((string)($body['email']??''));$type=trim((string)($body['event_type']??''));
$venueId=validId($body['venue_id']??null);$packageId=validId($body['package_id']??null);
$guests=filter_var($body['guests']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
$date=trim((string)($body['date']??''));$start=trim((string)($body['start']??''));$end=trim((string)($body['end']??''));
$addonIds=$body['addons']??[];
if ($name==='' || mb_strlen($name)<2 || mb_strlen($name)>150) bad('Enter a full name (2–150 characters).');
if (!preg_match('/^09\d{9}$/D',$phone)) bad('Contact number must start with 09 and contain 11 digits.');
if ($email!=='' && (strlen($email)>191 || !filter_var($email,FILTER_VALIDATE_EMAIL))) bad('Invalid email address.');
if (!in_array($type,['Wedding','Birthday','Family Reunion','Corporate Gathering','Other Event'],true)) bad('Invalid event type.');
if (!$venueId || $guests===false) bad('Select a valid venue and guest count.');
if (!validDate($date) || !validTime($start) || !validTime($end)) bad('Invalid event date/time.');
$zone=new DateTimeZone('Asia/Manila');
$a=DateTimeImmutable::createFromFormat('!Y-m-d H:i',"$date $start",$zone);
$b=DateTimeImmutable::createFromFormat('!Y-m-d H:i',"$date $end",$zone);
$timeNow=new DateTimeImmutable('now',$zone);
if (!$a || !$b || $b<=$a) bad('End time must be later than start time.');
if ($a<=$timeNow || $a>$timeNow->modify('+2 years')) bad('Select a future event date within two years.');
if (!is_array($addonIds) || count($addonIds)>30) bad('Invalid add-ons.');
$addonIds=array_map('validId',$addonIds);
if (in_array(0,$addonIds,true) || count(array_unique($addonIds))!==count($addonIds)) bad('Invalid add-ons.');
try {
    $pdo->beginTransaction();
    // Lock by venue to serialize competing requests. Staff confirmation must use same lock.
    $q=$pdo->prepare('SELECT id,capacity,base_price FROM venues WHERE id=? AND is_active=1 FOR UPDATE');
    $q->execute([$venueId]);$venue=$q->fetch();
    if (!$venue) { $pdo->rollBack(); bad('Venue unavailable.'); }
    if ((int)$guests>(int)$venue['capacity']) { $pdo->rollBack(); bad('Guest count exceeds venue capacity.'); }
    $packageCents=0;
    if ($packageId) {
        $q=$pdo->prepare('SELECT package_price FROM packages WHERE id=? AND venue_id=? AND is_active=1');
        $q->execute([$packageId,$venueId]);$pkg=$q->fetch();
        if (!$pkg) { $pdo->rollBack(); bad('Package does not belong to this venue.'); }
        $packageCents=cents($pkg['package_price']);
    }
    $addonRows=[];$addonCents=0;
    if ($addonIds) {
        $q=$pdo->prepare('SELECT id,unit_price FROM addons WHERE is_active=1 AND id IN ('.implode(',',array_fill(0,count($addonIds),'?')).')');
        $q->execute($addonIds);$addonRows=$q->fetchAll();
        if (count($addonRows)!==count($addonIds)) { $pdo->rollBack(); bad('Invalid add-on selection.'); }
        foreach ($addonRows as $row) $addonCents+=cents($row['unit_price']);
    }
    $q=$pdo->prepare("SELECT r.id FROM reservations r WHERE r.venue_id=? AND ".eeHoldCondition('r')." AND r.start_at < ? AND r.end_at > ? LIMIT 1");
    $q->execute([$venueId,$b->format('Y-m-d H:i:s'),$a->format('Y-m-d H:i:s')]);
    if ($q->fetchColumn()) { $pdo->rollBack(); bad('Venue is already confirmed for this time. Choose another schedule.',409); }
    $q=$pdo->prepare("SELECT id FROM reservations WHERE venue_id=? AND customer_phone=? AND start_at=? AND end_at=? AND status='pending' LIMIT 1");
    $q->execute([$venueId,$phone,$a->format('Y-m-d H:i:s'),$b->format('Y-m-d H:i:s')]);
    if($q->fetchColumn()){ $pdo->rollBack(); bad('A pending request already exists for this contact and schedule. Check your existing reservation or contact the resort.',409); }
    $depositPct=eventease_deposit_percent($pdo);
    $venueCents=cents($venue['base_price']);$total=$venueCents+$packageCents+$addonCents;
    $deposit=(int)round($total*$depositPct/100);
    $reference='EE-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(5)));
    $q=$pdo->prepare("INSERT INTO reservations (reference_code,customer_name,customer_email,customer_phone,event_type,guest_count,venue_id,package_id,start_at,end_at,venue_price,package_price,addons_total,total_amount,down_payment_percent,down_payment_required,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending')");
    $q->execute([$reference,$name,$email?:null,$phone,$type,$guests,$venueId,$packageId?:null,$a->format('Y-m-d H:i:s'),$b->format('Y-m-d H:i:s'),price($venueCents),price($packageCents),price($addonCents),price($total),$depositPct,price($deposit)]);
    $reservationId=(int)$pdo->lastInsertId();
    if ($addonRows) {
        $q=$pdo->prepare('INSERT INTO reservation_addons (reservation_id,addon_id,quantity,unit_price,line_total) VALUES (?,?,1,?,?)');
        foreach ($addonRows as $row) {$amount=price(cents($row['unit_price']));$q->execute([$reservationId,$row['id'],$amount,$amount]);}
    }
    $q=$pdo->prepare("INSERT INTO reservation_history (reservation_id,action,to_status,details) VALUES (?,'customer_request','pending','Customer request submitted')");
    $q->execute([$reservationId]);
    $pdo->commit();
    respond(201,['ok'=>true,'reference'=>$reference,'status'=>'Pending','total'=>price($total),'down_payment'=>price($deposit),'remaining'=>price($total-$deposit),'message'=>'Request saved. Admin confirmation is required.']);
} catch(Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('EventEase API reservation: '.$e->getMessage());
    bad('Unable to save reservation. Please try again.',500);
}
