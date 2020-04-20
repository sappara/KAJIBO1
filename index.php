<?php

// Composerでインストールしたライブラリを一括読み込み
require_once __DIR__ . '/vendor/autoload.php';
// テーブル名を定義
define('TABLE_NAME_ROOMS', 'rooms');

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
      // 家事stepの選択肢ボタンをタップした時の処理
      if($event->getPostbackData() == 'お試し'){
        replyFlexMessage($bot, $event->getReplyToken(), 'altText', ComponentLayout::VERTICAL, new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step1'));
      }
  
      continue;
    }
        // $json = file_get_contents('flex4.json');
        // $json = json_decode($json,true);
        // $container = [ $json ];
        // $contents = [
        //   "type" => "bubble",
        //   "header" => [
        //     "type" => "box",
        //     "layout" => "vertical",
        //     "contents" => [
        //       [
        //         "type" => "text",
        //         "text" => "step1",
        //         "weight" => "bold",
        //         "size" => "xl"
        //       ]
        //     ]
        //   ],
        //   "hero" => [
        //     "type" => "image",
        //     "url" => 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0234.jpg',
        //     "size" => "full",
        //     "aspectRatio" => "20:13",
        //     "aspectMode" => "cover",
        //   ],
        //   "body" => [
        //     "type" => "box",
        //     "layout" => "vertical",
        //     "contents" => [
        //       [
        //         "type" => "text",
        //         "text" => "下準備１：異物混入チェック",
        //         "weight" => "bold",
        //         "size" => "lg",
        //         "wrap" => true
        //       ]
        //     ]
        //   ],
        //   "footer" => [
        //     "type" => "box",
        //     "layout" => "vertical",
        //     "contents" => [
        //       [
        //         "type" => "text",
        //         "text" => "黒いもの。長いもの。引っかかりそうなもの。剥がれそうなもの。該当すれば洗濯ネットに入れて保護。",
        //         "wrap" => true
        //       ],
        //       [
        //         "type" => "spacer",
        //         "size" => "xxl"
        //       ]
        //     ]
        //   ]
        // ];
        // $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout('vertical');
        // $componentBuilders = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step1');
        // $headerComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder($layout, $componentBuilders);
        // $containerBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder($headerComponentBuilder);
        // $heroComponentBuilder = new \
        // $bodyComponentBuilder = new \
        // $footerComponentBuilder = new \
        // $containerBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder($headerComponentBuilder, $heroComponentBuilder, $bodyComponentBuilder, $footerComponentBuilder);
        // $messageBuilder = new \LINE\LINEBot\MessageBuilder\FlexMessageBuilder('altText', $containerBuilder);
        // $flexMessageBuilder = new \LINE\LINEBot\MessageBuilder($Message);
        // $flexMessageBuilder->buildMessage($Message);
        // step一個を返信
        // replyFlexMessage($bot,$event->getReplyToken(), $flexMessageBuilder
        // $response = $bot->replyMessage($event->getReplyToken(), $messageBuilder);
        // if (!$response->isSucceeded()) {
        //   error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
        // }
        
        // '「洗う」のステップです',
        // 'step1',
        // 'https://' . $_SERVER['HTTP_HOST'] . '/img/IMG_0724.jpg',
        // '洗濯機で洗うステップ開始',
        // 'まず洗剤を探してください'
        // );

  

  // MessageEvent型でなければ処理をスキップ
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent)) {
    error_log('Non message event has come');
    continue;
  }
  // TextMessage型でなければ処理をスキップ
  if (!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
    error_log('Non text message has come');
    continue;
  }

  // リッチコンテンツがタップされた時
  if(substr($event->getText(), 0, 4) == 'cmd_') {
    // ルーム作成
    if(substr($event->getText(), 4) == 'newroom') {
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
    else if(substr($event->getText(), 4) == 'enter') {
      // ユーザーが未入室の時
      if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
        replyTextMessage($bot, $event->getReplyToken(), 'ルームIDを入力してください。');
      } else {
        replyTextMessage($bot, $event->getReplyToken(), '入室済みです。');
      }
    }
    // 退室の確認ダイアログ
    else if(substr($event->getText(), 4) == 'leave_confirm') {
      replyConfirmTemplate($bot, $event->getReplyToken(), '本当に退出しますか？', '本当に退出しますか？',
        new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', 'cmd_leave'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('いいえ', 'cancel'));
        // このPostbackTemplateActionBuilder「cancel」はどこにも繋がっていない
    }
    // 退室
    else if(substr($event->getText(), 4) == 'leave') {
      if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
        leaveRoom($event->getUserId());
        replyTextMessage($bot, $event->getReplyToken(), '退室しました。');
      } else {
        replyTextMessage($bot, $event->getReplyToken(), 'ルームに入っていません。');
      }
    }

    // 作業終了の報告
    else if(substr($event->getText(), 4) == 'end_confirm') {
      if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
        replyTextMessage($bot, $event->getReplyToken(), 'ルームに入っていません。');
      } else {
        replyConfirmTemplate($bot, $event->getReplyToken(), '作業完了しましたか？メンバー皆様に完了報告を送信します。', '作業完了しましたか？メンバー皆様に完了報告を送信します。',
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', 'cmd_end'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', 'おつかされまでした🍺'));
      }
    }
    // 終了
    else if(substr($event->getText(), 4) == 'end') {
      endKaji($bot, $event->getUserId());
    }

    // LIFFで完了ボタン押した後の処理
     else if(substr($event->getText(), 4) == '完了'){
      // スタンプと文字を返信
      replyMultiMessage($bot, $event->getReplyToken(),
        new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('洗濯機回してくれてありがとう✨'),
        new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(11539, 52114110)
      );
    }

    // 家事stepの選択肢ボタンをタイムラインに投稿
    else if(substr($event->getText(), 4) == '洗う'){
      replyQuickReplyButton($bot, $event->getReplyToken(), '洗濯する方法でわからないことがあれば、下のボタンを押してね。',
       new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('洗濯前の準備', 'cmd_洗濯前の準備')),
        new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('洗剤について', 'cmd_洗剤について')),
         new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('柔軟剤について', 'cmd_柔軟剤について')),
         new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('洗濯機スタート', 'cmd_洗濯機スタート')),
         new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('お試し', 'お試し'))
      );
    }

    // 家事stepの選択肢ボタンをタップした時の処理(カルーセルテンプレート)
    else if(substr($event->getText(), 4) == '洗濯前の準備'){
      // step1~4を返信
      $columnArray = array();      
        $actionArray = array();
        array_push($actionArray, new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder ('次へ', 'cmd_洗剤について'));
        // 各stepの内容
        $column1 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step1 下準備１：異物混入チェック',
          '紙や異物が混じってないかポケットを確認してください。',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0724.jpg',
          $actionArray
        );
        $column2 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step2 下準備２：泥汚れの下洗い',
          '泥や排泄物で汚れていたら、風呂場で軽く下洗いしてください。',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0721.jpg',
          $actionArray
        );
        $column3 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step3 下準備３：洗濯ネットで保護',
          '黒いもの。長いもの。引っかかりそうなもの。剥がれそうなもの。該当すれば洗濯ネットに入れて保護。',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0234.jpg',
          $actionArray
        );
        $column4 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step4 洗濯ネットの収納場所',
          '洗濯ネットは「引き出しや戸棚の中」を探してください',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0725.jpg',
          $actionArray
        );
        // 配列に追加
        array_push($columnArray, $column1,$column2,$column3,$column4);
      replyCarouselTemplate($bot, $event->getReplyToken(),'洗濯前の準備', $columnArray);
    }

    else if(substr($event->getText(), 4) == '洗剤について'){
      // step5~10を返信
      $columnArray = array();      
        $actionArray = array();
        array_push($actionArray, new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder ('次へ', 'cmd_柔軟剤について'));
        // 各stepの内容
        $column5 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step5 洗剤の収納場所',
          '洗剤は「引き出しや戸棚の中」を探してください',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0214.jpg',
          $actionArray
        );
        $column6 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step6 洗剤の種類',
          '毎日の衣類・タオル類には「ハイジア」を使ってください。',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0720.jpg',
          $actionArray
        );
        $column7 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step7 洗濯機の水量',
          '全て洗濯機に入れたら、水量を知るために、洗濯機のスタートボタンを押してください。',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0710.jpg',
          $actionArray
        );
        $column8 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step8 洗剤について',
          '洗濯物の量に応じて水量が変わります、洗剤を水量に応じて入れます。',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0713.jpg',
          $actionArray
        );
        $column9 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step9 洗剤の量について',
          '洗剤の量は「背面か側面に載ってますので見てください」',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0215.jpg',
          $actionArray
        );
        $column10 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step10 洗剤の投入口',
          '洗剤を入れる場所は「機種によって異なります。洗濯機の中かフチか洗濯機の上部かにあります。」',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0218.jpg',
          $actionArray
        );
        // 配列に追加
        array_push($columnArray, $column5,$column6,$column7,$column8,$column9,$column10);
      replyCarouselTemplate($bot, $event->getReplyToken(),'洗剤について', $columnArray);
    }

    else if(substr($event->getText(), 4) == '柔軟剤について'){
      // step11~12を返信
      $columnArray = array();      
        $actionArray = array();
        array_push($actionArray, new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder ('次へ', 'cmd_洗濯機スタート'));
        // 各stepの内容
        $column11 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step11 柔軟剤について',
          '柔軟剤は「必要であれば入れてください。」',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/junanzai.jpg',
          $actionArray
        );
        $column12 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step12 柔軟剤の投入口',
          '柔軟剤を入れる場所は「洗剤とは異なる投入口が洗濯機にあります。」',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0708.jpg',
          $actionArray
        );
        // 配列に追加
        array_push($columnArray, $column11,$column12);
      replyCarouselTemplate($bot, $event->getReplyToken(),'柔軟剤について', $columnArray);
    }

    else if(substr($event->getText(), 4) == '洗濯機スタート'){
      // step13を返信
      $columnArray = array();      
        $actionArray = array();
        array_push($actionArray, new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder ('完了', 'cmd_完了'));
        // 各stepの内容
        $column13 = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder (
          'step13 洗濯機スタート',
          '洗濯機の蓋を閉めると洗濯が始まります。',
          'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0715.jpg',
          $actionArray
        );
        // 配列に追加
        array_push($columnArray, $column13);
      replyCarouselTemplate($bot, $event->getReplyToken(),'洗濯機スタート', $columnArray);
    }

    continue;
  }

  // リッチコンテンツ以外の時(ルームIDが入力された時)
  if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
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

}

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

