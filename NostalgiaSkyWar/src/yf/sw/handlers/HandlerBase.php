<?php
namespace yf\sw\handlers;

use Plugin;
use ServerAPI;

abstract class HandlerBase
{
	public $event = null;
	/**
	 * @var ServerAPI
	 */
	public $api;
	
	/**
	 * @var Plugin
	 */
	protected $pluginInst;
	public function __construct(Plugin $plugin, ServerAPI $api, $priority = 5){
		$api->addHandler($this->event, $this, $priority);
		$this->api = $api;
		$this->pluginInst = $plugin;
	}
	
	public abstract function __invoke($d, $e);	
}

