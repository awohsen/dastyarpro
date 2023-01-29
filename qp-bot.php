<?php

use Zanzara\Config;
use Zanzara\Context;
use Zanzara\Telegram\Type\CallbackQuery;
use Zanzara\Telegram\Type\Chat;
use Zanzara\Telegram\Type\ChatMember;
use Zanzara\Telegram\Type\Message;
use Zanzara\Telegram\Type\Response\TelegramException;
use Zanzara\Zanzara;
use Monolog\Logger;


require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/ArrayCache.php';

$config = new Config();

$logger = new Logger("adPubRobo");
$logger->setTimezone(new DateTimeZone('Asia/Tehran'));
$config->setLogger($logger);

$config->setApiTelegramUrl('http://127.0.0.1:8580');
//$config->setUpdateMode(Config::REACTPHP_WEBHOOK_MODE);
//$config->setServerUri(8584);

$cache = new ArrayCache();
$config->setCache($cache);
$config->setCacheTtl(null); //persistent

if (file_exists(__DIR__ . '/.cache'))
    if (($_cache = json_decode(file_get_contents(__DIR__ . '/.cache'), 1)) !== null) {
        if (isset($_cache['data'])) $cache->data = $_cache['data'];
        if (isset($_cache['expires'])) $cache->expires = $_cache['expires'];
    }

$config->setParseMode(Config::PARSE_MODE_HTML);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$bot = new Zanzara($_ENV['BOT_TOKEN'], $config);
$managers = explode(',', $_ENV['ADMINS']);

$bot->onUpdate(function (Context $ctx) {
    if (isset($ctx->get('uData')['step'])) {
        if (function_exists($f = $ctx->get('uData')['step'])) {
            call_user_func($f, $ctx);
        }
    }
});

$bot->onMessage(function (Context $ctx) {
    if (null !== $ctx->getMessage()->getText() && str_starts_with($ctx->getMessage()->getText(), '/start@AdvertisingDarsbot ')) {
        if (in_array($ctx->getEffectiveUser()->getId(), explode(',', $_ENV['ADMINS']))) {
            $uId = $ctx->getEffectiveUser()->getId();
            $channel = str_replace('/start@AdvertisingDarsbot ', '', $ctx->getMessage()->getText());
            if (isset($ctx->get('gData')[$uId]['Channels'][$channel])) {
                $Admins = $ctx->get('gData');
                $Admins[$uId]['Channels'][$channel]['discuss'] = $ctx->getEffectiveChat()->getId();
                $Admins['discuss'][$ctx->geteffectiveChat()->getId()][$channel] = $uId;
                $ctx->setGlobalDataItem('data', $Admins);

                $title = $Admins[$uId]['Channels'][$channel]['name'];
                $ctx->sendMessage("تنظیم گروه کامنت برای کانال '$title' موفقیت آمیز بود!", ['chat_id' => $uId]);
            }
        }
    } else {
        if ($ctx->getMessage()->getFrom()->getid() === 777000) {
            $channel = array_keys($ctx->get('gData')['discuss'][$ctx->getEffectiveChat()->getId()])[0];
            $user = $ctx->get('gData')['discuss'][$ctx->getEffectiveChat()->getId()][$channel];
            $Admins = $ctx->get('gData');

            foreach ($Admins[$user]['Ads']['o'] as $ad) {
                if ($ctx->getMessage()->getForwardFromMessageId() === $ad['Channels'][$channel]) {
                    $ctx->deleteMessage($ctx->getEffectiveChat()->getId(), $ctx->getMessage()->getMessageId());
                }
            }
            if ($ctx->getMessage()->getForwardFromChat()->getId() == $_ENV["ADSCHANNEL"]) {
                $ctx->deleteMessage($ctx->getEffectiveChat()->getId(), $ctx->getMessage()->getMessageId());
            }
        }
    }
}, ['chat_type' => 'supergroup']);

