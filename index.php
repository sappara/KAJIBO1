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
define('TABLE_NAME_STEP14S', 'step14s');
define('TABLE_NAME_USERSITUATIONS', 'usersituations');
define('TABLE_NAME_PHOTOSTEP10S', 'photostep10s');

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
        // $a = getRoomMate($event->getUserId());
        replyConfirmTemplate($bot, $event->getReplyToken(), '本当に退室しますか？', '本当に退室しますか？（全員が退室した場合ルームは削除されカスタマイズされた登録内容も失われます。）',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_leave'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '退室しません。ルームを維持します。'));
          // この時の「いいえ」はどこにも繋がっていない。これで終了。
      }
      // 退室
      else if(substr($event->getPostbackData(), 4) == 'leave') {
        // 先にルームID取っておく
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId !== PDO::PARAM_NULL) {//ルームIDがあれば
          // 自分のユーザーID消す
          leaveRoom($event->getUserId());

          if(getRoomMate($roomId) !== PDO::PARAM_NULL) {//仲間がまだルームに残っていたら
            // 自分のユーザーID消す
            // leaveRoom($event->getUserId());
            replyTextMessage($bot, $event->getReplyToken(), '退室しました。');
          } else {//誰もルームに残ってなかったら
            if(getFilenamePhoto10($roomId) !== PDO::PARAM_NULL) {//ファイル名が保存されてる時
              // 写真登録履歴あれば、前の写真消す
              \Cloudinary::config(array(
                'cloud_name' => getenv('CLOUDINARY_NAME'),
                'api_key' => getenv('CLOUDINARY_KEY'),
                'api_secret' => getenv('CLOUDINARY_SECRET')
              ));
              $oldfilename = getFilenamePhoto10($roomId);
              $public_id = 'kajiboimage/step10photo/'.$roomId.'/'.$oldfilename;
              $resultDelete = \Cloudinary\Uploader::destroy($public_id);
              // DBの各テーブルからもデータを消す
              // leaveRoom($event->getUserId());
              destroyAllRoom($roomId);
              replyTextMessage($bot, $event->getReplyToken(), '退室しました。保存されていたデータと写真を消去しました。');
            } else {//ファイル名の保存がない時
              // Cloudinaryには接続しないで、
              // DBの各テーブルからデータを消す
              // leaveRoom($event->getUserId());
              destroyAllRoom($roomId);
              replyTextMessage($bot, $event->getReplyToken(), '退室しました。保存されていたデータを消去しました。');
            }
          }
        } else {//ルームIDがなければ
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入っていません。');
        }
      }

      // ーーーーーーーーーーーー家事のメニュー（pushMessage関連）ーーーーーーーーーーーーーーーーー

      // 作業終了の報告
      else if(substr($event->getPostbackData(), 4) == 'end_confirm') {
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入っていません。');
        } else {
          replyConfirmTemplate($bot, $event->getReplyToken(), '作業完了しましたか？ルーム全員に完了報告を送信します。', '作業完了しましたか？ルーム全員に完了報告を送信します。',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_end'),
            new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '送信しません。'));
        }
      }
      // 終了
      else if(substr($event->getPostbackData(), 4) == 'end') {
        endKaji($bot, $event->getUserId());
      }

      // ーーーーーーーーーーーー家事マニュアルの選択肢ーーーーーーーーーーーーーーーーー

      // 家事stepの選択肢ボタンをタイムラインに投稿
      else if(substr($event->getPostbackData(), 4) == 'kaji'){
        replyQuickReplyButton($bot, $event->getReplyToken(), '洗濯マニュアルを個別stepで表示します。下のボタンを押してください。',
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
        replyConfirmTemplate($bot, $event->getReplyToken(),
        '操作方法の選択肢',
        '使い方の詳細はWebでご覧いただけます。web遷移時に認証が必要になります。',
        new LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder (
          'webで見る', 'https://liff.line.me/1654188823-B2ax05Mb'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '簡易版', 'cmd_simpleHowToUse')
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
        $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('カスタマイズのメニューへ', linkToUser(getenv('CHANNEL_ACCESS_TOKEN'), $event->getUserId(), 'richmenu-8405eb278b84adaadae1c5ddb7567c57')));
        // 登録更新削除バージョン'richmenu-483be03d906642db37c9bf40a14c421b'
      }
      // cmd_main_menu
      else if(substr($event->getPostbackData(), 4) == 'main_menu'){
        $bot->replyMessage($event->getReplyToken(), new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('メインメニューに戻る', linkToUser(getenv('CHANNEL_ACCESS_TOKEN'), $event->getUserId(), 'richmenu-09dfd1ce5fcf91cff8d2a64eb2546cfe')));
      }

      // cmd_simpleHowToUse
      else if(substr($event->getPostbackData(), 4) == 'simpleHowToUse'){
        replyMultiMessage($bot, $event->getReplyToken(),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('【家事マニュアルを見る】メニューバー「家事する時」→「個別に見る」→緑のボタン（ステップ選ぶ）'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('【家事マニュアルをカスタマイズ】メニューバー「カスタマイズ」→「登録修正」→緑のボタン（ステップ選ぶ）'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('※家事マニュアルをカスタマイズした際、同じルームに入室している全員に通知が届きます。'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('【家族とマニュアル共有】①メニューバー「ルーム」→「ルームを作る」→ルームIDを転送。②KAJIBOをシェア（右上の三本線ボタン→おすすめ→転送）'),
          new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('【ルームIDを受け取ったら】①ルームIDコピー。②KAJIBOと友達になる。③ルームIDをペーストしてKAJIBOへ送信'));
      }


      // ーーーーーーーーーーーーカスタマイズのメニュー関連（写真）ーーーーーーーーーーーーーーーーー

      // step10に登録　→リッチメニュの説明文のアクションからの導入に変更
      else if(substr($event->getPostbackData(), 4) == 'photo'){
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          replyTextMessage($bot, $event->getReplyToken(), '写真を一枚送信してください。');
          // 下方の、ImageMessage型イベント確認グループに続く
        }
      }
      // cmd_showPhoto10
      else if(substr($event->getPostbackData(), 4) == 'showPhoto10'){
        $step10 = getStep10($event->getUserId());
        $roomId = getRoomIdOfUser($event->getUserId());
        $filename = getFilenamePhoto10($roomId);
        replyButtonsTemplate($bot, $event->getReplyToken(),
        'step10のカスタマイズ',
        // 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0218.jpg',
        'https://res.cloudinary.com/kajibo/kajiboimage/step10photo/'.$roomId.'/'.$filename.'.jpg',
        '10)洗剤の投入口',
        '洗剤を入れる場所は「'.$step10.'」',
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '写真を再変更する', 'cmd_changePhoto10'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '他の写真も変える', 'cmd_modifyPhoto')
        );
      }
      // cmd_changePhoto10
      else if(substr($event->getPostbackData(), 4) == 'changePhoto10'){
        replyTextMessage($bot, $event->getReplyToken(), 'もう一度、写真を一枚送信してください。');
        // 下方の、ImageMessage型イベント確認グループに続く
      }
      // cmd_modifyPhoto
      else if(substr($event->getPostbackData(), 4) == 'modifyPhoto'){
        replyTextMessage($bot, $event->getReplyToken(), 'プレミアムコースを現在開発中です。完成しましたらお知らせします。');
      }

      // ーーーーーーーーーーーーカスタマイズのメニュー関連ーーーーーーーーーーーーーーーーー

      // cmd_modify
      else if(substr($event->getPostbackData(), 4) == 'modify'){
        if(getRoomIdOfUser($event->getUserId()) === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), '登録するにはルームに入ってください。');
        } else {
          replyQuickReplyButton($bot, $event->getReplyToken(), '登録するstepを選んでください。下のボタンを押してください。',
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('4)洗濯ネットの収納場所', 'cmd_modification4')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('5)洗剤の収納場所', 'cmd_modification5')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('6)洗剤の種類', 'cmd_modification6')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('9)洗剤の量について', 'cmd_modification9')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('10)洗剤の投入口', 'cmd_modification10')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('11)柔軟剤について', 'cmd_modification11')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('12)柔軟剤の投入口', 'cmd_modification12')),
            new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('14)洗剤の詰めかえ', 'cmd_modification14'))
          );
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'modification4') {
        $step4 = getStep4($event->getUserId());
        replyButtonsTemplate($bot, $event->getReplyToken(),
        'step4のカスタマイズ',
        'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0725.jpg',
        '4 ) 洗濯ネットの収納場所',
        '洗濯ネットは「'.$step4.'」を探してください',
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '登録する', 'cmd_edit4'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '初期化する', 'cmd_delete4')
        );
      }
      else if(substr($event->getPostbackData(), 4) == 'modification5') {
        $step5 = getStep5($event->getUserId());
        replyButtonsTemplate($bot, $event->getReplyToken(),
        'step5のカスタマイズ',
        'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0214.jpg',
        '5 ) 洗剤の収納場所',
        '洗剤は「'.$step5.'」を探してください',
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '登録する', 'cmd_edit5'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '初期化する', 'cmd_delete5')
        );
      }
      else if(substr($event->getPostbackData(), 4) == 'modification6') {
        $step6 = getStep6($event->getUserId());
        replyButtonsTemplate($bot, $event->getReplyToken(),
        'step6のカスタマイズ',
        'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0720.jpg',
        '6 ) 洗剤の種類',
        '毎日の衣類・タオル類には「'.$step6.'」を使ってください',
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '登録する', 'cmd_edit6'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '初期化する', 'cmd_delete6')
        );
      }
      else if(substr($event->getPostbackData(), 4) == 'modification9') {
        $step9 = getStep9($event->getUserId());
        replyButtonsTemplate($bot, $event->getReplyToken(),
        'step9のカスタマイズ',
        'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0215.jpg',
        '9 ) 洗剤の量について',
        '洗剤の量は「'.$step9.'」',
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '登録する', 'cmd_edit9'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '初期化する', 'cmd_delete9')
        );
      }
      else if(substr($event->getPostbackData(), 4) == 'modification10') {
        $step10 = getStep10($event->getUserId());
        replyButtonsTemplate($bot, $event->getReplyToken(),
        'step10のカスタマイズ',
        'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0218.jpg',
        '10)洗剤の投入口',
        '洗剤を入れる場所は「'.$step10.'」',
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '登録する', 'cmd_edit10'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '初期化する', 'cmd_delete10')
        );
      }
      else if(substr($event->getPostbackData(), 4) == 'modification11') {
        $step11 = getStep11($event->getUserId());
        replyButtonsTemplate($bot, $event->getReplyToken(),
        'step11のカスタマイズ',
        'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0854.jpg',
        '11 ) 柔軟剤について',
        '柔軟剤は「'.$step11.'」',
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '登録する', 'cmd_edit11'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '初期化する', 'cmd_delete11')
        );
      }
      else if(substr($event->getPostbackData(), 4) == 'modification12') {
        $step12 = getStep12($event->getUserId());
        replyButtonsTemplate($bot, $event->getReplyToken(),
        'step12のカスタマイズ',
        'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0708.jpg',
        '12 ) 柔軟剤の投入口',
        '柔軟剤を入れる場所は「'.$step12.'」',
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '登録する', 'cmd_edit12'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '初期化する', 'cmd_delete12')
        );
      }
      else if(substr($event->getPostbackData(), 4) == 'modification14') {
        $step14 = getStep14($event->getUserId());
        replyButtonsTemplate($bot, $event->getReplyToken(),
        'step14のカスタマイズ',
        'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_1113.jpg',
        '14 ) 洗剤の詰めかえ',
        '洗剤が終わりかけなら詰めかえてください。ストックは「'.$step14.'」にあります。',
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '登録する', 'cmd_edit14'),
        new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder (
          '初期化する', 'cmd_delete14')
        );
      }

      // cmd_edit4
      else if(substr($event->getPostbackData(), 4) == 'edit4') {
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          setUserSituation($roomId, 'set4');
          replyTextMessage($bot, $event->getReplyToken(), '家事マニュアルを入力送信してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'edit5') {
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          setUserSituation($roomId, 'set5');
          replyTextMessage($bot, $event->getReplyToken(), '家事マニュアルを入力送信してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'edit6') {
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          setUserSituation($roomId, 'set6');
          replyTextMessage($bot, $event->getReplyToken(), '家事マニュアルを入力送信してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'edit9') {
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          setUserSituation($roomId, 'set9');
          replyTextMessage($bot, $event->getReplyToken(), '家事マニュアルを入力送信してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'edit10') {
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          setUserSituation($roomId, 'set10');
          replyTextMessage($bot, $event->getReplyToken(), '家事マニュアルを入力送信してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'edit11') {
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          setUserSituation($roomId, 'set11');
          replyTextMessage($bot, $event->getReplyToken(), '家事マニュアルを入力送信してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'edit12') {
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          setUserSituation($roomId, 'set12');
          replyTextMessage($bot, $event->getReplyToken(), '家事マニュアルを入力送信してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'edit14') {
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        } else {
          setUserSituation($roomId, 'set14');
          replyTextMessage($bot, $event->getReplyToken(), '家事マニュアルを入力送信してください。');
        }
      }

      // cmd_delete
      else if(substr($event->getPostbackData(), 4) == 'delete4') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '４）洗濯ネットの収納場所 を初期化しますか？', '４）洗濯ネットの収納場所 を初期化しますか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_executeDelete4'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete5') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '５）洗剤の収納場所 を初期化しますか？', '５）洗剤の収納場所 を初期化しますか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_executeDelete5'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete6') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '６）洗剤の種類 を初期化しますか？', '６）洗剤の種類 を初期化しますか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_executeDelete6'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete9') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '９）洗剤の量について を初期化しますか？', '９）洗剤の量について を初期化しますか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_executeDelete9'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete10') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '１０）洗剤の投入口 を初期化しますか？', '１０）洗剤の投入口 を初期化しますか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_executeDelete10'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete11') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '１１）柔軟剤について を初期化しますか？', '１１）柔軟剤について を初期化しますか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_executeDelete11'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete12') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '１２）柔軟剤の投入口 を初期化しますか？', '１２）柔軟剤の投入口 を初期化しますか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_executeDelete12'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }
      else if(substr($event->getPostbackData(), 4) == 'delete14') {
        replyConfirmTemplate($bot, $event->getReplyToken(), '１４）洗剤の詰めかえ を初期化しますか？', '１４）洗剤の詰めかえ を初期化しますか？',
          new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('はい', 'cmd_executeDelete14'),
          new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder('いいえ', '登録を維持します。'));
      }

      // cmd_executeDelete4
      else if(substr($event->getPostbackData(), 4) == 'executeDelete4') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep4($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep4($bot, $event->getUserId());
            replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification4'),
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'executeDelete5') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep5($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep5($bot, $event->getUserId());
            replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification5'),
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'executeDelete6') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep6($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep6($bot, $event->getUserId());
            replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification6'),
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'executeDelete9') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep9($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep9($bot, $event->getUserId());
            replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification9'),
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'executeDelete10') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep10($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep10($bot, $event->getUserId());
            replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification10'),
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'executeDelete11') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep11($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep11($bot, $event->getUserId());
            replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification11'),
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'executeDelete12') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep12($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep12($bot, $event->getUserId());
            replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification12'),
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
      else if(substr($event->getPostbackData(), 4) == 'executeDelete14') {
        if(getRoomIdOfUser($event->getUserId()) !== PDO::PARAM_NULL) {
          if(getDetailOfStep14($event->getUserId()) !== PDO::PARAM_NULL) {
            deleteStep14($bot, $event->getUserId());
            replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification14'),
            new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録がありませんでした。');
          }
        } else {
          replyTextMessage($bot, $event->getReplyToken(), 'ルームに入ってから登録してください。');
        }
      }
  

      continue;
    }


    // ーーーーーーーーーーーー家事マニュアル関連ーーーーーーーーーーーーーーーーー

    // 家事stepの選択肢ボタンをタップした時の処理
    else if($event->getPostbackData() == 'step1'){
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step1   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('下準備１：異物混入チェック',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('紙や異物が混じってないかポケットを確認してください',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0724.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step2   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('下準備２：泥汚れの下洗い',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('泥や排泄物で汚れていたら、風呂場で軽く下洗いしてください',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0721.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step3   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('下準備３：洗濯ネットで保護',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('黒いもの。長いもの。引っかかりそうなもの。剥がれそうなもの。該当すれば洗濯ネットに入れて保護。',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0234.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $step4 = getStep4($event->getUserId());
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step4   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットの収納場所',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯ネットは「'.$step4.'」を探してください',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0725.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step5   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の収納場所',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤は「'.$step5.'」を探してください',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0214.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReplyStep14();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step6   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の種類',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('毎日の衣類・タオル類には「'.$step6.'」を使ってください',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0720.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReplyStep14();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step7   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯機の水量',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('全て洗濯機に入れたら、水量を知るために、洗濯機のスタートボタンを押してください',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0710.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step8   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量と水量の関係性',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯物の量に応じて水量が変わります、洗剤を水量に応じて入れます',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0713.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step9   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量について',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の量は「'.$step9.'」',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0215.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      // 写真カスタムない時
      if(getFilenamePhoto10(getRoomIdOfUser($event->getUserId())) === PDO::PARAM_NULL) {
        $step10 = getStep10($event->getUserId());
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step10   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の投入口',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤を入れる場所は「'.$step10.'」',null,null,null,null,null,true)];
        $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
        $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0218.jpg';
        $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
        $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
        $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
        $quickReplyButtons =  flexMessageQuickReply();
        $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
        $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
        replyFlexMessage($bot, $event->getReplyToken(), 'step10', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
        );
      } else {// 写真カスタムある時
        $step10 = getStep10($event->getUserId());
        $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step10   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
        $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の投入口',null,null,'xl',null,null,true,null,'bold')];
        $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤を入れる場所は「'.$step10.'」',null,null,null,null,null,true)];
        $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
        $roomId = getRoomIdOfUser($event->getUserId());
        $filename = getFilenamePhoto10($roomId);
        $heroImageUrl =  'https://res.cloudinary.com/kajibo/kajiboimage/step10photo/'.$roomId.'/'.$filename.'.jpg';
        $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
        $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
        $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
        $quickReplyButtons =  flexMessageQuickReply();
        $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
    }
    else if($event->getPostbackData() == 'step11'){
      $step11 = getStep11($event->getUserId());
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step11   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤について',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤は「'.$step11.'」',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0854.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReplyStep14();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step12   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤の投入口',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('柔軟剤を入れる場所は「'.$step12.'」',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0708.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step13   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯機スタート',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗濯機の蓋を閉めると洗濯が始まります。',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_0715.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
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
    else if($event->getPostbackData() == 'step14'){
      $headerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('step14   ★洗濯機で洗う（全14step）',null,null,'sm','center')];
      $bodyTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤の詰めかえ',null,null,'xl',null,null,true,null,'bold')];
      $footerTextComponents=[new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder('洗剤が終わりかけなら詰めかえてください。ストックは「'.$step14.'」にあります。',null,null,null,null,null,true)];
      $layout = new \LINE\LINEBot\Constant\Flex\ComponentLayout;
      $heroImageUrl = 'https://' . $_SERVER['HTTP_HOST'] .  '/img/IMG_1113.jpg';
      $heroImageSize = new \LINE\LINEBot\Constant\Flex\ComponentImageSize;
      $aspectRatio = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
      $aspectMode = new \LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
      $quickReplyButtons =  flexMessageQuickReply();
      $quickReply = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
      $headerPaddingTop = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $headerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $bodyPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingBottom = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingEnd = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      $footerPaddingStart = new \LINE\LINEBot\Constant\Flex\ComponentSpacing;
      replyFlexMessage($bot, $event->getReplyToken(), 'step14', $layout::VERTICAL, $headerTextComponents, $bodyTextComponents, $footerTextComponents, $heroImageUrl, $heroImageSize::FULL, $aspectRatio::R1TO1, $aspectMode::COVER, $quickReply, $headerPaddingTop::MD, $headerPaddingBottom::MD, $bodyPaddingEnd::LG, $bodyPaddingStart::LG, $footerPaddingBottom::XXL, $footerPaddingEnd::LG, $footerPaddingStart::LG
      );
    }

  
    continue;
  }



  // イベントがMessageEventクラスのインスタンスであれば
  else if ($event instanceof \LINE\LINEBot\Event\MessageEvent) {

    // ーーーーーーーーーーーーカスタマイズのメニュー関連（写真）ーーーーーーーーーーーーーーーーー

    // ImageMessageクラスのインスタンスであれば
    if($event instanceof \LINE\LINEBot\Event\MessageEvent\ImageMessage) {
      $roomId = getRoomIdOfUser($event->getUserId());
      if($roomId === PDO::PARAM_NULL) {
        replyTextMessage($bot, $event->getReplyToken(), '登録するにはルームに入ってください。');
      } else {
        // 写真登録履歴がなければ＝初回登録
        if(getFilenamePhoto10($roomId) === PDO::PARAM_NULL) {
          \Cloudinary::config(array(
            'cloud_name' => getenv('CLOUDINARY_NAME'),
            'api_key' => getenv('CLOUDINARY_KEY'),
            'api_secret' => getenv('CLOUDINARY_SECRET')
          ));

          $response = $bot->getMessageContent($event->getMessageId());
          $im = imagecreatefromstring($response->getRawBody());

          if ($im !== false) {
              $filename = uniqid();
              $directory_path = 'tmp';
              if(!file_exists($directory_path)) {
                if(mkdir($directory_path, 0777, true)) {
                    chmod($directory_path, 0777);
                }
              }
              imagejpeg($im, $directory_path. '/' . $filename . '.jpg', 75);
          }
          $path = dirname(__FILE__) . '/' . $directory_path. '/' . $filename . '.jpg';
          $filename_save = array('folder'=>'kajiboimage/step10photo/'.$roomId, 'public_id'=>$filename, 'format'=>'jpg','transformation'=>['quality'=>'30']);
          $result = \Cloudinary\Uploader::upload($path, $filename_save);
          // 写真登録したことルームメイトに通知する＋DBにファイル名保存
          endPhoto($bot, $event->getUserId(), $filename);
          replyConfirmTemplate($bot, $event->getReplyToken(), '写真を確認しますか？', '写真を確認しますか？',
              new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('写真を見る', 'cmd_showPhoto10'),
              new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('写真を変更', 'cmd_changePhoto10'));
        } else {
        // 写真登録履歴あれば、前の写真消す
          \Cloudinary::config(array(
            'cloud_name' => getenv('CLOUDINARY_NAME'),
            'api_key' => getenv('CLOUDINARY_KEY'),
            'api_secret' => getenv('CLOUDINARY_SECRET')
          ));
          // 不要になったファイルを消す
          $oldfilename = getFilenamePhoto10($roomId);
          $public_id = 'kajiboimage/step10photo/'.$roomId.'/'.$oldfilename;//先頭に/つけるとエラーだった
          $resultDelete = \Cloudinary\Uploader::destroy($public_id);

          // 以下写真のアップロード
          $response = $bot->getMessageContent($event->getMessageId());
          $im = imagecreatefromstring($response->getRawBody());
          if ($im !== false) {
              $filename = uniqid();
              $directory_path = 'tmp';
              if(!file_exists($directory_path)) {
                if(mkdir($directory_path, 0777, true)) {
                    chmod($directory_path, 0777);
                }
              }
              imagejpeg($im, $directory_path. '/' . $filename . '.jpg', 75);
          }
          $path = dirname(__FILE__) . '/' . $directory_path. '/' . $filename . '.jpg';
          $filename_save = array('folder'=>'kajiboimage/step10photo/'.$roomId, 'public_id'=>$filename, 'format'=>'jpg','transformation'=>['quality'=>'30']);
          $result = \Cloudinary\Uploader::upload($path, $filename_save);
          // 写真登録したことルームメイトに通知する＋DBにファイル名保存
          endPhoto($bot, $event->getUserId(), $filename);
          replyConfirmTemplate($bot, $event->getReplyToken(), '写真を確認しますか？', '写真を確認しますか？',
              new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('写真を見る', 'cmd_showPhoto10'),
              new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('写真を変更', 'cmd_changePhoto10'));

        }
      }
    }
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

      // ーーーーーーーーーーーーLIFF関連ーーーーーーーーーーーーーーーーー

      // LIFFで「家事マニュアルを見る」ボタン押した後の処理(cmd_kajiと同じ)
      else if($event->getText() == '家事マニュアルを見る'){
        replyQuickReplyButton($bot, $event->getReplyToken(), '洗濯マニュアルを個別stepで表示します。下のボタンを押してください。',
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

      // -----------------------登録・更新------------------------------------
      else if($event->getText() == '登録を維持します。'){
        replyTextMessage($bot, $event->getReplyToken(), '承知しました。');
      }
      else if($event->getText() == '退室しません。ルームを維持します。'){
        replyTextMessage($bot, $event->getReplyToken(), '承知しました。');
      }
      else if($event->getText() == '送信しません。'){
        replyTextMessage($bot, $event->getReplyToken(), '承知しました。洗濯おつかれさまでした🍺');
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


      // ーーーーーーーーーーーーカスタマイズのメニュー関連ーーーーーーーーーーーーーーーーー


      // -----------------------登録・更新------------------------------------
      else {
        $roomId = getRoomIdOfUser($event->getUserId());
        if($roomId === PDO::PARAM_NULL) {
          replyTextMessage($bot, $event->getReplyToken(), '家事マニュアルの登録には、先にルームを作る必要があります。');
        } else {
          if(getUserSituation($roomId) === 'set4'){
            if(checkStep4($roomId) === PDO::PARAM_NULL) {
              // データベースに保存されてなければ、新規登録
              registerStep4($bot, $event->getUserId(), $event->getText());
              // replyTextMessage($bot, $event->getReplyToken(), '登録しました。');//pushmessageで送信
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification4'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            } else {
              // データベースに保存されていれば、上書き更新
              updateStep4($bot, $event->getUserId(), $event->getText());
              // replyTextMessage($bot, $event->getReplyToken(), '更新しました。');//pushmessageで送信
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification4'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            }
          } else if(getUserSituation($roomId) === 'set5'){
            if(checkStep5($roomId) === PDO::PARAM_NULL) {
              registerStep5($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification5'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            } else {
              updateStep5($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification5'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            }
          } else if(getUserSituation($roomId) === 'set6'){
            if(checkStep6($roomId) === PDO::PARAM_NULL) {
              registerStep6($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification6'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            } else {
              updateStep6($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification6'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            }
          } else if(getUserSituation($roomId) === 'set9'){
            if(checkStep9($roomId) === PDO::PARAM_NULL) {
              registerStep9($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification9'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            } else {
              updateStep9($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification9'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            }
          } else if(getUserSituation($roomId) === 'set10'){
            if(checkStep10($roomId) === PDO::PARAM_NULL) {
              registerStep10($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification10'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            } else {
              updateStep10($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification10'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            }
          } else if(getUserSituation($roomId) === 'set11'){
            if(checkStep11($roomId) === PDO::PARAM_NULL) {
              registerStep11($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification11'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            } else {
              updateStep11($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification11'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            }
          } else if(getUserSituation($roomId) === 'set12'){
            if(checkStep12($roomId) === PDO::PARAM_NULL) {
              registerStep12($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification12'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            } else {
              updateStep12($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification12'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            }
          } else if(getUserSituation($roomId) === 'set14'){
            if(checkStep14($roomId) === PDO::PARAM_NULL) {
              registerStep14($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification14'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            } else {
              updateStep14($bot, $event->getUserId(), $event->getText());
              replyConfirmTemplate($bot, $event->getReplyToken(), '結果を確認しますか？', '結果を確認しますか？',
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('結果確認', 'cmd_modification14'),
                new LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のstepへ', 'cmd_modify'));
              setUserSituation($roomId, null);
            }
          } else {
            replyTextMessage($bot, $event->getReplyToken(), '登録したいステップ上の「登録する」ボタンを押してから再送してください。');
          }
        }
      }


  
      continue;
    }
  }
}
// ======================以下関数============================

// ーーーーーーーーーーーーカスタマイズのメニュー関連ーーーーーーーーーーーーーーーーー

// 写真登録完了の通知＋DBにファイル名保存の実行
function endPhoto($bot, $userId, $filename) {
  $roomId = getRoomIdOfUser($userId);
  // 毎回uniqueなファイル名でクラウディナリに写真保存。そのファイル名をDBに上書き保存。
  setFilenamePhoto10($roomId, $filename);
  //　以下はpushMessageのためのルームメイト情報抽出およびpushMessageの実行 
  $dbh = dbConnection::getConnection();
  $sql = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array(getRoomIdOfUser($userId)));
  // 各ユーザーにメッセージを送信
  foreach ($sth->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('【ご報告】step10の写真を変えました。'));
  }
}
// ファイル名をデータベースに保存
function setFilenamePhoto10($roomId, $filename) {
  if(getFilenamePhoto10($roomId) === PDO::PARAM_NULL) {
    $dbh = dbConnection::getConnection();
    $sql = 'insert into ' . TABLE_NAME_PHOTOSTEP10S . ' (roomid, filename) values (?, ?)';
    $sth = $dbh->prepare($sql);
    $sth->execute(array($roomId, $filename));
  } else {
    $dbh = dbConnection::getConnection();
    $sql = 'update ' . TABLE_NAME_PHOTOSTEP10S . ' set filename = ? where ? = roomid';
    $sth = $dbh->prepare($sql);
    $sth->execute(array($filename, $roomId));
  }
}
// データベースからファイル名を取得
function getFilenamePhoto10($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select filename from ' . TABLE_NAME_PHOTOSTEP10S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['filename'];
  }
}

// -----------------------step4------------------------------------
// 登録状況をデータベースに保存
function setUserSituation($roomId, $status) {
  if(getUserSituation($roomId) === PDO::PARAM_NULL) {
    $dbh = dbConnection::getConnection();
    $sql = 'insert into ' . TABLE_NAME_USERSITUATIONS . ' (roomid, status) values (?, ?)';
    $sth = $dbh->prepare($sql);
    $sth->execute(array($roomId, $status));
  } else {
    $dbh = dbConnection::getConnection();
    $sql = 'update ' . TABLE_NAME_USERSITUATIONS . ' set status = ? where ? = roomid';
    $sth = $dbh->prepare($sql);
    $sth->execute(array($status, $roomId));
  }
}
// データベースから登録状況を取得
function getUserSituation($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select status from ' . TABLE_NAME_USERSITUATIONS . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['status'];
  }
}
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
    return $row['step4'];
  }
}
// step4の存在確認
function checkStep4($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select step4 from ' . TABLE_NAME_STEP4S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  // レコードが存在しなければ定型文
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    // DBの内容を返す
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  if (!($row = $sth->fetch())) {
    return '引き出しや戸棚の中';
  } else {
    return $row['step5'];
  }
}
// step5の存在確認
function checkStep5($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select step5 from ' . TABLE_NAME_STEP5S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  if (!($row = $sth->fetch())) {
    return 'ハイジア';
  } else {
    return $row['step6'];
  }
}
// step6の存在確認
function checkStep6($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select step6 from ' . TABLE_NAME_STEP6S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  if (!($row = $sth->fetch())) {
    return '背面か側面に載ってますので見てください';
  } else {
    return $row['step9'];
  }
}
// step9の存在確認
function checkStep9($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select step9 from ' . TABLE_NAME_STEP9S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  if (!($row = $sth->fetch())) {
    return '機種によって異なります。洗濯機の中かフチか洗濯機の上部かにあります。';
  } else {
    return $row['step10'];
  }
}
// step10の存在確認
function checkStep10($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select step10 from ' . TABLE_NAME_STEP10S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  if (!($row = $sth->fetch())) {
    return '必要であれば入れてください。';
  } else {
    return $row['step11'];
  }
}
// step11の存在確認
function checkStep11($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select step11 from ' . TABLE_NAME_STEP11S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step11'];
  }
}
function updateStep11($bot, $userId, $step11) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STEP11S . ' set step11 = ? where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($step11, $roomId));
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  if (!($row = $sth->fetch())) {
    return '洗剤とは異なる投入口が洗濯機にあります';
  } else {
    return $row['step12'];
  }
}
// step12の存在確認
function checkStep12($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select step12 from ' . TABLE_NAME_STEP12S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
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
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step12'];
  }
}

// -----------------------step14-----------------------------------
// step14を登録
function registerStep14($bot, $userId, $step14){
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'insert into '. TABLE_NAME_STEP14S .' (roomid, step14) values (?, ?) ';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId, $step14));
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step14「洗剤の詰めかえ」を登録しました'));
  }
}
// step14を表示
function getStep14($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step14 from ' . TABLE_NAME_STEP14S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return '床下収納';
  } else {
    return $row['step14'];
  }
}
// step14の存在確認
function checkStep14($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select step14 from ' . TABLE_NAME_STEP14S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step14'];
  }
}
// step14の情報を更新（DBの上書き）
function updateStep14($bot, $userId, $step14) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'update ' . TABLE_NAME_STEP14S . ' set step14 = ? where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($step14, $roomId));
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step14「洗剤の詰めかえ」を修正しました'));
  }
}
// step14の情報をデータベースから削除
function deleteStep14($bot, $userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'delete FROM ' . TABLE_NAME_STEP14S . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $flag = $sth->execute(array($roomId));
  $sqlUsers = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sthUsers = $dbh->prepare($sqlUsers);
  $sthUsers->execute(array($roomId));
  foreach ($sthUsers->fetchAll() as $row) {
    $bot->pushMessage($row['userid'], new \LINE\LINEBot\MessageBuilder\TextMessageBuilder('step14「洗剤の詰めかえ」の登録を削除しました'));
  }
}
// ユーザーIDからstep14の登録内容を取得
function getDetailOfStep14($userId) {
  $roomId = getRoomIdOfUser($userId);
  $dbh = dbConnection::getConnection();
  $sql = 'select step14 from ' . TABLE_NAME_STEP14S . ' where ? = roomid';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['step14'];
  }
}





// ーーーーーーーーーーーーリッチメニュー関連ーーーーーーーーーーーーーーーーー

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
// 
function destroyAllRoom($roomId) {
  $dbh = dbConnection::getConnection();
  $sql4 = 'delete FROM ' . TABLE_NAME_STEP4S . ' where roomid = ?';
  $sth4 = $dbh->prepare($sql4);
  $sth4->execute(array($roomId));
  $sql5 = 'delete FROM ' . TABLE_NAME_STEP5S . ' where roomid = ?';
  $sth5 = $dbh->prepare($sql5);
  $sth5->execute(array($roomId));
  $sql6 = 'delete FROM ' . TABLE_NAME_STEP6S . ' where roomid = ?';
  $sth6 = $dbh->prepare($sql6);
  $sth6->execute(array($roomId));
  $sql9 = 'delete FROM ' . TABLE_NAME_STEP9S . ' where roomid = ?';
  $sth9 = $dbh->prepare($sql9);
  $sth9->execute(array($roomId));
  $sql10 = 'delete FROM ' . TABLE_NAME_STEP10S . ' where roomid = ?';
  $sth10 = $dbh->prepare($sql10);
  $sth10->execute(array($roomId));
  $sql11 = 'delete FROM ' . TABLE_NAME_STEP11S . ' where roomid = ?';
  $sth11 = $dbh->prepare($sql11);
  $sth11->execute(array($roomId));
  $sql12 = 'delete FROM ' . TABLE_NAME_STEP12S . ' where roomid = ?';
  $sth12 = $dbh->prepare($sql12);
  $sth12->execute(array($roomId));
  $sql14 = 'delete FROM ' . TABLE_NAME_STEP14S . ' where roomid = ?';
  $sth14 = $dbh->prepare($sql14);
  $sth14->execute(array($roomId));
  $sqlUserSituation = 'delete FROM ' . TABLE_NAME_USERSITUATIONS . ' where roomid = ?';
  $sthUserSituation = $dbh->prepare($sqlUserSituation);
  $sthUserSituation->execute(array($roomId));
  $sqlPhotoStep10 = 'delete FROM ' . TABLE_NAME_PHOTOSTEP10S . ' where roomid = ?';
  $sthPhotoStep10 = $dbh->prepare($sqlPhotoStep10);
  $sthPhotoStep10->execute(array($roomId));
}
// 
function getRoomMate($roomId) {
  $dbh = dbConnection::getConnection();
  $sql = 'select pgp_sym_decrypt(userid, \'' . getenv('DB_ENCRYPT_PASS') . '\') as userid from ' . TABLE_NAME_ROOMS . ' where roomid = ?';
  $sth = $dbh->prepare($sql);
  $sth->execute(array($roomId));
  if (!($row = $sth->fetch())) {
    return PDO::PARAM_NULL;
  } else {
    return $row['userid'];
  }
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
}

// ーーーーーーーーーーーー家事マニュアルとその選択肢ーーーーーーーーーーーーーーーーー
// ーーーーーーーーーーーーBotからの返信関連（QuickReplyとflexMessage）ーーーーーーーーーーーーーーーーー

// フレックスメッセージに添付するクイックリプライボタン
function flexMessageQuickReplyStep14(){
  $flexMessageQuickReplyStep14 = array( 
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('14)洗剤の詰めかえ', 'step14')),
    new \LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder(new \LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder('他のステップ', 'cmd_kaji'))
  );
  return $flexMessageQuickReplyStep14;
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
  $quickReplyButtons = array();
  foreach($actions as $value){
    array_push($quickReplyButtons,$value);
  }
  $qr = new \LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder($quickReplyButtons);
  $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($text1, $qr);
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

// フレックスメッセージ（写真カスタマイズ時のみ）
function replyFlexMessagePhoto($bot, $replyToken, $altText, $layout, $headerTextComponents=[], $bodyTextComponents=[], $footerTextComponents=[], $heroImageUrl, $heroImageSize, $aspectRatio, $aspectMode, $headerPaddingTop, $headerPaddingBottom, $bodyPaddingEnd, $bodyPaddingStart, $footerPaddingBottom, $footerPaddingEnd, $footerPaddingStart) {
  $headerBoxComponentBuilder = array();
  foreach($headerTextComponents as $value){
    array_push($headerBoxComponentBuilder,$value);
  }
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
  $footerComponentBuilder->setPaddingBottom($footerPaddingBottom);
  $footerComponentBuilder->setPaddingEnd($footerPaddingEnd);
  $footerComponentBuilder->setPaddingStart($footerPaddingStart);

  $heroComponentBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder($heroImageUrl, null, null, null, null, $heroImageSize, $aspectRatio, $aspectMode);

  $containerBuilder = new \LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder();
  $containerBuilder->setHeader($headerComponentBuilder);
  $containerBuilder->setHero($heroComponentBuilder);
  $containerBuilder->setBody($bodyComponentBuilder);
  $containerBuilder->setFooter($footerComponentBuilder);

  $messageBuilder = new \LINE\LINEBot\MessageBuilder\FlexMessageBuilder($altText, $containerBuilder);
  $response = $bot->replyMessage($replyToken, $messageBuilder);
  if (!$response->isSucceeded()) {
    error_log('Failed! '. $response->getHTTPStatus . ' ' . $response->getRawBody());
  }
}

// フレックスメッセージ（登録更新時）
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
