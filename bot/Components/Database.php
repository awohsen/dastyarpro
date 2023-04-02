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
        return $this->connection->query("UPDATE channels SET $key = ? WHERE channel_id = ?", [$value, $channel_id]);
    }

    public function getChannelByID($channel_id): PromiseInterface
    {
        return $this->connection->query('SELECT * FROM channels WHERE channel_id = ? LIMIT 1', [$channel_id]);
    }
    public function deleteChannelByID($channel_id): PromiseInterface
    {
        return $this->connection->query('DELETE FROM channels WHERE channel_id = ?', [$channel_id]);
    }

}