$bot->onCommand('start', function (Context $ctx) {
    endConversation($ctx);
    if ($ctx->getEffectiveChat()->getType() === 'private') {
            if ($ctx->get('uData')['rank'] === 'headAdmin') {
                $opens = count($ctx->get('gData')[$ctx->getEffectiveUser()->getId()]['Ads']['o'] ?? []);
                $text = '🔆 سلام مالک گرامی به پنل کاربری خوش آمدید.';
                $opt = ['reply_markup' =>
                    ['inline_keyboard' => [
                        [['text' => "تبلیغات فعال ($opens)", 'callback_data' => 'hRunningAds']],
                        [['text' => ' کانالها 📢', 'callback_data' => 'hChannels'], ['text' => 'ادمینها', 'callback_data' => 'hAdmins'], ['text' => '📂 دسته ها', 'callback_data' => 'hLists']]
                    ]]];

                $ctx->sendMessage($text, $opt);
            }
    }
});
$bot->onCommand('startad {AdNum}', function (Context $ctx, $AdNum) {
    $Admins = $ctx->get('gData') ?? [];
    $uId = $ctx->getEffectiveUser()->getId();
    if (isset($Admins[$uId]['Ads']['c'][$AdNum])) {
        send2Channels($ctx, $Admins[$uId]['Ads']['c'][$AdNum], $AdNum);

        $Admins[$uId]['Ads']['o'][$AdNum] = $Admins[$uId]['Ads']['c'][$AdNum];
        unset($Admins[$uId]['Ads']['c'][$AdNum]);
        $ctx->setGlobalDataItem('data', $Admins);

        $ctx->sendMessage('✅  بنظر تبلیغ شما  ارسال شده است!

💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...');
    } else {
        $ctx->sendMessage('❎ در حال حاضر تبلیغ ارسال شده!
        ☑️ لطفا برای تایید ارسال دوباره اجباری از طریق منو اقدام کنید!');
    }
});
$bot->onCommand('stopad {AdNum}', function (Context $ctx, $AdNum) {
    $Admins = $ctx->get('gData') ?? [];
    $uId = $ctx->getEffectiveUser()->getId();
    if (isset($Admins[$uId]['Ads']['o'][$AdNum])) {
        del2Channels($ctx, $Admins[$uId]['Ads']['o'][$AdNum]);

        $Admins[$uId]['Ads']['c'][$AdNum] = $Admins[$uId]['Ads']['o'][$AdNum];
        unset($Admins[$uId]['Ads']['o'][$AdNum]);
        $ctx->setGlobalDataItem('data', $Admins);

        $ctx->sendMessage('✅  بنظر تبلیغ شما از تمام کانالها حذف شده است!
        
💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...');
    } else {
        $ctx->sendMessage('❎ در حال حاضر تبلیغ ارسال نشده!
        ☑️ لطفا برای تایید توقف و تلاش مجدد برای حذف از کانالها توقف را بزنید!');
    }
});
$bot->onCbQueryData(['hRunningAds', 'hChannels', 'hAdmins', 'hLists'], function (Context $ctx) {
    $data = $ctx->getCallbackQuery()->getData();
    $uId = $ctx->getEffectiveUser()->getId();
    $Admins = $ctx->get('gData') ?? [];
    $ctx->answerCallbackQuery();

    switch ($data) {
        case 'hRunningAds':
            if (
                isset($ctx->get('gData')[$uId]['Ads']) &&
                (count($ctx->get('gData')[$uId]['Ads']['o']) >= 1 || count($ctx->get('gData')[$uId]['Ads']['c']) >= 1)
            ) {
                $show = [];
                if (count($ctx->get('gData')[$uId]['Ads']['o']) >= 1) {
                    foreach ($ctx->get('gData')[$uId]['Ads']['o'] as $ad => $val) {
                        $show['🟢 ' . $val['label']] = $ad;
                    }
                }
                if (count($ctx->get('gData')[$uId]['Ads']['c']) >= 1) {
                    foreach ($ctx->get('gData')[$uId]['Ads']['c'] as $ad => $val) {
                        $show[$val['label']] = $ad;
                    }
                }

                $IK = BuildInlineKeyboard(array_keys($show), array_values($show), 2);
                $IK[count($IK)][0] = ['text' => '🗑', 'callback_data' => 'hDelAd'];
                $IK[count($IK) - 1][1] = ['text' => '➕', 'callback_data' => 'hNewAd'];
                $IK[count($IK)][0] = ['text' => '🔙', 'callback_data' => 'hMain'];

                $ctx->editMessageText("🔸 تبلیغ مورد نظر را انتخاب کنید‍!",
                    ['reply_markup' => ['inline_keyboard' => $IK]]);
            } else {
                $ctx->editMessageText('📭 هیچ تبلیغی در حال اجرا نیست!
    
    ➕با دکمه زیر، شروع به تبلیغ در کانالهایی که اضافه کردید.', ['reply_markup' =>
                    ['inline_keyboard' => [
                        [['text' => '➕', 'callback_data' => 'hNewAd']],
                        [['text' => '🔙', 'callback_data' => 'hMain']],
                    ]]]);
            }
            nextStep('hRunningAds', $ctx);
            break;
        case 'hChannels':
            if (isset($ctx->get('gData')[$uId]['Channels']) && count($ctx->get('gData')[$uId]['Channels']) >= 1) {
                $uId = $ctx->getEffectiveUser()->getId();
                $Channels = $Admins[$uId]['Channels'];
                $show = [];
                foreach ($Channels as $key => $channel) {
                    $show[$channel['name']] = $key;
                }

                $IK = BuildInlineKeyboard(array_keys($show), array_values($show), 2);
                $IK[count($IK)][0] = ['text' => '➕', 'callback_data' => 'hNewChannel'];
                $IK[count($IK)][0] = ['text' => '🔙', 'callback_data' => 'hMain'];

                $ctx->editMessageText('💡برای حذف کانال روی اسم آن کلیک کنید...

➕با دکمه زیر چنل های خود را اضافه کنید.',
                    ['reply_markup' => ['inline_keyboard' => $IK]]);
                nextStep('hAdd2List', $ctx);
            } else {
                $ctx->editMessageText('📭 لیست چنل ها خالیست !

➕با دکمه زیر چنل های خود را اضافه کنید.', ['reply_markup' =>
                    ['inline_keyboard' => [
                        [['text' => '➕', 'callback_data' => 'hNewChannel']],
                        [['text' => '🔙', 'callback_data' => 'hMain']],
                    ]]]);
            }
            nextStep('hChannels', $ctx);
            break;
        case 'hLists':
            $ctx->answerCallbackQuery();
            $lists = $Admins[$uId]['Lists'];

            $IK = BuildInlineKeyboard(array_keys($lists), array_keys($lists), 2);
            $IK[count($IK)][0] = ['text' => '➕', 'callback_data' => 'fAdd'];
            $IK[count($IK) - 1][1] = ['text' => '🔙', 'callback_data' => 'hMain'];
            $ctx->editMessageText("📂 برای ویرایش دسته آن را انتخاب کنید!

➕برای اضافه کردن دسته جدید، دکمه را بزنید.

‌",
                ['reply_markup' => ['inline_keyboard' => $IK]]);
            nextStep('hLists', $ctx);
            break;

    }
});

function nextStep(callable $function, Context $ctx)
{
    setUserData($ctx, 'step', $function);
}

function endConversation(Context $ctx)
{
    deleteUserData($ctx, 'step');
}

function hMain(Context $ctx, $mode = 'edit')
{
    $opens = count($ctx->get('gData')[$ctx->getEffectiveUser()->getId()]['Ads']['o'] ?? []);


    $text = '🔆 سلام مالک گرامی به پنل کاربری خوش آمدید.';
    $opt = ['reply_markup' =>
        ['inline_keyboard' => [
            [['text' => "تبلیغات فعال ($opens)", 'callback_data' => 'hRunningAds']],
            [['text' => ' کانالها 📢', 'callback_data' => 'hChannels'], ['text' => 'ادمینها', 'callback_data' => 'hAdmins'], ['text' => '📂 دسته ها', 'callback_data' => 'hLists']]
        ]]];

    if ($mode == 'edit') $ctx->editMessageText($text, $opt);
    else $ctx->sendMessage($text, $opt);

    endConversation($ctx);
}

function hLists(Context $ctx)
{
    if ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        $data = $ctx->getCallbackQuery()->getData();
        switch ($data) {
            case 'hMain':
                $ctx->answerCallbackQuery(['text' => '🔅درحال برگشت به منوی اصلی ...']);
                hMain($ctx);
                break;
            case 'fAdd':
                $ctx->answerCallbackQuery(['text' => 'ساخت دسته جدید...']);
                $ctx->editMessageText('📂 لطفا نام دسته جدید را وارد کنید. 

⚠️ نام های بیشتر از 25 کاراکتر خلاصه میشوند!
', ['reply_markup' =>
                    ['inline_keyboard' => [
                        [['text' => '🔙', 'callback_data' => 'hMain']],
                    ]]]);
                nextStep('hNewList', $ctx);
                break;
            default:
                $uId = $ctx->getEffectiveUser()->getId();
                $Admins = $ctx->get('gData') ?? [];
                $lists = $Admins[$uId]['Lists'];
                if (in_array($data, array_keys($lists))) {
                    setUserData($ctx, 'list', $data);

                    $ctx->editMessageText('⚙️ برای تغییر انتخاب کنید : 

➖ برای حذف دسته 
🔙 برای برگشت به منوی اصلی 

‌', ['reply_markup' => ['inline_keyboard' => [
                        [['callback_data' => 'changeName', 'text' => 'تغییر نام دسته ✏️'], ['text' => '📢 تغییر کانالها', 'callback_data' => 'changeChannels']],
                        [['callback_data' => 'delList', 'text' => '➖']],
                        [['text' => '🔙', 'callback_data' => 'hMain']]
                    ]]]);
                    nextStep('hListMg', $ctx);
                }
        }
    }
}

