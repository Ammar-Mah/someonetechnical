<?php


class Session {
    /**
     * True once the session lock has been released for this request.
     *
     * PHP's default file session handler holds an exclusive lock for the whole
     * request. Because the UI fires one AJAX request per task group / kanban
     * column, those "parallel" requests were in fact running strictly one at a
     * time, each queued behind the previous one's lock.
     *
     * release() drops the lock. $_SESSION stays readable afterwards, so reads
     * cost nothing. Any write re-acquires the lock first via acquire(), so a
     * request that genuinely needs to write still gets correct, serialised
     * access — it just no longer blocks every other request while it renders.
     */
    private static bool $released = false;
    private static bool $lostWrites = false;

    public static function start() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        self::$released = false;
    }

    /**
     * Release the session lock. Call once a request has finished writing
     * session state — typically right before dispatching a handler.
     */
    public static function release(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            self::$released = true;
        }
    }

    /**
     * Re-acquire the lock and re-read the session, so a read-modify-write sees
     * whatever other requests committed in the meantime.
     */
    public static function acquire(): void {
        if (!self::$released || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // session_start() emits a warning once output has begun, and the global
        // error handler turns warnings into fatals — so check first. Writes made
        // after this point still update $_SESSION in memory (so reads inside this
        // request stay consistent) but will not be persisted.
        if (headers_sent()) {
            self::$lostWrites = true;
            return;
        }
        session_start();
        self::$released = false;
    }

    /**
     * True if a session write happened after output had already started and
     * therefore could not be persisted. Nothing in the normal request flow
     * should trigger this; it exists so the condition is detectable rather
     * than silent.
     */
    public static function hasLostWrites(): bool {
        return self::$lostWrites;
    }

    public static function isReleased(): bool {
        return self::$released;
    }

    public static function sync($key, $value, $toggle=false) {
        self::acquire();
        if (!isset($_SESSION[$key])) $_SESSION[$key]='';
        $vals= explode('|', $_SESSION[$key]);
        if (!in_array($value,$vals)) $vals[]=$value;
        else{
            if ($toggle){
                $vals=array_diff($vals, array($value));
            }
        }
        $_SESSION[$key] = implode('|', array_filter($vals));
    }



    public static function found($key,$value) {
        if (!isset($_SESSION[$key])) return false;
        $vals= explode('|', $_SESSION[$key]);
        return in_array($value,$vals);

    }
    public static function destroy($key="") {
        self::acquire();
        if ($key=="") session_destroy();
        else{
            unset($_SESSION[$key]);
        }
    }

    public static function get($key) {
        return $_SESSION[$key]??null;

    }

    public static function set($key, $value) {
        self::acquire();
        $_SESSION[$key] = $value;
    }
}
