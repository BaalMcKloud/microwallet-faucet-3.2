<?php

// Copyright (c) 2013 Land of Bitcoin http://www.landofbitcoin.com/
// Feel free to modify anything or remove banners as long as you keep the footer line unchanged.
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND.
// Donations greatly appreciated: 1MiCRoXT5gFtGZLSmW6efAx968WAKvD5xz

// INSTALLATION:
// 1. Set the config values below.
// 2. Upload the index.php and the Microwallet.php files.
// 3. Done, ready to use. You can test it with a Bitcoin address, for example with or donate address: 1MiCRoXT5gFtGZLSmW6efAx968WAKvD5xz

/*** CONFIG ***/
$faucetName = 'Microfaucet';
$faucetSlogan = 'Awesome Bitcoin faucet!';
$faucetBackgroundColor = 'white';
$faucetTextColor = 'black';
$faucetDonateBitcoinAddress = '';

// How often can the users claim rewards in minutes, 180 = every 3 hours
$intervalInMinutes = 180;

// List of rewards in satoshi, 1 satoshi = 0.00000001 BTC.
$rewards = array(
    500,
    400,
    300,
    200,
    100,
);

// Display the faucet balance or hide it? true or false
$displayFaucetBalance = false;

// Enter MySQL infos
$mysqlHost = 'localhost';
$mysqlUsername = '';
$mysqlPassword = '';
$mysqlDatabase = 'microfaucet';

// Get your Microwallet API key from here: https://www.microwallet.org/api
$microwalletApiKey = '';

// CHOOSE A CAPTCHA, you need to fill out recaptha API keys for recaptcha OR Solvemedia API keys for Solvemedia captcha, no need to fill out both.
// If you choose recaptcha: get your reCAPTCHA API keys from here: https://www.google.com/recaptcha/
$recaptchaPublicKey = '';
$recaptchaPrivateKey = '';

// If you choose solvemedia: get your Solve Media API keys here: http://solvemedia.com/publishers/
$solvemediaChallengeKey = '';
$solvemediaVerificationKey = '';

// The HTML is at the end of this file, scroll down, easy to customize.








/************************/
/*** APPLICATION CODE ***/
/************************/
error_reporting(0);

