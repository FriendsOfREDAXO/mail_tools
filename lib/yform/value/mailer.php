<?php

/**
 * YForm Value: E-Mail Versand beim Speichern (Tablemanager-kompatibel).
 *
 * Sendet beim Speichern eines Datensatzes eine E-Mail.
 * Unterstützt Templates, dynamische Empfänger, Anhänge aus Upload-Feldern.
 *
 * Features:
 * - E-Mail-Templates oder direkter Body
 * - Empfänger aus anderen Feldern der Tabelle
 * - Upload-Felder als Anhänge
 * - CC, BCC, Reply-To
 * - Nur bei Neuanlage oder immer
 * - Speichert optional Versandzeitpunkt in DB
 *
 * @author FriendsOfREDAXO
 */
class rex_yform_value_mailer extends rex_yform_value_abstract
{
    public function preValidateAction(): void
    {
        // Config parsen für spätere Verwendung
    }

    public function enterObject(): void
    {
        // Nur im Backend anzeigen, dass hier E-Mail-Versand konfiguriert ist
        if ($this->needsOutput() && $this->isViewable() && !rex::isFrontend()) {
            $this->params['form_output'][$this->getId()] = $this->parse(
                ['value.mailer-view.tpl.php', 'value.view.tpl.php'],
                [
                    'type' => 'text',
                    'value' => $this->getValue() ?: '',
                    'notice' => rex_i18n::msg('mail_tools_mailer_will_send'),
                ],
            );
        } else {
            $this->params['form_output'][$this->getId()] = '';
        }

        // Wert für DB vorbereiten (Versandzeitpunkt)
        $this->params['value_pool']['email'][$this->getName()] = $this->getValue();
        if ($this->saveInDb()) {
            $this->params['value_pool']['sql'][$this->getName()] = $this->getValue();
        }
    }

    public function postAction(): void
    {
        // Nur bei erfolgreichem Submit
        if ($this->params['send'] != 1) {
            return;
        }

        // Prüfen ob E-Mail gesendet werden soll
        $sendMode = (int) $this->getElement('send_mode');
        // 0 = immer, 1 = nur bei Neuanlage, 2 = nie (manuell)
        
        if (2 === $sendMode) {
            return; // Manueller Modus - kein automatischer Versand
        }

        if (1 === $sendMode && $this->params['main_id'] > 0) {
            // Nur bei Neuanlage - aber es ist ein Edit
            // Prüfen ob vorher schon gesendet wurde
            $currentValue = $this->getValue();
            if (!empty($currentValue)) {
                return; // Bereits gesendet
            }
        }

        try {
            $success = $this->sendEmail();
            
            if ($success && $this->saveInDb()) {
                // Versandzeitpunkt speichern
                $sql = rex_sql::factory();
                $sql->setTable($this->params['main_table']);
                $sql->setWhere(['id' => $this->params['main_id']]);
                $sql->setValue($this->getName(), date('Y-m-d H:i:s'));
                $sql->update();
            }
        } catch (\Exception $e) {
            if ($this->params['debug']) {
                dump('E-Mail Fehler: ' . $e->getMessage());
            }
            rex_logger::logException($e);
        }
    }

    /**
     * Sendet die E-Mail.
     */
    private function sendEmail(): bool
    {
        $templateName = $this->getElement('template');
        
        // Mit Template
        if (!empty($templateName)) {
            return $this->sendWithTemplate($templateName);
        }

        // Ohne Template - direkter Versand
        return $this->sendDirect();
    }

    /**
     * Sendet mit E-Mail-Template.
     */
    private function sendWithTemplate(string $templateName): bool
    {
        $etpl = rex_yform_email_template::getTemplate($templateName);

        if (!$etpl) {
            throw new \Exception("Template '$templateName' nicht gefunden");
        }

        // Werte aus allen Formularfeldern sammeln
        $values = $this->collectFormValues();

        // Template mit Werten befüllen
        $etpl = rex_yform_email_template::replaceVars($etpl, $values);

        // Empfänger
        $toField = $this->getElement('to');
        if (!empty($toField)) {
            $etpl['mail_to'] = $this->resolveFieldValue($toField);
        }

        // Reply-To
        $replyToField = $this->getElement('reply_to');
        if (!empty($replyToField)) {
            $replyTo = $this->resolveFieldValue($replyToField);
            if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $etpl['mail_reply_to'] = $replyTo;
            }
        }

