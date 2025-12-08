<?php

/**
 * YForm Validator: E-Mail Domain Check.
 *
 * Prüft ob die Domain einer E-Mail-Adresse existiert und einen Mailserver hat.
 *
 * Verwendung in YForm:
 * validate|email_domain|email_field|Fehlermeldung|[require_mx]
 */
class rex_yform_validate_email_domain extends rex_yform_validate_abstract
{
    public function enterObject()
    {
        $object = $this->getValueObject();

        if (!$this->isObject($object)) {
            return;
        }

        $value = $object->getValue();

        // Leere Werte überspringen (dafür gibt es empty-Validator)
        if ('' === $value || null === $value) {
            return;
        }

        // Basis-Syntax-Check
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->params['warning'][$object->getId()] = $this->params['error_class'];
            $this->params['warning_messages'][$object->getId()] = $this->getElement('message');
            return;
        }

        // Domain extrahieren
        $domain = \FriendsOfRedaxo\MailTools\DomainValidator::extractDomain($value);

        if ('' === $domain) {
            $this->params['warning'][$object->getId()] = $this->params['error_class'];
            $this->params['warning_messages'][$object->getId()] = $this->getElement('message');
            return;
        }

        // Domain-Existenz prüfen
        if (!\FriendsOfRedaxo\MailTools\DomainValidator::isDomainValid($domain)) {
            $this->params['warning'][$object->getId()] = $this->params['error_class'];
            $this->params['warning_messages'][$object->getId()] = $this->getElement('message');
            return;
        }

        // Optional: MX-Record prüfen
        $requireMx = (bool) $this->getElement('require_mx');
        if ($requireMx && !\FriendsOfRedaxo\MailTools\DomainValidator::hasMxRecord($domain)) {
            $this->params['warning'][$object->getId()] = $this->params['error_class'];
            $this->params['warning_messages'][$object->getId()] = $this->getElement('message');
        }
    }

    public function getDescription(): string
    {
        return 'validate|email_domain|label|Fehlermeldung|[require_mx=0]';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            'type' => 'validate',
            'name' => 'email_domain',
            'values' => [
                'name' => ['type' => 'select_name', 'label' => rex_i18n::msg('yform_validate_email_domain_name')],
                'message' => ['type' => 'text', 'label' => rex_i18n::msg('yform_validate_email_domain_message')],
                'require_mx' => ['type' => 'checkbox', 'label' => rex_i18n::msg('yform_validate_email_domain_require_mx')],
            ],
            'description' => rex_i18n::msg('yform_validate_email_domain_description'),
            'dbtype' => '',
            'famous' => false,
        ];
    } 
}
