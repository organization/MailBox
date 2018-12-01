<?php
/**
 * Created by PhpStorm.
 * User: Home
 * Date: 2018-11-18
 * Time: 오후 3:38
 */

namespace nlog\MailBox;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

class EventListener implements Listener {

    /** @var Loader */
    private $plugin;

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $event) {
        $mailBox = $this->plugin->getMailBox($event->getPlayer())->updateItems();

        if (count($mailBox->getContents(false)) > 0) {
            $event->getPlayer()->sendMessage(Loader::$prefix . "{$event->getPlayer()->getName()}님, 우편함에 미수령한 " .
                    count($mailBox->getContents(false)) . "개의 아이템들이 있습니다.");
            $event->getPlayer()->sendMessage(Loader::$prefix . "명령어 '/" . $this->plugin->getSetting()['command'] . "' 을(를) 사용하여 우편함을 확인해주세요. ");
        }
    }

    public function onModalFormResponse(DataPacketReceiveEvent $event) {
        $pk = $event->getPacket();
        if ($pk instanceof ModalFormResponsePacket) {
            if ($pk->formId === Loader::FORM_ID) {
                $this->plugin->getMailBox($event->getPlayer())->handleUI($event->getPlayer(), $pk->formData);
            }
        }
    }

}