<?php

use Components\Context;
use Components\Tools;
use DI\Container;
use React\EventLoop\Loop;
use Zanzara\Config;

class ZanzaraConfig extends Config
{

    function __construct()
    {
        $loop = Loop::get();
        $container = new Container();

        $this->setLoop($loop);
        $this->setContainer($container);
        $this->setContextClass(Context::class);

        $this->setCacheTtl(null);
        $this->setConversationTtl(null);
        $this->setCache(Tools::loadCache());

//        $this->setApiTelegramUrl('http://127.0.0.1:8580');
//        $this->setUpdateMode(self::REACTPHP_WEBHOOK_MODE);
//        $this->setServerUri('127.0.0.1:8501');

        $this->setParseMode(self::PARSE_MODE_HTML);
    }

}

return new ZanzaraConfig();