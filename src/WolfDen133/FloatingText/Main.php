<?php

declare(strict_types=1);

namespace WolfDen133\FloatingText;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\Listener;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\nbt\tag\CompoundTag;

use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\math\Vector3;

use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;

use WolfDen133\FloatingText\FormAPI\CustomForm;
use WolfDen133\FloatingText\FormAPI\SimpleForm;
use WolfDen133\FloatingText\FormAPI\ModalForm;


class Main extends PluginBase implements Listener{

    /* @var Config */
    private $config;

    private $new;

    public function onEnable()
    {

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getServer()->getCommandMap()->register("ft", new FloatingTextCommand($this));

        $this->config = $this->getConfig();

        $this->saveDefaultConfig();



        if(!$this->config->exists("Update interval")){
            mkdir($this->getDataFolder() . "fts/");

            $this->config->set("Update interval", 120);
            $this->config->save();
        }

        $ticks = $this->config->get("Update interval");

        if ($ticks < 10){
            $this->getServer()->getLogger()->notice("The update-interval($ticks) is set to less than 10 seconds, this could be intensive on your server");
        }

        $this->getScheduler()->scheduleRepeatingTask(new UpdateTask($this), $ticks*20);

        $this->getServer()->getLogger()->info("Started task with " . $ticks*20 . " ticks.");

        Entity::registerEntity(WFloatingText::class, true);

        $this->new = true;

    }

    public function onLoad()
    {

    }


    public function onDisable()
    {
        foreach ($this->getServer()->getLevels() as $level){
            foreach ($level->getEntities() as $entity) {
                if ($entity instanceof WFloatingText){
                    if (!$this->getServer()->isLevelLoaded($level->getName())) $this->getServer()->loadLevel($level->getName());
                    $entity->close();
                }
            }
        }
    }

    public function openCreation(Player $player){
        $form = new CustomForm(function (Player $player, array $data = null){
            if ($data === null) {
                return;
            }
            foreach ($player->getLevel()->getEntities() as $entity){
                if ($entity instanceof WFloatingText){
                    if ($entity->namedtag->getString("FTName") === $data[0]){
                        $player->sendMessage(TextFormat::RED . "That floating text already exists");
                        return;
                    }
                }
            }
            if ($data[1] !== "" and $data[0] !== ""){
                $ftconfig = new Config($this->getDataFolder() . "fts/" . $data[0] . ".yml", Config::YAML);
                $ftconfig->set("visible", true);
                $ftconfig->set("name", $data[0]);
                $ftconfig->set('x', $player->getX());
                $ftconfig->set('y', $player->getY() + 1);
                $ftconfig->set('z', $player->getZ());
                $ftconfig->set('level', $player->getLevel()->getName());
                $text = explode("#", (string)$data[1]);
                $ftconfig->set('lines', $text);
                $ftconfig->set("gap", $data[2]);
                $y = $player->getY() + 1 + $data[2]/10;
                foreach ($text as $value){
                    $y = $y - $data[2]/10;
                    $this->createText($data[0], str_replace(["&"], ["§"], $value), $player, $player->getX(), $y, $player->getZ());
                }
                $ftconfig->save();
                $player->sendMessage(TextFormat::GREEN . "Created:\n" . TextFormat::RESET . str_replace("&", "§",implode("\n", $text)) . TextFormat::RESET . TextFormat::GREEN . ",\nfloating text with the name: " . TextFormat::RESET . $data[0]);
            } else {
                $player->sendMessage(TextFormat::RED . "Incorrect arguments, aborting...");
            }
            return;


        });
        $form->setTitle("Create a FloatingText");
        $form->addInput("Unique name", "e.g. hubworld (required)");
        $form->addInput("Text (use & for colours and\n # for a new line)", "e.g. Welcome to#server (required)");
        $form->addSlider("Spacing(in 0. of a block)", 1, 9, 1, 3);
        $form->sendToPlayer($player);
        return $form;
    }

