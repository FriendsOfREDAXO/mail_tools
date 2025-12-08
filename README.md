# Mail Tools für REDAXO

Ein REDAXO AddOn mit nützlichen E-Mail-Werkzeugen für Überwachung und Validierung.

## Features

- **Domain-Validator**: Prüft E-Mail-Domains via DNS/MX-Lookup
- **Fehler-Log**: Übersicht aller fehlgeschlagenen E-Mails aus dem PHPMailer-Log
- **Cronjob Fehlerbericht**: Automatische Benachrichtigung bei E-Mail-Fehlern
- **Cronjob Retry**: Automatisches erneutes Senden bei temporären Fehlern
- **YForm-Validator**: E-Mail-Domain-Prüfung für Formulare
- **YForm Mailer**: E-Mail-Versand beim Speichern (Tablemanager-kompatibel)

---

## 🔍 E-Mail Domain-Validator

Prüft, ob eine E-Mail-Adresse zu einer existierenden Domain gehört, die E-Mails empfangen kann.

### PHP-Verwendung

```php
use FriendsOfRedaxo\MailTools\DomainValidator;

// Vollständige Validierung
$result = DomainValidator::validate('user@example.com');

if ($result['valid']) {
    echo 'E-Mail-Domain ist gültig';
} else {
    echo 'Fehler: ' . $result['message'];
}

// Ergebnis-Array:
// [
//     'valid' => true/false,
//     'syntax' => true/false,
//     'domain' => true/false,
//     'mx' => true/false,
//     'message' => 'Statusmeldung'
// ]

// Schnelle Prüfung (nur true/false)
if (DomainValidator::isValid('user@example.com')) {
    // Domain existiert
}

// Strenge Prüfung - Domain muss MX-Record haben
if (DomainValidator::isValid('user@example.com', true)) {
    // Domain hat Mailserver
}

// Einzelne Prüfungen
$domain = DomainValidator::extractDomain('user@example.com'); // 'example.com'
$hasMx = DomainValidator::hasMxRecord('example.com');
$hasA = DomainValidator::isDomainValid('example.com');
```

### YForm Validator

In YForm-Formularen kann der `email_domain` Validator verwendet werden:

**Pipe-Format:**
```
validate|email_domain|feldname|Fehlermeldung|0
```

**PHP-Schreibweise:**
```php
$yform->setValidateField('email_domain', ['email', 'Diese E-Mail-Domain existiert nicht', 0]);
```

**Parameter:**
- `feldname`: Name des E-Mail-Feldes
- `Fehlermeldung`: Wird angezeigt wenn Domain ungültig
- `0/1`: Strenge MX-Prüfung (1 = Domain muss MX-Record haben)

**Beispiel im Pipe-Format:**
```
text|email|E-Mail|
validate|email|email|Bitte gültige E-Mail eingeben
validate|email_domain|email|Diese E-Mail-Domain existiert nicht|0
```

**Beispiel in PHP:**
```php
$yform = new rex_yform();
$yform->setObjectparams('form_action', rex_getUrl());

$yform->setValueField('text', ['email', 'E-Mail']);
$yform->setValidateField('email', ['email', 'Bitte gültige E-Mail eingeben']);
$yform->setValidateField('email_domain', ['email', 'Diese E-Mail-Domain existiert nicht', 0]);

echo $yform->getForm();
```

---

## ✉️ YForm Mailer (Tablemanager-kompatibel)

Das `mailer` Value ermöglicht E-Mail-Versand beim Speichern eines Datensatzes - direkt im Tablemanager konfigurierbar.

### Features

- **Templates oder direkter Body** - Beides möglich
- **Dynamische Empfänger** - Aus anderen Tabellenfeldern
- **Upload-Anhänge** - Einfach Feldnamen angeben
- **CC, BCC, Reply-To** - Volle PHPMailer-Unterstützung
- **Versand-Modus** - Immer / Nur bei Neuanlage / Nie (manuell)
- **Speichert Versandzeitpunkt** - Optional in der DB

