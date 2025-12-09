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
    
    echo rex_view::success($addon->i18n('settings_saved'));
}

// Aktuelle Werte
$validateDomains = $addon->getConfig('validate_domains', true);
$invalidAction = $addon->getConfig('invalid_domain_action', 'block_invalid');
$blockedTlds = $addon->getConfig('blocked_tlds', '');
$checkMx = $addon->getConfig('check_mx', false);
$checkDisposable = $addon->getConfig('check_disposable', false);
$checkTypos = $addon->getConfig('check_typos', false);

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

    <div class="form-group">
        <button type="submit" name="save_settings" value="1" class="btn btn-primary">
            <i class="rex-icon fa-save"></i> ' . $addon->i18n('settings_save') . '
        </button>
    </div>

</form>';

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

