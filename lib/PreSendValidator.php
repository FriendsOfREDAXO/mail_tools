<?php

namespace FriendsOfRedaxo\MailTools;

use rex_addon;
use rex_extension_point;
use rex_mailer;

/**
 * Pre-Send Validator.
 *
 * Prüft E-Mail-Adressen vor dem Versand und verhindert Sendeversuche
 * an nicht existierende Domains.
 */
class PreSendValidator
{
    /**
     * Extension Point Handler für PHPMAILER_PRE_SEND.
     *
     * Prüft alle Empfänger-Domains vor dem Versand.
     * Bei ungültigen Domains wird der Versand abgebrochen und geloggt.
     *
     * @param rex_extension_point<rex_mailer> $ep
     */
    public static function validate(rex_extension_point $ep): void
    {
        /** @var rex_mailer $mailer */
        $mailer = $ep->getSubject();

        // Prüfen ob Validierung aktiviert ist
        $addon = rex_addon::get('mail_tools');
        if (!$addon->getConfig('validate_domains', true)) {
            return;
        }

        // Alle Empfänger sammeln (To, Cc, Bcc)
        $allRecipients = [];
        
        foreach ($mailer->getToAddresses() as $address) {
            $allRecipients[] = ['type' => 'to', 'email' => $address[0], 'name' => $address[1]];
        }
        foreach ($mailer->getCcAddresses() as $address) {
            $allRecipients[] = ['type' => 'cc', 'email' => $address[0], 'name' => $address[1]];
        }
        foreach ($mailer->getBccAddresses() as $address) {
            $allRecipients[] = ['type' => 'bcc', 'email' => $address[0], 'name' => $address[1]];
        }

        $invalidRecipients = [];

        // Blockierte TLDs laden
        $blockedTlds = self::getBlockedTlds();

        foreach ($allRecipients as $recipient) {
            $email = $recipient['email'];
            $domain = DomainValidator::extractDomain($email);

            if ('' === $domain) {
                $invalidRecipients[] = [
                    'email' => $email,
                    'reason' => 'invalid_format',
                    'message' => 'Invalid email format',
                ];
                continue;
            }

            // TLD-Prüfung
            $tld = self::extractTld($domain);
            if (in_array($tld, $blockedTlds, true)) {
                $invalidRecipients[] = [
                    'email' => $email,
                    'domain' => $domain,
                    'reason' => 'blocked_tld',
                    'message' => 'Blocked TLD: .' . $tld,
                ];
                continue;
            }

            // Domain-Prüfung
            if (!DomainValidator::isDomainValid($domain)) {
                $invalidRecipients[] = [
                    'email' => $email,
                    'domain' => $domain,
                    'reason' => 'domain_not_found',
                    'message' => 'Domain does not exist: ' . $domain,
                ];
            }
        }

        // Bei ungültigen Empfängern: Fehler loggen und Versand abbrechen
        if (!empty($invalidRecipients)) {
            self::logInvalidRecipients($mailer, $invalidRecipients);
            self::abortSend($mailer, $invalidRecipients);
        }
    }

    /**
     * Extrahiert die TLD aus einer Domain.
     */
    private static function extractTld(string $domain): string
    {
        $parts = explode('.', strtolower($domain));
        return end($parts) ?: '';
    }

    /**
     * Gibt die Liste der blockierten TLDs zurück.
     *
     * @return array<string>
     */
    private static function getBlockedTlds(): array
    {
        $addon = rex_addon::get('mail_tools');
        $blockedTldsString = $addon->getConfig('blocked_tlds', '');
        
        if ('' === $blockedTldsString) {
            return [];
        }
        
        // Komma- oder zeilengetrennt, mit oder ohne Punkt
        $tlds = preg_split('/[\s,]+/', $blockedTldsString, -1, PREG_SPLIT_NO_EMPTY);
        
        return array_map(static function ($tld) {
            return ltrim(strtolower(trim($tld)), '.');
        }, $tlds ?: []);
    }