// 作業終了の報告
function endKaji($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);

  $dbh = dbConnection::getConnection();
  $sql = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array(getRoomIdOfUser($userId)));
  // 各ユーザーにメッセージを送信
  foreach ($sth->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('作業終了しました✨'));
  }

  // ルームを削除（ユーザーも削除？）
  // $sqlDeleteRoom = 'delete FROM ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  // $sthDeleteRoom = $dbh->prepare($sqlDeleteRoom);
  // $sthDeleteRoom->execute(array($roomId));
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
// function replyFlexMessage($bot, $replyToken, $FlexMessage) {

  // $headerComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder($dir_text);
  // $heroComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder($url);
  // $bodyComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder($body_text);
  // $footerComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder($foot_text);

  // $containerBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder($headerComponentBuilder,$heroComponentBuilder,$bodyComponentBuilder,$footerComponentBuilder);
  // $FlexMessageBuilder = new \LINE\LINEBot\MessageBuilder\FlexMessageBuilder();
  // $FlexMessageBuilder -> setAltText('Restaurant');
  // $FlexMessageBuilder -> setContents($containerBuilder);

  // $containerBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder();
  // $containerBuilder ->setHero($heroComponentBuilder);
  // $containerBuilder ->setBody($bodyComponentBuilder);
  // $containerBuilder ->setFooter($footerComponentBuilder);
  // $containerBuilder ->setSize(BubleContainerSize::GIGA);

  // $heroComponentBuilder = createHeroBlock();
  // $bodyComponentBuilder = createBodyBlock();
  // $footerComponentBuilder = createFooterBlock();

  // $response = $bot->replyMessage($replyToken, $FlexMessageBuilder);
