<?php
declare(strict_types=1);
namespace InaudisVote\Services;

use PHPMailer\PHPMailer\PHPMailer;

final class InvitationMailService
{
    public static function send(array $election, array $invitation): void
    {
        $autoload=dirname(__DIR__,2).'/vendor/autoload.php';
        if (!is_file($autoload)) throw new \RuntimeException('PHPMailer no está instalado. Ejecuta composer install en el servidor.');
        require_once $autoload;
        $host=trim((string)getenv('MAIL_HOST'));
        $user=trim((string)getenv('MAIL_USER'));
        $password=(string)getenv('MAIL_PASSWORD');
        $from=trim((string)(getenv('MAIL_FROM')?:$user));
        $base=rtrim((string)getenv('APP_URL'),'/');
        if (!$host || !$user || !$password || !filter_var($from,FILTER_VALIDATE_EMAIL) || !filter_var($base,FILTER_VALIDATE_URL) || !str_starts_with($base,'https://')) {
            throw new \RuntimeException('Configura SMTP y APP_URL con HTTPS en el .env privado antes de enviar credenciales.');
        }
        $logoPath=OrganizationService::logoPath((int)($election['organization_id']??0));
        $logoHtml='';
        $link=$base.'/vote.php?token='.rawurlencode($invitation['token']);
        $h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $title=(string)$election['title'];
        $name=(string)$invitation['name'];
        $otp=(string)$invitation['otp'];
        $minutes=(int)ceil((int)$invitation['expires_in_seconds']/60);
        $mail=new PHPMailer(true);
        if($logoPath!==null){
            $absoluteLogo=dirname(__DIR__,2).'/public'.$logoPath;
            if(is_file($absoluteLogo)){
                $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($absoluteLogo)?:'application/octet-stream';
                $mail->addEmbeddedImage($absoluteLogo,'organization-logo',basename($absoluteLogo),'base64',$mime);
                $logoHtml='<img src="cid:organization-logo" alt="Logo de la organización" width="120" style="display:block;max-width:120px;max-height:70px;object-fit:contain;margin:0 0 14px auto;background:white;padding:5px">';
            }
        }
        $mail->isSMTP();
        $mail->Host=$host;
        $mail->Port=(int)(getenv('MAIL_PORT')?:587);
        $mail->SMTPAuth=true;
        $mail->Username=$user;
        $mail->Password=$password;
        $mail->SMTPSecure=strtolower((string)(getenv('MAIL_ENCRYPTION')?:'tls'))==='ssl'?PHPMailer::ENCRYPTION_SMTPS:PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet='UTF-8';
        $mail->setFrom($from,(string)(getenv('MAIL_FROM_NAME')?:'QUORUM by INAUDIS'));
        $mail->addAddress((string)$invitation['email'],$name);
        $mail->isHTML(true);
        $mail->Subject='Credenciales de votación | '.$title;
        $mail->Body='<!doctype html><html lang="es"><body style="margin:0;background:#f2f6f9;font-family:Arial,sans-serif;color:#132a44"><div style="max-width:600px;margin:24px auto;background:#fff;border:1px solid #dce5eb"><div style="background:#0f2a44;padding:25px 32px;color:white">'.$logoHtml.'<div style="font-size:27px;font-weight:bold;letter-spacing:2px">QUORUM</div><div style="font-size:11px;color:#c4d7e4">by INAUDIS · Votación electrónica</div></div><div style="height:4px;background:#ff7a00"></div><div style="padding:32px"><p style="font-size:12px;color:#23758c">INVITACIÓN A VOTACIÓN</p><h1 style="font-size:24px">Tus credenciales de acceso</h1><p>Estimado/a '.$h($name).':</p><p>Has sido invitado a participar en el proceso <strong>'.$h($title).'</strong>.</p><div style="background:#f2f6f9;padding:18px;margin:22px 0;text-align:center"><div style="font-size:12px">CÓDIGO DE VERIFICACIÓN</div><div style="font-size:32px;font-weight:bold;letter-spacing:6px">'.$h($otp).'</div><div style="font-size:12px">Válido por '.$minutes.' minutos desde su generación</div></div><p style="text-align:center"><a href="'.$h($link).'" style="display:inline-block;background:#ff7a00;color:#fff;text-decoration:none;padding:15px 25px;font-weight:bold">Acceder a la votación</a></p><p style="font-size:12px;overflow-wrap:anywhere">Si el botón no funciona, copia este enlace: <a href="'.$h($link).'">'.$h($link).'</a></p><p style="font-size:12px;color:#526879">El enlace y el código son personales. No los compartas. Si el código vence, solicita una nueva invitación al organizador.</p></div><div style="background:#f2f6f9;padding:18px;text-align:center;font-size:12px;color:#526879">QUORUM by INAUDIS · Plataforma de votación electrónica</div></div></body></html>';
        $mail->AltBody="QUORUM by INAUDIS\nVotación: ".$title."\nParticipante: ".$name."\nEnlace: ".$link."\nCódigo OTP: ".$otp."\nVigencia: ".$minutes." minutos. No compartas tus credenciales.";
        try {$mail->send();}
        catch (\Throwable $e) {error_log('QUORUM SMTP: '.$e->getMessage());throw new \RuntimeException('No fue posible entregar el correo al servidor SMTP. Revisa la configuración y vuelve a intentar.');}
    }
}
