<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/phpmailer/src/Exception.php';
require '../vendor/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/src/SMTP.php';

function sendMail($code, $templateId, $targets) {

    if(count($targets) == 0) return;

    // メールテンプレートを取得
    $mailtemplate = ORM::for_table(TBLMAILTEMPLATE)->find_one($templateId);
    if(!isset($mailtemplate)) return;

    // 送信元メール情報を取得
    $codes = ORM::for_table(TBLCODE)
            ->where('code', $code)
            ->where_null('deleteDate')
            ->find_array();
    if(count($codes) == 0) return;

    $mail = new PHPMailer(true);// インスタンスを生成（true指定で例外を有効化）
    $mail->IsSMTP();            // SMTPの使用宣言
    $mail->Mailer = "smtp";
    $mail->SMTPKeepAlive = true;
    $mail->SMTPDebug = 0;       // 0:DEBUG_OFF,2:DEBUG_SERVER
    $mail->CharSet = 'UTF-8';   // 文字エンコードを指定
    // ローカルの証明書を確認する仕様の場合、設定必要
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $fromMailAddress = '';
    foreach($codes as $code) {
        if($code['codeDetail'] == 'portNo') {
            $mail->Port = $code['name'];                    // TCPポートを指定
        }
        else if($code['codeDetail'] == 'mailServer') {
            $mail->Host = gethostbyname($code['name']);     // SMTPサーバーを指定
        }
        else if($code['codeDetail'] == 'authUser') {
            $mail->Username = gethostbyname($code['name']); // SMTPサーバーのユーザ名
        }
        else if($code['codeDetail'] == 'authPass') {
            $mail->Password = $code['name'];                // SMTPサーバーのパスワード
        }
        else if($code['codeDetail'] == 'mailAddress') {
            $fromMailAddress = $code['name'];
        }
        else if($code['codeDetail'] == 'SMTPAuth') {
            $SMTPAuth = $code['name'];
            if($SMTPAuth == 'true') $SMTPAuth = true;
            else $SMTPAuth = false;
            $mail->SMTPAuth = $SMTPAuth;                    // SMTP authenticationを有効化
        }
        else if($code['codeDetail'] == 'SMTPSecure') {
            $SMTPSecure = $code['name'];
            if($SMTPSecure != 'tls' && $SMTPSecure != 'ssl') $SMTPSecure = false;
            $mail->SMTPSecure = $SMTPSecure;                // 暗号化を有効（tls or ssl）無効の場合はfalse
        }
    }
    // 送信元メールアドレス
    $mail->setFrom($fromMailAddress);

    foreach($targets as $target) {
        // 送信先メールアドレス
        $toMailAddress = $target['mailAddress'];
        if($toMailAddress == '') continue;
        // 複数指定の場合
        if(is_array($toMailAddress)) {
            foreach($toMailAddress as $address) {
                $mail->addAddress($address['mailAddress'], $address['userName']);
            }
        }
        else $mail->addAddress($target['mailAddress'], $target['userName']);
        // $mail->addCC($target['mailAddress'], $target['userName']);// CC宛先
        // $mail->addReplyTo($target['mailAddress'], $target['userName']);// 返信先（返信時の初期値）
        $mail->Sender = 'maruyama@wavesync.co.jp';// Return-path（送信失敗時、通知）

        // 件名
        $subject = $mailtemplate['subject'];
        for($i = 1 ; $i <= 5; $i++) {
            $key = 'convSubject_' . $i;
            if(isset($target[$key])) {
                $subject = str_replace('{' . $key . '}', $target[$key], $subject);
            }
        }
        $mail->Subject = $subject;

        // 本文
        $body = $mailtemplate['body'];
        $body = str_replace('{userName}', $target['userName'], $body);
        for($i = 1 ; $i <= 5; $i++) {
            $key = 'convBody_' . $i;
            if(isset($target[$key])) {
                $body = str_replace('{' . $key . '}', $target[$key], $body);
            }
        }
        $body = str_replace('(改行)', "\r\n", $body);
        $mail->Body = $body;

        if (!$mail->send()) {
            // echo 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
        }
    }
}

?>