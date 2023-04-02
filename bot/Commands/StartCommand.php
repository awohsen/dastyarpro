<?php

namespace Commands;

use Components\Context;

class StartCommand
{

    public function __invoke(Context $ctx): void
    {
        $ctx->endConversation();

        $ctx->sendMessage('welcome =)');
    }

    public function customStart(Context $ctx, $param): void
    {
        $param = explode('_', $param, 2);

        switch ($param[0]) {
            default:
                $this->__invoke($ctx);
        }
    }
}