function hRunningAds(Context $ctx)
{
    if ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        $data = $ctx->getCallbackQuery()->getData();
        $uId = $ctx->getEffectiveUser()->getId();
        switch ($data) {
            case 'hMain':
                $ctx->answerCallbackQuery(['text' => '🔅درحال برگشت به منوی اصلی ...']);
                hMain($ctx);
                break;
            case 'hDelAd':
                if (isset($ctx->get('gData')[$uId]['Ads']['c']) && count($ctx->get('gData')[$uId]['Ads']['c']) >= 1) {
                    $Admins = $ctx->get('gData') ?? [];
                    $Admins[$uId]['Ads']['c'] = [];
                    $ctx->setGlobalDataItem('data', $Admins);

                    $ctx->answerCallbackQuery(['text' => '✅ تمامی تبلیغ های متوقف شده شما پاک شدند!', 'show_alert' => true]);

                    if (
                        isset($ctx->get('gData')[$uId]['Ads']) &&
                        (count($ctx->get('gData')[$uId]['Ads']['o']) >= 1)
                    ) {
                        $show = [];
                        if (count($ctx->get('gData')[$uId]['Ads']['o']) >= 1) {
                            foreach ($ctx->get('gData')[$uId]['Ads']['o'] as $ad => $val) {
                                $show['🟢 ' . $val['label']] = $ad;
                            }
                        }

                        $IK = BuildInlineKeyboard(array_keys($show), array_values($show), 2);
                        $IK[count($IK)][0] = ['text' => '🗑', 'callback_data' => 'hDelAd'];
                        $IK[count($IK) - 1][1] = ['text' => '➕', 'callback_data' => 'hNewAd'];
                        $IK[count($IK)][0] = ['text' => '🔙', 'callback_data' => 'hMain'];

                        $ctx->editMessageText("🔸 تبلیغ مورد نظر را انتخاب کنید‍!",
                            ['reply_markup' => ['inline_keyboard' => $IK]]);
                    } else {
                        $ctx->editMessageText('📭 هیچ تبلیغی در حال اجرا نیست!
    
    ➕با دکمه زیر، شروع به تبلیغ در کانالهایی که اضافه کردید.', ['reply_markup' =>
                            ['inline_keyboard' => [
                                [['text' => '➕', 'callback_data' => 'hNewAd']],
                                [['text' => '🔙', 'callback_data' => 'hMain']],
                            ]]]);
                    }
                    nextStep('hRunningAds', $ctx);
                    return;
                }

                $ctx->answerCallbackQuery(['text' => '❎ تبلیغی متوقف شده ای برای حذف وجود ندارد!']);
                break;
            case 'hNewAd':
                if (isset($ctx->get('gData')[$uId]['Channels']) && count($ctx->get('gData')[$uId]['Channels']) >= 1) {
                    $ctx->editMessageText('📂 لطفا یک نام نمایشی برای این دوره تبلیغ بفرستید

⚠️ نام های بیشتر از 25 کاراکتر خلاصه میشوند!
', ['reply_markup' =>
                        ['inline_keyboard' => [
                            [['text' => '🔙', 'callback_data' => 'hMain']],
                        ]]]);
                    nextStep('hNewAdName', $ctx);
                } else {
                    $ctx->answerCallbackQuery(['text' => '📁 برای شروع تبلیغ باید در پنل خودتون حداقل یک کانال و یک دسته داشته باشید!', 'show_alert' => true]);

                    if (isset($ctx->get('gData')[$uId]['Lists'])) {
                        $ctx->editMessageText('💬 لطفا آیدی عددی کانال را ارسال کنید یا یک پست از کانال فوروارد کنید تا آیدی رو پیدا کنم!', ['reply_markup' =>
                            ['inline_keyboard' => [
                                [['text' => '🔙', 'callback_data' => 'hMain']],
                            ]]]);
                        nextStep('hNewChannel', $ctx);
                    } else {
                        $ctx->editMessageText('📂 لطفا نام دسته جدید را وارد کنید. 

⚠️ نام های بیشتر از 25 کاراکتر خلاصه میشوند!
', ['reply_markup' =>
                            ['inline_keyboard' => [
                                [['text' => '🔙', 'callback_data' => 'hMain']],
                            ]]]);
                        nextStep('hNewList', $ctx);
                    }
                }
                break;
            default:
                $opens = $ctx->get('gData')[$uId]['Ads']['o'] ?? [];
                $close = $ctx->get('gData')[$uId]['Ads']['c'] ?? [];

                if (in_array($data, array_keys($opens)) || in_array($data, array_keys($close))) {
                    setUserData($ctx, 'AdNum', $data);

                    $ctx->editMessageText('🎯 انتخاب کنید !

⚡️ با دکمه شروع تبلیغ مربوطه را در کانال های خود ارسال کنید. 
✖️ با دکمه توقف روند فعلی تبلیغ را متوقف و آنرا از کانالها حذف کنید.

💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...',
                        ['reply_markup' => ['inline_keyboard' => [
                            [['text' => 'توقف✖️', 'callback_data' => 'stop'], ['text' => '⚡️شروع', 'callback_data' => 'start']],
                            [['text' => ' توقف زماندار⏳', 'callback_data' => 'stop-time'], ['text' => '⏳شروع زماندار', 'callback_data' => 'start-time']],
                            [['text' => '💬 کانال ها', 'callback_data' => 'ch']],
                            [['text' => 'تغییر پست', 'callback_data' => 'post'], ['text' => 'تغییر نام', 'callback_data' => 'name']],
                            [['text' => '🔙', 'callback_data' => 'hMain']]
                        ]]]);
                    nextStep('AdPanel', $ctx);
                }
        }
    }
}

function hNewAdName(Context $ctx)
{
    if ($ctx->getUpdate()->getUpdateType() === Message::class) {
        if (($text = $ctx->getMessage()->getText()) !== null) {
            $uId = $ctx->getEffectiveUser()->getId();

            if (mb_strlen($text) > 25) {
                $text = mb_substr($text, '0', 25, mb_detect_encoding($text));
                $text .= '...';
            }
            $Ad = [];
            $Ad['label'] = $text;

            setUserData($ctx, 'Ad', $Ad);

            $Admins = $ctx->get('gData') ?? [];

            $lists = [];
            foreach ($Admins[$uId]['Channels'] as $key => $list) {
                if (isset($list['username']) && !empty($list['username']))
                    $name = '@' . $list['username'];
                elseif (isset($list['name']) && !empty($list['name']))
                    $name = $list['name'];
                else $name = $key;

                $lists[$name]['id'] = $key;
                $lists[$name]['sel'] = true;
            }
            setUserData($ctx, 'lists', $lists);
            setUserData($ctx, 'Ad', $Ad);


            $show = function (array $lists) {
                $show = [];
                $listKey = array_keys($lists);
                foreach ($listKey as $key => $list) {
                    if ($lists[$list]['sel'] === true) {
                        $show[$key] = '✔️' . $list;
                    } else {
                        $show[$key] = $list;
                    }
                }
                return $show;
            };
            $show = $show($lists);

            $IK = BuildInlineKeyboard(array_values($show), array_keys($lists), 2);

//            $IK = BuildInlineKeyboard(array_keys($lists),array_keys($lists),2);
            $IK[count($IK)][0] = ['text' => '☑️', 'callback_data' => 'done'];
            $IK[count($IK)][0] = ['text' => '🔙', 'callback_data' => 'hMain'];

            $ctx->sendMessage('💬 لطفا کانالهای مورد نظر را برای ارسال انتخاب کنید!',
                ['reply_markup' => ['inline_keyboard' => $IK]]);
            nextStep('hNewAd', $ctx);
        }
    } elseif ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        if ($ctx->getCallbackQuery()->getData() == 'hMain') {
            $ctx->answerCallbackQuery(['text' => '🔅درحال برگشت به منوی اصلی ...']);
            hMain($ctx);
            deleteUserData($ctx, 'Ad');
        }
    }
}

