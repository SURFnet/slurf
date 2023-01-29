<?php

declare(strict_types=1);

namespace SimpleSAML\Module\slurf\Auth\Process;

use PDO;
use SimpleSAML\Auth;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\slurf\Slurf;
use SimpleSAML\Utils;

class Nickname extends Auth\ProcessingFilter
{
    public function __construct(array $config, $reserved)
    {
        parent::__construct($config, $reserved);
    }

    private function userExists(string $userid): ?string
    {
        $db = \SimpleSAML\Database::getInstance();
        Logger::info(sprintf("Looking up nicknames for: %s", $userid));
        $query = $db->read(
                    "SELECT nickname FROM " . Slurf::DB_TABLE . " WHERE idtype='person' AND saml_id = :userid",
                    ['userid' => $userid]
                );
        $query->execute();
        $result = $query->fetchColumn();

        return $result === false ? null : $result;
    }

    private function userGroupsExist(array $groups): array
    {
        if(empty($groups)) return [];
        $db = \SimpleSAML\Database::getInstance();
        Logger::info(sprintf("Looking up group nicknames for: [%s]", implode(',', $groups)));

        // SSP DB only supports named params, so we have to juggle a bit to provide those in a variable number
        $placeholders = ':grp0';
        $values = ['grp0' => array_shift($groups)];
        $i = 0;
        foreach($groups as $value) {
            ++$i;
            $placeholders .= ',:grp'.$i;
            $values['grp'.$i] = $value;
        }

        $query = $db->read(
                    "SELECT nickname FROM " . Slurf::DB_TABLE . " WHERE idtype='group' AND saml_id IN ($placeholders)",
                    $values
                );
        $query->execute();
        return $query->fetchAll(PDO::FETCH_COLUMN);
    }

    public function process(array &$state): void
    {
        Logger::info(sprintf("Starting Nickname authproc filter, target = %s", $this->nicknameattribute));

        $attributes = &$state['Attributes'];
        $nameId = $state['saml:sp:NameID']->getValue();
        $groups = $attributes[Slurf::USER_GROUPS_ATTRIBUTE] ?? [];

        Logger::info(sprintf("Received NameID: %s, groups: [%s]", $nameId, implode(',', $groups)));

        $allnicks = [];

        $nick = $this->userExists($nameId);
        if($nick !== null) {
            Logger::info(sprintf("Found nickname for user %s: %s", $nameId, $nick));
        }
        $state['slurf_personalnick'] = $nick;

        $groupnicks = $this->userGroupsExist($groups);
        if(!empty($groupnicks)) {
            Logger::info(sprintf("Found group nicknames for user %s: [%s]", $nameId, implode(',', $groupnicks)));
        }
        $state['slurf_groupnicks'] = $groupnicks;

        // No group accounts found
        if(empty($groupnicks)) {
            // Only a personal nick found? Continue to Mastodon immediately
            if($nick !== null) {
                $attributes[Slurf::TARGET_ATTRIBUTE] = [$nick];
                return;
            }

            // No personal nick, no group accounts: continue to nickname picker
            $target = 'slurf/nickname';
        } else {
            // If user has group accounts, always continue to account chooser
            $target = 'slurf/chooser';
        }

        $id = Auth\State::saveState($state, 'slurf:nickname');
        $url = Module::getModuleURL($target);
        $httpUtils = new Utils\HTTP();
        $httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
    }
}
