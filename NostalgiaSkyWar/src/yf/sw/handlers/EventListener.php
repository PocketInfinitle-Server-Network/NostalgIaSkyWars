<?php
namespace yf\sw\handlers;

use Plugin;
use ServerAPI;

abstract class EventListener
{
	/**
	 * @var Plugin
	 */
	public $plugin;
	/**
	 * @var ServerAPI
	 */
	public $api;
	public function __construct(Plugin $pluginInst, ServerAPI $api){
		$this->api = $api;
		$this->plugin = $pluginInst;
	}
	
	public function __toString(){
		return spl_object_id($this);
	}
}