<?php

namespace TimeRanks;

use _64FF00\PurePerms\PurePerms;
use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use TimeRanks\provider\SQLite3Provider;
use TimeRanks\provider\TimeRanksProvider;

class TimeRanks extends PluginBase{

    /** @var  TimeRanksProvider */
    private $provider = null;
    /** @var  Rank[] */
    private $ranks = [];
    /** @var  PurePerms */
    private $purePerms = null;
    private $defaultRank = null;
    /** @var  \SplFixedArray */
    private $minToRank;

    public function onEnable(){
        if(($pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms")) === null){
            $this->getLogger()->alert("TimeRanks: Dependency PurePerms not found, disabling plugin");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        $this->purePerms = $pp;
        $this->saveDefaultConfig();
        switch($this->getConfig()->get("data-provider", "sqlite3")){
            case "sqlite3":
                $this->provider = new SQLite3Provider($this);
                break;
            default:
                $this->getLogger()->alert("Invalid TimeRanks data provider set in config.yml, disabling plugin");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                return;
        }
        $this->loadRanks();
        uasort($this->ranks, function($a, $b){ /** @var Rank $a */ /** @var Rank $b */
            return $a->getMinutes() <=> $b->getMinutes();
        });

        $minToRank = [];
        $prevRankName = null;
        $prevRankMin = null;
        foreach($this->ranks as $key => $rank){
            if($prevRankName !== null){
                $minToRank = array_merge($minToRank, array_fill_keys(range($prevRankMin, $rank->getMinutes() - 1), $prevRankName));
            }
            $prevRankName = $key;
            $prevRankMin = $rank->getMinutes();
        }
        $minToRank[$prevRankMin] = $prevRankName;

        $this->minToRank = \SplFixedArray::fromArray($minToRank);
        unset($minToRank);
    }

    private function loadRanks(){
        $this->saveResource("ranks.yml");
        $ranks = yaml_parse_file($this->getDataFolder()."ranks.yml");
        foreach($ranks as $name => $data){
            $rank = Rank::fromData($this, $name, $data);
            if($rank !== null){
                $this->ranks[$name] = $rank;
            }
        }
        $default = 0;
        foreach($this->ranks as $rank){
            if($rank->isDefault()){
                ++$default;
            }
        }
        if($default !== 1){
            $this->getLogger()->alert("No/Too many default rank(s) set in ranks.yml, disabling plugin");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }

    public function onDisable(){
        $this->provider->close();
        $this->provider = null;
        $this->ranks = [];
        $this->purePerms = null;
        $this->defaultRank = null;
    }

    public function getPurePerms(){
        return $this->purePerms;
    }

    public function getProvider(){
        return $this->provider;
    }

    /**
     * @return Rank[]
     */
    public function getRanks(){
        return $this->ranks;
    }

    public function getRank($name){
        if(isset($this->ranks[$name])){
            return $this->ranks[$name];
        }
        return null;
    }

    public function getDefaultRank(){
        if($this->defaultRank === null){
            foreach($this->ranks as $rank){
                if($rank->isDefault()){
                    $this->defaultRank = $rank;
                    break;
                }
            }
        }
        return $this->defaultRank;
    }

    public function checkRankUp(Player $player, $before, $after){
        $old = $this->getRankOnMinute($before);
        $new = $this->getRankOnMinute($after);
        if($old !== $new){
            $new->onRankUp($player);
            return true;
        }
        return false;
    }

    public function getRankOnMinute($min){
        return $this->ranks[$this->minToRank[$min >= $this->minToRank->getSize() ? $this->minToRank->getSize() - 1 : $min]];
    }

}
