<?php

namespace FriendsOfRedaxo\MailTools;

use rex;
use rex_addon;
use rex_sql;
use rex_sql_exception;

class BounceHandler
{
    /**
     * Verarbeitet Bounces via IMAP.
     *
     * @return array{count?: int, processed?: array<string>, error?: string}
     */
    public static function processBounces(): array
    {
        if (!function_exists('imap_open')) {
            return ['error' => 'PHP IMAP extension is not available.'];
        }

        $addon = rex_addon::get('mail_tools');
        $host = $addon->getConfig('imap_host');
        $port = $addon->getConfig('imap_port', 993);
        $user = $addon->getConfig('imap_username');
        $password = $addon->getConfig('imap_password');
        $folder = $addon->getConfig('imap_folder', 'INBOX');
        $delete = $addon->getConfig('imap_delete_bounces', false);

        if (!$host || !$user || !$password) {
            return ['error' => 'IMAP settings are incomplete.'];
        }

        $mailbox = sprintf('{%s:%d/imap/ssl}%s', $host, $port, $folder);
        
        // Unterdrücke Warnungen bei Verbindungsfehlern
        $mbox = @imap_open($mailbox, $user, $password);

        if (!$mbox) {
            return ['error' => 'Could not connect to IMAP server: ' . imap_last_error()];
        }

        // Suche nach neuen Nachrichten
        // Wir suchen nach Nachrichten, die typische Bounce-Subjects haben oder von Mailer-Daemon kommen
        $searchCriteria = 'UNSEEN FROM "MAILER-DAEMON"'; 
        
        $emails = imap_search($mbox, $searchCriteria);

        if (!$emails) {
            imap_close($mbox);
            return ['count' => 0, 'processed' => []];
        }

        $processed = [];

        foreach ($emails as $msgNo) {
            // $header = imap_headerinfo($mbox, $msgNo);
            $body = imap_body($mbox, $msgNo);
            
            // Analyse des Body auf fehlgeschlagene E-Mail-Adresse
            $bouncedEmail = self::extractEmailFromBody($body);
            
            if ($bouncedEmail) {
                self::registerBounce($bouncedEmail, $body);
                $processed[] = $bouncedEmail;
                
                if ($delete) {
                    imap_delete($mbox, $msgNo);
                }
            }
        }

        if ($delete) {
            imap_expunge($mbox);
        }

        imap_close($mbox);

        return ['count' => count($processed), 'processed' => $processed];
    }

    /**
     * Testet die IMAP-Verbindung und gibt verfügbare Ordner zurück.
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @param int $port
     * @return array{success: bool, message?: string, folders?: array<string>}
     */
    public static function testConnection(string $host, string $user, string $password, int $port = 993): array
    {
        if (!function_exists('imap_open')) {
            return ['success' => false, 'message' => 'PHP IMAP extension is not available.'];
        }

        $mailbox = sprintf('{%s:%d/imap/ssl}', $host, $port);
        
        // OP_HALFOPEN: Verbindung herstellen, aber keine Mailbox öffnen (für Ordnerliste)
        $mbox = @imap_open($mailbox, $user, $password, OP_HALFOPEN);

        if (!$mbox) {
            return ['success' => false, 'message' => 'Connection failed: ' . imap_last_error()];
        }

        $folders = [];
        $list = imap_list($mbox, $mailbox, '*');
        
        if (is_array($list)) {
            foreach ($list as $val) {
                // Entferne Server-Prefix aus Ordnernamen
                $folders[] = str_replace($mailbox, '', $val);
            }
        } else {
             return ['success' => true, 'message' => 'Connection successful, but could not list folders.', 'folders' => []];
        }

        imap_close($mbox);

        return ['success' => true, 'folders' => $folders];
    }

    private static function extractEmailFromBody(string $body): ?string
    {
        // Suche nach "Final-Recipient: rfc822; email@example.com"
        if (preg_match('/Final-Recipient:\s*rfc822;\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $body, $matches)) {
            return $matches[1];
        }
        
        // Suche nach "failed: email@example.com"
        if (preg_match('/failed:\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $body, $matches)) {
            return $matches[1];
        }

        // Suche nach "<email@example.com>:"
        if (preg_match('/<([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>:/i', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function registerBounce(string $email, string $message): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('mail_tools_bounces'));
        $sql->setValue('email', $email);
        $sql->setValue('bounce_type', 'hard'); // Annahme vorerst
        $sql->setValue('bounce_message', substr($message, 0, 65000)); // Truncate
        $sql->setValue('updated_at', date('Y-m-d H:i:s'));
        
        // Insert or Update
        try {
            $sql->setValue('created_at', date('Y-m-d H:i:s'));
            $sql->setValue('count', 1);
            $sql->insert();
        } catch (rex_sql_exception $e) {
            // Duplicate entry, update count
            $sql = rex_sql::factory();
            $sql->setQuery('UPDATE ' . rex::getTable('mail_tools_bounces') . ' SET count = count + 1, updated_at = NOW(), bounce_message = ? WHERE email = ?', [substr($message, 0, 65000), $email]);
        }
    }
}
