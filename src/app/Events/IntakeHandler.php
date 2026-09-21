<?php

/**
 * The intake's one flow: a visitor's answers become a stored request.
 *
 * Any session holder can call this — every page load has a session, so a bot
 * has one too (ARCHITECTURE.md → Hazards). So nothing here trusts the payload:
 * every answer is cut to the length its column accepts before it reaches the
 * database, the two choice questions accept only the choices IntakeScreen
 * rendered, and the only thing that comes back to the screen is the visitor's
 * name, escaped.
 *
 * Validation is an early return that changes nothing else: the field's error
 * slot is filled and the input marked, so the visitor keeps what they typed.
 *
 * Every outcome is logged on `app` — stored, refused, or cut to fit — and
 * never with an answer, a name or an address in the context. The write's own
 * line is the `audit` one, which boot.inc.php reduces to column names for this
 * table.
 *
 * Two things stand in front of the store, because a bot has a session too
 * (#16): a filled honeypot is thanked and dropped, and an address that has
 * already stored LIMIT requests inside the hour is refused. Behind it, every
 * stored request is mailed to INTAKE_NOTIFY_TO, with a subject that carries
 * nothing the visitor typed — the `mail` line logs the subject.
 */
class IntakeHandler extends Handler
{
    /** Stored requests one address may make inside WINDOW seconds. */
    public const LIMIT  = 3;
    public const WINDOW = 3600;

    /** Told to an address that has reached LIMIT. */
    public const LIMITED = 'We already have three requests from you this hour, which is plenty for us to start with. Someone technical will be in touch — if something is urgent, try again in an hour.';

    /** The form's slot for LIMITED, appended once and replaced after that. */
    public const LIMIT_ID = 'intake-limit';

    /** Set by the suite in place of INTAKE_NOTIFY_TO, as Mailer::setHandler is. */
    private static ?string $recipient = null;

    public static function setRecipient(?string $address): void
    {
        self::$recipient = $address;
    }

    /**
     * What each answer may be, in intake_requests' own order: the length its
     * column accepts. The two TEXT answers are bounded too — a column that
     * takes 65,535 bytes is not a reason to store them.
     */
    private const LENGTHS = [
        'building'       => 5000,
        'ai_tool'        => 100,
        'stuck_on'       => 5000,
        'is_live'        => 20,
        'help_wanted'    => 20,
        'contact_name'   => 200,
        'contact_email'  => 254,
        'preferred_time' => 200,
    ];

    /**
     * Store one request, or refuse it.
     *
     * The form posts every named field as one JSON object in `value`
     * (LLM.txt §8.2).
     */
    public function send(Request $request)
    {
        $data = json_decode((string)$request->get('value'), true) ?: [];
        $data = is_array($data) ? $data : [];
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        // Filled only by something that reads the markup rather than the page.
        // It is thanked like anyone else, so it learns nothing to adapt to.
        if (is_scalar($data[IntakeScreen::TRAP] ?? null) && trim((string)$data[IntakeScreen::TRAP]) !== '') {
            Log::warn('security', 'intake honeypot filled', ['ip' => $ip]);

            return $this->confirm(is_scalar($data['contact_name'] ?? null) ? trim((string)$data['contact_name']) : '');
        }

        // Reading the count, storing and writing the count back are one step:
        // without the lock, requests arriving together all read a count under
        // LIMIT and all store (#16's review). The mail goes out after it.
        $lock = $this->lock($ip);
        try {
            $recent = $this->recent($ip);
            if (count($recent) >= self::LIMIT) {
                Log::warn('security', 'intake request refused: rate limit', [
                    'ip' => $ip, 'stored' => count($recent), 'window' => self::WINDOW,
                ]);

                return Event::make()
                    ->append('.intake-form', '<p class="intake-alert" id="' . self::LIMIT_ID . '" role="alert">' . e(self::LIMITED) . '</p>')
                    ->send();
            }

            $fields = $this->answers($data);

            if ($fields['contact_name'] === '') {
                return $this->refuse('contact_name', 'missing', IntakeScreen::NAME_ID, IntakeScreen::NAME_ERROR_ID,
                    'We need a name to greet you by.');
            }

            if (filter_var($fields['contact_email'], FILTER_VALIDATE_EMAIL) === false) {
                return $this->refuse('contact_email', 'invalid', IntakeScreen::EMAIL_ID, IntakeScreen::EMAIL_ERROR_ID,
                    'That email address does not look right. We reply to it, so it has to reach you.');
            }

            $stored = IntakeRequest::add($fields);

            Log::info('app', 'intake request stored', [
                'id'       => $stored->getKey(),
                'answered' => count(array_filter($fields, fn($value): bool => $value !== null && $value !== '')),
            ]);

            $this->record($ip, $recent);
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        $this->notify((int)$stored->getKey(), $fields);

        return $this->confirm($fields['contact_name']);
    }

    private function confirm(string $name)
    {
        return Event::make()
            ->inner('#' . IntakeScreen::REGION_ID, IntakeScreen::confirmation($name))
            ->add('#' . IntakeScreen::REGION_ID, 'intake-confirmed')
            ->send();
    }

    /**
     * When this address stored its requests inside the window, oldest first.
     *
     * Kept in Cache under a hash of the address, so the file name carries no
     * address. A request with no REMOTE_ADDR — the suite, a CLI — has nothing
     * to count by; a web server always sets one.
     */
    private function recent(string $ip): array
    {
        if ($ip === '') {
            return [];
        }

        $since = time() - self::WINDOW;
        $times = Cache::get(self::limitKey($ip), []);

        return array_values(array_filter(is_array($times) ? $times : [], fn($at): bool => is_int($at) && $at > $since));
    }

    private function record(string $ip, array $recent): void
    {
        if ($ip !== '') {
            Cache::set(self::limitKey($ip), [...$recent, time()], self::WINDOW);
        }
    }

    /**
     * The Cache key an address is counted under. An IPv6 host is handed a
     * whole /64, so it is counted by that prefix, or it could rotate through
     * addresses; IPv4 is counted by the address.
     */
    public static function limitKey(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16) {
            $ip = bin2hex(substr($packed, 0, 8)) . '::/64';
        }

        return 'intake-limit-' . hash('sha256', $ip);
    }