    public function openEditList(Player $player){
        $form = new SimpleForm(function (Player $player, $data = null){
            if ($data === null){
                return;
            }
            switch ($data){
                case "close":
                    return;
                    break;
                default:
                    $this->openEdit($player, $data);
                    return;
                    break;
            }
        });
        $form->setTitle("Remove a FloatingText");
        $form->setContent("Below are all the floating texts that are in your current level, click one to edit it.");
        $fts = array();
        foreach ($player->getLevel()->getEntities() as $entity){
            if ($entity instanceof WFloatingText){
                $ftname = $entity->namedtag->getString("FTName");
                if(!isset($fts[$ftname])){
                    $fts[$ftname] = true;
                    if ($ftname !== "")$form->addButton($ftname, -1, "", $ftname);
                }
            }
        }
        $form->addButton(TextFormat::RED . "Close", 0, "textures/ui/realms_red_x", "close");
        $form->sendToPlayer($player);
        return $form;
    }

    public function openEdit(Player $player, $ftname){
        $form = new CustomForm(function (Player $player, array $data = null) use ($ftname){
            if ($data === null) {
                return;
            }
            if ($data[0] !== ""){
                $text = explode("#", $data[0]);
                $x = null;
                foreach ($player->getLevel()->getEntities() as $entity){
                    if ($entity instanceof WFloatingText){
                        if ($entity->namedtag->getString("FTName") === $ftname){
                            if ($x === null){
                                $ftname = $entity->namedtag->getString("FTName");
                                $x = $entity->getX();
                                $y = $entity->getY() + $data[1]/10;
                                $z = $entity->getZ();
                            }
                            $entity->close();
                        }
                    }
                }
                $ftconfig = new Config($this->getDataFolder() . "fts/" . $ftname . ".yml", Config::YAML);
                $ftconfig->set("lines", $text);
                $ftconfig->set("gap", $data[1]);
                $ftconfig->save();
                foreach ($text as $value){
                    $y = $y - $data[1]/10;
                    $this->createText($ftname, str_replace("&","§", $value), $player, $x, $y, $z);
                }
                $player->sendMessage(TextFormat::GREEN . "Edited the floating text " . TextFormat::RESET . $ftname . TextFormat::RESET . TextFormat::GREEN . " to:\n" . TextFormat::RESET . str_replace("&", "§",implode("\n", $text)));
            } else {
                $player->sendMessage(TextFormat::RED . "Incorrect arguments, aborting...");
            }


        });
        $form->setTitle("Edit $ftname");
        $c = -1;
        foreach ($player->getLevel()->getEntities() as $entity){
            if ($entity instanceof WFloatingText){
                if ($entity->namedtag->getString("FTName") === $ftname){
                    $c = $c+1;
                    $oldname[$c] = $entity->namedtag->getString("CustomName");
                }
            }
        }
        $placeholder = implode("#", $oldname);
        $form->addInput("Text (use '&' for colours and\n '#' for a new line)", "e.g. Welcome to#server (required)", str_replace("§", "&", $placeholder));
        $form->addSlider("Spacing(in 0. of a block)", 1, 9, 1, 3);
        $form->sendToPlayer($player);
        return $form;
    }

    public function openRemove(Player $player){
        $form = new SimpleForm(function (Player $player, $data = null){
            if ($data === null){
                return true;
            }
            switch ($data){
                case "close":
                    return true;
                    break;
                default:
                    $form = new ModalForm(function (Player $player, bool $cdata = null) use ($data){
                        if ($cdata === null){
                            return;
                        }
                        if($cdata === true){
                            unlink($this->getDataFolder() . "fts/" . $data . ".yml");
                            foreach ($player->getLevel()->getEntities() as $entity){
                                if ($entity instanceof WFloatingText){
                                    if ($entity->namedtag->getString("FTName") === $data){
                                        $entity->close();
                                    }
                                }
                            }
                        } elseif ($cdata === false){
                            $this->openRemove($player);
                        }
                    });
                    $form->setTitle("Confirm");
                    $form->setContent("Are you sure you want to remove '$data'?");
                    $form->setButton1("Yes");
                    $form->setButton2("No");
                    $form->sendToPlayer($player);
                    return $form;
            }
        });
        $form->setTitle("Remove a FloatingText");
        $form->setContent("Below are all the floating texts that are in your current level, click one to remove it.");
        $fts = array();
        foreach ($player->getLevel()->getEntities() as $entity){
            if ($entity instanceof WFloatingText){
                $ftname = $entity->namedtag->getString("FTName");
                if(!isset($fts[$ftname])){
                    $fts[$ftname] = true;
                    if ($ftname !== "")$form->addButton($ftname, -1, "", $ftname);
                }
            }
        }

        $form->addButton(TextFormat::RED . "Close", 0, "textures/ui/realms_red_x", "close");
        $form->sendToPlayer($player);
        return $form;
    }