function hNewAd(Context $ctx)
{
    if ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        $lists = $ctx->get('uData')['lists'];

        $show = function (array $lists) {
            $show = [];
            $listKey = array_keys($lists);
            foreach ($listKey as $key => $list) {
                if ($lists[$list]['sel'] === true) {
                    $show[$key] = '✔️' . $list;
                } else {
                    $show[$key] = $list;
                }
            }
            return $show;
        };

        $data = $ctx->getCallbackQuery()->getData();
        if ($data == 'done') {
            $count = 0;
            $listKey = array_keys($lists);

            $Ad = $ctx->get('uData')['Ad'];

            foreach ($listKey as $list) {
                if ($lists[$list]['sel'] === true) {
                    if (!in_array($lists[$list]['id'], $Ad['Channels'] ?? []))
                        $Ad['Channels'][$lists[$list]['id']]['msg'] = null;
                    $count++;
                }
            }
            if ($count < 1)
                $ctx->answerCallbackQuery(['text' => '⚠️ حداقل یکی را انتخاب کنید!']);
            else {
                deleteUserData($ctx, 'lists');
                setUserData($ctx, 'Ad', $Ad);
                $ctx->answerCallbackQuery(['text' => "✅ $count کانال به لیست ارسال این تبلیغ اضافه شدند!"]);
                $ctx->editMessageText(' 💬 لطفاً تبلیغ را ارسال کنید...

💠 میتوانید حالت تبلیغ را هم از زیر مشخص کنید!', ['reply_markup' => ['inline_keyboard' => [
                    [['text' => 'غیر مستقیم ✔️', 'callback_data' => '1'], ['text' => 'فورواردی', 'callback_data' => '2'], ['text' => 'مستقیم', 'callback_data' => '3'],],
                    [['text' => '🔙', 'callback_data' => 'hMain']]
                ]]]);
                // todo next step
                nextStep('hNewAdGet', $ctx);
            }
        } elseif ($data == 'hMain') {
            deleteUserData($ctx, 'lists');
            deleteUserData($ctx, 'Ad');
            $ctx->answerCallbackQuery(['text' => '🔅درحال برگشت به منوی اصلی ...']);

            hMain($ctx);
        } else {
            if (in_array($data, array_keys($lists))) {
                $lists[$data]['sel'] = !$lists[$data]['sel'];
                $show = $show($lists);

                $IK = BuildInlineKeyboard(array_values($show), array_keys($lists), 2);
                $IK[count($IK)][0] = ['text' => '☑️', 'callback_data' => 'done'];
                $IK[count($IK)][0] = ['text' => '🔙', 'callback_data' => 'hMain'];

                $ctx->editMessageText('🎯 انتخاب شد!
💡موارد بیشتر انتخاب کنید یا ☑️ را بزنید...', ['reply_markup' => ['inline_keyboard' => $IK]]);

                setUserData($ctx, 'lists', $lists);
            }
        }
    }
}

function hNewAdGet(Context $ctx)
{
    $Ad = $ctx->get('uData')['Ad'];

    $doThis = function ($ctx, $Ad) {

        $Ad['send']['chatId'] = $_ENV['ADSCHANNEL'];
        deleteUserData($ctx, 'Ad');

        $Admins = $ctx->get('gData') ?? [];
        $uId = $ctx->getEffectiveUser()->getId();
        $Admins[$uId]['Ads']['c'][$r = rand(11111, 99999)] = $Ad;
        setUserData($ctx, 'AdNum', $r);

        $ctx->setGlobalDataItem('data', $Admins);
        $ctx->sendMessage('☑️ تبلیغ شما آماده ارسال است!

⚡️ با دکمه شروع تبلیغ مربوطه را در کانال های خود ارسال کنید. 
✖️ با دکمه توقف روند فعلی تبلیغ را متوقف و آنرا از کانالها حذف کنید.

💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...',
            ['reply_markup' => ['inline_keyboard' => [
                [['text' => 'توقف✖️', 'callback_data' => 'stop'], ['text' => '⚡️شروع', 'callback_data' => 'start']],
                [['text' => ' توقف زماندار⏳', 'callback_data' => 'stop-time'], ['text' => '⏳شروع زماندار', 'callback_data' => 'start-time']],
                [['text' => 'تغییر پست', 'callback_data' => 'post'], ['text' => 'تغییر نام', 'callback_data' => 'name']],
                [['text' => '🔙', 'callback_data' => 'hMain']]
            ]]]);
        nextStep('AdPanel', $ctx);
    };


    if ($ctx->getUpdate()->getUpdateType() === Message::class) {
        switch ($Ad['type'] ?? '1') {
            case '1':
                $ctx->copyMessage(
                    $_ENV['ADSCHANNEL'], $ctx->getEffectiveChat()->getId(), $ctx->getMessage()->getMessageId()
                )->then(function ($id) use ($Ad, $ctx, $doThis) {
                    $Ad['send']['msg'] = $id->getMessageId();
                    $doThis($ctx, $Ad);
                });
                break;
            case '2':
            case '3':
                $ctx->forwardMessage(
                    $_ENV['ADSCHANNEL'], $ctx->getEffectiveChat()->getId(), $ctx->getMessage()->getMessageId()
                )->then(function ($id) use ($Ad, $ctx, $doThis) {
                    $Ad['send']['msg'] = $id->getMessageId();
                    $doThis($ctx, $Ad);
                });
                break;
        }


    } elseif ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        $Ad['type'] = $ctx->getCallbackQuery()->getData();
        $ctx->answerCallbackQuery();
        switch ($ctx->getCallbackQuery()->getData()) {
            case '1':
                $ctx->editMessageText(' 💬 لطفاً تبلیغ را ارسال کنید...

💠 میتوانید حالت تبلیغ را هم از زیر مشخص کنید!', ['reply_markup' => ['inline_keyboard' => [
                    [['text' => 'غیر مستقیم ✔️', 'callback_data' => '1'], ['text' => 'فورواردی', 'callback_data' => '2'], ['text' => 'مستقیم', 'callback_data' => '3'],],
                    [['text' => '🔙', 'callback_data' => 'hMain']]
                ]]]);
                setUserData($ctx, 'Ad', $Ad);
                break;
            case '2':
                $ctx->editMessageText(' 💬 لطفاً تبلیغ را ارسال کنید...

💠 میتوانید حالت تبلیغ را هم از زیر مشخص کنید!', ['reply_markup' => ['inline_keyboard' => [
                    [['text' => 'غیر مستقیم', 'callback_data' => '1'], ['text' => 'فورواردی ✔️', 'callback_data' => '2'], ['text' => 'مستقیم', 'callback_data' => '3'],],
                    [['text' => '🔙', 'callback_data' => 'hMain']]
                ]]]);
                setUserData($ctx, 'Ad', $Ad);
                break;
            case '3':
                $ctx->editMessageText(' 💬 لطفاً تبلیغ را ارسال کنید...

💠 میتوانید حالت تبلیغ را هم از زیر مشخص کنید!', ['reply_markup' => ['inline_keyboard' => [
                    [['text' => 'غیر مستقیم', 'callback_data' => '1'], ['text' => 'فورواردی', 'callback_data' => '2'], ['text' => 'مستقیم ✔️', 'callback_data' => '3'],],
                    [['text' => '🔙', 'callback_data' => 'hMain']]
                ]]]);
                setUserData($ctx, 'Ad', $Ad);
                break;

            case 'hMain':
                deleteUserData($ctx, 'Ad');
                deleteUserData($ctx, 'lists');
                $ctx->answerCallbackQuery(['text' => '🔅درحال برگشت به منوی اصلی ...']);
                hMain($ctx);
                break;
        }
    }
}