### Konfiguration im Tablemanager

1. Neues Feld hinzufügen
2. Typ: **mailer**
3. Konfigurieren:
   - **Template**: YForm E-Mail-Template (optional)
   - **Empfänger**: E-Mail oder Feldname (z.B. `email`)
   - **Betreff**: Mit `###feldname###` Platzhaltern
   - **Nachricht**: HTML/Text (wenn kein Template)
   - **Anhänge**: Upload-Feldnamen kommagetrennt
   - **Versand-Modus**: Wann soll gesendet werden?

### Pipe-Format

```
mailer|mail_sent|E-Mail-Versand|kontakt_template|email||###name### hat sich angemeldet|email|||dokument,foto|0
```

### Parameter

| Nr | Parameter | Beschreibung |
|----|-----------|--------------|
| 1 | name | Feldname (speichert Versandzeitpunkt) |
| 2 | label | Bezeichnung im Backend |
| 3 | template | E-Mail-Template Name |
| 4 | to | Empfänger (E-Mail oder Feldname) |
| 5 | subject | Betreff mit Platzhaltern |
| 6 | body | Nachricht (wenn kein Template) |
| 7 | reply_to | Reply-To (E-Mail oder Feldname) |
| 8 | cc | CC-Empfänger |
| 9 | bcc | BCC-Empfänger |
| 10 | attachments | Upload-Feldnamen, kommagetrennt |
| 11 | send_mode | 0=immer, 1=nur neu, 2=nie |

### Versand-Modi

| Modus | Beschreibung |
|-------|--------------|
| **0 - Immer** | Bei jedem Speichern |
| **1 - Nur bei Neuanlage** | Nur beim ersten Speichern |
| **2 - Nie** | Manuell per EP oder Button |

### Eigene SMTP-Konfiguration

Optional kann für jedes Mailer-Feld eine eigene SMTP-Konfiguration hinterlegt werden - unabhängig von der globalen PHPMailer-Konfiguration.

**JSON-Format im Feld "SMTP-Konfiguration":**

```json
{
  "host": "smtp.example.com",
  "port": 587,
  "secure": "tls",
  "auth": true,
  "username": "user@example.com",
  "password": "geheim123",
  "from": "noreply@example.com",
  "from_name": "Meine App"
}
```

**Verfügbare Optionen:**

| Option | Typ | Beschreibung |
|--------|-----|--------------|
| `host` | string | SMTP-Server Adresse |
| `port` | int | Port (25, 465, 587) |
| `secure` | string | Verschlüsselung: `tls`, `ssl` oder leer |
| `auth` | bool | Authentifizierung aktivieren |
| `username` | string | SMTP-Benutzername |
| `password` | string | SMTP-Passwort |
| `from` | string | Absender-E-Mail überschreiben |
| `from_name` | string | Absender-Name überschreiben |
| `debug` | int | Debug-Level (0-4) |
| `timeout` | int | Verbindungs-Timeout in Sekunden |

**Anwendungsfälle:**

- Verschiedene Absender für verschiedene Formulare
- Transaktions-Mails über anderen SMTP-Provider
- Mandantenfähigkeit mit unterschiedlichen Zugangsdaten
- Test- vs. Produktiv-Mailserver

### Beispiele

**Kontaktformular mit Template:**
```
text|name|Name|
text|email|E-Mail|
textarea|message|Nachricht|
mailer|mail_sent|E-Mail|kontakt_tpl|info@firma.de||||email
```

**Bestellbestätigung mit Anhang:**
```
text|kunde_email|Kunden-E-Mail|
upload|rechnung|Rechnung|
mailer|bestaetigung_sent||bestellung_tpl|kunde_email||||rechnung|1
```

### PHP-Schreibweise (YForm)

Das Mailer Value kann auch in PHP verwendet werden:

