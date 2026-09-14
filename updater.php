<?php
header('Content-Type: application/json');

/**
 * Refuse a request, and say why — but only to a developer.
 *
 * In production every refusal is the same flat "Unknown action.": telling an
 * unauthenticated caller which classes exist and which methods they expose is
 * a map of the application. With DEBUG_MODE on, the same refusal explains
 * itself, because the overwhelmingly likely cause is a typo in a handler name
 * and the answer should not be "read the log".
 *
 * The full reason is logged either way.
 */
function refuse(string $reason, array $context, string $explanation): never
{
    http_response_code(403);

    if (class_exists('Log', false)) {
        Log::warn('security', 'updater request rejected: ' . $reason, $context + ['why' => $explanation]);
    }

    $debug = function_exists('isDebugMode') && isDebugMode();

    echo json_encode([
        'status'  => 'error',
        'message' => $debug ? $explanation : 'Unknown action.',
    ], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** The dispatchable handlers a class declares, for a helpful refusal. */
function handlersOf(string $class): array
{
    $names = [];
    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->isStatic() || str_starts_with($method->getName(), '__')) continue;
        if (in_array($method->getDeclaringClass()->getName(), ['Component', 'Handler'], true)) continue;
        $names[] = $method->getName();
    }
    sort($names);
    return $names;
}

/** The nearest candidate to $wanted, or '' when nothing is close enough. */
function closestTo(string $wanted, array $candidates): string
{
    $best = '';
    $bestDistance = PHP_INT_MAX;

    foreach ($candidates as $candidate) {
        $distance = levenshtein(strtolower($wanted), strtolower($candidate));
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $best = $candidate;
        }
    }

    // Only suggest a genuinely near miss; "did you mean save()?" for a name
    // sharing two letters is noise dressed up as help.
    $tolerance = max(2, (int)floor(strlen($wanted) / 3));
    return $bestDistance <= $tolerance ? $best : '';
}

