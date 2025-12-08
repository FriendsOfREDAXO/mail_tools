<?php

use FriendsOfREDAXO\MailTools\SmtpDiagnostics;

/** @var rex_addon $this */

// CSRF-Token
$csrfToken = rex_csrf_token::factory('mail_tools_diagnostics');

// API-Handler für AJAX-Requests
if (rex_request::isXmlHttpRequest()) {
    rex_response::cleanOutputBuffers();
    
    $action = rex_request('action', 'string', '');
    
    if (!$csrfToken->isValid()) {
        rex_response::sendJson(['error' => 'Invalid CSRF token']);
        exit;
    }
    
    $diagnostics = new SmtpDiagnostics();
    
    switch ($action) {
        case 'run_diagnostics':
            $results = $diagnostics->runFullDiagnosis();
            rex_response::sendJson($results);
            break;
            
        case 'test_connection':
            $results = $diagnostics->testConnection();
            rex_response::sendJson($results);
            break;
            
        case 'send_test_mail':
            $recipient = rex_request('recipient', 'string', '');
            $results = $diagnostics->sendTestMail($recipient);
            rex_response::sendJson($results);
            break;
            
        default:
            rex_response::sendJson(['error' => 'Unknown action']);
    }
    exit;
}

// Aktuelle Konfiguration laden
$diagnostics = new SmtpDiagnostics();
$currentConfig = $diagnostics->getConfig();

$content = '';

