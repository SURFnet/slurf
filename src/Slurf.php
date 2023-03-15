<?php

declare(strict_types=1);

namespace SimpleSAML\Module\slurf;

class Slurf
{
    public const TARGET_ATTRIBUTE = 'urn:mace:dir:attribute-def:eduPersonNickname';
    public const USER_ORG_ATTRIBUTE = 'urn:mace:terena.org:attribute-def:schacHomeOrganization';
    public const USER_GROUPS_ATTRIBUTE = 'urn:mace:dir:attribute-def:isMemberOf';
    public const USER_DISPLAYNAME_ATTRIBUTE = 'urn:mace:dir:attribute-def:displayName';
    public const USER_MAIL_ATTRIBUTE = 'urn:mace:dir:attribute-def:mail';
    public const DB_TABLE = 'saml2nick';
    public const ATTRIBUTES_MAXLENGTH = 30;

    public static function cleanupAttributes(array &$attr): void
    {
        $displayName = $attr[self::USER_DISPLAYNAME_ATTRIBUTE][0] ?? '';
        $mail = $attr[Slurf::USER_MAIL_ATTRIBUTE][0] ?? '';

        // Mastodon does not accept values for displayname or mail > 30 characters
	// (will crash on receiving them). But 'randomly' shortening them makes no
	// sense.

	// DisplayName: if too long, set to nickname
        if(strlen($displayName) > self::ATTRIBUTES_MAXLENGTH) {
            $attr[self::USER_DISPLAYNAME_ATTRIBUTE] = $attr[self::TARGET_ATTRIBUTE];
        }
	// Mail: if too long, do not send
        if(strlen($mail) > self::ATTRIBUTES_MAXLENGTH) {
            unset($attr[self::USER_MAIL_ATTRIBUTE]);
        }
    }
}