if (empty($microwalletApiKey) || ((empty($recaptchaPublicKey) || empty($recaptchaPrivateKey)) && (empty($solvemediaChallengeKey) || empty($solvemediaVerificationKey)))) {
    echo 'Missing API keys, check the settings in the index.php.';
    exit;
}
$db = mysqli_connect($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
if (!$db) {
    echo 'Can\'t connect to MySQL, check the settings in the index.php. Error: ' . mysqli_connect_error();
    exit;
}
mysqli_set_charset($db, 'latin1');

$result = mysqli_query($db, "select * from microfaucet_settings where name = 'faucet_balance'");
if (!$result && mysqli_errno($db) === 1146) {
    $query = "DROP TABLE IF EXISTS `microfaucet_settings`";
    mysqli_query($db, $query);
    $query = "CREATE  TABLE IF NOT EXISTS `microfaucet_settings` (`id` INT NOT NULL AUTO_INCREMENT, `name` VARCHAR(45) NOT NULL,  `value` VARCHAR(45) NOT NULL,  PRIMARY KEY (`id`)) ENGINE = MyISAM DEFAULT CHARACTER SET = latin1 COLLATE = latin1_swedish_ci";
    mysqli_query($db, $query);
    $query = "INSERT INTO `microfaucet_settings` (`name`, `value`) VALUES ('faucet_balance', 'N/A|1')";
    mysqli_query($db, $query);
    $query = "DROP TABLE IF EXISTS `microfaucet_users`";
    mysqli_query($db, $query);
    $query = "CREATE  TABLE IF NOT EXISTS `microfaucet_users` (`id` INT NOT NULL AUTO_INCREMENT, `username` VARCHAR(45) NOT NULL, `ip` INT NOT NULL, `claimed_at` INT NOT NULL, PRIMARY KEY (`id`), UNIQUE INDEX `username_UNIQUE` (`username` ASC)) ENGINE = MyISAM DEFAULT CHARACTER SET = latin1 COLLATE = latin1_swedish_ci";
    mysqli_query($db, $query);
    header('Location: index.php');
    exit;
}
$time = time();

if ($displayFaucetBalance) {
    $faucetBalance = mysqli_fetch_assoc($result);
    list($faucetBalance, $faucetBalanceFetchedAt) = explode('|', $faucetBalance['value']);
    if ($faucetBalanceFetchedAt + 10 * 60 < $time) {
        $faucetBalance = @file_get_contents('https://www.microwallet.org/api/v1/balance?api_key=' . rawurlencode($microwalletApiKey));
        $faucetBalance = json_decode($faucetBalance);
        if ($faucetBalance && isset($faucetBalance->balance_bitcoin)) {
            $faucetBalance = $faucetBalance->balance_bitcoin . ' BTC';
        } else {
            $faucetBalance = 'N/A';
        }
        $escapedValue = mysqli_real_escape_string($db, $faucetBalance . '|' . $time);
        $query = "update microfaucet_settings set value = '$escapedValue' where name = 'faucet_balance'";
        mysqli_query($db, $query);
    }
}

$captchaSolved = false;
$recaptcha = false;
if (!empty($recaptchaPublicKey) && !empty($recaptchaPrivateKey)) {
    $recaptcha = true;
}
$result = '';
$resultHtml = '';
$intervalH = floor($intervalInMinutes / 60);
if ($intervalH) {
    $interval = $intervalH . ' hours';
}
$intervalM = $intervalInMinutes - $intervalH * 60;
if ($intervalM) {
    $interval .= ($intervalH ? ' and ' : '') . $intervalM . ' mins';
}
$ip = $_SERVER['REMOTE_ADDR'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($recaptcha) {
        $captchaChallange = $_POST['recaptcha_challenge_field'];
        $captchaResponse = $_POST['recaptcha_response_field'];
    } else {
        $captchaChallange = $_POST['adcopy_challenge'];
        $captchaResponse = $_POST['adcopy_response'];
    }
    if (!empty($_POST['username']) && !empty($captchaChallange) && !empty($captchaResponse)) {
        if (!preg_match('/[^A-Za-z0-9\.\+\-\_\@]/', $_POST['username'])) {
            $escapedUsername = mysqli_real_escape_string($db, $_POST['username']);
            $escapedIp = mysqli_real_escape_string($db, ip2long($ip));
            $result = mysqli_query($db, "select * from microfaucet_users where username = '$escapedUsername' or ip = '$escapedIp' order by claimed_at desc");
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                if ($row === null || $row['claimed_at'] <= time() - ($intervalInMinutes * 60)) {
                    if ($recaptcha) {
                        $response = @file('https://www.google.com/recaptcha/api/verify?privatekey=' . $recaptchaPrivateKey . '&challenge=' . rawurlencode($captchaChallange). '&response=' . rawurlencode($captchaResponse) . '&remoteip=' . $ip);
                    } else {
                        $response = @file('http://verify.solvemedia.com/papi/verify?privatekey=' . $solvemediaVerificationKey . '&challenge=' . rawurlencode($captchaChallange) . '&response=' . rawurlencode($captchaResponse) . '&remoteip=' . $ip);
                    }
                    if (isset($response[0]) && trim($response[0]) === 'true') {
                        $captchaSolved = true;
                        require_once 'Microwallet.php';
                        $microwallet = new Microwallet($microwalletApiKey);
                        $amount = $rewards[mt_rand(0, count($rewards) - 1)];
                        $result = $microwallet->send($_POST['username'], $amount);
                        $resultHtml = $result['html'];
                        if ($result['success']) {
                            $escapedClaimedAt = mysqli_real_escape_string($db, time());
                            $result = mysqli_query($db, "insert into microfaucet_users (username, ip, claimed_at) values ('$escapedUsername', '$escapedIp', '$escapedClaimedAt')");
                            if (!$result && mysqli_errno($db) === 1062) {
                                mysqli_query($db, "update microfaucet_users set ip = '$escapedIp', claimed_at = '$escapedClaimedAt' where username = '$escapedUsername'");
                            }
                        }
                    } else {
                        $resultHtml = '<div class="alert alert-danger">Invalid captcha, try again!</div>';
                    }
                } else {
                    $waitingTime = ceil(($row['claimed_at'] - (time() - ($intervalInMinutes * 60))) / 60);
                    $resultHtml = '<div class="alert alert-danger">You have to wait ' . $waitingTime . ' minutes before claiming again!</div>';
                }
            } else {
                $resultHtml = '<div class="alert alert-danger">An error occured.</div>';
            }
        } else {
            $resultHtml = '<div class="alert alert-danger">Invalid address or username!</div>';
        }
    } else {
        $resultHtml = '<div class="alert alert-danger">Missing captcha, address or username, try again!</div>';
    }
}
/*******************************/
/*** END OF APPLICATION CODE ***/
/*******************************/








