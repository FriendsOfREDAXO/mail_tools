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
            
        case 'auto_configure':
            $email = rex_request('email', 'string', '');
            $config = $diagnostics->autoConfigureFromEmail($email);
            rex_response::sendJson(['config' => $config]);
            break;
            
        case 'apply_fix':
            $key = rex_request('key', 'string', '');
            $value = rex_request('value', 'string', '');
            $success = $diagnostics->applyConfigChange($key, $value);
            rex_response::sendJson(['success' => $success]);
            break;
            
        case 'apply_auto_config':
            $config = rex_request('config', 'array', []);
            $password = rex_request('password', 'string', '');
            if ($password) {
                $config['password'] = $password;
                $diagnostics->applyConfigChange('password', $password);
            }
            $success = $diagnostics->applyAutoConfig($config);
            rex_response::sendJson(['success' => $success]);
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

// CSS für die Diagnose-Seite (inline, da spezifisch)
$content .= '
<style>
.mail-tools-diag {
    --diag-ok: #28a745;
    --diag-warning: #ffc107;
    --diag-error: #dc3545;
    --diag-info: #17a2b8;
    --diag-bg: #fff;
    --diag-border: #dee2e6;
    --diag-text: #212529;
    --diag-muted: #6c757d;
}

.rex-theme-dark .mail-tools-diag {
    --diag-bg: #2d2d2d;
    --diag-border: #444;
    --diag-text: #e9e9e9;
    --diag-muted: #999;
}

.mail-tools-diag-card {
    background: var(--diag-bg);
    border: 1px solid var(--diag-border);
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.mail-tools-diag-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--diag-border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.mail-tools-diag-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.mail-tools-diag-body {
    padding: 20px;
}

.mail-tools-diag-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.mail-tools-diag-status-ok {
    background: rgba(40, 167, 69, 0.15);
    color: var(--diag-ok);
}

.mail-tools-diag-status-warning {
    background: rgba(255, 193, 7, 0.15);
    color: #856404;
}

.rex-theme-dark .mail-tools-diag-status-warning {
    color: var(--diag-warning);
}

.mail-tools-diag-status-error {
    background: rgba(220, 53, 69, 0.15);
    color: var(--diag-error);
}

.mail-tools-diag-status-info {
    background: rgba(23, 162, 184, 0.15);
    color: var(--diag-info);
}

.mail-tools-diag-table {
    width: 100%;
    border-collapse: collapse;
}

.mail-tools-diag-table td {
    padding: 10px 15px;
    border-bottom: 1px solid var(--diag-border);
    vertical-align: top;
}

.mail-tools-diag-table tr:last-child td {
    border-bottom: none;
}

.mail-tools-diag-table .label-col {
    width: 200px;
    color: var(--diag-muted);
    font-weight: 500;
}

.mail-tools-diag-hint {
    font-size: 12px;
    color: var(--diag-muted);
    margin-top: 4px;
}

.mail-tools-diag-issues {
    list-style: none;
    padding: 0;
    margin: 0;
}

.mail-tools-diag-issues li {
    padding: 8px 12px;
    margin-bottom: 5px;
    background: rgba(220, 53, 69, 0.1);
    border-left: 3px solid var(--diag-error);
    border-radius: 0 4px 4px 0;
}

.mail-tools-diag-recommendations {
    display: grid;
    gap: 15px;
}

.mail-tools-diag-rec-card {
    border: 1px solid var(--diag-border);
    border-radius: 6px;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.mail-tools-diag-rec-card.auto-fix {
    border-color: var(--diag-ok);
    background: rgba(40, 167, 69, 0.05);
}

.mail-tools-diag-rec-card.security {
    border-color: var(--diag-warning);
    background: rgba(255, 193, 7, 0.05);
}

.mail-tools-diag-rec-content h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
}

.mail-tools-diag-rec-content p {
    margin: 0;
    font-size: 13px;
    color: var(--diag-muted);
}

.mail-tools-diag-overall {
    text-align: center;
    padding: 30px;
}

.mail-tools-diag-overall-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.mail-tools-diag-overall-icon.ok { color: var(--diag-ok); }
.mail-tools-diag-overall-icon.warning { color: var(--diag-warning); }
.mail-tools-diag-overall-icon.error { color: var(--diag-error); }

.mail-tools-diag-provider {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(23, 162, 184, 0.1);
    border-radius: 20px;
    margin-bottom: 15px;
}

.mail-tools-diag-loading {
    text-align: center;
    padding: 60px 20px;
}

.mail-tools-diag-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--diag-border);
    border-top-color: var(--diag-info);
    border-radius: 50%;
    animation: mail-tools-spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes mail-tools-spin {
    to { transform: rotate(360deg); }
}

