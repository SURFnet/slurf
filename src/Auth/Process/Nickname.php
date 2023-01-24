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
        $values = ['grp0' => array_unshift($groups)];
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
            $allnicks[] = $nick;
        }

        $groupnicks = $this->userGroupsExist($groups);
        if(!empty($groupnicks)) {
            Logger::info(sprintf("Found group nicknames for user %s: [%s]", $nameId, implode(',', $groupnicks)));
            $allnicks = array_merge($allnicks, $groupnicks);
            // Unset all other attributes (user's personal info) in case of group nick
            $attributes = [];
        }

        // Single nick found? Continue to Mastodon immediately
        if(count($allnicks) === 1) {
            $attributes[Slurf::TARGET_ATTRIBUTE] = $allnicks;
            return;
        }

        // More than one nick: send user to account choosser, otherwise go to signup flow.
        if(count($allnicks) > 1) {
            $target = 'slurf/chooser';
            $state['slurf_nickchoices'] = $allnicks;
            $id = Auth\State::saveState($state, 'slurf:accountchooser');
        } else {
            $target = 'slurf/nickname';
            $id = Auth\State::saveState($state, 'slurf:nicknamepicker');
        }

        $url = Module::getModuleURL($target);
        $httpUtils = new Utils\HTTP();
        $httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
    }
}
