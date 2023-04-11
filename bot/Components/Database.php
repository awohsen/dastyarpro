<?php

namespace Components;

use React\MySQL\ConnectionInterface;
use React\Promise\PromiseInterface;

trait Database
{

    /**
     * @var ConnectionInterface
     */
    public ConnectionInterface $connection;

    public function getUserChannels($owner_id = null): PromiseInterface
    {
        return $this->connection->query(
            'SELECT * FROM channels WHERE owner_id = ?',
            [$owner_id ?? $this->getEffectiveUser()->getId()]
        );
    }

    public function addUserChannel($channel_id, $owner_id = null, $linked_chat_id = null, $username = null, $display_name = null): PromiseInterface
    {
        $this->deleteUserDataItem('channels');
        return $this->connection->query(
            'INSERT INTO channels (channel_id, owner_id, linked_chat_id, username, display_name) VALUES (? , ? , ?, ?, ?)',
            [
                $channel_id,
                $owner_id ?? $this->getEffectiveUser()->getId(),
                $linked_chat_id,
                $username,
                $display_name
            ]
        );
    }

    public function updateUserChannel($channel_id, $key, $value): PromiseInterface
    {
        $this->deleteUserDataItem('channels');
        return $this->connection->query("UPDATE channels SET $key = ? WHERE channel_id = ?", [$value, $channel_id]);
    }

    public function getChannelByID($channel_id): PromiseInterface
    {
        return $this->connection->query('SELECT * FROM channels WHERE channel_id = ? LIMIT 1', [$channel_id]);
    }

    public function deleteChannelByID($channel_id): PromiseInterface
    {
        $this->deleteUserDataItem('channels');
        return $this->connection->query('DELETE FROM channels WHERE channel_id = ?', [$channel_id]);
    }

    public function createUserAd($message_id, array $destinations, int $ad_id = null, array $settings = null, string $display_name = null): PromiseInterface
    {
        return $this->connection->query(
            'INSERT INTO ads (ad_id, owner_id, message_id, destinations, settings, display_name) VALUES (? , ? , ?, ?, ?, ?)',
            [
                $ad_id ?? rand(111111111, 999999999),
                $this->getEffectiveUser()->getId(),
                $message_id,
                json_encode($destinations),
                json_encode($settings ?? ['mode' => 'indirect']),
                $display_name
            ]
        );
    }

    public function getUserAd($ad_id): PromiseInterface
    {
        return $this->connection->query('SELECT * FROM ads WHERE ad_id = ?', [$ad_id]);
    }

    public function updateUserAd($ad_id, $key, $value): PromiseInterface
    {
        return $this->connection->query("UPDATE ads SET $key = ? WHERE ad_id = ?", [$value, $ad_id]);
    }

    public function getUserAds($owner_id = null): PromiseInterface
    {
        return $this->connection->query(
            'SELECT * FROM ads WHERE owner_id = ?',
            [$owner_id ?? $this->getEffectiveUser()->getId()]
        );
    }

    public function deleteUserAd($ad_id): PromiseInterface
    {
        return $this->connection->query('DELETE FROM ads WHERE ad_id = ?', [$ad_id]);
    }
}