    /**
     * An exclusive lock on the one lock file every count shares, held until
     * the caller releases it. With no address there is nothing to count, so
     * nothing to lock. A lock that cannot be taken is logged, and the request
     * goes on unguarded rather than being refused.
     *
     * @return resource|null
     */
    private function lock(string $ip)
    {
        if ($ip === '') {
            return null;
        }

        $handle = @fopen((defined('ROOT') ? ROOT : dirname(__DIR__, 3)) . '/cache/intake-limit.lock', 'c');
        if ($handle !== false && flock($handle, LOCK_EX)) {
            return $handle;
        }

        if ($handle !== false) {
            fclose($handle);
        }
        Log::warn('security', 'intake limit lock unavailable', ['ip' => $ip]);

        return null;
    }

    /**
     * Tell the owner. The request is already stored, so nothing here can undo
     * it: an empty recipient is a warning, and a transport that fails logs its
     * own `error mail` line through Mailer. The visitor is confirmed either way.
     */
    private function notify(int $id, array $fields): void
    {
        $to = self::$recipient ?? (defined('INTAKE_NOTIFY_TO') ? (string)INTAKE_NOTIFY_TO : '');

        if (trim($to) === '') {
            Log::warn('app', 'intake notification skipped', ['id' => $id, 'reason' => 'no recipient']);
            return;
        }

        $rows = '';
        foreach ($fields as $column => $value) {
            $rows .= '<tr><th align="left" valign="top">' . e($column) . '</th><td>'
                . nl2br(e((string)($value ?? '—'))) . '</td></tr>';
        }

        Mailer::send([
            'to'       => $to,
            'subject'  => 'New intake request #' . $id,
            'html'     => '<p>A request arrived through the intake.</p><table cellpadding="6">' . $rows . '</table>',
            'reply_to' => $fields['contact_email'],
        ]);
    }

    /**
     * The posted answers, one per column: trimmed, cut to the column's length,
     * and NULL where the visitor left the question alone. A choice question
     * keeps only a choice IntakeScreen offered.
     */
    private function answers(array $data): array
    {
        $fields = [];

        // The choice questions first: a value the page never offered is dropped
        // whole, so it is never also reported as an answer cut to fit.
        $offered = ['is_live' => IntakeScreen::IS_LIVE, 'help_wanted' => IntakeScreen::HELP_WANTED];

        foreach (self::LENGTHS as $name => $length) {
            $value = is_scalar($data[$name] ?? null) ? trim((string)$data[$name]) : '';

            if (isset($offered[$name])) {
                if ($value !== '' && !in_array($value, $offered[$name], true)) {
                    Log::warn('app', 'intake choice not offered', ['field' => $name]);
                    $value = '';
                }

                $fields[$name] = $value;
                continue;
            }

            if (mb_strlen($value) > $length) {
                $value = mb_substr($value, 0, $length);
                Log::warn('app', 'intake answer cut to fit', ['field' => $name, 'kept' => $length]);
            }

            $fields[$name] = $value;
        }

        // An unanswered question is NULL, not an empty string: "I don't know"
        // and "left alone" are the same thing, and the columns are nullable.
        // The contact pair is required, so it stays a string for the checks
        // above to refuse.
        foreach ($fields as $name => $value) {
            if ($value === '' && $name !== 'contact_name' && $name !== 'contact_email') {
                $fields[$name] = null;
            }
        }

        return $fields;
    }

    /**
     * Refuse one field. The log line names the field and why, never the value
     * the visitor sent (policies/logging.md).
     *
     * Both error slots are cleared first, so fixing one field and posting
     * again does not leave the other's message standing under a good answer.
     */
    private function refuse(string $field, string $reason, string $inputId, string $errorId, string $message)
    {
        Log::warn('app', 'intake request refused', ['field' => $field, 'reason' => $reason]);

        return Event::make()
            ->inner('#' . IntakeScreen::NAME_ERROR_ID, '')
            ->inner('#' . IntakeScreen::EMAIL_ERROR_ID, '')
            ->strip('#' . IntakeScreen::NAME_ID, 'is-invalid')
            ->strip('#' . IntakeScreen::EMAIL_ID, 'is-invalid')
            ->inner('#' . $errorId, e($message))
            ->add('#' . $inputId, 'is-invalid')
            ->focus('#' . $inputId)
            ->send();
    }
}
