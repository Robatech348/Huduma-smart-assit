<?php
declare(strict_types=1);

/**
 * Outbox-based mailer. Every message is stored in `notifications` first, then
 * delivered with the configured driver. Failed messages stay in the outbox and
 * are retried by cron/run.php.
 */
final class Mailer
{
    public static function queue(string $to, string $subject, string $text, string $html, string $type, ?string $ref = null): int
    {
        q('INSERT INTO notifications (recipient, subject, body_text, body_html, type, related_ref) VALUES (?,?,?,?,?,?)',
            [$to, $subject, $text, $html, $type, $ref]);
        $id = (int)db()->lastInsertId();
        self::deliver($id);
        return $id;
    }

    public static function deliver(int $id): bool
    {
        $n = q_one('SELECT * FROM notifications WHERE id = ?', [$id]);
        if (!$n || in_array($n['status'], ['sent', 'logged'], true)) {
            return true;
        }
        $driver = config('mail.driver', 'log');
        try {
            if ($driver === 'smtp') {
                self::sendSmtp($n);
                $status = 'sent';
            } elseif ($driver === 'mail') {
                self::sendMail($n);
                $status = 'sent';
            } else {
                self::writeEml($n);
                $status = 'logged';
            }
            q('UPDATE notifications SET status = ?, sent_at = NOW(), attempts = attempts + 1, error = NULL WHERE id = ?', [$status, $id]);
            return true;
        } catch (Throwable $e) {
            q("UPDATE notifications SET status = 'failed', attempts = attempts + 1, error = ? WHERE id = ?",
                [mb_substr($e->getMessage(), 0, 500), $id]);
            error_log('Mail delivery failed for notification ' . $id . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Retry queued/failed messages (max 5 attempts). */
    public static function processQueue(int $limit = 50): array
    {
        $rows = q_all("SELECT id FROM notifications WHERE status IN ('queued','failed') AND attempts < 5 ORDER BY id LIMIT " . (int)$limit);
        $ok = 0;
        foreach ($rows as $r) {
            $ok += self::deliver((int)$r['id']) ? 1 : 0;
        }
        return ['processed' => count($rows), 'delivered' => $ok];
    }

    private static function buildMime(array $n, string &$headers): string
    {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $fromName = config('mail.from_name');
        $fromEmail = config('mail.from_email');
        $headers = implode("\r\n", [
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'Reply-To: ' . config('app.support_email', $fromEmail),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: HudumaSmartAssist',
        ]);
        return "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($n['body_text'])) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($n['body_html'])) . "\r\n"
            . "--{$boundary}--\r\n";
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function writeEml(array $n): void
    {
        $headers = '';
        $body = self::buildMime($n, $headers);
        $dir = APP_ROOT . '/storage/mail';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = sprintf('%s/%s_%05d_%s.eml', $dir, date('Ymd_His'), $n['id'], preg_replace('/[^a-z_]/', '', $n['type']));
        $content = 'To: ' . $n['recipient'] . "\r\nSubject: " . self::encodeHeader($n['subject']) . "\r\nDate: " . date('r') . "\r\n" . $headers . "\r\n\r\n" . $body;
        if (file_put_contents($file, $content) === false) {
            throw new RuntimeException('Could not write ' . $file);
        }
    }

    private static function sendMail(array $n): void
    {
        $headers = '';
        $body = self::buildMime($n, $headers);
        if (!mail($n['recipient'], self::encodeHeader($n['subject']), $body, $headers)) {
            throw new RuntimeException('mail() returned false. Check sendmail settings in php.ini.');
        }
    }

    private static function sendSmtp(array $n): void
    {
        $c = config('mail.smtp');
        $host = $c['host'];
        $port = (int)$c['port'];
        $enc = strtolower((string)($c['encryption'] ?? ''));
        $timeout = (int)($c['timeout'] ?? 15);
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, $timeout);

        $read = function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = function (string $command, array $expect) use ($fp, $read): string {
            if ($command !== '') {
                fwrite($fp, $command . "\r\n");
            }
            $resp = $read();
            $code = (int)substr($resp, 0, 3);
            if (!in_array($code, $expect, true)) {
                $shown = str_starts_with($command, 'AUTH') || strlen($command) > 60 ? '[credentials]' : $command;
                throw new RuntimeException("SMTP error after '{$shown}': " . trim($resp));
            }
            return $resp;
        };

        $hostname = gethostname() ?: 'localhost';
        $cmd('', [220]);
        $cmd('EHLO ' . $hostname, [250]);
        if ($enc === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            $cmd('EHLO ' . $hostname, [250]);
        }
        if (!empty($c['username'])) {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($c['username']), [334]);
            $cmd(base64_encode($c['password']), [235]);
        }
        $cmd('MAIL FROM:<' . config('mail.from_email') . '>', [250]);
        $cmd('RCPT TO:<' . $n['recipient'] . '>', [250, 251]);
        $cmd('DATA', [354]);

        $headers = '';
        $body = self::buildMime($n, $headers);
        $message = 'To: ' . $n['recipient'] . "\r\n"
            . 'Subject: ' . self::encodeHeader($n['subject']) . "\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $hostname . ">\r\n"
            . $headers . "\r\n\r\n" . $body;
        // Dot-stuffing per RFC 5321.
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($fp, $message . "\r\n.\r\n");
        $cmd('', [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
    }
}
