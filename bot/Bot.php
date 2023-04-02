<?php

use DI\Container;
use React\EventLoop\LoopInterface;
use React\MySQL\ConnectionInterface;
use React\MySQL\Factory;
use Sections\ChannelsSection;
use Zanzara\Config;
use Zanzara\Zanzara;
use Components\Tools;
use React\Cache\CacheInterface;
use Commands\StartCommand;
use Zanzara\ZanzaraLogger;
use function Components\replyKeyboardRemove;

class Bot
{
    protected Zanzara $zanzara;

    public function __construct(Config $zanzaraConfig)
    {
        $this->zanzara = new Zanzara($_ENV['BOT_TOKEN'], $zanzaraConfig);
        $log = $zanzaraConfig->getLogger();

        $log->info('Setting up listeners...');
        $this->setupListeners($this->zanzara);

        $log->info('Setting up database initial connection...');
        $this->setupDatabase($this->zanzara->getLoop(), $this->zanzara->getContainer());

        $log->info('Setting up cache save loop...');
        $this->zanzara->getLoop()->addPeriodicTimer(10, function () {
            Tools::saveCache($this->zanzara->getContainer()->get(CacheInterface::class));
        });
    }

    private function setupListeners(Zanzara $bot): void
    {
        $privateChat = ['chat_type' => 'private'];

        $bot->onCommand('start', StartCommand::class, $privateChat);
        $bot->onCommand('start {param}', [StartCommand::class, 'customStart'], $privateChat);
        $bot->onCommand('channels', ChannelsSection::class, $privateChat);
        $bot->onCommand('channels {param}', ChannelsSection::class, $privateChat);
        $bot->onCommand('add_channel', [ChannelsSection::class, 'newChannel'], $privateChat);
        $bot->onCbQueryData(['new_channel'], [ChannelsSection::class, 'newChannel']);

        $bot->onCbQuery(function (\Components\Context $ctx){
            $ex = explode('_', $ctx->getCallbackQuery()->getData(), 2);
            switch ($ex[0]){
                case 'CHANNEL':
                    ChannelsSection::CallbackDataHandler($ctx, $ex[1]);
            }
        });

        $bot->onChatMemberUpdated(function (\Components\Context $ctx) {
            var_dump($ctx->getUpdate()->getMyChatMember());
        });

        $bot->onText('لغو', function (\Components\Context $ctx) {
            $ctx->endConversation();
            $ctx->sendMessage('☑️', Tools::replyKeyboard(replyKeyboardRemove()));
        });

        $bot->onChatShared(function (\Components\Context $ctx) {
                switch ($ctx->getMessage()->getChatShared()->getRequestId()) {
                    case crc32('addChannel'):
                        ChannelsSection::newChannel($ctx, $ctx->getMessage()->getChatShared()->getChatId());
                }
        });
    }

    private function setupDatabase(LoopInterface $loop, Container $container): void
    {
        $uri =
            rawurlencode($_ENV["MYSQL_USER"]) . ':' .
            rawurlencode($_ENV["MYSQL_PASSWORD"]) . '@' .
            $_ENV["MYSQL_HOST"] . '/' .
            $_ENV["MYSQL_DATABASE"];

        $container->set(
            ConnectionInterface::class,
            (new Factory($loop))->createLazyConnection($uri)
        );

    }

    public function run(): void
    {
        $this->zanzara->run();
    }
}