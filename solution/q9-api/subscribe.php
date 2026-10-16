<?php
declare(strict_types=1);

define('NEWSLETTER_ENDPOINT', 'https://newsletter.example.com/subscribers');
define('IDEMPOTENCY_TABLE',   'newsletter_idempotency');
define('MAX_RETRIES',         3);
define('RETRY_DELAY_SECONDS', 2);

function subscribe_user(string $email): array
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'invalid_email', 'message' => 'Email address is not valid.'];
    }

    $idempotency_key = 'sub_' . hash('sha256', strtolower(trim($email)));

    $existing = db_get_idempotency($idempotency_key);
    if ($existing !== null) {
        return ['status' => $existing['result'], 'message' => 'Already processed.'];
    }

    $attempt = 0;
    while ($attempt < MAX_RETRIES) {
        $attempt++;

        $result = call_newsletter_api($email, $idempotency_key);

        if ($result['http_code'] === 201 || $result['http_code'] === 200) {
            db_store_idempotency($idempotency_key, 'subscribed');
            log_event('subscribed', $idempotency_key);
            return ['status' => 'subscribed'];
        }

        if ($result['http_code'] === 409) {
            db_store_idempotency($idempotency_key, 'already_exists');
            log_event('already_exists', $idempotency_key);
            return ['status' => 'already_exists'];
        }

        if ($result['http_code'] === 422) {
            log_event('rejected_invalid', $idempotency_key);
            return ['status' => 'invalid_email', 'message' => 'Rejected by provider.'];
        }

        if ($result['http_code'] === 429) {
            $retry_after = (int) ($result['headers']['Retry-After'] ?? 60);
            log_event('rate_limited', $idempotency_key, ['retry_after' => $retry_after]);
            sleep(min($retry_after, 60));
            continue;
        }

        if ($result['timed_out']) {
            log_event('timeout', $idempotency_key, ['attempt' => $attempt]);
            if ($attempt < MAX_RETRIES) {
                sleep(RETRY_DELAY_SECONDS * $attempt);
            }
            continue;
        }

        log_event('unexpected_error', $idempotency_key, ['http_code' => $result['http_code']]);
        break;
    }

    return ['status' => 'pending', 'message' => 'Could not confirm. Will retry later.'];
}

function call_newsletter_api(string $email, string $idempotency_key): array
{
    $ch = curl_init(NEWSLETTER_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $idempotency_key,
        ],
        CURLOPT_POSTFIELDS     => json_encode(['email' => $email]),
    ]);

    $body      = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $timed_out = curl_errno($ch) === CURLE_OPERATION_TIMEOUTED;
    curl_close($ch);

    return ['http_code' => $http_code, 'body' => $body, 'timed_out' => $timed_out, 'headers' => []];
}

function db_get_idempotency(string $key): ?array
{
    return null;
}

function db_store_idempotency(string $key, string $result): void
{
}

function log_event(string $event, string $key, array $context = []): void
{
    error_log(json_encode(['event' => $event, 'key' => $key, 'context' => $context]));
}