    public function openMove(Player $player, $action = false){
        $form = new SimpleForm(function (Player $player, $data = null) use ($action){
            if ($data === null){
                return;
            }
            switch ($data){
                case "close":
                    return;
                    break;
                default:
                    if ($action === true){
                        foreach ($player->getLevel()->getEntities() as $entity){
                            if ($entity instanceof WFloatingText) {
                                if ($entity->namedtag->getString("FTName") === $data) {
                                    $player->teleport(new Vector3($entity->getX(), $entity->getY()-1, $entity->getZ()));
                                    break;
                                }
                            }
                        }
                    } else {
                        $y = $player->getY() + 1.3;
                        $ftconfig = new Config($this->getDataFolder() . "fts/" . $data . ".yml", Config::YAML);
                        $ftconfig->set('x', $player->getX());
                        $ftconfig->set('y', $y);
                        $ftconfig->set('z', $player->getZ());
                        $ftconfig->set('level', $player->getLevel()->getName());
                        $ftconfig->save();
                        foreach ($player->getLevel()->getEntities() as $entity){
                            if ($entity instanceof WFloatingText) {
                                if ($entity->namedtag->getString("FTName") === $data) {
                                    $y = $y - 0.3;
                                    $entity->teleport(new Vector3($player->getX(), $y, $player->getZ()));

                                }
                            }
                        }
                    }
            }
        });
        if ($action === true) {
            $form->setTitle("Teleport to a FloatingText");
            $form->setContent("Below are all the floating texts that are in your current level, click one to teleport to it.");
        } else {
            $form->setTitle("Move a FloatingText");
            $form->setContent("Below are all the floating texts that are in your current level, click one to move it.");
        }
        $fts = array();
        foreach ($player->getLevel()->getEntities() as $entity){
            if ($entity instanceof WFloatingText){
                $ftname = $entity->namedtag->getString("FTName");
                if(!isset($fts[$ftname])){
                    $fts[$ftname] = true;
                    if ($ftname !== "")$form->addButton($ftname, -1, "", $ftname);
                }
            }
        }

        $form->addButton(TextFormat::RED . "Close", 0, "textures/ui/realms_red_x", "close");
        $form->sendToPlayer($player);
        return $form;
    }

    # api

    private function createText(string $ftname, string $text, Player $player, $x, $y, $z, $level = null)
    {
        $nbt = $this->makeNBT("WFloatingText", $player, $text, $ftname, new Vector3($x, $y, $z));
        /* @var WFloatingText */
            if ($level === null){
                $chunk = $player->getLevel()->getChunkAtPosition(new Vector3($x, $y, $z));
                $player->getLevel()->loadChunk($chunk->getX(), $chunk->getZ());
                $entity = Entity::createEntity("WFloatingText", $player->getLevel(), $nbt);
            } else {
                if (!$this->getServer()->isLevelLoaded($level)) $this->getServer()->loadLevel($level);
                $chunk = $this->getServer()->getLevelByName($level)->getChunkAtPosition(new Vector3($x, $y, $z));
                if (!$this->getServer()->getLevelByName($level)->isChunkLoaded($chunk->getX(), $chunk->getZ())) $this->getServer()->getLevelByName($level)->loadChunk($chunk->getX(), $chunk->getZ());
                $entity = Entity::createEntity("WFloatingText", $this->getServer()->getLevelByName($level), $nbt);
                // $this->getServer()->getLogger()->notice("Tried to create the text $ftname with the text $text at $x, $y, $z, in the level $level, but couldn't");
            }
            $entity->getDataPropertyManager()->setFloat(Entity::DATA_SCALE, 0);
            $entity->sendData($entity->getViewers());
            $entity->spawnToAll();
            $this->reloadText($entity);
            return $entity;
    }