function AdPanel(Context $ctx)
{
    if ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        if (isset($ctx->get('uData')['AdNum'])) {
            $Admins = $ctx->get('gData') ?? [];
            $uId = $ctx->getEffectiveUser()->getId();
            $AdNum = $ctx->get('uData')['AdNum'];

            switch ($ctx->getCallbackQuery()->getData()) {
                case 'start':
                    if (isset($Admins[$uId]['Ads']['c'][$AdNum])) {
                        send2Channels($ctx, $Admins[$uId]['Ads']['c'][$AdNum], $AdNum);

                        $Admins[$uId]['Ads']['o'][$AdNum] = $Admins[$uId]['Ads']['c'][$AdNum];
                        unset($Admins[$uId]['Ads']['c'][$AdNum]);
                        $ctx->setGlobalDataItem('data', $Admins);


                        $ctx->editMessageText('✅  بنظر تبلیغ شما  ارسال شده است!

⚡️ با دکمه شروع تبلیغ مربوطه را در کانال های خود ارسال کنید. 
✖️ با دکمه توقف روند فعلی تبلیغ را متوقف و آنرا از کانالها حذف کنید.

💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...',
                            ['reply_markup' => ['inline_keyboard' => [
                                [['text' => 'توقف✖️', 'callback_data' => 'stop'], ['text' => '⚡️شروع', 'callback_data' => 'start']],
                                [['text' => ' توقف زماندار⏳', 'callback_data' => 'stop-time'], ['text' => '⏳شروع زماندار', 'callback_data' => 'start-time']],
                                [['text' => '💬 کانال ها', 'callback_data' => 'ch']],
                                [['text' => 'تغییر پست', 'callback_data' => 'post'], ['text' => 'تغییر نام', 'callback_data' => 'name']],
                                [['text' => '🔙', 'callback_data' => 'hMain']]
                            ]]]);
                    } else {
                        $ctx->answerCallbackQuery(['text' => '❎ در حال حاضر تبلیغ ارسال شده!']);
                        $ctx->editMessageText('☑️ لطفا برای تایید ارسال دوباره اجباری شروع را بزنید!

⚡️ با دکمه شروع تبلیغ مربوطه را در کانال های خود ارسال کنید. 
✖️ با دکمه توقف روند فعلی تبلیغ را متوقف و آنرا از کانالها حذف کنید.

💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...',
                            ['reply_markup' => ['inline_keyboard' => [
                                [['text' => 'توقف✖️', 'callback_data' => 'stop'], ['text' => '⚡️شروع', 'callback_data' => 'ConfirmStart']],
                                [['text' => ' توقف زماندار⏳', 'callback_data' => 'stop-time'], ['text' => '⏳شروع زماندار', 'callback_data' => 'start-time']],
                                [['text' => '💬 کانال ها', 'callback_data' => 'ch']],
                                [['text' => 'تغییر پست', 'callback_data' => 'post'], ['text' => 'تغییر نام', 'callback_data' => 'name']],
                                [['text' => '🔙', 'callback_data' => 'hMain']]
                            ]]]);
                    }
                    break;
                case 'ConfirmStart':
                    if (isset($Admins[$uId]['Ads']['o'][$AdNum])) {
                        send2Channels($ctx, $Admins[$uId]['Ads']['o'][$AdNum], $AdNum);

                        unset($Admins[$uId]['Ads']['c'][$AdNum]);
                        $ctx->setGlobalDataItem('data', $Admins);

                        $ctx->editMessageText('✅  بنظر تبلیغ شما  ارسال شده است!

⚡️ با دکمه شروع تبلیغ مربوطه را در کانال های خود ارسال کنید. 
✖️ با دکمه توقف روند فعلی تبلیغ را متوقف و آنرا از کانالها حذف کنید.

💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...',
                            ['reply_markup' => ['inline_keyboard' => [
                                [['text' => 'توقف✖️', 'callback_data' => 'stop'], ['text' => '⚡️شروع', 'callback_data' => 'start']],
                                [['text' => ' توقف زماندار⏳', 'callback_data' => 'stop-time'], ['text' => '⏳شروع زماندار', 'callback_data' => 'start-time']],
                                [['text' => '💬 کانال ها', 'callback_data' => 'ch']],
                                [['text' => 'تغییر پست', 'callback_data' => 'post'], ['text' => 'تغییر نام', 'callback_data' => 'name']],
                                [['text' => '🔙', 'callback_data' => 'hMain']]
                            ]]]);
                    } else {
                        $ctx->answerCallbackQuery(['text' => '❌ خطای جدی رخ داده، تبلیغ گم شده!!']);
                    }
                    break;
                case 'stop':
                    if (isset($Admins[$uId]['Ads']['o'][$AdNum])) {
                        del2Channels($ctx, $Admins[$uId]['Ads']['o'][$AdNum]);

                        $Admins[$uId]['Ads']['c'][$AdNum] = $Admins[$uId]['Ads']['o'][$AdNum];
                        unset($Admins[$uId]['Ads']['o'][$AdNum]);
                        $ctx->setGlobalDataItem('data', $Admins);

                        $ctx->editMessageText('✅  بنظر تبلیغ شما از تمام کانالها حذف شده است!

⚡️ با دکمه شروع تبلیغ مربوطه را در کانال های خود ارسال کنید. 
✖️ با دکمه توقف روند فعلی تبلیغ را متوقف و آنرا از کانالها حذف کنید.

💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...',
                            ['reply_markup' => ['inline_keyboard' => [
                                [['text' => 'توقف✖️', 'callback_data' => 'stop'], ['text' => '⚡️شروع', 'callback_data' => 'start']],
                                [['text' => ' توقف زماندار⏳', 'callback_data' => 'stop-time'], ['text' => '⏳شروع زماندار', 'callback_data' => 'start-time']],
                                [['text' => '💬 کانال ها', 'callback_data' => 'ch']],
                                [['text' => 'تغییر پست', 'callback_data' => 'post'], ['text' => 'تغییر نام', 'callback_data' => 'name']],
                                [['text' => '🔙', 'callback_data' => 'hMain']]
                            ]]]);
                    } else {
                        $ctx->answerCallbackQuery(['text' => '❎ در حال حاضر تبلیغ ارسال نشده!']);
                        $ctx->editMessageText('☑️ لطفا برای تایید توقف و تلاش مجدد برای حذف از کانالها توقف را بزنید!

⚡️ با دکمه شروع تبلیغ مربوطه را در کانال های خود ارسال کنید. 
✖️ با دکمه توقف روند فعلی تبلیغ را متوقف و آنرا از کانالها حذف کنید.

💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...',
                            ['reply_markup' => ['inline_keyboard' => [
                                [['text' => 'توقف✖️', 'callback_data' => 'ConfirmStop'], ['text' => '⚡️شروع', 'callback_data' => 'start']],
                                [['text' => ' توقف زماندار⏳', 'callback_data' => 'stop-time'], ['text' => '⏳شروع زماندار', 'callback_data' => 'start-time']],
                                [['text' => '💬 کانال ها', 'callback_data' => 'ch']],
                                [['text' => 'تغییر پست', 'callback_data' => 'post'], ['text' => 'تغییر نام', 'callback_data' => 'name']],
                                [['text' => '🔙', 'callback_data' => 'hMain']]
                            ]]]);
                    }
                    break;
                case 'ConfirmStop':
                    if (isset($Admins[$uId]['Ads']['c'][$AdNum])) {
                        del2Channels($ctx, $Admins[$uId]['Ads']['c'][$AdNum]);
                        unset($Admins[$uId]['Ads']['o'][$AdNum]);
                        $ctx->setGlobalDataItem('data', $Admins);

                        $ctx->editMessageText('✅  بنظر تبلیغ شما از تمام کانالها حذف شده است!

⚡️ با دکمه شروع تبلیغ مربوطه را در کانال های خود ارسال کنید. 
✖️ با دکمه توقف روند فعلی تبلیغ را متوقف و آنرا از کانالها حذف کنید.

💡پس از توقف تبلیغ گزارش نهایی آن در کانال مربوطه ارسال خواهد شد...',
                            ['reply_markup' => ['inline_keyboard' => [
                                [['text' => 'توقف✖️', 'callback_data' => 'stop'], ['text' => '⚡️شروع', 'callback_data' => 'start']],
                                [['text' => ' توقف زماندار⏳', 'callback_data' => 'stop-time'], ['text' => '⏳شروع زماندار', 'callback_data' => 'start-time']],
                                [['text' => '💬 کانال ها', 'callback_data' => 'ch']],
                                [['text' => 'تغییر پست', 'callback_data' => 'post'], ['text' => 'تغییر نام', 'callback_data' => 'name']],
                                [['text' => '🔙', 'callback_data' => 'hMain']]
                            ]]]);
                    } else {
                        $ctx->answerCallbackQuery(['text' => '❌ خطای جدی رخ داده، تبلیغ گم شده!!']);
                    }
                    break;
                case 'start-time':
                    $ctx->answerCallbackQuery();
                    $ctx->sendMessage(
                        '⏰ با استفاده از دستور زیر و قراردهی آن در بخش ارسال زماندار تلگرام(Schedule messages) میتوانید این تبلیغ را در هر زمانی که تنظیم کردید شروع کنید:' .
                        PHP_EOL . "<code>/startad $AdNum</code>"
                    );
                    break;
                case 'stop-time':
                    $ctx->answerCallbackQuery();
                    $ctx->sendMessage(
                        '⏰ با استفاده از دستور زیر و قراردهی آن در بخش ارسال زماندار تلگرام(Schedule messages) میتوانید این تبلیغ را در هر زمانی که تنظیم کردید متوقف کنید:' .
                        PHP_EOL . "<code>/stopad $AdNum</code>"
                    );
                    break;
                case 'hMain':
                    deleteUserData($ctx, 'Ad');
                    deleteUserData($ctx, 'lists');
                    $ctx->answerCallbackQuery(['text' => '🔅درحال برگشت به منوی اصلی ...']);
                    hMain($ctx);
                    break;
            }
        } else {
            $ctx->answerCallbackQuery(['text' => '❌ خطایی رخ داد تبلیغ رو گم کردیم! دوباره تلاش کنید...']);
            hMain($ctx);
        }
    }
}

