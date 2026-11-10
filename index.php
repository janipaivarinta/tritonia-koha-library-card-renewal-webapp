<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_name('tritonia_renew');
session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Strict','path'=>'/']);
session_start();
require __DIR__.'/src/core.php';
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data:; base-uri \'none\'; form-action \'self\'');
$l=lang();
$error='';
$notice='';
$phase=$_SESSION['phase']??'login';
$c=cfg();
if($c['mode']!=='demo'&&!$secure)
{
    http_response_code(403);
    exit('HTTPS required');
}
if(isset($_SESSION['last_seen'])&&time()-$_SESSION['last_seen']>$c['session_ttl_seconds'])
{
    session_unset();
    $phase='login';
}
$_SESSION['last_seen']=time();
if($_SERVER['REQUEST_METHOD']==='POST')
{
    checkcsrf();
    $action=(string)($_POST['action']??'');
    try
    {
        if($action==='logout')
        {
            session_unset();
            session_regenerate_id(true);
            $phase='login';
        }
        elseif($action==='login'&&$phase==='login')
        {
            $card=trim((string)($_POST['card']??''));
            $pin=(string)($_POST['pin']??'');
            if(!preg_match('/^[0-9]{10}$/D',$card)||!preg_match('/^[0-9]{5}$/D',$pin)||!throttle('login-ip-'.($_SERVER['REMOTE_ADDR']??''),20,900)||!throttle('login-card-'.$card,6,900))throw new RuntimeException('Invalid login');
            $p=authenticate($card,$pin);
            if(!$p||!filter_var($p['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid login');
            session_regenerate_id(true);
            $_SESSION['patron']=$p;
            $_SESSION['phase']='otp';
            $phase='otp';
            if(!otpCreate())throw new RuntimeException('Email failed');
        }
        elseif($action==='verify'&&$phase==='otp')
        {
            $code=(string)($_POST['code']??'');
            $_SESSION['otp_attempts']=($_SESSION['otp_attempts']??0)+1;
            if(($_SESSION['otp_attempts']>5)||time()>($_SESSION['otp_until']??0)||!preg_match('/^\d{6}$/',$code)||!hash_equals((string)($_SESSION['otp_hash']??''),hash_hmac('sha256',$code,csrf())))throw new RuntimeException('Invalid OTP');
            unset($_SESSION['otp_hash'],$_SESSION['demo_code']);
            session_regenerate_id(true);
            $_SESSION['phase']='renew';
            $phase='renew';
        }
        elseif($action==='resend'&&$phase==='otp')
        {
            if(time()-($_SESSION['otp_sent']??0)<60||!throttle('otp-'.($_SESSION['patron']['patron_id']??0),5,3600))throw new RuntimeException('Too soon');
            if(!otpCreate())throw new RuntimeException('Email failed');
        }
        elseif($action==='renew'&&$phase==='renew')
        {
            $years=filter_var($_POST['years']??null,FILTER_VALIDATE_INT);
            if(!in_array($years,[1,2,5],true))throw new RuntimeException('Invalid period');
            $p=$_SESSION['patron'];
            if($c['mode']!=='demo')
            {
                $tok=token();
                [$s,$fresh]=requestKoha('GET',sprintf($c['koha_patron_path'],$p['patron_id']),null,$tok);
                if($s!==200||!isset($fresh['expiry_date']))throw new RuntimeException('Lookup failed');
                $p['expiry_date']=$fresh['expiry_date'];
                // IMPORTANT: Integrators must add category, restrictions and debt checks here before enabling production renewals.
                if(empty($c['renewal_method']))throw new RuntimeException('Renewal not configured');
            }
            $new=proposedDate($p['expiry_date'],$years);
            if(!allowedDate($new))
            {
                $error=t('limit');
            }
            else
            {
                renewKoha((int)$p['patron_id'],$new);
                $_SESSION['new_expiry']=$new;
                $_SESSION['phase']='done';
                $phase='done';
            }
        }
        else throw new RuntimeException('Invalid state');
    }
    catch(Throwable $e)
    {
        error_log('Renewal error: '.$e->getMessage());
        $error=t('error');
        if($action==='login')
        {
            session_unset();
            $phase='login';
        }
        if($action==='verify'&&($_SESSION['otp_attempts']??0)>=5)
        {
            session_unset();
            $phase='login';
        }
    }
}
$p=$_SESSION['patron']??null;
function formStart(string $action): void
{
    echo '<form method="post"><input type="hidden" name="csrf" value="'.esc(csrf()).'"><input type="hidden" name="lang" value="'.esc(lang()).'"><input type="hidden" name="action" value="'.esc($action).'">';
}
?><!doctype html>
<html lang="<?=esc($l)?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<title><?=esc(t('title'))?> · Tritonia</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="shell">
<header>
<div class="brand">
<span class="brand-mark">
<img class="brand-logo" src="assets/img/logo.svg" alt="Tritonia">
</span>
<span>TRITONIA<small>Library services</small>
</span>
</div>
<div class="controls">
<label class="sr" for="language">Language</label>
<select id="language" aria-label="Language">
<option value="fi" <?=$l==='fi'?'selected':''?>>Suomi</option>
<option value="sv" <?=$l==='sv'?'selected':''?>>Svenska</option>
<option value="en" <?=$l==='en'?'selected':''?>>English</option>
</select>
<button type="button" id="theme" aria-label="Toggle color theme">☾</button>
</div>
</header>
<main>
<div class="eyebrow">TRITONIA · SELF SERVICE</div>
<h1><?=esc(t('title'))?></h1>
<p class="subtitle"><?=esc(t('subtitle'))?></p><?php if($c['mode']==='demo'):?><div class="demo"><?=esc(t('demo'))?></div><?php endif;?>
<section class="card" aria-live="polite"><?php if($error):?><div class="error" role="alert"><?=esc($error)?></div><?php endif;?>
<?php if($phase==='login'):?><h2><?=esc(t('login'))?></h2><?php formStart('login');?><label for="card"><?=esc(t('card'))?></label>
<input id="card" name="card" autocomplete="username" required minlength="10" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" autofocus>
<label for="pin"><?=esc(t('pin'))?></label>
<input id="pin" name="pin" type="password" autocomplete="current-password" required minlength="5" maxlength="5" pattern="[0-9]{5}" inputmode="numeric">
<small><a href="<?=esc(t('pin-address'))?>" target="_blank" rel="noopener noreferrer"><?=esc(t('forget-pin'))?></a></small>
<button class="primary" type="submit"><?=esc(t('continue'))?> <span aria-hidden="true">→</span>
</button>
</form><?php if($c['mode']==='demo'):?><p class="hint">Demo: 1234567890 / 12345</p><?php endif;?>
<?php elseif($phase==='otp'):?><div class="step-icon">✉</div>
<h2><?=esc(t('otp'))?></h2>
<p><?=esc(t('otpinfo'))?></p>
<p class="hint"><?=esc(preg_replace('/(^.).*(@.*$)/','$1••••$2',$p['email']))?></p><?php if($c['mode']==='demo'):?><div class="demo">Demo OTP: <?=esc($_SESSION['demo_code']??'')?></div><?php endif;?><?php formStart('verify');?><label for="code"><?=esc(t('code'))?></label>
<input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
<button class="primary"><?=esc(t('verify'))?></button>
</form><?php formStart('resend');?><button class="link-button" type="submit"><?=esc(t('resend'))?></button>
</form>
<?php elseif($phase==='renew'):?><h2><?=esc(t('renew'))?></h2>
<div class="datebox">
<span><?=esc(t('current'))?></span>
<strong><?=esc(date('d.m.Y',strtotime($p['expiry_date'])))?></strong>
</div>
<h3><?=esc(t('choose'))?></h3><?php formStart('renew');?><div class="options"><?php foreach([1,2,5] as $y):?><label class="option">
<input type="radio" name="years" value="<?=$y?>" <?=$y===1?'checked':''?>>
<span>
<strong><?=$y?></strong><?=esc(t('year'.$y))?></span>
</label><?php endforeach;?></div>
<div class="datebox next">
<span><?=esc(t('new'))?></span>
<strong id="new-date" data-expiry="<?=esc($p['expiry_date'])?>" data-max="<?=esc((new DateTimeImmutable('today',new DateTimeZone('Europe/Helsinki')))->add(new DateInterval('P'.$c['max_expiry_years_from_today'].'Y'))->format('Y-m-d'))?>">
</strong>
</div>
<button class="primary"><?=esc(t('confirm'))?></button>
</form>
<?php else:?><div class="step-icon success">✓</div>
<h2><?=esc(t('success'))?></h2>
<div class="datebox next">
<span><?=esc(t('done'))?></span>
<strong><?=esc(date('d.m.Y',strtotime($_SESSION['new_expiry']??'now')))?></strong>
</div><?php endif;?>
<?php if($phase!=='login'):?><?php formStart('logout');?><button class="link-button" type="submit"><?=esc(t('logout'))?></button>
</form><?php endif;?></section>
</main>
<footer><?=esc(t('footer'))?></footer>
</div>
<script src="assets/app.js" defer>
</script>
</body>
</html>
