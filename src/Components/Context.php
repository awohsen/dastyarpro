<?php

namespace Components;

use Zanzara\ZanzaraLogger;
use Zanzara\Telegram\Type\Update;
use Psr\Container\ContainerInterface;

class Context extends \Zanzara\Context
{

    /**
     * @var ZanzaraLogger
     */
    protected ZanzaraLogger $logger;

    function __construct(Update $update, ContainerInterface $container)
    {
        parent::__construct($update, $container);

        $this->logger = $container->get(ZanzaraLogger::class);
    }

    /**
     * @return ZanzaraLogger
     */
    public function log(): ZanzaraLogger
    {
        return $this->logger;
    }

}
