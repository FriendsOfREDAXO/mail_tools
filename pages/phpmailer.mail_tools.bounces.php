<?php

/**
 * Mail Tools - Bounces.
 *
 * @var rex_addon $this
 */

$func = rex_request('func', 'string');
$id = rex_request('id', 'int');

if ($func == 'delete' && $id > 0) {
    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('mail_tools_bounces'));
    $sql->setWhere(['id' => $id]);
    $sql->delete();
    echo rex_view::success($this->i18n('bounce_deleted'));
}

$list = rex_list::factory('SELECT * FROM ' . rex::getTable('mail_tools_bounces') . ' ORDER BY updated_at DESC');
$list->addTableAttribute('class', 'table-striped');

$list->removeColumn('id');
$list->removeColumn('bounce_message'); 

$list->setColumnLabel('email', $this->i18n('bounce_email'));
$list->setColumnLabel('bounce_type', $this->i18n('bounce_type'));
$list->setColumnLabel('created_at', $this->i18n('bounce_created'));
$list->setColumnLabel('updated_at', $this->i18n('bounce_updated'));
$list->setColumnLabel('count', $this->i18n('bounce_count'));

$list->addColumn($this->i18n('function'), $this->i18n('delete'));
$list->setColumnLayout($this->i18n('function'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
$list->setColumnParams($this->i18n('function'), ['func' => 'delete', 'id' => '###id###']);
$list->addLinkAttribute($this->i18n('function'), 'onclick', 'return confirm(\''.$this->i18n('delete').' ?\')');

$content = $list->get();

$fragment = new rex_fragment();
$fragment->setVar('title', $this->i18n('bounces_title'));
$fragment->setVar('content', $content, false);
echo $fragment->parse('core/page/section.php');
