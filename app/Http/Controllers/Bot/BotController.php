<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Request;

class BotController extends Controller
{
    public $bot;
    public function __construct()
    {
        $this->bot = new \LINE\LINEBot(
            new \LINE\LINEBot\HTTPClient\CurlHTTPClient(env('CHANNEL_ACCESS_TOKEN')),
                ['channelSecret' => env('CHANNEL_SECRET')]
        );
    }

/*
    public function sendMsg()
    {
        $post = Request::all();
        $msg = $post['msg'];
        $sendMsg = 'Your Message : ' . $post['msg'];

        $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($msg);
        $this->bot->pushMessage($post['id'], $textMessageBuilder);
        file_put_contents("php://stderr", "$sendMsg".PHP_EOL);
    }
*/
    public function callBack()
    {
        //get line content
        $jsonString = file_get_contents('php://input');
        $decode = json_decode($jsonString);
        file_put_contents("php://stderr", "$jsonString".PHP_EOL);

        //get info
        $replyToken = $decode->events[0]->replyToken;
        $text = $decode->events[0]->message->text;
        $messageId = $decode->events[0]->message->id;
        $type = $decode->events[0]->source->type;
        $userMessage = 'Message : ' . $text;


        if ($type == 'user') {
            $sendId = $messageId;
        } else if ($type == 'group') {
            $sendId = $decode->events[0]->source->groupId;
        }  else if ($type == 'room') {
            $sendId = $decode->events[0]->source->roomId;
        }

        //content
        $echoId = 'id : ' . $sendId;
        $response = $this->bot->getMessageContent($sendId);
        $contentString = json_encode($response);

        file_put_contents("php://stderr", "$type".PHP_EOL);
        file_put_contents("php://stderr", "$echoId".PHP_EOL);
        file_put_contents("php://stderr", "$userMessage".PHP_EOL);

        //匯率api
        $currency = null;
        try{
            $content = file_get_contents('http://rate.asper.tw/currency.json');    
            $currency = json_decode($content);
        } catch (Exception $e) {

        }
        
        $result = $this->changeName($text, $currency);

        if ( ! empty($result)) {
            //send
            $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($result);
            $this->bot->replyMessage($replyToken, $textMessageBuilder);
        }
    }

    /*
     * 美元(USD) 港幣(HKD) 英鎊(GBP) 澳幣(AUD) 加拿大幣(CAD) 新加坡幣(SGD) 瑞士法郎(CHF)
     * 日圓(JPY) 南非幣(ZAR) 瑞典克朗(SEK) 紐西蘭幣(NZD) 泰銖(THB) 菲律賓披索(PHP)
     * 印尼盾(IDR) 歐元(EUR) 韓幣(KRW) 越南幣(VND) 馬來西亞幣(MYR) 人民幣(CNY)
     */
    public function changeName($typeName, $sourceData)
    {
        $funny = $this->funny($typeName);
        if ( ! empty($funny)) {
            return $funny;
        }

        if($sourceData == null) return '說:'.$typeName;

        switch ($typeName) {
            case '日幣':
            case '日圓':
                $money = $sourceData->rates->JPY;
                break;

            case '美元':
            case '美金':
                $money = $sourceData->rates->USD;
                break;

            case '英鎊':
            case '英金':
                $money = $sourceData->rates->GBP;
                break;

            case '印尼盾':
                $money = $sourceData->rates->IDR;
                break;

            case '港幣':
                $money = $sourceData->rates->HKD;
                break;

            case '韓幣':
                $money = $sourceData->rates->KRW;
                break;

            case '澳幣':
                $money = $sourceData->rates->AUD;
                break;

            case '歐元':
                $money = $sourceData->rates->EUR;
                break;

            case '泰銖':
            case '泰珠':
            case '泰豬':
                $money = $sourceData->rates->THB;
                break;

            case '人民幣':
                $money = $sourceData->rates->CNY;
                break;
            default:
                return '';
        }

        //check zero
        if ($money->buySpot == 0) {
            $round = '無資料';
        } else {
            $round = round(1 / $money->buySpot, 4);
        }

        //to string
        $txt = "買入現金 : " . $money->buyCash;
        $txt .= "\n";
        $txt .= "買入即期 : " . $money->buySpot;
        $txt .= "\n";
        $txt .= "賣出現金 : " . $money->sellCash;
        $txt .= "\n";
        $txt .= "賣出即期 : " . $money->sellSpot;
        $txt .= "\n";
        $txt .= "所以買入一台幣 = " . $round . $typeName;
        $txt .= "\n";
        $txt .= "\n";
        $txt .= "\n";
        $txt .= "更新時間 : " . Carbon::createFromTimestamp($sourceData->updateTime)->format('Y-m-d H:i:s');

        return $txt;
    }

    public function funny($typeName)
    {
        switch (strtoupper($typeName)) {
            case "存款": return '八億七千萬'; break;
            case 520: return 'No~'; break;
            case 487: return 540; break;
            default: return ''; break;
        }
    }
}
