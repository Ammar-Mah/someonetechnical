<?php

/**
 * Sends one message. Knows nothing about the app.
 *
 * This is the only place in the codebase that calls mail(). It takes a plain array
 * describing a message and returns whether it left the building:
 *
 *     Mailer::send([
 *         'to'      => [['email' => 'a@b.c', 'name' => 'A B', 'type' => 'to']],
 *         'subject' => 'Hello',
 *         'html'    => '<p>Hi</p>',
 *     ]);
 *
 * Everything about *why* a message exists — templates, recipients, user
 * preferences, the outbox — lives in the models. This class only moves bytes.
 *
 * Two transports, chosen by MAIL_TRANSPORT in runtime.php:
 *
 *   mail        PHP's mail(). What production uses.
 *   log         format it, write it to the log, report success, deliver nothing
 *
 * 'log' is the default. The development database is a copy of production, so a
 * machine that has not been deliberately configured must not be able to mail
 * several hundred real colleagues.
 *
 * There were two more — a hand-written SMTP client and an adapter for a bundled
 * PHPMailer — and both are gone, along with src/app/SMTP and its 696 KB vendor
 * directory. Production sends with mail(); the SMTP paths were never configured
 * (MAIL_SMTP_HOST was empty, which the resolver treated as a reason to fall back to
 * log), so they were 300 lines and a vendored dependency that had never delivered a
 * message. The MIME construction they shared stayed: mail() needs exactly the same
 * headers and multipart body.
 */
final class Mailer
{
    public const TRANSPORT_LOG  = 'log';
    public const TRANSPORT_MAIL = 'mail';

    private const EOL = "\r\n";

    /** Why the last send() failed. Empty when it succeeded. */
    private static string $lastError = '';

    /** Set by tests to capture messages instead of sending them. */
    private static $handler = null;

    public static function lastError(): string
    {
        return self::$lastError;
    }

    /**
     * Route every send through $handler instead of a real transport.
     * Pass null to restore normal behaviour. Used by the test suite.
     */
    public static function setHandler(?callable $handler): void
    {
        self::$handler = $handler;
    }

    public static function transport(): string
    {
        return self::resolveTransport((string)self::config('MAIL_TRANSPORT', self::TRANSPORT_LOG));
    }

    /**
     * Decide which transport a configuration actually gets. Kept separate from the
     * constants so the fallback can be exercised directly.
     *
     * A name that is not one of the two falls back to log rather than to mail: a
     * typo in the config should stop delivery and say so, not start delivering.
     * 'smtp' and 'phpmailer' now land here, which is deliberate — an old config
     * still naming one of them gets a warning naming it, instead of silently
     * behaving like something else.
     */
    private static function resolveTransport(string $configured): string
    {
        $name = strtolower(trim($configured));

        if (!in_array($name, [self::TRANSPORT_LOG, self::TRANSPORT_MAIL], true)) {
            Log::warn('mail', 'Unknown MAIL_TRANSPORT, falling back to log', [
                'configured' => $name,
                'supported'  => [self::TRANSPORT_MAIL, self::TRANSPORT_LOG],
            ]);
            return self::TRANSPORT_LOG;
        }

        return $name;
    }

