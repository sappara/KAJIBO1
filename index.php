<?php

// Composerでインストールしたライブラリを一括読み込み
require_once __DIR__ . '/vendor/autoload.php';
// テーブル名を定義
define('TABLE_NAME_ROOMS', 'rooms');
define('TABLE_NAME_STEP4S', 'step4s');
define('TABLE_NAME_STEP5S', 'step5s');
define('TABLE_NAME_STEP6S', 'step6s');
define('TABLE_NAME_STEP9S', 'step9s');
define('TABLE_NAME_STEP10S', 'step10s');
define('TABLE_NAME_STEP11S', 'step11s');
define('TABLE_NAME_STEP12S', 'step12s');

// アクセストークンを使いCurlHTTPClientをインスタンス化
$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
// CurlHTTPClientとシークレットを使いLINEBotをインスタンス化
$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
// LINE Messaging APIがリクエストに付与した署名を取得
$signature = $_SERVER['HTTP_' . \LINE\LINEBot\Constant\HTTPHeader::LINE_SIGNATURE];

// 署名が正当かチェック。正当であればリクエストをパースし配列へ
// 不正であれば例外の内容を出力
try {
  $events = $bot->parseEventRequest(file_get_contents('php://input'), $signature);
} catch(\LINE\LINEBot\Exception\InvalidSignatureException $e) {
  error_log('parseEventRequest failed. InvalidSignatureException => '.var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownEventTypeException $e) {
  error_log('parseEventRequest failed. UnknownEventTypeException => '.var_export($e, true));
} catch(\LINE\LINEBot\Exception\UnknownMessageTypeException $e) {
  error_log('parseEventRequest failed. UnknownMessageTypeException => '.var_export($e, true));
} catch(\LINE\LINEBot\Exception\InvalidEventRequestException $e) {
  error_log('parseEventRequest failed. InvalidEventRequestException => '.var_export($e, true));
}

// 配列に格納された各イベントをループで処理
foreach ($events as $event) {

  // イベントがPostbackEventクラスのインスタンスであれば
  if ($event instanceof \LINE\LINEBot\Event\PostbackEvent) {

    // リッチコンテンツがタップされた時
    if(substr($event->getPostbackData(), 0, 4) == 'cmd_') {

      // ーーーーーーーーーーーールームのメニュー関連ーーーーーーーーーーーーーーーーー
      // ルーム作成
      if(substr($event->getPostbackData(), 4) == 'newroom') {
        // ユーザーが未入室の時
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          // ルームを作成し入室後ルームIDを取得
          $roomId = createRoomAndGetRoomId($event->getUserId());
          // ルームIDをユーザーに返信
          replyMultiMessage($bot,
            $event->getReplyToken(),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('ルームを作成し、入室しました。ルームIDは'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($roomId),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('です。'));
        }
        // 既に入室している時
        else {
          replyTextMessage($bot, $event->getReplyToken(), '既に入室済みです。');
        }
      }
      // 入室
      else if(substr($event->getPostbackData(), 4) == 'enter') {
        // ユーザーが未入室の時
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームIDを入力してください。');
        } else {
          replyTextMessage($bot, $event->getReplyToken(), '入室済みです。');
        }
      }
      // 退室の確認ダイアログ
      else if(substr($event->getPostbackData(), 4) == 'leave_confirm') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '本当に退室しますか？', '本当に退室しますか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_leave'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '退室しません。ルームを維持します。'));
          // この時の「いいえ」はどこにも繋がっていない。これで終了。
      }
      // 退室
      else if(substr($event->getPostbackData(), 4) == 'leave') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          leaveRoom($event->getUserId());
          replyTextMessage($bot, $event->getReplyToken(), '退室しました。');
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入っていません。');
        }
      }

      // ーーーーーーーーーーーー家事のメニュー（pushMessage関連）ーーーーーーーーーーーーーーーーー

      // 作業終了の報告
      else if(substr($event->getPostbackData(), 4) == 'end_confirm') {
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入っていません。');
        } else {
          replyConfirmTemplate($bot, $event->getReplyToken(), '作業完了しましたか？メンバー皆様に完了報告を送信します。', '作業完了しましたか？メンバー皆様に完了報告を送信します。',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_end'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', 'おつかされまでした🍺'));
        }
      }
      // 終了
      else if(substr($event->getPostbackData(), 4) == 'end') {
        endKaji($bot, $event->getUserId());
      }

      // ーーーーーーーーーーーー家事マニュアルの選択肢ーーーーーーーーーーーーーーーーー

      // 家事stepの選択肢ボタンをタイムラインに投稿
      else if(substr($event->getPostbackData(), 4) == 'kaji'){
        replyQuickReplyButton($bot, $event->getReplyToken(), '洗濯マニュアルを個別stepで見れるよ。ボタンを押してね。',
        new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('1)異物混入チェック', 'step1')),
          new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('2)泥汚れの下洗い', 'step2')),
          new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('3)洗濯ネットで保護', 'step3')),
          new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('4)洗濯ネットの収納場所', 'step4')),
          new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('5)洗剤の収納場所', 'step5')),
          new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('6)洗剤の種類', 'step6')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('7)洗濯機の水量', 'step7')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('8)洗剤の量と水量の関係性', 'step8')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('9)洗剤の量について', 'step9')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('10)洗剤の投入口', 'step10')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('11)柔軟剤について', 'step11')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('12)柔軟剤の投入口', 'step12')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('13)洗濯機スタート', 'step13'))
        );
      }

      // ーーーーーーーーーーーーリッチメニュー関連ーーーーーーーーーーーーーーーーー

      // cmd_how_to_use
      else if(substr($event->getPostbackData(), 4) == 'how_to_use'){
        // $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('使い方の説明'));
        $headerTextComponents = [new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('このアプリの使い方を体験できます。',null,null,'xs','center', null, true, null, null, '#0d1b2a')];

        $actionBuilder1_1 = new \LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder('一覧で見る','https://liff.line.me/1654069050-OPNWVd3j');
        $actionBuilder1_2 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('個別に見る','cmd_kaji');
        $actionBuilder2_1 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('ルームを作る','cmd_newroom');
        $actionBuilder2_4 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('ルームに入る','cmd_enter');
        $actionBuilder3_1 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('完了報告','cmd_end_confirm');
        $actionBuilder4_1 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('登録','cmd_insert');
        $actionBuilder4_2 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('修正','cmd_update');
        $actionBuilder5_1 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('写真登録','cmd_photo');
        

        $bodyBoxComponentSteps = [
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('【使い方の説明】',null,null,'xs','center', null, true, null, 'bold', '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\FillerComponentBuilder(),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１：家事マニュアル（洗濯機の使い方）',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('1-1：一度に全てのステップを見たい場合',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder1_1),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('1-2：各ステップを個別に見たい場合',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder1_2),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('２：二人で使う',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('2-1：ルームに入る',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder2_1),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('2-2：相手にルームナンバーを伝える',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('2-3：Bot(KAJIBO)と友達になってもらう',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('2-4：ルームに入ってもらう',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder2_4),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('３：家事が完了したら',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('3-1：作業完了の報告LINEを送る（同じルームに入室している全員に送信します）',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder3_1),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('４：家事マニュアルを変える',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('4-1：カスタマイズするステップを選ぶ（必須：ルームへの事前入室）',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder4_1),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('4-2：後から修正も可能',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder4_2),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('５：おためし',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('5-1：写真の変更（一枚だけ変更可能）',null,null,null,null, null, true, null, null, '#0d1b2a'),
          new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder5_1),
        ];

        $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $bodyPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $bodyPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;

        $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
        $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/object_76.jpg';
        $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
        replyFlexMessageForModification($bot, $event->getReplyToken(), '使い方の説明', $layout::VERTICAL, $headerTextComponents, $bodyBoxComponentSteps, $heroImageUrl, $aspectMode::COVER, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingTop::MD, $bodyPaddingBottom::MD
        );


      }
      // cmd_kaji_menu
      else if(substr($event->getPostbackData(), 4) == 'kaji_menu'){
        $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('家事をする時のメニューへ', linkToUser(getenv('CHANNEL_ACCESS_TOKEN'), $event->getUserId(), 'richmenu-d182fe2f083258f273d5e1035bb71dfe')));
      }
      // cmd_room_menu
      else if(substr($event->getPostbackData(), 4) == 'room_menu'){
        $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('ルームのメニューへ', linkToUser(getenv('CHANNEL_ACCESS_TOKEN'), $event->getUserId(), 'richmenu-0497d90d09a9dc238929295866e324d0')));
      }
      // cmd_modification_menu
      else if(substr($event->getPostbackData(), 4) == 'modification_menu'){
        $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('カスタマイズのメニューへ', linkToUser(getenv('CHANNEL_ACCESS_TOKEN'), $event->getUserId(), 'richmenu-483be03d906642db37c9bf40a14c421b')));
        // PHP Fatal error:  Uncaught TypeError: Argument 2 passed to LINE\LINEBot::replyMessage() must be an instance of LINE\LINEBot\MessageBuilder, instance of LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder given, called in /app/index.php on line 144 and defined in /app/vendor/linecorp/line-bot-sdk/src/LINEBot.php:125
      }
      // cmd_main_menu
      else if(substr($event->getPostbackData(), 4) == 'main_menu'){
        $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('メインメニューに戻る', linkToUser(getenv('CHANNEL_ACCESS_TOKEN'), $event->getUserId(), 'richmenu-04eeffc6e1d8b4d8d6e5a07354195c9b')));
        // $boundsBuilder1 = new \LINE\LINEBot\RichMenuBuilder\RichMenuAreaBoundsBuilder(0,0,300,405);
        // $actionBuilder1 =  new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('メインメニューに戻る','cmd_main_menu');
        // $boundsBuilder2 = new \LINE\LINEBot\RichMenuBuilder\RichMenuAreaBoundsBuilder(300,0,300,405);
        // $actionBuilder2 =  new \LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder('一覧で見る','https://liff.line.me/1654069050-OPNWVd3j');
        // $boundsBuilder3 = new \LINE\LINEBot\RichMenuBuilder\RichMenuAreaBoundsBuilder(600,0,300,405);
        // $actionBuilder3 =  new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('個別に見る','cmd_kaji');
        // $boundsBuilder4 = new \LINE\LINEBot\RichMenuBuilder\RichMenuAreaBoundsBuilder(900,0,300,405);
        // $actionBuilder4 =  new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('完了報告','cmd_end_confirm');
        // $richMenuAreaBuilder=[
        //   new LINE\LINEBot\RichMenuBuilder\RichMenuAreaBuilder($boundsBuilder1, $actionBuilder1),
        //   new LINE\LINEBot\RichMenuBuilder\RichMenuAreaBuilder($boundsBuilder2, $actionBuilder2),
        //   new LINE\LINEBot\RichMenuBuilder\RichMenuAreaBuilder($boundsBuilder3, $actionBuilder3),
        //   new LINE\LINEBot\RichMenuBuilder\RichMenuAreaBuilder($boundsBuilder4, $actionBuilder4)
        // ];
        // $richmenuId = createNewRichmenuKaji(getenv('CHANNEL_ACCESS_TOKEN'), getenv('CHANNEL_SECRET'), $richMenuAreaBuilder);
        // $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($richmenuId));
        // $richmenuId = createNewRichmenuKaji(getenv('CHANNEL_ACCESS_TOKEN'));
        // $bot->replyMessage($event->getReplyToken(),new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($richmenuId));


        // uploadImageToRichmenuKaji(getenv('CHANNEL_ACCESS_TOKEN'), getenv('CHANNEL_SECRET'), $richmenuId);

        // linkToUser(getenv('CHANNEL_ACCESS_TOKEN'), getenv('CHANNEL_SECRET'), $event->getUserId(), $richmenuId);
      // }
      //   // curl -v -X POST https://api.line.me/v2/bot/user/{userId}/richmenu/{richMenuId} \
      //   // -H "Authorization: Bearer {channel access token}"
        // $userId = $event->getUserId();
        // $channelaccesstoken = getenv('CHANNEL_ACCESS_TOKEN');
        // $url = 'https://api.line.me/v2/bot/user/'.$userId.'/richmenu/richmenu-d182fe2f083258f273d5e1035bb71dfe';
        // $curl = curl_init($url);
        // $options = array(
        //   //HEADER
        //   CURLOPT_HTTPHEADER => array(
        //       'Authorization: Bearer '.$channelaccesstoken,
        //   ),
        //   //Method
        //   CURLOPT_POST => true,//POST
        //   //body
        //   CURLOPT_POSTFIELDS => http_build_query($post_args),
        //   // 注意点、空のボディを送信するとき（APIのPOSTだけをCall）のような場合でもフィールドは必須。空文字をセットしないとContent-Length: -1 を送信してしまう。
        // );
        // //set options
        // curl_setopt_array($curl, $options);
        // // request
        // $result = curl_exec($curl);
      //   // 以下サンプルは動かず
        // $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
        // $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
        // $bot->linkRichMenu($event->getUserId(), 'richmenu-d182fe2f083258f273d5e1035bb71dfe');
      }

      // ーーーーーーーーーーーーカスタマイズのメニュー関連ーーーーーーーーーーーーーーーーー

      // cmd_insert
      else if(substr($event->getPostbackData(), 4) == 'insert'){
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), '登録するにはルームに入ってください。');
        } else {
          $headerTextComponents = [new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('家事マニュアルをカスタマイズできます。',null,null,'xs','center', null, true, null, null, '#0d1b2a')];

          $actionBuilder4 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を登録する','cmd_create4');
          $actionBuilder5 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を登録する','cmd_create5');
          $actionBuilder6 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を登録する','cmd_create6');
          $actionBuilder9 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を登録する','cmd_create9');
          $actionBuilder10 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を登録する','cmd_create10');
          $actionBuilder11 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を登録する','cmd_create11');
          $actionBuilder12 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を登録する','cmd_create12');

          $bodyBoxComponentSteps = [
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('４）洗濯ネットの収納場所',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットは「〇〇」を探してください。',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder4),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('５）洗剤の収納場所',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤は「〇〇」を探してください。',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder5),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('６）洗剤の種類',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('毎日の衣類・タオル類には「〇〇」を使ってください。',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder6),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('９）洗剤の量について',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder9),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１０）洗剤の投入口',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤を入れる場所は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder10),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１１）柔軟剤について',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder11),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１２）柔軟剤の投入口',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤を入れる場所は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder12)
          ];

          $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
          $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
          $bodyPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
          $bodyPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;

          $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
          $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/object_27.jpg';
          $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
          replyFlexMessageForModification($bot, $event->getReplyToken(), '家事マニュアルの登録', $layout::VERTICAL, $headerTextComponents, $bodyBoxComponentSteps, $heroImageUrl, $aspectMode::COVER, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingTop::MD, $bodyPaddingBottom::MD
          );
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'create4') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録四'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗濯ネットを収納している場所” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録四タオルが入っている戸棚の中の上から三段目'));
      }
      else if(substr($event->getPostbackData(), 4) == 'create5') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録五'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤を収納している場所” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録五洗面所の下の開戸の中'));
      }
      else if(substr($event->getPostbackData(), 4) == 'create6') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録六'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤の名前” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録六ハイジア'));
      }
      else if(substr($event->getPostbackData(), 4) == 'create9') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録九'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤の量” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録九ジェルボール1個'));
      }
      else if(substr($event->getPostbackData(), 4) == 'create10') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録十'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤を入れる場所” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録十洗濯槽の中の壁面、水色の蓋をパカっと開ける'));
      }
      else if(substr($event->getPostbackData(), 4) == 'create11') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録十一'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”柔軟剤について” 書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録十一ソフラン'));
      }
      else if(substr($event->getPostbackData(), 4) == 'create12') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録十二'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”柔軟剤を入れる場所” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録十二蓋の付け根のソフト仕上剤と書いてる所を引き出す'));
      }

      // cmd_update
      else if(substr($event->getPostbackData(), 4) == 'update'){
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'まずはルームに入って、先に登録してください。');
        } else {
          $headerTextComponents = [new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('家事マニュアルをカスタマイズできます。',null,null,'xs','center', null, true, null, null, '#0d1b2a')];

          $actionBuilder4 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を修正する','cmd_update4');
          $actionBuilder5 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を修正する','cmd_update5');
          $actionBuilder6 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を修正する','cmd_update6');
          $actionBuilder9 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を修正する','cmd_update9');
          $actionBuilder10 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を修正する','cmd_update10');
          $actionBuilder11 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を修正する','cmd_update11');
          $actionBuilder12 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を修正する','cmd_update12');

          $bodyBoxComponentSteps = [
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('４）洗濯ネットの収納場所',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットは「〇〇」を探してください。',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder4),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('５）洗剤の収納場所',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤は「〇〇」を探してください。',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder5),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('６）洗剤の種類',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('毎日の衣類・タオル類には「〇〇」を使ってください。',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder6),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('９）洗剤の量について',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder9),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１０）洗剤の投入口',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤を入れる場所は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder10),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１１）柔軟剤について',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder11),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１２）柔軟剤の投入口',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤を入れる場所は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder12)
          ];

          $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
          $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
          $bodyPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
          $bodyPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;

          $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
          $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/object_25.jpg';
          $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
          replyFlexMessageForModification($bot, $event->getReplyToken(), '家事マニュアルの更新', $layout::VERTICAL, $headerTextComponents, $bodyBoxComponentSteps, $heroImageUrl, $aspectMode::COVER, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingTop::MD, $bodyPaddingBottom::MD
          );
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'update4') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('修正四'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗濯ネットを収納している場所” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　修正四タオルが入っている戸棚の中の上から三段目'));
      }
      else if(substr($event->getPostbackData(), 4) == 'update5') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('修正五'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤を収納している場所” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　修正五洗面所の下の開戸の中'));
      }
      else if(substr($event->getPostbackData(), 4) == 'update6') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('修正六'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤の名前” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　修正六ハイジア'));
      }
      else if(substr($event->getPostbackData(), 4) == 'update9') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('修正九'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤の量” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　修正九ジェルボール1個'));
      }
      else if(substr($event->getPostbackData(), 4) == 'update10') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('修正十'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤を入れる場所” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　修正十洗濯槽の中の壁面、水色の蓋をパカっと開ける'));
      }
      else if(substr($event->getPostbackData(), 4) == 'update11') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('修正十一'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”柔軟剤について” 書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　修正十一ソフラン'));
      }
      else if(substr($event->getPostbackData(), 4) == 'update12') {
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓　下記のステップ名をコピーしてください'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('修正十二'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”柔軟剤を入れる場所” を書いて送信してください。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　修正十二蓋の付け根のソフト仕上剤と書いてる所を引き出す'));
      }

      // cmd_delete
      else if(substr($event->getPostbackData(), 4) == 'delete'){
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'まずはルームに入って、登録してください。');
        } else {
          $headerTextComponents = [new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('家事マニュアルをカスタマイズできます。',null,null,'xs','center', null, true, null, null, '#0d1b2a')];

          $actionBuilder4 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を初期化する','cmd_delete4');
          $actionBuilder5 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を初期化する','cmd_delete5');
          $actionBuilder6 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を初期化する','cmd_delete6');
          $actionBuilder9 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を初期化する','cmd_delete9');
          $actionBuilder10 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を初期化する','cmd_delete10');
          $actionBuilder11 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を初期化する','cmd_delete11');
          $actionBuilder12 = new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('↑この「〇〇」を初期化する','cmd_delete12');

          $bodyBoxComponentSteps = [
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('４）洗濯ネットの収納場所',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットは「〇〇」を探してください。',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder4),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('５）洗剤の収納場所',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤は「〇〇」を探してください。',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder5),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('６）洗剤の種類',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('毎日の衣類・タオル類には「〇〇」を使ってください。',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder6),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('９）洗剤の量について',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder9),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１０）洗剤の投入口',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤を入れる場所は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder10),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１１）柔軟剤について',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder11),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('１２）柔軟剤の投入口',null,null,'lg',null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤を入れる場所は「〇〇」',null,null,null,null, null, true, null, null, '#0d1b2a'),
            new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder($actionBuilder12)
          ];

          $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
          $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
          $bodyPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
          $bodyPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;

          $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
          $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/object_28.jpg';
          $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
          replyFlexMessageForModification($bot, $event->getReplyToken(), '家事マニュアルの削除', $layout::VERTICAL, $headerTextComponents, $bodyBoxComponentSteps, $heroImageUrl, $aspectMode::COVER, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingTop::MD, $bodyPaddingBottom::MD
          );
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'delete4') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '４）洗濯ネットの収納場所 を初期化しますか？', '４）洗濯ネットの収納場所 を初期化しますか？',
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', '削除四'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete5') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '５）洗剤の収納場所 を初期化しますか？', '５）洗剤の収納場所 を初期化しますか？',
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', '削除五'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete6') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '６）洗剤の種類 を初期化しますか？', '６）洗剤の種類 を初期化しますか？',
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', '削除六'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete9') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '９）洗剤の量について を初期化しますか？', '９）洗剤の量について を初期化しますか？',
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', '削除九'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete10') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '１０）洗剤の投入口 を初期化しますか？', '１０）洗剤の投入口 を初期化しますか？',
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', '削除十'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete11') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '１１）柔軟剤について を初期化しますか？', '１１）柔軟剤について を初期化しますか？',
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', '削除十一'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete12') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '１２）柔軟剤の投入口 を初期化しますか？', '１２）柔軟剤の投入口 を初期化しますか？',
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', '削除十二'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
  
      // // cmd_insert
      // else if(substr($event->getPostbackData(), 4) == 'insert'){
      // // if($event->getText() == '登録したい'){
      //   if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
      //     replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
      //   } else {
      //     replyMultiMessage($bot,
      //           $event->getReplyToken(),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓下記のステップ名をコピペして'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録四'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をつけて、続けて収納場所を書いて送信してください。例「登録四戸棚の中」'));
      //   }
      // }
      //// cmd_update
      // else if(substr($event->getPostbackData(), 4) == 'update'){
      // // if($event->getText() == '更新したい'){
      //   if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
      //     replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
      //   } else {
      //     replyMultiMessage($bot,
      //           $event->getReplyToken(),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓下記のステップ名をコピペして'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('更新四'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をつけて、続けて収納場所を書いて送信してください。例「更新四戸棚の中」'));
      //   }
      // }
      // // cmd_delete
      // else if(substr($event->getPostbackData(), 4) == 'delete'){
      // // if($event->getText() == '削除したい'){
      //   if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
      //     replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
      //   } else {
      //     replyMultiMessage($bot,
      //           $event->getReplyToken(),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓下記のステップ名をコピペして'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('削除四'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('ステップ名を、送信してください。例「削除四」'));
      //   }
      // }


      continue;
    }


    // ーーーーーーーーーーーー家事マニュアル関連ーーーーーーーーーーーーーーーーー

    // 家事stepの選択肢ボタンをタップした時の処理
    else if($event->getPostbackData() == 'step1'){
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step1   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('下準備１：異物混入チェック',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('紙や異物が混じってないかポケットを確認してください。',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0724.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step1', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step2'){
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step2   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('下準備２：泥汚れの下洗い',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('泥や排泄物で汚れていたら、風呂場で軽く下洗いしてください。',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0721.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step2', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step3'){
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step3   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('下準備３：洗濯ネットで保護',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('黒いもの。長いもの。引っかかりそうなもの。剥がれそうなもの。該当すれば洗濯ネットに入れて保護。',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0234.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step3', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step4'){
      // if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
      //   $step4 = substr($event->getText(), 1);
      //   registerStep4($event->getUserId(), $step4);
      //   replyTextMessage($bot, $event->getReplyToken(), '登録しました。');
      // } else {
      //   replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
      // }
      $step4 = getStep4($event->getUserId());
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step4   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットの収納場所',null,null,'xl',null,null,true,null,'bold')];
      // $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットは「引き出しや戸棚の中」を探してください',null,null,null,null,null,true)];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットは「'.$step4.'」を探してください',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0725.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step4', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step5'){
      $step5 = getStep5($event->getUserId());
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step5   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の収納場所',null,null,'xl',null,null,true,null,'bold')];
      // $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤は「引き出しや戸棚の中」を探してください',null,null,null,null,null,true)];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤は「'.$step5.'」を探してください',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0214.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step5', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step6'){
      $step6 = getStep6($event->getUserId());
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step6   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の種類',null,null,'xl',null,null,true,null,'bold')];
      // $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('毎日の衣類・タオル類には「ハイジア」を使ってください。',null,null,null,null,null,true)];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('毎日の衣類・タオル類には「'.$step6.'」を使ってください。',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0720.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step6', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step7'){
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step7   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯機の水量',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('全て洗濯機に入れたら、水量を知るために、洗濯機のスタートボタンを押してください。',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0710.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step7', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step8'){
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step8   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量と水量の関係性',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯物の量に応じて水量が変わります、洗剤を水量に応じて入れます。',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0713.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step8', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step9'){
      $step9 = getStep9($event->getUserId());
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step9   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量について',null,null,'xl',null,null,true,null,'bold')];
      // $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量は「背面か側面に載ってますので見てください」',null,null,null,null,null,true)];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量は「'.$step9.'」',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0215.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step9', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step10'){
      $step10 = getStep10($event->getUserId());
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step10   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の投入口',null,null,'xl',null,null,true,null,'bold')];
      // $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤を入れる場所は「機種によって異なります。洗濯機の中かフチか洗濯機の上部かにあります。」',null,null,null,null,null,true)];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤を入れる場所は「'.$step10.'」',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0218.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step10', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step11'){
      $step11 = getStep11($event->getUserId());
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step11   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤について',null,null,'xl',null,null,true,null,'bold')];
      // $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤は「必要であれば入れてください。」',null,null,null,null,null,true)];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤は「'.$step11.'」',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/KIMG0385.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step11', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step12'){
      $step12 = getStep12($event->getUserId());
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step12   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤の投入口',null,null,'xl',null,null,true,null,'bold')];
      // $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤を入れる場所は「洗剤とは異なる投入口が洗濯機にあります。」',null,null,null,null,null,true)];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤を入れる場所は「'.$step12.'」',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0708.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step12', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
    else if($event->getPostbackData() == 'step13'){
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step13   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯機スタート',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯機の蓋を閉めると洗濯が始まります。',null,null,null,null,null,true)];
      // echo ComponentLayout::VERTICAL;
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0715.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      // $spacing = ComponentSpacing::XXL;
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step13', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }
  
    continue;
  }

  // ーーーーーーーーーーーーカスタマイズのメニュー関連（写真）ーーーーーーーーーーーーーーーーー
  // ユーザーから送信された画像ファイルを取得し、サーバーに保存する
  // イベントがImageMessage型であれば
  // if ($event instanceof \LINE\LINEBot\Event\MessageEvent\ImageMessage) {
  //   // イベントのコンテンツを取得
  //   $content = $bot->getMessageContent($event->getMessageId());
  //   // コンテンツヘッダーを取得
  //   $headers = $content->getHeaders();
  //   // 画像の保存先フォルダ
  //   $directory_path = 'tmp';
  //   // 保存するファイル名
  //   // $filename = uniqid();
  //   $roomId = getRoomIdOfUser($event->getUserId());
  //   $filename = $roomId.'step10photo';
  //   // コンテンツの種類を取得
  //   $extension = explode('/', $headers['Content-Type'])[1];
  //   // 保存先フォルダが存在しなければ
  //   if(!file_exists($directory_path)) {
  //     // フォルダを作成
  //     if(mkdir($directory_path, 0777, true)) {
  //       // 権限を変更
  //       chmod($directory_path, 0777);
  //     }
  //   }
  //   // 保存先フォルダにコンテンツを保存
  //   file_put_contents($directory_path . '/' . $filename . '.' . $extension, $content->getRawBody());
  //   // 保存したファイルのURLを返信→ユーザーがタップすると画像を閲覧できる
  //   // replyTextMessage($bot, $event->getReplyToken(), 'http://' . $_SERVER['HTTP_HOST'] . '/' . $directory_path. '/' . $filename . '.' . $extension);
  //   replyMultiMessage($bot,
  //   $event->getReplyToken(),
  //   new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('マニュアルを見る時は、下記↓ステップ名をコピペして'),
  //   new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step10'),
  //   new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('ステップ名を、送信してください。例「step10」'));
  //   // 下のstep10に表示に続く
  // }
  // 実際の表示url (uniqid)の時
  // http://アプリ名.herokuapp.com/tmp/xxxxxxx.jpeg
  // 実際の表示url (固定)の時
  // http://アプリ名.herokuapp.com/tmp/step10photo.jpeg
  // githubに保存してる画像ファイルを表示する時はこちら
  // $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0218.jpg';



  // イベントがMessageEventクラスのインスタンスであれば
  else if ($event instanceof \LINE\LINEBot\Event\MessageEvent) {

    // ーーーーーーーーーーーーカスタマイズのメニュー関連（写真）ーーーーーーーーーーーーーーーーー

    // ImageMessageクラスのインスタンスであれば
    if($event instanceof \LINE\LINEBot\Event\MessageEvent\ImageMessage) {
      \Cloudinary::config(array(
        'cloud_name' => getenv('CLOUDINARY_NAME'),
        'api_key' => getenv('CLOUDINARY_KEY'),
        'api_secret' => getenv('CLOUDINARY_SECRET')
      ));

      $response = $bot->getMessageContent($event->getMessageId());
      $im = imagecreatefromstring($response->getRawBody());
      // PHP Fatal error:  Uncaught Error: Call to undefined function imagecreatefromstring()
      // imagecreatefromstring — 文字列の中のイメージストリームから新規イメージを作成する
      // ext-gd入れたら解決

      if ($im !== false) {
          // $roomId = getRoomIdOfUser($event->getUserId());
          // $filename = $roomId.'step10photo';
          $filename = uniqid();
          $directory_path = 'tmp';
          if(!file_exists($directory_path)) {
            if(mkdir($directory_path, 0777, true)) {
                chmod($directory_path, 0777);
            }
          }
          imagejpeg($im, $directory_path. '/' . $filename . '.jpg', 75);
      }

      // $filesize = new \LINE\LINEBot\Event\MessageEvent();
      // $filesize = $bot->getMessageContent($event->getFileSize());


      $path = dirname(__FILE__) . '/' . $directory_path. '/' . $filename . '.jpg';
      // $filesize = filesize($path);
      // 238830だった<=238kb
      // $filesize_save = floor(intdiv(100000, $filesize)*100);
      // 変数を入れ込むとうまくいかない、q_0になってしまう、もしくは計算上76kbの筈が7.9kbと一桁少なく保存される。なので固定値で。
      $roomId = getRoomIdOfUser($event->getUserId());
      $filename_save = array('folder'=>'kajiboimage/step10photo', 'public_id'=>$roomId, 'format'=>'jpg','transformation'=>['quality'=>'30']);
      $result = \Cloudinary\Uploader::upload($path, $filename_save);
      // セキュリティを配慮してファイル名を推測できない形→オプションでパラメータつけてフォルダ名、ファイル名管理

      // $bot->replyMessage($event->getReplyToken(),
      //     (new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder())
      //       ->add(new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($result['secure_url']))
      //   );
      replyMultiMessage($bot, $event->getReplyToken(),
        new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('マニュアルを見る時は、下記↓ステップ名をコピペして'),
        new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step10'),
        new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('ステップ名を、送信してください。例：　step10'));
        // 下のstep10に表示に続く
      ;
    }
  // }
  

  // // MessageEvent型でなければ処理をスキップ
  // if (!($event instanceof \LINE\LINEBot\Event\MessageEvent)) {
  //   error_log('Non message event has come');
  //   continue;
  // }
  // // TextMessage型でなければ処理をスキップ
  // if (!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
  //   error_log('Non text message has come');
  //   continue;
  // }

    // TextMessageクラスのインスタンスであれば
    else if ($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage) {

      // ーーーーーーーーーーーーLIFF関連ーーーーーーーーーーーーーーーーー

      // LIFFで完了ボタン押した後の処理
      if($event->getText() == '洗濯開始作業完了！'){
        // スタンプと文字を返信
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('洗濯機回してくれてありがとう✨'),
          new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(11539, 52114110)
        );
      }

      // ーーーーーーーーーーーールームのメニュー関連ーーーーーーーーーーーーーーーーー

      // リッチコンテンツ以外の時(ルームIDが入力された時)
      else if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
        // 入室
        $roomId = enterRoomAndGetRoomId($event->getUserId(), $event->getText());
        // 成功時
        if($roomId !== PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), "ルームID" . $roomId . "に入室しました。");
        }
        // 失敗時
        else {
          replyTextMessage($bot, $event->getReplyToken(), "そのルームIDは存在しません。");
        }
      }

      // ーーーーーーーーーーーーカスタマイズのメニュー関連（写真）ーーーーーーーーーーーーーーーーー

      // step10に登録
      else if($event->getText() == '写真変えたい'){
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          replyTextMessage($bot, $event->getReplyToken(), '写真を一枚送信してください。');
          // 上方の、ImageMessage型イベント確認グループに続く
        }
      }
      else if($event->getText() == 'step10'){
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step10   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の投入口',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤を入れる場所は「機種によって異なります。洗濯機の中かフチか洗濯機の上部かにあります。」',null,null,null,null,null,true)];
        // echo ComponentLayout::VERTICAL;
        $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
        // $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0218.jpg';
        $roomId = getRoomIdOfUser($event->getUserId());
        // $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/tmp/'.$roomId.'step10photo.jpeg';
        $heroImageUrl = 'https://res.cloudinary.com/kajibo/kajiboimage/step10photo/'.$roomId.'.jpg';
        $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
        $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
        $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
        // $quickReply = new \LINE\LINEBot\QuickReplyBuilder;
        $quickReplyButtons =  flexMessageQuickReply();
        $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
        // $spacing = ComponentSpacing::XXL;
        $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        replyFlexMessage($bot, $event->getReplyToken(), 'step10', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
        );
      }

      // ーーーーーーーーーーーーカスタマイズのメニュー関連ーーーーーーーーーーーーーーーーー

      // -----------------------step4------------------------------------
      // step4に登録→postbackに変更
      // if($event->getText() == '登録したい'){
      //   if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
      //     replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
      //   } else {
      //     // replyConfirmTemplate($bot, $event->getReplyToken(), 'step4に登録しますか。', 'step4に登録しますか。',
      //     //   new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', '先頭に ステップ４ とつけて続けて収納場所を書いて送信してください。'),
      //     //   new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', 'cancel'));
      //     replyMultiMessage($bot,
      //           $event->getReplyToken(),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓下記のステップ名をコピペして'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('t04'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をつけて、続けて収納場所を書いて送信してください。例「t04戸棚の中」'));
      //   }
      // }
      // step4に登録を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '登録四') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep4($event->getUserId()) === PDO::PARAM_NULL) {
            $step4 = mb_substr($event->getText(), 3, null, "UTF-8");
            registerStep4($bot, $event->getUserId(), $step4);
            // replyTextMessage($bot, $event->getReplyToken(), '登録しました。');
          } else {
            replyTextMessage($bot, $event->getReplyToken(), 'すでに登録されています。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }

      // step4に上書き更新→postbackに変更
      // if($event->getText() == '更新したい'){
      //   if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
      //     replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
      //   } else {
      //     replyMultiMessage($bot,
      //           $event->getReplyToken(),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓下記のステップ名をコピペして'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('u04'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をつけて、続けて収納場所を書いて送信してください。例「u04戸棚の中」'));
      //   }
      // }
      // step4に更新を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '修正四') {
      // if(substr($event->getText(), 0, 3) == 'u04') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep4($event->getUserId()) !== PDO::PARAM_NULL) {
            // $step4 = substr($event->getText(), 3);
            $step4 = mb_substr($event->getText(), 3, null, "UTF-8");
            updateStep4($bot, $event->getUserId(), $step4);
            // replyTextMessage($bot, $event->getReplyToken(), '更新しました。');
          } else {
            // replyTextMessage($bot, $event->getReplyToken(), '登録がありません。');
            replyMultiMessage($bot,
            $event->getReplyToken(),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録がありません。登録しますので、お手数ですが、↓　下記のステップ名をコピーしてください'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録四'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗濯ネットを収納している場所” を書いて再度送信してください。'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録四タオルが入っている戸棚の中の上から三段目'));
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }

      // step4をDBから削除→postbackに変更
      // if($event->getText() == '削除したい'){
      //   if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
      //     replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
      //   } else {
      //     replyMultiMessage($bot,
      //           $event->getReplyToken(),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('↓下記のステップ名をコピペして'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('s04'),
      //           new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('ステップ名を、送信してください。例「s04」'));
      //   }
      // }
      // step4の削除を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '削除四') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep4($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep4($bot, $event->getUserId());
            // replyTextMessage($bot, $event->getReplyToken(), '削除しました。');
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }

      // -----------------------step5------------------------------------
      // step5に登録を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '登録五') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep5($event->getUserId()) === PDO::PARAM_NULL) {
            $step5 = mb_substr($event->getText(), 3, null, "UTF-8");
            registerStep5($bot, $event->getUserId(), $step5);
          } else {
            replyTextMessage($bot, $event->getReplyToken(), 'すでに登録されています。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step5に更新を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '修正五') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep5($event->getUserId()) !== PDO::PARAM_NULL) {
            $step5 = mb_substr($event->getText(), 3, null, "UTF-8");
            updateStep5($bot, $event->getUserId(), $step5);
          } else {
            replyMultiMessage($bot,
            $event->getReplyToken(),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録がありません。登録しますので、お手数ですが、↓　下記のステップ名をコピーしてください'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録五'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤を収納している場所” を書いて再度送信してください。'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録五洗面所の下の開戸の中'));
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step5の削除を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '削除五') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep5($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep5($bot, $event->getUserId());
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }

      // -----------------------step6------------------------------------
      // step6に登録を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '登録六') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep6($event->getUserId()) === PDO::PARAM_NULL) {
            $step6 = mb_substr($event->getText(), 3, null, "UTF-8");
            registerStep6($bot, $event->getUserId(), $step6);
          } else {
            replyTextMessage($bot, $event->getReplyToken(), 'すでに登録されています。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step6に更新を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '修正六') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep6($event->getUserId()) !== PDO::PARAM_NULL) {
            $step6 = mb_substr($event->getText(), 3, null, "UTF-8");
            updateStep6($bot, $event->getUserId(), $step6);
          } else {
            replyMultiMessage($bot,
            $event->getReplyToken(),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録がありません。登録しますので、お手数ですが、↓　下記のステップ名をコピーしてください'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録六'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤の名前” を書いて再度送信してください。'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録六ハイジア'));
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step6の削除を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '削除六') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep6($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep6($bot, $event->getUserId());
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }

      // -----------------------step9------------------------------------
      // step9に登録を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '登録九') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep9($event->getUserId()) === PDO::PARAM_NULL) {
            $step9 = mb_substr($event->getText(), 3, null, "UTF-8");
            registerStep9($bot, $event->getUserId(), $step9);
          } else {
            replyTextMessage($bot, $event->getReplyToken(), 'すでに登録されています。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step9に更新を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '修正九') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep9($event->getUserId()) !== PDO::PARAM_NULL) {
            $step9 = mb_substr($event->getText(), 3, null, "UTF-8");
            updateStep9($bot, $event->getUserId(), $step9);
          } else {
            replyMultiMessage($bot,
            $event->getReplyToken(),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録がありません。登録しますので、お手数ですが、↓　下記のステップ名をコピーしてください'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録九'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤の量” を書いて再度送信してください。'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録九ジェルボール1個'));
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step9の削除を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '削除九') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep9($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep9($bot, $event->getUserId());
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
        
      // -----------------------step11------------------------------------
      // step11に登録を実行
      else if(mb_substr($event->getText(), 0, 4, "UTF-8") === '登録十一') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep11($event->getUserId()) === PDO::PARAM_NULL) {
            $step11 = mb_substr($event->getText(), 4, null, "UTF-8");
            registerStep11($bot, $event->getUserId(), $step11);
          } else {
            replyTextMessage($bot, $event->getReplyToken(), 'すでに登録されています。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step11に更新を実行
      else if(mb_substr($event->getText(), 0, 4, "UTF-8") === '修正十一') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep11($event->getUserId()) !== PDO::PARAM_NULL) {
            $step11 = mb_substr($event->getText(), 4, null, "UTF-8");
            updateStep11($bot, $event->getUserId(), $step11);
          } else {
            replyMultiMessage($bot,
            $event->getReplyToken(),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録がありません。登録しますので、お手数ですが、↓　下記のステップ名をコピーしてください'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録十一'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”柔軟剤について” 書いて再度送信してください。'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録十一ソフラン'));
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step11の削除を実行
      else if(mb_substr($event->getText(), 0, 4, "UTF-8") === '削除十一') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep11($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep11($bot, $event->getUserId());
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }

      // -----------------------step12------------------------------------
      // step12に登録を実行
      else if(mb_substr($event->getText(), 0, 4, "UTF-8") === '登録十二') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep12($event->getUserId()) === PDO::PARAM_NULL) {
            $step12 = mb_substr($event->getText(), 4, null, "UTF-8");
            registerStep12($bot, $event->getUserId(), $step12);
          } else {
            replyTextMessage($bot, $event->getReplyToken(), 'すでに登録されています。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step12に更新を実行
      else if(mb_substr($event->getText(), 0, 4, "UTF-8") === '修正十二') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep12($event->getUserId()) !== PDO::PARAM_NULL) {
            $step12 = mb_substr($event->getText(), 4, null, "UTF-8");
            updateStep12($bot, $event->getUserId(), $step12);
          } else {
            replyMultiMessage($bot,
            $event->getReplyToken(),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録がありません。登録しますので、お手数ですが、↓　下記のステップ名をコピーしてください'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録十二'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”柔軟剤を入れる場所” を書いて再度送信してください。'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録十二蓋の付け根のソフト仕上剤と書いてる所を引き出す'));
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step12の削除を実行
      else if(mb_substr($event->getText(), 0, 4, "UTF-8") === '削除十二') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep12($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep12($bot, $event->getUserId());
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }


      // -----------------------step10------------------------------------
      // step10に登録を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '登録十') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep10($event->getUserId()) === PDO::PARAM_NULL) {
            $step10 = mb_substr($event->getText(), 3, null, "UTF-8");
            registerStep10($bot, $event->getUserId(), $step10);
          } else {
            replyTextMessage($bot, $event->getReplyToken(), 'すでに登録されています。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step10に更新を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '修正十') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep10($event->getUserId()) !== PDO::PARAM_NULL) {
            $step10 = mb_substr($event->getText(), 3, null, "UTF-8");
            updateStep10($bot, $event->getUserId(), $step10);
          } else {
            replyMultiMessage($bot,
            $event->getReplyToken(),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録がありません。登録しますので、お手数ですが、↓　下記のステップ名をコピーしてください'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('登録十'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('先頭にステップ名をペーストして、続けて、'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('”洗剤を入れる場所” を書いて再度送信してください。'),
            new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('例：　登録十洗濯槽の中の壁面、水色の蓋をパカっと開ける'));
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      // step10の削除を実行
      else if(mb_substr($event->getText(), 0, 3, "UTF-8") === '削除十') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep10($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep10($bot, $event->getUserId());
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }


  
      continue;
    }
  }
}
// ======================以下関数============================

