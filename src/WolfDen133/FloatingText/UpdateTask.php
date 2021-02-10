<?php

namespace WolfDen133\FloatingText;

use pocketmine\scheduler\Task;

class UpdateTask extends Task{

    /* @var Main */
    private $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(int $currentTick)
    {
        foreach ($this->plugin->getServer()->getLevels() as $level){
            foreach ($level->getEntities() as $entity){
                if ($entity instanceof WFloatingText){
                    $this->plugin->reloadText($entity);
                }
            }
        }

    }
}