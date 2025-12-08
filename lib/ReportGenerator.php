<?php

namespace FriendsOfRedaxo\MailTools;

/**
 * E-Mail Fehler-Report Generator.
 *
 * Erstellt formatierte Berichte über fehlgeschlagene E-Mails.
 */
class ReportGenerator
{
    /**
     * Generiert einen HTML-Bericht.
     *
     * @param array<int, array{status: string, timestamp: int, from: string, to: string, subject: string, message: string, hash: string}> $failedEmails
     */
    public static function generateHtmlReport(array $failedEmails, array $statistics = []): string
    {
        $siteName = \rex::getServerName() ?: $_SERVER['HTTP_HOST'] ?? 'REDAXO';
        $reportDate = date('d.m.Y H:i:s');
        $count = count($failedEmails);

        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail Fehlerbericht</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .header {
            border-bottom: 3px solid #dc3545;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #dc3545;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .header .meta {
            color: #666;
            font-size: 14px;
        }
        .summary {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px 20px;
            margin-bottom: 30px;
        }
        .summary h2 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #856404;
        }
        .summary .count {
            font-size: 32px;
            font-weight: bold;
            color: #dc3545;
        }
        .error-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .error-item {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px 20px;
            margin-bottom: 15px;
            border-radius: 0 6px 6px 0;
        }
        .error-item .time {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .error-item .recipient {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        .error-item .subject {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        .error-item .message {
            background: #ffe6e6;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            margin-top: 10px;
            word-break: break-all;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 12px;
            text-align: center;
        }
        .stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 20px;
            font-weight: bold;
            color: #495057;
        }
        .stat-box .label {
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ E-Mail Fehlerbericht</h1>
            <div class="meta">
                <strong>' . rex_escape($siteName) . '</strong><br>
                Erstellt am ' . $reportDate . '
            </div>
        </div>';

        if (!empty($statistics)) {
            $html .= '
        <div class="stats">
            <div class="stat-box">
                <div class="number">' . $statistics['today'] . '</div>
                <div class="label">Heute</div>
            </div>
            <div class="stat-box">
                <div class="number">' . $statistics['week'] . '</div>
                <div class="label">Diese Woche</div>
            </div>
            <div class="stat-box">
                <div class="number">' . $statistics['month'] . '</div>
                <div class="label">Diesen Monat</div>
            </div>
            <div class="stat-box">
                <div class="number">' . $statistics['total'] . '</div>
                <div class="label">Gesamt</div>
            </div>
        </div>';
        }

        $html .= '
        <div class="summary">
            <h2>Neue fehlgeschlagene E-Mails</h2>
            <span class="count">' . $count . '</span> E-Mail(s) konnten nicht zugestellt werden
        </div>';

        if ($count > 0) {
            $html .= '<ul class="error-list">';

            foreach ($failedEmails as $entry) {
                $time = date('d.m.Y H:i:s', $entry['timestamp']);

                $html .= '
            <li class="error-item">
                <div class="time">📅 ' . rex_escape($time) . '</div>
                <div class="recipient">📧 ' . rex_escape($entry['to']) . '</div>
                <div class="subject">Betreff: ' . rex_escape($entry['subject']) . '</div>
                <div class="message">' . rex_escape($entry['message']) . '</div>
            </li>';
            }

            $html .= '</ul>';
        } else {
            $html .= '<p style="text-align: center; color: #28a745; padding: 20px;">✅ Keine neuen Fehler gefunden!</p>';
        }

        $html .= '
        <div class="footer">
            Dieser Bericht wurde automatisch von <strong>mail_tools</strong> für ' . rex_escape($siteName) . ' generiert.<br>
            <small>Powered by <a href="https://friendsofredaxo.github.io/" style="color: #666;">FriendsOfREDAXO</a></small>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Generiert einen Plain-Text-Bericht.
     *
     * @param array<int, array{status: string, timestamp: int, from: string, to: string, subject: string, message: string, hash: string}> $failedEmails
     */
    public static function generateTextReport(array $failedEmails): string
    {
        $siteName = \rex::getServerName() ?: $_SERVER['HTTP_HOST'] ?? 'REDAXO';
        $reportDate = date('d.m.Y H:i:s');
        $count = count($failedEmails);

        $text = "========================================\n";
        $text .= "E-MAIL FEHLERBERICHT\n";
        $text .= "========================================\n\n";
        $text .= "Website: {$siteName}\n";
        $text .= "Erstellt: {$reportDate}\n";
        $text .= "Anzahl Fehler: {$count}\n\n";
        $text .= "----------------------------------------\n\n";

        foreach ($failedEmails as $entry) {
            $time = date('d.m.Y H:i:s', $entry['timestamp']);

            $text .= "Zeit: {$time}\n";
            $text .= "An: {$entry['to']}\n";
            $text .= "Betreff: {$entry['subject']}\n";
            $text .= "Fehler: {$entry['message']}\n";
            $text .= "\n----------------------------------------\n\n";
        }

        $text .= "-- \n";
        $text .= "Automatisch generiert von mail_tools\n";
        $text .= "Powered by FriendsOfREDAXO - https://friendsofredaxo.github.io/\n";

        return $text;
    }

    /**
     * Sendet den Bericht per E-Mail.
     *
     * @param array<string> $recipients
     * @param array<int, array{status: string, timestamp: int, from: string, to: string, subject: string, message: string, hash: string}> $failedEmails
     */
    public static function sendReport(array $recipients, string $htmlContent, string $textContent, array $failedEmails = [], bool $attachEml = false): bool
    {
        if (empty($recipients)) {
            return false;
        }

        try {
            $mail = new \rex_mailer();
            $siteName = \rex::getServerName() ?: 'REDAXO';

            $mail->Subject = '[' . $siteName . '] E-Mail Fehlerbericht - ' . date('d.m.Y');
            $mail->Body = $htmlContent;
            $mail->AltBody = $textContent;
            $mail->isHTML(true);

            foreach ($recipients as $email) {
                $email = trim($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($email);
                }
            }

            // Optional: EML-Dateien aus dem Archiv anhängen
            if ($attachEml && !empty($failedEmails)) {
                $attachedCount = 0;
                foreach ($failedEmails as $entry) {
                    if ($attachedCount >= 10) {
                        break; // Maximal 10 Anhänge
                    }
                    $emlFile = LogParser::findArchiveFile($entry['subject'], $entry['timestamp']);
                    if ($emlFile && file_exists($emlFile)) {
                        $mail->addAttachment($emlFile);
                        ++$attachedCount;
                    }
                }
            }

            return $mail->send();
        } catch (\Exception $e) {
            \rex_logger::logException($e);
            return false;
        }
    }
}
