<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
try {
    $db=eventease_pdo();
    if((int)$db->query('SELECT COUNT(*) FROM staff_users')->fetchColumn()>0){
        echo "Existing accounts preserved. Initial setup is not applicable.\n";
        exit(0);
    }
    $username=trim((string)readline('Initial admin username: '));
    $email=trim((string)readline('Initial admin email: '));
    if(!preg_match('/^[A-Za-z0-9_.-]{3,60}$/D',$username)||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>191){
        fwrite(STDERR,"Invalid username or email.\n");exit(1);
    }
    $temporary=bin2hex(random_bytes(16)).'!A';
    $db->prepare("INSERT INTO staff_users(full_name,username,email,password_hash,role,is_active) VALUES (?,?,?,?,'admin',1)")
       ->execute(['EventEase Administrator',$username,$email,password_hash($temporary,PASSWORD_DEFAULT)]);
    echo "Initial admin created.\nUsername: ".$username."\nTemporary password: ".$temporary."\nChange the password after signing in. Keep this output private.\n";
}catch(Throwable $e){fwrite(STDERR,"Initial setup could not complete. Check the private database configuration.\n");exit(1);}
