<?php
namespace yf\sw\commands;

use Item;
use Player;
use Tile;

use Vector3;
use TextFormat;

use yf\sw\SWarena;

class CommandSkyWar extends CommandBase
{
	
	public $command = "sw";

	public function __invoke($cmd, $args, $sender, $alias){

		if (!($sender instanceof Player) || !$this->api->ban->isOp($sender)) {
			switch (strtolower(array_shift($args))):


				case 'entrar':
					if (!(count($args) < 0b11)) {
						$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '使用: /sw ' . FORMAT_GREEN . 'entrar [SWname]' . FORMAT_GRAY . ' [PlayerName]');
						return false;
					}

					if (isset($args[0])) {
						//SW NAME
						$SWname = Textformat::clean(array_shift($args));
						if (!array_key_exists($SWname, $this->pluginInst->arenas)) {
							$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '带名称的领地: ' . FORMAT_WHITE . $SWname . FORMAT_RED . ' doesn\'t exist');
							return false;
						}
					} else {
						if ($sender instanceof Player) {
							foreach ($this->pluginInst->arenas as $a) {
								if ($a->join($sender, false))
									break 2;
							}
							$sender->sendChat(FORMAT_RED . '没有游戏，再试一次');
						}
						return false;
					}

					$player = Textformat::clean(array_shift($args));
					if (strlen($player) > 0 && $sender == "console") {
						$p = $this->api->player->get($player);
						if ($p instanceof Player) {
							if ($this->pluginInst->inArena($p->iusername)) {
								$p->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '你已经在领地里面了');
								return false;
							}
							$this->pluginInst->arenas[$SWname]->join($p);
						} else {
							$sender->sendChat(FORMAT_RED . '未找到玩家!');
						}
					} elseif ($sender instanceof Player) {
						if ($this->pluginInst->inArena($sender->iusername)) {
							$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '你已经在领地里面了');
							return false;
						}
						$this->pluginInst->arenas[$SWname]->join($sender);
					} else {
						$sender->sendChat(FORMAT_RED . '未找到玩家!');
					}
					return false;


				case 'sair':
					if (!empty($args)) {
						$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . 'Use: /sw ' . FORMAT_GREEN . 'sair');
						return false;
					}

					if ($sender instanceof Player) {
						foreach ($this->pluginInst->arenas as $a) {
							if ($a->closePlayer($sender, true))
								return false;
						}
					} else {
						$sender->sendChat('这条指令只能在领地内使用');
					}
					return false;


				default:
					//No option found, usage
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '使用: /sw [entrar|sair]');
					return false;


			endswitch;
			return true;
		}

		//Searchs for a valid option
		switch (strtolower(array_shift($args))):


			case 'create':
				/*
										  _
				  ___  _ __   ___   __ _ | |_   ___
				 / __|| '__| / _ \ / _` || __| / _ \
				| (__ | |   |  __/| (_| || |_ |  __/
				 \___||_|    \___| \__,_| \__| \___|

				*/
				if (!(count($args) > 0b11 && count($args) < 0b101)) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '使用: /sw ' . FORMAT_GREEN . 'create [SWname] [slots] [countdown] [maxGameTime]');
					break;
				}

				$fworld = $sender->entity->level->getName();
				$world = $sender->entity->level->getName();

				//Checks if the world is default
				if ($this->api->getProperty("level-name") == $world || $this->api->level->getDefault()->getName() == $world || $this->api->level->getDefault()->getName() == $world) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '你不能在普通的世界里面创建领地');
					unset($fworld, $world);
					break;
				}

				//Checks if there is already an arena in the world
				foreach ($this->pluginInst->arenas as $aname => $arena) {
					if ($arena->getWorld() == $world) {
						$sender->sendChat(FORMAT_RED . '>' . FORMAT_RED . '你不能创建两个相同的领地在一个世界，可以尝试使用如下指令:');
						$sender->sendChat(FORMAT_RED . '>' . FORMAT_WHITE . '/sw list' . FORMAT_RED . ' 所有领地');
						$sender->sendChat(FORMAT_RED . '>' . FORMAT_WHITE . '/sw delete' . FORMAT_RED . ' 用来删除一个领地');
						unset($fworld, $world);
						break 2;
					}
				}

				//Checks if there is already a join sign in the world
				foreach ($this->pluginInst->signs as $loc => $name) {
					if (explode(':', $loc)[3] == $world) {
						$sender->sendChat(FORMAT_RED . '>' . FORMAT_RED . '你不能\'t 创建领地牌子在一个相同的世界:');
						$sender->sendChat(FORMAT_RED . '>' . FORMAT_WHITE . '/sw signdelete' . FORMAT_RED . ' 用来删除牌子');
						unset($fworld, $world);
						break 2;
					}
				}

				//SW NAME
				$SWname = array_shift($args);
				if (!($SWname && preg_match('/^[a-z0-9]+[a-z0-9]$/i', $SWname) && strlen($SWname) < 0x10 && strlen($SWname) > 0b10)) {
					$sender->sendChat(FORMAT_WHITE . '>' . FORMAT_AQUA . '[SWname]' . FORMAT_RED . ' must consists of a-z 0-9 (min3-max15)');
					unset($fworld, $world, $SWname);
					break;
				}

				//Checks if the arena already exists
				if (array_key_exists($SWname, $this->pluginInst->arenas)) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '带名称的领地: ' . FORMAT_WHITE . $SWname . FORMAT_RED . ' already exist');
					unset($fworld, $world, $SWname);
					break;
				}

				//ARENA SLOT
				$slot = array_shift($args);
				if (!($slot && is_numeric($slot) && is_int(($slot + 0)) && $slot < 0x33 && $slot > 1)) {
					$sender->sendChat(FORMAT_WHITE . '>' . FORMAT_AQUA . '[slots]' . FORMAT_RED . ' must be an integer >= 50 and >= 2');
					unset($fworld, $world, $SWname, $slot);
					break;
				}
				$slot += 0;

				//ARENA COUNTDOWN
				$countdown = array_shift($args);
				if (!($countdown && is_numeric($countdown) && is_int(($countdown + 0)) && $countdown > 0b1001 && $countdown < 0x12d)) {
					$sender->sendChat(FORMAT_WHITE . '>' . FORMAT_AQUA . '[countdown]' . FORMAT_RED . ' must be an integer <= 300 seconds (5 minutes) and >= 10');
					unset($fworld, $world, $SWname, $slot, $countdown);
					break;
				}
				$countdown += 0;

				//ARENA MAX EXECUTION TIME
				$maxtime = array_shift($args);
				if (!($maxtime && is_numeric($maxtime) && is_int(($maxtime + 0)) && $maxtime > 0x12b && $maxtime < 0x259)) {
					$sender->sendChat(FORMAT_WHITE . '>' . FORMAT_AQUA . '[maxGameTime]' . FORMAT_RED . ' must be an integer <= 600 (10 minutes) and >= 300');
					unset($fworld, $world, $SWname, $slot, $countdown, $maxtime);
					break;
				}
				$maxtime += 0;

				//ARENA LEVEL NAME
				if ($fworld == $world) {
					$sender->sendChat(FORMAT_WHITE . '>' . FORMAT_RED . '使用您当前所在的世界: ' . FORMAT_AQUA . $world . FORMAT_RED . ' ,expected lag');
				} else {
					$sender->sendChat(FORMAT_WHITE . '>' . FORMAT_RED . '这有一些小问题有关世界名称，可以尝试重启服务器进行解决');
//                    $provider = $sender->getLevel()->getProvider();
//                    if ($provider instanceof \pocketmine\level\format\generic\BaseLevelProvider) {
//                        $provider->getLevelData()->LevelName = new Str('LevelName', $fworld);
//                        $provider->saveLevelData();
//                    }
					unset($fworld, $world, $SWname, $slot, $countdown, $maxtime, $provider);
					break;
				}

				//Air world generator

				$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_LIGHT_PURPLE . 'I\'m creating a backup of the world...teleporting to hub');

				//This is the "fake void"

				$last = 0x80;