function send2Channels(Context $ctx, $Ad, $AdNum)
{
    $Channels = array_keys($Ad['Channels']);
    switch ($Ad['type'] ?? 1) {
        case '1':
        case '2':
            foreach ($Channels as $Channel) {
                $ctx->forwardMessage($Channel, $Ad['send']['chatId'], $Ad['send']['msg'])->then(
                    function ($id) use ($ctx, $Channel, $AdNum) {
                        $uId = $ctx->getEffectiveUser()->getId();
                        $ctx->getGlobalDataItem('data')->then(function ($Admins) use ($ctx, $uId, $AdNum, $Channel, $id) {
                            $Admins[$uId]['Ads']['o'][$AdNum]['Channels'][$Channel] = $id->getMessageId();
                            unset($Admins[$uId]['Ads']['c'][$AdNum]);
                            $ctx->setGlobalDataItem('data', $Admins);
                        });
                    }
                );
            }
            break;
        case '3':
            foreach ($Channels as $Channel) {
                $ctx->copyMessage($Channel, $Ad['send']['chatId'], $Ad['send']['msg'])->then(
                    function ($id) use ($Ad, $Channel, $AdNum, $ctx) {
                        $uId = $ctx->getEffectiveUser()->getId();
                        $ctx->getGlobalDataItem('data')->then(function ($Admins) use ($ctx, $uId, $AdNum, $Channel, $id) {
                            $Admins[$uId]['Ads']['o'][$AdNum]['Channels'][$Channel] = $id->getMessageId();
                            $ctx->setGlobalDataItem('data', $Admins);
                        });
                    }
                );
            }
            break;
    }
}

