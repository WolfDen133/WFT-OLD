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
                        case "spawn":
                        case "summon":
                        case "new":
                        case "make":
                            if ($sender->hasPermission("wft.add")) {
                                $this->plugin->openCreation($sender);
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                        break;
                        case "break":
                        case "delete":
                        case "bye":
                        case "remove":
                            if ($sender->hasPermission("wft.remove")) {
                                $this->plugin->openRemove($sender);
                            } else {
                                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
                            }
                        break;
                        case "edit":
                            if ($sender->hasPermission("wft.edit")) {
                                $this->plugin->openEditList($sender);
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
                        default:
                            $sender->sendMessage(TextFormat::GRAY . "Usage: ft {add/remove/edit/tp/tphere}");
                         break;
                    }
                } else {
                    $sender->sendMessage("Usage: ft {add/remove/edit/tp/tphere}");
                }
            } else {
                $sender->sendMessage(TextFormat::RED . "> You do not have permission to use this command!");
            }
        } else {
            $sender->sendMessage("This command is for players only");
        }
    }
}
