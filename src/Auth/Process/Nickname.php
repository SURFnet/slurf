<?php

declare(strict_types=1);

namespace SimpleSAML\Module\slurf\Auth\Process;

use SimpleSAML\Auth;
use SimpleSAML\Logger;

class Nickname extends Auth\ProcessingFilter
{
    private string $nicknameattribute;

    public function __construct(array $config, $reserved)
    {
        parent::__construct($config, $reserved);

	$this->nicknameattribute = $config['targetattribute'];
    }

    public function process(array &$state): void
    {
        Logger::info(sprintf("Starting Nickname authproc filter, target = %s", $this->nicknameattribute));

	$nameId = $state['saml:sp:NameID']->getValue();

	Logger::info(sprintf("Received NameID: %s", $nameId));
	
	$attributes = &$state['Attributes'];
	$attributes[$this->nicknameattribute] = [$nameId];
    }

}
