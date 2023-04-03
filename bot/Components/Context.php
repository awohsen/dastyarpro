<?php

namespace Components;

use React\MySQL\ConnectionInterface;
use React\Promise\PromiseInterface;
use Zanzara\Telegram\Type\CallbackQuery;
use Zanzara\ZanzaraLogger;
use Zanzara\Telegram\Type\Update;
use Psr\Container\ContainerInterface;

class Context extends \Zanzara\Context
{

    use Database;

    /**
     * @var ZanzaraLogger
     */
    protected ZanzaraLogger $logger;

    function __construct(Update $update, ContainerInterface $container)
    {
        parent::__construct($update, $container);

        $this->logger = $container->get(ZanzaraLogger::class);
        $this->connection = $container->get(ConnectionInterface::class);
    }

    /**
     * @return ZanzaraLogger
     */
    public function log(): ZanzaraLogger
    {
        return $this->logger;
    }

    public function sendOrEditMessage(string $text, array $opt = []): PromiseInterface
    {
        if ($this->getUpdate()->getUpdateType() === CallbackQuery::class) {
            if ($this->getCallbackQuery()->getMessage()->getDate() > (time() - 86400)) {
                return $this->editMessageText($text, $opt);
            }
            if ($this->getCallbackQuery()->getMessage()->getReplyToMessage() && !isset($opt['reply_to_message_id'])) {
                $opt['reply_to_message_id'] = $this->getCallbackQuery()->getMessage()->getReplyToMessage()->getMessageId();
                if (!isset($opt['allow_sending_without_reply'])) $opt['allow_sending_without_reply'] = true;
            }
        }

        return $this->sendMessage($text, $opt);
    }

}
