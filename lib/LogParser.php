<?php

namespace FriendsOfRedaxo\MailTools;

/**
 * PHPMailer Log Parser.
 *
 * Analysiert das PHPMailer-Log und extrahiert fehlgeschlagene E-Mails.
 */
class LogParser
{
    /**
     * Liest das PHPMailer-Log und gibt alle Einträge zurück.
     *
     * @param int $limit Maximale Anzahl der Einträge
     * @return array<int, array{status: string, timestamp: int, from: string, to: string, subject: string, message: string, hash: string}>
     */
    public static function getLogEntries(int $limit = 1000): array
    {
        $logFile = \rex_mailer::logFile();

        if (!file_exists($logFile)) {
            return [];
        }

        $entries = [];
        $file = \rex_log_file::factory($logFile);

        foreach (new \LimitIterator($file, 0, $limit) as $entry) {
            $data = $entry->getData();
            $timestamp = $entry->getTimestamp();

            // Hash erstellen für Deduplizierung
            $hash = md5($timestamp . ($data[1] ?? '') . ($data[2] ?? '') . ($data[3] ?? '') . ($data[4] ?? ''));

            $entries[] = [
                'status' => trim($data[0] ?? ''),
                'timestamp' => $timestamp,
                'from' => $data[1] ?? '',
                'to' => $data[2] ?? '',
                'subject' => $data[3] ?? '',
                'message' => $data[4] ?? '',
                'hash' => $hash,
            ];
        }

        return $entries;
    }

    /**
     * Gibt nur fehlgeschlagene E-Mails zurück.
     *
     * @param int $limit Maximale Anzahl der Einträge
     * @return array<int, array{status: string, timestamp: int, from: string, to: string, subject: string, message: string, hash: string}>
     */
    public static function getFailedEmails(int $limit = 1000): array
    {
        $entries = self::getLogEntries($limit);

        return array_filter($entries, static function ($entry) {
            if ('ERROR' !== $entry['status']) {
                return false;
            }
            
            // Nur Fehler ohne jeglichen Empfänger filtern 
            // (z.B. "Bitte geben Sie mindestens eine Empfängeradresse an")
            if ('' === trim($entry['to'])) {
                return false;
            }
            
            return true;
        });
    }

    /**
     * Gibt fehlgeschlagene E-Mails zurück, die noch nicht gemeldet wurden.
     *
     * @return array<int, array{status: string, timestamp: int, from: string, to: string, subject: string, message: string, hash: string}>
     */
    public static function getUnreportedFailedEmails(): array
    {
        $failed = self::getFailedEmails();

        if (empty($failed)) {
            return [];
        }

        // Bereits gemeldete Hashes laden
        $sql = \rex_sql::factory();
        $reported = $sql->getArray(
            'SELECT log_hash FROM ' . \rex::getTable('mail_tools_reported')
        );
        $reportedHashes = array_column($reported, 'log_hash');

        // Filtern
        return array_filter($failed, static function ($entry) use ($reportedHashes) {
            return !in_array($entry['hash'], $reportedHashes, true);
        });
    }

    /**
     * Markiert Einträge als gemeldet.
     *
     * @param array<int, array{hash: string, to: string, subject: string, message: string, timestamp: int}> $entries
     */
    public static function markAsReported(array $entries): void
    {
        $sql = \rex_sql::factory();
        $now = date('Y-m-d H:i:s');

        foreach ($entries as $entry) {
            try {
                $sql->setTable(\rex::getTable('mail_tools_reported'));
                $sql->setValue('log_hash', $entry['hash']);
                $sql->setValue('recipient', $entry['to']);
                $sql->setValue('subject', $entry['subject']);
                $sql->setValue('error_message', $entry['message']);
                $sql->setValue('log_timestamp', date('Y-m-d H:i:s', $entry['timestamp']));
                $sql->setValue('reported_at', $now);
                $sql->insert();
            } catch (\rex_sql_exception $e) {
                // Duplikat ignorieren (UNIQUE constraint)
            }
        }
    }

