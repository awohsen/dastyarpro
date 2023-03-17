<?php

namespace Components;

class Tools
{

    public static function toEnNumber($input): string
    {
        return strtr($input, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }

    public static function abstract($input, $allowed = 45): string
    {
        return mb_strlen($input) < $allowed ? $input : mb_substr($input, '0', $allowed, mb_detect_encoding($input)) . '...';
    }

    public static function bk(array|string...$buttons): array
    {
        array_unshift($buttons, 0);
        return call_user_func_array([__CLASS__, 'bkl'], $buttons);
    }

    public static function bkl(int $lineLimit, array|string...$buttons): array
    {
        $count = count($buttons);
        switch (true) {
            case $count < 1:
                return self::replyKeyboardRemove();
            case $count == 1:
                if (is_array($buttons) && is_array($buttons[0]))
                    $buttons = $buttons[0];
        }

        $lineBreaks = false;
        foreach ($buttons as $button) {
            if (is_array($button))
                $lineBreaks = true;
        }

        $line = 0;
        $index = 0;
        $keyboard = [];
        foreach ($buttons as $button) {
            if (is_array($button)) {
                foreach ($button as $innerIndex => $innerButton) {
                    $keyboard[$line][$innerIndex] = self::detectButtonType($innerButton);
                }
            } else if (is_string($button)) {
                $keyboard[$line][$index] = self::detectButtonType($button);
            }

            $index++;
            if ($lineBreaks || $index === $lineLimit) {
                $line++;
                $index = 0;
            }
        }

        return ['keyboard' => $keyboard];
    }

    public static function detectButtonType(string $button): array
    {
        if (str_starts_with($button, 'reqcontact_'))
            return ['text' => str_replace('reqcontact_', '', $button), 'request_contact' => true];
        if (str_starts_with($button, 'reqlocation_'))
            return ['text' => str_replace('reqlocation_', '', $button), 'request_location' => true];

        return ['text' => $button];
    }

    public static function BuildInlineKeyboard($text = [], $cb = [], int $sort = 1): array
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

    public static function replyKeyboardRemove(): array
    {
        return ['remove_keyboard' => true];
    }

    public static function replyKeyboard(array $keyboard, string $placeholder = null, bool $resize = true, bool $selective = false, bool $oneTime = false): array
    {
        if (count($keyboard) >= 1)
            if (!isset($keyboard['keyboard']) && !isset($keyboard['remove_keyboard'])) {
                array_unshift($keyboard, 0);
                $keyboard = call_user_func_array([__CLASS__, 'bkl'], $keyboard);
            }

        $replyMarkup['resize_keyboard'] = $resize;
        if (!empty($placeholder))
            $replyMarkup['input_field_placeholder'] = $placeholder;
        if ($selective)
            $replyMarkup['selective'] = true;
        if ($oneTime)
            $replyMarkup['one_time_keyboard'] = true;

        return ['reply_markup' => $keyboard + $replyMarkup];
    }

    public static function inputValidator(Context $ctx, array $values): bool
    {
        return $ctx->getMessage()->getText() !== null && in_array($ctx->getmessage()->getText(), $values);
    }

    public static function saveCache($cache): bool|int
    {
        $_cache = [];
        $_cache['data'] = $cache->data;
        $_cache['expires'] = $cache->expires;

        if (!file_exists(__DIR__ . '/../../data/')) {
            mkdir(__DIR__ . '/../../data/');
        }

        return file_put_contents(__DIR__ . '/../../data/.cache', json_encode($_cache));
    }

    public static function loadCache(): ArrayCache
    {
        $cache = new ArrayCache();

        if (file_exists($_cache = __DIR__ . '/../../data/.cache'))
            if (($_cache = json_decode(file_get_contents($_cache), 1)) !== null) {
                if (isset($_cache['data']))
                    $cache->data = $_cache['data'];
                if (isset($_cache['expires']))
                    $cache->expires = $_cache['expires'];
            }
        return $cache;
    }
}