// ーーーーーーーーーーーーカスタマイズのメニュー関連ーーーーーーーーーーーーーーーーー

// -----------------------step4------------------------------------
// step4を登録
function registerStep4($bot, $userId, $step4){
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STEP4S .' (roomid, step4) values (?, ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId, $step4));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step4「洗濯ネットの収納場所」を登録しました'));
  }
}
// step4を表示
function getStep4($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step4 from ' . TABLE_NAME_STEP4S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければ定型文
  if (!($row = $sth->fetch())) {
    // return PDO::PARAM_NULL;
    return '引き出しや戸棚の中';
  } else {
    // DBの内容を返す
    // return json_decode($row['stone']);
    return $row['step4'];
  }
}
// step4の情報を更新（DBの上書き）
function updateStep4($bot, $userId, $step4) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STEP4S . ' set step4 = ? where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($step4, $roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step4「洗濯ネットの収納場所」を修正しました'));
  }
}
// step4の情報をデータベースから削除
function deleteStep4($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_STEP4S . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $flag = $sth->execute(array($roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step4「洗濯ネットの収納場所」の登録を削除しました'));
  }
}
// ユーザーIDからstep4の登録内容を取得
function getDetailOfStep4($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step4 from ' . TABLE_NAME_STEP4S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければnull、あればその内容
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step4'];
  }
}

