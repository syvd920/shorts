<?php
require_once __DIR__.'/config.php';
$pdo=db();
$count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
if($count>0) exit('관리자 계정이 이미 존재합니다. setup.php 파일을 서버에서 삭제하세요.');
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $id=trim($_POST['username']??'');
  $pw=$_POST['password']??'';
  if(strlen($id)<4 || strlen($pw)<8) $msg='아이디 4자 이상, 비밀번호 8자 이상으로 입력하세요.';
  else{
    $st=$pdo->prepare("INSERT INTO users(username,password_hash,display_name,role,active,max_devices,session_version,created_at)
                       VALUES(?,?,?,?,1,2,0,?)");
    $st->execute([$id,password_hash($pw,PASSWORD_DEFAULT),'최고관리자','admin',date('Y-m-d H:i:s')]);
    exit('관리자 생성 완료. 보안을 위해 setup.php 파일을 삭제한 뒤 index.php에서 로그인하세요.');
  }
}
?>
<!doctype html><meta charset="utf-8"><style>body{font-family:Arial;padding:40px}input{display:block;margin:10px 0;padding:10px;width:300px}</style>
<h2>최초 관리자 생성</h2><?php if($msg)echo '<p>'.$msg.'</p>';?>
<form method="post"><input name="username" placeholder="관리자 아이디" required><input name="password" type="password" placeholder="비밀번호 8자 이상" required><button>관리자 생성</button></form>
