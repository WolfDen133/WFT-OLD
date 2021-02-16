<?php

declare(strict_types=1);

namespace WolfDen133\FloatingText;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
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

use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\ModalForm;



class Main extends PluginBase implements Listener{

    public const TEXT = 0;
    public const GAP = 1;

    /* @var Config */
    private $config;

    /* @var array */
    private $fts = [];

    private $new;

    /* @var array */
    public $idlist = [];

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
            if ($data[1] !== "" and $data[0] !== ""){
                $this->regText($data[0], $data[1], $data[2]/10, $player);
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
                    break;
                default:
                    $this->openEdit($player, $data);
            }
        });
        $form->setTitle("Edit a FloatingText");
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
                $this->editText($ftname, self::TEXT, $data[0], $player);
                $this->editText($ftname, self::GAP, $data[1], $player);
            } else {
                $player->sendMessage(TextFormat::RED . "Incorrect arguments, aborting...");
            }


        });
        $form->setTitle("Edit $ftname");
        $oldname = [];
        $c = 0;
        foreach ($player->getLevel()->getEntities() as $entity){
            if ($entity instanceof WFloatingText){
                if ($entity->namedtag->getString("FTName") === $ftname){
                    $oldname[$c] = $entity->namedtag->getString("CustomName");
                    $c++;
                }
            }
        }
        $placeholder = implode("#", $oldname);
        $form->addInput("Text (use '&' for colours and\n '#' for a new line)", "e.g. Welcome to#server (required)", str_replace("§", "&", $placeholder));
        $form->addSlider("Spacing(in 0. of a block)", 1, 9, 1, 3);
        $form->sendToPlayer($player);
    }

    public function openRemove(Player $player){
        $form = new SimpleForm(function (Player $player, $data = null){
            if ($data === null){
                return;
            }
            switch ($data){
                case "close":
                    break;
                default:
                    $this->removeText($data, $player);
            }
        });
        $form->setTitle("Remove a FloatingText");
        $form->setContent("Below are all the floating texts that are in your current level, click one to remove it.");
        $fts = [];
        foreach ($player->getLevel()->getEntities() as $entity){
            if ($entity instanceof WFloatingText){
                $ftname = $entity->namedtag->getString("FTName");
                if(!isset($fts[$ftname])){
                    $fts[$ftname] = true;
                    if ($ftname !== "") {
                        $form->addButton($ftname, -1, "", $ftname);
                    }
                }
            }
        }

        $form->addButton(TextFormat::RED . "Close", 0, "textures/ui/realms_red_x", "close");
        $form->sendToPlayer($player);
    }

    public function openMove(Player $player, $action = false){
        $form = new SimpleForm(function (Player $player, $data = null) use ($action){
            if ($data === null){
                return;
            }
            switch ($data){
                case "close":
                    break;
                default:
                    $this->moveText($data, $action, $player);
            }
        });
        if ($action) {
            $form->setTitle("Teleport to a FloatingText");
            $form->setContent("Below are all the floating texts that are in your current level, click one to teleport to it.");
        } else {
            $form->setTitle("Move a FloatingText");
            $form->setContent("Below are all the floating texts that are in your current level, click one to move it.");
        }
        $fts = [];
        foreach ($player->getLevel()->getEntities() as $entity){
            if ($entity instanceof WFloatingText){
                $ftname = $entity->namedtag->getString("FTName");
                if(!isset($fts[$ftname])){
                    $fts[$ftname] = true;
                    if ($ftname !== ""){
                        $form->addButton($ftname, -1, "", $ftname);
                    }
                }
            }
        }

        $form->addButton(TextFormat::RED . "Close", 0, "textures/ui/realms_red_x", "close");
        $form->sendToPlayer($player);
    }

    /** API */

    public function moveText (string $ftname, bool $action, Player $player){
        if (!isset($this->fts[$ftname])){
            $player->sendMessage(TextFormat::GRAY . "There is no ft with the name $ftname");
            return;
        }

        if ($action){
            foreach ($player->getLevel()->getEntities() as $entity){
                if ($entity instanceof WFloatingText) {
                    if ($entity->namedtag->getString("FTName") === $ftname) {
                        $player->teleport(new Vector3($entity->getX(), $entity->getY()-1, $entity->getZ()));
                    }
                }
            }
        } else {
            $y = $player->getY() + 1.3;
            $ftconfig = new Config($this->getDataFolder() . "fts/" . $ftname . ".yml", Config::YAML);
            $ftconfig->set('x', $player->getX());
            $ftconfig->set('y', $y);
            $ftconfig->set('z', $player->getZ());
            $ftconfig->set('level', $player->getLevel()->getName());
            $ftconfig->save();
            foreach ($player->getLevel()->getEntities() as $entity){
                if ($entity instanceof WFloatingText) {
                    if ($entity->namedtag->getString("FTName") === $ftname) {
                        $y = $y - 0.3;
                        $entity->teleport(new Vector3($player->getX(), $y, $player->getZ()));
                    }
                }
            }
        }
    }

    public function removeText(string $ftname, Player $sender){
        if (!isset($this->fts[$ftname])) {
            $sender->sendMessage(TextFormat::GRAY . "There is no ft with the name $ftname");
            return;
        }

        $form = new ModalForm(function (Player $player, bool $cdata = null) use ($ftname){
            if ($cdata === null){
                return;
            }
            if($cdata){
                unlink($this->getDataFolder() . "fts/" . $ftname . ".yml");
                foreach ($player->getLevel()->getEntities() as $entity){
                    if ($entity instanceof WFloatingText){
                        if ($entity->namedtag->getString("FTName") === $ftname){
                            $entity->close();
                        }
                    }
                }
                unset($this->fts[$ftname]);
            } else {
                $this->openRemove($player);
            }
        });
        $form->setTitle("Confirm");
        $form->setContent("Are you sure you want to remove '$ftname'?");
        $form->setButton1("Yes");
        $form->setButton2("No");
        $form->sendToPlayer($sender);
    }

    public function editText(string $ftname, int $mode, $input, Player $sender){
        if (!isset($this->fts[$ftname])) {
            $sender->sendMessage(TextFormat::GRAY . "There is no ft with the name $ftname");
            return;
        }

        if ($mode === self::TEXT){
            $text = explode("#", $input);
            $x = null;
            $y = null;
            $z = null;
            $ftconfig = new Config($this->getDataFolder() . "fts/" . $ftname . ".yml", Config::YAML);
            foreach ($sender->getLevel()->getEntities() as $entity){
                if ($entity instanceof WFloatingText){
                    if ($entity->namedtag->getString("FTName") === $ftname){
                        if ($x === null){
                            $ftname = $entity->namedtag->getString("FTName");
                            $x = $entity->getX();
                            $y = $entity->getY() + (float)$ftconfig->get("gap")/10;
                            $z = $entity->getZ();
                        }
                        
                        $entity->close();
                    }
                }
            }

            $ftconfig->set("lines", explode("#", (string)$input));
            $ftconfig->save();
            foreach ($text as $value){
                $y = $y - (float)$ftconfig->get("gap")/10;
                $this->createText($ftname, str_replace("&","§", $value), $sender, $x, $y, $z);
            }
            $sender->sendMessage(TextFormat::GREEN . "Edited the floating text " . TextFormat::RESET . $ftname . TextFormat::RESET . TextFormat::GREEN . " to:\n" . TextFormat::RESET . str_replace("&", "§",implode("\n", $text)));
        } elseif ($mode === self::GAP){
            $x = null;
            $y = null;
            $z = null;
            $ftconfig = new Config($this->getDataFolder() . "fts/" . $ftname . ".yml", Config::YAML);
            foreach ($sender->getLevel()->getEntities() as $entity){
                if ($entity instanceof WFloatingText){
                    if ($entity->namedtag->getString("FTName") === $ftname){
                        if ($x === null){
                            $ftname = $entity->namedtag->getString("FTName");
                            $x = $entity->getX();
                            $y = $entity->getY() + (float)$ftconfig->get("gap")/10;
                            $z = $entity->getZ();
                        }
                        $entity->close();
                    }
                }
            }

            $ftconfig->set("gap", $input);
            $ftconfig->save();
            foreach ((array)$ftconfig->get("lines") as $value){
                $y = $y - (float)$input/10;
                $this->createText($ftname, str_replace("&","§", $value), $sender, $x, $y, $z);
            }
            $sender->sendMessage(TextFormat::GREEN . "Edited the floating text " . TextFormat::RESET . $ftname . TextFormat::RESET . TextFormat::GREEN . " to have the gap of:\n" . TextFormat::RESET . (float)$input);
        } else {
            $sender->sendMessage(TextFormat::GRAY . "Usage: ft edit ({ftname} {name/gap} {value/s})");
        }
    }

    public function regText(string $ftname, string $text, float $gap, Player $sender){
        if (isset($this->fts[$ftname])) {
            $sender->sendMessage(TextFormat::GRAY . "The ft with the name $ftname already exists");
            return;
        }

        $ftconfig = new Config($this->getDataFolder() . "fts/" . $ftname . ".yml", Config::YAML);
        $ftconfig->set("visible", true);
        $ftconfig->set("name", $ftname);
        $ftconfig->set('x', $sender->getX());
        $ftconfig->set('y', $sender->getY() + 1);
        $ftconfig->set('z', $sender->getZ());
        $ftconfig->set('level', $sender->getLevel()->getName());
        $ftconfig->set('lines', explode("#", (string)$text));
        $ftconfig->set("gap", $gap*10);
        $y = $sender->getY() + 1 + $gap;
        $text = explode("#", (string)$text);
        foreach ($text as $value){
            $y = $y - $gap;
            $this->createText($ftname, $value, $sender, $sender->getX(), $y, $sender->getZ());
        }
        $ftconfig->save();
        $this->fts[$ftname] = $text;
        $sender->sendMessage(TextFormat::GREEN . "Created:\n" . TextFormat::RESET . str_replace("&", "§", implode("\n", $text)) . TextFormat::RESET . TextFormat::GREEN . ",\nfloating text with the name: " . TextFormat::RESET . $ftname);
    }

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
        return str_replace(["{max_players}", "{online_players}", "{level}", "{tps}", "{load}", "&"], [$this->getServer()->getMaxPlayers(), count($this->getServer()->getOnlinePlayers()), $floatingText->getLevel()->getName(), $this->getServer()->getTicksPerSecondAverage(), $this->getServer()->getTickUsage(), "§"], $text);
    }

    public function reloadTexts(Player $sender){
        foreach ($this->getServer()->getLevels() as $level){
            foreach ($level->getEntities() as $entity){
                if ($entity instanceof WFloatingText){
                    $entity->close();
                }
            }
        }
        $this->fts = [];
        $directory = new \RecursiveDirectoryIterator($this->getDataFolder() . "fts/");
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $info) {
            $value = new Config($info->getPathname());
            $name = (string)$value->get("name");
            if ($value->get("visible") === true && !isset($this->fts[$name])){
                $x = (float)$value->get("x");
                $y = (float)$value->get("y") + (int)$value->get("gap")/10;
                $z = (float)$value->get("z");
                $level = (string)$value->get("level");
                foreach ((array) $value->get("lines") as $line){
                    $y = $y - (int)$value->get("gap")/10;
                    $this->createText($name, (string)$line, $sender, $x, $y, $z, $level);
                }
                $this->fts[$name] = implode("#", (array)$value->get("lines"));
            }
        }
    }

    public function openHelp (Player $player){
        $form = new SimpleForm(function (Player $player, int $data = null){
            if ($data === null){
                return;
            }
            switch ($data){
                case 0:
                    $form = new SimpleForm(function (Player $player, $data = null){
                        return;
                    });
                    $form->setTitle(TextFormat::BOLD . TextFormat::AQUA . "Add");
                    $form->setContent(TextFormat::ITALIC . TextFormat::AQUA . "There are three ways of creating a floating text,\n\n  1. Form\nSimply execute '/ft add' and it will open a menu\n\n  2. Command\nExecute '/ft add {ftname} {gap} {text}', {ftname} is a Unique identifier for the ft e.g. 'Welcome', {gap} is how much of a gap there is between the lines of the ft, this is in 0. of a block e.g.'3' is 0.3 of a block, {text} is the text you want to be in your ft (you can use the tags at the bottom)\n\n  3. Config\nThe harder method of the three, you create a ft config file in the ft/ directory, make sure to use the proper format, after creating a config file run '/ft reload' to spawn the ft.\n\n\n Tags:\n{tps} - The servers tps\n{load} - The servers load\n{online_players} - The current online players\n{max_players} - The maximum players aloud on your server\n{level} - The level name the ft is in\nMore coming soon...\n");
                    $form->addButton(TextFormat::RED . "Close");
                    $form->sendToPlayer($player);
                    break;
                case 1:
                    $form = new SimpleForm(function (Player $player, $data = null){
                        return;
                    });
                    $form->setTitle(TextFormat::BOLD . TextFormat::AQUA . "Edit");
                    $form->setContent(TextFormat::ITALIC . TextFormat::AQUA . "Like adding there are three methods for editing a ft, \n\n  1. Form\nSimply execute '/ft edit' and it will bring up a menu\n\n  2. Command\nExecute '/ft edit {ftname} {text/gap} {value}', {text} mode you can edit the text of the ft so {value} would be the regular text input, {gap} mode you can edit the gap between the lines so {value} would be a number e.g. 3\n\n  3. Config\nThe config is more sensitive to errors so be careful when editing it, but feel free to. When done editing the config you will have to run '/ft reload' to reload the fts you edited.\n\n'/ft {tp/tphere}' work in similar ways, you can run '/ft {tp/tphere}' to bring up the menu or '/ft {tp/tphere} {ftname}' to bypass the menu.");
                    $form->addButton(TextFormat::RED . "Close");
                    $form->sendToPlayer($player);
                    break;
                case 2:
                    $form = new SimpleForm(function (Player $player, $data = null){
                        return;
                    });
                    $form->setTitle(TextFormat::BOLD . TextFormat::AQUA . "Remove");
                    $form->setContent(TextFormat::ITALIC . TextFormat::AQUA . "Like adding and editing there are three methods of removing a ft, \n\n  1. Form\nSimply execute '/ft remove' and it will open a menu\n\n  2. Command\nExecute '/ft remove {ftname}' and it will be removed,\n\n  3. Config\nIf you want to simply make a ft invisible for some reason, change the visible value in the config for the ft, it wont be deleted just not visible, or delete the .yml file for the ft to remove the whole thing, you will have to run '/ft reload' after editing the config files.");
                    $form->addButton(TextFormat::RED . "Close");
                    $form->sendToPlayer($player);
                    break;
                case 3:
                    break;
            }
        });
        $form->setTitle("Help menu");
        $form->addButton(TextFormat::BOLD . TextFormat::AQUA . "Add");
        $form->addButton(TextFormat::BOLD . TextFormat::AQUA . "Edit");
        $form->addButton(TextFormat::BOLD . TextFormat::AQUA . "Remove");
        $form->addButton(TextFormat::RED . "Close");
        $form->sendToPlayer($player);
    }
    
    /** EVENTS */
    
    public function onDamage(EntityDamageEvent $event){
        if ($event->getEntity() instanceof WFloatingText){
            $event->setCancelled(true);
            if ($event instanceof EntityDamageByEntityEvent) {
                if ($event->getDamager() instanceof Player && isset($this->idlist[$event->getDamager()->getName()])) {
                    $event->getDamager()->sendMessage(TextFormat::GREEN . "The name for this ft is: " . $event->getEntity()->namedtag->getString("FTName"));
                    unset($this->idlist[$event->getDamager()->getName()]);
                }
            }
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
            if ($sender instanceof Player) {
                $this->reloadTexts($sender);
                return true;
            }
        }
        
        return false;
    }

    public function onJoin(PlayerJoinEvent $event){
        if ($this->new === true){
            $this->new = false;
            $directory = new \RecursiveDirectoryIterator($this->getDataFolder() . "fts/");
            $iterator = new \RecursiveIteratorIterator($directory);
            foreach ($iterator as $info) {
                $value = new Config($info->getPathname());
                $name = (string)$value->get("name");
                if ($value->get("visible") === true && !isset($this->fts[$name])){
                    $x = (float)$value->get("x");
                    $y = (float)$value->get("y") + (int)$value->get("gap")/10;
                    $z = (float)$value->get("z");
                    $level = (string)$value->get("level");
                    // $this->getServer()->getLogger()->notice("Found the text $name with the text " . implode("#", $value->get("lines")) . " at $x, $y, $z, in the level $level");
                    foreach ((array) $value->get("lines") as $line){
                        $y = $y - (int)$value->get("gap")/10;
                        $this->createText($name, (string)$line, $event->getPlayer(), $x, $y, $z, $level);
                    }
                    $this->fts[$name] = implode("#", (array)$value->get("lines"));
                }
            }
        }
        $this->reloadTexts($event->getPlayer());
    }
}
