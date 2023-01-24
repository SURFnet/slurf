<?php

declare(strict_types=1);

namespace SimpleSAML\Module\slurf;

class Slurf
{
    public const TARGET_ATTRIBUTE = 'urn:mace:dir:attribute-def:eduPersonNickname';
    public const USER_ORG_ATTRIBUTE = 'urn:mace:terena.org:attribute-def:schacHomeOrganization';
    public const USER_GROUPS_ATTRIBUTE = 'urn:mace:dir:attribute-def:isMemberOf';
    public const DB_TABLE = 'saml2nick';
}
