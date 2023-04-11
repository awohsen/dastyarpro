<?php

use Components\Context;
use DI\Container;
use React\EventLoop\LoopInterface;
use React\MySQL\ConnectionInterface;
use React\MySQL\Factory;
use Sections\AdsSection;
use Sections\ChannelsSection;
use Zanzara\Config;
use Zanzara\Zanzara;
use Components\Tools;
use React\Cache\CacheInterface;
use Commands\StartCommand;
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
        $bot->onCommand('ads', AdsSection::class, $privateChat);
        $bot->onCommand('ads {param}', AdsSection::class, $privateChat);

        $bot->onCbQuery(function (Context $ctx) {
            $ex = explode('_', $ctx->getCallbackQuery()->getData(), 2);
            switch ($ex[0]) {
                case 'CHANNEL':
                    ChannelsSection::CallbackDataHandler($ctx, $ex[1]);
                    break;
                case 'ADS':
                    AdsSection::CallbackDataHandler($ctx, $ex[1]);
                    break;
            }
        });

        $bot->onChatMemberUpdated(function (Context $ctx) {
//            var_dump($ctx->getUpdate()->getMyChatMember());
        });

        $bot->onText('لغو', function (Context $ctx) {
            $ctx->endConversation();
            $ctx->sendMessage('☑️', Tools::replyKeyboard(replyKeyboardRemove()));
        });

        $bot->onChatShared(function (Context $ctx) {
            switch ($ctx->getMessage()->getChatShared()->getRequestId()) {
                case crc32('addChannel'):
                    ChannelsSection::newChannel($ctx, $ctx->getMessage()->getChatShared()->getChatId());
            }
        });

        $bot->fallback(function (Context $ctx) {
            if ($ctx->getEffectiveChat()->getType() !== 'private') return;
            if ($ctx->getMessage() && !$ctx->getMessage()->getReplyToMessage()) {
                if ($ctx->getMessage()->isServiceMessage()) return;

                // todo: module selector should reply to the message

                $ctx->sendMessage('ماژول مورد علاقه خود را انتخاب کنید:', [
                    'reply_to_message_id' => $ctx->getMessage()->getMessageId(),
                    'reply_markup' => ['inline_keyboard' => [[['text' => 'Ads', 'callback_data' => 'ADS_ADD']]]]
                ]);
            }

            if ($ctx->getMessage()->getReplyToMessage()) {
                $ctx->getUserDataItem('message_data_' . $ctx->getMessage()->getReplyToMessage()->getMessageId())->then(function ($messageData) use ($ctx) {
                    if (isset($messageData['ad_id'])) {
                        AdsSection::replyHandler($ctx, $messageData['ad_id']);
                    }
                });
            }
        });

        $bot->middleware(function (Context $ctx, $next) {
            $before = microtime(true);

            $next($ctx);
            $result = round((microtime(true) - $before) * 1000, 3);
            $ctx->log()->info('Request ' . $ctx->getUpdate()->getUpdateId() . ' took ' . $result);
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