**Einfaches Beispiel:**
```php
$yform = new rex_yform();
$yform->setObjectparams('form_action', rex_getUrl());

$yform->setValueField('text', ['name', 'Name']);
$yform->setValueField('text', ['email', 'E-Mail']);
$yform->setValueField('textarea', ['message', 'Nachricht']);

// Mailer Value - sendet E-Mail beim Speichern
$yform->setValueField('mailer', [
    'mail_sent',              // name - Feldname (speichert Versandzeitpunkt)
    'E-Mail-Versand',         // label
    'kontakt_template',       // template - YForm E-Mail-Template
    'email',                  // to - Empfänger (hier: aus Feld "email")
    '',                       // subject - leer wenn Template verwendet
    '',                       // body - leer wenn Template verwendet
    'email',                  // reply_to
    '',                       // cc
    '',                       // bcc
    '',                       // attachments
    0                         // send_mode: 0=immer, 1=nur neu, 2=nie
]);

echo $yform->getForm();
```

**Mit direktem Body (ohne Template):**
```php
$yform->setValueField('mailer', [
    'notification_sent',
    'Benachrichtigung',
    '',                                           // kein Template
    'admin@example.com',                          // feste E-Mail
    'Neue Anfrage von ###name###',                // Betreff mit Platzhalter
    '<h1>Neue Anfrage</h1><p>###message###</p>', // HTML-Body
    'email',                                      // reply_to aus Formularfeld
    '',                                           // cc
    'archiv@example.com',                         // bcc
    '',                                           // attachments
    0                                             // send_mode
]);
```

**Mit Upload-Anhängen:**
```php
$yform->setValueField('upload', ['dokument', 'Dokument hochladen', '', '', 'pdf,doc,docx']);
$yform->setValueField('upload', ['foto', 'Foto', '', '', 'jpg,png']);

$yform->setValueField('mailer', [
    'mail_sent',
    'E-Mail',
    'bewerbung_template',
    'hr@firma.de',
    '',
    '',
    'email',
    '',
    '',
    'dokument,foto',    // Anhänge: Feldnamen kommagetrennt
    1                   // nur bei Neuanlage
]);
```

### Platzhalter

| Platzhalter | Beschreibung |
|-------------|--------------|
| `###feldname###` | Wert eines Tabellenfelds |
| `###id###` | ID des Datensatzes |
| `###REX_SERVER###` | Servername |
| `###REX_DATE###` | Datum (TT.MM.JJJJ) |
| `###REX_DATETIME###` | Datum und Uhrzeit |

### Bedingte Blöcke

Mit bedingten Blöcken können Sie Inhalte nur anzeigen, wenn ein Feld ausgefüllt wurde. Das Template bleibt dabei im Browser lesbar (HTML-Kommentare).

**Syntax:**
```html
<!--@IF:feldname-->
  Dieser Text erscheint nur, wenn "feldname" ausgefüllt ist.
  Telefon: ###telefon###
<!--@ENDIF:feldname-->
```

**Beispiel E-Mail-Template:**
```html
<h1>Neue Kontaktanfrage</h1>

<p><strong>Name:</strong> ###name###</p>
<p><strong>E-Mail:</strong> ###email###</p>

<!--@IF:telefon-->
<p><strong>Telefon:</strong> ###telefon###</p>
<!--@ENDIF:telefon-->

<!--@IF:firma-->
<p><strong>Firma:</strong> ###firma###</p>
<!--@ENDIF:firma-->

<h2>Nachricht</h2>
<p>###message###</p>
```

> **Tipp:** Da die Bedingungen als HTML-Kommentare formatiert sind, können Sie das Template einfach im Browser öffnen und das Layout prüfen - die Kommentare werden nicht angezeigt.

---

## 📊 Fehler-Log

Unter **E-Mail Tools → Fehler-Log** werden alle fehlgeschlagenen E-Mails angezeigt:

- **Statistiken**: Fehler heute, diese Woche, diesen Monat, gesamt
- **Zeitfilter**: Letzte Stunde bis alle Einträge
- **Details**: Zeitpunkt, Empfänger, Betreff, Fehlermeldung

### PHP-Verwendung

