<?php

/*
 *                _   _
 *  ___  __   __ (_) | |   ___
 * / __| \ \ / / | | | |  / _ \
 * \__ \  \ / /  | | | | |  __/
 * |___/   \_/   |_| |_|  \___|
 *
 * SkyWars plugin for PocketMine-MP & forks
 *
 * @Author: svile
 * @Kik: _svile_
 * @Telegram_Gruop: https://telegram.me/svile
 * @E-mail: thesville@gmail.com
 * @Github: https://github.com/svilex/SkyWars-PocketMine
 *
 * Copyright (C) 2016 svile
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * DONORS LIST :
 * - Ahmet
 * - Jinsong Liu
 * - no one
 *
 */

namespace yf\sw;


use Player;

use Block;
use BlockAPI;
use Position;

use Config;
use TextFormat;

use Tile;
use Item;

use AdventureSettingsPacket;

final class SWarena
{
    /** @var int */
    public $GAME_STATE = 0;//0 -> GAME_COUNTDOWN | 1 -> GAME_RUNNING | 2 -> no-pvp
    /** @var SWmain */
    private $pg;

    /** @var string */
    private $SWname;
    /** @var int */
    private $slot;
    /** @var string */
    private $world;
    /** @var int */
    private $countdown = 60;//Seconds to wait before the game starts
    /** @var int */
    private $maxtime = 300;//Max seconds after the countdown, if go over this, the game will finish
    /** @var int */
    public $void = 0;//This is used to check "fake void" to avoid fall (stunck in air) bug
    /** @var array */
    private $spawns = [];//Players spawns

    /** @var int */
    private $time = 0;//Seconds from the last reload | GAME_STATE
    /** @var array */
    private $players = [];
    /** @var array */
    private $spectators = [];


    /**
     * @param SWmain $plugin
     * @param string $SWname
     * @param int $slot
     * @param string $world
     * @param int $countdown
     * @param int $maxtime
     * @param int $void
     */
    public function __construct(SWmain $plugin, $SWname = 'sw', $slot = 0, $world = 'world', $countdown = 60, $maxtime = 300, $void = 0)
    {
        $this->pg = $plugin;
        $this->SWname = $SWname;
        $this->slot = ($slot + 0);
        $this->world = $world;
        $this->countdown = ($countdown + 0);
        $this->maxtime = ($maxtime + 0);
        $this->void = $void;
        if (!$this->reload()) {
			\ConsoleAPI::error(FORMAT_RED . '在运行时产生了一个错误: ' . FORMAT_WHITE . $this->SWname);
			$this->pg->api->console->defaultCommands("stop", "", "plugin", false);
        }
    }


