<?php

/**
 * Mail Tools - Einstellungen.
 *
 * @var rex_addon $this
 */

$addon = rex_addon::get('mail_tools');

// Formular verarbeiten
if (rex_post('save_settings', 'bool', false)) {
    $addon->setConfig('validate_domains', rex_post('validate_domains', 'bool', false));
    $addon->setConfig('invalid_domain_action', rex_post('invalid_domain_action', 'string', 'block_invalid'));
    $addon->setConfig('blocked_tlds', rex_post('blocked_tlds', 'string', ''));
    $addon->setConfig('check_mx', rex_post('check_mx', 'bool', false));
    $addon->setConfig('check_disposable', rex_post('check_disposable', 'bool', false));
    $addon->setConfig('check_typos', rex_post('check_typos', 'bool', false));
    $addon->setConfig('report_recipients', rex_post('report_recipients', 'string', ''));
    
    // IMAP Settings
    $addon->setConfig('imap_host', rex_post('imap_host', 'string', ''));
    $addon->setConfig('imap_port', rex_post('imap_port', 'int', 993));
    $addon->setConfig('imap_username', rex_post('imap_username', 'string', ''));
    $addon->setConfig('imap_password', rex_post('imap_password', 'string', ''));
    $addon->setConfig('imap_folder', rex_post('imap_folder', 'string', 'INBOX'));
    $addon->setConfig('imap_delete_bounces', rex_post('imap_delete_bounces', 'bool', false));

    // GDPR Settings
    $addon->setConfig('gdpr_anonymize_days', rex_post('gdpr_anonymize_days', 'int', 30));
    
    echo rex_view::success($addon->i18n('settings_saved'));
}

// Aktuelle Werte
$validateDomains = $addon->getConfig('validate_domains', true);
$invalidAction = $addon->getConfig('invalid_domain_action', 'block_invalid');
$blockedTlds = $addon->getConfig('blocked_tlds', '');
$checkMx = $addon->getConfig('check_mx', false);
$checkDisposable = $addon->getConfig('check_disposable', false);
$checkTypos = $addon->getConfig('check_typos', false);
$reportRecipients = $addon->getConfig('report_recipients', '');

// IMAP
$imapHost = $addon->getConfig('imap_host', '');
$imapPort = $addon->getConfig('imap_port', 993);
$imapUsername = $addon->getConfig('imap_username', '');
$imapPassword = $addon->getConfig('imap_password', '');
$imapFolder = $addon->getConfig('imap_folder', 'INBOX');
$imapDeleteBounces = $addon->getConfig('imap_delete_bounces', false);

// GDPR
$gdprAnonymizeDays = $addon->getConfig('gdpr_anonymize_days', 30);