    /**
     * Extrahiert E-Mail-Adressen aus Fehlermeldungen.
     *
     * Beispiele:
     * - "The following recipients failed: test@example.com"
     * - "Die folgenden Empfänger sind nicht korrekt: test@example.com"
     *
     * @return array<string>
     */
    public static function extractFailedRecipients(string $errorMessage): array
    {
        $emails = [];

        // Regex für E-Mail-Adressen
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $errorMessage, $matches)) {
            $emails = array_unique($matches[0]);
        }

        return $emails;
    }

    /**
     * Sucht die archivierte EML-Datei zu einem Log-Eintrag.
     *
     * PHPMailer speichert Archiv-Dateien im Format:
     * data/addons/phpmailer/mail_log/YYYY/MM/STATUS_YYYY-MM-DD_HH_ii_ss.eml
     */
    public static function findArchiveFile(string $subject, int $timestamp): ?string
    {
        $archiveFolder = \rex_mailer::logFolder();

        if (!is_dir($archiveFolder)) {
            return null;
        }

        // Pfad basierend auf Timestamp: YYYY/MM/
        $year = date('Y', $timestamp);
        $month = date('m', $timestamp);
        $monthFolder = $archiveFolder . '/' . $year . '/' . $month;

        if (!is_dir($monthFolder)) {
            return null;
        }

        // Dateiname-Muster: ERROR_YYYY-MM-DD_HH_ii_ss.eml
        $datePattern = date('Y-m-d_H_i', $timestamp); // Ohne Sekunden für Toleranz

        // Suche nach passender .eml Datei
        $files = glob($monthFolder . '/*.eml');
        if (!$files) {
            return null;
        }

        // Erst versuchen über Timestamp zu matchen
        foreach ($files as $file) {
            $filename = basename($file);
            // Datei enthält das Datum im Namen
            if (str_contains($filename, $datePattern)) {
                return $file;
            }
        }

        // Fallback: Zeitfenster von ±60 Sekunden prüfen
        foreach ($files as $file) {
            $filename = basename($file);

            // Timestamp aus Dateiname extrahieren: STATUS_YYYY-MM-DD_HH_ii_ss.eml
            if (preg_match('/_(\d{4}-\d{2}-\d{2}_\d{2}_\d{2}_\d{2})\.eml$/', $filename, $matches)) {
                $fileDate = str_replace('_', '-', $matches[1]);
                $fileDate = preg_replace('/-(\d{2})-(\d{2})-(\d{2})$/', ' $1:$2:$3', $fileDate);
                $fileTimestamp = strtotime($fileDate);

                if ($fileTimestamp && abs($fileTimestamp - $timestamp) <= 60) {
                    return $file;
                }
            }
        }

        // Letzter Fallback: Inhalt der EML prüfen (Betreff)
        $normalizedSubject = strtolower(trim($subject));
        foreach ($files as $file) {
            $content = \rex_file::get($file);
            if ($content && str_contains(strtolower($content), $normalizedSubject)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Statistik über fehlgeschlagene E-Mails.
     *
     * @return array{total: int, today: int, week: int, month: int, top_domains: array<string, int>}
     */
    public static function getStatistics(): array
    {
        $failed = self::getFailedEmails(10000);

        $now = time();
        $todayStart = strtotime('today');
        $weekStart = strtotime('-7 days');
        $monthStart = strtotime('-30 days');

        $stats = [
            'total' => count($failed),
            'today' => 0,
            'week' => 0,
            'month' => 0,
            'top_domains' => [],
        ];

        $domainCounts = [];

        foreach ($failed as $entry) {
            if ($entry['timestamp'] >= $todayStart) {
                ++$stats['today'];
            }
            if ($entry['timestamp'] >= $weekStart) {
                ++$stats['week'];
            }
            if ($entry['timestamp'] >= $monthStart) {
                ++$stats['month'];
            }

            // Domain aus Empfänger extrahieren
            $recipients = self::extractFailedRecipients($entry['to'] . ' ' . $entry['message']);
            foreach ($recipients as $email) {
                $domain = DomainValidator::extractDomain($email);
                if ('' !== $domain) {
                    $domainCounts[$domain] = ($domainCounts[$domain] ?? 0) + 1;
                }
            }
        }

        arsort($domainCounts);
        $stats['top_domains'] = array_slice($domainCounts, 0, 10, true);

        return $stats;
    }
}