    /**
     * @return bool
     */
    private function reload()
    {
        //Map reset
        if (!is_file($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar') && !is_file($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz'))
            return false;
        if ($this->pg->api->level->get($this->world)) {
            if ($this->pg->configs['world.reset.from.tar']) {
				$this->pg->api->level->unloadLevel($this->pg->api->level->get($this->world));
                if (is_file($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar'))
                    $tar = new \PharData($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar');
                elseif (is_file($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz'))
                    $tar = new \PharData($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz');
                else
                    return false;//WILL NEVER REACH THIS
                $tar->extractTo(DATA_PATH . 'worlds/' . $this->world, null, true);
                unset($tar);
				$this->pg->api->level->loadLevel($this->world);
            }
			$this->pg->api->level->unloadLevel($this->pg->api->level->get($this->world));
			$this->pg->api->level->loadLevel($this->world);
        } else {
            if (is_file($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar'))
                $tar = new \PharData($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar');
            elseif (is_file($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz'))
                $tar = new \PharData($this->pg->dataPath . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz');
            else
                return false;//WILL NEVER REACH THIS
            $tar->extractTo(DATA_PATH . 'worlds/' . $this->world, null, true);
            unset($tar);
			$this->pg->api->level->loadLevel($this->world);
        }

        $config = new Config($this->pg->dataPath . 'arenas/' . $this->SWname . '/settings.yml', CONFIG_YAML, [//TODO: put descriptions
            'name' => $this->SWname,
            'slot' => $this->slot,
            'world' => $this->world,
            'countdown' => $this->countdown,
            'maxGameTime' => $this->maxtime,
            'void_Y' => $this->void,
            'spawns' => []
        ]);
        $this->SWname = $config->get('name');
        $this->slot = ($config->get('slot') + 0);
        $this->world = $config->get('world');
        $this->countdown = ($config->get('countdown') + 0);
        $this->maxtime = ($config->get('maxGameTime') + 0);
        $this->spawns = $config->get('spawns');
        $this->void = ($config->get('void_Y') + 0);
        unset($config);
        $this->players = [];
        $this->spectators = [];
        $this->time = 0;
        $this->GAME_STATE = 0;

        //Reset Sign
        $this->pg->refreshSigns(false, $this->SWname, 0, $this->slot);
        return true;
    }


    /**
     * @return string
     */
    public function getState()
    {
        $state = 'Tap to Join';
        switch ($this->GAME_STATE) {
            case 1:
            case 2:
                $state = 'Ongoing';
                break;
            case 0:
                if (count($this->players) >= $this->slot)
                    $state = 'Starting';
                break;
        }
        return $state;
    }


    /**
     * @param bool $players
     * @return int
     */
    public function getSlot($players = false)
    {
        if ($players)
            return count($this->players);
        return $this->slot;
    }


    /**
     * @param bool $spawn
     * @param string $playerName
     * @return string|array
     */
    public function getWorld($spawn = false, $playerName = '')
    {
        if ($spawn && array_key_exists($playerName, $this->players))
            return $this->players[$playerName];
        else
            return $this->world;
    }


    /**
     * @param string $playerName
     * @return int
     */
    public function inArena($playerName = '')
    {
        if (array_key_exists($playerName, $this->players))
            return 1;
        if (in_array($playerName, $this->spectators))
            return 2;
        return 0;
    }


    /**
     * @param Player $player
     * @param int $slot
     * @return bool
     */
    public function setSpawn(Player $player, $slot = 1)
    {
        if ($slot > $this->slot) {
            $player->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '已经设置过这片区域了 ' . FORMAT_WHITE . $this->slot . FORMAT_RED . ' 插槽');
            return false;
        }
        $config = new Config($this->pg->dataPath . 'arenas/' . $this->SWname . '/settings.yml', CONFIG_YAML);

        if (empty($config->get('spawns', []))) {
            $keys = [];
            for ($i = $this->slot; $i >= 1; $i--) {
                $keys[] = $i;
            }
            unset($i);
            $config->set('spawns', array_fill_keys(array_reverse($keys), [
                'x' => 'n.a',
                'y' => 'n.a',
                'z' => 'n.a',
                'yaw' => 'n.a',
                'pitch' => 'n.a'
            ]));
            unset($keys);
        }
        $s = $config->get('spawns');
        $s[$slot] = [
            'x' => floor($player->entity->x),
            'y' => floor($player->entity->y),
            'z' => floor($player->entity->z),
            'yaw' => $player->entity->yaw,
            'pitch' => $player->entity->pitch
        ];
        $config->set('spawns', $s);
        $this->spawns = $s;
        unset($s);
        if (!$config->save() || count($this->spawns) != $this->slot) {
            $player->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '设置时出现错误，请与开发人员联系');
            return false;
        } else
            return true;
    }


    /**
     * @return bool
     */
    public function checkSpawns()
    {
        if (empty($this->spawns))
            return false;
        foreach ($this->spawns as $key => $val) {
            if (!is_array($val) || count($val) != 5 || $this->slot != count($this->spawns) || in_array('n.a', $val, true))
                return false;
        }
        return true;
    }


    /** VOID */
    private function refillChests()
    {
        $contents = $this->pg->getChestContents();
        foreach ($this->pg->api->tile->getAll($this->world) as $tile) {
			if ($tile instanceof Tile && $tile->class === TILE_CHEST) {
                //CLEARS CHESTS
                for ($i = 0; $i < CHEST_SLOTS; $i++) {
					$tile->setSlot($i, BlockAPI::getItem(AIR));
                }
                //SET CONTENTS
                if (empty($contents))
                    $contents = $this->pg->getChestContents();
                foreach (array_shift($contents) as $key => $val) {
					$tile->setSlot($key, BlockAPI::getItem($val[0], 0, $val[1]));
                }
            }
        }
        unset($contents, $tile);
    }


    /** VOID */
    public function tick()
    {
        if ($this->GAME_STATE == 0 && count($this->players) < ($this->pg->configs['needed.players.to.run.countdown'] + 0))
            return;
        $this->time++;

        //START and STOP
        if ($this->GAME_STATE == 0 && $this->pg->configs['start.when.full'] && $this->slot <= count($this->players)) {
            $this->start();
            return;
        }
        if ($this->GAME_STATE > 0 && 2 > count($this->players)) {
            $this->stop();
            return;
        }
        if ($this->GAME_STATE == 0 && $this->time >= $this->countdown) {
            $this->start();
            return;
        }
        if ($this->GAME_STATE > 0 && $this->time >= $this->maxtime) {
            $this->stop();
            return;
        }

        //Chest refill
        if ($this->GAME_STATE > 0 && $this->pg->configs['chest.refill'] && ($this->time % $this->pg->configs['chest.refill.rate']) == 0) {
            $this->refillChests();
            foreach ($this->pg->api->level->get($this->world)->players as $p) {
                $p->sendChat($this->pg->lang['game.chest.refill']);
            }
            return;
        }

        //PvP - updates
        if ($this->GAME_STATE == 2) {
            if ($this->time <= $this->pg->configs['no.pvp.countdown'])
                foreach ($this->pg->api->level->get($this->world)->players as $p)
                    $p->sendChat(str_replace('{COUNT}', $this->pg->configs['no.pvp.countdown'] - $this->time + 1, $this->pg->lang['no.pvp.countdown']));
            else
                $this->GAME_STATE = 1;
            return;
        }

        //Chat and Popup messanges
        if ($this->GAME_STATE == 0 && $this->time % 30 == 0) {
            foreach ($this->pg->api->level->get($this->world)->players as $p) {
                $p->sendChat(str_replace('{N}', date('i:s', ($this->countdown - $this->time)), $this->pg->lang['chat.countdown']));
            }
        }
        if ($this->GAME_STATE == 0) {
            foreach ($this->pg->api->level->get($this->world)->players as $p) {
                $p->sendChat(str_replace('{N}', date('i:s', ($this->countdown - $this->time)), $this->pg->lang['popup.countdown']));
                if (($this->countdown - $this->time) <= 10){}
                    //$p->getLevel()->addSound((new \pocketmine\level\sound\ButtonClickSound($p)), [$p]);
					//TODO::Support simplesound
            }
        }
    }


    /**
     * @param Player $player
     * @param bool $msg
     * @return bool
     */
    public function join(Player $player, $msg = true)
    {
        if ($this->GAME_STATE > 0) {
            if ($msg)
                $player->sendChat($this->pg->lang['sign.game.running']);
            return false;
        }
        if (count($this->players) >= $this->slot || empty($this->spawns)) {
            if ($msg)
                $player->sendChat($this->pg->lang['sign.game.full']);
            return false;
        }
        //Sound
        //$player->getLevel()->addSound((new \pocketmine\level\sound\EndermanTeleportSound($player)), [$player]);
		//TODO Support simplesound

        //Removes player things
        $player->setGamemode(SURVIVAL);
        if ($this->pg->configs['clear.inventory.on.arena.join']){
			$air = BlockAPI::getItem(AIR, 0, 0);
			foreach ($player->inventory as $s => $item) {
				if ($item->getID() !== AIR) {
					$player->inventory[$s] = $air;
				}
			}
			$player->armor = [$air, $air, $air, $air];
			$player->sendInventory();
			$player->sendArmor($player);
		}
		$player->entity->setHealth($this->pg->configs['join.max.health']);
		$this->pg->api->level->loadLevel($this->world);
        $level = $this->pg->api->level->get($this->world);
        $tmp = array_shift($this->spawns);
        $player->teleport(new Position($tmp['x'] + 0.5, $tmp['y'], $tmp['z'] + 0.5, $level), $tmp['yaw'], $tmp['pitch']);
        $this->players[$player->iusername] = $tmp;
        foreach ($level->players as $p) {
            $p->sendChat(str_replace('{COUNT}', '[' . $this->getSlot(true) . '/' . $this->slot . ']', str_replace('{PLAYER}', $player->username, $this->pg->lang['game.join'])));
        }
        $this->pg->refreshSigns(false, $this->SWname, $this->getSlot(true), $this->slot, $this->getState());
        return true;
    }


    /**
     * @param string $playerName
     * @param bool $left
     * @param bool $spectate
     * @return bool
     */
    private function quit($playerName, $left = false, $spectate = false)
    {
        if (in_array($playerName, $this->spectators)) {
            unset($this->spectators[array_search($playerName, $this->spectators)]);
            foreach ($this->players as $name => $spawn) {
                if ((($p = $this->pg->api->player->get($name)) instanceof Player) && (($s = $this->pg->api->player->get($playerName)) instanceof Player))
                    $s->setInvisibleFor($p, false);
            }
			if(($s = $this->pg->api->player->get($playerName)) instanceof Player)
				$this->sendSettings($s, true, false);
            return true;
        }
        if (!array_key_exists($playerName, $this->players))
            return false;
        if ($this->GAME_STATE == 0)
            $this->spawns[] = $this->players[$playerName];
        unset($this->players[$playerName]);
        $this->pg->refreshSigns(false, $this->SWname, $this->getSlot(true), $this->slot, $this->getState());
        if ($left)
            foreach ($this->pg->api->level->get($this->world)->players as $p)
                $p->sendChat(str_replace('{COUNT}', '[' . $this->getSlot(true) . '/' . $this->slot . ']', str_replace('{PLAYER}', $playerName, $this->pg->lang['game.left'])));
        if ($spectate && !in_array($playerName, $this->spectators))
            $this->spectators[] = $playerName;
        foreach ($this->spectators as $sp) {
            if ((($p = $this->pg->api->player->get($playerName)) instanceof Player) && (($s = $this->pg->api->player->get($sp)) instanceof Player))
				$s->setInvisibleFor($p, false);
        }
		if(($s = $this->pg->api->player->get($playerName)) instanceof Player)
			$this->sendSettings($s, true, true);
        return true;
    }


    /**
     * @param Player $p
     * @param bool $left
     * @param bool $spectate
     * @return bool
     */
    public function closePlayer(Player $p, $left = false, $spectate = false)
    {
        if ($this->quit($p->iusername, $left, $spectate)) {
            $p->gamemode = 4;//Just to make sure setGamemode() won't return false if the gm is the same
            $p->setGamemode($this->pg->api->getProperty("gamemode"));
			$air = BlockAPI::getItem(AIR, 0, 0);
			foreach ($p->inventory as $s => $item) {
				if ($item->getID() !== AIR) {
					$p->inventory[$s] = $air;
				}
			}
			$p->armor = [$air, $air, $air, $air];
			$p->sendInventory();
			$p->sendArmor($p);
			if($p->entity)
				if (!$p->entity->dead)
					$p->entity->setHealth(20);

            if (!$spectate) {
                //TODO: Invisibility issues for death players
                $p->teleport($this->pg->api->level->getDefault()->getSpawn());
            } elseif ($this->GAME_STATE > 0 && 1 < count($this->players)) {
				$idmeta = explode(':', $this->pg->configs['spectator.quit.item']);
				$p->addItem($idmeta[0], (int)$idmeta[1], 1, true, true, false);
				console("cnm");
				$p->entity->setHealth(20);
                $p->gamemode = SPECTATOR;
//                $p->spawnToAll();
//                $pk = new ContainerSetContentPacket();
//                $pk->windowid = ContainerSetContentPacket::SPECIAL_CREATIVE;
//                $p->dataPacket($pk);
                foreach ($this->players as $dname => $spawn) {
                    if (($d = $this->pg->api->player->get($dname)) instanceof Player)
						$p->setInvisibleFor($d, true);
                }
                $p->sendChat($this->pg->lang['death.spectator']);
            }
            return true;
        }
        return false;
    }


    /** VOID */
    private function start()
    {
        if ($this->pg->configs['chest.refill'])
			$this->refillChests();
		$this->refillChests();
		console("nnd");
        foreach ($this->players as $name => $spawn) {
            if (($p = $this->pg->api->player->get($name)) instanceof Player) {

				$p->entity->setHealth($this->pg->configs['join.health']);
                $p->sendChat($this->pg->lang['game.start']);
                if ($p->entity->level->getBlock((new \Vector3(floor($p->entity->x), floor($p->entity->y), floor($p->entity->z)))->subtract(0, 2))->getID() == 20)
                    $p->entity->level->setBlock((new \Vector3(floor($p->entity->x), floor($p->entity->y), floor($p->entity->z)))->subtract(0, 2), BlockAPI::get(AIR), true, false);
                if ($p->entity->level->getBlock((new \Vector3(floor($p->entity->x), floor($p->entity->y), floor($p->entity->z)))->subtract(0, 1))->getID() == 20)
					$p->entity->level->setBlock((new \Vector3(floor($p->entity->x), floor($p->entity->y), floor($p->entity->z)))->subtract(0, 1), BlockAPI::get(AIR), true, false);
            }
        }
        $this->time = 0;
        $this->GAME_STATE = 2;
        $this->pg->refreshSigns(false, $this->SWname, $this->getSlot(true), $this->slot, $this->getState());
    }


    /**
     * @param bool $force
     * @return bool
     */
    public function stop($force = false)
    {
        $this->pg->api->level->loadLevel($this->world);
        //CLOSE SPECTATORS
        foreach ($this->spectators as $playerName) {
            if (($s = $this->pg->api->player->get($playerName)) instanceof Player)
				$s->sendChat("\n\n           §l§c你§r\n§7错过了那场比赛§r\n\n\n\n\n\n ");
                $this->closePlayer($s);
        }
        //CLOSE PLAYERS
        foreach ($this->players as $name => $spawn) {
            if (($p = $this->pg->api->player->get($name)) instanceof Player) {
                 $p->sendChat("\n\n   §l§a你§r\n§f赢得了胜利!§r\n\n\n\n\n\n ");
                $this->closePlayer($p);
                if (!$force) {
                    //Broadcast winner
                    foreach ($this->pg->api->level->getDefault()->players as $pl) {
                        $pl->sendChat(str_replace('{SWNAME}', $this->SWname, str_replace('{PLAYER}', $p->username, $this->pg->lang['server.broadcast.winner'])));
                    }
                    //Economy reward
                    if ($this->pg->configs['reward.winning.players'] && is_numeric($this->pg->configs['reward.value']) && is_int(($this->pg->configs['reward.value'] + 0)) && $this->pg->economy->getApiVersion() != 0) {
                        $this->pg->economy->addMoney($p, (int)$this->pg->configs['reward.value']);
                        $p->sendChat(str_replace('{MONEY}', $this->pg->economy->getMoney($p), str_replace('{VALUE}', $this->pg->configs['reward.value'], $this->pg->lang['winner.reward.msg'])));
                    }
                    //Reward command
                    $command = trim($this->pg->configs['reward.command']);
                    if (strlen($command) > 1 && $command[0] == '/') {
						//$this->api->console->defaultCommands("stop", "", "plugin", false);
                        //$this->pg->getServer()->dispatchCommand(new \pocketmine\command\ConsoleCommandSender(), str_replace('{PLAYER}', $p->getName(), substr($command, 1)));
                    }
                }
            }
        }
        //Other players
        foreach ($this->pg->api->level->get($this->world)->players as $p)
            $p->teleport($this->pg->api->level->getDefault()->getSafeSpawn());
        $this->reload();
        return true;
    }

	private function sendSettings(Player $player, $nametags = true, $b = true){
		/*
		 bit mask | flag name
		0b00000001 allowInteract
		0b00000010 - enablePVP
		0b00000100 - enablePVE
		0b00001000 - field_3 (<?>autojump)
		0b00010000 - dayLightCycle (0 enabled 1 disabled)
		0b00100000 - field_5 (<?>nametags_visible)
		0b01000000 - unused
		0b10000000 - unused
		*/
		$flags = 0;
		if($b){
			$flags |= 0x01; //Do not allow placing/breaking blocks, adventure mode
			$flags |= 0x2; //pvp
			$flags |= 0x4; //pve
		}

		if($nametags !== false){
			$flags |= 0x20; //Show Nametags
		}

		$pk = new AdventureSettingsPacket;
		$pk->flags = $flags;
		$player->dataPacket($pk);
	}
}