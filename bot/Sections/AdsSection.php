<?php

namespace Sections;

use Components\Context;
use Components\Tools;
use React\MySQL\QueryResult;
use Zanzara\Telegram\Type\CallbackQuery;
use Zanzara\Telegram\Type\Message;
use Zanzara\Telegram\Type\MessageId;
use function Components\ib;
use function React\Async\await;
use function React\Async\coroutine;
use function React\Promise\all;

class AdsSection
{
    const NAMES = [
        'darya' => 'دریا',
        'baran' => 'باران',
        'berkeh' => 'برکه',
        'nasim' => 'نسیم',
        'asal' => 'عسل',
        'gandom' => 'گندم',
        'sahra' => 'سحرا',
        'nahal' => 'نهال',
        'khorshid' => 'خورشید',
        'sadaf' => 'صدف',
        'sahel' => 'ساحل',
        'setareh' => 'ستاره',
        'bahar' => 'بهار',
        'melorin' => 'ملورین',
        'toranj' => 'ترنج',
        'aseman' => 'آسمان',
        'nilofar' => 'نیلوفر',
        'laleh' => 'لاله',
        'ladan' => 'لادن',
        'yas' => 'یاس',
        'atash' => 'آتش',
        'toofan' => 'توفان',
        'alborz' => 'البرز',
        'shahab' => 'شهاب',
        'sepand' => 'سپند',
        'davin' => 'داوین',
        'sahanad' => 'سهند',
    ];

    public function __invoke(Context $ctx, $param = null): void
    {
        if ($param) {
            if ($ctx->getMessage()->getReplyToMessage() && count(explode(' ', $param)) === 1) {
                $messageData = await($ctx->getUserDataItem('message_data_' . $ctx->getMessage()->getReplyToMessage()->getMessageId()));
                if (isset($messageData['ad_id'])){
                    $param .= ' '. $messageData['ad_id'];
                    $ctx->getUpdate()->setUpdateType(Message::class);
                }
            }

            $param = explode(' ', $param, 2);
            switch ($param[0]) {
                case 'start':
                    if (isset($param[1])) {
                        self::startAd($ctx, $param[1]);
                    }
                    break;
                case 'stop':
                    if (isset($param[1])) {
                        self::stopAd($ctx, $param[1]);
                    }
                    break;
                case 'edit':
                    if (isset($param[1])) {
                        self::editAd($ctx, $param[1]);
                    }
                    break;
                case 'reply':
                    if (isset($param[1])) {
                        self::replyAd($ctx, $param[1]);
                    }
            }
        }
    }

