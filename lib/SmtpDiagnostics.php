<?php

namespace FriendsOfREDAXO\MailTools;

use rex;
use rex_addon;
use rex_i18n;
use rex_mailer;

/**
 * SMTP Diagnose-Tool für PHPMailer
 * Analysiert Konfiguration, testet Verbindungen und gibt verständliche Hilfestellungen
 */
class SmtpDiagnostics
{
    /** @var array<string, mixed> */
    private array $results = [];
    
    /** @var array<string, mixed> */
    private array $config = [];
    
    /** @var array<string, string> */
    private array $suggestions = [];

    /** @var bool */
    private bool $isSecure = false;
    
    // Bekannte Provider-Konfigurationen
    private const KNOWN_PROVIDERS = [
        'microsoft365' => [
            'hosts' => ['smtp.office365.com', 'smtp-mail.outlook.com'],
            'domains' => ['outlook.com', 'outlook.de', 'hotmail.com', 'hotmail.de', 'live.com', 'live.de', 'msn.com'],
            'port' => 587,
            'security' => 'tls',
            'auth' => true,
            'name' => 'Microsoft 365 / Outlook',
            'help_url' => 'https://support.microsoft.com/office',
            'notes' => [
                'OAuth2 oder App-Kennwort erforderlich',
                'Standard-Passwort funktioniert nicht bei aktivierter MFA',
                'Absender muss mit authentifiziertem Konto übereinstimmen',
            ],
        ],
        'gmail' => [
            'hosts' => ['smtp.gmail.com', 'smtp.googlemail.com'],
            'domains' => ['gmail.com', 'googlemail.com'],
            'port' => 587,
            'security' => 'tls',
            'auth' => true,
            'name' => 'Google Gmail / Workspace',
            'help_url' => 'https://support.google.com/mail',
            'notes' => [
                'App-Kennwort erforderlich (nicht das normale Passwort)',
                '2-Faktor-Authentifizierung muss aktiviert sein',
                'App-Kennwort unter myaccount.google.com/apppasswords erstellen',
            ],
        ],
        'icloud' => [
            'hosts' => ['smtp.mail.me.com'],
            'domains' => ['icloud.com', 'me.com', 'mac.com'],
            'port' => 587,
            'security' => 'tls',
            'auth' => true,
            'name' => 'Apple iCloud',
            'help_url' => 'https://support.apple.com/de-de/102525',
            'notes' => [
                'App-spezifisches Passwort erforderlich',
                'Unter appleid.apple.com → Anmeldung und Sicherheit → App-spezifische Passwörter erstellen',
                '2-Faktor-Authentifizierung muss aktiviert sein',
            ],
        ],
        'ionos' => [
            'hosts' => ['smtp.ionos.de', 'smtp.ionos.com', 'smtp.1und1.de'],
            'domains' => ['ionos.de', '1und1.de'],
            'port' => 587,
            'security' => 'tls',
            'auth' => true,
            'name' => 'IONOS / 1&1',
            'help_url' => 'https://www.ionos.de/hilfe/',
            'notes' => [
                'E-Mail-Adresse als Benutzername verwenden',
                'SMTP muss im Hosting-Vertrag enthalten sein',
            ],
        ],
        'strato' => [
            'hosts' => ['smtp.strato.de', 'smtp.strato.com'],
            'domains' => ['strato.de'],
            'port' => 465,
            'security' => 'ssl',
            'auth' => true,
            'name' => 'Strato',
            'help_url' => 'https://www.strato.de/faq/',
            'notes' => [
                'Port 465 mit SSL verwenden',
                'E-Mail-Adresse als Benutzername',
            ],
        ],
        'hosteurope' => [
            'hosts' => ['smtp.hosteurope.de'],
            'domains' => ['hosteurope.de'],
            'port' => 587,
            'security' => 'tls',
            'auth' => true,
            'name' => 'Host Europe',
            'help_url' => 'https://www.hosteurope.de/faq/',
            'notes' => [],
        ],
        'allinkl' => [
            'hosts' => ['smtp.all-inkl.com'],
            'domains' => ['all-inkl.com'],
            'port' => 587,
            'security' => 'tls',
            'auth' => true,
            'name' => 'All-Inkl',
            'help_url' => 'https://all-inkl.com/wichtig/anleitungen/',
            'notes' => [
                'KAS-Benutzername oder E-Mail-Adresse verwenden',
            ],
        ],
        'gmx' => [
            'hosts' => ['mail.gmx.net'],
            'domains' => ['gmx.de', 'gmx.net', 'gmx.at', 'gmx.ch'],
            'port' => 587,
            'security' => 'tls',
            'auth' => true,
            'name' => 'GMX',
            'help_url' => 'https://hilfe.gmx.net/',
            'notes' => [
                'SMTP-Versand muss in den GMX-Einstellungen aktiviert werden',
                'E-Mail-Adresse als Benutzername',
            ],
        ],
        'webde' => [
            'hosts' => ['smtp.web.de'],
            'domains' => ['web.de'],
            'port' => 587,
            'security' => 'tls',
            'auth' => true,
            'name' => 'WEB.DE',
            'help_url' => 'https://hilfe.web.de/',
            'notes' => [
                'SMTP-Versand muss in den WEB.DE-Einstellungen aktiviert werden',
                'E-Mail-Adresse als Benutzername',
            ],
        ],
    ];

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Lädt die aktuelle PHPMailer-Konfiguration
     */
    private function loadConfig(): void
    {
        $phpmailer = rex_addon::get('phpmailer');
        
        $this->config = [
            'mailer' => $phpmailer->getConfig('mailer', 'mail'),
            'host' => $phpmailer->getConfig('host', ''),
            'port' => (int) $phpmailer->getConfig('port', 25),
            'security' => $phpmailer->getConfig('smtpsecure', ''),
            'security_mode' => (bool) $phpmailer->getConfig('security_mode', true), // Auto-TLS
            'auth' => (bool) $phpmailer->getConfig('smtp_auth', false),
            'username' => $phpmailer->getConfig('username', ''),
            'password' => $phpmailer->getConfig('password', ''),
            'from' => $phpmailer->getConfig('from', ''),
            'fromname' => $phpmailer->getConfig('fromname', ''),
            'confirm_to' => $phpmailer->getConfig('confirm_to', ''),
            'encoding' => $phpmailer->getConfig('encoding', '8bit'),
            'priority' => $phpmailer->getConfig('priority', 0),
            'smtp_debug' => $phpmailer->getConfig('smtp_debug', '0'),
            'timeout' => (int) $phpmailer->getConfig('timeout', 10),
        ];
    }

