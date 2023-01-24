<?php

declare(strict_types=1);

namespace SimpleSAML\Module\slurf\Controller;

use PDO;
use SimpleSAML\Auth;
use SimpleSAML\Error;
use SimpleSAML\Configuration;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\slurf\Slurf;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class NicknameChooser
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Session */
    protected Session $session;
    
    /**
     * @var \SimpleSAML\Auth\State|string
     */
    protected $authState = Auth\State::class;

    public function __construct(
        Configuration $config,
        Session $session
    ) {
        $this->config = $config;
        $this->session = $session;
    }

    public function setAuthState(Auth\State $authState): void
    {
        $this->authState = $authState;
    }

    private function validNickname(string $nickname): bool
    {
        return preg_match('/^[a-z0-9_]{2,30}$/i', $nickname) === 1;
    }

    private function nickExists(string $nickname): bool
    {
        $db = \SimpleSAML\Database::getInstance();
        Logger::info(sprintf("Checking for desired nickname existance: %s", $nickname));
	// todo: is this SST or do we also need to query other sources/mastodon
        $query = $db->read(
                    "SELECT count(*) AS cnt FROM " . Slurf::DB_TABLE . " WHERE lower(nickname) = lower(:nickname)",
                    ['nickname' => $nickname]
                );
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        Logger::info(sprintf("Found %s matches for nickname %s", $result['cnt'], $nickname));
        return $result['cnt'] !== 0;
    }

    private function registerNick(string $nickname, string $nameId, string $homeOrg): void
    {
        $db = \SimpleSAML\Database::getInstance();
        Logger::info(sprintf("Registering user %s from %s: nickname %s", $nameId, $homeOrg, $nickname));
        $db->write(
            "INSERT INTO " . Slurf::DB_TABLE . " (nickname, saml_id, homeorg) " .
	    " VALUES (:nickname, :nameId, :homeOrg)",
            [
		    'nickname' => $nickname,
		    'nameId' => $nameId,
		    'homeOrg' => $homeOrg,
	    ]
                );
    }

    public function main(Request $request): Response
    {
        Logger::info('Nickname chooser - showing form to user');

        $id = $request->get('StateId', null);
        if ($id === null) {
            throw new Error\BadRequest('Missing required StateId query parameter.');
        }
        $state = $this->authState::loadState($id, 'slurf:nicknamechooser');

        if (is_null($state)) {
            throw new Error\NoState();
        } elseif ($request->get('proceed', null) !== null) {
            $desiredNick = $request->get('nickname');

            if(!$this->validNickname($desiredNick)) {
                Logger::info('Nickname chooser - invalid nickname entered');
                $nicknameInvalid = true;
            } else {

                $nickExists = $this->nickExists($desiredNick);

                if ($nickExists === false) {
                    $nameId = $state['saml:sp:NameID']->getValue();
                    $homeOrg = $state['Attributes'][Slurf::USER_ORG_ATTRIBUTE][0];
                    $this->registerNick($desiredNick, $nameId, $homeOrg);

                    Logger::info('Nickname chooser - new nickname registered, continue');
                    $state['Attributes'][Slurf::TARGET_ATTRIBUTE] = [$desiredNick];

		    Auth\ProcessingChain::resumeProcessing($state);
                }

                Logger::info('Nickname chooser - nickname already exists');
            }
        }

        $t = new Template($this->config, 'slurf:nicknamechooser.twig');
        $t->data['target'] = Module::getModuleURL('slurf/nickname');
        $t->data['data'] = ['StateId' => $id];
        $t->data['desiredNick'] = $desiredNick ?? '';
        $t->data['nickExists'] = $nickExists ?? null;
        $t->data['invalidNickname'] = $nicknameInvalid ?? false;
        return $t;
    }


}
