<?php

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';

use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Tools;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\RPCErrorException;

/**
 * Event handler class.
 */
class MyEventHandler extends EventHandler
{
    /**
     * @var int|string Username or ID of bot admin
     */
    const ADMIN = "@simocosimo"; 
    /**
     * Get peer(s) where to report errors
     *
     * @return int|string|array
     */
    public function getReportPeers()
    {
        return [self::ADMIN];
    }
    /**
      * Called on startup, can contain async calls for initialization of the bot
      *
      * @return void
      */
    public function onStart()
    {
    }
    /**
     * Handle updates from supergroups and channels
     *
     * @param array $update Update
     * 
     * @return void
     */
    public function onUpdateNewChannelMessage(array $update): \Generator
    {
        return onUpdateNewMessage($update);
    }
    /**
     * Handle updates from users.
     *
     * @param array $update Update
     *
     * @return \Generator
     */
    public function onUpdateNewMessage(array $update): \Generator
    {
        if ($update['message']['_'] === 'messageEmpty' || 
            $update['message']['out'] ?? false) {
                return;
        }
        
        // If it's a user chat
        if($update['message']['to_id']['_'] !== 'peerChat') {
            $res = \json_encode($update, JSON_PRETTY_PRINT);

            try {
                yield $this->messages->sendMessage([
                    'peer' => $update, 
                    'message' => "<code>$res</code>", 
                    'reply_to_msg_id' => isset($update['message']['id']) ? $update['message']['id'] : null, 
                    'parse_mode' => 'HTML']);

                if (isset($update['message']['media']) && 
                    $update['message']['media']['_'] !== 'messageMediaGame') {
                        yield $this->messages->sendMedia([
                            'peer' => $update, 
                            'message' => $update['message']['message'], 
                            'media' => $update]);
                }
            } catch (RPCErrorException $e) {
                $this->report("Surfaced: $e");
            } catch (Exception $e) {
                if (\stripos($e->getMessage(), 'invalid constructor given') === false) {
                    $this->report("Surfaced: $e");
                }
            }
        } else {
            // if it's a group chat
            try {
                // $groupId = $update['update'];
                $groupInfos = yield $this->getPwrChat($update);
                $groupTitle = $groupInfos['title'];
                $reportMessage = $update['message']['message'];
                $reportSender = yield $this->users->getFullUser([
                    'id' => $update['message']['from_id']
                ]);
                $senderUsername = $reportSender['user']['username'];
                //$res = \json_encode($reportSender, JSON_PRETTY_PRINT);
    
                if(strpos($update['message']['message'], "@admin") !== FALSE) {
                    yield $this->messages->sendMessage([
                        'peer' => "@simocosimo", 
                        'message' => "Segnalazione dal gruppo <b>$groupTitle</b> da parte di @$senderUsername:<br><code>$reportMessage</code>", 
                        // 'reply_to_msg_id' => isset($update['message']['id']) ? $update['message']['id'] : null, 
                        'parse_mode' => 'HTML']); 

                    yield $this->messages->sendMessage([
                        'peer' => $update, 
                        'message' => "Segnalazione inviata agli admin!", 
                        'reply_to_msg_id' => isset($update['message']['id']) ? $update['message']['id'] : null, 
                        'parse_mode' => 'HTML']); 
                } 
            } catch (RPCErrorException $e) {
                $this->report("Surfaced: $e");
            } catch (Exception $e) {
                if (\stripos($e->getMessage(), 'invalid constructor given') === false) {
                    $this->report("Surfaced: $e");
                }
            }
        }
    }
}

$settings = [
    'logger' => [
        'logger_level' => 5
    ],
    'serialization' => [
        'serialization_interval' => 30,
    ],
];

$MadelineProto = new \danog\MadelineProto\API('session.madeline');
$MadelineProto->startAndLoop(MyEventHandler::class);