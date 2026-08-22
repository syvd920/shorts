<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    session_start();
}

function require_user(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }

    $pdo = db();
    $st = $pdo->prepare("SELECT id, username, display_name, role, active, expires_at, session_version
                         FROM users WHERE id=? LIMIT 1");
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();

    $invalid = !$u
        || !(int)$u['active']
        || ((int)$u['session_version'] !== (int)($_SESSION['session_version'] ?? -1))
        || (!empty($u['expires_at']) && strtotime($u['expires_at']) < time());

    if ($invalid) {
        session_unset();
        session_destroy();
        header('Location: index.php?expired=1');
        exit;
    }

    $_SESSION['username'] = $u['username'];
    $_SESSION['display_name'] = $u['display_name'] ?: $u['username'];
    $_SESSION['role'] = $u['role'];
}

function require_admin(): void {
    require_user();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('관리자 권한이 필요합니다.');
    }
}

function clean_device_id(string $v): string {
    return substr(preg_replace('/[^a-zA-Z0-9._:-]/', '', $v), 0, 120);
}

function login_user(string $username, string $password, string $deviceId): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT * FROM users WHERE username=? FOR UPDATE");
        $st->execute([$username]);
        $u = $st->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            throw new RuntimeException('아이디 또는 비밀번호가 올바르지 않습니다.');
        }
        if (!(int)$u['active']) throw new RuntimeException('사용이 중지된 계정입니다.');
        if (!empty($u['expires_at']) && strtotime($u['expires_at']) < time()) {
            throw new RuntimeException('사용기간이 종료된 계정입니다.');
        }

        $deviceId = clean_device_id($deviceId);
        if ($deviceId === '') throw new RuntimeException('기기 확인에 실패했습니다.');

        $ds = $pdo->prepare("SELECT id, revoked FROM user_devices WHERE user_id=? AND device_id=? LIMIT 1");
        $ds->execute([$u['id'], $deviceId]);
        $device = $ds->fetch();

        if ($device && (int)$device['revoked']) {
            throw new RuntimeException('관리자에 의해 차단된 기기입니다.');
        }

        if (!$device) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM user_devices WHERE user_id=? AND revoked=0");
            $cnt->execute([$u['id']]);
            if ((int)$cnt->fetchColumn() >= (int)$u['max_devices']) {
                throw new RuntimeException('등록 가능한 기기 수를 초과했습니다. 관리자에게 기기 초기화를 요청하세요.');
            }
            $ins = $pdo->prepare("INSERT INTO user_devices(user_id, device_id, user_agent, first_seen_at, last_seen_at)
                                  VALUES(?,?,?,?,?)");
            $now = date('Y-m-d H:i:s');
            $ins->execute([$u['id'], $deviceId, substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,255), $now, $now]);
        } else {
            $up = $pdo->prepare("UPDATE user_devices SET last_seen_at=?, user_agent=? WHERE id=?");
            $up->execute([date('Y-m-d H:i:s'), substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,255), $device['id']]);
        }

        // 새 로그인 시 session_version 증가 → 기존 로그인 세션은 다음 요청부터 자동 무효화.
        $newVersion = (int)$u['session_version'] + 1;
        $up = $pdo->prepare("UPDATE users SET session_version=?, last_login_at=?, last_login_ip=? WHERE id=?");
        $up->execute([$newVersion, date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'] ?? '', $u['id']]);

        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['username'] = $u['username'];
        $_SESSION['display_name'] = $u['display_name'] ?: $u['username'];
        $_SESSION['role'] = $u['role'];
        $_SESSION['session_version'] = $newVersion;
        return ['ok'=>true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false, 'message'=>$e->getMessage()];
    }
}