// -----------------------step5------------------------------------
// step5を登録
function registerStep5($bot, $userId, $step5){
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STEP5S .' (roomid, step5) values (?, ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId, $step5));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step5「洗剤の収納場所」を登録しました'));
  }
}
// step5を表示
function getStep5($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step5 from ' . TABLE_NAME_STEP5S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければ定型文
  if (!($row = $sth->fetch())) {
    // return PDO::PARAM_NULL;
    return '引き出しや戸棚の中';
  } else {
    // DBの内容を返す
    // return json_decode($row['stone']);
    return $row['step5'];
  }
}
// step5の情報を更新（DBの上書き）
function updateStep5($bot, $userId, $step5) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STEP5S . ' set step5 = ? where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($step5, $roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step5「洗剤の収納場所」を修正しました'));
  }
}
// step5の情報をデータベースから削除
function deleteStep5($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_STEP5S . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $flag = $sth->execute(array($roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step5「洗剤の収納場所」の登録を削除しました'));
  }
}
// ユーザーIDからstep5の登録内容を取得
function getDetailOfStep5($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step5 from ' . TABLE_NAME_STEP5S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければnull、あればその内容
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step5'];
  }
}

// -----------------------step6------------------------------------
// step6を登録
function registerStep6($bot, $userId, $step6){
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STEP6S .' (roomid, step6) values (?, ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId, $step6));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step6「洗剤の種類」を登録しました'));
  }
}
// step6を表示
function getStep6($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step6 from ' . TABLE_NAME_STEP6S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければ定型文
  if (!($row = $sth->fetch())) {
    // return PDO::PARAM_NULL;
    return 'ハイジア';
  } else {
    // DBの内容を返す
    // return json_decode($row['stone']);
    return $row['step6'];
  }
}
// step6の情報を更新（DBの上書き）
function updateStep6($bot, $userId, $step6) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STEP6S . ' set step6 = ? where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($step6, $roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step6「洗剤の種類」を修正しました'));
  }
}
// step6の情報をデータベースから削除
function deleteStep6($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_STEP6S . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $flag = $sth->execute(array($roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step6「洗剤の種類」の登録を削除しました'));
  }
}
// ユーザーIDからstep6の登録内容を取得
function getDetailOfStep6($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step6 from ' . TABLE_NAME_STEP6S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければnull、あればその内容
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step6'];
  }
}

