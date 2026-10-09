<?php
declare(strict_types=1);

namespace TimeTracker;

/**
 * A small dependency-free SMTP client for administrator notifications: plain, STARTTLS or implicit TLS, optional
 * AUTH PLAIN / LOGIN, UTF-8 subject and body. Certificates are always verified.
 */
final class Smtp
{
    /** @var resource|null */
    private $fp = null;
    private string $lastResponse = '';

    /** @param array{host: string, port: int, encryption: string, username: string, password: string, from_email: string, from_name: string, timeout?: int} $cfg */
    public function __construct(private array $cfg)
    {
    }

    /** @param string[] $to @return array{ok: bool, error: ?string} */
    public function send(array $to, string $subject, string $body): array
    {
        try {
            $this->connect();
            $this->deliver($to, $subject, $body);
            $this->command('QUIT', [221, 250]);
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            if (is_resource($this->fp)) {
                @fclose($this->fp);
            }
            $this->fp = null;
        }
    }

    private function connect(): void
    {
        $enc = $this->cfg['encryption'];
        $host = $this->cfg['host'];
        $port = $this->cfg['port'];
        $timeout = (int) ($this->cfg['timeout'] ?? 10);
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException(t('Could not connect to {host}:{port} ({error}).', ['host' => $host, 'port' => $port, 'error' => trim($errstr) ?: (string) $errno]));
        }
        stream_set_timeout($fp, $timeout);
        $this->fp = $fp;
        $this->expect([220]);
        $domain = preg_replace('/[^A-Za-z0-9.\-]/', '', to_str($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        $this->command('EHLO ' . $domain, [250]);
        if ($enc === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException(t('The server did not accept the STARTTLS upgrade.'));
            }
            $this->command('EHLO ' . $domain, [250]);
        }
        if ($this->cfg['username'] !== '') {
            if (stripos($this->lastResponse, 'AUTH') !== false && stripos($this->lastResponse, 'PLAIN') !== false) {
                $this->command('AUTH PLAIN ' . base64_encode("\0" . $this->cfg['username'] . "\0" . $this->cfg['password']), [235]);
            } else {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($this->cfg['username']), [334]);
                $this->command(base64_encode($this->cfg['password']), [235]);
            }
        }
    }

    private function deliver(array $to, string $subject, string $body): void
    {
        $from = $this->cfg['from_email'];
        $this->command('MAIL FROM:<' . self::addr($from) . '>', [250]);
        foreach ($to as $rcpt) {
            $this->command('RCPT TO:<' . self::addr($rcpt) . '>', [250, 251]);
        }
        $this->command('DATA', [354]);
        $name = trim(str_replace(["\r", "\n", '"'], '', $this->cfg['from_name']));
        $headers = [
            'From: ' . ($name !== '' ? self::encodeHeader($name) . ' ' : '') . '<' . self::addr($from) . '>',
            'To: ' . implode(', ', array_map(static fn($a) => '<' . self::addr($a) . '>', $to)),
            'Subject: ' . self::encodeHeader($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (preg_replace('/[^A-Za-z0-9.\-]/', '', explode('@', $from)[1] ?? 'localhost') ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");
        $payload = preg_replace('/^\./m', '..', $payload); // dot-stuffing (base64 never starts a line with "." but headers might)
        $this->write($payload . "\r\n.");
        $this->expect([250]);
    }

    /** An address with anything that could inject protocol commands or headers stripped. */
    private static function addr(string $a): string
    {
        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(t('"{address}" is not a valid email address.', ['address' => $a]));
        }
        return $a;
    }

    private static function encodeHeader(string $s): string
    {
        $s = str_replace(["\r", "\n"], ' ', $s);
        return preg_match('/^[\x20-\x7E]*$/', $s) ? $s : '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    private function write(string $data): void
    {
        if (@fwrite($this->fp, $data . "\r\n") === false) {
            throw new \RuntimeException(t('The connection to the mail server was lost.'));
        }
    }

    /** @param int[] $ok accepted reply codes */
    private function command(string $line, array $ok): void
    {
        $this->write($line);
        $this->expect($ok);
    }

    /** Reads a (possibly multi-line) reply and checks its code. */
    private function expect(array $ok): void
    {
        $text = '';
        do {
            $line = @fgets($this->fp, 1024);
            if ($line === false) {
                throw new \RuntimeException(t('The mail server did not answer (timeout).'));
            }
            $text .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $this->lastResponse = $text;
        $code = (int) substr($text, 0, 3);
        if (!in_array($code, $ok, true)) {
            throw new \RuntimeException(t('The mail server replied: {reply}', ['reply' => trim(preg_replace('/\s+/', ' ', $text))]));
        }
    }

    /** Builds the client from the saved settings, or null when mail is not configured. */
    public static function fromSettings(): ?self
    {
        if (Settings::get('mail.host') === '' || !filter_var(Settings::get('mail.from_email'), FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return new self([
            'host'       => Settings::get('mail.host'),
            'port'       => max(1, Settings::int('mail.port')),
            'encryption' => in_array(Settings::get('mail.encryption'), ['tls', 'ssl', 'none'], true) ? Settings::get('mail.encryption') : 'tls',
            'username'   => Settings::get('mail.username'),
            'password'   => Settings::get('mail.password'),
            'from_email' => Settings::get('mail.from_email'),
            'from_name'  => Settings::get('mail.from_name'),
        ]);
    }
}