.mail-tools-diag-debug {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 12px;
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}

.mail-tools-diag-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.mail-tools-auto-config {
    background: linear-gradient(135deg, rgba(23, 162, 184, 0.1) 0%, rgba(40, 167, 69, 0.1) 100%);
    border: 1px dashed var(--diag-info);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.mail-tools-auto-config h4 {
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mail-tools-auto-config-form {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.mail-tools-auto-config-form .form-group {
    flex: 1;
    margin: 0;
}
</style>
';

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
            
            let resultHtml = data.success 
                ? `<div class="alert alert-success"><i class="fa fa-check"></i> ${data.message}</div>`
                : `<div class="alert alert-danger"><i class="fa fa-times"></i> ${data.message}</div>`;
            
            if (data.debug) {
                resultHtml += `
                    <details style="margin-top: 15px;">
                        <summary>' . rex_i18n::msg('mail_tools_diag_show_debug') . '</summary>
                        <div class="mail-tools-diag-debug">${this.escapeHtml(data.debug)}</div>
                    </details>
                `;
            }
            
            document.getElementById("mail-tools-connection-result").innerHTML = resultHtml;
        } catch (error) {
            document.getElementById("mail-tools-connection-result").innerHTML = 
                `<div class="alert alert-danger">${error.message}</div>`;
        }
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    },
    
    async autoConfigure() {
        const email = document.getElementById("auto-config-email").value;
        if (!email) {
            alert("' . rex_i18n::msg('mail_tools_diag_enter_email') . '");
            return;
        }
        
        const btn = document.getElementById("btn-auto-configure");
        const originalText = btn.innerHTML;
        btn.innerHTML = \'<i class="fa fa-spinner fa-spin"></i> ' . rex_i18n::msg('mail_tools_diag_detecting') . '\';
        btn.disabled = true;
        
        try {
            const response = await fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams({
                    action: "auto_configure",
                    email: email,
                    _csrf_token: this.csrfToken
                })
            });
            
            const data = await response.json();
            
            if (data.config) {
                this.showAutoConfigResult(data.config);
            } else {
                document.getElementById("mail-tools-auto-config-result").innerHTML = 
                    `<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> ' . rex_i18n::msg('mail_tools_diag_no_config_found') . '</div>`;
            }
        } catch (error) {
            document.getElementById("mail-tools-auto-config-result").innerHTML = 
                `<div class="alert alert-danger">${error.message}</div>`;
        }
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    },
    
    showAutoConfigResult(config) {
        let html = `
            <div class="mail-tools-diag-card" style="margin-top: 15px;">
                <div class="mail-tools-diag-header">
                    <i class="fa fa-magic"></i>
                    <h3>' . rex_i18n::msg('mail_tools_diag_detected_config') . '</h3>
                    <span class="mail-tools-diag-status mail-tools-diag-status-ok">
                        <i class="fa fa-check"></i> ${config.provider_name}
                    </span>
                </div>
                <div class="mail-tools-diag-body">
                    <table class="mail-tools-diag-table">
                        <tr>
                            <td class="label-col">' . rex_i18n::msg('mail_tools_diag_smtp_host') . '</td>
                            <td><code>${config.host}</code></td>
                        </tr>
                        <tr>
                            <td class="label-col">' . rex_i18n::msg('mail_tools_diag_port') . '</td>
                            <td><code>${config.port}</code></td>
                        </tr>
                        <tr>
                            <td class="label-col">' . rex_i18n::msg('mail_tools_diag_encryption') . '</td>
                            <td><code>${config.security.toUpperCase()}</code></td>
                        </tr>
                        <tr>
                            <td class="label-col">' . rex_i18n::msg('mail_tools_diag_username') . '</td>
                            <td><code>${config.username}</code></td>
                        </tr>
                        <tr>
                            <td class="label-col">' . rex_i18n::msg('mail_tools_diag_password') . '</td>
                            <td>
                                <input type="password" id="auto-config-password" class="form-control" 
                                       placeholder="' . rex_i18n::msg('mail_tools_diag_enter_password') . '" style="max-width: 300px;">
                            </td>
                        </tr>
                    </table>
        `;
        
        if (config.notes && config.notes.length > 0) {
            html += `<div class="alert alert-info" style="margin-top: 15px;"><strong>' . rex_i18n::msg('mail_tools_diag_provider_notes') . '</strong><ul style="margin-bottom: 0;">`;
            config.notes.forEach(note => {
                html += `<li>${note}</li>`;
            });
            html += `</ul></div>`;
        }
        
        html += `
                    <div style="margin-top: 15px;">
                        <button type="button" class="btn btn-apply" onclick="MailToolsDiag.applyAutoConfig(${JSON.stringify(config).replace(/"/g, "&quot;")})">
                            <i class="fa fa-save"></i> ' . rex_i18n::msg('mail_tools_diag_apply_config') . '
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById("mail-tools-auto-config-result").innerHTML = html;
    },
    
    async applyAutoConfig(config) {
        const password = document.getElementById("auto-config-password")?.value || "";
        
        if (!password) {
            if (!confirm("' . rex_i18n::msg('mail_tools_diag_no_password_confirm') . '")) {
                return;
            }
        }
        
        try {
            const response = await fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams({
                    action: "apply_auto_config",
                    config: JSON.stringify(config),
                    password: password,
                    _csrf_token: this.csrfToken
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert("' . rex_i18n::msg('mail_tools_diag_config_applied') . '");
                window.location.reload();
            } else {
                alert("' . rex_i18n::msg('mail_tools_diag_config_failed') . '");
            }
        } catch (error) {
            alert(error.message);
        }
    },
    
    async applyFix(key, value) {
        try {
            const response = await fetch(window.location.href, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams({
                    action: "apply_fix",
                    key: key,
                    value: value,
                    _csrf_token: this.csrfToken
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert("' . rex_i18n::msg('mail_tools_diag_fix_applied') . '");
                window.location.reload();
            }
        } catch (error) {
            alert(error.message);
        }
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
                `;
                
                if (rec.type === "auto_fix" && rec.value) {
                    html += `
                        <button type="button" class="btn btn-success btn-sm" 
                                onclick="MailToolsDiag.applyFix(\'${rec.key}\', \'${rec.value}\')">
                            <i class="fa fa-wrench"></i> ' . rex_i18n::msg('mail_tools_diag_apply_fix') . '
                        </button>
                    `;
                }
                
                html += `</div>`;
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

// Aktuelle Konfiguration anzeigen
$content .= '<div class="mail-tools-diag">';

// Auto-Konfiguration Box
$content .= '
<div class="mail-tools-auto-config">
    <h4><i class="fa fa-magic"></i> ' . rex_i18n::msg('mail_tools_diag_auto_config') . '</h4>
    <p class="text-muted">' . rex_i18n::msg('mail_tools_diag_auto_config_desc') . '</p>
    <div class="mail-tools-auto-config-form">
        <div class="form-group">
            <label for="auto-config-email">' . rex_i18n::msg('mail_tools_diag_your_email') . '</label>
            <input type="email" id="auto-config-email" class="form-control" 
                   placeholder="ihre@email-adresse.de" value="' . rex_escape($currentConfig['from']) . '">
        </div>
        <button type="button" id="btn-auto-configure" class="btn btn-primary" onclick="MailToolsDiag.autoConfigure()">
            <i class="fa fa-search"></i> ' . rex_i18n::msg('mail_tools_diag_detect_settings') . '
        </button>
    </div>
    <div id="mail-tools-auto-config-result"></div>
</div>
';

// Aktionen
$content .= '
<div class="mail-tools-diag-actions" style="margin-bottom: 20px;">
    <button type="button" class="btn btn-primary" onclick="MailToolsDiag.runDiagnostics()">
        <i class="fa fa-refresh"></i> ' . rex_i18n::msg('mail_tools_diag_run_again') . '
    </button>
    <button type="button" id="btn-test-connection" class="btn btn-success" onclick="MailToolsDiag.testConnection()">
        <i class="fa fa-plug"></i> ' . rex_i18n::msg('mail_tools_diag_test_connection') . '
    </button>
    <a href="' . rex_url::backendPage('phpmailer/config') . '" class="btn btn-default">
        <i class="fa fa-cog"></i> ' . rex_i18n::msg('mail_tools_diag_open_config') . '
    </a>
</div>
<div id="mail-tools-connection-result"></div>
';

// Ergebnis-Container
$content .= '<div id="mail-tools-diag-results"></div>';

$content .= '</div>';

// Fragment für Content-Box
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('mail_tools_diag_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
