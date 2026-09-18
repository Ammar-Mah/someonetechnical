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
 * table. Owner notification and abuse limits are #16's.
 */
class IntakeHandler extends Handler
{
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
        $fields = $this->answers(is_array($data) ? $data : []);

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

        return Event::make()
            ->inner('#' . IntakeScreen::REGION_ID, IntakeScreen::confirmation($fields['contact_name']))
            ->add('#' . IntakeScreen::REGION_ID, 'intake-confirmed')
            ->send();
    }

    /**
     * The posted answers, one per column: trimmed, cut to the column's length,
     * and NULL where the visitor left the question alone. A choice question
     * keeps only a choice IntakeScreen offered.
     */
    private function answers(array $data): array
    {
        $fields = [];

        foreach (self::LENGTHS as $name => $length) {
            $value = is_scalar($data[$name] ?? null) ? trim((string)$data[$name]) : '';

            if (mb_strlen($value) > $length) {
                $value = mb_substr($value, 0, $length);
                Log::warn('app', 'intake answer cut to fit', ['field' => $name, 'kept' => $length]);
            }

            $fields[$name] = $value;
        }

        foreach (['is_live' => IntakeScreen::IS_LIVE, 'help_wanted' => IntakeScreen::HELP_WANTED] as $name => $choices) {
            if ($fields[$name] !== '' && !in_array($fields[$name], $choices, true)) {
                Log::warn('app', 'intake choice not offered', ['field' => $name]);
                $fields[$name] = '';
            }
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
