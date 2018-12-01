<?php
/**
 * Created by PhpStorm.
 * User: Home
 * Date: 2018-12-01
 * Time: 오전 9:29
 */

namespace nlog\MailBox\data;

use nlog\MailBox\Loader;
use pocketmine\inventory\BaseInventory;
use pocketmine\IPlayer;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;

class MailBox extends BaseInventory implements \JsonSerializable {

    public const NAMED_TAG = 'expiration';

    public static function getDataPath(Loader $plugin, IPlayer $player): string {
        return $plugin->getDataFolder() . $player->getName() . ".json";
    }

    /**
     * return true when item is expiration
     * If item doesn't have tag, it will be return $noTag
     *
     * @param Item $item
     * @param bool $noTag
     * @return bool
     */
    public static function isExpiration(Item $item, bool $noTag = true): bool {
        if (($tag = $item->getNamedTagEntry(self::NAMED_TAG)) !== null) {
            return $tag->getValue() <= time();
        }
        return $noTag;
    }

    public static function getRemainingTime(Item $item) {
        if (($tag = $item->getNamedTagEntry(self::NAMED_TAG)) !== null) {
            return $tag->getValue() - time();
        }

        return 0;
    }

    public function getTimeUnit(int $time) {
        $str = '';
        $seconds = \floor($time % 60);

        $minutes = -1;
        $hours = -1;
        $days = -1;
        $month = -1;
        $year = -1;


        if($time >= 60){
            $minutes = \floor(($time % 3600) / 60);
            if($time >= 3600){
                $hours = \floor(($time % (3600 * 24)) / 3600);
                if($time >= 3600 * 24){
                    $days = \floor(($time % (3600 * 24 * 30)) / (3600 * 24));
                    if($time >= 3600 * 24 * 30){ // 한달을 30일로 계산
                        $month = \floor(($time % (3600 * 24 * 30 * 12)) / (3600 * 24 * 30));
                        if($time >= 3600 * 24 * 365){
                            $year = \floor($time / (3600 * 24 * 365));
                            if ($year > 0) {
                                $str .= "{$year}년 ";
                            }
                        }
                        if ($month > 0) {
                            $str .= "{$month}개월 ";
                        }
                    }
                    if ($days > 0) {
                        $str .= "{$days}일 ";
                    }
                }
                if ($hours > 0 && ($year < 1 || $month < 1 || $days < 1)) {
                    $str .= "{$hours}시간 ";
                }
            }
            if ($minutes > 0 && ($year < 1 || $month < 1 || $days < 1)) {
                $str .= "{$minutes}분";
            }
        }

        if ($year < 1 && $month < 1 && $days < 1 && $hours < 1) {
            $str .= " {$seconds}초";
        }

        return $str;
    }


    public function jsonSerialize() {
        return [
                'items' => array_filter(array_map(function ($slot) {
                    return $slot instanceof Item ? (self::isExpiration($slot) ? null : $slot->jsonSerialize()) : null;
                }, $this->getContents(false)))
        ];
    }

    /** @var null|IPlayer */
    private $player = null;

    /** @var null|Loader */
    private $plugin = null;

    public function __construct(IPlayer $player, Loader $plugin, $items = [], int $size = \null, string $title = \null) {
        $new_items = [];
        foreach ($items as $item) {
            if ($item !== null) {
                $item = Item::jsonDeserialize($item);
                if (!self::isExpiration($item)) {
                    $new_items[] = clone $item;
                }
            }
        }

        $this->player = $player;
        $this->plugin = $plugin;

        parent::__construct($new_items, $this->getDefaultSize(), $this->getName());
    }

    /**
     * @return IPlayer
     */
    public function getIPlayer(): IPlayer {
        return $this->player;
    }

    public function getName(?string $name = null): string {
        return "§b§l" . ($name ?? $this->player->getName()) . "님의 메일함";
    }

    public function getDefaultSize(): int {
        return 100;
    }

    public function updateItems(): MailBox {
        $slots = array_filter(array_map(function ($slot) {
            return $slot instanceof Item ? (self::isExpiration($slot) ? null : $slot) : null;
        }, $this->getContents(false)));

        $slots = array_values(array_slice($slots, 0, $this->getDefaultSize()));

        $this->slots = new \SplFixedArray($this->getDefaultSize());
        foreach ($slots as $index => $slot) {
            $this->setItem($index, $slot, false);
        }

        return $this;
    }

    public function save(bool $needUpdate = true) {
        if ($needUpdate) {
            $this->updateItems();
        }
        file_put_contents(self::getDataPath($this->plugin, $this->getIPlayer()), json_encode($this));
    }

    /** @var array */
    private $taskList = [];

    public function sendUI(Player $player) {
        $this->updateItems()->clearTask($player);
        $contents = $this->getContents(false);
        uasort($contents, function ($a, $b) {
            return $a->getNamedTagEntry(self::NAMED_TAG)->getValue() - $b->getNamedTagEntry(self::NAMED_TAG)->getValue();
        });

        $pk = new ModalFormRequestPacket();
        $pk->formId = Loader::FORM_ID;
        $pk->formData = json_encode([
                'type' => 'form',
                'title' => $this->getName($player->getName()),
                'content' => '받고 싶은 물품을 선택해주세요.',
                'buttons' => ($this->taskList[$player->getName()] = array_merge([
                        ['text' => '닫기', 'item' => null]
                ], array_map(function ($slot) {
                    return [
                            'text' => ItemInfo::getItemName($slot->getId(), $slot->getDamage()) . " " . $slot->getCount() . "개\n" .
                                    "기간 : " . self::getTimeUnit(self::getRemainingTime($slot)),
                            'item' => $slot->jsonSerialize()];
                }, $contents)))
        ]);
        var_dump($this->taskList[$player->getName()]);

        return $player->sendDataPacket($pk);
    }

    public function handleUI(Player $player, $formData) {
        if (!$this->hasTask($player)) {
            return;
        }
        $prevData = $this->taskList[$player->getName()];
        $this->clearTask($player);

        $json = json_decode($formData, true);
        if ($json === null) {
            return;
        }

        $result = $prevData[$json];
        if ($result['item'] === null) {
            return;
        }

        $item = Item::jsonDeserialize($result['item']);
        if (!$this->updateItems()->contains($item)) {
            $player->sendMessage(Loader::$prefix . "이미 만료된 아이템을 선택하였습니다.");
            return;
        }

        $t_item = clone $item;
        $t_item->removeNamedTagEntry(self::NAMED_TAG);
        if (!$player->getInventory()->canAddItem($t_item)) {
            $player->sendMessage(Loader::$prefix . "아이템을 받을 수 없는 상태입니다.");
            return;
        }

        $this->removeItem($item);

        $item->removeNamedTagEntry(self::NAMED_TAG);
        $player->getInventory()->addItem($item);
        $player->sendMessage(Loader::$prefix . ItemInfo::getItemName($item->getId(), $item->getDamage()) . " " . $item->getCount() . "개를 받았습니다.");

        $this->save(false);
    }

    public function clearTask(Player $player) {
        if (isset($this->taskList[$player->getName()])) {
            unset($this->taskList[$player->getName()]);
        }
    }

    public function hasTask(Player $player) {
        return isset($this->taskList[$player->getName()]);
    }

}