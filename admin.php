<?php
require_once __DIR__.'/auth.php';
require_admin();
$pdo=db();
$msg='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['action']??'';
    if($act==='create'){
        $username=trim($_POST['username']??'');
        $pw=$_POST['password']??'';
        $name=trim($_POST['display_name']??'');
        $expires=$_POST['expires_at']?:null;
        $max=max(1,min(5,(int)($_POST['max_devices']??2)));
        if(strlen($username)<4 || strlen($pw)<8) $msg='아이디 4자 이상, 비밀번호 8자 이상으로 입력하세요.';
        else{
            try{
                $st=$pdo->prepare("INSERT INTO users(username,password_hash,display_name,role,active,expires_at,max_devices,session_version,created_at)
                                   VALUES(?,?,?,'user',1,?,?,0,?)");
                $st->execute([$username,password_hash($pw,PASSWORD_DEFAULT),$name,$expires,$max,date('Y-m-d H:i:s')]);
                $msg='계정을 생성했습니다.';
            }catch(Throwable $e){$msg='이미 사용 중인 아이디이거나 저장에 실패했습니다.';}
        }
    } elseif($act==='toggle'){
        $id=(int)$_POST['id'];
        $pdo->prepare("UPDATE users SET active=IF(active=1,0,1), session_version=session_version+1 WHERE id=? AND role='user'")->execute([$id]);
        $msg='사용상태를 변경했습니다.';
    } elseif($act==='devices'){
        $id=(int)$_POST['id'];
        $pdo->prepare("DELETE FROM user_devices WHERE user_id=?")->execute([$id]);
        $pdo->prepare("UPDATE users SET session_version=session_version+1 WHERE id=?")->execute([$id]);
        $msg='등록기기를 초기화하고 기존 로그인도 해제했습니다.';
    } elseif($act==='resetpw'){
        $id=(int)$_POST['id']; $pw=$_POST['new_password']??'';
        if(strlen($pw)>=8){
            $pdo->prepare("UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?")->execute([password_hash($pw,PASSWORD_DEFAULT),$id]);
            $msg='비밀번호를 변경하고 기존 로그인을 해제했습니다.';
        }
    }
}
$users=$pdo->query("SELECT u.*,(SELECT COUNT(*) FROM user_devices d WHERE d.user_id=u.id AND d.revoked=0) device_count
                    FROM users u WHERE role='user' ORDER BY id DESC")->fetchAll();
?>
<!doctype html>
<html lang="ko"><head><meta charset="utf-8"><title>쇼츠 스튜디오 관리자</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f6f8;font-family:Arial,"Noto Sans KR",sans-serif;color:#1b2430}
.top{height:66px;background:#fff;border-bottom:1px solid #e8ebef;display:flex;align-items:center;padding:0 28px;gap:14px}.top b{font-size:15px}.top a{margin-left:auto;color:#555;text-decoration:none}
main{max-width:1400px;margin:24px auto;padding:0 20px}.card{background:#fff;border:1px solid #e4e8ee;border-radius:18px;padding:22px;margin-bottom:18px}
h2{font-size:17px;margin:0 0 18px}.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}input{height:42px;border:1px solid #dfe3e9;border-radius:9px;padding:0 10px;width:100%}
button{border:0;border-radius:9px;padding:10px 13px;font-weight:800;cursor:pointer}.primary{background:#5147ed;color:#fff}.ghost{background:#f1f3f6;color:#505968}.danger{background:#fff0f0;color:#c43c3c}
table{width:100%;border-collapse:collapse;font-size:12px}th,td{border-bottom:1px solid #edf0f3;padding:11px 8px;text-align:left}th{color:#8791a0}.on{color:#168b54}.off{color:#c43c3c}.msg{padding:10px 12px;border-radius:10px;background:#eef5ff;margin-bottom:14px;font-size:12px}
.inline{display:flex;gap:6px;align-items:center}.inline input{width:130px;height:34px}
</style></head><body>
<div class="top"><b>부동산 쇼츠 스튜디오 · 관리자</b><a href="studio.php">제작기 보기</a><a href="logout.php">로그아웃</a></div>
<main>
<div class="card"><h2>중개업소 계정 발급</h2>
<?php if($msg):?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif;?>
<form method="post" class="grid"><input type="hidden" name="action" value="create">
<input name="display_name" placeholder="중개업소명" required>
<input name="username" placeholder="아이디" required>
<input name="password" type="password" placeholder="임시비밀번호 8자+" required>
<input name="expires_at" type="date">
<input name="max_devices" type="number" value="2" min="1" max="5" title="등록기기 수">
<button class="primary" style="grid-column:5">계정 발급</button></form></div>

<div class="card"><h2>사용자 관리</h2>
<table><thead><tr><th>중개업소</th><th>아이디</th><th>상태</th><th>사용기간</th><th>기기</th><th>마지막 로그인</th><th>관리</th></tr></thead><tbody>
<?php foreach($users as $u):?>
<tr>
<td><?=htmlspecialchars($u['display_name'])?></td><td><?=htmlspecialchars($u['username'])?></td>
<td class="<?=$u['active']?'on':'off'?>"><?=$u['active']?'사용중':'중지'?></td>
<td><?=htmlspecialchars($u['expires_at']?:'제한 없음')?></td>
<td><?=$u['device_count']?> / <?=$u['max_devices']?></td>
<td><?=htmlspecialchars($u['last_login_at']?:'-')?></td>
<td>
<div class="inline">
<form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="ghost"><?=$u['active']?'사용중지':'사용재개'?></button></form>
<form method="post"><input type="hidden" name="action" value="devices"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="ghost">기기초기화</button></form>
<form method="post" class="inline"><input type="hidden" name="action" value="resetpw"><input type="hidden" name="id" value="<?=$u['id']?>"><input name="new_password" type="password" placeholder="새 비밀번호"><button class="danger">PW변경</button></form>
</div>
</td>
</tr>
<?php endforeach;?></tbody></table></div>
</main></body></html>
