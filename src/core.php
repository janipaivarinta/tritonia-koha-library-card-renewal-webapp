<?php
declare(strict_types=1);
function cfg(): array
{
    static $c;
    return $c ??= require __DIR__.'/config.php';
}
function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}
function lang(): string
{
    $v=$_GET['lang']??$_POST['lang']??$_SESSION['lang']??'fi';
    $v=in_array($v,['fi','sv','en'],true)?$v:'fi';
    $_SESSION['lang']=$v;
    return $v;
}
function t(string $k): string
{
    static $d = [
    'fi'=>['title'=>'Kirjastokortin uusiminen','subtitle'=>'Jatka kirjastokorttisi voimassaoloa turvallisesti.','login'=>'Kirjaudu sisään','card'=>'Kirjastokortin numero','pin'=>'PIN-koodi', 'pin-address'=>'https://www.tritonia.fi/fi/unohtuiko-pin-koodi/','forget-pin'=>'Unohtuiko PIN-koodi?','continue'=>'Jatka','otp'=>'Sähköpostivahvistus','otpinfo'=>'Syötä sähköpostiisi lähetetty kuusinumeroinen vahvistuskoodi.','code'=>'Vahvistuskoodi','verify'=>'Vahvista','resend'=>'Lähetä uusi koodi','renew'=>'Uusi kirjastokortti','current'=>'Nykyinen voimassaoloaika','new'=>'Uusi voimassaoloaika','choose'=>'Valitse lisäaika','year1'=>'1 vuosi','year2'=>'2 vuotta','year5'=>'5 vuotta','confirm'=>'Vahvista uusiminen','success'=>'Kirjastokortti uusittu onnistuneesti','done'=>'Uusi voimassaoloaika','logout'=>'Kirjaudu ulos','error'=>'Toiminto epäonnistui. Tarkista tiedot tai yritä myöhemmin.','limit'=>'Uusiminen ei ole sallittua valitulla ajalla.','demo'=>'DEMO – tietoja ei muuteta Kohassa','mailfail'=>'Vahvistuskoodin lähetys epäonnistui.','back'=>'Takaisin','footer'=>'Tritonia · Kirjastopalvelut'],
    'sv'=>['title'=>'Förnya bibliotekskort','subtitle'=>'Förläng ditt bibliotekskorts giltighetstid på ett säkert sätt.','login'=>'Logga in','card'=>'Bibliotekskortsnummer','pin'=>'PIN-kod','pin-address'=>'https://www.tritonia.fi/sv/glomt-pinkoden/','forget-pin'=>'Glömt pinkoden?','continue'=>'Fortsätt','otp'=>'E-postverifiering','otpinfo'=>'Ange den sexsiffriga koden som skickats till din e-post.','code'=>'Verifieringskod','verify'=>'Verifiera','resend'=>'Skicka en ny kod','renew'=>'Förnya bibliotekskort','current'=>'Nuvarande giltighetstid','new'=>'Ny giltighetstid','choose'=>'Välj förlängning','year1'=>'1 år','year2'=>'2 år','year5'=>'5 år','confirm'=>'Bekräfta förnyelsen','success'=>'Bibliotekskortet har förnyats','done'=>'Ny giltighetstid','logout'=>'Logga ut','error'=>'Åtgärden misslyckades. Kontrollera uppgifterna eller försök senare.','limit'=>'Förnyelse är inte tillåten för den valda perioden.','demo'=>'DEMO – inga uppgifter ändras i Koha','mailfail'=>'Verifieringskoden kunde inte skickas.','back'=>'Tillbaka','footer'=>'Tritonia · Bibliotekstjänster'],
    'en'=>['title'=>'Renew library card','subtitle'=>'Securely extend the validity of your library card.','login'=>'Sign in','card'=>'Library card number','pin'=>'PIN code','pin-address'=>'https://www.tritonia.fi/en/forgot-your-pin-code/','forget-pin'=>'Fogot your PIN code?','continue'=>'Continue','otp'=>'Email verification','otpinfo'=>'Enter the six-digit verification code sent to your email.','code'=>'Verification code','verify'=>'Verify','resend'=>'Send a new code','renew'=>'Renew library card','current'=>'Current expiry date','new'=>'New expiry date','choose'=>'Choose extension','year1'=>'1 year','year2'=>'2 years','year5'=>'5 years','confirm'=>'Confirm renewal','success'=>'Library card renewed successfully','done'=>'New expiry date','logout'=>'Sign out','error'=>'Request failed. Check your details or try again later.','limit'=>'Renewal is not allowed for the selected period.','demo'=>'DEMO – no Koha data is changed','mailfail'=>'Could not send the verification code.','back'=>'Back','footer'=>'Tritonia · Library services'] ];
    return $d[lang()][$k]??$k;
}
function csrf(): string
{
    return $_SESSION['csrf']??=bin2hex(random_bytes(32));
}
function checkcsrf(): void
{
    if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))
    {
        http_response_code(403);
        exit('Invalid request');
    }
}
function throttle(string $key,int $max,int $window): bool
{
    $path=__DIR__.'/../storage/rate-'.hash('sha256',$key);
    $f=fopen($path,'c+');
    if(!$f)return false;
    flock($f,LOCK_EX);
    $data=json_decode(stream_get_contents($f),true);
    $now=time();
    $data=is_array($data)?array_values(array_filter($data,fn($x)=>is_int($x)&&$x>$now-$window)):[];
    $ok=count($data)<$max;
    if($ok)$data[]=$now;
    rewind($f);
    ftruncate($f,0);
    fwrite($f,json_encode($data));
    fflush($f);
    flock($f,LOCK_UN);
    fclose($f);
    return $ok;
}
function requestKoha(string $method,string $path,?array $payload=null,?string $token=null): array
{
    $c=cfg();
    $url=rtrim($c['koha_base_url'],'/').$path;
    if(!str_starts_with($url,'https://'))throw new RuntimeException('HTTPS required');
    $headers=['Accept: application/json'];
    if($payload!==null)$headers[]='Content-Type: application/json';
    if($token)$headers[]='Authorization: Bearer '.$token;
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true]);
    if($payload!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR));
    $raw=curl_exec($ch);
    $status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    if($raw===false)throw new RuntimeException('Koha request failed');
    curl_close($ch);
    return [$status,json_decode($raw,true)??[]];
}
function token(): string
{
    $c=cfg();
    $url=rtrim($c['koha_base_url'],'/').$c['koha_token_path'];
    if(!str_starts_with($url,'https://'))throw new RuntimeException('HTTPS required');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'client_credentials','client_id'=>$c['koha_client_id'],'client_secret'=>$c['koha_client_secret']]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true]);
    $raw=curl_exec($ch);
    $status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $body=json_decode((string)$raw,true);
    if($status!==200||empty($body['access_token']))throw new RuntimeException('OAuth failure');
    return $body['access_token'];
}
function authenticate(string $card,string $pin): ?array
{
    if(cfg()['mode']==='demo')return ($card==='1234567890'&&$pin==='12345')?['patron_id'=>42,'email'=>'demo@example.org','expiry_date'=>'2026-12-15','category_id'=>'ADULT']:null;
    $tok=token();
    [$s,$v]=requestKoha('POST',cfg()['koha_password_path'],['identifier'=>$card,'password'=>$pin],$tok);
    if($s!==201&&$s!==200)return null;
    $id=$v['patron_id']??$v['borrowernumber']??null;
    if(!is_numeric($id))throw new RuntimeException('Unexpected password validation response');
    [$s,$patron]=requestKoha('GET',sprintf(cfg()['koha_patron_path'],(int)$id),null,$tok);
    if($s!==200||!isset($patron['email'],$patron['expiry_date']))throw new RuntimeException('Patron data missing');
    return ['patron_id'=>(int)$id,'email'=>$patron['email'],'expiry_date'=>$patron['expiry_date'],'category_id'=>$patron['category_id']??''];
}
function proposedDate(string $date,int $years): string
{
    $now=new DateTimeImmutable('today',new DateTimeZone('Europe/Helsinki'));
    $current=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$current||$current->format('Y-m-d')!==$date)throw new RuntimeException('Bad expiry date');
    $base=$current>$now?$current:$now;
    return $base->add(new DateInterval('P'.$years.'Y'))->format('Y-m-d');
}
function allowedDate(string $date): bool
{
    return $date<= (new DateTimeImmutable('today',new DateTimeZone('Europe/Helsinki')))->add(new DateInterval('P'.cfg()['max_expiry_years_from_today'].'Y'))->format('Y-m-d');
}
function renewKoha(int $id,string $date): void
{
    if(cfg()['mode']==='demo')return;
    if(cfg()['renewal_method']!=='patch_expiry_date')throw new RuntimeException('Renewal integration not enabled');
    $tok=token();
    [$s]=requestKoha('PATCH',sprintf(cfg()['koha_patron_path'],$id),['expiry_date'=>$date],$tok);
    if(!in_array($s,[200,204],true))throw new RuntimeException('Koha renewal failed');
}
function sendCode(string $email,string $code): bool
{
    if(cfg()['mode']==='demo')return true;
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
    return mail($email,'Tritonia verification code','Your verification code: '.$code.'\nExpires in 15 minutes.','From: '.cfg()['sender_email'].'\r\nContent-Type: text/plain; charset=UTF-8');
}
function otpCreate(): bool
{
    $code=(string)random_int(100000,999999);
    $_SESSION['otp_hash']=hash_hmac('sha256',$code,csrf());
    $_SESSION['otp_until']=time()+cfg()['otp_ttl_seconds'];
    $_SESSION['otp_attempts']=0;
    $_SESSION['otp_sent']=time();
    if(cfg()['mode']==='demo')$_SESSION['demo_code']=$code;
    return sendCode($_SESSION['patron']['email'],$code);
}
