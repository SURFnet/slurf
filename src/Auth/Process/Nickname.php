<?php

declare(strict_types=1);

namespace SimpleSAML\Module\slurf\Auth\Process;

use PDO;
use SimpleSAML\Auth;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Utils;

class Nickname extends Auth\ProcessingFilter
{
    private string $nicknameattribute;
    private string $tablename;

    public function __construct(array $config, $reserved)
    {
        parent::__construct($config, $reserved);

        $this->nicknameattribute = $config['targetattribute'];
        $this->tablename         = $config['usermapping'];
    }

    private function userExists(string $userid): ?string
    {
        $db = \SimpleSAML\Database::getInstance();
        Logger::info(sprintf("Looking up nicknames for: %s", $userid));
        $query = $db->read(
                    "SELECT * FROM " . $this->tablename . " WHERE saml_id = :userid",
                    ['userid' => $userid]
                );
        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_ASSOC);

        if(!empty($result)) {
            return $result[0]['nickname'];
        }
        return null;
    }

    public function process(array &$state): void
    {
        Logger::info(sprintf("Starting Nickname authproc filter, target = %s", $this->nicknameattribute));

        $nameId = $state['saml:sp:NameID']->getValue();

        Logger::info(sprintf("Received NameID: %s", $nameId));

        $nick = $this->userExists($nameId);
        if($nick !== null) {
            Logger::info(sprintf("Found nickname for user %s: %s", $nameId, $nick));

            $attributes = &$state['Attributes'];
            $attributes[$this->nicknameattribute] = [$nick];

            return;
        }
        $id = Auth\State::saveState($state, 'slurf:nicknamechooser');
        $url = Module::getModuleURL('slurf/nickname');
        $httpUtils = new Utils\HTTP();
        $httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
    }
}