```php
use FriendsOfRedaxo\MailTools\LogParser;

// Alle fehlgeschlagenen E-Mails
$failed = LogParser::getFailedEmails();

// Noch nicht gemeldete Fehler
$unreported = LogParser::getUnreportedFailedEmails();

// Statistiken
$stats = LogParser::getStatistics();
// [
//     'today' => 2,
//     'week' => 5,
//     'month' => 12,
//     'total' => 47,
//     'top_domains' => ['example.com' => 3, ...]
// ]

// Als gemeldet markieren
LogParser::markAsReported($unreported);
```

---

## 📧 Cronjob für Fehlerberichte

Ein Cronjob analysiert regelmäßig das PHPMailer-Log und sendet Berichte über fehlgeschlagene E-Mails per E-Mail.

### Einrichtung

1. Gehen Sie zu **System → Cronjob**
2. Neuen Cronjob erstellen
3. Typ: **E-Mail Fehlerbericht**
4. Konfigurieren Sie:
   - **Empfänger**: Kommagetrennte E-Mail-Adressen
   - **Nur bei Fehlern**: Bericht nur senden wenn neue Fehler vorhanden
   - **EML anhängen**: Archivierte E-Mails als Anhang mitsenden (optional)
5. Zeitplan festlegen (z.B. täglich)
6. Cronjob aktivieren

### Report-Vorschau

Unter **E-Mail Tools → Test** können Sie einen Test-Report an sich selbst senden, um das Aussehen zu prüfen.

---

## 🔄 Cronjob für Retry

Ein zweiter Cronjob versendet E-Mails mit temporären Fehlern automatisch erneut.

### Temporäre Fehler (Retry sinnvoll)

- Connection Timeout / Refused
- SMTP 4xx Codes (421, 450, 451, 452)
- Greylisting
- Rate Limiting
- Server überlastet

### Permanente Fehler (kein Retry)

- User unknown / Mailbox not found
- Domain not found
- SMTP 5xx Codes (550, 551, 552, 553, 554)
- Blacklisted / Blocked

### Einrichtung

1. Gehen Sie zu **System → Cronjob**
2. Neuen Cronjob erstellen
3. Typ: **E-Mail Retry**
4. Zeitplan festlegen (z.B. stündlich)
5. Cronjob aktivieren

### Retry-Logik

- Maximal 3 Versuche pro E-Mail
- Wartezeiten: 1. Retry nach 1h, 2. nach 6h, 3. nach 24h
- Benötigt archivierte E-Mails (PHPMailer Archiv-Funktion)

### PHP-Verwendung

```php
use FriendsOfRedaxo\MailTools\RetryHandler;

// Prüfen ob Fehler temporär ist
if (RetryHandler::isTemporaryError($errorMessage)) {
    // Retry sinnvoll
}

// Alle retry-fähigen E-Mails
$retryable = RetryHandler::getRetryableEmails();

// Einzelne E-Mail erneut senden
$result = RetryHandler::retry($hash);

// Alle fälligen Retries ausführen
$stats = RetryHandler::processRetries();
// ['total' => 5, 'success' => 3, 'failed' => 2]
```

---

## 🧪 Testseite

Unter **E-Mail Tools → Test** können Sie:

1. **Domain-Validator testen**: E-Mail eingeben und Domain-Existenz prüfen
2. **Test-Report senden**: Beispiel-Fehlerbericht an eine E-Mail-Adresse senden

---

## Installation

1. Im REDAXO Installer nach `mail_tools` suchen
2. AddOn installieren und aktivieren

## Voraussetzungen

| Paket | Version |
|-------|---------|
| REDAXO | >= 5.17 |
| PHP | >= 8.1 |
| PHPMailer | >= 2.10 |

### Empfohlen

- **Cronjob AddOn** - für automatische Fehlerberichte
- **YForm AddOn** - für den E-Mail-Domain-Validator in Formularen

---

## Lizenz

MIT License

## Credits

[Friends Of REDAXO](https://friendsofredaxo.github.io/)