    /**
     * @param array $message  to, subject, html, and optionally text, from,
     *                        from_name, reply_to, attachments
     * @return bool true when the transport accepted the message
     */
    public static function send(array $message): bool
    {
        self::$lastError = '';

        try {
            $message = self::normalise($message);
        } catch (InvalidArgumentException $e) {
            self::$lastError = $e->getMessage();
            Log::warn('mail', 'Rejected an invalid message', ['error' => self::$lastError]);
            return false;
        }

        // An injected handler goes through the same try/catch and the same logging as
        // a real transport. It used to return directly from here, which meant a
        // handler that threw escaped send() — and send()'s whole contract is that it
        // returns a bool and never throws, because Notification::deliver() depends on
        // getting false rather than an exception. A test seam that behaves unlike the
        // thing it stands in for is worse than no seam.
        $transport = self::$handler !== null ? 'handler' : self::transport();

        try {
            $ok = self::$handler !== null
                ? (bool)(self::$handler)($message)
                : match ($transport) {
                    self::TRANSPORT_LOG  => self::sendViaLog($message),
                    self::TRANSPORT_MAIL => self::sendViaMail($message),
                };
        } catch (Throwable $e) {
            $ok = false;
            self::$lastError = $e->getMessage();
        }

        // Everything needed to answer "why has this not arrived", and nothing that is
        // only of interest when profiling. The send duration used to be here and is
        // gone: it never explained a delivery failure and it was one more float per
        // line. See Log::shortenFloats().
        $ctx = [
            'transport'  => $transport,
            'to'         => self::addressList($message['to']),
            'subject'    => $message['subject'],
            'from'       => $message['from'],
        ];
        // Only when it happened, so the common case stays short — but always when it
        // did, because a redirect still switched on is the likeliest reason a message
        // "was sent" and nobody received it.
        if (!empty($message['redirected'])) {
            $ctx['redirected_from'] = $message['intended'];
        }
        // Set by the dispatcher, so a line here can be traced to its queue row.
        if (isset($message['notification_id'])) {
            $ctx['notification_id'] = $message['notification_id'];
        }

        if ($ok) {
            // 'log' accepts every message and delivers none. Saying "sent" for that
            // would be the single most misleading line this file could contain.
            if ($transport === self::TRANSPORT_LOG) {
                Log::warn('mail', 'Accepted but NOT delivered (MAIL_TRANSPORT=log)', $ctx);
            } else {
                Log::info('mail', 'Sent', $ctx);
            }
        } else {
            if (self::$lastError === '') self::$lastError = 'The ' . $transport . ' transport reported a failure.';
            Log::error('mail', 'Send failed', $ctx + ['error' => self::$lastError]);
        }

        Log::count($ok ? 'mail_sent' : 'mail_failed');
        return $ok;
    }

    // --- Message preparation -------------------------------------------------

    /**
     * Validate and canonicalise a message. Throws rather than half-sending.
     */
    private static function normalise(array $message): array
    {
        $subject = trim((string)($message['subject'] ?? ''));
        if ($subject === '') {
            throw new InvalidArgumentException('A message needs a subject.');
        }

        $html = (string)($message['html'] ?? '');
        $text = (string)($message['text'] ?? '');
        if ($html === '' && $text === '') {
            throw new InvalidArgumentException('A message needs a body.');
        }
        if ($text === '' && $html !== '') {
            $text = self::htmlToText($html);
        }

        $to = self::normaliseRecipients($message['to'] ?? []);
        if ($to === []) {
            throw new InvalidArgumentException('A message needs at least one valid recipient.');
        }

        // One address for the whole run, so a real SMTP test can be pointed at a
        // single inbox without touching the rest of the pipeline.
        //
        // The address the message was *meant* for is carried on the message as
        // `intended`, because the log lines used to record only the final recipient:
        // with a redirect configured, every line named the same inbox and there was
        // no way to tell from the log who each message had been addressed to. That
        // makes a redirect left switched on indistinguishable from mail genuinely not
        // being sent, which is exactly the question these logs have to answer.
        $intended = self::addressList($to);
        $redirected = false;

        $redirect = trim((string)self::config('MAIL_REDIRECT_ALL_TO', ''));
        if ($redirect !== '' && filter_var($redirect, FILTER_VALIDATE_EMAIL)) {
            $redirected = true;
            $to = [['email' => $redirect, 'name' => '', 'type' => 'to']];
            $html = '<p style="font:12px monospace;color:#888">[redirected from '
                  . htmlspecialchars($intended, ENT_QUOTES, 'UTF-8') . ']</p>' . $html;
            $text = "[redirected from $intended]\n" . $text;
        }

        $from = trim((string)($message['from'] ?? self::config('MAIL_FROM', '')));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('MAIL_FROM is not a valid address: ' . $from);
        }

