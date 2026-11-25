<?php
declare(strict_types=1);
function catalogText(string $key,int $max,bool $required=false): string {
 $v=$_POST[$key]??'';if(!is_string($v))throw new RuntimeException('Invalid field.');
 $v=trim($v);if(mb_strlen($v)>$max||($required&&$v===''))throw new RuntimeException('Check the required fields and length limits.');return $v;
}
function catalogPrice(string $key): string {
 $v=catalogText($key,20,true);if(!preg_match('/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/D',$v))throw new RuntimeException('Enter a valid non-negative price.');return $v;
}
function catalogSetting(PDO $db,string $key,string $value): void{
 $db->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
}
function catalogSave(PDO $db): void{
 $kind=catalogText('kind',20,true);$id=filter_var($_POST['record_id']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
 if($id===false)throw new RuntimeException('Invalid record.');
 if($kind==='settings'){
  $deposit=catalogPrice('deposit');if((float)$deposit>100)throw new RuntimeException('Deposit must be between 0 and 100 percent.');
  $mode=isset($_POST['catalog_mode'])?catalogText('catalog_mode',20,true):null;if($mode!==null&&!in_array($mode,['sample','verified'],true))throw new RuntimeException('Select a valid catalog mode.');$gcash=catalogText('resort_gcash',11);if($gcash!==''&&!preg_match('/^09[0-9]{9}$/D',$gcash))throw new RuntimeException('GCash number must be 11 digits starting with 09.');if((float)$deposit<=0||(float)$deposit>=100)throw new RuntimeException('Down payment must be greater than 0 and less than 100 percent.');$email=catalogText('resort_email',191);if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid contact email.');$hours=catalogText('resort_hours',300);$phone=catalogText('resort_phone',80);if($phone!==''&&!preg_match('/^\+?[0-9][0-9 ()-]{5,24}$/D',$phone))throw new RuntimeException('Enter a valid contact number.');$phone2=catalogText('resort_phone_alt',80);if($phone2!==''&&!preg_match('/^\+?[0-9][0-9 ()-]{5,24}$/D',$phone2))throw new RuntimeException('Enter a valid alternate contact number.');$address=catalogText('resort_address',500);$fb=catalogText('resort_facebook',500);$map=catalogText('resort_map',500);
  foreach([$fb,$map] as $link){if($link!==''&&(!filter_var($link,FILTER_VALIDATE_URL)||parse_url($link,PHP_URL_SCHEME)!=='https'))throw new RuntimeException('Use a valid HTTPS link.');}
  $db->beginTransaction();try{foreach(['down_payment_percent'=>$deposit,'resort_gcash'=>$gcash,'resort_phone'=>$phone,'resort_phone_alt'=>$phone2,'resort_email'=>$email,'resort_hours'=>$hours,'resort_address'=>$address,'resort_facebook'=>$fb,'resort_map'=>$map] as $k=>$v)catalogSetting($db,$k,$v);if($mode!==null)catalogSetting($db,'catalog_mode',$mode);$db->commit();}catch(Throwable $ex){if($db->inTransaction())$db->rollBack();throw $ex;}
  return;
 }
 if(!in_array($kind,['venue','package','addon'],true))throw new RuntimeException('Invalid catalog type.');
 $name=catalogText('name',150,true);$description=catalogText('description',2000);$price=catalogPrice('price');$active=isset($_POST['active'])?1:0;
 $db->beginTransaction();try{
 if($kind==='venue'){
  $capacity=filter_var($_POST['capacity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
  if(!$capacity)throw new RuntimeException('Enter capacity from 1 to 10,000.');
  $image=catalogText('image',40);if(!in_array($image,['','pool-day.jpg','pool-overview.jpg','function-hall.jpg','pavilion.jpg','pool-night.jpg','reception-setup.jpg','outdoor-event.jpg'],true))throw new RuntimeException('Choose an available resort photo.');
  // Serialize catalog availability changes; avoid an empty public venue list.
  $all=$db->query('SELECT id,is_active FROM venues ORDER BY id FOR UPDATE')->fetchAll();
  if($id){$existing=array_values(array_filter($all,fn($v)=>(int)$v['id']===$id));if(!$existing)throw new RuntimeException('Venue not found.');
   if(!$active&&(int)$existing[0]['is_active']===1&&count(array_filter($all,fn($v)=>(int)$v['is_active']===1))===1)throw new RuntimeException('Keep at least one active venue.');
   $db->prepare('UPDATE venues SET name=?,description=?,capacity=?,base_price=?,is_active=? WHERE id=?')->execute([$name,$description,$capacity,$price,$active,$id]);
  }else{$slug='venue-'.bin2hex(random_bytes(6));$db->prepare('INSERT INTO venues(name,slug,description,capacity,base_price,is_active) VALUES (?,?,?,?,?,?)')->execute([$name,$slug,$description,$capacity,$price,$active]);$id=(int)$db->lastInsertId();}
  catalogSetting($db,'venue_photo_'.$id,$image);
 }elseif($kind==='package'){
  if($id){$q=$db->prepare('SELECT id FROM packages WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('Package not found.');$db->prepare('UPDATE packages SET name=?,description=?,package_price=?,is_active=? WHERE id=?')->execute([$name,$description,$price,$active,$id]);}
  else{$venue=filter_var($_POST['venue_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$q=$db->prepare('SELECT id FROM venues WHERE id=?');$q->execute([$venue]);if(!$q->fetchColumn())throw new RuntimeException('Choose an existing venue.');$db->prepare('INSERT INTO packages(venue_id,name,description,package_price,is_active) VALUES (?,?,?,?,?)')->execute([$venue,$name,$description,$price,$active]);}
 }else{
  if($id){$q=$db->prepare('SELECT id FROM addons WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('Add-on not found.');$db->prepare('UPDATE addons SET name=?,description=?,unit_price=?,is_active=? WHERE id=?')->execute([$name,$description,$price,$active,$id]);}
  else{$db->prepare('INSERT INTO addons(name,description,unit_price,is_active) VALUES (?,?,?,?)')->execute([$name,$description,$price,$active]);}
 }
 $db->commit();
 }catch(Throwable $ex){if($db->inTransaction())$db->rollBack();throw $ex;}
}
