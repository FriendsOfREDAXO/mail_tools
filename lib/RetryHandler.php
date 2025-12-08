<?php

namespace FriendsOfRedaxo\MailTools;

/**
 * E-Mail Retry Handler.
 *
 * Erkennt temporäre Fehler und ermöglicht erneutes Versenden.
 */
class RetryHandler
{
    /**
     * Patterns für temporäre Fehler (Retry sinnvoll).
     *
     * @var array<string>
     */
    private const TEMPORARY_ERROR_PATTERNS = [
        // Verbindungsprobleme
        'connection timed out',
        'connection refused',
        'could not connect',
        'unable to connect',
        'network is unreachable',
        'no route to host',

        // SMTP 4xx Codes (temporär)
        'smtp.*4\d{2}',
        '421',  // Service not available
        '450',  // Mailbox temporarily unavailable
        '451',  // Local error in processing
        '452',  // Insufficient storage

        // Temporäre Ablehnungen
        'try again later',
        'temporarily rejected',
        'temporary failure',
        'temporarily unavailable',
        'please retry',
        'greylisting',
        'greylist',

        // Rate Limiting
        'rate limit',
        'too many connections',
        'too many recipients',
        'too many messages',
        'throttl',
        'deferred',

        // Server überlastet
        'server busy',
        'service unavailable',
        'resources temporarily unavailable',
    ];

    /**
     * Patterns für permanente Fehler (Retry sinnlos).
     *
     * @var array<string>
     */
    private const PERMANENT_ERROR_PATTERNS = [
        // Empfänger existiert nicht
        'user unknown',
        'user not found',
        'recipient.*rejected',
        'address rejected',
        'mailbox not found',
        'does not exist',
        'no such user',
        'invalid recipient',
        'unknown user',

        // Domain-Probleme
        'domain not found',
        'no mx record',
        'bad destination',

        // SMTP 5xx Codes (permanent)
        '550',  // Mailbox unavailable
        '551',  // User not local
        '552',  // Message size exceeded
        '553',  // Mailbox name invalid
        '554',  // Transaction failed

        // Spam/Blocking
        'blacklisted',
        'blocked',
        'banned',
        'rejected.*spam',
        'spam.*detected',
        'policy rejection',

        // Authentifizierung
        'authentication required',
        'auth.*failed',
        'relay.*denied',
        'relaying denied',
    ];

    /**
     * Maximale Anzahl Retry-Versuche.
     */
    public const MAX_RETRIES = 3;

    /**
     * Wartezeiten zwischen Versuchen in Sekunden.
     * [1. Retry nach 1h, 2. nach 6h, 3. nach 24h]
     *
     * @var array<int>
     */
    public const RETRY_DELAYS = [3600, 21600, 86400];

    /**
     * Prüft ob ein Fehler temporär ist (Retry sinnvoll).
     */
    public static function isTemporaryError(string $errorMessage): bool
    {
        $errorLower = strtolower($errorMessage);

        // Erst prüfen ob es ein permanenter Fehler ist
        foreach (self::PERMANENT_ERROR_PATTERNS as $pattern) {
            if (str_contains($pattern, '.*')) {
                // Regex-Pattern
                if (preg_match('/' . $pattern . '/i', $errorLower)) {
                    return false;
                }
            } elseif (str_contains($errorLower, $pattern)) {
                return false;
            }
        }

        // Dann prüfen ob es ein temporärer Fehler ist
        foreach (self::TEMPORARY_ERROR_PATTERNS as $pattern) {
            if (str_contains($pattern, '.*') || str_contains($pattern, '\d')) {
                // Regex-Pattern
                if (preg_match('/' . $pattern . '/i', $errorLower)) {
                    return true;
                }
            } elseif (str_contains($errorLower, $pattern)) {
                return true;
            }
        }

        // Unbekannter Fehler - sicherheitshalber kein Retry
        return false;
    }

