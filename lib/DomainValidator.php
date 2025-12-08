<?php

namespace FriendsOfRedaxo\MailTools;

/**
 * E-Mail Domain Validator.
 *
 * Prüft ob die Domain einer E-Mail-Adresse existiert und einen Mailserver hat.
 */
class DomainValidator
{
    /** @var int DNS-Timeout in Sekunden */
    private static int $timeout = 5;

    /**
     * Setzt den DNS-Timeout.
     */
    public static function setTimeout(int $seconds): void
    {
        self::$timeout = $seconds;
    }

    /**
     * Validiert eine E-Mail-Adresse vollständig.
     *
     * @param string $email Die zu prüfende E-Mail-Adresse
     * @return array{valid: bool, syntax: bool, domain: bool, mx: bool, message: string}
     */
    public static function validate(string $email): array
    {
        $result = [
            'valid' => false,
            'syntax' => false,
            'domain' => false,
            'mx' => false,
            'message' => '',
        ];

        // 1. Syntax-Prüfung
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = 'Ungültige E-Mail-Syntax';
            return $result;
        }
        $result['syntax'] = true;

        // 2. Domain extrahieren
        $domain = self::extractDomain($email);
        if ('' === $domain) {
            $result['message'] = 'Domain konnte nicht extrahiert werden';
            return $result;
        }

        // 3. Domain/DNS prüfen (A oder AAAA Record)
        if (!self::checkDomain($domain)) {
            $result['message'] = 'Domain existiert nicht: ' . $domain;
            return $result;
        }
        $result['domain'] = true;

        // 4. MX-Record prüfen
        if (!self::checkMx($domain)) {
            // Fallback: Manche Domains haben keinen MX, akzeptieren aber Mail auf A-Record
            $result['message'] = 'Kein Mailserver (MX) für Domain: ' . $domain;
            // Trotzdem als "bedingt gültig" markieren
            $result['valid'] = true;
            return $result;
        }
        $result['mx'] = true;

        $result['valid'] = true;
        $result['message'] = 'E-Mail-Adresse ist gültig';

        return $result;
    }

    /**
     * Schnelle Validierung - gibt nur true/false zurück.
     *
     * @param string $email Die zu prüfende E-Mail-Adresse
     * @param bool $requireMx Ob ein MX-Record erforderlich ist
     */
    public static function isValid(string $email, bool $requireMx = false): bool
    {
        $result = self::validate($email);

        if ($requireMx) {
            return $result['valid'] && $result['mx'];
        }

        return $result['valid'];
    }

    /**
     * Prüft nur die Domain-Existenz (ohne Syntax-Check).
     */
    public static function isDomainValid(string $domain): bool
    {
        return self::checkDomain($domain);
    }

    /**
     * Prüft ob ein MX-Record existiert.
     */
    public static function hasMxRecord(string $domain): bool
    {
        return self::checkMx($domain);
    }

    /**
     * Extrahiert die Domain aus einer E-Mail-Adresse.
     */
    public static function extractDomain(string $email): string
    {
        $atPos = strrpos($email, '@');
        if (false === $atPos) {
            return '';
        }
        return strtolower(substr($email, $atPos + 1));
    }

    /**
     * Prüft ob die Domain existiert (A oder AAAA Record).
     */
    private static function checkDomain(string $domain): bool
    {
        // Versuche A-Record
        if (checkdnsrr($domain, 'A')) {
            return true;
        }
        // Versuche AAAA-Record (IPv6)
        if (checkdnsrr($domain, 'AAAA')) {
            return true;
        }
        return false;
    }

    /**
     * Prüft ob ein MX-Record existiert.
     */
    private static function checkMx(string $domain): bool
    {
        return checkdnsrr($domain, 'MX');
    }

    /**
     * Holt alle MX-Records einer Domain.
     *
     * @return array<string, int> Hostname => Priority
     */
    public static function getMxRecords(string $domain): array
    {
        $hosts = [];
        $weights = [];

        if (getmxrr($domain, $hosts, $weights)) {
            return array_combine($hosts, $weights) ?: [];
        }

        return [];
    }

    /**
     * Prüft mehrere E-Mail-Adressen auf einmal.
     *
     * @param array<string> $emails
     * @return array<string, array{valid: bool, message: string}>
     */
    public static function validateMultiple(array $emails): array
    {
        $results = [];
        foreach ($emails as $email) {
            $validation = self::validate($email);
            $results[$email] = [
                'valid' => $validation['valid'],
                'message' => $validation['message'],
            ];
        }
        return $results;
    }
}
