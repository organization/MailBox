<?php
/**
 * Created by PhpStorm.
 * User: Home
 * Date: 2018-12-01
 * Time: 오후 2:59
 */

namespace nlog\MailBox\command;

use nlog\MailBox\Loader;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;

class MailBoxCommand extends PluginCommand {

    public function __construct(string $name, Loader $owner, array $aliases = []) {
        parent::__construct($name, $owner);
        $this->setAliases($aliases);
        $this->setDescription("우편함을 여는 명령어입니다.");
        $this->setUsage("/" . $name);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if (!$sender instanceof Player) {
            $sender->sendMessage(Loader::$prefix . "인게임에서 실행해주세요.");
            return true;
        }
        $this->getPlugin()->getMailBox($sender)->updateItems()->sendUI($sender);
        return true;
    }

}