function del2Channels(Context $ctx, $Ad)
{
    $Channels = array_keys($Ad['Channels']);
    foreach ($Channels as $Channel) {
        $ctx->deleteMessage($Channel, $Ad['Channels'][$Channel]);
    }
}


function hNewList(Context $ctx)
{
    if ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        if ($ctx->getCallbackQuery()->getData() == 'hMain') {
            $ctx->answerCallbackQuery(['text' => '🔅درحال برگشت به منوی اصلی ...']);
            hMain($ctx);
        }
    } elseif ($ctx->getUpdate()->getUpdateType() === Message::class) {
        if (($text = $ctx->getMessage()->getText()) !== null) {
            if (mb_strlen($text) > 25) {
                $text = mb_substr($text, '0', 25, mb_detect_encoding($text));
                $text .= '...';
            }
            $Admins = $ctx->get('gData') ?? [];
            $uId = $ctx->getEffectiveUser()->getId();

            if (!isset($Admins[$uId]['Lists'][$text])) {
                $Admins[$uId]['Lists'][$text]['Channels'] = [];
                $ctx->setGlobalDataItem('data', $Admins);

                $ctx->sendMessage("✅ دسته ی '$text' با موفقیت ایجاد شد!");
                hMain($ctx, false);
            } else
                $ctx->sendMessage("❌ دسته ی '$text' از قبل ایجاد شده بود، نام دیگری انتخاب کنید!");


            hMain($ctx, false);
        }
    }
}

function hChannels(Context $ctx)
{
    $uId = $ctx->getEffectiveUser()->getId();
    if ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        $data = $ctx->getCallbackQuery()->getData();
        switch ($data) {
            case 'hNewChannel':
                if (isset($ctx->get('gData')[$uId]['Lists']) && count($ctx->get('gData')[$uId]['Lists']) >= 1) {
                    $ctx->editMessageText('💬 لطفا آیدی عددی کانال را ارسال کنید یا یک پست از کانال فوروارد کنید تا آیدی رو پیدا کنم!', ['reply_markup' =>
                        ['inline_keyboard' => [
                            [['text' => '🔙', 'callback_data' => 'hMain']],
                        ]]]);
                    nextStep('hNewChannel', $ctx);
                } else {
                    $ctx->answerCallbackQuery(['text' => '📁 هر چنل باید در دسته خاصی قرار گرفته باشد! شما تا به حال دسته ای نساخته بودید، الان بسازید.', 'show_alert' => true]);
                    $ctx->editMessageText('📂 لطفا نام دسته جدید را وارد کنید. 

⚠️ نام های بیشتر از 25 کاراکتر خلاصه میشوند!
', ['reply_markup' =>
                        ['inline_keyboard' => [
                            [['text' => '🔙', 'callback_data' => 'hMain']],
                        ]]]);
                    nextStep('hNewList', $ctx);
                }
                break;
            case 'hMain':
                $ctx->answerCallbackQuery(['text' => '🔅درحال برگشت به منوی اصلی ...']);
                hMain($ctx);
                break;
            default:
                $Admins = $ctx->get('gData') ?? [];
                $Channels = $Admins[$uId]['Channels'];

                if (in_array($data, array_keys($Channels))) {
                    $count = 0;
                    foreach ($Admins[$uId]['Lists'] as $key => $list) {
                        if (in_array($data, $Admins[$uId]['Lists'][$key]['Channels'])) {
                            unset($Admins[$uId]['Lists'][$key]['Channels'][array_search($data, $Admins[$uId]['Lists'][$key]['Channels'])]);
                        }
                        $count++;
                    }
                    if ($count < 1) {
                        $ctx->answerCallbackQuery(['text' => '💤 کانال در هیچ دسته ای استفاده نشده بود!']);
                    } else {
                        $ctx->answerCallbackQuery(['text' => "☑️🗑 با موفقیت از $count دسته حذف شد! "]);
                    }

                    unset($Admins[$uId]['Channels'][$data]);

                    $Channels = $Admins[$uId]['Channels'];
                    $show = [];
                    foreach ($Channels as $key => $channel) {
                        $show[$channel['name']] = $key;
                    }

                    $IK = BuildInlineKeyboard(array_keys($show), array_values($show), 2);
                    $IK[count($IK)][0] = ['text' => '➕', 'callback_data' => 'hNewChannel'];
                    $IK[count($IK)][0] = ['text' => '🔙', 'callback_data' => 'hMain'];

                    $ctx->editMessageText('💡برای حذف کانال روی اسم آن کلیک کنید...

➕با دکمه زیر چنل های خود را اضافه کنید.',
                        ['reply_markup' => ['inline_keyboard' => $IK]]);
                    $ctx->setGlobalDataItem('data', $Admins);
                }
        }
    }
}

function hNewChannel(Context $ctx)
{
    if ($ctx->getUpdate()->getUpdateType() === Message::class) {
        if ($ctx->getMessage()->getForwardFromChat() !== null) {
            getChatMember($ctx->getMessage()->getForwardFromChat()->getId(), explode(':', $_ENV['BOT_TOKEN'])[0], $ctx);
        } elseif ($ctx->getMessage()->getText() !== null) {
            getChatMember($ctx->getMessage()->getText(), explode(':', $_ENV['BOT_TOKEN'])[0], $ctx);
        }
    } elseif ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        if ($ctx->getCallbackQuery()->getData() == 'hMain') {
            $ctx->answerCallbackQuery(['text' => '🔅درحال برگشت به منوی اصلی ...']);
            hMain($ctx);
        }
    }
}

