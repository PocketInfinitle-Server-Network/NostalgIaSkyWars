<?php
namespace yf\sw\commands;

use Plugin;
use ServerAPI;

abstract class CommandBase
{
	public $command = null;
	/**
	 * @var ServerAPI
	 */
	public $api;
	
	/**
	 * @var \Plugin
	 */
	protected $pluginInst;
	public function __construct(Plugin $plugin, ServerAPI $api, $help, $onlyOp = true, $aliases = []){
		$api->console->register($this->command, $help, $this);
		if(!$onlyOp) $api->console->cmdWhitelist($this->command);
		foreach($aliases as $a){
			$api->console->alias($a, $this->command);
		}
		$this->api = $api;
		$this->pluginInst = $plugin;
	}
	
	abstract function __invoke($cmd, $args, $sender, $alias);
}