// -----------------------step9------------------------------------
// step9を登録
function registerStep9($bot, $userId, $step9){
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STEP9S .' (roomid, step9) values (?, ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId, $step9));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step9「洗剤の量について」を登録しました'));
  }
}
// step9を表示
function getStep9($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step9 from ' . TABLE_NAME_STEP9S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければ定型文
  if (!($row = $sth->fetch())) {
    // return PDO::PARAM_NULL;
    return '背面か側面に載ってますので見てください';
  } else {
    // DBの内容を返す
    // return json_decode($row['stone']);
    return $row['step9'];
  }
}
// step9の情報を更新（DBの上書き）
function updateStep9($bot, $userId, $step9) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STEP9S . ' set step9 = ? where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($step9, $roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step9「洗剤の量について」を修正しました'));
  }
}
// step9の情報をデータベースから削除
function deleteStep9($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_STEP9S . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $flag = $sth->execute(array($roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step9「洗剤の量について」の登録を削除しました'));
  }
}
// ユーザーIDからstep9の登録内容を取得
function getDetailOfStep9($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step9 from ' . TABLE_NAME_STEP9S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければnull、あればその内容
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step9'];
  }
}

// -----------------------step10-----------------------------------
// step10を登録
function registerStep10($bot, $userId, $step10){
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STEP10S .' (roomid, step10) values (?, ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId, $step10));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step10「洗剤の投入口」を登録しました'));
  }
}
// step10を表示
function getStep10($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step10 from ' . TABLE_NAME_STEP10S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければ定型文
  if (!($row = $sth->fetch())) {
    // return PDO::PARAM_NULL;
    return '機種によって異なります。洗濯機の中かフチか洗濯機の上部かにあります。';
  } else {
    // DBの内容を返す
    // return json_decode($row['stone']);
    return $row['step10'];
  }
}
// step10の情報を更新（DBの上書き）
function updateStep10($bot, $userId, $step10) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STEP10S . ' set step10 = ? where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($step10, $roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step10「洗剤の投入口」を修正しました'));
  }
}
// step10の情報をデータベースから削除
function deleteStep10($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_STEP10S . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $flag = $sth->execute(array($roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step10「洗剤の投入口」の登録を削除しました'));
  }
}
// ユーザーIDからstep10の登録内容を取得
function getDetailOfStep10($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step10 from ' . TABLE_NAME_STEP10S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければnull、あればその内容
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step10'];
  }
}

