<?php

namespace FriendsOfRedaxo\MailTools;

/**
 * Generiert E-Mail-Statistiken aus dem PHPMailer-Log.
 */
class StatisticsGenerator
{
    /** @var array<int, array<string, mixed>> */
    private array $entries;

    /** @var string Eigene Server-Domain (wird aus Statistiken ausgeschlossen) */
    private string $ownDomain;

    public function __construct()
    {
        $this->entries = LogParser::getLogEntries(10000);
        $this->ownDomain = $this->extractOwnDomain();
    }

    /**
     * Ermittelt die eigene Server-Domain.
     */
    private function extractOwnDomain(): string
    {
        $serverName = \rex::getServerName();
        if (empty($serverName)) {
            return '';
        }

        // Domain aus URL extrahieren
        $parsed = parse_url($serverName);
        $host = $parsed['host'] ?? $serverName;

        // Subdomain entfernen (www.example.com -> example.com)
        $parts = explode('.', $host);
        if (count($parts) > 2) {
            return implode('.', array_slice($parts, -2));
        }

        return $host;
    }

    /**
     * Heatmap-Daten: Versandzeiten nach Wochentag und Stunde.
     *
     * @return array<int, array<int, int>> [Wochentag 0-6][Stunde 0-23] => Anzahl
     */
    public function getHeatmapData(): array
    {
        // Initialisieren: 7 Tage x 24 Stunden
        $heatmap = [];
        for ($day = 0; $day < 7; ++$day) {
            $heatmap[$day] = array_fill(0, 24, 0);
        }

        foreach ($this->entries as $entry) {
            $timestamp = $entry['timestamp'];
            $dayOfWeek = (int) date('w', $timestamp); // 0 = Sonntag
            $hour = (int) date('G', $timestamp); // 0-23

            ++$heatmap[$dayOfWeek][$hour];
        }

        return $heatmap;
    }

    /**
     * Top Empfänger-Domains (ohne eigene Domain).
     *
     * @return array<string, int> Domain => Anzahl
     */
    public function getTopRecipientDomains(int $limit = 10): array
    {
        $domains = [];

        foreach ($this->entries as $entry) {
            $email = $entry['to'] ?? '';
            $domain = $this->extractDomain($email);

            if ('' === $domain) {
                continue;
            }

            // Eigene Domain ausschließen
            if ($this->isOwnDomain($domain)) {
                continue;
            }

            $domains[$domain] = ($domains[$domain] ?? 0) + 1;
        }

        arsort($domains);

        return array_slice($domains, 0, $limit, true);
    }

    /**
     * Top Absender-Domains (Reply-To, dann From - ohne eigene Domain).
     *
     * Bei Kontaktformularen ist das die Domain der Besucher.
     *
     * @return array<string, int> Domain => Anzahl
     */
    public function getTopSenderDomains(int $limit = 10): array
    {
        $domains = [];

        foreach ($this->entries as $entry) {
            // Reply-To bevorzugen, sonst From
            $email = $entry['reply_to'] ?? $entry['from'] ?? '';
            $domain = $this->extractDomain($email);

            if ('' === $domain) {
                continue;
            }

            // Eigene Domain ausschließen
            if ($this->isOwnDomain($domain)) {
                continue;
            }

            $domains[$domain] = ($domains[$domain] ?? 0) + 1;
        }

        arsort($domains);

        return array_slice($domains, 0, $limit, true);
    }

    /**
     * Top Betreff-Patterns (normalisiert).
     *
     * @return array<string, int> Betreff => Anzahl
     */
    public function getTopSubjects(int $limit = 10): array
    {
        $subjects = [];

        foreach ($this->entries as $entry) {
            $subject = $entry['subject'] ?? '';
            if ('' === $subject) {
                continue;
            }

            // Betreff normalisieren (Zahlen, Daten, IDs entfernen)
            $normalized = $this->normalizeSubject($subject);

            $subjects[$normalized] = ($subjects[$normalized] ?? 0) + 1;
        }

        arsort($subjects);

        return array_slice($subjects, 0, $limit, true);
    }