try {
    require_once './src/core/inc/initialize.inc.php';
    require_once './src/core/inc/functions.inc.php';
    require_once './src/app/boot.inc.php';

    $payload = $_POST['data'] ?? '';
    $data = is_string($payload) ? json_decode($payload, true) : null;
    if (!is_array($data)) {
        throw new Exception('Invalid request payload');
    }

    // ---------------------------------------------------------------- guards
    // This endpoint used to instantiate whatever class name arrived in the POST
    // body and invoke whatever method name came with it, with no login check,
    // no CSRF token and no restriction on the target. Four checks now stand in
    // front of dispatch.

    // 1. Authenticated session required.
    $authUserId = Session::get('user') ?? Session::get('user_id') ?? Session::get('userId');
    if (empty($authUserId)) {
        http_response_code(401);
        Log::warn('security', 'unauthenticated updater request rejected', [
            'comp' => $data['comp'] ?? null,
            'func' => $data['func'] ?? null,
        ]);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Your session has ended. Please reload the page and sign in again.',
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // 2. Request must carry the token issued with the page.
    if (!Csrf::check($data['csrf'] ?? null)) {
        http_response_code(403);
        Log::warn('security', 'updater request rejected: bad or missing CSRF token', [
            'comp' => $data['comp'] ?? null,
            'func' => $data['func'] ?? null,
        ]);
        echo json_encode([
            'status'  => 'error',
            'message' => 'This page is out of date. Please reload and try again.',
        ], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $request = Request::create($data);

    $funcName = $request->handlerName();
    $funcParams = $request->handlerParams();

    // "Comp.method()" overrides the component named in the payload.
    $class = $data['comp'] ?? null;
    if (is_string($funcName) && str_contains($funcName, ".")) {
        $class = explode(".", $funcName)[0];
        $funcName = explode(".", $funcName)[1];
    }

    // 3. Only components are dispatchable — not arbitrary classes that merely
    //    happen to expose a static make().
    if (!is_string($class) || $class === '' || !class_exists($class) || !is_subclass_of($class, 'Component')) {
        $name = is_string($class) ? $class : gettype($class);
        refuse(
            'not a component',
            ['comp' => $name, 'func' => $funcName],
            $name === '' || $name === 'NULL'
                ? 'No component was named in the request. A handler is addressed as "Class.method()", '
                  . 'or the element must carry a comp attribute.'
                : (class_exists($name)
                    ? "\"{$name}\" exists but does not extend Component, so it is not dispatchable."
                    : "There is no class \"{$name}\". Components live in src/app/Components and "
                      . 'src/core/Components, one class per file, filename identical to the class name.')
        );
    }

    // Boot is done and nothing below writes session state unless a handler asks
    // for it, so drop the session lock here. Session::set() and State::set()
    // transparently re-acquire it, so writers are still correct — they just no
    // longer block every other in-flight request for the whole render.
    Session::release();

    // Identify the handler in the request summary. This is what turns the log
    // into something you can rank by cost — "which handler runs the most
    // queries / returns the most bytes" is otherwise unanswerable.
    if (Log::enabled()) {
        Log::set('comp', $class);
        Log::set('func', $funcName);
        Log::mark('dispatch');
    }

    $item = $class::make($data['id'] ?? '')->with($data);
    $request->params = $funcParams;

    // A missing method used to reach ReflectionMethod's constructor and surface
    // as an uncaught ReflectionException — a 500 with a stack trace pointing at
    // this file rather than at the caller's mistake.
    if (!method_exists($item, $funcName)) {
        $available = handlersOf($class);
        $guess = closestTo($funcName, $available);
        refuse(
            'no such method',
            ['comp' => $class, 'func' => $funcName],
            "{$class} has no method \"{$funcName}()\"."
                . ($guess !== '' ? " Did you mean \"{$guess}()\"?" : '')
                . ($available === []
                    ? " {$class} declares no handlers at all."
                    : ' Handlers on ' . $class . ': ' . implode(', ', $available) . '.')
        );
    }

    $method = new ReflectionMethod($item, $funcName);

    // 4. Only genuine handlers are callable. A handler is a public, non-static,
    //    non-magic method the application declared — anything inherited from the
    //    Component base is framework plumbing (make, with, parse, addClass,
    //    action, …) and must not be reachable from a POST body.
    $declaring = $method->getDeclaringClass()->getName();
    if (!$method->isPublic()
        || $method->isStatic()
        || str_starts_with($funcName, '__')
        || in_array($declaring, ['Component', 'Handler'], true)) {
        $why = !$method->isPublic()            ? "{$class}::{$funcName}() is not public."
             : ($method->isStatic()            ? "{$class}::{$funcName}() is static. A handler must be an instance "
                                                 . 'method — the component is rebuilt from the request before it runs, '
                                                 . 'and a static method would not see that state.'
             : (str_starts_with($funcName, '__') ? "\"{$funcName}\" is a magic method."
             : "{$funcName}() is declared on {$declaring}, which is framework plumbing. "
               . 'Only methods your own class declares are dispatchable.'));

        refuse('not a handler', ['comp' => $class, 'func' => $funcName, 'declared_on' => $declaring], $why);
    }

    $paramsCount = $method->getNumberOfParameters();
    if ($paramsCount === 0) {
        $res = $item->$funcName();
    } else {
        $firstParam = $method->getParameters()[0];
        $firstParamType = $firstParam->getType();
        $wantsRequest = ($firstParamType && $firstParamType instanceof ReflectionNamedType && $firstParamType->getName() === 'Request')
            || strtolower($firstParam->getName()) === 'request';

        if ($wantsRequest || $paramsCount === 1) {
            $res = $item->$funcName($request);
        } else {
            $res = $item->$funcName(...$funcParams);
        }
    }

    // JSON_INVALID_UTF8_SUBSTITUTE: without it a single malformed byte anywhere
    // in the rendered HTML makes json_encode() return false, which echoed an
    // empty body — the browser's JSON.parse('') then threw and the UI hung.
    $encoded = json_encode($res, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if ($encoded === false) {
        throw new Exception('Failed to encode response: ' . json_last_error_msg());
    }

    if (Log::enabled()) {
        Log::count('bytes', strlen($encoded));
        if (is_array($res) && isset($res['actions']) && is_array($res['actions'])) {
            Log::set('actions', count($res['actions']));
        }
    }

    echo $encoded;
} catch (Throwable $e) {
    http_response_code(500);

    if (class_exists('Log', false)) {
        Log::exception('updater', $e);
    }

    // The request id goes back to the client so a user-reported failure can be
    // matched to its log lines without guesswork.
    $rid = class_exists('Log', false) ? Log::requestId() : '';

    if (function_exists('isDebugMode') && isDebugMode()) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'rid' => $rid,
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Unexpected error has occurred, kindly try later',
            'rid' => $rid,
        ], JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