// -----------------------step11-----------------------------------
// step11を登録
function registerStep11($bot, $userId, $step11){
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STEP11S .' (roomid, step11) values (?, ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId, $step11));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step11「柔軟剤について」を登録しました'));
  }
}
// step11を表示
function getStep11($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step11 from ' . TABLE_NAME_STEP11S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければ定型文
  if (!($row = $sth->fetch())) {
    // return PDO::PARAM_NULL;
    return '必要であれば入れてください。';
  } else {
    // DBの内容を返す
    // return json_decode($row['stone']);
    return $row['step11'];
  }
}
// step11の情報を更新（DBの上書き）
function updateStep11($bot, $userId, $step11) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STEP11S . ' set step11 = ? where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($step11, $roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step11「柔軟剤について」を修正しました'));
  }
}
// step11の情報をデータベースから削除
function deleteStep11($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_STEP11S . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $flag = $sth->execute(array($roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step11「柔軟剤について」の登録を削除しました'));
  }
}
// ユーザーIDからstep11の登録内容を取得
function getDetailOfStep11($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step11 from ' . TABLE_NAME_STEP11S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければnull、あればその内容
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step11'];
  }
}

// -----------------------step12-----------------------------------
// step12を登録
function registerStep12($bot, $userId, $step12){
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STEP12S .' (roomid, step12) values (?, ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId, $step12));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step12「柔軟剤の投入口」を登録しました'));
  }
}
// step12を表示
function getStep12($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step12 from ' . TABLE_NAME_STEP12S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければ定型文
  if (!($row = $sth->fetch())) {
    // return PDO::PARAM_NULL;
    return '洗剤とは異なる投入口が洗濯機にあります';
  } else {
    // DBの内容を返す
    // return json_decode($row['stone']);
    return $row['step12'];
  }
}
// step12の情報を更新（DBの上書き）
function updateStep12($bot, $userId, $step12) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STEP12S . ' set step12 = ? where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($step12, $roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step12「柔軟剤の投入口」を修正しました'));
  }
}
// step12の情報をデータベースから削除
function deleteStep12($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_STEP12S . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $flag = $sth->execute(array($roomId));
  // $dbh = dbConnection::getConnection();
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  // 各ユーザーにメッセージを送信
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step12「柔軟剤の投入口」の登録を削除しました'));
  }
}
// ユーザーIDからstep12の登録内容を取得
function getDetailOfStep12($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step12 from ' . TABLE_NAME_STEP12S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければnull、あればその内容
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step12'];
  }
}






