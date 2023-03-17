<?php

use Zanzara\Zanzara;
use Components\Tools;
use React\Cache\CacheInterface;
use Commands\StartCommand;

class Bot
{
    protected Zanzara $zanzara;

    public function __construct(Zanzara $bot)
    {
        $this->zanzara = $bot;

        $this->setupListeners();

        $bot->getLoop()->addPeriodicTimer(10, function () use ($bot) {
            Tools::saveCache($bot->getContainer()->get(CacheInterface::class));
        });
    }

    private function setupListeners(): void
    {
        $privateChat = ['chat_type' => 'private'];

        $this->zanzara->onCommand('start', StartCommand::class, $privateChat);
        $this->zanzara->onCommand('start {param}', [StartCommand::class, 'customStart'], $privateChat);
    }

    public function run(): void
    {
        $this->zanzara->run();
    }
}