<?php
namespace yf\sw;

use Arrow;
use DataPacketReceiveEvent;
use Entity;
use Player;
use Plugin;
use ServerAPI;
use BlockAPI;
use \yf\sw\handlers\PacketListener;
use \yf\sw\commands\CommandSkyWar;
use \yf\sw\SWarena;

class SWmain implements Plugin{
	
	public $api;
	public $dataPath;
	public $playerTiles = [];

    /** Plugin Version */
    const SW_VERSION = '0.6dev';

    /** @var CommandSkyWar */
    private $commands;
    /** @var SWarena[] */
    public $arenas = [];
    /** @var array */
    public $signs = [];
    /** @var array */
    public $configs;
    /** @var array */
    public $lang;
    /** @var \SQLite3 */
    private $db;

    public $economy;

    /** @var int */
    private $seconds = 0;
    /** @var bool */
    private $tick = false;


	public function __construct(ServerAPI $api, $server = false)
	{
		$this->api = $api;
	}
	
	public function init()
	{

		//sw/commands/CommandSkyWar
		$this->commands = new CommandSkyWar($this, $this->api, "play an SkyWar", false);

        //Sometimes the silence operator " @ " doesn't works and the server crash, this is better.Don't ask me why, i just know that.
		$this->dataPath = "{$this->api->plugin->configPath($this)}/";
		if(!is_dir($this->dataPath)) {
			mkdir($this->dataPath, 0755, true);
		}

		$arenaPath = "{$this->api->plugin->configPath($this)}/arenas/";
		if(!is_dir($arenaPath)) {
			mkdir($arenaPath, 0777, true);
		}
		unset($arenaPath);

		//TODO:: World level name check

        //Creates the database that is needed to store signs info
        try {
            if (!is_file($this->dataPath . "\x53\x57\x5f\x73\x69\x67\x6e\x73\x2e\x64\x62")) {
                $this->db = new \SQLite3($this->dataPath . "\x53\x57\x5f\x73\x69\x67\x6e\x73\x2e\x64\x62", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            } else {
                $this->db = new \SQLite3($this->dataPath . "\x53\x57\x5f\x73\x69\x67\x6e\x73\x2e\x64\x62", SQLITE3_OPEN_READWRITE);
            }
            $this->db->exec("CREATE TABLE IF NOT EXISTS signs (arena TEXT PRIMARY KEY COLLATE NOCASE, x INTEGER , y INTEGER , z INTEGER, world TEXT);");
        } catch (\Exception $e) {
			\ConsoleAPI::warn($e->getMessage() . ' in §b' . $e->getFile() . '§c on line §b' . $e->getLine());
			$this->api->console->defaultCommands("stop", "", "plugin", false);
        }

        //Config file...
        $file = new \Config($this->dataPath . 'SW_configs.yml', CONFIG_YAML);
		$file->save();
		unset($file);

        //Config files: /SW_configs.yml /SW_lang.yml & for arenas: /arenas/SWname/settings.yml

        /*
                                       __  _                                   _
                   ___   ___   _ __   / _|(_)  __ _  ___     _   _  _ __ ___  | |
                  / __| / _ \ | '_ \ | |_ | | / _` |/ __|   | | | || '_ ` _ \ | |
                 | (__ | (_) || | | ||  _|| || (_| |\__ \ _ | |_| || | | | | || |
                  \___| \___/ |_| |_||_|  |_| \__, ||___/(_) \__, ||_| |_| |_||_|
                                              |___/          |___/
        */
        $this->configs = new \Config($this->dataPath . 'SW_configs.yml', CONFIG_YAML, [
            'CONFIG_VERSION' => self::SW_VERSION,
            'banned.commands.while.in.game' => array('/hub', '/lobby', '/spawn', '/tpa', '/tp', '/tpaccept', '/back', '/home', '/f', '/kill'),
            'start.when.full' => true,
            'needed.players.to.run.countdown' => 1,
            'join.max.health' => 20,
            'join.health' => 20,
            'damage.cancelled.causes' => [0, 3, 4, 8, 12, 15],
            'drops.on.death' => false,
            'player.drop.item' => true,
            'chest.refill' => true,
            'chest.refill.rate' => 0xf0,
            'no.pvp.countdown' => 20,
            'death.spectator' => true,
            'spectator.quit.item' => '345:0',
            'reward.winning.players' => false,
            'reward.value' => 100,
            'reward.command' => '/',
            '1st_line' => 'SkyWars',
            '2nd_line' => '{SWNAME}',
            'sign.tick' => false,
            'sign.knockBack' => true,
            'knockBack.radius.from.sign' => 1,
            'knockBack.intensity' => 0b10,
            'knockBack.follow.sign.direction' => false,
            'always.spawn.in.defaultLevel' => true,
            'clear.inventory.on.respawn&join' => false,//many people don't know on respawn means also on join
            'clear.inventory.on.arena.join' => true,
            'clear.effects.on.respawn&join' => false,//many people don't know on respawn means also on join
            'clear.effects.on.arena.join' => true,
            'world.generator.air' => true,
            'world.compress.tar' => false,
            'world.reset.from.tar' => true
        ]);
        $this->configs = $this->configs->getAll();

        /*
                  _                                                   _
                 | |   __ _   _ __     __ _       _   _   _ __ ___   | |
                 | |  / _` | | '_ \   / _` |     | | | | | '_ ` _ \  | |
                 | | | (_| | | | | | | (_| |  _  | |_| | | | | | | | | |
                 |_|  \__,_| |_| |_|  \__, | (_)  \__, | |_| |_| |_| |_|
                                      |___/       |___/
        */
        $this->lang = new \Config($this->dataPath . 'SW_lang.yml', CONFIG_YAML, [
			'banned.command.msg' => '@a» §f you can not type commands here.',
			'sign.game.full' => '@c§cgame is full!',
			'sign.game.running' => '@a» §cGame is full',
			'game.join' => '@a» @e{PLAYER} @fJoined the HungerGame @7{COUNT}',
			'popup.countdown' => 'Hungergame will be started in  {N}  !',
			'chat.countdown' => '',
			'game.start' => '@a» §fGame has been Started! Cheating is bannable!',
			'no.pvp.countdown' => 'PvP will be enabled in {COUNT}  !',
			'game.chest.refill' => '',
			'game.left' => '@a» @e{PLAYER} @f Leave the game @7{COUNT}',
			'death.player' => '@a» @e{PLAYER} @fwas Killed by @b{KILLER}@f! @7{COUNT}',
			'death.arrow' => '@a» @e{PLAYER} @fwas Killed by @b{KILLER}@f! @7{COUNT}',
			'death.void' => '@a» @e{PLAYER} @fell into the void! @7{COUNT}',
			'death.lava' => '@a» @e{PLAYER} @fWas kill by Lava @7{COUNT}',
			'death.spectator' => '',
			'server.broadcast.winner' => '@a» @b{PLAYER} @eHas win in: @b{SWNAME}',
			'winner.reward.msg' => ''
        ]);
        touch($this->dataPath . 'SW_lang.yml');
		console(3);
        $this->lang = $this->lang->getAll();
        file_put_contents($this->dataPath . 'SW_lang.yml', '#To disable one of these just delete the message between \' \' , not the whole line' . PHP_EOL . '#You can use " @ " to set colors and _EOL_ as EndOfLine' . PHP_EOL . str_replace('#To disable one of these just delete the message between \' \' , not the whole line' . PHP_EOL . '#You can use " @ " to set colors and _EOL_ as EndOfLine' . PHP_EOL, '', file_get_contents($this->dataPath . 'SW_lang.yml')));
        $newlang = [];
		console(1);
        foreach ($this->lang as $key => $val) {
            $newlang[$key] = str_replace('  ', ' ', str_replace('_EOL_', "\n", str_replace('@', '§', trim($val))));
        }
		console(2);
        $this->lang = $newlang;
        unset($newlang);

		$this->tick = (bool)$this->configs['sign.tick'];

        //Register timer and listener
		$this->api->schedule(19, [$this, "onRun"], $this->tick, 'server,schedule');

		$this->api->addHandler("tile.update", [$this, "onSignChange"]);
		$this->api->addHandler("player.interact", [$this, "onInteract"]);
		$this->api->addHandler("player.teleport.level", [$this, "onLevelChange"]);
		$this->api->addHandler("player.teleport", [$this, "onTeleport"]);
		$this->api->addHandler("player.drop", [$this, "onDropItem"]);
		$this->api->addHandler("player.pickup", [$this, "onPickUp"]);
		$this->api->addHandler("player.equipment.change", [$this, "onItemHeld"]);
//		$this->api->addHandler("player.join", [$this, "handler"]);
//		$this->api->addHandler("player.join", [$this, "handler"]);
		$this->api->addHandler("player.block.break", [$this, "onBreak"]);
		$this->api->addHandler("player.death", [$this, "onDeath"]);
		//$this->api->addHandler("player.touch", [$this, "handler"]);
		$this->api->addHandler("player.quit", [$this, "onQuit"]);
		$this->api->addHandler("player.respawn", [$this, "onRespawn"]);
		$this->api->addHandler("player.block.place", [$this, "onPlace"]);
		$this->api->addHandler("player.block.touch", [$this, "onTouch"]);
//		$this->api->addHandler("player.offline.get", [$this, "handler"]);
		$this->api->addHandler("player.move", [$this, "onMove"]);
		$this->api->addHandler("entity.health.change", [$this, "onDamage"]);
		$this->api->addHandler("console.command", [$this, "onCommand"]);

        //Calls loadArenas() & loadSigns() to loads arenas & signs...
        if (!($this->loadSigns() && $this->loadArenas())) {
			\ConsoleAPI::error('一个错误产生在加载插件中, 尝试删除插件的文件夹');
			$this->api->console->defaultCommands("stop", "", "plugin", false);
        }

        if ($this->configs['reward.winning.players']) {
			//$this->economy
			foreach($this->api->plugin->getList() as $p){
				if($p["name"] == "EconomyAPI"){
					$ex = true;
					break;
				}
			}
			if($ex == false){
				echo FORMAT_RED."EconomyAPI not exist\n";
				$this->api->console->defaultCommands("stop", "", "plugin", false);
				return;
			}
        }

		$this->api->economy->EconomySRegister("NostalgiaSkyWar");

		DataPacketReceiveEvent::register(new PacketListener($this, $this->api), \EventPriority::NORMAL);
		if(!is_file("{$this->dataPath}/config.yml")){
		}
	}

    public function onSignChange($data)
    {
		if($data->class === TILE_SIGN){
			$result = $data->data["Text1"];
			if($result){

				$player = $this->api->player->get($data->data["creator"], false);
				if ($data->data["Text1"] != 'sw' || !$this->api->ban->isOp($data->data["creator"]))
					return;

				//Checks if the arena exists
				$SWname = \TextFormat::clean(trim($data->data["Text2"]));
				if (!array_key_exists($SWname, $this->arenas)) {
					$player->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '这个领地未能设置成功，可以尝试 ' . FORMAT_WHITE . '/sw create');
					return;
				}

				//Checks if a sign already exists for the arena
				if (in_array($SWname, $this->signs)) {
					$player->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '这个领地的牌子已经设置了，可以尝试 ' . FORMAT_WHITE . '/sw signdelete');
					return;
				}

				//Checks if the sign is placed inside arenas
				$world = $data->level->getName();
				foreach ($this->arenas as $name => $arena) {
					if ($world == $arena->getWorld()) {
						$player->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '你不能放置参与牌子在领地内');
						return;
					}
				}

				//Checks arena spawns
				if (!$this->arenas[$SWname]->checkSpawns()) {
					$player->sendChat(FORMAT_GREEN . '>' . FORMAT_RED . '不是所有的出生点都设置好了，可以尝试 ' . FORMAT_WHITE . ' /sw setspawn');
					return;
				}

				//Saves the sign
				if (!$this->setSign($SWname, ($data->x + 0), ($data->y + 0), ($data->z + 0), $world))
					$player->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '一个错误产生，请报告给开发者 ');
				else
					$player->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '空岛战争牌子设置成功 !');

				//Sets sign format

				$data->data["Text1"] = $this->configs['1st_line'];
				$data->data["Text2"] = str_replace('{SWNAME}', $SWname, $this->configs['2nd_line']);
				$data->data["Text3"] = '0 / ' . $this->arenas[$SWname]->getSlot();
				$data->data["Text4"] = 'Tap to Join';
				$this->api->tile->spawnToAll($data);
				$this->refreshSigns(true);
				unset($SWname, $world);
			}
		}
    }

    public function onInteract($data)
    {
        //In-arena Tap
        foreach ($this->arenas as $a) {
            if ($t = $a->inArena($data["player"]->iusername)) {
                if ($t == 2)
                   	return false;
                if ($a->GAME_STATE == 0)
                    return false;
                return;
            }
        }
    }

	public function onLevelChange($data)
    {
        if ($data["player"] instanceof Player) {
            foreach ($this->arenas as $a) {
                if ($a->inArena($data["player"]->iusername)) {
                    return false;
                }
            }
        }
    }


    public function onTeleport($data)
    {
        if ($data["player"] instanceof Player) {
            foreach ($this->arenas as $a) {
                if ($a->inArena($data["player"]->iusername)) {
                    //Allow near teleport
					$from = new \Vector3($data["player"]->entity->x, $data["player"]->entity->y, $data["player"]->entity->z);
                    if ($from->distanceSquared($data["target"]) < 20)
                        return;
					return false;
                }
            }
        }
    }


    public function onDropItem($data)
    {
        foreach ($this->arenas as $a) {
            if (($f = $a->inArena($data["player"]->iusername))) {
                if ($f == 2) {
                    return false;
                }
                if (!$this->configs['player.drop.item']) {
                    return false;
                }
                return;
            }
        }
    }


    public function onPickUp($data)
    {
        if (($p = $data["entity"]) instanceof Player) {
            foreach ($this->arenas as $a) {
                if ($f = $a->inArena($p->iusername)) {
                    if ($f == 2)
                        return false;
                    return;
                }
            }
        }
    }


    public function onItemHeld($data)
    {
        foreach ($this->arenas as $a) {
            if ($f = $a->inArena($data["player"]->iusername)) {
                if ($f == 2) {
                    if ($data["player"]->getHeldItem()->getID() . ':' . ($data["player"]->getHeldItem()->getMetadata()) == $this->configs['spectator.quit.item'])
                        $a->closePlayer($data["player"]);
                    return false;
                    $ev->getPlayer()->getInventory()->setHeldItemIndex(1);
                }
                return;
            }
        }
    }


    public function onMove($data)
    {
        foreach ($this->arenas as $a) {
            if ($a->inArena($data->player->iusername)) {
                if ($a->GAME_STATE == 0) {
                    $spawn = $a->getWorld(true, $data->player->iusername);
					$position = new \Vector3($data->x, $data->y, $data->z);
                    if ($position->distanceSquared(new \Position($spawn['x'], $spawn['y'], $spawn['z'])) > 4)
						$data->player->teleport(new \Position($spawn['x'] , $spawn['y'], $spawn['z'], $data->player->entity->level), $spawn['yaw'], $spawn['pitch'], false, false);
					unset($position);
                    return;
                }
                if ($a->void >= floor($data->player->entity->y) && !$data->player->entity->dead) {
                    $data->player->entity->outOfWorld();
                }
                return;
            }
        }
        //Checks if knockBack is enabled
        if ($this->configs['sign.knockBack']) {
            foreach ($this->signs as $key => $val) {
                $ex = explode(':', $key);
                $pl = $data->player;
                if ($pl->entity->level->getName() == $ex[3]) {
                    $x = (int)floor($pl->entity->x);
                    $y = (int)floor($pl->entity->y);
                    $z = (int)floor($pl->entity->z);
                    $radius = (int)$this->configs['knockBack.radius.from.sign'];
                    //If is inside the sign radius, knockBack
                    if (($x >= ($ex[0] - $radius) && $x <= ($ex[0] + $radius)) && ($z >= ($ex[2] - $radius) && $z <= ($ex[2] + $radius)) && ($y >= ($ex[1] - $radius) && $y <= ($ex[1] + $radius))) {
                        //If the block is not a sign, break
                        $block = $pl->entity->level->getBlock(new \Vector3($ex[0], $ex[1], $ex[2]));
                        if ($block->getId() != 63 && $block->getId() != 68)
                            return;
                        //Max $i should be 90 to avoid bugs-lag, yes 90 is a magic number :P
                        $i = (int)$this->configs['knockBack.intensity'];
                        if ($this->configs['knockBack.follow.sign.direction']) {
                            //Finds sign yaw
                            switch ($block->getId()):
                                case 68:
                                    switch ($block->getMetadata()) {
                                        case 3:
                                            $yaw = 0;
                                            break;
                                        case 4:
                                            $yaw = 0x5a;
                                            break;
                                        case 2:
                                            $yaw = 0xb4;
                                            break;
                                        case 5:
                                            $yaw = 0x10e;
                                            break;
                                        default:
                                            $yaw = 0;
                                            break;
                                    }
                                    break;
                                case 63:
                                    switch ($block->getMetadata()) {
                                        case 0:
                                            $yaw = 0;
                                            break;
                                        case 1:
                                            $yaw = 22.5;
                                            break;
                                        case 2:
                                            $yaw = 0x2d;
                                            break;
                                        case 3:
                                            $yaw = 67.5;
                                            break;
                                        case 4:
                                            $yaw = 0x5a;
                                            break;
                                        case 5:
                                            $yaw = 112.5;
                                            break;
                                        case 6:
                                            $yaw = 0x87;
                                            break;
                                        case 7:
                                            $yaw = 157.5;
                                            break;
                                        case 8:
                                            $yaw = 0xb4;
                                            break;
                                        case 9:
                                            $yaw = 202.5;
                                            break;
                                        case 10:
                                            $yaw = 0xe1;
                                            break;
                                        case 11:
                                            $yaw = 247.5;
                                            break;
                                        case 12:
                                            $yaw = 0x10e;
                                            break;
                                        case 13:
                                            $yaw = 292.5;
                                            break;
                                        case 14:
                                            $yaw = 0x13b;
                                            break;
                                        case 15:
                                            $yaw = 337.5;
                                            break;
                                        default:
                                            $yaw = 0;
                                            break;
                                    }
                                    break;
                                default:
                                    $yaw = 0;
                            endswitch;
                            //knockBack sign direction
                            $vector = (new \Vector3(-sin(deg2rad($yaw)), 0, cos(deg2rad($yaw))))->normalize();
                            //$pl->entity->knockback($pl, 0, $vector->x, $vector->z, ($i / 0xa));
							//TODO ::KnockBack
                        } else {
                            //knockBack sign center
                            //$pl->knockBack($pl, 0, ($pl->x - ($block->x + 0.5)), ($pl->z - ($block->z + 0.5)), ($i / 0xa));
							//TODO ::KnockBack
                        }
                        break;
                    }
                    unset($ex, $pl, $x, $y, $z, $radius, $block, $i, $yaw);
                }
            }
        }
    }


    public function onQuit($data)
    {
        foreach ($this->arenas as $a) {
            if ($a->closePlayer($data, true))
                break;
        }
    }


    public function onDeath($data)
    {
        if ($data["player"] instanceof \Player) {
            $p = $data["player"];
            foreach ($this->arenas as $a) {
                if ($a->closePlayer($p)) {
                    //$event->setDeathMessage('');
                    $cause = $data["cause"];
                    $count = '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']';

                    switch ($cause):

                        case "void":
                            $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->username, $this->lang['death.void']));
                            break;


                        case "fire":
                            $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->username, $this->lang['death.lava']));
                            break;


                        default:
                            $message = str_replace('{COUNT}', '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']', str_replace('{PLAYER}', $p->username, $this->lang['game.left']));
                            break;


                    endswitch;

					if(is_numeric($cause)){
						$e = $this->api->entity->get($cause);
						if($e instanceof \Entity){
							if($e instanceof Arrow){
								if($e->shotByEntity && isset($this->server->api->entity->entities[$e->shooterEID]) && $this->server->api->entity->entities[$e->shooterEID] instanceof \Entity){
									$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $this->server->api->entity->entities[$e->shooterEID]->name, str_replace('{PLAYER}', $p->username, $this->lang['death.arrow'])));
								}else{
									$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', 'Unknown', str_replace('{PLAYER}', $p->username, $this->lang['death.player'])));
								}
							}else{
								$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $e->name, str_replace('{PLAYER}', $p->username, $this->lang['death.player'])));
							}
						}
					}

                    foreach ($this->api->level->get($a->getWorld())->players as $pl)
                        $pl->sendChat($message);

