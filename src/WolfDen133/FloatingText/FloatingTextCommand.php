<?php

namespace WolfDen133\FloatingText;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class FloatingTextCommand extends Command implements PluginIdentifiableCommand {

    /* @var Main */
    public $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;

        parent::__construct("ft", "Master floating text command", "/ft add/remove/edit", ["floatingtext", "wft", "wolfiesfloatingtext"]);
        $this->setPermission("wft.master");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if ($sender instanceof Player){
            if ($sender->hasPermission("wft.master") || $sender->isOp()) {
                if (count($args) > 0){
                    switch ($args[0]) {
                        case "add":
                        case "spawn":
                        case "summon":
                        case "new":
                        case "make":
                        case "create":
                        case "c":
                        case "a":
                            if ($sender->hasPermission("wft.add")) {
                                if (count($args) === 1) {
                                    $this->plugin->openCreation($sender);
                                } elseif (count($args) >= 3) {
                                    $ftname = $args[1];
                                    if (is_numeric($args[2])) {
                                        $gap = $args[2];
                                        $gap = $gap / 10;
                                        $text = array_slice($args, 3);
                                    } else {
                                        $gap = 0.3;
                                        $text = array_slice($args, 2);
                                    }
                                    $this->plugin->regText($ftname, implode(" ", $text), $gap, $sender);
                                } else {
                                    $sender->sendMessage(TextFormat::GRAY . "Somethings not quite right, Run: '/ft help' if your stuck");
                                }
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                            break;
                        case "break":
                        case "delete":
                        case "bye":
                        case "remove":
                        case "d":
                        case "r":
                            if ($sender->hasPermission("wft.remove")) {
                                if (count($args) === 1){
                                    $this->plugin->openRemove($sender);
                                } elseif (count($args) === 2) {
                                    $this->plugin->removeText($args[1], $sender);
                                } else {
                                    $sender->sendMessage(TextFormat::GRAY . "Somethings not quite right, Run: '/ft help' if your stuck");
                                }

                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                            break;
                        case "edit":
                        case "e":
                            if ($sender->hasPermission("wft.edit")) {
                                if (count($args) === 1){
                                    $this->plugin->openEditList($sender);
                                } elseif (count($args) > 3) {
                                    if ($args[2] === "gap"){
                                        $gap = (float)$args[3];
                                        $this->plugin->editText($args[1], Main::GAP, $gap, $sender);
                                    } elseif ($args[2] === "text"){
                                        $this->plugin->editText($args[1], Main::TEXT, implode(" ", array_slice($args, 3)), $sender);
                                    }
                                } else {
                                    $sender->sendMessage(TextFormat::GRAY . "Somethings not quite right, Run: '/ft help' if your stuck");
                                }

                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                            break;
                        case "reload":
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
                        case "tph":
                            if ($sender->hasPermission("wft.movehere")) {
                                if (count($args) === 1){
                                    $this->plugin->openMove($sender);
                                } elseif (count($args) === 2){
                                    $this->plugin->moveText($args[1], false, $sender);
                                } else {
                                    $sender->sendMessage(TextFormat::GRAY . "Somethings not quite right, Run: '/ft help' if your stuck");
                                }
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
                                if (count($args) === 1) {
                                    $this->plugin->openMove($sender, true);
                                } elseif (count($args) === 2) {
                                    $this->plugin->moveText($args[1], true, $sender);
                                } else {
                                    $sender->sendMessage(TextFormat::GRAY . "Somethings not quite right, Run: '/ft help' if your stuck");
                                }
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
                        case "help":
                        case "stuck":
                        case "h":
                        case "?":
                            if ($sender->hasPermission("wft.help")) {
                                $this->plugin->openHelp($sender);
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                            break;
                        default:
                            $sender->sendMessage(TextFormat::GRAY . "Somethings not quite right, Run: '/ft help' if your stuck");
                            break;
                    }
                } else {
                    $sender->sendMessage(TextFormat::GRAY . "Hmm, Somethings not quite right, Run: '/ft help' if your stuck");
                }
            } else {
                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
            }
        } else {
            $sender->sendMessage(TextFormat::GRAY . "This command is for players only");
        }
    }
   
    /** 
    * @return Main 
    **/
    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }
}
