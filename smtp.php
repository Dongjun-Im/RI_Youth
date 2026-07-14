<?php
require_once __DIR__ . '/config.php';

// lib.php 없이 단독 포함될 때를 대비한 폴백 (설정값 = config.php 상수)
if (!function_exists('mail_cfg')) {
    function mail_cfg($k) { return defined($k) ? constant($k) : null; }
}

/**
 * 외부 라이브러리 없이 순수 PHP 소켓으로 SMTP 메일 발송.
 * Gmail(465 SSL / 587 STARTTLS) 등 표준 SMTP 서버 지원.
 *
 * @param string $toEmail  받는사람 이메일
 * @param string $toName   받는사람 이름
 * @param string $subject  제목 (UTF-8)
 * @param string $htmlBody 본문 (HTML, UTF-8)
 * @param string|null $err 실패 시 에러 메시지가 담김
 * @return bool 성공 여부
 */
function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, ?string &$err = null): bool {
    // 발신 설정: 관리자 설정(settings) 우선, 없으면 config.php 상수
    $dryRun   = (bool)(int)mail_cfg('MAIL_DRY_RUN');
    $host     = (string)mail_cfg('SMTP_HOST');
    $port     = (int)mail_cfg('SMTP_PORT');
    $user     = (string)mail_cfg('SMTP_USER');
    $pass     = (string)mail_cfg('SMTP_PASS');
    $from     = (string)mail_cfg('SMTP_FROM');
    $fromName = (string)mail_cfg('SMTP_FROM_NAME');

    if ($dryRun) {
        @file_put_contents(__DIR__ . '/mail_dryrun.log',
            date('c') . " TO={$toEmail} SUBJ={$subject}\n" . $htmlBody . "\n\n",
            FILE_APPEND);
        return true;
    }

    $useSSL      = ($port == 465);
    $useSTARTTLS = ($port == 587);

    $transport = $useSSL ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $fp = @stream_socket_client($transport, $errno, $errstr, 20,
        STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $err = "연결 실패: {$errstr} ({$errno})"; return false; }
    stream_set_timeout($fp, 20);

    // 서버 응답 읽기 (여러 줄 대응)
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // 4번째 문자가 공백이면 마지막 줄
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $send = function (string $cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };
    // 명령 실행 후 응답 코드 확인
    $expect = function (int $code, string $stage) use ($read, &$err): bool {
        $resp = $read();
        if ((int)substr($resp, 0, 3) !== $code) {
            $err = "{$stage} 실패: " . trim($resp);
            return false;
        }
        return true;
    };

    try {
        if (!$expect(220, '접속'))                 { fclose($fp); return false; }

        $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$expect(250, 'EHLO'))                 { fclose($fp); return false; }

        if ($useSTARTTLS) {
            $send('STARTTLS');
            if (!$expect(220, 'STARTTLS'))         { fclose($fp); return false; }
            if (!stream_socket_enable_crypto($fp, true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $err = 'TLS 협상 실패'; fclose($fp); return false;
            }
            $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            if (!$expect(250, 'EHLO(TLS)'))        { fclose($fp); return false; }
        }

        // AUTH LOGIN
        $send('AUTH LOGIN');
        if (!$expect(334, 'AUTH'))                 { fclose($fp); return false; }
        $send(base64_encode($user));
        if (!$expect(334, '사용자 인증'))          { fclose($fp); return false; }
        $send(base64_encode($pass));
        if (!$expect(235, '비밀번호 인증'))        { fclose($fp); return false; }

        $send('MAIL FROM:<' . $from . '>');
        if (!$expect(250, 'MAIL FROM'))            { fclose($fp); return false; }

        // RCPT 응답은 250 또는 251 모두 허용
        $send('RCPT TO:<' . $toEmail . '>');
        $rcptResp = $read();
        $rcptCode = (int)substr($rcptResp, 0, 3);
        if ($rcptCode !== 250 && $rcptCode !== 251) {
            $err = 'RCPT TO 실패: ' . trim($rcptResp);
            fclose($fp); return false;
        }

        // DATA
        $send('DATA');
        if (!$expect(354, 'DATA'))                 { fclose($fp); return false; }

        $fromHeader = mime_name($fromName) . ' <' . $from . '>';
        $toHeader   = mime_name($toName) . ' <' . $toEmail . '>';
        $subjHeader = mime_subject($subject);
        $date       = date('r');

        $headers  = "From: {$fromHeader}\r\n";
        $headers .= "To: {$toHeader}\r\n";
        $headers .= "Subject: {$subjHeader}\r\n";
        $headers .= "Date: {$date}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";

        // 본문 내 "." 로 시작하는 줄은 ".." 로 escape (SMTP dot-stuffing)
        $body = preg_replace('/^\./m', '..', $htmlBody);

        $send($headers . "\r\n" . $body . "\r\n.");
        if (!$expect(250, '본문 전송'))            { fclose($fp); return false; }

        $send('QUIT');
        fclose($fp);
        return true;
    } catch (\Throwable $e) {
        $err = $e->getMessage();
        if (is_resource($fp)) fclose($fp);
        return false;
    }
}

/** 이름을 UTF-8 MIME 인코딩 */
function mime_name(string $name): string {
    if (preg_match('/^[\x20-\x7E]*$/', $name)) return '"' . $name . '"';
    return '=?UTF-8?B?' . base64_encode($name) . '?=';
}
/** 제목을 UTF-8 MIME 인코딩 */
function mime_subject(string $subject): string {
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}