//                    if (!$this->configs['drops.on.death'])
//                        $event->setDrops([]);
					if ($this->configs['always.spawn.in.defaultLevel'])
						$p->setSpawn($this->api->level->getDefault()->getSafeSpawn());
                    return;
                }
            }
        }
    }


    public function onDamage($data)
    {
        if ($data["entity"]->player instanceof \Player) {
            $p = $data["entity"]->player;
            foreach ($this->arenas as $a) {
                if ($f = $a->inArena($p->iusername)) {
                    if ($f != 1) {
                        return false;
                    }
                    if ($data["cause"] === "player" && ($d = $this->api->entity->get($data["cause"])) instanceof Player) {
                        if (($f = $a->inArena($d->getName())) == 2 || $f == 0) {
                            return false;
                        }
                    }
                    $cause = $data["cause"];
                    if (in_array($cause, $this->configs['damage.cancelled.causes'])) {
                        return false;
                    }
                    if ($a->GAME_STATE == 0 || $a->GAME_STATE == 2) {
                        return false;
                    }

                    //SPECTATORS
                    $spectate = (bool)$this->configs['death.spectator'];
                    if ($spectate) {
                        if (($data["health"] <= 0)) {
                            //FAKE KILL PLAYER MSG
                            $count = '[' . ($a->getSlot(true) - 1) . '/' . $a->getSlot() . ']';

                            switch ($cause):

                                case "void":
                                    $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->username, $this->lang['death.void']));
                                    break;


                                case "fire":
                                    $message = str_replace('{COUNT}', $count, str_replace('{PLAYER}', $p->username, $this->lang['death.lava']));
                                    break;


                                default:
                                    $message = str_replace('{COUNT}', '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']', str_replace('{PLAYER}', $p->username, $this->lang['game.left']));
                                    break;


                            endswitch;

							if(is_numeric($cause)){
								$e = $this->api->entity->get($cause);
								if($e instanceof \Entity){
									if($e instanceof Arrow){
										if($e->shotByEntity && isset($this->server->api->entity->entities[$e->shooterEID]) && $this->server->api->entity->entities[$e->shooterEID] instanceof Entity){
											$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $this->server->api->entity->entities[$e->shooterEID]->name, str_replace('{PLAYER}', $p->username, $this->lang['death.arrow'])));
										}else{
											$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', 'Unknown', str_replace('{PLAYER}', $p->username, $this->lang['death.player'])));
										}
									}else{
										$message = str_replace('{COUNT}', $count, str_replace('{KILLER}', $e->name, str_replace('{PLAYER}', $p->username, $this->lang['death.player'])));
									}
								}
							}

                            foreach ($p->entity->level->players as $pl)
                                $pl->sendChat($message);

//                            //DROPS
//                            if ($this->configs['drops.on.death']) {
//                                foreach ($p->getDrops() as $item) {
//                                    $p->getLevel()->dropItem($p, $item);
//                                }
//                            }

                            //CLOSE
                            $a->closePlayer($p, false, true);
							return false;
                        }
                    }
                    return;
                }
            }
        }
    }


    public function onRespawn($data)
    {
        if ($this->configs['always.spawn.in.defaultLevel'])
			$data->setSpawn($this->api->level->getDefault()->getSafeSpawn());
        //Removes player things
        if ($this->configs['clear.inventory.on.respawn&join']) {
			$air = BlockAPI::getItem(AIR, 0, 0);
			foreach ($data->inventory as $s => $item) {
				if ($item->getID() !== AIR) {
					$data->inventory[$s] = $air;
				}
			}
			$data->armor = [$air, $air, $air, $air];
			$data->sendInventory();
			$data->sendArmor($data);
		}
    }


    public function onBreak($data)
    {
        foreach ($this->arenas as $a) {
            if ($t = $a->inArena($data["player"]->iusername)) {
                if ($t == 2)
                    return false;
                if ($a->GAME_STATE == 0)
                    return false;
                return;
            }
        }
        if (!$this->api->ban->isOp($data["player"]->username)){
			//Join sign Tap check
			$key = $data["target"]->x . ':' . $data["target"]->y . ':' . $data["target"]->z . ':' . $data["target"]->level->getName();
			if (array_key_exists($key, $this->signs))
				if(isset($this->arenas[$this->signs[$key]]))
					$this->arenas[$this->signs[$key]]->join($data["player"]);
			unset($key);
			return;
		}else{
			$key = ((floor($data["target"]->x) + 0) . ':' . (ceil($data["target"]->y) + 0) . ':' . (ceil($data["target"]->z) + 0) . ':' . $data["player"]->entity->level->getName());
			if (array_key_exists($key, $this->signs)) {
				$this->arenas[$this->signs[$key]]->stop(true);
				$data["player"]->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '领地重新加载成功 !');
				if ($this->setSign($this->signs[$key], 0, 0, 0, 'world', true, false)) {
					$data["player"]->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '空岛战争参加牌子成功删除 !');
				} else {
					$data["player"]->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '一个错误产生，请报告给开发者');
				}
			}
			unset($key);
		}
    }


    public function onPlace($data)
    {
        foreach ($this->arenas as $a) {
            if ($t = $a->inArena($data["player"]->iusername)) {
                if ($t == 2)
                    return false;
                if ($a->GAME_STATE == 0)
                    return false;
                return;
            }
        }
    }

	public function onTouch($data)
	{
		foreach ($this->arenas as $a) {
			if ($t = $a->inArena($data["player"]->iusername)) {
				if ($t == 2)
					return false;
				if ($a->GAME_STATE == 0)
					return false;
				return;
			}
		}
		//Join sign Tap check
		$key = $data["target"]->x . ':' . $data["target"]->y . ':' . $data["target"]->z . ':' . $data["target"]->level->getName();
		if (array_key_exists($key, $this->signs))
			if(isset($this->arenas[$this->signs[$key]]))
				$this->arenas[$this->signs[$key]]->join($data["player"]);
		unset($key);
	}

    public function onCommand($data)
    {
        $command = strtolower($data["cmd"]);
        if ($command[0] == '/') {
            $command = explode(' ', $command)[0];
			if($data["issuer"] instanceof \Player){
				if ($this->inArena($data["issuer"]->iusername)) {
					if (in_array($command, $this->configs['banned.commands.while.in.game'])) {
						return false;
					}
				}
			}
        }
        unset($command);
    }

    /*
                      _
       __ _   _ __   (_)
      / _` | | '_ \  | |
     | (_| | | |_) | | |
      \__,_| | .__/  |_|
             |_|

    */

    /**
     * @return bool
     */
    public function loadArenas()
    {
        foreach (scandir($this->dataPath . 'arenas/') as $arenadir) {
            if ($arenadir != '..' && $arenadir != '.' && is_dir($this->dataPath . 'arenas/' . $arenadir)) {
                if (is_file($this->dataPath . 'arenas/' . $arenadir . '/settings.yml')) {
                    $config = new \Config($this->dataPath . 'arenas/' . $arenadir . '/settings.yml', CONFIG_YAML, [
                        'name' => 'default',
                        'slot' => 0,
                        'world' => 'world_1',
                        'countdown' => 0xb4,
                        'maxGameTime' => 0x258,
                        'void_Y' => 0,
                        'spawns' => [],
                    ]);
                    $this->arenas[$config->get('name')] = new SWarena($this, $config->get('name'), ($config->get('slot') + 0), $config->get('world'), ($config->get('countdown') + 0), ($config->get('maxGameTime') + 0), ($config->get('void_Y') + 0));
                    unset($config);
                } else {
                    return false;
                    break;
                }
            }
        }
        return true;
    }


    /**
     * @return bool
     */
    public function loadSigns()
    {
        $this->signs = [];
        $r = $this->db->query("SELECT * FROM signs;");
        while ($array = $r->fetchArray(SQLITE3_ASSOC))
            $this->signs[$array['x'] . ':' . $array['y'] . ':' . $array['z'] . ':' . $array['world']] = $array['arena'];
        if (empty($this->signs) && !empty($array))
            return false;
        else
            return true;
    }


    /**
     * @param string $SWname
     * @param int $x
     * @param int $y
     * @param int $z
     * @param string $world
     * @param bool $delete
     * @param bool $all
     * @return bool
     */
    public function setSign($SWname, $x, $y, $z, $world, $delete = false, $all = true)
    {
        if ($delete) {
            if ($all)
                $this->db->query("DELETE FROM signs;");
            else
                $this->db->query("DELETE FROM signs WHERE arena='$SWname';");
            if ($this->loadSigns())
                return true;
            else
                return false;
        } else {
            $stmt = $this->db->prepare("INSERT OR REPLACE INTO signs (arena, x, y, z, world) VALUES (:arena, :x, :y, :z, :world);");
            $stmt->bindValue(":arena", $SWname);
            $stmt->bindValue(":x", $x);
            $stmt->bindValue(":y", $y);
            $stmt->bindValue(":z", $z);
            $stmt->bindValue(":world", $world);
            $stmt->execute();
            if ($this->loadSigns())
                return true;
            else
                return false;
        }
    }


    /**
     * @param bool $all
     * @param string $SWname
     * @param int $players
     * @param int $slot
     * @param string $state
     */
    public function refreshSigns($all = true, $SWname = '', $players = 0, $slot = 0, $state = 'Tap to Join')
    {
        if (!$all) {
            $ex = explode(':', array_search($SWname, $this->signs));
            if (count($ex) == 0b100) {
				$this->api->level->loadLevel($ex[0b11]);
                if ($this->api->level->get($ex[0b11]) != null) {
					$tile = $this->api->tile->getXYZ($this->api->level->get($ex[0b11]), $ex[0], $ex[1], $ex[0b10]);
                    if ($tile instanceof \Tile && $tile->class === TILE_SIGN) {
						$text = $tile->getText();
                        $tile->setText($text[0], $text[1],$players . '/' . $slot, $state);
                    } else {
						\ConsoleAPI::error('Can\'t get ' . $SWname . ' sign.Error finding sign on level: ' . $ex[0b11] . ' x:' . $ex[0] . ' y:' . $ex[1] . ' z:' . $ex[2]);
                    }
                }
            }
        } else {
            foreach ($this->signs as $key => $val) {
                $ex = explode(':', $key);
				$this->api->level->loadLevel($ex[0b11]);
                if (($this->api->level->get($ex[0b11]) instanceof \Level)) {
					$tile = $this->api->tile->getXYZ($this->api->level->get($ex[0b11]), $ex[0], $ex[1], $ex[2]);
                    if ($tile instanceof \Tile && $tile->class === TILE_SIGN) {
                        $text = $tile->getText();
                        $tile->setText($text[0], $text[1], $this->arenas[$val]->getSlot(true) . '/' . $this->arenas[$val]->getSlot(), $text[3]);
                    } else {
						\ConsoleAPI::warn('Can\'t get ' . $val . ' sign.Error finding sign on level: ' . $ex[0b11] . ' x:' . $ex[0] . ' y:' . $ex[1] . ' z:' . $ex[2]);
                    }
                }
            }
        }
    }


    /**
     * @param string $playerName
     * @return bool
     */
    public function inArena($playerName = '')
    {
        foreach ($this->arenas as $a) {
            if ($a->inArena($playerName)) {
                return true;
            }
        }
        return false;
    }


    /**
     * @return array
     */
    public function getChestContents() //TODO: **rewrite** this and let the owner decide the contents of the chest
    {
        $items = array(
            //ARMOR
            'armor' => array(
                array(
                    LEATHER_CAP,
                    IRON_CHESTPLATE,
                    DIAMOND_LEGGINGS,
                    IRON_BOOTS
                ),
                array(
                    GOLD_HELMET,
                    LEATHER_TUNIC,
                    IRON_LEGGINGS,
                    CHAIN_BOOTS
                ),
				array(
                    CHAIN_HELMET,
                    GOLD_CHESTPLATE,
                    LEATHER_PANTS,
                    DIAMOND_BOOTS
                ),
				array(
                    IRON_HELMET,
                    DIAMOND_CHESTPLATE,
                    CHAIN_LEGGINGS,
                    LEATHER_BOOTS
                ),
				array(
                    DIAMOND_HELMET,
                    CHAIN_CHESTPLATE,
                    GOLD_LEGGINGS,
                    GOLD_BOOTS
                )
            ),

            //WEAPONS
            'weapon' => array(
                array(
                    GOLDEN_SWORD,
                    IRON_AXE
                ),
                array(
                    IRON_SWORD,
                   	GOLDEN_AXE
                ),
				array(
                    DIAMOND_SWORD,
                    WOODEN_AXE
                ),
				array(
                    WOODEN_SWORD,
                    DIAMOND_AXE
                ),
				array(
                    STONE_SWORD,
                    DIAMOND_AXE
                ),
				array(
                   	DIAMOND_SWORD,
                    STONE_AXE
                )
            ),

            //FOOD
            'food' => array(
                array(
                    CARROT,
					SADDLE
                ),
                array(
					STEAK
                ),
				array(
					PUMPKIN_PIE,
					BEETROOT_SOUP
                ),
				array(
                    APPLE,
					RAW_PORKCHOP
                ),
				array(
                   	COOKED_PORKCHOP
                ),
				array(
                    SLIMEBALL,
					CAKE
                ),
				array(
                    MELON_SLICE,
					RAW_CHICKEN
                ),
				array(
                    COOKED_BEEF,
                    BEEF
                ),
				array(
                    COOKED_CHICKEN
                ),
				array(
                    BONE
                ),
				array(
                    MELON,
					MELON_BLOCK
                ),
				array(
                   	CARROT
                ),
				array(
                    BUCKET,
					TNT
                ),
                array(
                    MUSHROOM_STEW,
					BONE
                ),
                array(
                    ARROW
                ),
            ),

            //THROWABLE
            'throwable' => array(
                array(
					BOW,
					ARROW
                ),
				array(
					ARROW
				),
				array(
					EGG
				),
                array(
                    SNOWBALL
                )
            ),

            //BLOCKS
            'block' => array(
                STONE,
                WOODEN_PLANK,
				CLAY,
				MOSSY_STONE,
                COBBLESTONE,
				DIRT,
				BRICKS_BLOCK,
				COBWEB
            ),

            //OTHER
            'other' => array(
                array(
                    DIAMOND_PICKAXE,
					WOODEN_PICKAXE,
					STONE_PICKAXE,
					IRON_PICKAXE,
					GOLDEN_PICKAXE,
					LAVA,
					BUCKET,
					WATER
                )
            )
        );

        $templates = [];
        for ($i = 0; $i < 10; $i++) {

            $armorq = mt_rand(0, 1);
            $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
            $armor1 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
            if ($armorq) {
                $armortype = $items['armor'][mt_rand(0, (count($items['armor']) - 1))];
                $armor2 = array($armortype[mt_rand(0, (count($armortype) - 1))], 1);
            } else {
                $armor2 = array(0, 1);
            }
            unset($armorq, $armortype);

            $weapontype = $items['weapon'][mt_rand(0, (count($items['weapon']) - 1))];
            $weapon = array($weapontype[mt_rand(0, (count($weapontype) - 1))], 1);
            unset($weapontype);

            $ftype = $items['food'][mt_rand(0, (count($items['food']) - 1))];
            $food = array($ftype[mt_rand(0, (count($ftype) - 1))], mt_rand(2, 5));
            unset($ftype);

            $add = mt_rand(0, 1);
            if ($add) {
                $tr = $items['throwable'][mt_rand(0, (count($items['throwable']) - 1))];
                if (count($tr) == 2) {
                    $throwable1 = array($tr[1], mt_rand(10, 20));
                    $throwable2 = array($tr[0], 1);
                } else {
                    $throwable1 = array(0, 1);
                    $throwable2 = array($tr[0], mt_rand(5, 10));
                }
                $other = array(0, 1);
            } else {
                $throwable1 = array(0, 1);
                $throwable2 = array(0, 1);
                $ot = $items['other'][mt_rand(0, (count($items['other']) - 1))];
                $other = array($ot[mt_rand(0, (count($ot) - 1))], 1);
            }
            unset($add, $tr, $ot);

            $block = array($items['block'][mt_rand(0, (count($items['block']) - 1))], 64);

            $contents = array(
                $armor1,
                $armor2,
                $weapon,
                $food,
                $throwable1,
                $throwable2,
                $block,
                $other
            );
            shuffle($contents);
            $fcontents = array(
                mt_rand(1, 2) => array_shift($contents),
                mt_rand(3, 5) => array_shift($contents),
                mt_rand(6, 10) => array_shift($contents),
                mt_rand(11, 15) => array_shift($contents),
                mt_rand(16, 17) => array_shift($contents),
                mt_rand(18, 20) => array_shift($contents),
                mt_rand(21, 25) => array_shift($contents),
                mt_rand(26, 27) => array_shift($contents),
            );
            $templates[] = $fcontents;

        }

        shuffle($templates);
        return $templates;
    }

    public function onRun($tick)
    {
        foreach ($this->arenas as $SWname => $SWarena)
            $SWarena->tick();

        if ($this->tick) {
            if (($this->seconds % 5 == 0)){
				$this->refreshSigns();
				$this->seconds - 5;
			}
            $this->seconds++;
        }
    }

    public function __destruct(){
    	foreach ($this->arenas as $name => $arena)
                    $arena->stop(true);
    }
}