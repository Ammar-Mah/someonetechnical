<?php

/**
 * One visitor's request from the intake.
 *
 * The table is #41's; this is the only thing that writes it. Every column but
 * the contact pair is nullable, because "I don't know" is an acceptable answer
 * to every question and an unanswered question stores NULL.
 *
 * It holds personal data — a name, an email address and whatever the visitor
 * chose to tell us — so nothing here is ever logged by value. The audit hook
 * in boot.inc.php reduces this table's writes to their column names, and no
 * handler puts an answer in a context array (docs/DATABASE.md).
 *
 * created_at and updated_at are not automatic: add() stamps them, as
 * .agent/framework/RULES.md §8 requires.
 *
 *   CREATE TABLE intake_requests (
 *       id INTEGER PRIMARY KEY AUTO_INCREMENT,
 *       building TEXT NULL,
 *       ai_tool VARCHAR(100) NULL,
 *       stuck_on TEXT NULL,
 *       is_live VARCHAR(20) NULL,
 *       help_wanted VARCHAR(20) NULL,
 *       contact_name VARCHAR(200) NOT NULL,
 *       contact_email VARCHAR(254) NOT NULL,
 *       preferred_time VARCHAR(200) NULL,
 *       created_at DATETIME NOT NULL,
 *       updated_at DATETIME NOT NULL,
 *       deleted_at DATETIME NULL
 *   );
 *   CREATE INDEX intake_requests_created_at ON intake_requests (created_at);
 */
class IntakeRequest extends Model
{
    protected $table = 'intake_requests';

    /**
     * The answers and the contact, then the columns the application sets.
     *
     * $fillable is also what survives HYDRATION, so a column left out here
     * reads back as null however it was written. The stamps and the key are
     * therefore listed — nothing but add() ever passes them, and add() builds
     * its array from IntakeHandler's fixed list of answers.
     */
    protected $fillable = [
        'building',
        'ai_tool',
        'stuck_on',
        'is_live',
        'help_wanted',
        'contact_name',
        'contact_email',
        'preferred_time',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Store one request. $fields are already trimmed, cut to the column
     * lengths and checked by IntakeHandler; this only stamps and writes.
     *
     * The guard is that IntakeHandler builds $fields itself, one key per
     * answer, so nothing a visitor posts reaches this method under any other
     * name. $fillable cannot be that guard here: it has to list the stamps for
     * them to survive hydration, so it would let them through as well.
     */
    public static function add(array $fields): self
    {
        $request = new static($fields);
        $now = date('Y-m-d H:i:s');

        $request->created_at = $now;
        $request->updated_at = $now;
        $request->save();

        return $request;
    }
}
