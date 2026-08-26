<?php
namespace yf\sw\handlers;

class PlayerContainerSlotHandler extends HandlerBase
{
	
	public $event = "player.container.slot";

	public function __invoke($d, $e)
	{
		$player = $d["player"];
		if(isset($this->pluginInst[$player->iusername])){
			
		}
		
	}

}

