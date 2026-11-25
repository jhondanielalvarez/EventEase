<?php
declare(strict_types=1);
function eeReviewDeposit(PDO $db,array $r,array $staff,string $action): array {
 $id=(int)$r['id'];$venue=(int)$r['venue_id'];
 if($r['status']!=='pending'||strtotime($r['start_at'])<=time())throw new RuntimeException('Only future pending bookings can be reviewed.');
 $gcash=eeGcash($db,true);$info=eeDepositInfo($db,$r);
 if(conflicts($db,$venue,$id,$r['start_at'],$r['end_at']))throw new RuntimeException('Double booking blocked: another reservation holds this schedule.');
 $deposit=(int)round((float)$r['down_payment_required']*100);
 if($action==='approve'){
  if(in_array($info['state'],['awaiting_payment','verification','verification_expired'],true))throw new RuntimeException('Down payment workflow is already active.');
  if(!$deposit){$db->prepare("UPDATE reservations SET status='confirmed',approved_by=?,approved_at=NOW(),status_reason=NULL WHERE id=?")->execute([$staff['id'],$id]);return ['approve','confirmed','Confirmed without required deposit'];}
  if(!$gcash)throw new RuntimeException('Configure the official resort GCash number in Resort Settings first.');
  if($deposit>=(int)round((float)$r['total_amount']*100))throw new RuntimeException('Deposit must be less than total.');
  $db->prepare('UPDATE reservations SET approved_by=?,approved_at=NOW(),status_reason=NULL WHERE id=?')->execute([$staff['id'],$id]);
  return ['deposit_requested','pending',json_encode(['number'=>$gcash],JSON_THROW_ON_ERROR)];
 }
 if($info['state']!=='verification'||($_POST['verified']??'')!=='1')throw new RuntimeException('Verify the submitted reference and actual GCash receipt first.');
 if(eeReferenceUsed($db,$info['reference'],$id))throw new RuntimeException('Transaction reference already used.');
 $q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id=?');$q->execute([$id]);if((float)$q->fetchColumn()>0)throw new RuntimeException('Payment already exists. Review the reservation.');
 $db->prepare("INSERT INTO payments(reservation_id,recorded_by,amount,method,payment_reference,notes,received_at) VALUES (?,?,?,'e_wallet',?,'Verified GCash down payment',NOW())")->execute([$id,$staff['id'],amount($deposit),$info['reference']]);
 $db->prepare("UPDATE reservations SET status='confirmed',approved_by=?,approved_at=NOW(),status_reason=NULL WHERE id=?")->execute([$staff['id'],$id]);
 return ['verify_deposit','confirmed','Verified GCash down payment'];
}
function eeSubmitReference(PDO $db,array $r,string $phone,string $ref): void {
 if(!preg_match('/^[0-9]{8,25}$/D',$ref))throw new RuntimeException('Enter the numeric GCash reference (8–25 digits).');
 $db->beginTransaction();
 try{
  $q=$db->prepare('SELECT id FROM venues WHERE id=? FOR UPDATE');$q->execute([$r['venue_id']]);$q->fetchColumn();
  $q=$db->prepare('SELECT * FROM reservations WHERE id=? AND customer_phone=? FOR UPDATE');$q->execute([$r['id'],$phone]);$fresh=$q->fetch();
  if(!$fresh||$fresh['venue_id']!=$r['venue_id'])throw new RuntimeException('Reservation changed. Check again.');
  eeGcash($db,true);$info=eeDepositInfo($db,$fresh);
  if($info['state']!=='awaiting_payment')throw new RuntimeException('Reference submission requires an active down payment window.');
  if(eeReferenceUsed($db,$ref,(int)$r['id']))throw new RuntimeException('This reference is already used.');
  $db->prepare("INSERT INTO reservation_history(reservation_id,action,from_status,to_status,details) VALUES (?,'gcash_reference_submitted','pending','pending',?)")->execute([$r['id'],$ref]);
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
