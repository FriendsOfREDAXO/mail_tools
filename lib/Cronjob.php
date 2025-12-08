<?php

namespace FriendsOfRedaxo\MailTools;

use rex_cronjob;

/**
 * Cronjob für E-Mail Fehlerberichte.
 *
 * Analysiert das PHPMailer-Log und sendet Berichte über fehlgeschlagene E-Mails.
 */
class Cronjob extends rex_cronjob
{
    public function execute(): bool
    {
        // Konfiguration aus Cronjob-Parametern laden
        $recipients = $this->getParam('recipients', '');
        $onlyErrors = (bool) $this->getParam('only_errors', true);
        $attachEml = (bool) $this->getParam('attach_eml', false);

        // Empfänger prüfen
        if (empty($recipients)) {
            $this->setMessage('Keine Empfänger konfiguriert');
            return true; // Kein Fehler, nur keine Empfänger
        }

        $recipientList = array_map('trim', explode(',', $recipients));
        $recipientList = array_filter($recipientList, static function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        if (empty($recipientList)) {
            $this->setMessage('Keine gültigen E-Mail-Adressen konfiguriert');
            return true;
        }

        try {
            // Noch nicht gemeldete Fehler abrufen
            $newFailedEmails = LogParser::getUnreportedFailedEmails();

            // Statistiken sammeln
            $statistics = LogParser::getStatistics();

            // Bericht nur senden wenn Fehler vorhanden oder "immer senden" aktiv
            if (empty($newFailedEmails) && $onlyErrors) {
                $this->setMessage('Keine neuen Fehler gefunden');
                return true;
            }

            // Berichte generieren
            $htmlReport = ReportGenerator::generateHtmlReport($newFailedEmails, $statistics);
            $textReport = ReportGenerator::generateTextReport($newFailedEmails);

            // Bericht senden (optional mit EML-Anhängen)
            $sent = ReportGenerator::sendReport($recipientList, $htmlReport, $textReport, $newFailedEmails, $attachEml);

            if ($sent) {
                // Fehler als gemeldet markieren
                LogParser::markAsReported($newFailedEmails);

                $this->setMessage(sprintf(
                    '%d fehlgeschlagene E-Mail(s) gemeldet an: %s',
                    count($newFailedEmails),
                    implode(', ', $recipientList),
                ));
                return true;
            }

            $this->setMessage('Fehler beim Senden des Berichts');
            return false;
        } catch (\Exception $e) {
            $this->setMessage('Fehler: ' . $e->getMessage());
            \rex_logger::logException($e);
            return false;
        }
    }

    public function getTypeName(): string
    {
        return \rex_addon::get('mail_tools')->i18n('cronjob_name');
    }

    /**
     * @return list<array{label?: string, name: string, type: string, notice?: string, default?: mixed, options?: array<int|string, string>}>
     */
    public function getParamFields(): array
    {
        $addon = \rex_addon::get('mail_tools');

        return [
            [
                'label' => $addon->i18n('config_recipients'),
                'name' => 'recipients',
                'type' => 'text',
                'notice' => $addon->i18n('config_recipients_notice'),
            ],
            [
                'name' => 'only_errors',
                'type' => 'checkbox',
                'options' => [1 => $addon->i18n('config_only_errors_label')],
                'default' => 1,
            ],
            [
                'name' => 'attach_eml',
                'type' => 'checkbox',
                'options' => [1 => $addon->i18n('config_attach_eml_label')],
                'default' => 0,
            ],
        ];
    }
}