// function replyFlexMessage($bot, $replyToken, $altText, $flexMessage) {
  // $containerBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder($flexMessage);
  // build()
  // $flexMessageBuilder = new \LINE\LINEBot\MessageBuilder\FlexMessageBuilder($altText, $flexMessage);
  // buildMessage()
//   function replyFlexMessage($bot, $replyToken, $flexMessageBuilder) {
//   $response = $bot->replyMessage($replyToken, $flexMessageBuilder);
//   if (!$response->isSucceeded()) {
//     error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
//   }
// }
function replyFlexMessage($bot, $replyToken, $altText, $vertical, ...$TextComponents) {
  $componentBuilders = array();
  foreach($TextComponents as $value){
    array_push($componentBuilders,$value);
  }
  $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout($vertical);
  // $componentBuilders = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder($text);
  $bodyComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder($layout, $componentBuilders);
  $containerBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder();
  $containerBuilder->setBody($bodyComponentBuilder);
  $messageBuilder = new \LINE\LINEBot\MessageBuilder\FlexMessageBuilder($altText, $containerBuilder);
  $response = $bot->replyMessage($replyToken, $messageBuilder);
  if (!$response->isSucceeded()) {
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}
// // 重要なのはここですね。
//     $containerBuilder = new BubbleContainerBuilder();
//     $containerBuilder->setBody($bodyComponentBuilder);
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
// 上記のコードだとそこの部分も書き方変えてます
// $bodyComponentBuilder = new BoxComponentBuilder(ComponentLayout::VERTICAL, > [$componentBuilder]);



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