    /**
     * Prüft ob ein Fehler permanent ist (Retry sinnlos).
     */
    public static function isPermanentError(string $errorMessage): bool
    {
        $errorLower = strtolower($errorMessage);

        foreach (self::PERMANENT_ERROR_PATTERNS as $pattern) {
            if (str_contains($pattern, '.*')) {
                if (preg_match('/' . $pattern . '/i', $errorLower)) {
                    return true;
                }
            } elseif (str_contains($errorLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Holt alle E-Mails die für Retry in Frage kommen.
     *
     * @return array<int, array{hash: string, to: string, subject: string, message: string, timestamp: int, retry_count: int, next_retry: int, eml_file: ?string}>
     */
    public static function getRetryableEmails(): array
    {
        $failed = LogParser::getFailedEmails();
        $retryable = [];

        foreach ($failed as $entry) {
            // Nur temporäre Fehler
            if (!self::isTemporaryError($entry['message'])) {
                continue;
            }

            // Retry-Status aus DB laden
            $retryInfo = self::getRetryInfo($entry['hash']);

            // Max Retries erreicht?
            if ($retryInfo['retry_count'] >= self::MAX_RETRIES) {
                continue;
            }

            // Nächster Retry fällig?
            if ($retryInfo['next_retry'] > time()) {
                continue;
            }

            // EML-Datei suchen
            $emlFile = LogParser::findArchiveFile($entry['subject'], $entry['timestamp']);

            $retryable[] = [
                'hash' => $entry['hash'],
                'to' => $entry['to'],
                'subject' => $entry['subject'],
                'message' => $entry['message'],
                'timestamp' => $entry['timestamp'],
                'retry_count' => $retryInfo['retry_count'],
                'next_retry' => $retryInfo['next_retry'],
                'eml_file' => $emlFile,
            ];
        }

        return $retryable;
    }

    /**
     * Holt Retry-Informationen aus der Datenbank.
     *
     * @return array{retry_count: int, next_retry: int, last_retry: ?int}
     */
    public static function getRetryInfo(string $hash): array
    {
        $sql = \rex_sql::factory();
        $sql->setQuery(
            'SELECT retry_count, next_retry_at, last_retry_at FROM ' . \rex::getTable('mail_tools_retry') . ' WHERE log_hash = :hash',
            ['hash' => $hash],
        );

        if ($sql->getRows() > 0) {
            return [
                'retry_count' => (int) $sql->getValue('retry_count'),
                'next_retry' => $sql->getValue('next_retry_at') ? strtotime($sql->getValue('next_retry_at')) : 0,
                'last_retry' => $sql->getValue('last_retry_at') ? strtotime($sql->getValue('last_retry_at')) : null,
            ];
        }

        return [
            'retry_count' => 0,
            'next_retry' => 0,
            'last_retry' => null,
        ];
    }

    /**
     * Versucht eine E-Mail erneut zu versenden.
     *
     * @return array{success: bool, message: string}
     */
    public static function retry(string $hash): array
    {
        // Retry-Info laden
        $retryInfo = self::getRetryInfo($hash);

        if ($retryInfo['retry_count'] >= self::MAX_RETRIES) {
            return ['success' => false, 'message' => 'Maximale Retry-Anzahl erreicht'];
        }

        // Original-E-Mail finden
        $failed = LogParser::getFailedEmails();
        $entry = null;

        foreach ($failed as $item) {
            if ($item['hash'] === $hash) {
                $entry = $item;
                break;
            }
        }

        if (!$entry) {
            return ['success' => false, 'message' => 'E-Mail nicht gefunden'];
        }

        // EML-Datei suchen
        $emlFile = LogParser::findArchiveFile($entry['subject'], $entry['timestamp']);

        if (!$emlFile || !file_exists($emlFile)) {
            return ['success' => false, 'message' => 'Archivierte E-Mail nicht gefunden'];
        }

        // EML parsen und erneut senden
        $result = self::sendFromEml($emlFile);

        // Retry-Status aktualisieren
        self::updateRetryStatus($hash, $retryInfo['retry_count'] + 1, $result['success']);

        return $result;
    }

    /**
     * Sendet eine E-Mail aus einer EML-Datei.
     *
     * @return array{success: bool, message: string}
     */
    public static function sendFromEml(string $emlFile): array
    {
        if (!file_exists($emlFile)) {
            return ['success' => false, 'message' => 'EML-Datei nicht gefunden'];
        }

        try {
            $emlContent = \rex_file::get($emlFile);
            if (!$emlContent) {
                return ['success' => false, 'message' => 'EML-Datei konnte nicht gelesen werden'];
            }

            // EML parsen
            $parsed = self::parseEml($emlContent);

            if (!$parsed['to']) {
                return ['success' => false, 'message' => 'Empfänger konnte nicht ermittelt werden'];
            }

            // Neue E-Mail erstellen und senden
            $mail = new \rex_mailer();

            // Empfänger
            foreach ($parsed['to'] as $recipient) {
                $mail->addAddress($recipient);
            }

            // CC
            foreach ($parsed['cc'] as $cc) {
                $mail->addCC($cc);
            }

            // Betreff
            $mail->Subject = $parsed['subject'];

            // Body
            if ($parsed['html']) {
                $mail->isHTML(true);
                $mail->Body = $parsed['html'];
                $mail->AltBody = $parsed['text'] ?: strip_tags($parsed['html']);
            } else {
                $mail->Body = $parsed['text'];
            }

            // Reply-To
            if ($parsed['reply_to']) {
                $mail->addReplyTo($parsed['reply_to']);
            }

            $success = $mail->send();

            return [
                'success' => $success,
                'message' => $success ? 'E-Mail erfolgreich erneut gesendet' : 'Fehler beim Senden: ' . $mail->ErrorInfo,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }

    /**
     * Parst eine EML-Datei.
     *
     * @return array{to: array<string>, cc: array<string>, subject: string, text: string, html: string, reply_to: ?string}
     */
    public static function parseEml(string $emlContent): array
    {
        $result = [
            'to' => [],
            'cc' => [],
            'subject' => '',
            'text' => '',
            'html' => '',
            'reply_to' => null,
        ];

        // Header und Body trennen
        $parts = preg_split('/\r?\n\r?\n/', $emlContent, 2);
        $header = $parts[0] ?? '';
        $body = $parts[1] ?? '';

        // Header parsen (mehrzeilige Header zusammenfügen)
        $header = preg_replace('/\r?\n\s+/', ' ', $header);
        $headerLines = preg_split('/\r?\n/', $header);

        foreach ($headerLines as $line) {
            if (preg_match('/^To:\s*(.+)$/i', $line, $m)) {
                $result['to'] = self::extractEmails($m[1]);
            } elseif (preg_match('/^Cc:\s*(.+)$/i', $line, $m)) {
                $result['cc'] = self::extractEmails($m[1]);
            } elseif (preg_match('/^Subject:\s*(.+)$/i', $line, $m)) {
                $result['subject'] = self::decodeHeader($m[1]);
            } elseif (preg_match('/^Reply-To:\s*(.+)$/i', $line, $m)) {
                $emails = self::extractEmails($m[1]);
                $result['reply_to'] = $emails[0] ?? null;
            }
        }

        // Body verarbeiten (vereinfacht - unterstützt keine komplexen MIME-Strukturen)
        // Für komplexere E-Mails wäre eine Library wie php-mime-mail-parser empfohlen
        if (str_contains($header, 'text/html')) {
            $result['html'] = $body;
        } else {
            $result['text'] = $body;
        }

        // Base64 dekodieren wenn nötig
        if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $header)) {
            if ($result['html']) {
                $result['html'] = base64_decode($result['html']);
            }
            if ($result['text']) {
                $result['text'] = base64_decode($result['text']);
            }
        }

        // Quoted-Printable dekodieren
        if (preg_match('/Content-Transfer-Encoding:\s*quoted-printable/i', $header)) {
            if ($result['html']) {
                $result['html'] = quoted_printable_decode($result['html']);
            }
            if ($result['text']) {
                $result['text'] = quoted_printable_decode($result['text']);
            }
        }

        return $result;
    }

    /**
     * Extrahiert E-Mail-Adressen aus einem Header-Wert.
     *
     * @return array<string>
     */
    private static function extractEmails(string $headerValue): array
    {
        $emails = [];

        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $headerValue, $matches)) {
            $emails = $matches[0];
        }

        return $emails;
    }

    /**
     * Dekodiert einen MIME-kodierten Header.
     */
    private static function decodeHeader(string $header): string
    {
        // RFC 2047 encoded-word Dekodierung
        $decoded = preg_replace_callback(
            '/=\?([^?]+)\?([BQ])\?([^?]*)\?=/i',
            static function ($matches) {
                $charset = $matches[1];
                $encoding = strtoupper($matches[2]);
                $text = $matches[3];

                if ($encoding === 'B') {
                    $text = base64_decode($text);
                } elseif ($encoding === 'Q') {
                    $text = quoted_printable_decode(str_replace('_', ' ', $text));
                }

                if (strtoupper($charset) !== 'UTF-8') {
                    $text = mb_convert_encoding($text, 'UTF-8', $charset);
                }

                return $text;
            },
            $header,
        );

        return $decoded ?: $header;
    }

    /**
     * Aktualisiert den Retry-Status in der Datenbank.
     */
    public static function updateRetryStatus(string $hash, int $retryCount, bool $success): void
    {
        $sql = \rex_sql::factory();
        $now = date('Y-m-d H:i:s');

        // Nächsten Retry-Zeitpunkt berechnen
        $nextRetry = null;
        if (!$success && $retryCount < self::MAX_RETRIES) {
            $delay = self::RETRY_DELAYS[$retryCount - 1] ?? self::RETRY_DELAYS[count(self::RETRY_DELAYS) - 1];
            $nextRetry = date('Y-m-d H:i:s', time() + $delay);
        }

        try {
            // Prüfen ob Eintrag existiert
            $sql->setQuery(
                'SELECT id FROM ' . \rex::getTable('mail_tools_retry') . ' WHERE log_hash = :hash',
                ['hash' => $hash],
            );

            if ($sql->getRows() > 0) {
                // Update
                $sql->setTable(\rex::getTable('mail_tools_retry'));
                $sql->setWhere(['log_hash' => $hash]);
                $sql->setValue('retry_count', $retryCount);
                $sql->setValue('last_retry_at', $now);
                $sql->setValue('next_retry_at', $nextRetry);
                $sql->setValue('last_success', $success ? 1 : 0);
                $sql->update();
            } else {
                // Insert
                $sql->setTable(\rex::getTable('mail_tools_retry'));
                $sql->setValue('log_hash', $hash);
                $sql->setValue('retry_count', $retryCount);
                $sql->setValue('last_retry_at', $now);
                $sql->setValue('next_retry_at', $nextRetry);
                $sql->setValue('last_success', $success ? 1 : 0);
                $sql->insert();
            }
        } catch (\rex_sql_exception $e) {
            \rex_logger::logException($e);
        }
    }

    /**
     * Führt alle fälligen Retries aus.
     *
     * @return array{total: int, success: int, failed: int}
     */
    public static function processRetries(): array
    {
        $retryable = self::getRetryableEmails();

        $stats = [
            'total' => count($retryable),
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($retryable as $entry) {
            $result = self::retry($entry['hash']);

            if ($result['success']) {
                ++$stats['success'];
            } else {
                ++$stats['failed'];
            }
        }

        return $stats;
    }
}
