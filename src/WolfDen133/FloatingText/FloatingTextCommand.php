<?php

namespace WolfDen133\FloatingText;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class FloatingTextCommand extends Command {

    /* @var Main */
    public $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;

        parent::__construct("ft", "Master floating text command", "/ft add/remove/edit", ["floatingtext"]);
        $this->setPermission("wft.master");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if ($sender instanceof Player){
            if ($sender->hasPermission("wft.master")){
                if (isset($args[0])) {
                    switch ($args[0]) {
                        case "add":
                        case "a":
                        case "spawn":
                        case "summon":
                        case "new":
                        case "make":
                        case "create":
                            if ($sender->hasPermission("wft.add")) {
                                $this->plugin->openCreation($sender);
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                        break;
                        case "break":
                        case "delete":
                        case "r":
                        case "bye":
                        case "remove":
                            if ($sender->hasPermission("wft.remove")) {
                                $this->plugin->openRemove($sender);
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                        break;
                        case "edit":
                        case "e":
                            if ($sender->hasPermission("wft.edit")) {
                                $this->plugin->openEditList($sender);
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                        break;
                        case "reload":
                        case "r":
                            if ($sender->hasPermission("wft.reload")) {
                                $this->plugin->reloadTexts($sender);
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                        break;
                        case "teleporthere":
                        case "tphere":
                        case "movehere":
                        case "bringhere":
                            if ($sender->hasPermission("wft.movehere")) {
                                $this->plugin->openMove($sender);
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                            break;
                        case "teleportto":
                        case "tpto":
                        case "goto":
                        case "teleport":
                        case "tp":
                            if ($sender->hasPermission("wft.moveto")) {
                                $this->plugin->openMove($sender, true);
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                            break;
                        case "name":
                        case "id":
                        case "whois":
                        case "n":
                            if ($sender->hasPermission("wft.name")) {
                                $this->plugin->idlist[$sender->getName()] = true;
                                $sender->sendMessage(TextFormat::GREEN . "Tap on/slightly below a text to get its name");
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                        break;
                        default:
                            $sender->sendMessage(TextFormat::GRAY . "Usage: ft {(a)dd/(r)emove/(e)dit/tp/tphere/(n)ame/(r)eload}");
                            break;
                    }
                } else {
                    $sender->sendMessage("Usage: ft {(a)dd/(r)emove/(e)dit/tp/tphere/(n)ame/(r)eload}");
                }
            } else {
                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
            }
        } else {
            $sender->sendMessage("This command is for players only");
        }
    }
}