function getChatMember($ChatId, $UserID, Context $ctx)
{
    $uId = $ctx->getEffectiveUser()->getId();

    if (!isset($ctx->get('gData')[$uId]['Channels'][$ChatId])) {
        $ctx->getChatMember($ChatId, $UserID)->then(
            function (ChatMember $result) use ($ctx, $ChatId) {
                if (in_array($result->getStatus(), ['creator', 'administrator'])) {
                    $ctx->getChat($ChatId)->then(function (Chat $result) use ($ctx, $ChatId) {
                        if ($result->getType() == 'channel') {
                            $Admins = $ctx->get('gData') ?? [];
                            $uId = $ctx->getEffectiveUser()->getId();
                            $title = $result->getTitle();

                            if (mb_strlen($title) > 25) {
                                $title = mb_substr($title, '0', 25, mb_detect_encoding($title));
                                $title .= '...';
                            }

                            $Admins[$uId]['Channels'][$ChatId]['name'] = $title;
                            if ($result->getUsername() !== null) $Admins[$uId]['Channels'][$ChatId]['username'] = $result->getUsername();
                            $ctx->setGlobalDataItem('data', $Admins);
                            setUserData($ctx, 'Channel', $ChatId);

                            $ctx->sendMessage("✅ کانال '$title' با موفقیت اضافه شد!


✳️ در صورت علاقه میتونید گروه متصل به کانال رو هم اینجا اضافه کنید تا امکان کامنت تبلیغ بطور خودکار حذف بشه

۱. با استفاده از دکمه زیر ربات رو به گروهتون اضافه کنید؛ ربات بطور خودکار گروه را تشخیص داده و تنظیم میکند.
۲. ربات رو ادمین کنین و دسترسی ها علاوه بر دسترسی حذف پیام ها؛ دسترسی anonymous ربات رو هم روشن کنید تا اعضا متوجه ربات تبلیغاتی نشوند.


⚠️ در انتخاب گروه دقت کنید؛ امکان ویرایش گروه تنها با حذف و اضافه کردن مجدد کانال وجود دارد!
⚠️ در انتخاب این ویژگی دقت کنید؛ گزینه ای برای غیر فعال سازی این امکان وجود ندارد!", ['reply_markup' =>
                                ['inline_keyboard' => [
                                    [['text' => "➕ افزودن به گروه ➕", 'url' => 'https://t.me/AdvertisingDarsbot?startgroup=' . $ChatId]],
                                ]]]);

                            $lists = $Admins[$uId]['Lists'];

                            $IK = BuildInlineKeyboard(array_keys($lists), array_keys($lists), 2);
                            $IK[count($IK)][0] = ['text' => '☑️', 'callback_data' => 'done'];
                            $ctx->sendMessage("🙄 لطفا یک یا چند دسته بندی را برای کانال انتخاب کنید:",
                                ['reply_markup' => ['inline_keyboard' => $IK]]);
                            nextStep('hAdd2List', $ctx);
                        } else {
                            $ctx->sendMessage('❌ لطفا پیام را از کانال فوروارد کنید! بنظر میرسد از چتی غیر از نوع کانال فوروارد شده...');
                        }
                    });
                } else {
                    $ctx->sendMessage('❌ ربات در کانال مورد نظر ادمین نیست! باید ادمین باشه خب ...');
                }
            },
            function (TelegramException $result) use ($ctx) {
                switch ($result->getErrorCode()) {
                    case '400':
                        $ctx->sendMessage('❌ ۴۰۰ - ایدی مورد نظر پیدا نشد! ');
                        break;
                    case '403':
                        $ctx->sendMessage('❌ ۴۰۳ - دسترسی به چنل نداریم! مطمئنید ربات را در کانال خصوصیتون ادمین کردید؟!');
                }
            },
        );
    } else {
        $ctx->sendMessage("❌ کانال از قبل ثبت شده بود، کانال دیگری انتخاب کنید!");
    }
}

function hAdd2List(Context $ctx)
{
    if ($ctx->getUpdate()->getUpdateType() === CallbackQuery::class) {
        $Admins = $ctx->get('gData') ?? [];
        $uId = $ctx->getEffectiveUser()->getId();

        if (isset($ctx->get('uData')['lists'])) {
            $lists = $ctx->get('uData')['lists'];
        } else {
            $lists = [];
            foreach ($Admins[$uId]['Lists'] as $key => $list) {
                $lists[$key]['sel'] = false;
            }
            setUserData($ctx, 'lists', $lists);
        }

        $show = function (array $lists) {
            $show = [];
            $listKey = array_keys($lists);
            foreach ($listKey as $key => $list) {
                if ($lists[$list]['sel'] === true) {
                    $show[$key] = '✔️' . $list;
                } else {
                    $show[$key] = $list;
                }
            }
            return $show;
        };

        $data = $ctx->getCallbackQuery()->getData();
        if ($data == 'done') {
            $count = 0;
            $listKey = array_keys($lists);

            $Channel = $ctx->get('uData')['Channel'];

            foreach ($listKey as $list) {
                if ($lists[$list]['sel'] === true) {
                    if (!in_array($Channel, $Admins[$uId]['Lists'][$list]['Channels'] ?? []))
                        $Admins[$uId]['Lists'][$list]['Channels'][] = $Channel;
                    $count++;
                }
            }
            if ($count < 1)
                $ctx->answerCallbackQuery(['text' => '⚠️ حداقل یکی را انتخاب کنید!']);
            else {
                $ctx->setGlobalDataItem('data', $Admins);
                deleteUserData($ctx, 'lists');
                deleteUserData($ctx, 'Channel');

                $ctx->answerCallbackQuery(['text' => "✅ با موفقیت به $count دسته اضافه شد!"]);
                hMain($ctx);
            }
        } else {
            if (in_array($data, array_keys($lists))) {
                $lists[$data]['sel'] = !$lists[$data]['sel'];

                $show = $show($lists);

                $IK = BuildInlineKeyboard(array_values($show), array_keys($lists), 2);
                $IK[count($IK)][0] = ['text' => '☑️', 'callback_data' => 'done'];

                $ctx->editMessageText('🎯 انتخاب شد!
💡موارد بیشتر انتخاب کنید یا ☑️ را بزنید...', ['reply_markup' => ['inline_keyboard' => $IK]]);

                setUserData($ctx, 'lists', $lists);
            }
        }
    }

}

$bot->middleware(function (Context $ctx, $next) use ($managers) {
    $ctx->getGlobalDataItem('data')->then(function ($gData) use ($ctx, $next, $managers) {
        $ctx->getUserDataItem('data')->then(function ($uData) use ($ctx, $next, $managers, $gData) {
            $uData['manager'] = in_array($ctx->getEffectiveUser()->getId(), $managers);
            if (!$uData['manager'] && !in_array($uData['owner'], $managers)) return;
            $ctx->set('uData', $uData);
            $ctx->set('gData', $gData);
            $next($ctx);
        });
    });
});

function setUserData(Context $ctx, $key, $input_data): void
{
    $data = $ctx->get('uData');
    $data[$key] = $input_data;
    $ctx->set('uData', $data);
    $ctx->setUserDataItem('data', $data);
}

function deleteUserData(Context $ctx, $key): void
{
    $data = $ctx->get('uData');
    unset($data[$key]);
    $ctx->set('uData', $data);
    $ctx->setUserDataItem('data', $data);
}

//save new Cache!
$bot->getLoop()->addPeriodicTimer(5, function () use ($cache) {
    $_cache = [];
    $_cache['data'] = $cache->data;
    $_cache['expires'] = $cache->expires;
    file_put_contents(__DIR__ . '/.cache', json_encode($_cache, JSON_PRETTY_PRINT));
});
$bot->run();

function BuildInlineKeyboard($text = [], $cb = [], int $sort = 1): array
{
    $Line = 0;
    $count_added = 0;
    $keyboard = [];
    foreach ($text as $add) {
        $keyboard[$Line][$count_added]['text'] = $add;
        $count_added += 1;
        if ($count_added == $sort) {
            $count_added -= $sort;
            $Line += 1;
        }
    }
    $Line = 0;
    $count_added = 0;
    foreach ($cb as $cb_add) {
        $keyboard[$Line][$count_added]['callback_data'] = $cb_add;
        $count_added += 1;
        if ($count_added == $sort) {
            $count_added -= $sort;
            $Line += 1;
        }
    }
    return $keyboard;
}

