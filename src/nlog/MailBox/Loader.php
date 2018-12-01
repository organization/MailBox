<?php

declare(strict_types=1);

namespace nlog\MailBox;

use nlog\MailBox\command\MailBoxCommand;
use nlog\MailBox\data\MailBox;
use pocketmine\IPlayer;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;


class Loader extends PluginBase {

    public const FORM_ID = 13342124;

    public static $prefix = "§b§l[우편함]§r§7 ";

    /** @var null|Loader */
    private static $instance = \null;

    public static function getInstance(): ?Loader {
        return self::$instance;
    }

    /** @var null|EventListener */
    private $event = \null;

    /** @var array */
    private const SETTING_DEFAULT = [
            'command' => '우편함',
            "command-aliases" => [
                    "mail",
                    "mailbox",
                    "우편"
            ],
            "command-alias-enabled" => true
    ];

    /** @var array */
    private $setting = self::SETTING_DEFAULT;

    /** @var MailBox[] */
    private $data = [];

    protected function onLoad() {
        self::$instance = $this;
    }

    public function onEnable(): void {
        $this->saveResource('setting.json');
        $json = file_get_contents($this->getDataFolder() . 'setting.json');
        $json = json_decode($json, true);
        $this->setting = array_merge(self::SETTING_DEFAULT, $json);

        $this->getServer()->getCommandMap()->register('mail', new MailBoxCommand(
                $this->setting['command'],
                $this,
                $this->setting['command-alias-enabled'] ? array_values($this->setting['command-aliases']) : []
        ));

        $this->getServer()->getPluginManager()->registerEvents($this->event = new EventListener($this), $this);

        $this->getServer()->getLogger()->info(TextFormat::GOLD . '[MailBox]플러그인이 활성화 되었어요');
    }

    /**
     * @return array
     */
    public function getSetting(): array {
        return $this->setting;
    }

    protected function onDisable() {
        $this->save();
    }

    public function getMailBox(IPlayer $player) {
        return $this->data[$player->getName()] = ($this->data[$player->getName()] ?? $this->loadData($player));
    }

    protected function loadData(IPlayer $player) {
        $d = file_exists(MailBox::getDataPath($this, $player)) ? file_get_contents(MailBox::getDataPath($this, $player)) : false;
        $d = json_decode(!$d ? json_encode([]) : $d, true);

        return new MailBox($player, $this, $d['items'] ?? []);
    }

    public function save() {
        foreach ($this->data as $k => $mailBox) {
            $mailBox->save();
        }
    }

}