// JavaScript
$content .= '
<script>
const MailToolsDiag = {
    csrfToken: ' . json_encode($csrfToken->getValue()) . ',
    
    async runDiagnostics() {
        const container = document.getElementById("mail-tools-diag-results");
        container.innerHTML = `
            <div class="mail-tools-diag-loading">
                <div class="mail-tools-diag-spinner"></div>
                <p>' . rex_i18n::msg('mail_tools_diag_running') . '</p>
            </div>
        `;
        
        try {
            const response = await fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams({
                    action: "run_diagnostics",
                    _csrf_token: this.csrfToken
                })
            });
            
            const data = await response.json();
            this.renderResults(data);
        } catch (error) {
            container.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
        }
    },
    
    async testConnection() {
        const btn = document.getElementById("btn-test-connection");
        const originalText = btn.innerHTML;
        btn.innerHTML = \'<i class="fa fa-spinner fa-spin"></i> ' . rex_i18n::msg('mail_tools_diag_testing') . '\';
        btn.disabled = true;
        
        try {
            const response = await fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams({
                    action: "test_connection",
                    _csrf_token: this.csrfToken
                })
            });
            
            const data = await response.json();
            this.showConnectionResult(data);
        } catch (error) {
            document.getElementById("mail-tools-test-result").innerHTML = 
                `<div class="alert alert-danger">${error.message}</div>`;
        }
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    },
    
    async sendTestMail() {
        const recipient = document.getElementById("test-mail-recipient").value;
        if (!recipient) {
            alert("' . rex_i18n::msg('mail_tools_diag_enter_recipient') . '");
            return;
        }
        
        const btn = document.getElementById("btn-send-test");
        const originalText = btn.innerHTML;
        btn.innerHTML = \'<i class="fa fa-spinner fa-spin"></i> ' . rex_i18n::msg('mail_tools_diag_sending') . '\';
        btn.disabled = true;
        
        try {
            const response = await fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams({
                    action: "send_test_mail",
                    recipient: recipient,
                    _csrf_token: this.csrfToken
                })
            });
            
            const data = await response.json();
            this.showTestMailResult(data);
        } catch (error) {
            document.getElementById("mail-tools-test-result").innerHTML = 
                `<div class="alert alert-danger">${error.message}</div>`;
        }
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    },
    
    showConnectionResult(data) {
        let html = "";
        
        if (data.success) {
            html = `
                <div class="alert alert-success">
                    <h4><i class="fa fa-check-circle"></i> ' . rex_i18n::msg('mail_tools_diag_connection_ok') . '</h4>
                    <p>${data.message}</p>
                </div>
            `;
        } else {
            html = `
                <div class="alert alert-danger">
                    <h4><i class="fa fa-times-circle"></i> ' . rex_i18n::msg('mail_tools_diag_connection_failed') . '</h4>
                    <p>${data.message}</p>
                </div>
            `;
            
            // Hilfestellung anzeigen
            if (data.help && data.help.length > 0) {
                html += `
                    <div class="mail-tools-diag-help">
                        <h5><i class="fa fa-lightbulb-o"></i> ' . rex_i18n::msg('mail_tools_diag_possible_solutions') . '</h5>
                        <ul>
                            ${data.help.map(h => `<li>${h}</li>`).join("")}
                        </ul>
                    </div>
                `;
            }
        }
        
        // Debug-Ausgabe
        if (data.debug) {
            html += `
                <details class="mail-tools-diag-details">
                    <summary><i class="fa fa-code"></i> ' . rex_i18n::msg('mail_tools_diag_technical_details') . '</summary>
                    <div class="mail-tools-diag-debug">${this.escapeHtml(data.debug)}</div>
                </details>
            `;
        }
        
        document.getElementById("mail-tools-test-result").innerHTML = html;
    },
    
    showTestMailResult(data) {
        let html = "";
        
        if (data.success) {
            html = `
                <div class="alert alert-success">
                    <h4><i class="fa fa-check-circle"></i> ' . rex_i18n::msg('mail_tools_diag_mail_sent') . '</h4>
                    <p>${data.message}</p>
                </div>
            `;
        } else {
            html = `
                <div class="alert alert-danger">
                    <h4><i class="fa fa-times-circle"></i> ' . rex_i18n::msg('mail_tools_diag_mail_failed') . '</h4>
                    <p><strong>' . rex_i18n::msg('mail_tools_diag_error') . ':</strong> ${data.message}</p>
                </div>
            `;
            
            // Verständliche Erklärung
            if (data.explanation) {
                html += `
                    <div class="mail-tools-diag-explanation">
                        <h5><i class="fa fa-info-circle"></i> ' . rex_i18n::msg('mail_tools_diag_what_means') . '</h5>
                        <p>${data.explanation}</p>
                    </div>
                `;
            }
            
            // Hilfestellung anzeigen
            if (data.help && data.help.length > 0) {
                html += `
                    <div class="mail-tools-diag-help">
                        <h5><i class="fa fa-lightbulb-o"></i> ' . rex_i18n::msg('mail_tools_diag_possible_solutions') . '</h5>
                        <ul>
                            ${data.help.map(h => `<li>${h}</li>`).join("")}
                        </ul>
                    </div>
                `;
            }
        }
        
        // Debug-Ausgabe
        if (data.debug) {
            html += `
                <details class="mail-tools-diag-details">
                    <summary><i class="fa fa-code"></i> ' . rex_i18n::msg('mail_tools_diag_technical_details') . '</summary>
                    <div class="mail-tools-diag-debug">${this.escapeHtml(data.debug)}</div>
                </details>
            `;
        }
        
        document.getElementById("mail-tools-test-result").innerHTML = html;
    },
    
    renderResults(data) {
        const container = document.getElementById("mail-tools-diag-results");
        let html = "";
        
        // Overall Status
        const statusIcon = {
            ok: "fa-check-circle",
            warning: "fa-exclamation-triangle", 
            error: "fa-times-circle"
        };
        
        const statusText = {
            ok: "' . rex_i18n::msg('mail_tools_diag_status_ok') . '",
            warning: "' . rex_i18n::msg('mail_tools_diag_status_warning') . '",
            error: "' . rex_i18n::msg('mail_tools_diag_status_error') . '"
        };
        
        html += `
            <div class="mail-tools-diag-card">
                <div class="mail-tools-diag-overall">
                    <div class="mail-tools-diag-overall-icon ${data.overall_status}">
                        <i class="fa ${statusIcon[data.overall_status]}"></i>
                    </div>
                    <h2>${statusText[data.overall_status]}</h2>
        `;
        
        if (data.provider) {
            html += `
                <div class="mail-tools-diag-provider">
                    <i class="fa fa-cloud"></i>
                    <span>' . rex_i18n::msg('mail_tools_diag_detected_provider') . ': <strong>${data.provider.name}</strong></span>
                </div>
            `;
        }
        
        html += `
                    <p class="text-muted">' . rex_i18n::msg('mail_tools_diag_timestamp') . ': ${data.timestamp}</p>
                </div>
            </div>
        `;
        
        // Einzelne Checks
        for (const [key, check] of Object.entries(data.checks)) {
            html += this.renderCheck(check);
        }
        
        // Empfehlungen
        if (data.recommendations && data.recommendations.length > 0) {
            html += `
                <div class="mail-tools-diag-card">
                    <div class="mail-tools-diag-header">
                        <i class="fa fa-lightbulb-o"></i>
                        <h3>' . rex_i18n::msg('mail_tools_diag_recommendations') . '</h3>
                    </div>
                    <div class="mail-tools-diag-body">
                        <div class="mail-tools-diag-recommendations">
            `;
            
            data.recommendations.forEach(rec => {
                html += `
                    <div class="mail-tools-diag-rec-card ${rec.type}">
                        <div class="mail-tools-diag-rec-content">
                            <h4>${rec.title}</h4>
                            <p>${rec.description}</p>
                        </div>
                    </div>
                `;
            });
            
            html += `
                        </div>
                    </div>
                </div>
            `;
        }
        
        container.innerHTML = html;
    },
    
    renderCheck(check) {
        const statusClass = `mail-tools-diag-status-${check.status}`;
        const statusIcon = {
            ok: "fa-check",
            warning: "fa-exclamation-triangle",
            error: "fa-times",
            info: "fa-info"
        };
        
        let html = `
            <div class="mail-tools-diag-card">
                <div class="mail-tools-diag-header">
                    <span class="mail-tools-diag-status ${statusClass}">
                        <i class="fa ${statusIcon[check.status]}"></i>
                    </span>
                    <h3>${check.title}</h3>
                </div>
                <div class="mail-tools-diag-body">
        `;
        
        // Issues
        if (check.issues && check.issues.length > 0) {
            html += `<ul class="mail-tools-diag-issues">`;
            check.issues.forEach(issue => {
                html += `<li><i class="fa fa-exclamation-circle"></i> ${issue}</li>`;
            });
            html += `</ul>`;
        }
        
        // Details
        if (check.details && check.details.length > 0) {
            html += `<table class="mail-tools-diag-table">`;
            check.details.forEach(detail => {
                const detailStatusClass = detail.status ? `mail-tools-diag-status-${detail.status}` : "";
                html += `
                    <tr>
                        <td class="label-col">${detail.label}</td>
                        <td>
                            <span class="${detailStatusClass}">${detail.value}</span>
                            ${detail.hint ? `<div class="mail-tools-diag-hint">${detail.hint}</div>` : ""}
                        </td>
                    </tr>
                `;
            });
            html += `</table>`;
        }
        
        html += `
                </div>
            </div>
        `;
        
        return html;
    },
    
    escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }
};

