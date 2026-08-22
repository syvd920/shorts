<?php
require_once __DIR__.'/auth.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: '.(($_SESSION['role'] ?? '') === 'admin' ? 'admin.php' : 'studio.php'));
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = login_user(trim($_POST['username'] ?? ''), $_POST['password'] ?? '', $_POST['device_id'] ?? '');
    if ($r['ok']) {
        header('Location: '.(($_SESSION['role'] ?? '') === 'admin' ? 'admin.php' : 'studio.php'));
        exit;
    }
    $error = $r['message'] ?? '로그인에 실패했습니다.';
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>부동산 쇼츠 스튜디오</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,"Noto Sans KR",sans-serif;background:#f5f6f8;color:#18202d}
.login{width:420px;margin:110px auto;background:#fff;border:1px solid #e7e9ee;border-radius:22px;padding:34px;box-shadow:0 22px 60px rgba(20,28,45,.08)}
.logo{width:42px;height:42px;border-radius:12px;background:#172131;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900}
h1{font-size:22px;margin:18px 0 6px}p{font-size:12px;color:#8b94a3;margin:0 0 26px}
label{display:block;font-size:12px;font-weight:800;margin:15px 0 7px}
input{width:100%;height:46px;border:1px solid #dfe3e9;border-radius:11px;padding:0 13px;font-size:14px}
button{width:100%;height:48px;border:0;border-radius:11px;background:#5147ed;color:#fff;font-weight:900;font-size:14px;margin-top:22px;cursor:pointer}
.err{background:#fff1f1;color:#b83333;border:1px solid #ffd7d7;border-radius:10px;padding:10px 12px;font-size:12px;margin:12px 0}
</style>
</head>
<body>
<form class="login" method="post">
<div class="logo">R</div>
<h1>부동산 쇼츠 스튜디오</h1>
<p>관리자에게 부여받은 계정으로 로그인하세요.</p>
<?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif; ?>
<label>아이디</label><input name="username" autocomplete="username" required>
<label>비밀번호</label><input name="password" type="password" autocomplete="current-password" required>
<input type="hidden" name="device_id" id="device_id">
<button>로그인</button>
</form>
<script>
(function(){
  let id=localStorage.getItem('shorts_device_id');
  if(!id){id=(crypto.randomUUID?crypto.randomUUID():'dev-'+Date.now()+'-'+Math.random().toString(36).slice(2));localStorage.setItem('shorts_device_id',id)}
  document.getElementById('device_id').value=id;
})();
</script>
</body></html>