    /**
     * Führt vollständige Diagnose durch
     * @return array<string, mixed>
     */
    public function runFullDiagnosis(): array
    {
        $this->results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'config' => $this->getConfigSummary(),
            'checks' => [],
            'provider' => null,
            'overall_status' => 'unknown',
            'recommendations' => [],
        ];

        // 1. Basis-Konfigurationsprüfung
        $this->checkBasicConfig();
        
        // 2. Provider erkennen
        $this->detectProvider();
        
        // 3. DNS/MX-Prüfung
        $this->checkDnsRecords();
        
        // 4. Port-Erreichbarkeit
        $this->checkPortConnectivity();
        
        // 5. SSL/TLS-Zertifikat
        $this->checkSslCertificate();
        
        // 6. SMTP-Handshake
        $this->checkSmtpHandshake();
        
        // 7. Authentifizierung
        $this->checkAuthentication();
        
        // 8. Provider-spezifische Checks
        $this->runProviderSpecificChecks();
        
        // Gesamtstatus berechnen
        $this->calculateOverallStatus();
        
        // Empfehlungen generieren
        $this->generateRecommendations();
        
        return $this->results;
    }

    /**
     * Gibt Konfigurations-Zusammenfassung zurück (ohne Passwort)
     * @return array<string, mixed>
     */
    private function getConfigSummary(): array
    {
        $summary = $this->config;
        $summary['password'] = $this->config['password'] ? '***' : '(leer)';
        return $summary;
    }

    /**
     * Basis-Konfigurationsprüfung
     */
    private function checkBasicConfig(): void
    {
        $check = [
            'name' => 'basic_config',
            'title' => rex_i18n::msg('mail_tools_diag_basic_config'),
            'status' => 'ok',
            'details' => [],
            'issues' => [],
        ];

        // Mailer-Typ
        if ($this->config['mailer'] === 'mail') {
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_mailer_type'),
                'value' => 'PHP mail()',
                'status' => 'warning',
                'hint' => rex_i18n::msg('mail_tools_diag_php_mail_hint'),
            ];
            $check['status'] = 'warning';
        } elseif ($this->config['mailer'] === 'smtp') {
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_mailer_type'),
                'value' => 'SMTP',
                'status' => 'ok',
            ];
        } elseif ($this->config['mailer'] === 'sendmail') {
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_mailer_type'),
                'value' => 'Sendmail',
                'status' => 'info',
            ];
        }

        // SMTP-Host
        if ($this->config['mailer'] === 'smtp') {
            if (empty($this->config['host'])) {
                $check['issues'][] = rex_i18n::msg('mail_tools_diag_no_host');
                $check['status'] = 'error';
            } else {
                $check['details'][] = [
                    'label' => rex_i18n::msg('mail_tools_diag_smtp_host'),
                    'value' => $this->config['host'],
                    'status' => 'ok',
                ];
            }

            // Port
            $port = $this->config['port'];
            $security = $this->config['security'];
            $autoTls = $this->config['security_mode'];
            $portStatus = 'ok';
            $portHint = '';

            if ($port === 25) {
                $portStatus = 'warning';
                $portHint = rex_i18n::msg('mail_tools_diag_port_25_hint');
            } elseif ($port === 465 && $security !== 'ssl') {
                $portStatus = 'warning';
                $portHint = rex_i18n::msg('mail_tools_diag_port_465_needs_ssl');
            } elseif ($port === 587 && $security !== 'tls' && !$autoTls) {
                // Port 587 braucht TLS, aber Auto-TLS ist auch OK
                $portStatus = 'warning';
                $portHint = rex_i18n::msg('mail_tools_diag_port_587_needs_tls');
            }

            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_port'),
                'value' => $port,
                'status' => $portStatus,
                'hint' => $portHint,
            ];

            // Verschlüsselung
            $security = $this->config['security'];
            $autoTls = $this->config['security_mode'];
            
            // Label und Status basierend auf Konfiguration
            if ($security === 'ssl') {
                $securityLabel = 'SSL/TLS (implicit)';
                $securityStatus = 'ok';
                $securityHint = '';
            } elseif ($security === 'tls') {
                $securityLabel = 'STARTTLS (explicit)';
                $securityStatus = 'ok';
                $securityHint = '';
            } elseif ($autoTls) {
                // Auto-TLS aktiviert - das ist OK, PHPMailer versucht automatisch STARTTLS
                $securityLabel = rex_i18n::msg('mail_tools_diag_auto_tls');
                $securityStatus = 'ok';
                $securityHint = rex_i18n::msg('mail_tools_diag_auto_tls_hint');
            } else {
                // Weder explizite Verschlüsselung noch Auto-TLS
                $securityLabel = rex_i18n::msg('mail_tools_diag_no_encryption');
                $securityStatus = 'warning';
                $securityHint = rex_i18n::msg('mail_tools_diag_no_encryption_hint');
            }
            
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_encryption'),
                'value' => $securityLabel,
                'status' => $securityStatus,
                'hint' => $securityHint,
            ];

            $this->isSecure = !empty($security) || $autoTls;

            // Authentifizierung
            if ($this->config['auth']) {
                if (empty($this->config['username'])) {
                    $check['issues'][] = rex_i18n::msg('mail_tools_diag_no_username');
                    $check['status'] = 'error';
                }
                if (empty($this->config['password'])) {
                    $check['issues'][] = rex_i18n::msg('mail_tools_diag_no_password');
                    $check['status'] = 'error';
                }
            }
        }

        // Absender
        if (empty($this->config['from'])) {
            $check['issues'][] = rex_i18n::msg('mail_tools_diag_no_from');
            $check['status'] = 'error';
        } else {
            if (!filter_var($this->config['from'], FILTER_VALIDATE_EMAIL)) {
                $check['issues'][] = rex_i18n::msg('mail_tools_diag_invalid_from');
                $check['status'] = 'error';
            } else {
                $check['details'][] = [
                    'label' => rex_i18n::msg('mail_tools_diag_from'),
                    'value' => $this->config['from'],
                    'status' => 'ok',
                ];
            }
        }

        $this->results['checks']['basic_config'] = $check;
    }

    /**
     * Provider erkennen
     */
    private function detectProvider(): void
    {
        $host = strtolower($this->config['host']);
        
        foreach (self::KNOWN_PROVIDERS as $key => $provider) {
            foreach ($provider['hosts'] as $providerHost) {
                if (str_contains($host, $providerHost) || str_contains($providerHost, $host)) {
                    $this->results['provider'] = [
                        'key' => $key,
                        'name' => $provider['name'],
                        'expected_port' => $provider['port'],
                        'expected_security' => $provider['security'],
                        'help_url' => $provider['help_url'],
                        'notes' => $provider['notes'],
                    ];
                    return;
                }
            }
        }
        
        // Versuche anhand der Domain zu erkennen
        if (str_contains($host, 'office365') || str_contains($host, 'outlook') || str_contains($host, 'microsoft')) {
            $this->results['provider'] = self::KNOWN_PROVIDERS['microsoft365'] + ['key' => 'microsoft365'];
        } elseif (str_contains($host, 'google') || str_contains($host, 'gmail')) {
            $this->results['provider'] = self::KNOWN_PROVIDERS['gmail'] + ['key' => 'gmail'];
        }
    }

    /**
     * DNS/MX-Prüfung
     */
    private function checkDnsRecords(): void
    {
        if ($this->config['mailer'] !== 'smtp' || empty($this->config['host'])) {
            return;
        }

        $check = [
            'name' => 'dns',
            'title' => rex_i18n::msg('mail_tools_diag_dns_check'),
            'status' => 'ok',
            'details' => [],
            'issues' => [],
        ];

        $host = $this->config['host'];
        
        // A/AAAA-Record
        $ip = gethostbyname($host);
        if ($ip === $host) {
            // Vielleicht IPv6?
            $ipv6 = dns_get_record($host, DNS_AAAA);
            if (empty($ipv6)) {
                $check['issues'][] = rex_i18n::msg('mail_tools_diag_host_not_resolved', $host);
                $check['status'] = 'error';
            } else {
                $check['details'][] = [
                    'label' => 'IPv6 (AAAA)',
                    'value' => $ipv6[0]['ipv6'] ?? 'gefunden',
                    'status' => 'ok',
                ];
            }
        } else {
            $check['details'][] = [
                'label' => 'IP-Adresse',
                'value' => $ip,
                'status' => 'ok',
            ];
        }

        // Reverse DNS (optional)
        if ($ip !== $host) {
            $reverseDns = gethostbyaddr($ip);
            if ($reverseDns && $reverseDns !== $ip) {
                $check['details'][] = [
                    'label' => 'Reverse DNS',
                    'value' => $reverseDns,
                    'status' => 'info',
                ];
            }
        }

        $this->results['checks']['dns'] = $check;
    }

    /**
     * Port-Erreichbarkeit prüfen
     */
    private function checkPortConnectivity(): void
    {
        if ($this->config['mailer'] !== 'smtp' || empty($this->config['host'])) {
            return;
        }

        $check = [
            'name' => 'connectivity',
            'title' => rex_i18n::msg('mail_tools_diag_connectivity'),
            'status' => 'ok',
            'details' => [],
            'issues' => [],
        ];

        $host = $this->config['host'];
        $port = $this->config['port'];
        $timeout = min($this->config['timeout'], 5);

        $errno = 0;
        $errstr = '';
        
        $startTime = microtime(true);
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $connectionTime = round((microtime(true) - $startTime) * 1000);

        if ($connection) {
            fclose($connection);
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_port_reachable', $port),
                'value' => rex_i18n::msg('mail_tools_diag_connection_time', $connectionTime),
                'status' => $connectionTime > 2000 ? 'warning' : 'ok',
                'hint' => $connectionTime > 2000 ? rex_i18n::msg('mail_tools_diag_slow_connection') : '',
            ];
        } else {
            $check['status'] = 'error';
            $check['issues'][] = rex_i18n::msg('mail_tools_diag_port_blocked', $port, $errstr);
            
            // Alternative Ports vorschlagen
            $alternativePorts = [25, 465, 587, 2525];
            $openPorts = [];
            
            foreach ($alternativePorts as $altPort) {
                if ($altPort === $port) {
                    continue;
                }
                $altConn = @fsockopen($host, $altPort, $errno, $errstr, 2);
                if ($altConn) {
                    fclose($altConn);
                    $openPorts[] = $altPort;
                }
            }
            
            if (!empty($openPorts)) {
                $check['details'][] = [
                    'label' => rex_i18n::msg('mail_tools_diag_alternative_ports'),
                    'value' => implode(', ', $openPorts),
                    'status' => 'info',
                    'hint' => rex_i18n::msg('mail_tools_diag_try_alternative_port'),
                ];
                
                $this->suggestions['use_alternative_port'] = $openPorts[0];
            }
        }

        $this->results['checks']['connectivity'] = $check;
    }

    /**
     * SSL/TLS-Zertifikat prüfen
     */
    private function checkSslCertificate(): void
    {
        if ($this->config['mailer'] !== 'smtp' || empty($this->config['host'])) {
            return;
        }

        if (empty($this->config['security'])) {
            return;
        }

        $check = [
            'name' => 'ssl_certificate',
            'title' => rex_i18n::msg('mail_tools_diag_ssl_check'),
            'status' => 'ok',
            'details' => [],
            'issues' => [],
        ];

        $host = $this->config['host'];
        $port = $this->config['port'];
        
        // Bei STARTTLS (Port 587) müssen wir anders vorgehen
        if ($this->config['security'] === 'tls' && $port === 587) {
            // Für STARTTLS können wir nur prüfen, ob SSL prinzipiell funktioniert
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'capture_peer_cert' => true,
                ],
            ]);
            
            $check['details'][] = [
                'label' => 'STARTTLS',
                'value' => rex_i18n::msg('mail_tools_diag_starttls_will_upgrade'),
                'status' => 'info',
            ];
        } else {
            // SSL/TLS direkt
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'capture_peer_cert' => true,
                ],
            ]);

            $sslHost = 'ssl://' . $host . ':' . $port;
            $errno = 0;
            $errstr = '';
            
            $connection = @stream_socket_client($sslHost, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
            
            if ($connection) {
                $params = stream_context_get_params($connection);
                if (isset($params['options']['ssl']['peer_certificate'])) {
                    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                    
                    if ($cert) {
                        $check['details'][] = [
                            'label' => rex_i18n::msg('mail_tools_diag_cert_issuer'),
                            'value' => $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Unbekannt',
                            'status' => 'ok',
                        ];
                        
                        $validTo = $cert['validTo_time_t'];
                        $daysRemaining = floor(($validTo - time()) / 86400);
                        
                        $certStatus = 'ok';
                        if ($daysRemaining < 0) {
                            $certStatus = 'error';
                            $check['issues'][] = rex_i18n::msg('mail_tools_diag_cert_expired');
                            $check['status'] = 'error';
                        } elseif ($daysRemaining < 14) {
                            $certStatus = 'warning';
                        }
                        
                        $check['details'][] = [
                            'label' => rex_i18n::msg('mail_tools_diag_cert_valid_until'),
                            'value' => date('d.m.Y', $validTo) . " ($daysRemaining " . rex_i18n::msg('mail_tools_diag_days') . ")",
                            'status' => $certStatus,
                        ];
                        
                        // CN prüfen
                        $cn = $cert['subject']['CN'] ?? '';
                        $altNames = $cert['extensions']['subjectAltName'] ?? '';
                        
                        if ($cn === $host || str_contains($altNames, $host)) {
                            $check['details'][] = [
                                'label' => rex_i18n::msg('mail_tools_diag_cert_hostname'),
                                'value' => rex_i18n::msg('mail_tools_diag_cert_match'),
                                'status' => 'ok',
                            ];
                        } else {
                            $check['details'][] = [
                                'label' => rex_i18n::msg('mail_tools_diag_cert_hostname'),
                                'value' => $cn,
                                'status' => 'warning',
                                'hint' => rex_i18n::msg('mail_tools_diag_cert_mismatch', $host),
                            ];
                        }
                    }
                }
                fclose($connection);
            } else {
                $check['status'] = 'error';
                $check['issues'][] = rex_i18n::msg('mail_tools_diag_ssl_failed', $errstr);
            }
        }

        $this->results['checks']['ssl_certificate'] = $check;
    }

    /**
     * SMTP-Handshake testen
     */
    private function checkSmtpHandshake(): void
    {
        if ($this->config['mailer'] !== 'smtp' || empty($this->config['host'])) {
            return;
        }

        $check = [
            'name' => 'smtp_handshake',
            'title' => rex_i18n::msg('mail_tools_diag_smtp_handshake'),
            'status' => 'ok',
            'details' => [],
            'issues' => [],
        ];

        $host = $this->config['host'];
        $port = $this->config['port'];
        $timeout = min($this->config['timeout'], 10);

        $errno = 0;
        $errstr = '';
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$connection) {
            $check['status'] = 'error';
            $check['issues'][] = rex_i18n::msg('mail_tools_diag_connection_failed');
            $this->results['checks']['smtp_handshake'] = $check;
            return;
        }

        stream_set_timeout($connection, $timeout);
        
        // Banner lesen
        $banner = fgets($connection, 512);
        if ($banner) {
            $code = (int) substr($banner, 0, 3);
            if ($code === 220) {
                $check['details'][] = [
                    'label' => rex_i18n::msg('mail_tools_diag_server_banner'),
                    'value' => trim($banner),
                    'status' => 'ok',
                ];
            } else {
                $check['status'] = 'warning';
                $check['issues'][] = rex_i18n::msg('mail_tools_diag_unexpected_banner', $banner);
            }
        }

        // EHLO senden
        $ehlo = "EHLO " . gethostname() . "\r\n";
        fwrite($connection, $ehlo);
        
        $response = '';
        $capabilities = [];
        while ($line = fgets($connection, 512)) {
            $response .= $line;
            // Capabilities extrahieren
            if (preg_match('/^250[- ](.+)$/i', trim($line), $matches)) {
                $capabilities[] = strtoupper(trim($matches[1]));
            }
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        if (!empty($capabilities)) {
            // Wichtige Capabilities prüfen
            $importantCaps = ['STARTTLS', 'AUTH', 'AUTH LOGIN', 'AUTH PLAIN', '8BITMIME', 'SIZE'];
            $foundCaps = [];
            
            foreach ($capabilities as $cap) {
                foreach ($importantCaps as $important) {
                    if (str_starts_with($cap, $important)) {
                        $foundCaps[] = $cap;
                    }
                }
            }

            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_capabilities'),
                'value' => implode(', ', $foundCaps),
                'status' => 'info',
            ];

            // STARTTLS verfügbar?
            if ($this->config['security'] === 'tls') {
                if (in_array('STARTTLS', $capabilities)) {
                    $check['details'][] = [
                        'label' => 'STARTTLS',
                        'value' => rex_i18n::msg('mail_tools_diag_supported'),
                        'status' => 'ok',
                    ];
                } else {
                    $check['status'] = 'error';
                    $check['issues'][] = rex_i18n::msg('mail_tools_diag_starttls_not_supported');
                }
            }

            // AUTH verfügbar?
            if ($this->config['auth']) {
                $authFound = false;
                foreach ($capabilities as $cap) {
                    if (str_starts_with($cap, 'AUTH')) {
                        $authFound = true;
                        break;
                    }
                }
                
                if (!$authFound) {
                    $check['status'] = 'warning';
                    $check['issues'][] = rex_i18n::msg('mail_tools_diag_auth_not_advertised');
                }
            }
        }

        // QUIT senden
        fwrite($connection, "QUIT\r\n");
        fclose($connection);

        $this->results['checks']['smtp_handshake'] = $check;
    }

    /**
     * Authentifizierung prüfen (ohne echten Login)
     */
    private function checkAuthentication(): void
    {
        if ($this->config['mailer'] !== 'smtp' || !$this->config['auth']) {
            return;
        }

        $check = [
            'name' => 'authentication',
            'title' => rex_i18n::msg('mail_tools_diag_auth_check'),
            'status' => 'ok',
            'details' => [],
            'issues' => [],
        ];

        $username = $this->config['username'];
        $password = $this->config['password'];

        // Benutzername-Format prüfen
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_username_format'),
                'value' => rex_i18n::msg('mail_tools_diag_email_format'),
                'status' => 'ok',
            ];
        } else {
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_username_format'),
                'value' => rex_i18n::msg('mail_tools_diag_custom_format'),
                'status' => 'info',
                'hint' => rex_i18n::msg('mail_tools_diag_check_username_hint'),
            ];
        }

        // Passwort-Qualität prüfen
        $passLen = strlen($password);
        if ($passLen < 8) {
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_password'),
                'value' => rex_i18n::msg('mail_tools_diag_password_short'),
                'status' => 'warning',
            ];
        } elseif ($passLen === 16 || $passLen === 19) {
            // Typische App-Passwort-Längen
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_password'),
                'value' => rex_i18n::msg('mail_tools_diag_looks_like_app_password'),
                'status' => 'ok',
            ];
        } else {
            $check['details'][] = [
                'label' => rex_i18n::msg('mail_tools_diag_password'),
                'value' => rex_i18n::msg('mail_tools_diag_password_set'),
                'status' => 'ok',
            ];
        }

        // Provider-spezifische Hinweise
        if (isset($this->results['provider'])) {
            $provider = $this->results['provider'];
            if (in_array($provider['key'] ?? '', ['microsoft365', 'gmail'])) {
                $check['details'][] = [
                    'label' => rex_i18n::msg('mail_tools_diag_auth_type'),
                    'value' => rex_i18n::msg('mail_tools_diag_app_password_required'),
                    'status' => 'info',
                    'hint' => rex_i18n::msg('mail_tools_diag_mfa_hint'),
                ];
            }
        }

        // Absender = Benutzername?
        if ($this->config['from'] !== $username) {
            $fromDomain = substr(strrchr($this->config['from'], '@'), 1);
            $userDomain = substr(strrchr($username, '@'), 1);
            
            if ($fromDomain !== $userDomain) {
                $check['details'][] = [
                    'label' => rex_i18n::msg('mail_tools_diag_sender_mismatch'),
                    'value' => rex_i18n::msg('mail_tools_diag_different_domains'),
                    'status' => 'warning',
                    'hint' => rex_i18n::msg('mail_tools_diag_sender_mismatch_hint'),
                ];
            }
        }

        $this->results['checks']['authentication'] = $check;
    }

    /**
     * Provider-spezifische Prüfungen
     */
    private function runProviderSpecificChecks(): void
    {
        if (!isset($this->results['provider'])) {
            return;
        }

        $provider = $this->results['provider'];
        $key = $provider['key'] ?? '';

        $check = [
            'name' => 'provider_specific',
            'title' => rex_i18n::msg('mail_tools_diag_provider_check', $provider['name'] ?? ''),
            'status' => 'ok',
            'details' => [],
            'issues' => [],
        ];

        // Port & Security prüfen
        $expectedPort = $provider['expected_port'] ?? null;
        $expectedSecurity = $provider['expected_security'] ?? null;

        if ($expectedPort && $this->config['port'] !== $expectedPort) {
            $check['status'] = 'warning';
            $check['issues'][] = rex_i18n::msg('mail_tools_diag_wrong_port', $this->config['port'], $expectedPort);
            $this->suggestions['correct_port'] = $expectedPort;
        }

        if ($expectedSecurity && $this->config['security'] !== $expectedSecurity) {
            $check['status'] = 'warning';
            $check['issues'][] = rex_i18n::msg('mail_tools_diag_wrong_security', $this->config['security'] ?: 'keine', $expectedSecurity);
            $this->suggestions['correct_security'] = $expectedSecurity;
        }

        // Provider-spezifische Hinweise
        if (!empty($provider['notes'])) {
            foreach ($provider['notes'] as $note) {
                $check['details'][] = [
                    'label' => rex_i18n::msg('mail_tools_diag_provider_note'),
                    'value' => $note,
                    'status' => 'info',
                ];
            }
        }

        // Microsoft 365 spezifisch
        if ($key === 'microsoft365') {
            // Prüfen ob Absender zur authentifizierten Domain passt
            $fromDomain = substr(strrchr($this->config['from'], '@'), 1);
            $userDomain = substr(strrchr($this->config['username'], '@'), 1);
            
            if ($fromDomain !== $userDomain) {
                $check['status'] = 'warning';
                $check['issues'][] = rex_i18n::msg('mail_tools_diag_m365_sender_must_match');
            }
        }

        // Gmail spezifisch
        if ($key === 'gmail') {
            $passLen = strlen($this->config['password']);
            // Google App-Passwörter sind 16 Zeichen (ohne Leerzeichen)
            if ($passLen !== 16 && $passLen !== 19) { // 19 mit Leerzeichen
                $check['details'][] = [
                    'label' => rex_i18n::msg('mail_tools_diag_gmail_app_password'),
                    'value' => rex_i18n::msg('mail_tools_diag_password_length_unusual'),
                    'status' => 'info',
                    'hint' => rex_i18n::msg('mail_tools_diag_gmail_app_password_hint'),
                ];
            }
        }

        if (!empty($check['details']) || !empty($check['issues'])) {
            $this->results['checks']['provider_specific'] = $check;
        }
    }

    /**
     * Gesamtstatus berechnen
     */
    private function calculateOverallStatus(): void
    {
        $hasError = false;
        $hasWarning = false;

        foreach ($this->results['checks'] as $check) {
            if ($check['status'] === 'error') {
                $hasError = true;
            } elseif ($check['status'] === 'warning') {
                $hasWarning = true;
            }
        }

        if ($hasError) {
            $this->results['overall_status'] = 'error';
        } elseif ($hasWarning) {
            $this->results['overall_status'] = 'warning';
        } else {
            $this->results['overall_status'] = 'ok';
        }
    }

    /**
     * Empfehlungen generieren
     */
    private function generateRecommendations(): void
    {
        $recommendations = [];

        // Basierend auf Suggestions
        if (isset($this->suggestions['use_alternative_port'])) {
            $recommendations[] = [
                'type' => 'auto_fix',
                'key' => 'port',
                'title' => rex_i18n::msg('mail_tools_diag_rec_change_port'),
                'description' => rex_i18n::msg('mail_tools_diag_rec_port_blocked', $this->suggestions['use_alternative_port']),
                'action' => 'change_port',
                'value' => $this->suggestions['use_alternative_port'],
            ];
        }

        if (isset($this->suggestions['correct_port'])) {
            $recommendations[] = [
                'type' => 'auto_fix',
                'key' => 'port',
                'title' => rex_i18n::msg('mail_tools_diag_rec_correct_port'),
                'description' => rex_i18n::msg('mail_tools_diag_rec_provider_port', $this->results['provider']['name'] ?? '', $this->suggestions['correct_port']),
                'action' => 'change_port',
                'value' => $this->suggestions['correct_port'],
            ];
        }

        if (isset($this->suggestions['correct_security'])) {
            $recommendations[] = [
                'type' => 'auto_fix',
                'key' => 'security',
                'title' => rex_i18n::msg('mail_tools_diag_rec_correct_security'),
                'description' => rex_i18n::msg('mail_tools_diag_rec_provider_security', $this->results['provider']['name'] ?? '', strtoupper($this->suggestions['correct_security'])),
                'action' => 'change_security',
                'value' => $this->suggestions['correct_security'],
            ];
        }

        // PHP mail() -> SMTP Empfehlung
        if ($this->config['mailer'] === 'mail') {
            $recommendations[] = [
                'type' => 'suggestion',
                'key' => 'use_smtp',
                'title' => rex_i18n::msg('mail_tools_diag_rec_use_smtp'),
                'description' => rex_i18n::msg('mail_tools_diag_rec_smtp_benefits'),
                'action' => 'switch_to_smtp',
            ];
        }

        // Auto-TLS oder keine Verschlüsselung - explizite TLS empfehlen
        if ($this->config['mailer'] === 'smtp' && empty($this->config['security'])) {
            // Prüfen ob Auto-TLS aktiv ist
            $isAutoTls = ($this->config['security_mode'] ?? '') === '1';
            
            $recommendations[] = [
                'type' => 'security',
                'key' => 'enable_encryption',
                'title' => rex_i18n::msg('mail_tools_diag_rec_enable_encryption'),
                'description' => $isAutoTls 
                    ? rex_i18n::msg('mail_tools_diag_rec_autotls_warning')
                    : rex_i18n::msg('mail_tools_diag_rec_encryption_important'),
                'action' => 'enable_tls',
            ];
        }

        $this->results['recommendations'] = $recommendations;
    }

    /**
     * Versucht automatische Konfiguration basierend auf E-Mail-Domain
     * @return array<string, mixed>|null
     */
    public function autoConfigureFromEmail(string $email): ?array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));
        
        // 1. Zuerst: Domain direkt gegen bekannte Provider prüfen
        foreach (self::KNOWN_PROVIDERS as $providerKey => $provider) {
            if (isset($provider['domains']) && in_array($domain, $provider['domains'], true)) {
                return [
                    'provider' => $providerKey,
                    'provider_name' => $provider['name'],
                    'host' => $provider['hosts'][0],
                    'port' => $provider['port'],
                    'security' => $provider['security'],
                    'auth' => $provider['auth'],
                    'username' => $email,
                    'notes' => $provider['notes'],
                    'help_url' => $provider['help_url'],
                    'detected_via' => rex_i18n::msg('mail_tools_diag_detected_via_domain', $domain),
                ];
            }
        }
        
        // 2. MX-Records abrufen und gegen bekannte Provider prüfen
        $mxRecords = [];
        if (getmxrr($domain, $mxRecords) && !empty($mxRecords)) {
            $mxHost = strtolower($mxRecords[0]);
            
            // Provider anhand MX-Record erkennen
            $detectedProvider = null;
            
            if (str_contains($mxHost, 'outlook') || str_contains($mxHost, 'microsoft') || str_contains($mxHost, 'office365')) {
                $detectedProvider = 'microsoft365';
            } elseif (str_contains($mxHost, 'google') || str_contains($mxHost, 'gmail')) {
                $detectedProvider = 'gmail';
            } elseif (str_contains($mxHost, 'icloud') || str_contains($mxHost, 'apple') || str_contains($mxHost, 'me.com')) {
                $detectedProvider = 'icloud';
            } elseif (str_contains($mxHost, 'ionos') || str_contains($mxHost, '1und1')) {
                $detectedProvider = 'ionos';
            } elseif (str_contains($mxHost, 'strato')) {
                $detectedProvider = 'strato';
            } elseif (str_contains($mxHost, 'hosteurope')) {
                $detectedProvider = 'hosteurope';
            } elseif (str_contains($mxHost, 'kasserver') || str_contains($mxHost, 'all-inkl')) {
                $detectedProvider = 'allinkl';
            } elseif (str_contains($mxHost, 'gmx')) {
                $detectedProvider = 'gmx';
            } elseif (str_contains($mxHost, 'web.de')) {
                $detectedProvider = 'webde';
            }

            if ($detectedProvider && isset(self::KNOWN_PROVIDERS[$detectedProvider])) {
                $provider = self::KNOWN_PROVIDERS[$detectedProvider];
                return [
                    'provider' => $detectedProvider,
                    'provider_name' => $provider['name'],
                    'host' => $provider['hosts'][0],
                    'port' => $provider['port'],
                    'security' => $provider['security'],
                    'auth' => $provider['auth'],
                    'username' => $email,
                    'notes' => $provider['notes'],
                    'help_url' => $provider['help_url'],
                    'detected_via' => 'MX: ' . $mxHost,
                ];
            }
        }
        
        // Fallback: Verschiedene SMTP-Host-Varianten versuchen
        $hostsToTry = [
            $domain,           // Domain direkt (z.B. klxm.de)
            'smtp.' . $domain, // Standard smtp. prefix
            'mail.' . $domain, // Alternative mail. prefix
        ];
        
        // Prüfen welcher Host erreichbar ist
        foreach ($hostsToTry as $testHost) {
            $ip = gethostbyname($testHost);
            if ($ip !== $testHost) {
                // Host existiert, Ports testen
                foreach ([587, 465, 25] as $testPort) {
                    $conn = @fsockopen($testHost, $testPort, $errno, $errstr, 3);
                    if ($conn) {
                        fclose($conn);
                        return [
                            'provider' => 'generic',
                            'provider_name' => $domain,
                            'host' => $testHost,
                            'port' => $testPort,
                            'security' => $testPort === 465 ? 'ssl' : ($testPort === 587 ? 'tls' : ''),
                            'auth' => true,
                            'username' => $email,
                            'notes' => [rex_i18n::msg('mail_tools_diag_auto_detected')],
                            'detected_via' => 'Port-Scan: ' . $testHost . ':' . $testPort,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Wendet eine Konfigurationsänderung an
     */
    public function applyConfigChange(string $key, mixed $value): bool
    {
        $phpmailer = rex_addon::get('phpmailer');
        
        $mapping = [
            'port' => 'port',
            'security' => 'security',
            'host' => 'host',
            'username' => 'username',
            'password' => 'password',
            'from' => 'from',
            'mailer' => 'mailer',
            'auth' => 'smtp_auth',
        ];

        if (!isset($mapping[$key])) {
            return false;
        }

        $phpmailer->setConfig($mapping[$key], $value);
        return true;
    }

    /**
     * Wendet komplette Auto-Konfiguration an
     * @param array<string, mixed> $config
     */
    public function applyAutoConfig(array $config): bool
    {
        $phpmailer = rex_addon::get('phpmailer');
        
        if (isset($config['host'])) {
            $phpmailer->setConfig('host', $config['host']);
        }
        if (isset($config['port'])) {
            $phpmailer->setConfig('port', $config['port']);
        }
        if (isset($config['security'])) {
            $phpmailer->setConfig('security', $config['security']);
        }
        if (isset($config['auth'])) {
            $phpmailer->setConfig('smtp_auth', $config['auth']);
        }
        if (isset($config['username'])) {
            $phpmailer->setConfig('username', $config['username']);
        }
        
        $phpmailer->setConfig('mailer', 'smtp');
        
        return true;
    }

    /**
     * Führt echten Verbindungstest durch (mit PHPMailer)
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'help' => [],
            'debug' => '',
        ];

        try {
            $mailer = new rex_mailer();
            
            // Debug aktivieren
            $mailer->SMTPDebug = 3;
            $mailer->Debugoutput = function ($str, $level) use (&$result) {
                $result['debug'] .= "[$level] $str\n";
            };

            // Nur Verbindung testen, keine E-Mail senden
            if ($mailer->smtpConnect()) {
                $result['success'] = true;
                $result['message'] = rex_i18n::msg('mail_tools_diag_connection_successful');
                $mailer->smtpClose();
            } else {
                $errorInfo = $mailer->ErrorInfo;
                $result['message'] = $errorInfo;
                $result['help'] = $this->getHelpForError($errorInfo);
            }
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            $result['help'] = $this->getHelpForError($e->getMessage());
        }

        return $result;
    }

    /**
     * Sendet eine Test-E-Mail mit verständlichen Fehlermeldungen
     * @return array<string, mixed>
     */
    public function sendTestMail(string $recipient): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'explanation' => '',
            'help' => [],
            'debug' => '',
        ];

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = rex_i18n::msg('mail_tools_diag_invalid_recipient');
            return $result;
        }

        try {
            $mailer = new rex_mailer();
            
            // Debug aktivieren
            $mailer->SMTPDebug = 3;
            $mailer->Debugoutput = function ($str, $level) use (&$result) {
                $result['debug'] .= "[$level] $str\n";
            };

            // Test-E-Mail konfigurieren
            $mailer->addAddress($recipient);
            $mailer->Subject = rex_i18n::msg('mail_tools_diag_test_subject', date('d.m.Y H:i:s'));
            $mailer->Body = rex_i18n::msg('mail_tools_diag_test_body', rex::getServerName(), date('d.m.Y H:i:s'));
            $mailer->AltBody = strip_tags($mailer->Body);

            if ($mailer->send()) {
                $result['success'] = true;
                $result['message'] = rex_i18n::msg('mail_tools_diag_mail_sent_to', $recipient);
            } else {
                $errorInfo = $mailer->ErrorInfo;
                $result['message'] = $errorInfo;
                
                // Verständliche Erklärung und Hilfe
                $analysis = $this->analyzeError($errorInfo);
                $result['explanation'] = $analysis['explanation'];
                $result['help'] = $analysis['help'];
            }
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            $analysis = $this->analyzeError($e->getMessage());
            $result['explanation'] = $analysis['explanation'];
            $result['help'] = $analysis['help'];
        }

        return $result;
    }

    /**
     * Analysiert einen Fehler und gibt verständliche Erklärung + Hilfe zurück
     * @return array{explanation: string, help: array<string>}
     */
    private function analyzeError(string $error): array
    {
        $errorLower = strtolower($error);
        
        // Authentifizierungsfehler
        if (str_contains($errorLower, 'authentication') || 
            str_contains($errorLower, 'username and password') ||
            str_contains($errorLower, '535') ||
            str_contains($errorLower, 'credentials')) {
            return [
                'explanation' => rex_i18n::msg('mail_tools_diag_error_auth_explain'),
                'help' => [
                    rex_i18n::msg('mail_tools_diag_help_check_username'),
                    rex_i18n::msg('mail_tools_diag_help_check_password'),
                    rex_i18n::msg('mail_tools_diag_help_app_password'),
                ],
            ];
        }
        
        // Verbindung abgelehnt
        if (str_contains($errorLower, 'connection refused') ||
            str_contains($errorLower, 'connection timed out') ||
            str_contains($errorLower, 'could not connect')) {
            return [
                'explanation' => rex_i18n::msg('mail_tools_diag_error_connection_explain'),
                'help' => [
                    rex_i18n::msg('mail_tools_diag_help_check_host'),
                    rex_i18n::msg('mail_tools_diag_help_check_port'),
                    rex_i18n::msg('mail_tools_diag_help_check_firewall'),
                ],
            ];
        }
        
        // DNS/Host nicht gefunden
        if (str_contains($errorLower, 'getaddrinfo') ||
            str_contains($errorLower, 'no such host') ||
            str_contains($errorLower, 'name or service not known')) {
            return [
                'explanation' => rex_i18n::msg('mail_tools_diag_error_dns_explain'),
                'help' => [
                    rex_i18n::msg('mail_tools_diag_help_check_hostname'),
                    rex_i18n::msg('mail_tools_diag_help_typo_hostname'),
                ],
            ];
        }
        
        // SSL/TLS-Fehler
        if (str_contains($errorLower, 'ssl') ||
            str_contains($errorLower, 'tls') ||
            str_contains($errorLower, 'certificate') ||
            str_contains($errorLower, 'crypto')) {
            return [
                'explanation' => rex_i18n::msg('mail_tools_diag_error_ssl_explain'),
                'help' => [
                    rex_i18n::msg('mail_tools_diag_help_try_tls'),
                    rex_i18n::msg('mail_tools_diag_help_check_encryption'),
                    rex_i18n::msg('mail_tools_diag_help_port_encryption'),
                ],
            ];
        }
        
        // Absender abgelehnt
        if (str_contains($errorLower, 'sender') ||
            str_contains($errorLower, '550') ||
            str_contains($errorLower, 'relay') ||
            str_contains($errorLower, 'not allowed')) {
            return [
                'explanation' => rex_i18n::msg('mail_tools_diag_error_sender_explain'),
                'help' => [
                    rex_i18n::msg('mail_tools_diag_help_check_sender'),
                    rex_i18n::msg('mail_tools_diag_help_sender_match'),
                ],
            ];
        }
        
        // Timeout
        if (str_contains($errorLower, 'timeout')) {
            return [
                'explanation' => rex_i18n::msg('mail_tools_diag_error_timeout_explain'),
                'help' => [
                    rex_i18n::msg('mail_tools_diag_help_check_port'),
                    rex_i18n::msg('mail_tools_diag_help_check_firewall'),
                    rex_i18n::msg('mail_tools_diag_help_increase_timeout'),
                ],
            ];
        }
        
        // Generische Hilfe
        return [
            'explanation' => rex_i18n::msg('mail_tools_diag_error_generic_explain'),
            'help' => [
                rex_i18n::msg('mail_tools_diag_help_check_settings'),
                rex_i18n::msg('mail_tools_diag_help_contact_provider'),
            ],
        ];
    }

    /**
     * Gibt Hilfe-Tipps basierend auf Fehlermeldung zurück
     * @return array<string>
     */
    private function getHelpForError(string $error): array
    {
        $analysis = $this->analyzeError($error);
        return $analysis['help'];
    }

    /**
     * Gibt Konfiguration zurück
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
