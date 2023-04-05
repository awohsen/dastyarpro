<?php

namespace Commands;

use Components\Context;

class StartCommand
{

    public function __invoke(Context $ctx): void
    {
        $ctx->endConversation();

        $ctx->sendMessage('welcome =)');

        $ctx->setMyCommands([
            ['command' => 'ads', 'description' => '💎 پنل تبلیغات'],
            ['command' => 'channels', 'description' => '📢 مدیریت کانال ها'],
            ['command' => 'settings', 'description' => '⚙️ تنظیمات ربات'],
        ], ['scope' => ['type' => 'chat', 'chat_id' => $ctx->getEffectiveUser()->getId()]]);

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