//                foreach ($this->api->level->get($sender->entity->level->getName())->getMiniChunk() as $chunk) {
//                    for ($x = 0; $x < 0x10; $x++) {
//                        for ($z = 0; $z < 0x10; $z++) {
//                            for ($y = 0; $y < 0x7f; $y++) {
//                                $block = $chunk->getBlockId($x, $y, $z);
//                                if ($block !== 0 && $last > $y) {
//                                    $last = $y;
//                                    break;
//                                }
//                            }
//                        }
//                    }
//                }
//				$void = ($last - 1);
				$void = 0;

				$sender->teleport($this->api->level->getDefault()->getSafeSpawn());
				foreach ($this->api->level->get($world)->players as $p)
					$p->close('', 'Please re-join');
				$this->api->level->unloadLevel($this->api->level->get($world));

				//From here @vars are: $SWname , $slot , $world
				// { TAR.GZ
				@mkdir($this->pluginInst->dataPath . 'arenas/' . $SWname, 0755);
				$tar = new \PharData($this->pluginInst->dataPath . 'arenas/' . $SWname . '/' . $world . '.tar');
				$tar->startBuffering();
				$tar->buildFromDirectory(realpath(DATA_PATH . 'worlds/' . $world));
				if ($this->pluginInst->configs['world.compress.tar'])
					$tar->compress(\Phar::GZ);
				$tar->stopBuffering();
				if ($this->pluginInst->configs['world.compress.tar']) {
					$tar = null;
					@unlink($this->pluginInst->dataPath . 'arenas/' . $SWname . '/' . $world . '.tar');
				}
				unset($tar);
				$this->api->level->loadLevel($world);
				// END TAR.GZ }

				//SWarena object
				$this->pluginInst->arenas[$SWname] = new SWarena($this->pluginInst, $SWname, $slot, $world, $countdown, $maxtime, $void);
				$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . 'Arena: ' . FORMAT_DARK_GREEN . $SWname . FORMAT_GREEN . ' 成功创建!');
				$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '现在设置领地使用指令 ' . FORMAT_WHITE . '/sw setspawn [slot]');
				unset($fworld, $world, $SWname, $slot, $countdown, $maxtime, $provider, $void);
				break;


			case 'setspawn':
				/*
							_    ____
				 ___   ___ | |_ / ___|  _ __   __ _ __      __ _ __
				/ __| / _ \| __|\___ \ | '_ \ / _` |\ \ /\ / /| '_ \
				\__ \|  __/| |_  ___) || |_) | (_| | \ /  / / | | | |
				|___/ \___| \__||____/ | .__/ \__,_|  \_/\_/  |_| |_|
									   |_|

				*/
				if (count($args) != 1) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '使用: /sw ' . FORMAT_GREEN . 'setspawn [slot]');
					break;
				}

				$SWname = '';
				foreach ($this->pluginInst->arenas as $name => $arena) {
					if ($arena->getWorld() == $sender->entity->level->getName()) {
						$SWname = $name;
						break;
					}
				}
				if (!($SWname && preg_match('/^[a-z0-9]+[a-z0-9]$/i', $SWname) && strlen($SWname) < 0x10 && strlen($SWname) > 0b10 && array_key_exists($SWname, $this->pluginInst->arenas))) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '未找到领地 ' . FORMAT_WHITE . '/sw create');
					unset($SWname);
					return false;
				}

				$slot = array_shift($args);
				if (!($slot && is_numeric($slot) && is_int(($slot + 0)) && $slot < 0x33 && $slot > 0)) {
					$sender->sendChat(FORMAT_WHITE . '>' . FORMAT_AQUA . '[slot]' . FORMAT_RED . ' must be an integer <= than 50 and >= 1');
					unset($SWname, $slot);
					return false;
				}
				$slot += 0;

				if ($sender->entity->level->getName() == $this->pluginInst->arenas[$SWname]->getWorld()) {
					if ($this->pluginInst->arenas[$SWname]->setSpawn($sender, $slot)) {
						$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '新出生点: ' . FORMAT_WHITE . $slot . FORMAT_GREEN . ' 在领地内: ' . FORMAT_WHITE . $SWname);
						if ($this->pluginInst->arenas[$SWname]->checkSpawns())
							$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '我找到了所有的出生点的领地: ' . FORMAT_WHITE . $SWname . FORMAT_GREEN . ', 现在你可以创建一个加入的木牌!');
					}
				}
				break;


			case 'list':
				/*
				  _   _         _
				 | | (_)  ___  | |_
				 | | | | / __| | __|
				 | | | | \__ \ | |_
				 |_| |_| |___/  \__|

				*/
				if (count($this->pluginInst->arenas) > 0) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '加载领地中:');
					foreach ($this->pluginInst->arenas as $key => $val) {
						$sender->sendChat(FORMAT_BLACK . '> ' . FORMAT_YELLOW . $key . FORMAT_AQUA . ' [' . $val->getSlot(true) . '/' . $val->getSlot() . ']' . FORMAT_DARK_GRAY . ' => ' . FORMAT_GREEN . $val->getWorld());
					}
				} else {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '这里无法领地，创建一个使用 ' . FORMAT_WHITE . '/sw create');
				}
				break;


			case 'delete':
				/*
					 _        _        _
				  __| |  ___ | |  ___ | |_   ___
				 / _` | / _ \| | / _ \| __| / _ \
				| (_| ||  __/| ||  __/| |_ |  __/
				 \__,_| \___||_| \___| \__| \___|

				*/
				if (count($args) != 1) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '使用: /sw ' . FORMAT_GREEN . 'delete [SWname]');
					break;
				}

				$SWname = array_shift($args);
				if (!($SWname && preg_match('/^[a-z0-9]+[a-z0-9]$/i', $SWname) && strlen($SWname) < 0x10 && strlen($SWname) > 0b10 && array_key_exists($SWname, $this->pluginInst->arenas))) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . 'Arena: ' . FORMAT_WHITE . $SWname . FORMAT_RED . ' doesn\'t exist');
					unset($SWname);
					break;
				}

				if (!(is_dir($this->pluginInst->dataPath . 'arenas/' . $SWname) && is_file($this->pluginInst->dataPath . 'arenas/' . $SWname . '/settings.yml'))) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . 'Arena files doesn\'t exists');
					unset($SWname);
					break;
				}

				$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '请等待，可能需要一会儿');
				$this->pluginInst->arenas[$SWname]->stop(true);
				foreach ($this->pluginInst->signs as $loc => $name) {
					if ($SWname == $name) {
						$ex = explode(':', $loc);
						if ($this->api->level->loadLevel($ex[0b11])) {

							$block = $this->api->level->get($ex[0b11])->getBlock(new Vector3($ex[0], $ex[1], $ex[0b10]));
							if ($block->getId() == 0x3f || $block->getId() == 0x44)
								$this->api->level->get($ex[0b11])->setBlock((new Vector3($ex[0], $ex[1], $ex[0b10])), BlockAPI::get(AIR));
						}
					}
				}
				$this->pluginInst->setSign($SWname, 0, 0, 0, 'world', true, false);
				unset($this->pluginInst->arenas[$SWname]);

				foreach (scandir($this->pluginInst->dataPath . 'arenas/' . $SWname) as $file) {
					if ($file != '.' && $file != '..' && is_file($this->pluginInst->dataPath . 'arenas/' . $SWname . '/' . $file)) {
						@unlink($this->pluginInst->dataPath . 'arenas/' . $SWname . '/' . $file);
					}
				}
				@rmdir($this->pluginInst->dataPath . 'arenas/' . $SWname);
				$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . 'Arena: ' . FORMAT_DARK_GREEN . $SWname . FORMAT_GREEN . ' Deleted !');
				unset($SWname, $loc, $name, $ex, $block);
				break;


			case 'signdelete':
				/*
					  _                ____         _        _
				 ___ (_)  __ _  _ __  |  _ \   ___ | |  ___ | |_   ___
				/ __|| | / _` || '_ \ | | | | / _ \| | / _ \| __| / _ \
				\__ \| || (_| || | | || |_| ||  __/| ||  __/| |_ |  __/
				|___/|_| \__, ||_| |_||____/  \___||_| \___| \__| \___|
						 |___/

				*/
				if (count($args) != 1) {
					$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '使用: /sw ' . FORMAT_GREEN . 'signdelete [SWname|all]');
					break;
				}

				$SWname = array_shift($args);
				if (!array_key_exists($SWname, $this->pluginInst->arenas)) {
					if ($SWname == 'all') {
						//Deleting SW signs blocks
						foreach ($this->pluginInst->signs as $loc => $name) {
							$ex = explode(':', $loc);
							if ($this->api->level->loadLevel($ex[0b11])) {
								$block = $this->api->level->get($ex[0b11])->getBlock(new Vector3($ex[0], $ex[1], $ex[0b10]));
								if ($block->getId() == 0x3f || $block->getId() == 0x44)
									$this->api->level->get($ex[0b11])->setBlock((new Vector3($ex[0], $ex[1], $ex[0b10])), BlockAPI::get(AIR));
							}
						}
						//Deleting signs from db & array
						$this->pluginInst->setSign($SWname, 0, 0, 0, 'world', true);
						$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '成功删除了所有牌子 !');
						unset($SWname, $loc, $name, $ex, $block);
					} else {
						$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . 'Arena: ' . FORMAT_WHITE . $SWname . FORMAT_RED . ' doesn\'t exist');
						unset($SWname);
					}
					break;
				}
				$this->pluginInst->arenas[$SWname]->stop(true);
				foreach ($this->pluginInst->signs as $loc => $name) {
					if ($SWname == $name) {
						$ex = explode(':', $loc);
						if ($this->api->level->loadLevel($ex[0b11])) {
							$block = $this->api->level->get($ex[0b11])->getBlock(new Vector3($ex[0], $ex[1], $ex[0b10]));
							if ($block->getId() == 0x3f || $block->getId() == 0x44)
								$this->api->level->get($ex[0b11])->setBlock((new Vector3($ex[0], $ex[1], $ex[0b10])), BlockAPI::get(AIR));
						}
					}
				}
				$this->pluginInst->setSign($SWname, 0, 0, 0, 'world', true, false);
				$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_GREEN . '删除了领地的牌子: ' . FORMAT_DARK_GREEN . $SWname);
				unset($SWname, $loc, $name, $ex, $block);
				break;


			default:
				//No option found, usage
				$sender->sendChat(FORMAT_AQUA . '>' . FORMAT_RED . '使用: /sw [create|setspawn|list|delete|signdelete]');
				break;


		endswitch;
		return "You must be a player to execute this command.";
	}
}