    /**
     * Normalisiert einen Betreff für Gruppierung.
     *
     * Entfernt variable Teile wie Nummern, Daten, IDs.
     */
    private function normalizeSubject(string $subject): string
    {
        // Daten entfernen (verschiedene Formate)
        $subject = preg_replace('/\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}/', '[DATUM]', $subject) ?? $subject;

        // Uhrzeiten entfernen
        $subject = preg_replace('/\d{1,2}:\d{2}(:\d{2})?/', '[ZEIT]', $subject) ?? $subject;

        // Lange Zahlenfolgen (IDs, Bestellnummern) ersetzen
        $subject = preg_replace('/\b\d{4,}\b/', '[ID]', $subject) ?? $subject;

        // Kurze Zahlen (1-3 Stellen) behalten, da oft bedeutungsvoll

        // Mehrfache Leerzeichen normalisieren
        $subject = preg_replace('/\s+/', ' ', $subject) ?? $subject;

        return trim($subject);
    }

    /**
     * Erfolgs- und Fehler-Statistiken.
     *
     * @return array{total: int, success: int, failed: int, success_rate: float}
     */
    public function getSuccessStats(): array
    {
        $total = count($this->entries);
        $failed = 0;
        $success = 0;

        foreach ($this->entries as $entry) {
            $status = strtolower($entry['status'] ?? '');
            if ('ok' === $status || str_contains($status, 'success')) {
                ++$success;
            } else {
                ++$failed;
            }
        }

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Statistiken nach Zeitraum.
     *
     * @return array{today: int, week: int, month: int}
     */
    public function getTimeStats(): array
    {
        $now = time();
        $today = strtotime('today');
        $weekAgo = strtotime('-7 days');
        $monthAgo = strtotime('-30 days');

        $stats = ['today' => 0, 'week' => 0, 'month' => 0];

        foreach ($this->entries as $entry) {
            $timestamp = $entry['timestamp'];

            if ($timestamp >= $today) {
                ++$stats['today'];
            }
            if ($timestamp >= $weekAgo) {
                ++$stats['week'];
            }
            if ($timestamp >= $monthAgo) {
                ++$stats['month'];
            }
        }

        return $stats;
    }

    /**
     * Versand pro Tag (letzte 30 Tage).
     *
     * @return array<string, int> Datum (Y-m-d) => Anzahl
     */
    public function getDailyStats(int $days = 30): array
    {
        $cutoff = strtotime("-{$days} days");
        $daily = [];

        // Alle Tage initialisieren
        for ($i = $days - 1; $i >= 0; --$i) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $daily[$date] = 0;
        }

        foreach ($this->entries as $entry) {
            $timestamp = $entry['timestamp'];
            if ($timestamp < $cutoff) {
                continue;
            }

            $date = date('Y-m-d', $timestamp);
            if (isset($daily[$date])) {
                ++$daily[$date];
            }
        }

        return $daily;
    }

    /**
     * Extrahiert die Domain aus einer E-Mail-Adresse.
     */
    private function extractDomain(string $email): string
    {
        // E-Mail aus Format "Name <email@domain.com>" extrahieren
        if (preg_match('/<([^>]+)>/', $email, $matches)) {
            $email = $matches[1];
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '';
        }

        return strtolower(trim($parts[1]));
    }

    /**
     * Prüft ob eine Domain zur eigenen gehört.
     */
    private function isOwnDomain(string $domain): bool
    {
        if ('' === $this->ownDomain) {
            return false;
        }

        // Exakter Match oder Subdomain
        return $domain === $this->ownDomain
            || str_ends_with($domain, '.' . $this->ownDomain);
    }

    /**
     * Gibt die erkannte eigene Domain zurück.
     */
    public function getOwnDomain(): string
    {
        return $this->ownDomain;
    }

    /**
     * Setzt die eigene Domain manuell (für Filter).
     */
    public function setOwnDomain(string $domain): self
    {
        $this->ownDomain = strtolower(trim($domain));
        return $this;
    }
}
