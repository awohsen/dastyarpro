<?php

namespace Sections;

use Components\Context;
use Components\Tools;
use React\MySQL\Exception;
use React\Promise\PromiseInterface;
use Zanzara\Telegram\Type\Chat;
use Zanzara\Telegram\Type\ChatMember;
use Zanzara\Telegram\Type\Message;
use Zanzara\Telegram\Type\Response\TelegramException;
use function Components\ib;
use function React\Async\coroutine;

class ChannelsSection
{
    public function __invoke(Context $ctx, $param = null): void
    {
        if (isset($param)) {
            // todo: show's the channel info
            return;
        }

        coroutine(function () use ($ctx, $param) {
            try {
                $channels = (yield $ctx->getUserChannels())->resultRows;
                if (isset($channels) && count($channels) >= 1) {
                    $show = [];
                    foreach ($channels as $channel) {
                        $channelID = $channel['channel_id'];
                        if (isset($channel['display_name'])) {
                            $show[$channel['display_name']] = 'CHANNEL_DELETE_' . $channelID; // todo: change DELETE_ to INFO_
                        } else {
                            $displayName = yield $this->getChannelDisplayName($channelID, $ctx);
                            if (!empty($displayName)) {
                                $show[$displayName] = 'INFO_' . $channelID;
                                yield $ctx->updateUserChannel($channelID, 'display_name', $displayName);
                            }
                        }
                    }

                    $keyboard = Tools::BuildInlineKeyboard(array_keys($show), array_values($show), 2);
                    $keyboard[] = [ib('➕', 'new_channel')];

                    $ctx->sendOrEditMessage('💡برای حذف کانال روی اسم آن کلیک کنید...

➕با دکمه زیر چنل های خود را اضافه کنید.',
                        Tools::replyKeyboard(['inline_keyboard' => $keyboard]));
                } else {
                    $ctx->sendOrEditMessage('💤 لیست کانال های شما خالیست!

➕ با دکمه زیر کانال های جدید اضافه کنید.',
                        Tools::replyInlineKeyboard(['inline_keyboard' => [[ib('➕', 'new_channel')]]])
                    );
                }
            } catch (\Exception $e) {
                $ctx->sendMessage('👾 خطایی در دریافت لیست کانال های شما رخ داد!');
                print_r($e);
                $ctx->log()->error($e->getMessage(), ['code' => $e->getCode(), 'trace' => $e->getTraceAsString()]);
            }
        });
    }

    public static function CallbackDataHandler(Context $ctx, $param): void
    {
        $param = explode('_', $param, 2);
        switch ($param[0]) {
            case 'DELETE':
                coroutine(function () use ($ctx, $param) {
                    try {
                        //todo: limit how many channels each person could add!

                        $channel = (yield $ctx->getChannelByID($param[1]))->resultRows;
                        if (count($channel) !== 0) {
                            if ($channel[0]['owner_id'] == $ctx->getEffectiveUser()->getId()) {
                                yield $ctx->deleteChannelByID($param[1]);
                                $ctx->answerCallbackQuery(['text' => '🗑 کانال انتخاب شده با موفقیت حذف شد!', 'show_alert' => true]);

                                if ($ctx->getCallbackQuery()->getMessage()->getDate() < time() - 86400){
                                    $ctx->getUpdate()->setUpdateType(Message::class);
                                }

                                (new ChannelsSection)($ctx);
                            }
                        }

                    } catch (Exception $exception) {
                        var_dump($exception); //fixme database err
                        return;
                    }
                });
        }
    }