// ーーーーーーーーーーーーリッチメニュー関連ーーーーーーーーーーーーーーーーー

// 家事する時のリッチメニュー rich5.jpg
// 一覧で見る 個別に見る 完了報告 戻る
// function createNewRichmenuKaji($channelAccessToken, $channelSecret, $richMenuAreaBuilder=[]) {
  // $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($channelAccessToken);
  // $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $channelSecret]);
  // $sizeBuilder = new \LINE\LINEBot\RichMenuBuilder\RichMenuSizeBuilder(405,1200);
  // $selected = true;
  // $name = 'KAJIBO_richmenu_kaji';
  // $chatBarText = 'メニューを開く/閉じる';
  // $areaBuilders = array();
  // foreach($richMenuAreaBuilder as $value){
  //   array_push($areaBuilders,$value);
  // }
  // $richMenuBuilder = new \LINE\LINEBot\RichMenuBuilder($sizeBuilder, $selected, $name, $chatBarText, $areaBuilders);
  // $response = $bot->createRichMenu($richMenuBuilder);
  // return var_dump($response);
  // // $arr = json_decode($response,true);
  // $decoded_json = json_decode($response);
  // // $decoded_json->{"c"}
  // if(isset($decoded_json->{"richMenuId"})) {
  //   return $decoded_json->{"richMenuId"};
  // }
  // else {
  //   return $decoded_json->{"message"};
  // }
//   function createNewRichmenuKaji($channelAccessToken) {
//   $url = "https://api.line.me/v2/bot/richmenu";
//   $curl = curl_init($url);
//   $body = '{"size": {"width": 1200,"height": 405},"selected": false,"name": "KAJIBO_richmenu_2","chatBarText": "メニューを開く/閉じる","areas": [{"bounds": {"x": 0,"y": 0,"width": 300,"height": 405},"action": {"type": "postback","data": "cmd_main_menu"}},{"bounds": {"x": 300,"y": 0,"width": 300,"height": 405},"action": {"type": "uri","uri": "https://liff.line.me/1654069050-OPNWVd3j"}},{"bounds": {"x": 600,"y": 0,"width": 300,"height": 405},"action": {"type": "postback","data": "cmd_kaji"}},{"bounds": {"x": 900,"y": 0,"width": 300,"height": 405},"action": {"type": "postback","data": "cmd_end_confirm"}}]}';
//   $options = array(
//     //HEADER
//     CURLOPT_HTTPHEADER => array(
//       'Authorization: Bearer'.$channelAccessToken,
//       'Content-Type: application/json',
//     ),
//     CURLOPT_POST => true,
//     CURLOPT_POSTFIELDS=>$body, 
//   );
//   //set options
//   curl_setopt_array($curl, $options);
//   // request実行
//   $result = curl_exec($curl);

