<?php

declare(strict_types=1);

namespace AndreasHGK\SellAll;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use onebone\economyapi\EconomyAPI;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class SellAll extends PluginBase{

    const CFGVERSION = 1.3;
    
    private static $instance;

    public $cfg;
    public $msg;
    public $msgfile;
    public $setting;
    public $settingfile;

	public function onEnable() : void{
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->cfg = $this->getConfig()->getAll();
        $this->saveResource("messages.yml");
        $this->saveResource("settings.yml");
        $this->msgfile = new Config($this->getDataFolder() . "messages.yml", Config::YAML, []);
        $this->msg = $this->msgfile->getAll();
        $this->settingfile = new Config($this->getDataFolder() . "settings.yml", Config::YAML, []);
        $this->setting = $this->settingfile->getAll();
        if(!isset($this->cfg["cfgversion"])){
            $this->getLogger()->critical("config version outdated! please regenerate your config or this plugin might not work correctly.");
        }elseif($this->cfg["cfgversion"] != self::CFGVERSION){
            $this->getLogger()->critical("config version outdated! please regenerate your config or this plugin might not work correctly.");
        }
        if(!isset($this->msg["cfgversion"])){
            $this->getLogger()->critical("messages version outdated! please regenerate your messages file or this plugin might not work correctly.");
        }elseif($this->msg["cfgversion"] != self::CFGVERSION){
            $this->getLogger()->critical("messages version outdated! please regenerate messages file config or this plugin might not work correctly.");
		}
		if(!isset($this->setting["cfgversion"])){
            $this->getLogger()->critical("settings version outdated! please regenerate your settings file or this plugin might not work correctly.");
        }elseif($this->setting["cfgversion"] != self::CFGVERSION){
            $this->getLogger()->critical("settings version outdated! please regenerate settings file config or this plugin might not work correctly.");
        }
        $this->getLogger()->info(TextFormat::RED . "Plugin Enable, Please make sure the economy provider in settings.yml is correct!");
	}
	
	public static function getInstance(){
        return self::$instance;
    }

