<?php
class CsrfToken {
    public static function generate(): string {
        self::startSession();
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public static function validate(?string $token): bool {
        self::startSession();

        if (!isset($_SESSION['csrf_token']) || !is_string($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    private static function startSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