//   if(isset($result['richMenuId'])) {
//     return $result['richMenuId'];
//   }
//   else {
//     return $result['message'];
//   }
// }

// function uploadImageToRichmenuKaji($channelAccessToken, $channelSecret, $richmenuId) {
//   $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($channelAccessToken);
//   $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $channelSecret]);
//   $imagePath = 'https://' . $_SERVER['HTTP_HOST'] .  '/richmenu/rich5.jpg';
//   $contentType = 'image/jpeg';
//   $response = $bot->uploadRichMenuImage($richmenuId, $imagePath, $contentType);
//   // if(!isRichmenuIdValid($richmenuId)) {
//   //   return 'invalid richmenu id';
//   // }
//   // 用意された５種類の画像の中から、ランダムに選ばれ、リッチメニューとしてアップロードされる
// //   $imageIndex = 5;
// //   $imagePath = realpath('') . 'richmenu/' . 'rich' . $imageIndex . '.jpg';
// //   $sh = <<< EOF
// //   curl -X POST \
// //   -H 'Authorization: Bearer $channelAccessToken' \
// //   -H 'Content-Type: image/jpg' \
// //   -H 'Expect:' \
// //   -T $imagePath \
// //   https://api.line.me/v2/bot/richmenu/$richmenuId/content
// // EOF;
// //   $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
//   // if(isset($result['message'])) {
//   //   return $result['message'];
//   //   // 失敗するとエラー内容が記述されて返ってきます。{'message': 'error description'}
//   // }
//   // else {
//   //   return 'success. Image #0' . $randomImageIndex . ' has uploaded onto ' . $richmenuId;
//   // }
// }
function linkToUser($channelAccessToken, $userId, $richmenuId) {
  if(!isRichmenuIdValid($richmenuId)) {
    return 'invalid richmenu id';
  }
  $sh = <<< EOF
  curl -X POST \
  -H 'Authorization: Bearer $channelAccessToken' \
  -H 'Content-Length: 0' \
  https://api.line.me/v2/bot/user/$userId/richmenu/$richmenuId
EOF;
  $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
  if(isset($result['message'])) {
    return $result['message'];
    // 失敗するとエラー内容が記述されて返ってきます。{'message': 'error description'}
  }
  else {
    return 'success';
  }
}
function isRichmenuIdValid($string) {
  if(preg_match('/^[a-zA-Z0-9-]+$/', $string)) {
    return true;
  } else {
    return false;
  }
}
// function linkToUser($channelAccessToken, $channelSecret, $userId, $richmenuId) {
//   $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($channelAccessToken);
//   $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $channelSecret]);
//   $response = $bot->linkRichMenu($userId, $richmenuId);
  // if(!isRichmenuIdValid($richmenuId)) {
  //   return 'invalid richmenu id';
  // }
//   $sh = <<< EOF
//   curl -X POST \
//   -H 'Authorization: Bearer $channelAccessToken' \
//   -H 'Content-Length: 0' \
//   https://api.line.me/v2/bot/user/$userId/richmenu/$richmenuId
// EOF;
//   $result = json_decode(shell_exec(str_replace('\\', '', str_replace(PHP_EOL, '', $sh))), true);
  // // $body = '';
  // $url = 'https://api.line.me/v2/bot/user/'.$userId.'/richmenu/'.$richmenuId;
  // $curl = curl_init($url);
  // $options = array(
  //   //HEADER
  //   CURLOPT_HTTPHEADER => array(
  //     'Authorization: Bearer'.$channelAccessToken,
  //     'Content-Length: 0',
  //   ),
  //   //Method
  //   CURLOPT_POST => true,//POST
  //   //body
  //   // CURLOPT_POSTFIELDS => http_build_query($post_args), 
  //   // CURLOPT_POSTFIELDS=>$body, 
  // );
  // //set options
  // curl_setopt_array($curl, $options);
  // // request
  // $result = curl_exec($curl);
  // if(isset($result['message'])) {
  //   return $result['message'];
  //   // 失敗するとエラー内容が記述されて返ってきます。{'message': 'error description'}
  // }
  // else {
  //   return 'success';
  // }
// }

// ーーーーーーーーーーーールームのメニュー関連ーーーーーーーーーーーーーーーーー
// ユーザーIDからルームIDを取得
function getRoomIdOfUser($userId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select roomid from ' . TABLE_NAME_ROOMS . ' where ? = pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['roomid'];
  }
}

// ルームを作成し入室後ルームIDを返す
function createRoomAndGetRoomId($userId) {
  $roomId = uniqid();
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_ROOMS .' (userid, roomid) values (pgp_sym_encrypt(?, \'' . getenv('DB_ENCRYPT_PASS') . '\'), ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId, $roomId));

  return $roomId;
}

// 入室しルームIDを返す
function enterRoomAndGetRoomId($userId, $roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_ROOMS .' (userid, roomid) SELECT pgp_sym_encrypt(?, \'' . getenv('DB_ENCRYPT_PASS') . '\'), ? where exists(select roomid from ' . TABLE_NAME_ROOMS . ' where roomid = ?) returning roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId, $roomId, $roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['roomid'];
  }
}

// 退室
function leaveRoom($userId) {
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_ROOMS . ' where ? = pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\')';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($userId));
}

// ーーーーーーーーーーーー家事のメニュー（pushMessage関連）ーーーーーーーーーーーーーーーーー
// 作業終了の報告
function endKaji($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);

  $dbh = dbConnection::getConnection();
  $sql = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array(getRoomIdOfUser($userId)));
  // 各ユーザーにメッセージを送信
  foreach ($sth->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('【ご報告】洗濯機を回しました✨'));
  }

  // ルームを削除（ユーザーも削除？）
  // $sqlDeleteRoom = 'delete FROM ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  // $sthDeleteRoom = $dbh->prepare($sqlDeleteRoom);
  // $sthDeleteRoom->execute(array($roomId));
}

// ーーーーーーーーーーーー家事マニュアルとその選択肢ーーーーーーーーーーーーーーーーー
// ーーーーーーーーーーーーBotからの返信関連（QuickReplyとflexMessage）ーーーーーーーーーーーーーーーーー

// フレックスメッセージに添付するクイックリプライボタン
function flexMessageQuickReply(){
  $flexMessageQuickReply = array( 
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('1)異物混入チェック', 'step1')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('2)汚れの下洗い', 'step2')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('3)洗濯ネットで保護', 'step3')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('4)洗濯ネットの収納場所', 'step4')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('5)洗剤の収納場所', 'step5')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('6)洗剤の種類', 'step6')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('7)洗濯機の水量', 'step7')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('8)洗剤の量と水量の関係性', 'step8')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('9)洗剤の量について', 'step9')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('10)洗剤の投入口', 'step10')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('11)柔軟剤について', 'step11')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('12)柔軟剤の投入口', 'step12')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('13)洗濯機スタート', 'step13')) 
  );
  return $flexMessageQuickReply;
}


