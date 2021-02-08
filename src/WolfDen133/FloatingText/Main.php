<?php

declare(strict_types=1);

namespace WolfDen133\FloatingText;

use pocketmine\entity\Entity;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\Listener;

use pocketmine\nbt\tag\CompoundTag;

use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\math\Vector3;

use pocketmine\utils\TextFormat;

use WolfDen133\FloatingText\FormAPI\CustomForm;
use WolfDen133\FloatingText\FormAPI\SimpleForm;
use WolfDen133\FloatingText\FormAPI\ModalForm;


class Main extends PluginBase implements Listener{


    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getServer()->getCommandMap()->register("ft", new FloatingTextCommand($this));

        Entity::registerEntity(WFloatingText::class, true);

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
                $text = explode("#", $data[1]);
                $y = $player->getY() + 1 + $data[2]/10;
                foreach ($text as $value){
                    $y = $y - $data[2]/10;
                    $this->createText($data[0], str_replace(["{line}", "&"], ["", "§"], $value), $player, $player->getX(), $y, $player->getZ());
                }
                $player->sendMessage(TextFormat::GREEN . "Created:\n" . TextFormat::RESET . implode("\n", $text) . TextFormat::GREEN . ",\nfloating text with the name: " . TextFormat::RESET . $data[0]);
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
                    break;
            }
            return;
        });
        $form->setTitle("Remove a FloatingText");
        $form->setContent("Below are all the floating texts that are in your current level, click one to edit it.");
        $fts = array();
        foreach ($player->getLevel()->getEntities() as $entity){
            if ($entity instanceof WFloatingText){
                $ftname = $entity->namedtag->getString("FTName");
                if(!isset($fts[$ftname])){
                    $fts[$ftname] = true;
                    $form->addButton($ftname, -1, "", $ftname);
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
                foreach ($text as $value){
                    $y = $y - $data[1]/10;
                    $this->createText($ftname, str_replace("&","§", $value), $player, $x, $y, $z);
                }
                $player->sendMessage(TextFormat::GREEN . "Edited the floating text " . TextFormat::RESET . $ftname . TextFormat::GREEN . " to:\n" . TextFormat::RESET . implode("\n", $text));
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
                    break;
                default:
                    $form = new ModalForm(function (Player $player, bool $cdata = null) use ($data){
                        if ($cdata === null){
                            return;
                        }
                        if($cdata === true){
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
            return true;
        });
        $form->setTitle("Remove a FloatingText");
        $form->setContent("Below are all the floating texts that are in your current level, click one to remove it.");
        $fts = array();
        foreach ($player->getLevel()->getEntities() as $entity){
            if ($entity instanceof WFloatingText){
                $ftname = $entity->namedtag->getString("FTName");
                if(!isset($fts[$ftname])){
                    $fts[$ftname] = true;
                    $form->addButton($ftname, -1, "", $ftname);
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
                        foreach ($player->getLevel()->getEntities() as $entity){
                            if ($entity instanceof WFloatingText) {
                                if ($entity->namedtag->getString("FTName") === $data) {
                                    $y = $y - 0.3;
                                    $entity->teleport(new Vector3($player->getX(), $y, $player->getZ()));
                                }
                            }
                        }
                    }
                break;
            }
            return;
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
                    $form->addButton($ftname, -1, "", $ftname);
                }
            }
        }

        $form->addButton(TextFormat::RED . "Close", 0, "textures/ui/realms_red_x", "close");
        $form->sendToPlayer($player);
        return $form;
    }

    public function createText(string $ftname, string $text, Player $player, $x, $y, $z, $op = false)
    {
        $nbt = $this->makeNBT("WFloatingText", $player, $text, $ftname, new Vector3($x, $y, $z));
        $entity = Entity::createEntity("WFloatingText", $player->getLevel(), $nbt);
        $entity->getDataPropertyManager()->setFloat(Entity::DATA_SCALE, 0);
        $entity->sendData($entity->getViewers());
        if ($op === true){
            foreach ($this->getServer()->getOnlinePlayers() as $player){
                if ($player->isOp()) $entity->spawnTo($player);
            }
        } else {
            $entity->spawnToAll();
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
}