	public function onCommand(CommandSender $sender, Command $command, String $label, Array $args) : bool{
        if(!$sender instanceof Player && !isset($args[0]) && $args[0] === "reload"){
            $sender->sendMessage(TextFormat::colorize("&cPlease execute this command in-game"));
            return true;
        }
		switch($command->getName()){
			case "vender":
				if(isset($args[0])){
				    switch(strtolower($args[0])){
                        case "mao":
                            $item = $sender->getInventory()->getItemInHand();
                            if(isset($this->cfg[$item->getId().":".$item->getMeta()])){
                                $price = $this->cfg[$item->getId().":".$item->getMeta()];
                                $count = $item->getCount();
                                $totalprice = $price * $count;
                                $this->addMoney($sender->getName(), (int)$totalprice);
                                $item->setCount($item->getCount() - (int)$count);
                                $sender->getInventory()->setItemInHand($item);
                                $sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell"], array(
                                    "QUANTIA" => (string)$count,
                                    "NOME DO ITEM" => $item->getName(),
                                    "DINHEIRO" => (string)$totalprice))));
                                return true;
                            }elseif(isset($this->cfg[$item->getId()])){
                                $price = $this->cfg[$item->getId()];
                                $count = $item->getCount();
                                $totalprice = $price * $count;
                                $this->addMoney($sender->getName(), (int)$totalprice);
                                $item->setCount($item->getCount() - (int)$count);
                                $sender->getInventory()->setItemInHand($item);
                                $sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell"], array(
                                    "QUANTIA" => (string)$count,
                                    "NOME DO ITEM" => $item->getName(),
                                    "DINHEIRO" => (string)$totalprice))));
                                return true;
                            }
                            $sender->sendMessage(TextFormat::colorize($this->msg["error.not-found"]));
                            return true;
                            break;

                        case "tudo":
                            $item = $sender->getInventory()->getItemInHand();
                            $inventory = $sender->getInventory();
                            $contents = $inventory->getContents();
                            if(isset($this->cfg[$item->getId().":".$item->getMeta()])){
                                $price = $this->cfg[$item->getId().":".$item->getMeta()];
                                $count = 0;
                                foreach($contents as $slot){
                                    if($slot->getId() == $item->getId()){
                                        $count = $count + $slot->getCount();
                                        $inventory->remove($slot);
                                    }
                                }
                                $totalprice = $count * $price;
                                $this->addMoney($sender->getName(), (int)$totalprice);
                                $sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell"], array(
                                    "QUANTIA" => (string)$count,
                                    "NOME DO ITEM" => $item->getName(),
                                    "DINHEIRO" => (string)$totalprice))));
                                return true;
                            }elseif(isset($this->cfg[$item->getId()])){
                                $price = $this->cfg[$item->getId()];
                                $count = 0;
                                foreach($contents as $slot){
                                    if($slot->getId() == $item->getId()){
                                        $count = $count + $slot->getCount();
                                        $inventory->remove($slot);
                                    }
                                }
                                $totalprice = $count * $price;
                                $this->addMoney($sender->getName(), (int)$totalprice);
                                $sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell"], array(
                                    "QUANTIA" => (string)$count,
                                    "NOME DO ITEM" => $item->getName(),
                                    "DINHEIRO" => (string)$totalprice))));
                                return true;
                            }
                            $sender->sendMessage(TextFormat::colorize($this->msg["error.not-found"]));
                            return true;
                            break;

						case "inv":
                        case "inventario":
                            $inv = $sender->getInventory()->getContents();
                            $revenue = 0;
                            foreach($inv as $item){
                                if(isset($this->cfg[$item->getId().":".$item->getMeta()])){
                                    $revenue = $revenue + ($item->getCount() * $this->cfg[$item->getId().":".$item->getMeta()]);
                                    $sender->getInventory()->remove($item);
                                }elseif(isset($this->cfg[$item->getId()])){
                                    $revenue = $revenue + ($item->getCount() * $this->cfg[$item->getId()]);
                                    $sender->getInventory()->remove($item);
                                }
                            }
                            if($revenue <= 0){
                                $sender->sendMessage(TextFormat::colorize($this->msg["error.no.sellables"]));
                                return true;
                            }
                            $this->addMoney($sender->getName(), (int)$revenue);
                            $sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell.inventory"], array(
                                "DINHEIRO" => (string)$revenue))));
                            return true;
                            break;

                        case "reload":
                            if($sender->hasPermission("sellall.reload")){
                                $this->reloadConfig();
                                $this->cfg = $this->getConfig()->getAll();
                                $this->msgfile = new Config($this->getDataFolder() . "messages.yml", Config::YAML, []);
                                $this->msg = $this->msgfile->getAll();
                                $this->settingfile = new Config($this->getDataFolder() . "settings.yml", Config::YAML, []);
                                $this->setting = $this->settingfile->getAll();
                                if(!isset($this->cfg["cfgversion"])){
                                    $this->getLogger()->critical("config version outdated! please regenerate your config or this plugin might not work correctly.");
                                }elseif($this->cfg["cfgversion"] != self::CFGVERSION){
                                    $this->getLogger()->critical("config version outdated! please regenerate your config or this plugin might not work correctly.");
                                }
                                if(!isset($this->msg["cfgversion"])){
                                    $this->getLogger()->critical("messages version outdated! please regenerate your messages file or this plugin might not work correctly.");
                                }elseif($this->msg["cfgversion"] != self::CFGVERSION){
                                    $this->getLogger()->critical("messages version outdated! please regenerate messages file config or this plugin might not work correctly.");
                                }
                                if(!isset($this->setting["cfgversion"])){
                                    $this->getLogger()->critical("settings version outdated! please regenerate your settings file or this plugin might not work correctly.");
                                }elseif($this->setting["cfgversion"] != self::CFGVERSION){
                                    $this->getLogger()->critical("settings version outdated! please regenerate settings file config or this plugin might not work correctly.");
                                }
                                $sender->sendMessage(TextFormat::colorize($this->msg["reload"]));
                            }else{
                                $sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["error.argument"], array(
                                    "ARGS" => $this->listArguments()))));
                                return true;
                            }
                            return true;
                            break;

                        default:
                            if(array_key_exists($args[0], $this->cfg["groups"])){
                                $group = $this->cfg["groups"][$args[0]];

                                $inv = $sender->getInventory()->getContents();
                                $revenue = 0;
                                foreach($inv as $item){
                                    if(isset($this->cfg[$item->getId()])){
                                        if(in_array($item->getId(), $group["items"]) || in_array($item->getName(), $group["items"])){
                                            if(isset($this->cfg[$item->getId().":".$item->getMeta()])){
                                                $revenue = $revenue + ($item->getCount() * $this->cfg[$item->getId().":".$item->getMeta()]);
                                                $sender->getInventory()->remove($item);
                                            }elseif(isset($this->cfg[$item->getID()])){
                                                $revenue = $revenue + ($item->getCount() * $this->cfg[$item->getId()]);
                                                $sender->getInventory()->remove($item);
                                            }
                                        }
                                    }
                                }
                                if($revenue <= 0){
                                    $sender->sendMessage(TextFormat::colorize($group["failed"]));
                                    return true;
                                }
                                $this->addMoney($sender->getName(), (int)$revenue);
                                $sender->sendMessage(TextFormat::colorize($this->replaceVars($group["successo"], array(
                                    "DINHEIRO" => (string)$revenue))));
                                return true;
                            }
                            $sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["error.argument"], array(
                                "ARGS" => $this->listArguments()))));
                            return true;

                    }
                }
                $sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["error.argument"], array(
                    "ARGS" => $this->listArguments()))));
				return true;
			default:
				return false;
		}
	}
	
	public function addMoney($player, $amount){
		if($this->setting["economy.provider"] === "EconomyAPI"){
			EconomyAPI::getInstance()->addMoney($player, $amount);
		} elseif($this->setting["economy.provider"] === "BedrockEconomy"){
			BedrockEconomyAPI::getInstance()->addToPlayerBalance($player, (int) ceil($amount));
		}
	}
	
	public function replaceVars($str, array $vars) : string{
        foreach($vars as $key => $value){
            $str = str_replace("{" . $key . "}", $value, $str);
        }
        return $str;
    }
	
	public function getSellPrice(Item $item) : ?float {
        return $this->cfg[$item->getId().":".$item->getMeta()] ?? $this->cfg[$item->getId()] ?? null;
    }

    public function isSellable(Item $item) : bool{
        return $this->getSellPrice($item) !== null ? true : false;
    }

	public function listArguments() : string{
	    $seperator = $this->msg["separator"];
	    $args = "mao".$seperator."tudo".$seperator."inv";
	    foreach($this->cfg["groups"] as $name => $group){
	        $args = $args.$seperator.$name;
        }
        return $args;
    }

}