    public function reloadText(WFloatingText $entity){
        if ($entity instanceof WFloatingText){
            if ($entity->namedtag->hasTag("CustomName")) {
                $name = $entity->namedtag->getString("CustomName");
                $name = $this->nameReplace($name, $entity);
                $entity->setNameTag($name);
            }
        } else {
            $this->getServer()->getLogger()->error("The entity class for reloadTexts was not type FloatingText");
        }
    }


    private function makeNBT($type, Player $player, $name, $ftname, Vector3 $pos): CompoundTag
    {
        $nbt = Entity::createBaseNBT(new Vector3($pos->getX(), $pos->getY(), $pos->getZ()), null, 0, 0);
        $nbt->setShort("Health", 1);
        $nbt->setString("CustomName", $name);
        $nbt->setString("FTName", $ftname);
        if ($type === "WFloatingText") {
            $player->saveNBT();
            $inventoryTag = $player->namedtag->getListTag("Inventory");
            assert($inventoryTag !== null);
            $nbt->setTag(clone $inventoryTag);

            $skinTag = $player->namedtag->getCompoundTag("Skin");
            assert($skinTag !== null);
            $nbt->setTag(clone $skinTag);
        }
        return $nbt;
    }

    private function nameReplace(String $text, WFloatingText $floatingText){
        $name = str_replace(["{max_players}", "{online_players}", "{level}", "{tps}", "{load}", "&"], [$this->getServer()->getMaxPlayers(), count($this->getServer()->getOnlinePlayers()), $floatingText->getLevel()->getName(), $this->getServer()->getTicksPerSecondAverage(), $this->getServer()->getTickUsage(), "§"], $text);
        return $name;
    }

    public function reloadTexts(Player $sender){
        foreach ($this->getServer()->getLevels() as $level){
            foreach ($level->getEntities() as $entity){
                if ($entity instanceof WFloatingText){
                    $entity->close();
                }
            }
        }
        $directory = new \RecursiveDirectoryIterator($this->getDataFolder() . "fts/");
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $info) {
            $value = new Config($info->getPathname());
            if ($value->get("visible") === true){
                $x = (int)$value->get("x");
                $y = (int)$value->get("y") + (int)$value->get("gap")/10;
                $z = (int)$value->get("z");
                $level = (string)$value->get("level");
                $name = (string)$value->get("name");
                foreach ((array) $value->get("lines") as $line){
                    $y = $y - (int)$value->get("gap")/10;
                    $this->createText($name, (string)$line, $sender, $x, $y, $z, $level);
                }
            }
        }
    }

    # events

    public function onDamage(EntityDamageEvent $event){
        if ($event->getEntity() instanceof WFloatingText){
            $event->setCancelled(true);
        }
    }

    public function onEntityMotion(EntityMotionEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof WFloatingText) {
            $event->setCancelled(true);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command->getName() === "reload"){
            if ($sender instanceof Player) $this->reloadTexts($sender);
        }
    }

    public function onJoin(PlayerJoinEvent $event){
        if ($this->new === true){
            $this->new = false;
            $directory = new \RecursiveDirectoryIterator($this->getDataFolder() . "fts/");
            $iterator = new \RecursiveIteratorIterator($directory);
            foreach ($iterator as $info) {
                $value = new Config($info->getPathname());
                $level = (string)$value->get("level");
                if ($value->get("visible") === true){
                    $x = (int)$value->get("x");
                    $y = (int)$value->get("y") + (int)$value->get("gap")/10;
                    $z = (int)$value->get("z");
                    $name = (string)$value->get("name");
                    // $this->getServer()->getLogger()->notice("Found the text $name with the text " . implode("#", $value->get("lines")) . " at $x, $y, $z, in the level $level");
                    foreach ((array) $value->get("lines") as $line){
                        $y = $y - (int)$value->get("gap")/10;
                        $this->createText($name, (string)$line, $event->getPlayer(), $x, $y, $z, $level);

                    }
                }
            }
        }
    }
}