    public static function newChannel(Context $ctx, $newChannelID = null): void
    {
        if (!isset($newChannelID)) {
            if ($ctx->getCallbackQuery()) $ctx->answerCallbackQuery();
            self::receiveChannelID($ctx);
            return;
        }
        coroutine(function () use ($ctx, $newChannelID) {
            // check channel in database:
            try {
                //todo: limit how many channels each person could add!

                $channel = (yield $ctx->getChannelByID($newChannelID))->resultRows;

                if (count($channel) !== 0) {
                    if ($channel[0]['owner_id'] == $ctx->getEffectiveUser()->getId()) {
                        $ctx->sendMessage('❎ این کانال قبلا به ربات اضافه شده!');
                    } else {
                        // checking if weather it's the original owner or another admin or random guy trying to take over this channel
                        /* @var ChatMember $user */
                        $user = yield $ctx->getChatMember($newChannelID, $ctx->getEffectiveUser()->getId());
                        if (!in_array($user->getStatus(), ['creator', 'administrator'])) {
                            yield $ctx->sendMessage('❌');
                            $ctx->sendMessage('💬 شما دسترسی لازم برای اضافه کردن ربات را ندارید!');
                            return;
                        }
                        switch ($user->getStatus()) {
                            case 'creator':
                                // take over the ownership in the robot!
                                yield $ctx->updateUserChannel($newChannelID, 'owner_id', $ctx->getEffectiveUser()->getId());
                                yield $ctx->sendMessage('✅');
                                $ctx->sendMessage('💬 شما دسترسی مدیریت کانال در ربات دستیار را به خودتان منتقل کردید!');
                                // todo: ask if he wants to clear current settings like admins and other stuff
                                break;
                            case 'administrator':
                                $ctx->sendMessage('❎ این کانال قبلا توسط ادمین دیگری به ربات اضافه شده!');
                                // todo: if in the channel settings admins are allowed to have panel in bot (which is set by owner strictly) he can add channel by being admin in it not as owner_id to this!
                                break;
                        }

                    }
                    return;
                }

                // currently we only accept channel chats to be added to channels! (no cap)
                /* @var Chat $channel */
                $channel = yield $ctx->getChat($newChannelID);
                if ($channel->getType() !== 'channel') {
                    $ctx->sendMessage('💬 لطفا یک کانال را انتخاب کنید!');
                    return;
                }

                // check if the user who is adding this chat to bot, is indeed an admin himself
                /* @var ChatMember $user */
                $user = yield $ctx->getChatMember($newChannelID, $ctx->getEffectiveUser()->getId());
                if (!in_array($user->getStatus(), ['creator', 'administrator'])) {
                    yield $ctx->sendMessage('❌');
                    $ctx->sendMessage('💬 شما دسترسی لازم برای اضافه کردن ربات را ندارید!');
                    return;
                }
                $displayName = yield self::getChannelDisplayName($newChannelID, $ctx);
                yield $ctx->addUserChannel(
                    $newChannelID,
                    $ctx->getEffectiveUser()->getId(),
                    $channel->getLinkedChatId(),
                    $channel->getUsername(),
                    !empty($displayName) ? $displayName : null
                );
                yield $ctx->sendMessage('✅');
                $ctx->sendMessage('💬 کانال شما با موفقیت اضافه شد!');
            } catch (TelegramException $exception) {
                if ($exception->getErrorCode() === 400) {
                    switch ($exception->getDescription()) {
                        case 'Bad Request: chat not found':
                            yield $ctx->sendMessage('❌');
                            $ctx->sendMessage('💬 خطا! ربات به چت دسترسی ندارد.');
                            return;
                    }
                }
                var_dump($exception); //fixme handle all telegram api types err
            } catch (Exception $exception) {
                var_dump($exception); //fixme database err
                return;
            }
        });
    }

    public static function receiveChannelID(Context $ctx): void
    {
        $ctx->sendMessage('💬 با دکمه زیر کانال مورد نظر را انتخاب کنید:', ['reply_markup' => ['keyboard' => [
            [['text' => '«انتخاب کانال»', 'request_chat' => [
                'request_id' => crc32('addChannel'),
                'chat_is_channel' => true,
                'user_administrator_rights' => [
                    'can_manage_chat' => true,
                    'can_post_messages' => true
                ],
                'bot_administrator_rights' => [
                    'can_manage_chat' => true,
                    'can_post_messages' => true
                ],
                'bot_is_member' => true
            ]]],
            [['text' => 'لغو']]
        ]]]);
    }

    private static function getChannelDisplayName($channelID, Context $ctx): PromiseInterface
    {
        return coroutine(function () use ($channelID, $ctx) {
            try {
                /* @var Chat $chat */
                $chat = yield $ctx->getChat($channelID);
                if ($chat->getUsername()) {
                    return Tools::abstract('@' . $chat->getUsername(), 32, '');
                }
                return Tools::abstract($chat->getTitle(), 32);
            } catch (\Exception $e) {
                $ctx->sendMessage("👾 خطایی در دستیابی به نام نمایشی کانال <code>$channelID</code> رخ داد!");
                print_r($e);
                $ctx->log()->error($e->getMessage(), ['code' => $e->getCode(), 'trace' => $e->getTraceAsString()]);
                return false;
            }
        });
    }

}