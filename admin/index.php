<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';require_once dirname(__DIR__).'/app/deposit_flow.php';require_once dirname(__DIR__).'/app/deposit_actions.php';
date_default_timezone_set('Asia/Manila');
ini_set('display_errors','0');
ini_set('session.use_strict_mode','1');
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
session_name('eventease_admin_sid');
session_set_cookie_params(['lifetime'=>0,'path'=>'/eventease/admin/','httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')]);
session_start();

function e(mixed $v): string {return htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function redirect(string $url='index.php'): never {header('Location: '.$url,true,303);exit;}
function flash(string $kind,string $info): void {$_SESSION['flash']=['kind'=>$kind,'info'=>$info];}
function token(): string {return $_SESSION['csrf']??($_SESSION['csrf']=bin2hex(random_bytes(32)));}
function verifyToken(): void {if(!hash_equals(token(),(string)($_POST['csrf']??'')))throw new RuntimeException('Invalid session token. Refresh the page.');}
function price(mixed $n): string {return '₱'.number_format((float)$n,2);}
function moneyCents(string $s): int {
 if(!preg_match('/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/D',$s))throw new RuntimeException('Invalid amount.');
 [$whole,$fraction]=array_pad(explode('.',$s,2),2,'');
 $n=(int)$whole*100+(int)str_pad($fraction,2,'0');
 if($n<1)throw new RuntimeException('Amount must be positive.');
 return $n;
}
function amount(int $cents): string {return number_format($cents/100,2,'.','');}
function eventHours(string $date,string $from,string $to): array {
 if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$from)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$to))throw new RuntimeException('Invalid event schedule.');
 $tz=new DateTimeZone('Asia/Manila');
 $a=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$from,$tz);
 $b=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$to,$tz);
 if(!$a||!$b||$a->format('Y-m-d H:i')!==$date.' '.$from||$b->format('Y-m-d H:i')!==$date.' '.$to||$b<=$a)throw new RuntimeException('End time must follow start time.');
 if($a<=new DateTimeImmutable('now',$tz))throw new RuntimeException('Choose a future event time.');
 return [$a->format('Y-m-d H:i:s'),$b->format('Y-m-d H:i:s')];
}
function conflicts(PDO $db,int $venue,int $id,string $start,string $end): bool {
 $q=$db->prepare("SELECT r.id FROM reservations r WHERE r.venue_id=? AND r.id<>? AND ".eeHoldCondition('r')." AND r.start_at<? AND r.end_at>? LIMIT 1");
 $q->execute([$venue,$id,$end,$start]);return (bool)$q->fetchColumn();
}
function recordLog(PDO $db,int $id,int $staff,string $action,string $from,string $to,string $details): void {
 $q=$db->prepare('INSERT INTO reservation_history(reservation_id,performed_by,action,from_status,to_status,details) VALUES (?,?,?,?,?,?)');
 $q->execute([$id,$staff,$action,$from,$to,$details]);
}
try{$db=eventease_pdo();}
catch(Throwable $ex){error_log('Admin database error '.$ex->getMessage());http_response_code(503);exit('EventEase admin database unavailable.');}
$staff=null;
if(isset($_SESSION['staff_id'])){
 if(time()-(int)($_SESSION['active_at']??0)<=2700){
  $q=$db->prepare('SELECT id,full_name,username,role,is_active FROM staff_users WHERE id=?');
  $q->execute([(int)$_SESSION['staff_id']]);$staff=$q->fetch();
  if($staff && (int)$staff['is_active'] && $staff['role']==='admin'){
   $_SESSION['active_at']=time();
   if(time()-(int)($_SESSION['regen_at']??0)>900){session_regenerate_id(true);$_SESSION['regen_at']=time();}
  }else{$staff=null;}
 }
 if(!$staff){$_SESSION=[];session_regenerate_id(true);}
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  verifyToken();
  $action=(string)($_POST['action']??'');
  if($action==='login'){
   $now=time();
   $tries=array_values(array_filter((array)($_SESSION['tries']??[]),static fn($time)=>is_int($time)&&$now-$time<900));
   if(count($tries)>=5)throw new RuntimeException('Too many attempts. Try again later.');
   $username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
   if(strlen($username)>60||strlen($password)>256)throw new RuntimeException('Invalid credentials.');
   $q=$db->prepare('SELECT * FROM staff_users WHERE username=? LIMIT 1');$q->execute([$username]);$user=$q->fetch();
   if(!$user||!(int)$user['is_active']||$user['role']!=='admin'||!password_verify($password,$user['password_hash'])){
    $tries[]=$now;$_SESSION['tries']=$tries;throw new RuntimeException('Invalid username or password.');
   }
   session_regenerate_id(true);
   $_SESSION['staff_id']=(int)$user['id'];$_SESSION['active_at']=time();$_SESSION['regen_at']=time();unset($_SESSION['tries']);
   $db->prepare('UPDATE staff_users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
   flash('success','Welcome, '.$user['full_name'].'.');redirect();
  }
  if(!$staff)throw new RuntimeException('Please sign in first.');
  if($action==='catalog_save'){
   require_once dirname(__DIR__).'/app/catalog_admin.php';
   $tab=['venue'=>'venues','package'=>'packages','addon'=>'addons','settings'=>'settings'][$_POST['kind']??'']??'venues';
   try{catalogSave($db);flash('success','Changes saved.');}catch(Throwable $ex){error_log('Catalog: '.$ex->getMessage());flash('error',$ex instanceof PDOException?'Unable to save: check for duplicate records.':($ex instanceof RuntimeException?$ex->getMessage():'Unable to save changes.'));}
   redirect('index.php?page=catalog&tab='.$tab);
  }
  if($action==='logout'){$_SESSION=[];session_regenerate_id(true);flash('success','Signed out.');redirect();}
  if($action==='password'){
   $q=$db->prepare('SELECT password_hash FROM staff_users WHERE id=?');$q->execute([$staff['id']]);
   if(!password_verify((string)($_POST['old']??''),(string)$q->fetchColumn()))throw new RuntimeException('Current password is incorrect.');
   $new=(string)($_POST['new']??'');
   if(strlen($new)<12||strlen($new)>128||$new!==(string)($_POST['confirm']??''))throw new RuntimeException('New password must be 12–128 characters and match confirmation.');
   $db->prepare('UPDATE staff_users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$staff['id']]);
   session_regenerate_id(true);flash('success','Password changed.');redirect('index.php#account');
  }
  $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$id)throw new RuntimeException('Select a valid reservation.');
  if(in_array($action,['approve','verify_deposit','reject','cancel','complete','reschedule'],true)){
   $note=trim((string)($_POST['note']??''));
   if(mb_strlen($note)>500)throw new RuntimeException('Reason is too long.');
   if(in_array($action,['reject','cancel'],true)&&mb_strlen($note)<3)throw new RuntimeException('Enter a reason with at least 3 characters.');
   $q=$db->prepare('SELECT venue_id FROM reservations WHERE id=?');$q->execute([$id]);$venue=(int)$q->fetchColumn();
   if(!$venue)throw new RuntimeException('Reservation not found.');
   $db->beginTransaction();
   try{
    // Match Phase 3 lock order (venue, then reservation) to serialize confirmations.
    $q=$db->prepare('SELECT id FROM venues WHERE id=? FOR UPDATE');$q->execute([$venue]);
    if(!$q->fetchColumn())throw new RuntimeException('Venue missing.');
    $q=$db->prepare('SELECT * FROM reservations WHERE id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch();
    if(!$r)throw new RuntimeException('Reservation missing.');
    $old=$r['status'];$new=$old;$description=$note;
    if(in_array($action,['approve','verify_deposit'],true)){ [$action,$new,$description]=eeReviewDeposit($db,$r,$staff,$action);
    }elseif($action==='reject'){
     if($old!=='pending')throw new RuntimeException('Only pending requests may be rejected.');
     $new='rejected';$db->prepare("UPDATE reservations SET status='rejected',status_reason=? WHERE id=?")->execute([$note,$id]);
    }elseif($action==='cancel'){
     if(!in_array($old,['pending','confirmed','paid'],true))throw new RuntimeException('Cannot cancel this reservation.');
     $new='cancelled';$db->prepare("UPDATE reservations SET status='cancelled',status_reason=? WHERE id=?")->execute([$note,$id]);
    }elseif($action==='complete'){
     if(!in_array($old,['confirmed','paid'],true)||strtotime($r['end_at'])>time())throw new RuntimeException('The confirmed event must have finished before completion.');
     $q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id=?');$q->execute([$id]);if(round((float)$q->fetchColumn()*100)<round((float)$r['total_amount']*100))throw new RuntimeException('Settle the remaining balance before completion.');$new='completed';$description='Event completed';
     $db->prepare("UPDATE reservations SET status='completed' WHERE id=?")->execute([$id]);
    }else{
     if(!in_array($old,['pending','confirmed','paid'],true))throw new RuntimeException('Cannot reschedule this reservation.');
     [$start,$end]=eventHours((string)($_POST['date']??''),(string)($_POST['start']??''),(string)($_POST['end']??''));
     if(conflicts($db,$venue,(int)$id,$start,$end))throw new RuntimeException('Reschedule blocked: overlapping confirmed booking.');
     if($old==='pending'){ $info=eeDepositInfo($db,$r);if(in_array($info['state'],['awaiting_payment','verification','verification_expired'],true))throw new RuntimeException('Resolve the active down payment before rescheduling.');$db->prepare('UPDATE reservations SET approved_at=NULL,approved_by=NULL WHERE id=?')->execute([$id]); } $db->prepare('UPDATE reservations SET start_at=?,end_at=? WHERE id=?')->execute([$start,$end,$id]);
     $description='Rescheduled from '.$r['start_at'].'-'.$r['end_at'].' to '.$start.'-'.$end;
    }
    recordLog($db,(int)$id,(int)$staff['id'],$action,$old,$new,$description);
    $db->commit();
   }catch(Throwable $ex){if($db->inTransaction())$db->rollBack();throw $ex;}
   flash('success','Reservation updated successfully.');redirect('index.php?view='.(int)$id.'#details');
  }
  if($action==='payment'){
   $cents=moneyCents(trim((string)($_POST['amount']??'')));
   $method=(string)($_POST['method']??'');
   $reference=trim((string)($_POST['reference']??''));
   $date=(string)($_POST['date']??'');
   $notes=trim((string)($_POST['notes']??''));
   if(!in_array($method,['cash','bank_transfer','e_wallet','other'],true)||mb_strlen($reference)>120||mb_strlen($notes)>1000)throw new RuntimeException('Invalid payment details.');
   $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
   if(!$dt||$dt->format('Y-m-d')!==$date||$date>date('Y-m-d'))throw new RuntimeException('Choose a valid received date.');
   $hasProof=isset($_FILES['proof'])&&($_FILES['proof']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
   if($method!=='cash'&&$reference===''&&!$hasProof)throw new RuntimeException('Non-cash payments require reference number or proof.');
   $proof=null;$path=null;
   if($hasProof){
    $file=$_FILES['proof'];
    if($file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name'])||(int)$file['size']>4*1024*1024)throw new RuntimeException('Upload failed or proof exceeds 4MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $accepted=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
    if(!isset($accepted[$mime]))throw new RuntimeException('Proof must be JPG, PNG, WebP or PDF.');
    $proof=bin2hex(random_bytes(16)).'.'.$accepted[$mime];
    $folder=eventease_private_dir().'/payment_proofs';
    if(!is_dir($folder)&&!mkdir($folder,0700,true))throw new RuntimeException('Could not create proof folder.');
    $path=$folder.'/'.$proof;
   }
   $db->beginTransaction();
   try{
    $q=$db->prepare('SELECT * FROM reservations WHERE id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch();
    if(!$r||!in_array($r['status'],['confirmed','paid','completed'],true))throw new RuntimeException('Payments require a confirmed or completed reservation.');
    if($method==='e_wallet'&&$reference!=='')eeGcash($db,true);
    $q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id=?');$q->execute([$id]);
    $paid=(int)round((float)$q->fetchColumn()*100);$total=(int)round((float)$r['total_amount']*100);
    if($date<substr($r['start_at'],0,10))throw new RuntimeException('Remaining balance is payable at the resort on the event day or later.');if($paid+$cents!==$total)throw new RuntimeException('Record the full remaining balance.');if($method==='e_wallet'&&$reference!==''){if(eeReferenceUsed($db,$reference))throw new RuntimeException('GCash reference already used.');}
    if($path&&!move_uploaded_file($_FILES['proof']['tmp_name'],$path))throw new RuntimeException('Proof could not be saved.');
    $db->prepare('INSERT INTO payments(reservation_id,recorded_by,amount,method,payment_reference,proof_path,notes,received_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$id,$staff['id'],amount($cents),$method,$reference?:null,$proof,$notes?:null,$date.' '.date('H:i:s')]);
    $status=$r['status'];$new=$status;
    if($status==='confirmed'&&$paid+$cents===$total){
     $db->prepare("UPDATE reservations SET status='paid' WHERE id=?")->execute([$id]);$new='paid';
    }
    recordLog($db,(int)$id,(int)$staff['id'],'payment_recorded',$status,$new,'Recorded '.amount($cents).' by '.$method);
    $db->commit();
   }catch(Throwable $ex){if($db->inTransaction())$db->rollBack();if($path&&is_file($path))@unlink($path);throw $ex;}
   flash('success','Payment saved.');redirect('index.php?view='.(int)$id.'#details');
  }
  throw new RuntimeException('Unsupported action.');
 }catch(Throwable $ex){
  error_log('EventEase admin action: '.$ex->getMessage());
  $publicMessage=$ex instanceof PDOException?($ex->getCode()==='23000'?'A record with these details already exists.':'Database action failed.'):($ex instanceof RuntimeException?$ex->getMessage():'Action failed. Please try again.');
  flash('error',$publicMessage);redirect($staff&&isset($_POST['id'])?'index.php?view='.(int)$_POST['id'].'#details':($staff&&($_POST['action']??'')==='password'?'index.php#account':'index.php'));
 }
}
$notice=$_SESSION['flash']??null;unset($_SESSION['flash']);$csrf=token();
if(!$staff):
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><link rel="icon" type="image/jpeg" href="../assets/images/resort/logo.jpg"><title>EventEase | Admin Login</title>
<style>:root{--green:#173e34}*{box-sizing:border-box}body{font:15px/1.7 system-ui,sans-serif;margin:0;background:#f7f8f5;color:#253c32}.wrap{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}.left{background:var(--green);color:white;padding:12vh 9vw}.left h1{font:normal clamp(39px,5vw,72px)/1.12 Georgia,serif;margin:70px 0 20px}.left em{color:#d7c39f}.left p{color:#c5dcd0}.logo{font-size:25px;font-weight:800}.right{display:grid;place-items:center;padding:35px}.login{width:min(100%,400px)}.login h2{font:normal 34px Georgia,serif;color:var(--green)}.login p{color:#728078}.field{margin:19px 0}.field label{display:block;font-weight:700;font-size:12px;margin-bottom:7px}.field input{width:100%;border:1px solid #d4ded5;border-radius:9px;padding:13px;font:inherit}.btn{background:var(--green);color:white;border:0;border-radius:9px;padding:13px 19px;font-weight:750;cursor:pointer}.full{width:100%}.notice{padding:13px;border-radius:8px}.error{background:#ffe8df;color:#994d38}.success{background:#e1efe7;color:#216144}a{color:#2c6650}@media(max-width:760px){.wrap{display:block}.left{padding:35px 24px}.left h1{font-size:34px;margin:24px 0 10px}.right{padding:45px 24px}}</style></head><body><main class="wrap"><section class="left"><div class="logo"><img src="../assets/images/resort/logo.jpg" alt="Terraza Del Rio logo" width="42" height="42" style="width:42px;height:42px;object-fit:contain;border-radius:7px;vertical-align:middle;margin-right:9px">EventEase</div><h1>Manage every <em>special occasion.</em></h1><p>Terraza Del Rio Private Resort &amp; Events Place · Authorized admin portal.</p></section><section class="right"><div class="login"><h2>Welcome back.</h2><p>Sign in to manage bookings, schedules, and payment records.</p><?php if($notice):?><div class="notice <?=e($notice['kind'])?>"><?=e($notice['info'])?></div><?php endif;?><form method="post" id="eventease-login-form" autocomplete="off"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="login"><div class="field"><label for="username">Username</label><input id="username" name="username" required autocomplete="off" autocapitalize="off" spellcheck="false" readonly onfocus="this.removeAttribute('readonly')" data-lpignore="true" maxlength="60"></div><div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="off" readonly onfocus="this.removeAttribute('readonly')" data-lpignore="true"></div><button class="btn full" type="submit">Sign in securely</button></form><script>window.addEventListener("pageshow",function(){var f=document.getElementById("eventease-login-form");if(!f)return;f.reset();["username","password"].forEach(function(id){var input=document.getElementById(id);if(input){input.value="";input.setAttribute("readonly","readonly");}});});</script><p><a href="../">← Return to website</a></p></div></section></main></body></html>
<?php exit; endif;

if(isset($_GET['proof'])){
 $proofId=filter_var($_GET['proof'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$proofId){http_response_code(404);exit('Not found');}
 $q=$db->prepare('SELECT proof_path FROM payments WHERE id=?');$q->execute([$proofId]);$file=$q->fetchColumn();
 if(!$file||!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|pdf)$/D',$file)){http_response_code(404);exit('Not found');}
 $path=eventease_private_dir().'/payment_proofs/'.$file;
 if(!is_file($path)){http_response_code(404);exit('Not found');}
 header('Content-Type: application/octet-stream');
 header('Content-Disposition: attachment; filename="payment-proof-'.(int)$proofId.'.'.pathinfo($file,PATHINFO_EXTENSION).'"');
 header('Content-Length: '.filesize($path));readfile($path);exit;
}
if (($_GET['page']??null)==='reports') { require __DIR__.'/report_view.php'; exit; }
if (($_GET['page']??null)==='catalog') { require __DIR__.'/catalog_view.php'; exit; }
$filter=(string)($_GET['status']??'all');$statuses=['all','pending','confirmed','paid','cancelled','completed','rejected'];
if(!in_array($filter,$statuses,true))$filter='all';
$search=mb_substr(trim((string)($_GET['search']??'')),0,80);
$where=[];$params=[];
if($filter!=='all'){$where[]='r.status=?';$params[]=$filter;}
if($search!==''){$where[]='(r.reference_code LIKE ? OR r.customer_name LIKE ? OR r.customer_phone LIKE ?)';array_push($params,'%'.$search.'%','%'.$search.'%','%'.$search.'%');}
$whereSql=$where?' WHERE '.implode(' AND ',$where):'';
$q=$db->prepare('SELECT COUNT(*) FROM reservations r'.$whereSql);$q->execute($params);$totalRows=(int)$q->fetchColumn();
$pages=max(1,(int)ceil($totalRows/20));$page=min($pages,max(1,(int)($_GET['page']??1)));$offset=($page-1)*20;
$q=$db->prepare("SELECT r.id,r.reference_code,r.customer_name,r.customer_phone,r.event_type,r.start_at,r.end_at,r.status,r.total_amount,v.name AS venue_name,COALESCE(p.paid,0) AS paid FROM reservations r JOIN venues v ON v.id=r.venue_id LEFT JOIN (SELECT reservation_id,SUM(amount) paid FROM payments GROUP BY reservation_id) p ON p.reservation_id=r.id".$whereSql." ORDER BY r.created_at DESC,r.id DESC LIMIT 20 OFFSET ".$offset);
$q->execute($params);$bookings=$q->fetchAll();
$stats=['pending'=>0,'confirmed'=>0,'paid'=>0,'cancelled'=>0,'completed'=>0,'rejected'=>0];
foreach($db->query('SELECT status,COUNT(*) cnt FROM reservations GROUP BY status')->fetchAll() as $row){$stats[$row['status']]=(int)$row['cnt'];}
$collected=(float)$db->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn();
$detail=null;$history=[];$detailPayments=[];$paidSum=0.0;
$id=(int)($_GET['view']??0);
if($id>0){
 $q=$db->prepare('SELECT r.*,v.name AS venue_name,p.name AS package_name FROM reservations r JOIN venues v ON v.id=r.venue_id LEFT JOIN packages p ON p.id=r.package_id WHERE r.id=?');$q->execute([$id]);$detail=$q->fetch();
 if($detail){
  $q=$db->prepare('SELECT p.*,s.full_name AS recorder FROM payments p LEFT JOIN staff_users s ON s.id=p.recorded_by WHERE p.reservation_id=? ORDER BY p.id DESC');$q->execute([$id]);$detailPayments=$q->fetchAll();
  $paidSum=array_sum(array_map(static fn($p)=>(float)$p['amount'],$detailPayments));
  $q=$db->prepare('SELECT h.*,s.full_name AS actor FROM reservation_history h LEFT JOIN staff_users s ON s.id=h.performed_by WHERE h.reservation_id=? ORDER BY h.id DESC LIMIT 30');$q->execute([$id]);$history=$q->fetchAll();
 }
}
$payments=$db->query('SELECT p.id,p.reservation_id,p.amount,p.method,p.payment_reference,p.received_at,r.reference_code,r.customer_name FROM payments p JOIN reservations r ON r.id=p.reservation_id ORDER BY p.id DESC LIMIT 15')->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><link rel="icon" type="image/jpeg" href="../assets/images/resort/logo.jpg"><title>EventEase | Dashboard</title>
<style>
:root{--green:#173e34;--accent:#37745b;--bg:#f5f7f3;--line:#dfe6df;--soft:#6f8075;--ink:#263c32}*{box-sizing:border-box}html{scroll-behavior:smooth}body{font:13px/1.6 system-ui,sans-serif;margin:0;color:var(--ink);background:var(--bg)}a{color:var(--accent);text-decoration:none}h1,h2{font-family:Georgia,serif;font-weight:400;color:var(--green)}h1{font-size:35px;margin:0}h2{font-size:26px;margin:0 0 16px}h3{font-size:16px;margin:0 0 11px;color:var(--green)}.layout{display:grid;grid-template-columns:220px minmax(0,1fr)}aside{background:var(--green);color:white;min-height:100vh;padding:26px 19px;position:sticky;top:0;height:100vh}.logo{font-size:24px;color:white;font-weight:800;margin-bottom:40px}.nav{display:grid;gap:4px}.nav a{color:#d6e4d9;padding:10px 13px;border-radius:8px}.nav a:hover{background:#ffffff1c}.me{margin-top:60px;color:#bdd5c5;font-size:12px}.me strong{display:block;color:white}.main{padding:30px clamp(15px,3vw,47px);min-width:0}.headline{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:25px}.muted{color:var(--soft)}.small{font-size:11px}.section{margin-bottom:25px;background:white;border:1px solid var(--line);border-radius:14px;overflow:hidden;box-shadow:0 12px 32px #1e3c2506}.pad{padding:24px}.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin-bottom:25px}.metric{padding:21px;background:white;border:1px solid var(--line);border-radius:12px}.metric span{color:var(--soft);font-weight:700}.metric strong{display:block;font:normal 29px Georgia,serif;color:var(--green);margin-top:12px}.filter{display:flex;gap:10px;align-items:end;flex-wrap:wrap;padding:19px;background:#fbfcfa;border-top:1px solid var(--line)}.field{display:grid;gap:6px;min-width:0}.field label{font-weight:700;color:#4f665a;font-size:12px}.field input,.field select,.field textarea{padding:10px;border:1px solid #d5ded6;border-radius:8px;background:white;color:var(--ink);width:100%;font:inherit;font-size:13px;min-height:40px}.field textarea{min-height:82px;resize:vertical}.field:focus-within input,.field:focus-within select{outline:2px solid #d9efe1}.grow{flex:1;min-width:180px}.btn{background:var(--green);color:white;border:1px solid var(--green);border-radius:8px;font-weight:750;padding:10px 14px;display:inline-block;cursor:pointer;font:inherit}.btn.alt{background:white;color:var(--green);border-color:#c5d4c8}.btn.red{background:#a65143;border-color:#a65143}.btn:hover{filter:brightness(.94)}.scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:780px;text-align:left}th,td{padding:13px 15px;border-bottom:1px solid #edf0e9}th{color:var(--soft);background:#fafcf9;font-size:11px}td strong,td small{display:block}td small{color:var(--soft);font-size:11px}.badge{display:inline-block;border-radius:99px;padding:4px 9px;background:#e8ede8;color:#42604a;font-weight:750;text-transform:capitalize}.badge.pending{background:#fff0d4;color:#916321}.badge.confirmed,.badge.paid{background:#dcefe3;color:#216644}.badge.rejected,.badge.cancelled{background:#ffe6dc;color:#963f33}.cols{display:grid;grid-template-columns:1fr 1fr;gap:16px}.panel{border:1px solid var(--line);border-radius:12px;padding:17px;background:white}.panel form{display:grid;gap:12px}.panel p{margin:5px 0;color:var(--soft)}.pair{display:flex;justify-content:space-between;gap:12px;padding:5px 0}.pair strong{text-align:right}.actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px;margin-top:17px}.notice{border-radius:9px;padding:13px 16px;margin-bottom:20px;font-weight:700}.notice.success{background:#e0efe6;color:#226143}.notice.error{background:#ffebe1;color:#944630}.pages{padding:15px;display:flex;align-items:center;justify-content:flex-end;gap:14px}.account{display:grid;gap:12px}.actions .btn{justify-self:start}.footer{padding:20px;color:var(--soft)}.menu-button{display:none}.subheading{margin:25px 0 10px}.alert{background:#fff7e7;border:1px solid #eedfc1;border-radius:8px;padding:11px;font-size:12px;color:#7b633b}
@media(max-width:1050px){.cards{grid-template-columns:repeat(2,1fr)}.actions{grid-template-columns:1fr}}
@media(max-width:780px){.layout{display:block}aside{position:relative;height:auto;min-height:0;padding:14px 20px}.logo{margin:0;display:inline-block}.menu-button{display:inline-block;float:right;background:#ffffff20;color:white;border:1px solid #ffffff66;border-radius:6px;padding:8px}.nav{display:none;padding-top:14px}.nav.visible{display:grid}.me{display:none}.main{padding:22px 14px}.headline h1{font-size:30px}.cols{grid-template-columns:1fr}}
@media(max-width:520px){.cards{gap:8px}.metric{padding:14px}.metric strong{font-size:25px}.pad{padding:16px}.filter>.field{width:100%}.filter>.btn{flex:1;text-align:center}.headline{display:block}.headline a{display:inline-block;margin-top:12px}}
@media print{aside,.filter,button,.actions{display:none!important}.layout{display:block}.main{padding:0}.section{box-shadow:none}}
/* Independent admin dashboard sections */
.admin-routing .main>section[hidden]{display:none!important}
.admin-routing .main>section:focus{outline:none}
.nav a.active{background:#396d5c;color:white;box-shadow:inset 3px 0 #d4b980}
.layout>aside{overflow-y:auto;min-height:0}
.logo{margin-bottom:25px}.me{margin-top:26px}
.overview-links{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}
.overview-links a{display:block;padding:20px;background:#fff;border:1px solid var(--line);border-radius:12px;color:var(--green)}
.overview-links a:hover{border-color:#77a18c}.overview-links strong{display:block;font-size:15px}.overview-links small{display:block;color:var(--soft);margin-top:5px}
.detail-back{display:inline-block;margin:0 0 14px}
@media screen and (min-width:781px){.admin-routing body{overflow:hidden}.admin-routing .layout{height:100dvh;min-height:0;overflow:hidden}.admin-routing .layout>aside{height:100dvh}.admin-routing .main{height:100dvh;overflow-y:auto;overscroll-behavior:contain}}
@media(max-width:1050px){.overview-links{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:780px){.overview-links{grid-template-columns:1fr}.layout>aside{overflow:visible}.nav.visible+.me{display:block;margin:12px 0 0}.overview-links a{padding:15px}}

.payment-panel{grid-column:1/-1;max-width:720px;width:100%}
.payment-balance{display:flex;justify-content:space-between;align-items:center;gap:12px;background:#eef4ee;border-radius:9px;padding:11px 13px;margin:14px 0;font-size:13px}.payment-balance strong{font-size:18px;color:var(--green)}
.payment-extras{border:1px solid var(--line);border-radius:8px;padding:10px 12px}.payment-extras summary{cursor:pointer;font-weight:700;color:var(--green)}.payment-extras .field{margin-top:12px}.payment-history-links{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}

/* Final administrative layout polish */
.btn:focus-visible,.nav a:focus-visible,.field input:focus-visible,.field select:focus-visible,.payment-extras summary:focus-visible{outline:2px solid #b5975e;outline-offset:3px}
.btn{min-height:40px}.btn:disabled{opacity:.55;cursor:not-allowed}
.panel,.metric,.field{min-width:0}.pair{align-items:flex-start}.pair strong{overflow-wrap:anywhere;min-width:0}
.section .pad{padding:clamp(16px,2.1vw,24px)}
.actions>.panel{min-width:0}.payment-panel{max-width:none}
.payment-panel input[readonly]{background:#eef4ee;font-weight:700}
@media(max-width:780px){.cols,.actions{grid-template-columns:minmax(0,1fr)}.filter .grow{min-width:0}.pages{flex-wrap:wrap}.detail-back{margin-bottom:12px}.payment-balance{flex-wrap:wrap}.payment-history-links .btn{flex:1;text-align:center}}

</style></head><body><div class="layout"><aside><div class="logo"><img src="../assets/images/resort/logo.jpg" alt="Terraza Del Rio logo" width="42" height="42" style="width:42px;height:42px;object-fit:contain;border-radius:7px;vertical-align:middle;margin-right:9px">EventEase</div><button type="button" class="menu-button" id="menu" aria-controls="nav" aria-expanded="false">Menu ☰</button><nav class="nav" id="nav"><a href="#overview">Overview</a><a href="#reservations">Reservations</a><a href="#payments-records">Payment Records</a><a href="?page=reports">Reports &amp; Records</a><a href="?page=catalog">Resort Management</a><a href="#account">Admin Account</a><a href="../?from=admin" target="_blank" rel="noopener noreferrer">Customer Website ↗</a></nav><div class="me"><strong><?=e($staff['full_name'])?></strong><?=e(ucfirst($staff['role']))?> account<form method="post" style="margin-top:15px"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="logout"><button class="btn alt" type="submit">Sign out</button></form></div></aside><main class="main">
<?php if($notice):?><div class="notice <?=e($notice['kind'])?>" role="alert"><?=e($notice['info'])?></div><?php endif;?>
<section id="overview"><div class="headline"><div><div class="muted small">TERRA​ZA DEL RIO · ADMIN PORTAL</div><h1>Reservation dashboard</h1><div class="muted">Bookings, schedules and manually recorded payments</div></div></div>

<div class="cards"><div class="metric"><span>Pending requests</span><strong><?=$stats['pending']?></strong></div><div class="metric"><span>Confirmed events</span><strong><?=$stats['confirmed']+$stats['paid']?></strong></div><div class="metric"><span>Completed events</span><strong><?=$stats['completed']?></strong></div><div class="metric"><span>Received payments</span><strong style="font-size:23px"><?=price($collected)?></strong></div></div><div class="overview-links"><a href="#reservations"><strong>Reservations</strong><small>Review and manage booking requests.</small></a><a href="#payments-records"><strong>Payment Records</strong><small>View recorded customer payments.</small></a><a href="?page=reports"><strong>Reports &amp; Records</strong><small>Review monthly booking and collection summaries.</small></a><a href="?page=catalog"><strong>Resort Management</strong><small>Manage venues, packages, add-ons and schedules.</small></a></div></section>
<section class="section" id="reservations"><div class="pad"><h2>Reservation management</h2><div class="muted">Review each booking and open its details to approve, reject, reschedule, cancel, or complete.</div></div>
<form class="filter" method="get" action="index.php#reservations"><div class="field grow"><label for="search">Search customer or booking</label><input name="search" id="search" value="<?=e($search)?>" placeholder="Name, contact or reference"></div><div class="field"><label for="status">Status</label><select id="status" name="status"><?php foreach($statuses as $st):?><option value="<?=e($st)?>" <?=$st===$filter?'selected':''?>><?=e(ucfirst($st))?></option><?php endforeach;?></select></div><button class="btn" type="submit">Filter</button><a class="btn alt" href="index.php#reservations">Reset</a></form>
<div class="scroll"><table><thead><tr><th>Reference</th><th>Customer</th><th>Event</th><th>Venue & Schedule</th><th>Cost / Paid</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$bookings):?><tr><td colspan="7" class="muted">No reservations found.</td></tr><?php endif;?>
<?php foreach($bookings as $r):?><tr><td><strong><?=e($r['reference_code'])?></strong></td><td><strong><?=e($r['customer_name'])?></strong><small><?=e($r['customer_phone'])?></small></td><td><?=e($r['event_type'])?></td><td><strong><?=e($r['venue_name'])?></strong><small><?=e(date('M d, Y g:i A',strtotime($r['start_at'])))?> – <?=e(date('g:i A',strtotime($r['end_at'])))?></small></td><td><strong><?=price($r['total_amount'])?></strong><small>Paid <?=price($r['paid'])?></small></td><td><span class="badge <?=e($r['status'])?>"><?=e($r['status'])?></span></td><td><a class="btn alt" href="index.php?view=<?=(int)$r['id']?>#details">Manage</a></td></tr><?php endforeach;?></tbody></table></div>
<div class="pages"><?php if($page>1):?><a href="?page=<?=$page-1?>&status=<?=urlencode($filter)?>&search=<?=urlencode($search)?>#reservations">← Previous</a><?php endif;?><span>Page <?=$page?> / <?=$pages?></span><?php if($page<$pages):?><a href="?page=<?=$page+1?>&status=<?=urlencode($filter)?>&search=<?=urlencode($search)?>#reservations">Next →</a><?php endif;?></div></section>
<?php if($detail):?><section class="section" id="details"><div class="pad"><a class="btn alt detail-back" href="index.php#reservations">← Back to Reservations</a><h2>Manage <?=e($detail['reference_code'])?> <span class="badge <?=e($detail['status'])?>"><?=e($detail['status'])?></span></h2><div class="cols">
<div class="panel"><h3>Customer & Event</h3><div class="pair"><span>Customer</span><strong><?=e($detail['customer_name'])?></strong></div><div class="pair"><span>Contact</span><strong><?=e($detail['customer_phone'])?></strong></div><div class="pair"><span>Email</span><strong><?=e($detail['customer_email']?:'Not provided')?></strong></div><div class="pair"><span>Event</span><strong><?=e($detail['event_type'])?> (<?=(int)$detail['guest_count']?> guests)</strong></div><div class="pair"><span>Venue</span><strong><?=e($detail['venue_name'])?></strong></div><div class="pair"><span>Package</span><strong><?=e($detail['package_name']?:'Venue only')?></strong></div><div class="pair"><span>Date & Time</span><strong><?=e($detail['start_at'])?> — <?=e(substr($detail['end_at'],11,5))?></strong></div></div>
<div class="panel"><h3>Charges & Balance</h3><div class="pair"><span>Venue</span><strong><?=price($detail['venue_price'])?></strong></div><div class="pair"><span>Package</span><strong><?=price($detail['package_price'])?></strong></div><div class="pair"><span>Add-ons</span><strong><?=price($detail['addons_total'])?></strong></div><hr><div class="pair"><span>Total</span><strong><?=price($detail['total_amount'])?></strong></div><div class="pair"><span>Required deposit</span><strong><?=price($detail['down_payment_required'])?></strong></div><div class="pair"><span>Paid</span><strong><?=price($paidSum)?></strong></div><div class="pair"><span>Remaining</span><strong><?=price(max(0,(float)$detail['total_amount']-$paidSum))?></strong></div><?php if($detail['status_reason']):?><div class="alert">Reason: <?=e($detail['status_reason'])?></div><?php endif;?></div></div>
<div class="actions">
<?php if($detail['status']==='pending'):?>
<div class="panel"><h3>Down payment review</h3><?php $di=eeDepositInfo($db,$detail);$ds=$di['state'];if(in_array($ds,['review','expired'],true)):?><p><?=$ds==='expired'?'Payment window expired. Review the schedule before reopening.':'Review the request before sending payment instructions.'?></p><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="approve"><button class="btn" type="submit">Request GCash Down Payment</button></form><?php elseif($ds==='awaiting_payment'):?><p>Pending — awaiting GCash payment.</p><div class="pair"><span>Expected deposit</span><strong><?=price($detail['down_payment_required'])?></strong></div><div class="pair"><span>Deadline</span><strong><?=e($di['deadline'])?></strong></div><p>Customer submits reference in Track Reservation.</p><?php else:?><p>Pending — payment verification.</p><div class="pair"><span>GCash reference</span><strong><?=e($di['reference'])?></strong></div><div class="pair"><span>Expected amount</span><strong><?=price($detail['down_payment_required'])?></strong></div><div class="pair"><span>Recipient</span><strong><?=e($di['number'])?></strong></div><?php if($ds==='verification'):?><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="verify_deposit"><label><input type="checkbox" name="verified" value="1" required> I verified the actual GCash receipt and exact amount.</label><button class="btn" type="submit">Verify &amp; Confirm Booking</button></form><?php else:?><p class="alert">Event start has passed. Resolve payment with customer.</p><?php endif;?><?php endif;?></div>
<div class="panel"><h3>Reject request</h3><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="reject"><div class="field"><label>Reason</label><textarea name="note" minlength="3" maxlength="500" required></textarea></div><button class="btn red" type="submit">Reject Request</button></form></div>
<?php endif;?>
<?php if(in_array($detail['status'],['pending','confirmed','paid'],true)):?>
<div class="panel"><h3>Reschedule event</h3><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="reschedule"><div class="field"><label>New date</label><input type="date" name="date" min="<?=date('Y-m-d')?>" value="<?=e(substr($detail['start_at'],0,10))?>" required></div><div class="cols"><div class="field"><label>Start time</label><input type="time" name="start" value="<?=e(substr($detail['start_at'],11,5))?>" required></div><div class="field"><label>End time</label><input type="time" name="end" value="<?=e(substr($detail['end_at'],11,5))?>" required></div></div><button class="btn alt" type="submit">Save New Schedule</button></form></div>
<div class="panel"><h3>Cancel reservation</h3><form method="post" onsubmit="return confirm('Cancel booking? Any recorded payments will remain in the history.');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="cancel"><div class="field"><label>Reason</label><textarea name="note" minlength="3" maxlength="500" required></textarea></div><button class="btn red" type="submit">Cancel Booking</button></form></div>
<?php endif;?>
<?php if(in_array($detail['status'],['confirmed','paid'],true)):?>
<div class="panel"><h3>Finish event</h3><p>Available after the event ends and full payment is received.</p><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="complete"><button class="btn" type="submit">Mark Completed</button></form></div>
<?php endif;?>
<?php if(in_array($detail['status'],['confirmed','paid','completed'],true)&&$paidSum<(float)$detail['total_amount']):?>
<div class="panel payment-panel"><h3>Record remaining balance</h3><?php if(date('Y-m-d')<substr($detail['start_at'],0,10)):?><p>Final payment opens on <?=e(substr($detail['start_at'],0,10))?> at the resort.</p><?php else:?><p>Record the full balance received at the resort.</p><div class="payment-balance"><span>Remaining balance</span><strong><?=price(max(0,(float)$detail['total_amount']-$paidSum))?></strong></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="payment"><div class="field"><label for="payment-amount">Amount received (PHP)</label><input id="payment-amount" type="number" name="amount" readonly value="<?=e(number_format(max(0,(float)$detail['total_amount']-$paidSum),2,'.',''))?>"></div><div class="cols"><div class="field"><label for="payment-method">Payment method</label><select id="payment-method" name="method"><option value="cash">Cash</option><option value="e_wallet">E-wallet / GCash</option><option value="bank_transfer">Bank transfer</option><option value="other">Other</option></select></div><div class="field"><label for="payment-date">Date received</label><input id="payment-date" type="date" min="<?=e(substr($detail['start_at'],0,10))?>" max="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>" name="date" required></div></div><div class="field"><label for="payment-reference">Transaction reference (if applicable)</label><input id="payment-reference" name="reference" maxlength="120"><small class="muted">For non-cash payments, enter a reference number or attach proof below.</small></div><details class="payment-extras"><summary>Optional proof and notes</summary><div class="field"><label for="payment-proof">Attach proof (optional, up to 4 MB)</label><input id="payment-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf"></div><div class="field"><label for="payment-notes">Notes (optional)</label><textarea id="payment-notes" name="notes" maxlength="1000"></textarea></div></details><button class="btn" type="submit">Record Full Balance</button></form><?php endif;?></div>
<?php endif;?>
</div><h3 class="subheading" id="payments">Payment history for this booking</h3><div class="panel"><?php if(!$detailPayments):?><div class="muted">No payments recorded.</div><?php endif;?><?php foreach($detailPayments as $p):?><div class="pair"><span><?=e($p['received_at'])?> · <?=e($p['method'])?> · <?=e($p['payment_reference']?:'No reference')?> <?php if($p['proof_path']):?><a href="?proof=<?=(int)$p['id']?>">Download proof</a><?php endif;?></span><strong><?=price($p['amount'])?></strong></div><?php endforeach;?></div>
<h3 class="subheading">Activity history</h3><div class="panel"><?php foreach($history as $h):?><p><strong><?=e(str_replace('_',' ',$h['action']))?></strong> · <?=e($h['actor']?:'Customer')?> · <?=e($h['created_at'])?><br><span class="muted"><?=e($h['details'])?></span></p><?php endforeach;?></div></div></section><?php endif;?>
<section class="section" id="payments-records"><div class="pad"><h2>Payment Records</h2><p class="muted">Latest 15 received payments. To record a payment, open its reservation from Reservations. Full monthly history is available in Reports &amp; Records.</p><div class="payment-history-links"><a class="btn alt" href="#reservations">Go to Reservations</a><a class="btn alt" href="?page=reports">Monthly Reports</a></div></div><div class="scroll"><table><thead><tr><th>Reservation</th><th>Customer</th><th>Amount</th><th>Method</th><th>Reference</th><th>Received</th></tr></thead><tbody><?php if(!$payments):?><tr><td colspan="6">No payments yet.</td></tr><?php endif;?><?php foreach($payments as $p):?><tr><td><a href="index.php?view=<?=(int)$p['reservation_id']?>#details"><strong><?=e($p['reference_code'])?></strong></a></td><td><?=e($p['customer_name'])?></td><td><?=price($p['amount'])?></td><td><?=e($p['method'])?></td><td><?=e($p['payment_reference']?:'—')?></td><td><?=e($p['received_at'])?></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="section" id="account"><div class="pad"><h2>Admin Account</h2><div class="cols"><div class="panel"><h3>Change password</h3><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="password"><div class="field"><label>Current password</label><input type="password" name="old" autocomplete="current-password" required></div><div class="field"><label>New password (12+ characters)</label><input type="password" name="new" minlength="12" maxlength="128" autocomplete="new-password" required></div><div class="field"><label>Confirm new password</label><input type="password" name="confirm" minlength="12" maxlength="128" autocomplete="new-password" required></div><button class="btn" type="submit">Update Password</button></form></div>
</div>

<form method="post" style="margin-top:23px"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="logout"><button class="btn alt" type="submit">Sign Out</button></form></div></section>
</main></div>
<script src="../assets/js/admin-sections.js?v=3" defer></script>
</body></html>