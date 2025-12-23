<?php

namespace FriendsOfRedaxo\MailTools;

use rex_mailer;

class GdprTools
{
    /**
     * Anonymisiert E-Mail-Adressen in Logs, die älter als X Tage sind.
     *
     * @param int $days Tage, nach denen anonymisiert werden soll.
     * @return int Anzahl der anonymisierten Einträge.
     */
    public static function anonymizeLogs(int $days = 30): int
    {
        $logFile = rex_mailer::logFile();
        if (!file_exists($logFile)) {
            return 0;
        }

        $lines = file($logFile);
        if (!$lines) {
            return 0;
        }

        $cutoffTimestamp = time() - ($days * 86400);
        $modified = false;
        $count = 0;

        foreach ($lines as &$line) {
            // rex_log_file Format: YYYY-MM-DD HH:MM:SS | ...
            $logDate = substr($line, 0, 19); 
            $timestamp = strtotime($logDate);
            
            if ($timestamp && $timestamp < $cutoffTimestamp) {
                // Anonymisiere E-Mails in dieser Zeile
                $newLine = preg_replace_callback('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', function($matches) {
                    return self::maskEmail($matches[0]);
                }, $line);
                
                if ($newLine !== $line) {
                    $line = $newLine;
                    $modified = true;
                    $count++;
                }
            }
        }

        if ($modified) {
            file_put_contents($logFile, implode('', $lines));
        }

        return $count;
    }

    /**
     * Maskiert eine E-Mail-Adresse (t***@example.com).
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        if (strlen($name) <= 1) {
             $maskedName = '***';
        } else {
             $maskedName = substr($name, 0, 1) . '***';
        }
        
        return $maskedName . '@' . $domain;
    }

    /**
     * Exportiert Log-Daten für eine spezifische E-Mail-Adresse.
     *
     * @param string $email
     * @return array<int, array<string, mixed>>
     */
    public static function exportLogsForEmail(string $email): array
    {
        $entries = LogParser::getLogEntries(10000); // Limit hoch setzen
        $found = [];

        foreach ($entries as $entry) {
            if (strpos($entry['to'], $email) !== false || strpos($entry['from'], $email) !== false) {
                $found[] = $entry;
            }
        }

        return $found;
    }
}
