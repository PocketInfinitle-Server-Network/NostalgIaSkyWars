<?php
namespace yf\sw\handlers;

class PacketListener extends EventListener
{
	
	public function __invoke(\DataPacketReceiveEvent $e){
		$player = $e->getPlayer();
		$pk = $e->getPacket();
		if($pk->pid() === \ProtocolInfo::CONTAINER_CLOSE_PACKET && isset($this->plugin->playerTiles[$player->iusername])){
			if(!is_array($player->windows[$pk->windowid]) && $player->windows[$pk->windowid]->class === TILE_CHEST){
				/**
				 * @var $tile \Tile
				 */
				$tile = $player->windows[$pk->windowid];
				$this->plugin->calcuateItemsValue($player, $tile);
				unset($this->plugin->playerTiles[$player->iusername]); //TODO closeChest
				$idmeta = $player->level->level->getBlock($tile->x, $tile->y, $tile->z);
				$pkk = new \UpdateBlockPacket();
				$pkk->x = (int) $tile->x;
				$pkk->y = (int) $tile->y;
				$pkk->z = (int) $tile->z;
				$pkk->block = $idmeta[0];
				$pkk->meta = $idmeta[1];
				$player->dataPacket($pkk);
			}
		}
	}
}

