<?php

/**
 * Copyright 2016 LINE Corporation
 *
 * LINE Corporation licenses this file to you under the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

require_once('./LINEBotTiny.php');

$channelAccessToken = '<CHANNEL-ACCESS-TOKEN>';
$channelSecret = '<CHANNEL-SECRET>';

$client = new LINEBotTiny($channelAccessToken, $channelSecret);
foreach ($client->parseEvents() as $event) {
    switch ($event['type']) {
        case 'message':
            $message = $event['message'];
            switch ($message['type']) {
                case 'image':
                    // 0. Use messageId as image filename later
                    $messageId = $message['id'];
                        
                    // 1. Get content and save as file
                    $header = array(
                        'Authorization: Bearer ' . $channelAccessToken,
                    );
            
                    $context = stream_context_create(array(
                        "http" => array(
                            "method" => "GET",
                            "header" => "Authorization: Bearer {$channelAccessToken}\r\n",
                        ),
                    ));
            
                    $response = file_get_contents("https://api-data.line.me/v2/bot/message/{$messageId}/content", false, $context);
        
                    $thePhotoFilename = "{$messageId}.jpg";
        
                    file_put_contents('<YOUR-WEB-SERVER-PATH>' . $thePhotoFilename, $response);

                    // 2. Call Azure image analyze API
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , 'Ocp-Apim-Subscription-Key: <YOUR-AZURE-KEY>'));
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_URL, 'https://<YOUR-ENDPOINT>m/vision/v3.2/analyze?visualFeatures=Categories,Description&details=Landmarks');
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
                        'url' => "<YOUR-WEB-SERVER-PATH>/{$messageId}.jpg",
                    )));
                    $response = curl_exec($curl);
                    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    curl_close($curl);
            
                    $result = "無法辨識！";
            
                    if ($httpCode == 200) {
                        $obj = json_decode($response);
            
                        $result = $obj->description->captions[0]->text;
                    }
            
                    $client->replyMessage([
                        'replyToken' => $event['replyToken'],
                        'messages' => [
                            [
                                'type' => 'text',
                                'text' => '🤖 我覺得這張照片看起來像是……'
                            ],
                            [
                                'type' => 'text',
                                'text' => $result
                            ],
                            [
                                'type' => 'text',
                                'text' => '🖼️ 歡迎再次上傳照片，讓我看圖說故事給你聽吧。'
                            ]
                        ]
                    ]);

                default:
                    error_log('Unsupported message type: ' . $message['type']);

                    $client->replyMessage([
                        'replyToken' => $event['replyToken'],
                        'messages' => [
                            [
                                'type' => 'text',
                                'text' => '🤖 我是看圖說故事AI機器人，我只吃照片喔。'
                            ]
                        ]
                    ]);

                    break;
            }
            break;
        default:
            error_log('Unsupported event type: ' . $event['type']);
            break;
    }
};