        $normalised = [
            'to'          => $to,
            'intended'    => $intended,
            'redirected'  => $redirected,
            'subject'     => self::stripNewlines($subject),
            'html'        => $html,
            'text'        => $text,
            'from'        => $from,
            'from_name'   => self::stripNewlines((string)($message['from_name'] ?? self::config('MAIL_FROM_NAME', ''))),
            'reply_to'    => trim((string)($message['reply_to'] ?? '')),
            'attachments' => is_array($message['attachments'] ?? null) ? $message['attachments'] : [],
        ];

        // An opaque correlation id from the caller, carried through so the send line
        // names the queue row it came from. This array is built explicitly rather than
        // merged, so anything not listed here is dropped — which is why it has to be
        // named.
        if (isset($message['notification_id'])) {
            $normalised['notification_id'] = $message['notification_id'];
        }

        return $normalised;
    }

    /**
     * Accepts a bare string, "a@b, c@d", ['email' => .., 'name' => ..], or a
     * list of any of those, and returns a clean list. Invalid addresses are
     * dropped and logged rather than silently smuggled into a header.
     */
    public static function normaliseRecipients($input): array
    {
        if ($input === null || $input === '' || $input === []) return [];

        if (is_string($input)) {
            $input = array_map('trim', explode(',', $input));
        } elseif (isset($input['email'])) {
            $input = [$input];
        }
        if (!is_array($input)) return [];

        $out = [];
        $seen = [];
        foreach ($input as $entry) {
            if (is_string($entry)) {
                $entry = ['email' => $entry];
            }
            if (!is_array($entry)) continue;

            $email = strtolower(trim((string)($entry['email'] ?? '')));
            if ($email === '') continue;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warn('mail', 'Dropped an invalid recipient address', ['email' => $email]);
                continue;
            }
            if (isset($seen[$email])) continue;
            $seen[$email] = true;

            $type = strtolower(trim((string)($entry['type'] ?? 'to')));
            if (!in_array($type, ['to', 'cc', 'bcc'], true)) $type = 'to';

            $out[] = [
                'email' => $email,
                'name'  => self::stripNewlines((string)($entry['name'] ?? '')),
                'type'  => $type,
            ];
        }
        return $out;
    }

    /** "Ada Lovelace <ada@example.com>, bob@example.com" */
    public static function addressList(array $recipients, ?string $onlyType = null): string
    {
        $parts = [];
        foreach ($recipients as $r) {
            if ($onlyType !== null && ($r['type'] ?? 'to') !== $onlyType) continue;
            $parts[] = self::formatAddress((string)$r['email'], (string)($r['name'] ?? ''));
        }
        return implode(', ', $parts);
    }

    private static function formatAddress(string $email, string $name = ''): string
    {
        if ($name === '') return $email;
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    /**
     * RFC 2047 for anything that is not plain ASCII, so "Müller" does not arrive
     * as "MÃ¼ller". Plain names are quoted instead of encoded so they stay
     * readable in the raw source.
     */
    private static function encodeHeader(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return '"' . addcslashes($value, '"\\') . '"';
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /** Header injection guard: a newline in a subject or name splits the message. */
    private static function stripNewlines(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], ' ', $value));
    }

    /**
     * A readable plain-text alternative. The templates are hand-written HTML
     * with inline styles, so this is deliberately simple: block tags become line
     * breaks, links keep their target, everything else is dropped.
     */
    public static function htmlToText(string $html): string
    {
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        // Parentheses, not angle brackets: strip_tags() below would treat
        // "<http://x.test>" as a tag and delete the URL along with it.
        $text = preg_replace_callback(
            '#<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            static function (array $m): string {
                $href  = trim($m[2]);
                $label = trim(strip_tags($m[3]));
                if ($href === '' || $label === '') return $label !== '' ? $label : $href;
                // No point printing the same URL twice.
                return $label === $href ? $href : $label . ' (' . $href . ')';
            },
            $text
        ) ?? $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|h[1-6]|li|tr)>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<hr\s*/?>#i', "\n----\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ ?\n ?/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    // --- Transports ----------------------------------------------------------

    /**
     * The no-op transport: record the message and report success.
     *
     * Only the body excerpt is recorded here — send() already logs the transport,
     * recipients, subject and redirect for every message however it went out, so
     * repeating them would double the size of these lines for nothing.
     */
    private static function sendViaLog(array $m): bool
    {
        Log::debug('mail', 'Message body (transport=log)', [
            'subject' => $m['subject'],
            'bytes'   => strlen($m['html']),
            'text'    => self::excerpt($m['text']),
        ]);
        return true;
    }

    private static function sendViaMail(array $m): bool
    {
        if (!function_exists('mail')) {
            self::$lastError = 'mail() is disabled on this server.';
            return false;
        }

        $headers = self::headers($m, false);
        $body    = self::mimeBody($m);

        // mail() takes the To header separately from the rest.
        $to = self::addressList($m['to'], 'to');
        if ($to === '') $to = self::addressList($m['to']);

        $ok = @mail($to, self::encodeSubject($m['subject']), $body, implode(self::EOL, $headers));
        if (!$ok) self::$lastError = 'mail() returned false.';
        return $ok;
    }

    // --- MIME ----------------------------------------------------------------

    /**
     * @param bool $includeTo whether To/Cc belong in the header block. mail()
     *                        adds them itself; a raw DATA payload must carry them.
     */
    private static function headers(array $m, bool $includeTo): array
    {
        $headers = [];

        if ($includeTo) {
            $to = self::addressList($m['to'], 'to');
            if ($to !== '') $headers[] = 'To: ' . $to;
            $headers[] = 'Subject: ' . self::encodeSubject($m['subject']);
        }

        $cc = self::addressList($m['to'], 'cc');
        if ($cc !== '') $headers[] = 'Cc: ' . $cc;
        // Bcc is deliberately absent: it goes in the envelope, never the header.

        $headers[] = 'From: ' . self::formatAddress($m['from'], $m['from_name']);
        if ($m['reply_to'] !== '') $headers[] = 'Reply-To: ' . $m['reply_to'];

        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::messageIdHost() . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . self::boundary($m) . '"';

        return $headers;
    }

    private static function encodeSubject(string $subject): string
    {
        return self::isAscii($subject) ? $subject : '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private static function isAscii(string $value): bool
    {
        return preg_match('/^[\x20-\x7E]*$/', $value) === 1;
    }

    /**
     * Stable per message so headers() and mimeBody() agree without threading a
     * value between them.
     */
    private static function boundary(array $m): string
    {
        return 'bstn-' . substr(md5($m['subject'] . '|' . self::addressList($m['to'])), 0, 20);
    }

    /**
     * text/plain and text/html, both base64 so long lines and UTF-8 survive the
     * 998-octet line limit intact.
     */
    private static function mimeBody(array $m): string
    {
        $b = self::boundary($m);
        $eol = self::EOL;

        $body  = 'This is a message in MIME format.' . $eol . $eol;
        $body .= '--' . $b . $eol;
        $body .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $body .= chunk_split(base64_encode($m['text']), 76, $eol) . $eol;
        $body .= '--' . $b . $eol;
        $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
        $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
        $body .= chunk_split(base64_encode($m['html']), 76, $eol) . $eol;
        $body .= '--' . $b . '--' . $eol;

        return $body;
    }

    // --- Helpers -------------------------------------------------------------

    /**
     * The domain for the Message-ID header.
     *
     * Was heloName(), because it also greeted the SMTP server. Nothing greets
     * anything now, and a name describing a protocol this class no longer speaks
     * would send the next reader looking for an SMTP client that is not there.
     */
    private static function messageIdHost(): string
    {
        $host = (string)parse_url((string)self::config('APP_URL', ''), PHP_URL_HOST);
        if ($host === '' || $host === 'localhost') {
            $host = gethostname() ?: 'localhost';
        }
        return $host;
    }

    /**
     * runtime.php keys are defined as constants during boot. An empty string is
     * a real answer — MAIL_REDIRECT_ALL_TO uses it to mean "do not redirect" —
     * so only an undefined constant falls back to the default.
     */
    private static function config(string $key, $default = null)
    {
        return defined($key) ? constant($key) : $default;
    }

    private static function excerpt(string $value, int $length = 160): string
    {
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return strlen($value) > $length ? substr($value, 0, $length) . '…' : $value;
    }
}
