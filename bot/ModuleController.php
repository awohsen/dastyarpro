<?php

use Components\Context;

class ModuleController
{
    public function moduleSelector(Context $ctx): void
    {
        $ctx->sendMessage('ماژول مورد علاقه خود را انتخاب کنید:', [
            'reply_to_message_id' => $ctx->getMessage()->getMessageId(),
            'reply_mar kup' => ['inline_keyboard' => [[['text' => 'Ads', 'callback_data' => 'ADS_ADD']]]]
        ]);
    }
}