        // Anhänge aus Template
        if (!empty($etpl['attachments']) && is_string($etpl['attachments'])) {
            $staticFiles = array_map('trim', explode(',', $etpl['attachments']));
            $etpl['attachments'] = [];
            foreach ($staticFiles as $file) {
                if (!empty($file) && file_exists(rex_path::media($file))) {
                    $etpl['attachments'][] = ['name' => $file, 'path' => rex_path::media($file)];
                }
            }
        } else {
            $etpl['attachments'] = [];
        }

        // Upload-Felder als Anhänge
        $this->addAttachmentsToTemplate($etpl);

        if ($this->params['debug']) {
            dump(['mailer_template' => $templateName, 'to' => $etpl['mail_to'] ?? '', 'attachments' => count($etpl['attachments'])]);
        }

        return rex_yform_email_template::sendMail($etpl, $templateName);
    }

    /**
     * Direkter Versand ohne Template.
     */
    private function sendDirect(): bool
    {
        $mail = new \rex_mailer();
        
        // Eigene SMTP-Konfiguration anwenden
        $this->applySmtpConfig($mail);
        
        $mail->isHTML(true);
        $mail->CharSet = 'utf-8';

        // Empfänger
        $to = $this->resolveFieldValue($this->getElement('to'));
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Kein gültiger Empfänger');
        }
        $mail->addAddress($to);

        // CC
        $cc = $this->getElement('cc');
        if (!empty($cc)) {
            foreach (explode(',', $this->resolveFieldValue($cc)) as $addr) {
                $addr = trim($addr);
                if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($addr);
                }
            }
        }

        // BCC
        $bcc = $this->getElement('bcc');
        if (!empty($bcc)) {
            foreach (explode(',', $this->resolveFieldValue($bcc)) as $addr) {
                $addr = trim($addr);
                if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($addr);
                }
            }
        }

        // Reply-To
        $replyTo = $this->resolveFieldValue($this->getElement('reply_to'));
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }

        // Subject und Body
        $values = $this->collectFormValues();
        $mail->Subject = $this->replacePlaceholders($this->getElement('subject') ?? '', $values);
        
        $body = $this->replacePlaceholders($this->getElement('body') ?? '', $values);
        $mail->Body = $this->wrapHtmlBody($body);
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        // Anhänge
        $this->addAttachments($mail);

        if ($this->params['debug']) {
            dump(['mailer_direct' => true, 'to' => $to, 'subject' => $mail->Subject]);
        }

        return $mail->send();
    }

    /**
     * Wendet optionale SMTP-Konfiguration auf den Mailer an.
     *
     * JSON-Format:
     * {
     *   "host": "smtp.example.com",
     *   "port": 587,
     *   "secure": "tls",
     *   "auth": true,
     *   "username": "user@example.com",
     *   "password": "secret",
     *   "from": "noreply@example.com",
     *   "from_name": "My App",
     *   "debug": 0
     * }
     */
    private function applySmtpConfig(\rex_mailer $mail): void
    {
        $configJson = $this->getElement('smtp_config');
        if (empty($configJson)) {
            return;
        }

        $config = json_decode($configJson, true);
        if (!is_array($config)) {
            if ($this->params['debug']) {
                dump('SMTP-Config JSON ungültig');
            }
            return;
        }

        // SMTP aktivieren
        if (!empty($config['host'])) {
            $mail->isSMTP();
            $mail->Host = $config['host'];
        }

        // Port
        if (!empty($config['port'])) {
            $mail->Port = (int) $config['port'];
        }

        // Verschlüsselung (tls, ssl, oder leer)
        if (isset($config['secure'])) {
            $mail->SMTPSecure = $config['secure'];
        }

        // Authentifizierung
        if (!empty($config['auth'])) {
            $mail->SMTPAuth = true;
            if (!empty($config['username'])) {
                $mail->Username = $config['username'];
            }
            if (!empty($config['password'])) {
                $mail->Password = $config['password'];
            }
        }

        // Absender überschreiben
        if (!empty($config['from'])) {
            $fromName = $config['from_name'] ?? '';
            $mail->setFrom($config['from'], $fromName);
        }

        // Debug-Level
        if (isset($config['debug'])) {
            $mail->SMTPDebug = (int) $config['debug'];
        }

        // Timeout
        if (!empty($config['timeout'])) {
            $mail->Timeout = (int) $config['timeout'];
        }

        if ($this->params['debug']) {
            dump(['smtp_config_applied' => true, 'host' => $config['host'] ?? 'default']);
        }
    }

    /**
     * Sammelt alle Formularwerte.
     *
     * @return array<string, mixed>
     */
    private function collectFormValues(): array
    {
        $values = [];

        // Aus allen value_pool Quellen
        if (isset($this->params['value_pool']) && is_array($this->params['value_pool'])) {
            foreach ($this->params['value_pool'] as $pool) {
                if (is_array($pool)) {
                    $values = array_merge($values, $pool);
                }
            }
        }

        // Direkt aus den Value-Objekten (für no_db Felder etc.)
        if (isset($this->params['values']) && is_array($this->params['values'])) {
            foreach ($this->params['values'] as $valueObj) {
                if (is_object($valueObj) && method_exists($valueObj, 'getName') && method_exists($valueObj, 'getValue')) {
                    $name = $valueObj->getName();
                    // Nur hinzufügen wenn noch nicht vorhanden (value_pool hat Priorität)
                    if ($name && !isset($values[$name])) {
                        $values[$name] = $valueObj->getValue();
                    }
                }
            }
        }

        // Fallback: POST-Daten für Felder die nirgends auftauchen
        if (isset($this->params['this']->objparams['form_name'])) {
            $formName = $this->params['this']->objparams['form_name'];
            $post = rex_request($formName, 'array', []);
            foreach ($post as $key => $value) {
                if (!isset($values[$key]) && is_string($value)) {
                    $values[$key] = $value;
                }
            }
        }

        // ID des Datensatzes hinzufügen
        $values['id'] = $this->params['main_id'] ?? 0;
        $values['REX_SERVER'] = \rex::getServerName();
        $values['REX_DATE'] = date('d.m.Y');
        $values['REX_TIME'] = date('H:i');
        $values['REX_DATETIME'] = date('d.m.Y H:i');

        return $values;
    }

    /**
     * Löst einen Feldwert auf.
     */
    private function resolveFieldValue(?string $field): string
    {
        if (empty($field)) {
            return '';
        }

        // Direkte E-Mail-Adresse
        if (filter_var($field, FILTER_VALIDATE_EMAIL)) {
            return $field;
        }

        // Aus value_pool
        if (isset($this->params['value_pool']['email'][$field])) {
            $value = $this->params['value_pool']['email'][$field];
            return is_array($value) ? implode(', ', $value) : (string) $value;
        }
        if (isset($this->params['value_pool']['sql'][$field])) {
            $value = $this->params['value_pool']['sql'][$field];
            return is_array($value) ? implode(', ', $value) : (string) $value;
        }

        // Feld direkt zurückgeben (könnte feste E-Mail sein)
        return $field;
    }

    /**
     * Ersetzt Platzhalter und bedingte Blöcke.
     *
     * Unterstützte Formate:
     * - ###feldname### - Einfacher Platzhalter
     * - <!--@IF:feldname-->...<!--@ENDIF:feldname--> - Bedingter Block (nur wenn Feld gefüllt)
     * - REX_YFORM_DATA[field="feldname"] - YForm-Kompatibilität
     *
     * @param array<string, mixed> $values
     */
    private function replacePlaceholders(string $text, array $values): string
    {
        // Erst bedingte Blöcke verarbeiten
        $text = $this->processConditionalBlocks($text, $values);

        // Dann einfache Platzhalter ersetzen
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            // Alle unterstützten Formate ersetzen
            $text = str_replace(
                [
                    '###' . $key . '###',
                    'REX_YFORM_DATA[field="' . $key . '"]',
                ],
                (string) $value,
                $text
            );
        }
        return $text;
    }

    /**
     * Verarbeitet bedingte Blöcke.
     *
     * Format: <!--@IF:feldname-->Inhalt wenn gefüllt<!--@ENDIF:feldname-->
     * Der Block wird komplett entfernt wenn das Feld leer ist.
     * So kann man das Template auch im Browser betrachten (HTML-Kommentare).
     *
     * @param array<string, mixed> $values
     */
    private function processConditionalBlocks(string $text, array $values): string
    {
        // Pattern: <!--@IF:feldname-->...<!--@ENDIF:feldname-->
        $pattern = '/<!--@IF:([a-zA-Z0-9_]+)-->(.*?)<!--@ENDIF:\1-->/s';

        return (string) preg_replace_callback($pattern, function ($matches) use ($values) {
            $fieldName = $matches[1];
            $content = $matches[2];

            // Prüfen ob Feld existiert und nicht leer ist
            $value = $values[$fieldName] ?? '';
            if (is_array($value)) {
                $value = implode('', $value);
            }

            // Wenn leer, kompletten Block entfernen
            if ('' === trim((string) $value)) {
                return '';
            }

            // Wenn gefüllt, Inhalt behalten (ohne die Kommentar-Tags)
            return $content;
        }, $text);
    }

    /**
     * Fügt Anhänge zum Template hinzu.
     *
     * @param array<string, mixed> $etpl
     */
    private function addAttachmentsToTemplate(array &$etpl): void
    {
        $attachmentFields = $this->getElement('attachments');
        if (empty($attachmentFields)) {
            return;
        }

        $fields = array_map('trim', explode(',', $attachmentFields));

        foreach ($fields as $fieldName) {
            $this->addFieldAttachmentToTemplate($etpl, $fieldName);
        }
    }

    /**
     * Fügt ein Upload-Feld als Anhang zum Template hinzu.
     *
     * @param array<string, mixed> $etpl
     */
    private function addFieldAttachmentToTemplate(array &$etpl, string $fieldName): void
    {
        // Aus files Pool (enthält Pfade)
        if (isset($this->params['value_pool']['files'][$fieldName])) {
            $fileInfo = $this->params['value_pool']['files'][$fieldName];
            // Format: [filename, filepath, real_filepath]
            if (isset($fileInfo[2]) && file_exists($fileInfo[2])) {
                $etpl['attachments'][] = ['name' => $fileInfo[0], 'path' => $fileInfo[2]];
                return;
            }
            if (isset($fileInfo[1]) && file_exists($fileInfo[1])) {
                $etpl['attachments'][] = ['name' => $fileInfo[0], 'path' => $fileInfo[1]];
                return;
            }
        }

        // Fallback: Dateiname aus value_pool
        $filename = $this->resolveFieldValue($fieldName);
        if (empty($filename)) {
            return;
        }

        // Im Upload-Ordner suchen
        $uploadFolder = $this->getUploadFolder($fieldName);
        $mainId = $this->params['main_id'] ?? 0;
        
        $possiblePaths = [
            $uploadFolder . '/' . $mainId . '_' . $filename,
            $uploadFolder . '/' . $filename,
            rex_path::media($filename),
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $etpl['attachments'][] = ['name' => basename($filename), 'path' => $path];
                return;
            }
        }
    }

    /**
     * Fügt Anhänge zu rex_mailer hinzu.
     */
    private function addAttachments(\rex_mailer $mail): void
    {
        $attachmentFields = $this->getElement('attachments');
        if (empty($attachmentFields)) {
            return;
        }

        $fields = array_map('trim', explode(',', $attachmentFields));

        foreach ($fields as $fieldName) {
            $this->addFieldAttachment($mail, $fieldName);
        }
    }

    /**
     * Fügt ein Upload-Feld als Anhang hinzu.
     */
    private function addFieldAttachment(\rex_mailer $mail, string $fieldName): void
    {
        // Aus files Pool
        if (isset($this->params['value_pool']['files'][$fieldName])) {
            $fileInfo = $this->params['value_pool']['files'][$fieldName];
            if (isset($fileInfo[2]) && file_exists($fileInfo[2])) {
                $mail->addAttachment($fileInfo[2], $fileInfo[0]);
                return;
            }
            if (isset($fileInfo[1]) && file_exists($fileInfo[1])) {
                $mail->addAttachment($fileInfo[1], $fileInfo[0]);
                return;
            }
        }

        // Fallback
        $filename = $this->resolveFieldValue($fieldName);
        if (empty($filename)) {
            return;
        }

        $uploadFolder = $this->getUploadFolder($fieldName);
        $mainId = $this->params['main_id'] ?? 0;

        $possiblePaths = [
            $uploadFolder . '/' . $mainId . '_' . $filename,
            $uploadFolder . '/' . $filename,
            rex_path::media($filename),
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $mail->addAttachment($path, basename($filename));
                return;
            }
        }
    }

    /**
     * Ermittelt den Upload-Ordner für ein Feld.
     */
    private function getUploadFolder(string $fieldName): string
    {
        $table = $this->params['main_table'] ?? '';
        if (!empty($table)) {
            return rex_path::pluginData('yform', 'manager', 'upload/' . $table . '/' . $fieldName);
        }
        return rex_path::pluginData('yform', 'manager', 'upload/frontend');
    }

    /**
     * Wraps body in HTML.
     */
    private function wrapHtmlBody(string $body): string
    {
        if (preg_match('/<html|<!DOCTYPE/i', $body)) {
            return $body;
        }

        if (!preg_match('/<[^>]+>/', $body)) {
            $body = nl2br(rex_escape($body));
        }

        return '<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;line-height:1.6;color:#333;}</style>
</head><body>' . $body . '</body></html>';
    }

    public function getDescription(): string
    {
        return 'mailer|name|label|template|to|[subject]|[body]|[reply_to]|[cc]|[bcc]|[attachments]|[send_mode: 0=immer,1=nur neu,2=nie]|[smtp_config]';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'mailer',
            'values' => [
                'name' => ['type' => 'name', 'label' => rex_i18n::msg('yform_values_defaults_name')],
                'label' => ['type' => 'text', 'label' => rex_i18n::msg('yform_values_defaults_label')],
                'template' => ['type' => 'text', 'label' => rex_i18n::msg('mail_tools_mailer_template'), 'notice' => rex_i18n::msg('mail_tools_mailer_template_notice')],
                'to' => ['type' => 'text', 'label' => rex_i18n::msg('mail_tools_mailer_to'), 'notice' => rex_i18n::msg('mail_tools_mailer_to_notice')],
                'subject' => ['type' => 'text', 'label' => rex_i18n::msg('mail_tools_mailer_subject'), 'notice' => rex_i18n::msg('mail_tools_mailer_subject_notice')],
                'body' => ['type' => 'textarea', 'label' => rex_i18n::msg('mail_tools_mailer_body'), 'notice' => rex_i18n::msg('mail_tools_mailer_body_notice')],
                'reply_to' => ['type' => 'text', 'label' => rex_i18n::msg('mail_tools_mailer_reply_to')],
                'cc' => ['type' => 'text', 'label' => rex_i18n::msg('mail_tools_mailer_cc')],
                'bcc' => ['type' => 'text', 'label' => rex_i18n::msg('mail_tools_mailer_bcc')],
                'attachments' => ['type' => 'text', 'label' => rex_i18n::msg('mail_tools_mailer_attachments'), 'notice' => rex_i18n::msg('mail_tools_mailer_attachments_notice')],
                'send_mode' => [
                    'type' => 'choice',
                    'label' => rex_i18n::msg('mail_tools_mailer_send_mode'),
                    'choices' => [
                        '0' => rex_i18n::msg('mail_tools_mailer_send_always'),
                        '1' => rex_i18n::msg('mail_tools_mailer_send_new_only'),
                        '2' => rex_i18n::msg('mail_tools_mailer_send_never'),
                    ],
                    'default' => '0',
                ],
                'smtp_config' => [
                    'type' => 'textarea',
                    'label' => rex_i18n::msg('mail_tools_mailer_smtp_config'),
                    'notice' => rex_i18n::msg('mail_tools_mailer_smtp_config_notice'),
                    'attributes' => ['class' => 'form-control codemirror', 'data-codemirror-mode' => 'application/json'],
                ],
                'no_db' => ['type' => 'no_db', 'label' => rex_i18n::msg('yform_values_defaults_table'), 'default' => 0],
            ],
            'description' => rex_i18n::msg('mail_tools_mailer_description'),
            'db_type' => ['datetime'],
            'famous' => true,
        ];
    }
}