    public static function CallbackDataHandler(Context $ctx, $param): void
    {
        $param = explode('_', $param, 2);
        switch ($param[0]) {
            case 'ADD':
                self::newAd($ctx);
                break;
            case 'SELECT':
            case 'DESELECT':
                self::receiveChannels($ctx);
                break;
            case 'SAVE':
                self::saveAd($ctx);
                break;
            case 'START':
                if (isset($param[1])) {
                    self::startAd($ctx, $param[1]);
                }
                break;
            case 'STOP':
                if (isset($param[1])) {
                    self::stopAd($ctx, $param[1]);
                }
                break;
            case 'SCHEDULE':
                if (isset($param[1])){
                    $ctx->answerCallbackQuery();
                    $ctx->sendMessage("⏲ <b>برای شروع و توقف تبلیغات به صورت <a href='https://t.me/TelegramTipsFA/227'>زمان‌بندی شده</a></b> می‌تونید دستورات زیر رو کپی کنید و توی بخش ارسال زمان‌دار تلگرام برای زمان دلخواه تنظیم کنید!

<b>🚀 شروع:</b>
<code>/ads start {$param[1]}</code>

<b>♻️ توقف:</b>
<code>/ads stop {$param[1]}</code>
‌");
                }
                break;
            case 'REPLY':
                $ctx->answerCallbackQuery(['text' => '⤴️ برای ریپلی زدن به تبلیغ، کافیه به همون پیام تبلیغ که برای ربات ارسال کردید هر چقدر لازم بود ریپلی بزنید!
👌ربات خودکار در همه کانال ها ریپلی زده و با توقف تبلیغ، همگی رو حذف میکنه.', 'show_alert' => true]);
                break;
            case 'EDIT':
                if (isset($param[1])){
                    $ctx->answerCallbackQuery();
                    $ctx->sendMessage("📝 اگر پست تبلیغ رو دوست ندارین یا اشتباهی هست، جدیدش رو ارسال کنید و بعد دستور زیر رو کپی کنید و بهش ریپلی بزنید:

<code>/ads edit {$param[1]}</code>

🥱 اگه همین براتون سخته؛ خب یه تبلیغ جدید بسازین!
‌");
                }
        }
    }

    public static function replyHandler(Context $ctx, $param): void
    {
        AdsSection::replyAd($ctx, $param);
    }

    public static function newAd(Context $ctx): void
    {
        // todo: some checks for if the guy has too much ads or not or anything like this
        self::receiveChannels($ctx);
    }

    public static function receiveChannels(Context $ctx): void
    {
        // todo: there needs to be a security check where a bad client sends id of another existing channel in bot
        coroutine(function () use ($ctx) {
            try {
                $channels = yield $ctx->getUserDataItem('channels');
                if (!(is_array($channels) && count($channels) >= 1)) {
                    $channels = (yield $ctx->getUserChannels())->resultRows;
                    if (!(is_array($channels) && count($channels) >= 1)) {
                        $ctx->sendOrEditMessage('💤 لیست کانال های شما خالیست!

💡 شما بدون هیچ کانالی نمی‌تونید هیچ تبلیغی بذارین!

➕ با دکمه زیر کانال های جدید اضافه کنید.',
                            Tools::replyInlineKeyboard(['inline_keyboard' => [[ib('➕', 'CHANNEL_ADD')]]])
                        );
                        return;
                    }
                    $ctx->setUserDataItem('channels', $channels, 60);
                }

                $show = [];
                foreach ($channels as $channel) {
                    if (isset($channel['display_name']) && isset($channel['channel_id'])) {
                        if (isset(self::getSelectedChannels($ctx)[$channel['channel_id']])) {
                            $show['✔️' . $channel['display_name']] = 'ADS_DESELECT_' . $channel['channel_id'];
                        } else {
                            $show[$channel['display_name']] = 'ADS_SELECT_' . $channel['channel_id'];
                        }
                    }
                }

                $keyboard = Tools::BuildInlineKeyboard(array_keys($show), array_values($show), 2);
                $keyboard[] = [ib('☑️', 'ADS_SAVE')];

                $ctx->sendOrEditMessage('💡برای انتخاب کانال روی اسم آن کلیک کنید...

➕با دکمه زیر چنل های خود را اضافه کنید.',
                    Tools::replyKeyboard(['inline_keyboard' => $keyboard]));

            } catch (\Exception $e) {
                $ctx->sendMessage('👾 خطایی در دریافت لیست کانال های شما رخ داد!');
                print_r($e);
                $ctx->log()->error($e->getMessage(), ['code' => $e->getCode(), 'trace' => $e->getTraceAsString()]);
            }
        });

    }

    private static function getSelectedChannels(Context $ctx): array
    {
        if (!$ctx->getCallbackQuery()) return [];

        $selected = [];
        foreach ($ctx->getCallbackQuery()->getMessage()->getReplyMarkup()->getInlineKeyboard() as $keyboardLines) {
            foreach ($keyboardLines as $keyboardButton) {
                if (str_starts_with($keyboardButton->getCallbackData(), 'ADS_DESELECT_')) {
                    $selected[substr($keyboardButton->getCallbackData(), 13)] = [];
                }
            }
        }

        if (str_starts_with($ctx->getCallbackQuery()->getData(), 'ADS_SELECT_')) {
            $selected[substr($ctx->getCallbackQuery()->getData(), 11)] = [];
        } elseif (str_starts_with($ctx->getCallbackQuery()->getData(), 'ADS_DESELECT_')) {
            unset($selected[substr($ctx->getCallbackQuery()->getData(), 13)]);
        }

        return $selected;
    }

    public static function saveAd(Context $ctx): void
    {
        coroutine(function () use ($ctx) {
            if (!$ctx->getCallbackQuery()->getMessage()->getReplyToMessage()) {
                yield $ctx->sendMessage('😵');
                $ctx->sendMessage('💬 پیام مورد نظر رو گم کردیم! میشه دوباره تبلیغ رو بسازین؟‌');
                return;
            }

            $destinations = self::getSelectedChannels($ctx);
            if (count($destinations) < 1) {
                $ctx->answerCallbackQuery(['text' => '⚠️ حداقل یکی را انتخاب کنید!']);
                return;
            }

            // todo: implement try-catch blocks
            /** @var MessageId $adMessage */
            $adMessage = yield $ctx->copyMessage(
                $_ENV['ADS_CHANNEL'], $ctx->getEffectiveChat()->getId(), $ctx->getCallbackQuery()->getMessage()->getReplyToMessage()->getMessageId()
            );

            $adDisplayName = array_rand(self::NAMES);

            // todo: check if whether some record exist with this $ad_id
            try {
                /** @var QueryResult $result */
                yield $ctx->createUserAd($adMessage->getMessageId(), $destinations, ($ad_id = rand(111111111, 999999999)), null, $adDisplayName);
            } catch (Exception|\Exception $err) {
                $ctx->log()->error($err->getMessage());
            }
            $ctx->setUserDataItem('message_data_' . $ctx->getCallbackQuery()->getMessage()->getReplyToMessage()->getMessageId(), ['ad_id' => $ad_id]);

            $ctx->answerCallbackQuery(['text' => '✅ ' . count($destinations) . ' کانال به لیست ارسال این تبلیغ اضافه شد!']);

            $ctx->editMessageText('🔥 تبلیغ ' . self::NAMES[$adDisplayName] . ' آماده ارسال هست!

💡 با هر کدوم از گزینه های زیر تنظیمات تبلیغ رو تغییر دهید! 
‌',
                [
                    'reply_markup' => ['inline_keyboard' => [
                        [['text' => 'توقف ♻️', 'callback_data' => 'ADS_STOP_' . $ad_id], ['text' => '🚀 شروع', 'callback_data' => 'ADS_START_' . $ad_id]],
                        [['text' => '⏳ شروع و توقف زمان دار ⌛️', 'callback_data' => 'ADS_SCHEDULE_' . $ad_id]],
                        [['text' => 'ویرایش تبلیغ ✏️', 'callback_data' => 'ADS_EDIT_' . $ad_id], ['text' => '⤴️ ریپلی به تبلیغ', 'callback_data' => 'ADS_REPLY_' . $ad_id]]
                    ]]
                ]
            );
        });
    }

    public static function startAd(Context $ctx, $ad_id): void
    {
        coroutine(function () use ($ctx, $ad_id) {
            $ad = (yield $ctx->getAdByID($ad_id))->resultRows[0];
            $destinations = json_decode($ad['destinations'], 1);

            if ($destinations && count($destinations) >= 1) {
                $save = function (Message $sentMessage) use ($ctx, &$destinations) {
                    $destinations[$sentMessage->getChat()->getId()]['message_id'] = $sentMessage->getMessageId();
                };

                $requests = [];
                foreach ($destinations as $channel_id => $data) {
                    switch (json_decode($ad['settings'], 1)['mode']) {
                        case 'direct':
                            $requests[] = $ctx->copyMessage($channel_id, $_ENV['ADS_CHANNEL'], $ad['message_id'])->then($save);
                            break;
                        case 'indirect':
                            $requests[] = $ctx->forwardMessage($channel_id, $_ENV['ADS_CHANNEL'], $ad['message_id'])->then($save);
                            break;
                    }
                }

                yield all($requests);
                yield $ctx->updateUserAd($ad_id, 'destinations', json_encode($destinations));

                if ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) $ctx->answerCallbackQuery(['text' => '✅ تبلیغات با موفقیت ارسال شدند!']);
                else if ($ctx->getUpdate()->getUpdateType() === Message::class) $ctx->sendMessage('✅ تبلیغات با موفقیت ارسال شدند!', ['reply_to_message_id' => $ctx->getMessage()->getMessageId()]);
            }
        });
    }

    public static function stopAd(Context $ctx, $ad_id): void
    {
        coroutine(function () use ($ctx, $ad_id) {
            $ad = (yield $ctx->getAdByID($ad_id))->resultRows[0];
            $destinations = json_decode($ad['destinations'], 1);

            if ($destinations && count($destinations) >= 1) {
                $requests = [];
                foreach ($destinations as $channel_id => $data) {
                    if (isset($data['message_id'])) {
                        $requests[] = $ctx->deleteMessage($channel_id, $data['message_id'])->then(function (bool $true) use ($ctx, $channel_id, &$destinations) {
                            if ($true)
                                unset($destinations[$channel_id]['message_id']);
                        });
                    }
                    if (isset($data['replies'])) {
                        foreach ($data['replies'] as $reply => $reply_id) {
                            $requests[] = $ctx->deleteMessage($channel_id, $reply_id)->then(function (bool $true) use ($ctx, $channel_id, $reply, &$destinations) {
                                if ($true)
                                    unset($destinations[$channel_id]['replies'][$reply]);
                            });
                        }
                    }
                }

                yield all($requests);
                yield $ctx->updateUserAd($ad_id, 'destinations', json_encode($destinations));

                if ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) $ctx->answerCallbackQuery(['text' => '✅ تبلیغات با موفقیت حذف شدند!']);
                else if ($ctx->getUpdate()->getUpdateType() === Message::class) $ctx->sendMessage('✅ تبلیغات با موفقیت حذف شدند!', ['reply_to_message_id' => $ctx->getMessage()->getMessageId()]);
            }
        });
    }

    public static function editAd(Context $ctx, $ad_id): void
    {
        coroutine(function () use ($ctx, $ad_id) {

            if ($ctx->getMessage()->getReplyToMessage()->isServiceMessage()) {
                $ctx->sendMessage('💬 این نوع پیام پشتیبانی نمی‌شود!');
                return;
            }

            /** @var MessageId $adMessage */
            $adMessage = yield $ctx->copyMessage(
                $_ENV['ADS_CHANNEL'], $ctx->getEffectiveChat()->getId(), $ctx->getMessage()->getReplyToMessage()->getMessageId()
            );

            // todo: check if whether some record exist with this $ad_id
            /** @var QueryResult $result */
            yield $ctx->updateUserAd($ad_id, 'message_id', $adMessage->getMessageId());
            $ctx->setUserDataItem('message_data_' . $ctx->getMessage()->getReplyToMessage()->getMessageId(), ['ad_id' => $ad_id]);

            $ctx->sendMessage('💬 پیام تبلیغ با موفقیت تغییر یافت!');
        });
    }

    public static function replyAd(Context $ctx, $ad_id): void
    {
        coroutine(function () use ($ctx, $ad_id) {
            $ad = (yield $ctx->getAdByID($ad_id))->resultRows[0];
                $destinations = json_decode($ad['destinations'], 1);

            if ($destinations && count($destinations) >= 1) {
                $requests = [];
                foreach ($destinations as $channel_id => $data) {
                    if (isset($data['message_id'])) {
                        $requests[] = $ctx->copyMessage($channel_id, $ctx->getEffectiveChat()->getId(), $ctx->getMessage()->getMessageId(), ['reply_to_message_id' => $data['message_id']])->then(function (MessageId $sentMessage) use ($ctx, $channel_id, &$destinations) {
                            $destinations[$channel_id]['replies'][] = $sentMessage->getMessageId();
                        });
                    }
                }

                try {
                    if (count($requests) >= 1) {
                        yield all($requests);
                        $ctx->updateUserAd($ad_id, 'destinations', json_encode($destinations))->then('printf', 'printf');
                        $ctx->sendMessage('💬 با موفقیت به ' . count($requests) . ' تبلیغ ریپلی داده شد!', ['reply_to_message_id' => $ctx->getMessage()->getMessageId()]);
                        return;
                    }

                    $ctx->sendMessage('💬 تبلیغ در هیچ کانالی هنوز قرار نگرفته! ابتدا تبلیغ را شروع و بعد ریپلی بزنید...');
                } catch (\Exception $exception) {
                    var_dump($exception); //fixme idk
                }
            }
        });
    }
}
