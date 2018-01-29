<?php
/* 
 * Endpoint for Github Webhook URLs
 *
 * see: https://help.github.com/articles/post-receive-hooks
 *
 */
// script errors will be send to this email:
$error_mail = 'david.stein@mailbox.org';
function run() {
    // read config.json
    $config_filename = '/home/std19050/githook.config.json';
    if (!file_exists($config_filename)) {
        throw new Exception('Can\'t find '.$config_filename);
    }
    $config = json_decode(file_get_contents($config_filename), true);
    $postBody = filter_input(INPUT_POST, 'payload');
    $payload = json_decode($postBody);
    if (isset($config['email'])) {
        $headers = 'From: '.$config['email']['from']."\r\n";
        $headers .= 'CC: ' . $payload->pusher->email . "\r\n";
        $headers .= 'MIME-Version: 1.0'."\r\n";
        $headers .= 'Content-Type: text/html; charset=ISO-8859-1'."\r\n";
    }
    // check if the request comes from github server
    
    $secret = $config['secret'];
    if ($secret === NULL) {
        throw new \Exception('Config error: could not find hash secret.');
        die();
    }

    $signature = filter_input(INPUT_SERVER, 'HTTP_X_HUB_SIGNATURE');
    if (empty($signature)) {
            throw new \Exception('HTTP header \'X-Hub-Signature\' is missing.');
            die();
    } elseif (!extension_loaded('hash')) {
            throw new \Exception('Missing \'hash\' extension to check the secret code validity.');
            die();
    }
    list($algo, $hash) = explode('=', $signature, 2) + array('', '');
    if (!in_array($algo, hash_algos(), TRUE)) {
            throw new \Exception('Hash algorithm "'.$algo.'" is not supported.');
            die();
    }
    $rawPost = file_get_contents('php://input');
    if (!hash_equals($hash, hash_hmac($algo, $rawPost, $secret))) {
            throw new \Exception('Hook secret does not match.');
            die();
    }
    throw new \Exception('hash check passed');
    foreach ($config['endpoints'] as $endpoint) {
        // check if the push came from the right repository and branch
        if ($payload->repository->url == 'https://github.com/' . $endpoint['repo']
            && $payload->ref == 'refs/heads/' . $endpoint['branch']) {
            // execute update script, and record its output
            //ob_start();
            //passthru($endpoint['run']);
            //$output = ob_end_contents();
            $output = "heyo";
            // prepare and send the notification email
            if (isset($config['email'])) {
                // send mail to someone, and the github user who pushed the commit
                $body = '<p>The Github user <a href="https://github.com/'
                . $payload->pusher->name .'">@' . $payload->pusher->name . '</a>'
                . ' has pushed to ' . $payload->repository->url
                . ' and consequently, ' . $endpoint['action']
                . '.</p>';
                $body .= '<p>Here\'s a brief list of what has been changed:</p>';
                $body .= '<ul>';
                foreach ($payload->commits as $commit) {
                    $body .= '<li>'.$commit->message.'<br />';
                    $body .= '<small style="color:#999">added: <b>'.count($commit->added)
                        .'</b> &nbsp; modified: <b>'.count($commit->modified)
                        .'</b> &nbsp; removed: <b>'.count($commit->removed)
                        .'</b> &nbsp; <a href="' . $commit->url
                        . '">read more</a></small></li>';
                }
                $body .= '</ul>';
                $body .= '<p>What follows is the output of the script:</p><pre>';
                $body .= $output. '</pre>';
                $body .= '<p>Cheers, <br/>Github Webhook Endpoint</p>';
                mail($config['email']['to'], $endpoint['action'], $body, $headers);
            }
            return true;
        }
    }
}
try {
    //if (isset($_POST['payload'])) {
        run();
    //}
} catch ( Exception $e ) {
    $msg = $e->getMessage();
    mail($error_mail, 'Githook endpoint error', $msg.PHP_EOL.$e);
}

?>