// クイックリプライを添付。引数はLINEBot、返信先、textMessage、アクション
function replyQuickReplyButton($bot, $replyToken, $text1, ...$actions) {
// function replyQuickReplyButton($bot, $replyToken, $text1, $label, $text2) {
  $quickReplyButtons = array();
  foreach($actions as $value){
    array_push($quickReplyButtons,$value);
  }
  // $action = new \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder($label, $text2);
  // // var_dump($action->buildTemplateAction());
  // $button = new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder($action);
  // // var_dump($button->buildQuickReplyButton());
  $qr = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
  // var_dump($qr->buildQuickReply());
  $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($text1, $qr);
  // var_dump($textMessageBuilder->buildMessage());

  $response = $bot->replyMessage($replyToken, $textMessageBuilder);

  if (!$response->isSucceeded()) {
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// フレックスメッセージ
function replyFlexMessage($bot, $replyToken, $altText, $layout, $headerTextComponents=[], $bodyTextComponents=[], $footerTextComponents=[], $heroImageUrl, $heroImageSize, $aspectRatio, $aspectMode, $quickReply, $headerPaddingTop, $headerPaddingBottom, $bodyPaddingEnd, $bodyPaddingStart, $footerPaddingBottom, $footerPaddingEnd, $footerPaddingStart) {
  $headerBoxComponentBuilder = array();
  foreach($headerTextComponents as $value){
    array_push($headerBoxComponentBuilder,$value);
  }
  // $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout($vertical);
  // $componentBuilders = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder($text);
  $headerComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder($layout, $headerBoxComponentBuilder);
  $headerComponentBuilder->setPaddingTop($headerPaddingTop);
  $headerComponentBuilder->setPaddingBottom($headerPaddingBottom);

  $bodyBoxComponentBuilders = array();
  foreach($bodyTextComponents as $value){
    array_push($bodyBoxComponentBuilders,$value);
  }
  $bodyComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder($layout, $bodyBoxComponentBuilders);
  $bodyComponentBuilder->setPaddingEnd($bodyPaddingEnd);
  $bodyComponentBuilder->setPaddingStart($bodyPaddingStart);

  $footerBoxComponentBuilder = array();
  foreach($footerTextComponents as $value){
    array_push($footerBoxComponentBuilder,$value);
  }
  $footerComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder($layout, $footerBoxComponentBuilder);
  // $footerComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder($layout, $footerBoxComponentBuilder, null, $spacing);//spacingは横との隙間だった
  // $footerComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder();
  // $footerComponentBuilder->setLayout($layout);
  // $footerComponentBuilder->setContents($footerBoxComponentBuilder);
  $footerComponentBuilder->setPaddingBottom($footerPaddingBottom);
  $footerComponentBuilder->setPaddingEnd($footerPaddingEnd);
  $footerComponentBuilder->setPaddingStart($footerPaddingStart);

  $heroComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder($heroImageUrl, null, null, null, null, $heroImageSize, $aspectRatio, $aspectMode);

  $containerBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder();
  $containerBuilder->setHeader($headerComponentBuilder);
  $containerBuilder->setHero($heroComponentBuilder);
  $containerBuilder->setBody($bodyComponentBuilder);
  $containerBuilder->setFooter($footerComponentBuilder);

  $messageBuilder = new \LINE\LINEBot\MessageBuilder\FlexMessageBuilder($altText, $containerBuilder, $quickReply);
  $response = $bot->replyMessage($replyToken, $messageBuilder);
  if (!$response->isSucceeded()) {
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}
// function pushFlexMessage($bot,$target) {
//   $componentBuilder = new TextComponentBuilder('test');
//   $bodyComponentBuilder = new BoxComponentBuilder(ComponentLayout::VERTICAL, > [$componentBuilder]);
//   $containerBuilder = new BubbleContainerBuilder();
//   $containerBuilder->setBody($bodyComponentBuilder);
//   $messageBuilder = new FlexMessageBuilder('testalt', $containerBuilder);
//   $response = $bot->pushMessage($target, $messageBuilder);
//   if ($response->isSucceeded()) {
//       echo 'Succeeded!';
//       return;
//   }
//   // Failed
//   echo $response->getHTTPStatus() . ' ' . $response->getRawBody();
// }
// // 重要なのはここですね。
//     $containerBuilder = new BubbleContainerBuilder();
//     $containerBuilder->setBody($bodyComponentBuilder);
// 上記のコードだとそこの部分も書き方変えてます
// $bodyComponentBuilder = new BoxComponentBuilder(ComponentLayout::VERTICAL, > [$componentBuilder]);

// フレックスメッセージ
function replyFlexMessageForModification($bot, $replyToken, $altText, $layout, $headerTextComponents=[], $bodyBoxComponentSteps=[], $heroImageUrl, $aspectMode, $headerPaddingTop, $headerPaddingBottom, $bodyPaddingTop, $bodyPaddingBottom) {
  $headerBoxComponentBuilder = array();
  foreach($headerTextComponents as $value){
    array_push($headerBoxComponentBuilder,$value);
  }
  $headerComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder($layout, $headerBoxComponentBuilder);
  $headerComponentBuilder->setPaddingTop($headerPaddingTop);
  $headerComponentBuilder->setPaddingBottom($headerPaddingBottom);


  $bodyBoxComponentBuilders = array();
  foreach($bodyBoxComponentSteps as $value){
    array_push($bodyBoxComponentBuilders,$value);
  }
  $bodyComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder($layout, $bodyBoxComponentBuilders);
  $bodyComponentBuilder->setPaddingTop($bodyPaddingTop);
  $bodyComponentBuilder->setPaddingBottom($bodyPaddingBottom);
   

  $heroComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder($heroImageUrl, null, null, null, null, null, null, $aspectMode);

  $containerBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder();
  $containerBuilder->setHeader($headerComponentBuilder);
  $containerBuilder->setHero($heroComponentBuilder);
  $containerBuilder->setBody($bodyComponentBuilder);

  $messageBuilder = new \LINE\LINEBot\MessageBuilder\FlexMessageBuilder($altText, $containerBuilder);
  $response = $bot->replyMessage($replyToken, $messageBuilder);
  if (!$response->isSucceeded()) {
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// ーーーーーーーーーーーーBotからの返信関連（基本の雛形）ーーーーーーーーーーーーーーーーー

// テキストを返信。引数はLINEBot、返信先、テキスト
function replyTextMessage($bot, $replyToken, $text) {
  // 返信を行いレスポンスを取得
  // TextMessageBuilderの引数はテキスト
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($text));
  // レスポンスが異常な場合
  if (!$response->isSucceeded()) {
    // エラー内容を出力
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// 画像を返信。引数はLINEBot、返信先、画像URL、サムネイルURL
function replyImageMessage($bot, $replyToken, $originalImageUrl, $previewImageUrl) {
  // ImageMessageBuilderの引数は画像URL、サムネイルURL
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\ImageMessageBuilder($originalImageUrl, $previewImageUrl));
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// 位置情報を返信。引数はLINEBot、返信先、タイトル、住所、
// 緯度、経度
function replyLocationMessage($bot, $replyToken, $title, $address, $lat, $lon) {
  // LocationMessageBuilderの引数はダイアログのタイトル、住所、緯度、経度
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\LocationMessageBuilder($title, $address, $lat, $lon));
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// スタンプを返信。引数はLINEBot、返信先、
// スタンプのパッケージID、スタンプID
function replyStickerMessage($bot, $replyToken, $packageId, $stickerId) {
  // StickerMessageBuilderの引数はスタンプのパッケージID、スタンプID
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder($packageId, $stickerId));
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// 動画を返信。引数はLINEBot、返信先、動画URL、サムネイルURL
function replyVideoMessage($bot, $replyToken, $originalContentUrl, $previewImageUrl) {
  // VideoMessageBuilderの引数は動画URL、サムネイルURL
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\VideoMessageBuilder($originalContentUrl, $previewImageUrl));
  if (!$response->isSucceeded()) {
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// オーディオファイルを返信。引数はLINEBot、返信先、
// ファイルのURL、ファイルの再生時間
function replyAudioMessage($bot, $replyToken, $originalContentUrl, $audioLength) {
  // AudioMessageBuilderの引数はファイルのURL、ファイルの再生時間
  $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\AudioMessageBuilder($originalContentUrl, $audioLength));
  if (!$response->isSucceeded()) {
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// 複数のメッセージをまとめて返信。引数はLINEBot、
// 返信先、メッセージ(可変長引数)
// {"message":"Size must be between 1 and 5","property":"messages"}
function replyMultiMessage($bot, $replyToken, ...$msgs) {
  // MultiMessageBuilderをインスタンス化
  $builder = new \LINE\LINEBot\MessageBuilder\MultiMessageBuilder();
  // ビルダーにメッセージを全て追加
  foreach($msgs as $value) {
    $builder->add($value);
  }
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// Buttonsテンプレートを返信。引数はLINEBot、返信先、代替テキスト、
// 画像URL、タイトル、本文、アクション(可変長引数)
function replyButtonsTemplate($bot, $replyToken, $alternativeText, $imageUrl, $title, $text, ...$actions) {
  // アクションを格納する配列
  $actionArray = array();
  // アクションを全て追加
  foreach($actions as $value) {
    array_push($actionArray, $value);
  }
  // TemplateMessageBuilderの引数は代替テキスト、ButtonTemplateBuilder
  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder(
    $alternativeText,
    // ButtonTemplateBuilderの引数はタイトル、本文、
    // 画像URL、アクションの配列
    new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder ($title, $text, $imageUrl, $actionArray)
  );
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// Confirmテンプレートを返信。引数はLINEBot、返信先、代替テキスト、
// 本文、アクション(可変長引数)
function replyConfirmTemplate($bot, $replyToken, $alternativeText, $text, ...$actions) {
  $actionArray = array();
  foreach($actions as $value) {
    array_push($actionArray, $value);
  }
  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder(
    $alternativeText,
    // Confirmテンプレートの引数はテキスト、アクションの配列
    new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ConfirmTemplateBuilder ($text, $actionArray)
  );
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// Carouselテンプレートを返信。引数はLINEBot、返信先、代替テキスト、
// ダイアログの配列
function replyCarouselTemplate($bot, $replyToken, $alternativeText, $columnArray) {
  $builder = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder(
  $alternativeText,
  // Carouselテンプレートの引数はダイアログの配列
  new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder (
   $columnArray)
  );
  $response = $bot->replyMessage($replyToken, $builder);
  if (!$response->isSucceeded()) {
    error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// ーーーーーーーーーーーーDB関連ーーーーーーーーーーーーーーーーー
// データベースへの接続を管理するクラス
class dbConnection {
  // インスタンス
  protected static $db;
  // コンストラクタ
  private function __construct() {

    try {
      // 環境変数からデータベースへの接続情報を取得し
      $url = parse_url(getenv('DATABASE_URL'));
      // データソース
      $dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));
      // 接続を確立
      self::$db = new PDO($dsn, $url['user'], $url['pass']);
      // エラー時例外を投げるように設定
      self::$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }
    catch (PDOException $e) {
      error_log('Connection Error: ' . $e->getMessage());
    }
  }

  // シングルトン。存在しない場合のみインスタンス化
  public static function getConnection() {
    if (!self::$db) {
      new dbConnection();
    }
    return self::$db;
  }
}

?>