$content = '
<form action="' . rex_url::currentBackendPage() . '" method="post">

    <fieldset>
        <legend>' . $addon->i18n('settings_validation_title') . '</legend>
        
        <div class="form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="validate_domains" value="1"' . ($validateDomains ? ' checked' : '') . '>
                    ' . $addon->i18n('settings_validate_domains_label') . '
                </label>
                <p class="help-block">' . $addon->i18n('settings_validate_domains_notice') . '</p>
            </div>
        </div>

        <div class="form-group">
            <label for="invalid_domain_action">' . $addon->i18n('settings_invalid_action_label') . '</label>
            <select class="form-control" id="invalid_domain_action" name="invalid_domain_action">
                <option value="block_all"' . ('block_all' === $invalidAction ? ' selected' : '') . '>' . $addon->i18n('settings_action_block_all') . '</option>
                <option value="block_invalid"' . ('block_invalid' === $invalidAction ? ' selected' : '') . '>' . $addon->i18n('settings_action_block_invalid') . '</option>
                <option value="log_only"' . ('log_only' === $invalidAction ? ' selected' : '') . '>' . $addon->i18n('settings_action_log_only') . '</option>
            </select>
            <p class="help-block">' . $addon->i18n('settings_invalid_action_notice') . '</p>
        </div>
    </fieldset>

    <fieldset>
        <legend>' . $addon->i18n('settings_additional_checks') . '</legend>
        
        <div class="form-group">
            <label for="blocked_tlds">' . $addon->i18n('settings_blocked_tlds_label') . '</label>
            <textarea class="form-control" id="blocked_tlds" name="blocked_tlds" rows="3" 
                      placeholder=".ru, .cn, .xyz">' . rex_escape($blockedTlds) . '</textarea>
            <p class="help-block">' . $addon->i18n('settings_blocked_tlds_notice') . '</p>
        </div>

        <div class="form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="check_mx" value="1"' . ($checkMx ? ' checked' : '') . '>
                    ' . $addon->i18n('settings_check_mx_label') . '
                </label>
                <p class="help-block">' . $addon->i18n('settings_check_mx_notice') . '</p>
            </div>
        </div>

        <div class="form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="check_disposable" value="1"' . ($checkDisposable ? ' checked' : '') . '>
                    ' . $addon->i18n('settings_check_disposable_label') . '
                </label>
                <p class="help-block">' . $addon->i18n('settings_check_disposable_notice') . '</p>
            </div>
        </div>

        <div class="form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="check_typos" value="1"' . ($checkTypos ? ' checked' : '') . '>
                    ' . $addon->i18n('settings_check_typos_label') . '
                </label>
                <p class="help-block">' . $addon->i18n('settings_check_typos_notice') . '</p>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>' . $addon->i18n('settings_cronjob_title') . '</legend>
        
        <div class="form-group">
            <label for="report_recipients">' . $addon->i18n('settings_report_recipients_label') . '</label>
            <input type="text" class="form-control" id="report_recipients" name="report_recipients" 
                   value="' . rex_escape($reportRecipients) . '" placeholder="admin@example.com, info@example.com">
            <p class="help-block">' . $addon->i18n('settings_report_recipients_notice') . '</p>
        </div>
    </fieldset>

    <fieldset>
        <legend>IMAP Bounce Handling</legend>
        
        <div class="form-group">
            <label for="imap_host">IMAP Host</label>
            <input type="text" class="form-control" id="imap_host" name="imap_host" value="' . rex_escape($imapHost) . '">
        </div>
        
        <div class="form-group">
            <label for="imap_port">IMAP Port</label>
            <input type="number" class="form-control" id="imap_port" name="imap_port" value="' . (int)$imapPort . '">
        </div>
        
        <div class="form-group">
            <label for="imap_username">IMAP Username</label>
            <input type="text" class="form-control" id="imap_username" name="imap_username" value="' . rex_escape($imapUsername) . '">
        </div>
        
        <div class="form-group">
            <label for="imap_password">IMAP Password</label>
            <input type="password" class="form-control" id="imap_password" name="imap_password" value="' . rex_escape($imapPassword) . '">
        </div>
        
        <div class="form-group">
            <label for="imap_folder">IMAP Folder</label>
            <div class="input-group">
                <input type="text" class="form-control" id="imap_folder" name="imap_folder" value="' . rex_escape($imapFolder) . '">
                <span class="input-group-btn">
                    <button class="btn btn-default" type="button" id="test-imap-connection">
                        <i class="fa fa-refresh"></i> Test & Load Folders
                    </button>
                    <button class="btn btn-info" type="button" id="debug-imap-emails">
                        <i class="fa fa-bug"></i> Debug: Show last 5 Emails
                    </button>
                </span>
            </div>
            <p class="help-block">Folder to check for bounces (e.g. INBOX or Bounces)</p>
            <div id="imap-test-result" style="margin-top: 10px;"></div>
            <div id="imap-debug-result" style="margin-top: 10px;"></div>
        </div>
        
        <div class="form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="imap_delete_bounces" value="1"' . ($imapDeleteBounces ? ' checked' : '') . '>
                    Delete processed bounce emails
                </label>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>GDPR / DSGVO</legend>
        
        <div class="form-group">
            <label for="gdpr_anonymize_days">Anonymize Logs after (days)</label>
            <input type="number" class="form-control" id="gdpr_anonymize_days" name="gdpr_anonymize_days" value="' . (int)$gdprAnonymizeDays . '">
            <p class="help-block">0 to disable auto-anonymization.</p>
        </div>
    </fieldset>

    <div class="form-group">
        <button type="submit" name="save_settings" value="1" class="btn btn-primary">
            <i class="rex-icon fa-save"></i> ' . $addon->i18n('settings_save') . '
        </button>
    </div>