document.addEventListener("DOMContentLoaded", function() {
    // Automatisch Diagnose starten
    MailToolsDiag.runDiagnostics();
});
</script>
';

// Hauptinhalt
$content .= '<div class="mail-tools-diag">';

// Testmail-Box
$content .= '
<div class="mail-tools-diag-testbox">
    <h4><i class="fa fa-envelope"></i> ' . rex_i18n::msg('mail_tools_diag_send_testmail') . '</h4>
    <p class="text-muted">' . rex_i18n::msg('mail_tools_diag_testmail_desc') . '</p>
    <div class="mail-tools-diag-testform">
        <div class="form-group">
            <label for="test-mail-recipient">' . rex_i18n::msg('mail_tools_diag_recipient') . '</label>
            <input type="email" id="test-mail-recipient" class="form-control" 
                   placeholder="test@example.com" value="' . rex_escape($currentConfig['from']) . '">
        </div>
        <div class="mail-tools-diag-testbuttons">
            <button type="button" id="btn-test-connection" class="btn btn-default" onclick="MailToolsDiag.testConnection()">
                <i class="fa fa-plug"></i> ' . rex_i18n::msg('mail_tools_diag_test_connection') . '
            </button>
            <button type="button" id="btn-send-test" class="btn btn-success" onclick="MailToolsDiag.sendTestMail()">
                <i class="fa fa-paper-plane"></i> ' . rex_i18n::msg('mail_tools_diag_send_test') . '
            </button>
        </div>
    </div>
    <div id="mail-tools-test-result"></div>
</div>
';

// Aktionen
$content .= '
<div class="mail-tools-diag-actions">
    <button type="button" class="btn btn-primary" onclick="MailToolsDiag.runDiagnostics()">
        <i class="fa fa-refresh"></i> ' . rex_i18n::msg('mail_tools_diag_run_again') . '
    </button>
    <a href="' . rex_url::backendPage('phpmailer/config') . '" class="btn btn-default">
        <i class="fa fa-cog"></i> ' . rex_i18n::msg('mail_tools_diag_open_config') . '
    </a>
</div>
';

// Ergebnis-Container
$content .= '<div id="mail-tools-diag-results"></div>';

$content .= '</div>';

// Fragment für Content-Box
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('mail_tools_diag_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