/************/
/*** HTML ***/
/************/
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo htmlspecialchars($faucetName); ?></title>
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.2/css/bootstrap.min.css">
    <style>
        body {
            background: <?php echo $faucetBackgroundColor; ?>;
            color: <?php echo $faucetTextColor; ?>;
        }

        .alert {
            font-weight: bold;
            text-align: center;
        }
        
        .faucet {
            margin: 20px 0;
        }
        
        h5 {
            font-weight: bold;
        }

        /* Solvemedia captcha fix. */
        #adcopy-outer {
            -moz-box-sizing: content-box;
            -webkit-box-sizing: content-box;
            box-sizing: content-box;
        }
    </style>
</head>
<body>

<div class="container">
    <h1 class="text-center"><?php echo htmlspecialchars($faucetName); ?></h1>
    <h4 class="text-center text-muted"><?php echo htmlspecialchars($faucetSlogan); ?></h4>
    <hr />
    <div class="row faucet">
        <div class="col-sm-3">
            <h3 class="text-center">Rewards here</h3>
            <hr />
            <?php foreach ($rewards as $reward): ?>
                <h5 class="text-center"><?php echo htmlspecialchars($reward); ?> satoshi</h5>
            <?php endforeach; ?>
            <hr />
            <h5 class="text-center">Claim every <?php echo htmlspecialchars($interval); ?>!</h5>
            <hr />
            <center><div style="width: 200px; height: 200px; background: #DDD;">Adspace</div></center>
        </div>
        <div class="col-sm-6">
            <?php if (!empty($faucetDonateBitcoinAddress)): ?>
                <h4 class="text-center">Donate bitcoins to keep the faucet alive:<br /><?php echo htmlspecialchars($faucetDonateBitcoinAddress); ?></h4>
                <hr />
            <?php endif; ?>
            <center><div style="width: 468px; height: 60px; background: #DDD;">Adspace</div></center>
            <hr />
            <?php if ($displayFaucetBalance): ?>
                <h4 class="text-center">Faucet balance: <?php echo htmlspecialchars($faucetBalance); ?></h4>
                <hr />
            <?php endif; ?>
            <?php echo $resultHtml; ?>
            <?php if ($captchaSolved): ?>
                <div class="text-center"><a class="btn btn-primary btn-lg" href="">Reload</a></div>
            <?php else: ?>
                <h4 class="text-center">Enter your Bitcoin address, email or <a target="_blank" href="https://www.microwallet.org/">Microwallet.org</a> username</h4>
                <form action="" method="POST">
                    <div class="form-group">
                        <input class="form-control input-lg" type="text" name="username" id="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" placeholder="Bitcoin address, email or Microwallet.org username" />
                    </div>
                    <div class="form-group">
                        <?php if ($recaptcha): ?>
                            <center><script type="text/javascript" src="https://www.google.com/recaptcha/api/challenge?k=<?php echo htmlspecialchars($recaptchaPublicKey); ?>"></script></center>
                        <?php else: ?>
                            <center><script type="text/javascript" src="http://api.solvemedia.com/papi/challenge.script?k=<?php echo htmlspecialchars($solvemediaChallengeKey); ?>"></script></center>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <input class="form-control input-lg btn-success" type="submit" value="Claim reward!" />
                    </div>
                </form>
            <?php endif; ?>
            <hr />
            <center><div style="width: 468px; height: 60px; background: #DDD;">Adspace</div></center>
            <hr />
            <center><iframe src="http://ads.landofbitcoin.com/microfaucet-468/" scrolling="no" style="width: 468px; height: 60px; border: 0; padding:0; overflow: hidden;" allowtransparency="true"></iframe></center>
        </div>
        <div class="col-sm-3">
            <h5 class="text-center">My favorite links</h5>
            <p class="text-center"><a target="_blank" href="http://bitcoin.org/">Bitcoin.org</a></p>
            <p class="text-center"><a target="_blank" href="https://bitcointalk.org/">Bitcointalk</a></p>
            <p class="text-center"><a target="_blank" href="http://www.landofbitcoin.com/">Land of Bitcoin</a></p>
            <hr />
            <center><div style="width: 200px; height: 200px; background: #DDD;">Adspace</div></center>
            <hr />
            <center><iframe src="http://ads.landofbitcoin.com/microfaucet-200/" scrolling="no" style="width: 200px; height: 200px; border: 0; padding:0; overflow: hidden;" allowtransparency="true"></iframe></center>
        </div>
    </div>
    <p class="text-center">Powered by <a target="_blank" href="https://www.microwallet.org/">Microwallet</a>. Get <a target="_blank" href="http://www.landofbitcoin.com/">free bitcoins on Land of Bitcoin</a>!</p>
</div>

</body>
</html>