</form>

<script>
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("test-imap-connection").addEventListener("click", function() {
        var btn = this;
        var resultDiv = document.getElementById("imap-test-result");
        var host = document.getElementById("imap_host").value;
        var user = document.getElementById("imap_username").value;
        var pass = document.getElementById("imap_password").value;
        var port = document.getElementById("imap_port").value;
        
        btn.disabled = true;
        btn.innerHTML = "<i class=\'fa fa-spinner fa-spin\'></i> Testing...";
        resultDiv.innerHTML = "";
        
        var url = "index.php?rex-api-call=mail_tools_imap_test&host=" + encodeURIComponent(host) + "&user=" + encodeURIComponent(user) + "&password=" + encodeURIComponent(pass) + "&port=" + encodeURIComponent(port);
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = "<i class=\'fa fa-refresh\'></i> Test & Load Folders";
                
                if (data.success) {
                    var html = "<div class=\'alert alert-success\'>Connection successful!</div>";
                    if (data.folders && data.folders.length > 0) {
                        html += "<label>Select Folder:</label><select class=\'form-control\' onchange=\'document.getElementById(\"imap_folder\").value = this.value\'>";
                        html += "<option value=\'\'>-- Select Folder --</option>";
                        data.folders.forEach(function(folder) {
                            html += "<option value=\'" + folder + "\'>" + folder + "</option>";
                        });
                        html += "</select>";
                    }
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = "<div class=\'alert alert-danger\'>Connection failed: " + data.message + "</div>";
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = "<i class=\'fa fa-refresh\'></i> Test & Load Folders";
                resultDiv.innerHTML = "<div class=\'alert alert-danger\'>Error: " + error + "</div>";
            });
    });

    document.getElementById("debug-imap-emails").addEventListener("click", function() {
        var btn = this;
        var resultDiv = document.getElementById("imap-debug-result");
        var host = document.getElementById("imap_host").value;
        var user = document.getElementById("imap_username").value;
        var pass = document.getElementById("imap_password").value;
        var port = document.getElementById("imap_port").value;
        var folder = document.getElementById("imap_folder").value;
        
        btn.disabled = true;
        btn.innerHTML = "<i class=\'fa fa-spinner fa-spin\'></i> Loading...";
        resultDiv.innerHTML = "";
        
        var url = "index.php?rex-api-call=mail_tools_imap_test&debug=1&host=" + encodeURIComponent(host) + "&user=" + encodeURIComponent(user) + "&password=" + encodeURIComponent(pass) + "&port=" + encodeURIComponent(port) + "&folder=" + encodeURIComponent(folder);
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = "<i class=\'fa fa-bug\'></i> Debug: Show last 5 Emails";
                
                if (data.error) {
                    resultDiv.innerHTML = "<div class=\'alert alert-danger\'>Error: " + data.error + "</div>";
                } else {
                    var html = "<div class=\'alert alert-info\'>Found " + data.count + " emails in folder \'" + folder + "\'. Showing last 5:</div>";
                    if (data.emails && data.emails.length > 0) {
                        html += "<table class=\'table table-striped table-hover\'><thead><tr><th>ID</th><th>Date</th><th>From</th><th>Subject</th><th>Flags</th><th>Action</th></tr></thead><tbody>";
                        data.emails.forEach(function(email) {
                            html += "<tr>";
                            html += "<td>" + email.id + "</td>";
                            html += "<td>" + email.date + "</td>";
                            html += "<td>" + email.from + "</td>";
                            html += "<td>" + email.subject + "</td>";
                            html += "<td><span class=\'label label-default\'>" + email.flags.seen + "</span> <span class=\'label label-default\'>" + email.flags.flagged + "</span></td>";
                            html += "<td><button type=\'button\' class=\'btn btn-xs btn-warning analyze-bounce\' data-id=\'" + email.id + "\'>Analyze</button></td>";
                            html += "</tr>";
                        });
                        html += "</tbody></table>";
                    } else {
                        html += "<p>No emails found.</p>";
                    }
                    resultDiv.innerHTML = html;
                    
                    // Attach events to new buttons
                    document.querySelectorAll(".analyze-bounce").forEach(function(btn) {
                        btn.addEventListener("click", function() {
                            analyzeBounce(this.getAttribute("data-id"));
                        });
                    });
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = "<i class=\'fa fa-bug\'></i> Debug: Show last 5 Emails";
                resultDiv.innerHTML = "<div class=\'alert alert-danger\'>Error: " + error + "</div>";
            });
    });
    
    function analyzeBounce(msgId) {
        var resultDiv = document.getElementById("imap-debug-result");
        var host = document.getElementById("imap_host").value;
        var user = document.getElementById("imap_username").value;
        var pass = document.getElementById("imap_password").value;
        var port = document.getElementById("imap_port").value;
        var folder = document.getElementById("imap_folder").value;
        
        var url = "index.php?rex-api-call=mail_tools_imap_test&analyze=" + msgId + "&host=" + encodeURIComponent(host) + "&user=" + encodeURIComponent(user) + "&password=" + encodeURIComponent(pass) + "&port=" + encodeURIComponent(port) + "&folder=" + encodeURIComponent(folder);
        
        // Show loading indicator
        var loadingDiv = document.createElement("div");
        loadingDiv.innerHTML = "<div class=\'alert alert-warning\'><i class=\'fa fa-spinner fa-spin\'></i> Analyzing Msg #" + msgId + "...</div>";
        resultDiv.appendChild(loadingDiv);
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                loadingDiv.remove();
                var html = "<div class=\'panel panel-default\'><div class=\'panel-heading\'>Analysis Result for Msg #" + msgId + "</div><div class=\'panel-body\'>";
                
                if (data.error) {
                    html += "<div class=\'alert alert-danger\'>" + data.error + "</div>";
                } else {
                    if (data.extracted_email) {
                        html += "<div class=\'alert alert-success\'><strong>Success!</strong> Extracted Email: " + data.extracted_email + "</div>";
                    } else {
                        html += "<div class=\'alert alert-danger\'><strong>Failed!</strong> Could not extract email address.</div>";
                    }
                    html += "<h4>Body Preview (first 2000 chars):</h4><pre style=\'max-height: 300px; overflow: auto;\'>" + data.body_preview.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</pre>";
                }
                html += "</div></div>";
                resultDiv.innerHTML += html;
            })
            .catch(error => {
                loadingDiv.remove();
                resultDiv.innerHTML += "<div class=\'alert alert-danger\'>Error: " + error + "</div>";
            });
    }
});
</script>
';

// Status-Info
$statusContent = '<table class="table">
    <tr>
        <th>' . $addon->i18n('settings_status_validation') . '</th>
        <td>' . ($validateDomains ? '<span class="text-success"><i class="fa fa-check"></i> ' . $addon->i18n('settings_active') . '</span>' : '<span class="text-muted"><i class="fa fa-times"></i> ' . $addon->i18n('settings_inactive') . '</span>') . '</td>
    </tr>
    <tr>
        <th>' . $addon->i18n('settings_status_action') . '</th>
        <td>' . $addon->i18n('settings_action_' . str_replace('_', '_', $invalidAction)) . '</td>
    </tr>
</table>';

// Fragment für Status
$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', $addon->i18n('settings_status_title'), false);
$fragment->setVar('body', $statusContent, false);
echo $fragment->parse('core/page/section.php');

// Fragment für Formular
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('settings_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

