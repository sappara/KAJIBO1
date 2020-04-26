<?php

// Composerでインストールしたライブラリを一括読み込み
require_once __DIR__ . '/vendor/autoload.php';
// テーブル名を定義
define('TABLE_NAME_ROOMS', 'rooms');
define('TABLE_NAME_STEP4S', 'step4s');

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
          replyConfirmTemplate($bot, $event->getReplyToken(), '本当に退出しますか？', '本当に退出しますか？',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_leave'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', 'cancel'));
            // このPostbackTemplateActionBuilder「cancel」はどこにも繋がっていない
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

        // // LIFFで完了ボタン押した後の処理
        // else if(substr($event->getText(), 4) == '完了'){
        //   // スタンプと文字を返信
        //   replyMultiMessage($bot, $event->getReplyToken(),
        //     new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('洗濯機回してくれてありがとう✨'),
        //     new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(11539, 52114110)
        //   );
        // }

        // 家事stepの選択肢ボタンをタイムラインに投稿
        else if(substr($event->getPostbackData(), 4) == '洗う'){
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


        continue;
      }


      // 家事stepの選択肢ボタンをタップした時の処理
      if($event->getPostbackData() == 'step1'){
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
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step4   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットの収納場所',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットは「引き出しや戸棚の中」を探してください',null,null,null,null,null,true)];
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
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step5   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の収納場所',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤は「引き出しや戸棚の中」を探してください',null,null,null,null,null,true)];
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
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step6   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の種類',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('毎日の衣類・タオル類には「ハイジア」を使ってください。',null,null,null,null,null,true)];
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
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step9   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量について',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量は「背面か側面に載ってますので見てください」',null,null,null,null,null,true)];
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
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step10   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の投入口',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤を入れる場所は「機種によって異なります。洗濯機の中かフチか洗濯機の上部かにあります。」',null,null,null,null,null,true)];
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
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step11   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤について',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤は「必要であれば入れてください。」',null,null,null,null,null,true)];
        // echo ComponentLayout::VERTICAL;
        $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
        $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/junanzai.jpg';
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
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step12   ★洗濯機で洗う（全13step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤の投入口',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤を入れる場所は「洗剤とは異なる投入口が洗濯機にあります。」',null,null,null,null,null,true)];
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

  // LIFFで完了ボタン押した後の処理
  if($event->getText() == '洗濯開始作業完了！'){
    // スタンプと文字を返信
    replyMultiMessage($bot, $event->getReplyToken(),
      new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('洗濯機回してくれてありがとう✨'),
      new \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(11539, 52114110)
    );
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

  // step4に登録
  if($event->getText() == '登録したい'){
    if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
      replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
    } else {
      replyConfirmTemplate($bot, $event->getReplyToken(), 'step4に登録しますか。', 'step4に登録しますか。',
        new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('はい', '先頭に 「step4」 とつけて続けて収納場所を書いて送信してください。'),
        new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', 'cansel'));
    }
  }
  // step4に登録を実行
  if(substr($event->getText(), 0, 7) == '「step4」') {
    // ルーム作成
    // if(substr($event->getText(), 4) == 'newroom') {
      // ユーザーが未入室の時
      // if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
        // ルームを作成し入室後ルームIDを取得
        // $roomId = createRoomAndGetRoomId($event->getUserId());
    // step4に登録を実行
    // if($event->getText() == 'cmd_登録') {
    if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
      $step4 = substr($event->getText(), 7);
      $roomId = getRoomIdOfUser($event->getUserId());
      registerStep4($roomId, $step4);
      replyTextMessage($bot, $event->getReplyToken(), '登録しました。');
    } else {
      replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
    }
  }
}


// step4を登録
function registerStep4($roomId, $step4){
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STEP4S .' (roomid, step4) values (?, ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId, $step4));
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
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('【ご報告】洗濯機を回しました✨'));
  }

  // ルームを削除（ユーザーも削除？）
  // $sqlDeleteRoom = 'delete FROM ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  // $sthDeleteRoom = $dbh->prepare($sqlDeleteRoom);
  // $sthDeleteRoom->execute(array($roomId));
}

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
