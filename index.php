<?php

// Composerでインストールしたライブラリを一括読み込み
require_once __DIR__ . '/vendor/autoload.php';

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

$actionArray = array();

// 配列に格納された各イベントをループで処理
foreach ($events as $event) {
    // // MessageEventクラスのインスタンスでなければ処理をスキップ
    // if (!($event instanceof \LINE\LINEBot\Event\MessageEvent)) {
    //   error_log('Non message event has come');
    //   continue;
    // }
    // // TextMessageクラスのインスタンスでなければ処理をスキップ
    // if (!($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage)) {
    //   error_log('Non text message has come');
    //   continue;
    // }

    // TextMessageクラスのインスタンスの場合
    if ($event instanceof \LINE\LINEBot\Event\MessageEvent\TextMessage) {
      // 入力されたテキストを取得
      $word = $event->getText();
    }

    // PostbackEventクラスのインスタンスの場合
      if ($event instanceof \LINE\LINEBot\Event\PostbackEvent) {
      // 入力されたテキストを取得
      $word = $event->getPostbackData();
    }

    if($word == '洗う'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗う」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/template.jpg',
      '洗濯機で洗うステップ開始 (step1/14)',
      'まず洗剤を探してください',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '洗剤の場所')
      );
    }

    if($word == '洗剤の場所'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗剤の場所」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/img0214.jpg',
      '洗剤の場所 (step2/14)',
      '洗剤は引き出しや戸棚の中を探してください',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '洗剤の量')
      );
    }

    if($word == '洗剤の量'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗剤の量」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/img0215.jpg',
      '洗剤の量 (step3/14)',
      '洗剤の使う量は背面か側面に載ってますので見てください',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '洗う前の注意点')
      );
    }

    if($word == '洗う前の注意点'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗う前の注意点」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/template.jpg',
      '洗う前の注意点 (step4/14)',
      '洗うものを洗濯機に入れてください。最初に３つの注意点をお伝えします。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '注意点1')
      );
    }

    if($word == '注意点1'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗う前の注意点1」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/template.jpg',
      '洗う前の注意点1 (step5/14)',
      '紙や異物が混じってないかポケット確認してください。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '注意点2')
      );
    }

    if($word == '注意点2'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗う前の注意点2」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/template.jpg',
      '洗う前の注意点2 (step6/14)',
      '泥や排泄物で汚れていたら、風呂場で軽く下洗いしてください。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '注意点3')
      );
    }

    if($word == '注意点3'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗う前の注意点3」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/img0222.jpg',
      '洗う前の注意点3 (step7/14)',
      '洗濯ネットで保護した方が良い衣服が４種類あります。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '洗濯ネットに入れるもの')
      );
    }

    if($word == '洗濯ネットに入れるもの'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗濯ネットに入れるもの」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/img0234.jpg',
      '洗濯ネットに入れるもの (step8/14)',
      '黒いもの。長いもの。引っかかりそうなもの。剥がれそうなものの4つです。該当すれば洗濯ネットへ。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '洗濯ネットの場所')
      );
    }

    if($word == '洗濯ネットの場所'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗濯ネットの場所」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/img0223.jpg',
      '洗濯ネットの場所 (step9/14)',
      '洗濯ネットは引き出しや戸棚の中を探してください',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '洗濯機の水量')
      );
    }

    if($word == '洗濯機の水量'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗濯機の水量」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/template.jpg',
      '洗濯機の水量 (step10/14)',
      '全て洗濯機に入れ終わったら、水量を知るために、洗濯機のスタートボタンを押してください。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '洗剤を入れる')
      );
    }

    if($word == '洗剤を入れる'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗剤を入れる」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/template.jpg',
      '洗剤を入れる (step11/14)',
      '洗濯物の量に応じて水量が変わります。洗剤を水量に応じて入れます。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '洗剤を入れる場所')
      );
    }

    if($word == '洗剤を入れる場所'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗剤を入れる場所」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/img0218.jpg',
      '洗剤を入れる場所 (step12/14)',
      '洗剤を入れる場所は機種によって異なります。洗濯槽の中かフチか洗濯機の上部かにあります。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '柔軟剤')
      );
    }

    if($word == '柔軟剤'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「柔軟剤」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/template.jpg',
      '柔軟剤 (step13/14)',
      '柔軟剤も必要であれば入れてください。洗剤とは異なる投入口が洗濯機にあります。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '洗濯機スタート')
      );
    }

    if($word == '洗濯機スタート'){
      // Buttonsテンプレートメッセージを返信
      replyButtonsTemplate($bot,
      $event->getReplyToken(),
      '「洗濯機スタート」のステップです',
      'https://' . $_SERVER['HTTP_HOST'] . '/imgs/template.jpg',
      '洗濯機スタート (step14/14)',
      '洗濯機の蓋を閉めると洗濯が始まります。',
      new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder ('次へ', '完了')
      );
    }

    if($word == '完了'){
      // スタンプと文字を返信
      replyMultiMessage($bot, $event->getReplyToken(),
        new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('これで完了です。お疲れ様でした❣️'),
        new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(11537, 52002752)
      );
    }



  }

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
  
  // 位置情報を返信。引数はLINEBot、返信先、タイトル、住所、緯度、経度
  function replyLocationMessage($bot, $replyToken, $title, $address, $lat, $lon) {
    // LocationMessageBuilderの引数はダイアログのタイトル、住所、緯度、経度
    $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\LocationMessageBuilder($title, $address, $lat, $lon));
    if (!$response->isSucceeded()) {
      error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
    }
  }
  
  // スタンプを返信。引数はLINEBot、返信先、スタンプのパッケージID、スタンプID
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
  
  // オーディオファイルを返信。引数はLINEBot、返信先、ファイルのURL、ファイルの再生時間
  function replyAudioMessage($bot, $replyToken, $originalContentUrl, $audioLength) {
    // AudioMessageBuilderの引数はファイルのURL、ファイルの再生時間
    $response = $bot->replyMessage($replyToken, new \LINE\LINEBot\MessageBuilder\AudioMessageBuilder($originalContentUrl, $audioLength));
    if (!$response->isSucceeded()) {
      error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
    }
  }
  
  // 複数のメッセージをまとめて返信。引数はLINEBot、返信先、メッセージ(可変長引数)
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
  
  // Buttonsテンプレートを返信。引数はLINEBot、返信先、代替テキスト、画像URL、タイトル、本文、アクション(可変長引数)
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
      // ButtonTemplateBuilderの引数はタイトル、本文、画像URL、アクションの配列
      new \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder ($title, $text, $imageUrl, $actionArray)
    );
    $response = $bot->replyMessage($replyToken, $builder);
    if (!$response->isSucceeded()) {
      error_log('Failed!'. $response->getHTTPStatus . ' ' . $response->getRawBody());
    }
  }
  
  // Confirmテンプレートを返信。引数はLINEBot、返信先、代替テキスト、本文、アクション(可変長引数)
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
  
  // Carouselテンプレートを返信。引数はLINEBot、返信先、代替テキスト、ダイアログの配列
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
  
?>