    /**
     * Loggt ungültige Empfänger ins PHPMailer-Log.
     *
     * @param rex_mailer $mailer
     * @param array<array{email: string, reason: string, message: string, domain?: string}> $invalidRecipients
     */
    private static function logInvalidRecipients(rex_mailer $mailer, array $invalidRecipients): void
    {
        $emails = array_column($invalidRecipients, 'email');
        $messages = array_column($invalidRecipients, 'message');

        // Fehlermeldung zusammenbauen
        $errorMsg = 'Invalid domain(s): ' . implode('; ', $messages);

        // Direkt ins PHPMailer-Logfile schreiben (wie rex_mailer::log())
        $logFile = \rex_mailer::logFile();
        $log = \rex_log_file::factory($logFile, 2_000_000);

        // Alle Empfänger für das Log sammeln (To, Cc, Bcc)
        $allRecipients = [];
        foreach ($mailer->getToAddresses() as $address) {
            $allRecipients[] = $address[0];
        }
        foreach ($mailer->getCcAddresses() as $address) {
            $allRecipients[] = $address[0];
        }
        foreach ($mailer->getBccAddresses() as $address) {
            $allRecipients[] = $address[0];
        }

        // Reply-To für From-Feld
        $replytos = '';
        if (count($mailer->getReplyToAddresses()) > 0) {
            $replytos = implode(', ', array_column($mailer->getReplyToAddresses(), 0));
        }

        // Log-Eintrag im PHPMailer-Format
        $data = [
            'ERROR',
            $mailer->From . ($replytos ? '; reply-to: ' . $replytos : ''),
            implode(', ', $allRecipients),
            $mailer->Subject,
            $errorMsg,
        ];
        $log->add($data);

        // Auch in REDAXO System-Log schreiben
        \rex_logger::factory()->log(
            'warning',
            'mail_tools: Blocked email to invalid domain(s): ' . implode(', ', $emails),
            [],
            __FILE__,
            __LINE__
        );
    }

    /**
     * Bricht den Versand ab indem alle Empfänger entfernt werden.
     *
     * @param rex_mailer $mailer
     * @param array<array{email: string, reason: string, message: string}> $invalidRecipients
     */
    private static function abortSend(rex_mailer $mailer, array $invalidRecipients): void
    {
        $addon = rex_addon::get('mail_tools');
        $blockMode = $addon->getConfig('invalid_domain_action', 'block_invalid');

        $emails = array_column($invalidRecipients, 'email');
        $messages = array_column($invalidRecipients, 'message');

        if ('block_all' === $blockMode) {
            // Alle Empfänger entfernen = Versand schlägt fehl
            $mailer->clearAllRecipients();
            
            // Fehlermeldung setzen die vom PHPMailer Log erkannt wird
            $mailer->ErrorInfo = 'SMTP Error: The following recipients failed: ' . implode(', ', $emails) . ' - ' . implode('; ', $messages);
        } elseif ('block_invalid' === $blockMode) {
            // Nur ungültige entfernen, Rest senden
            // PHPMailer hat keine direkte Methode zum Entfernen einzelner Empfänger
            // Daher: Alle entfernen und gültige wieder hinzufügen
            self::removeInvalidRecipients($mailer, $emails);
        }
        // 'log_only' = nichts tun, nur loggen (Versand wird versucht)
    }

    /**
     * Entfernt nur ungültige Empfänger aus dem Mailer.
     *
     * @param rex_mailer $mailer
     * @param array<string> $invalidEmails
     */
    private static function removeInvalidRecipients(rex_mailer $mailer, array $invalidEmails): void
    {
        // Gültige Empfänger zwischenspeichern
        $validTo = [];
        $validCc = [];
        $validBcc = [];

        foreach ($mailer->getToAddresses() as $address) {
            if (!in_array($address[0], $invalidEmails, true)) {
                $validTo[] = $address;
            }
        }
        foreach ($mailer->getCcAddresses() as $address) {
            if (!in_array($address[0], $invalidEmails, true)) {
                $validCc[] = $address;
            }
        }
        foreach ($mailer->getBccAddresses() as $address) {
            if (!in_array($address[0], $invalidEmails, true)) {
                $validBcc[] = $address;
            }
        }

        // Alle entfernen
        $mailer->clearAllRecipients();

        // Gültige wieder hinzufügen
        foreach ($validTo as $address) {
            $mailer->addAddress($address[0], $address[1]);
        }
        foreach ($validCc as $address) {
            $mailer->addCC($address[0], $address[1]);
        }
        foreach ($validBcc as $address) {
            $mailer->addBCC($address[0], $address[1]);
        }

        // Wenn keine gültigen Empfänger übrig: Fehler setzen
        if (empty($validTo) && empty($validCc) && empty($validBcc)) {
            $mailer->ErrorInfo = 'All recipients have invalid domains';
        }
    }

    /**
     * Registriert den Extension Point Handler.
     */
    public static function register(): void
    {
        \rex_extension::register('PHPMAILER_PRE_SEND', [self::class, 'validate']);
    }
}
