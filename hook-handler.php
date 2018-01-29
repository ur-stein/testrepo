<?php
/*
 * Endpoint for Github Webhook URLs
 * Initial version adopted from: https://gist.github.com/gka/4627519
 * Initial author: Gregor Aisch / gka
 * 
 * Modified and upgraded by David Stein
 * (better config and error handling, signature verification, ...)
 *
 */
function run() {
    // read config.json
    $config_filename = '/home/std19050/.githook/githook.config.json';
    if (!file_exists($config_filename)) {
        throw new Exception('Can\'t find config.');
    }
    $config = json_decode(file_get_contents($config_filename), true);
    $postBody = filter_input(INPUT_POST, 'payload');
    $payload = json_decode($postBody);
    if (isset($config['email'])) {
        $headers = 'From: '.$config['email']['from']."\r\n";
        if (isset($config['email']['cc'])) { 
            $headers .= 'CC: ' .$config['email']['cc']. "\r\n";
        }
        if (isset($config['email']['bcc'])) { 
            $headers .= 'BCC: ' .$config['email']['bcc']. "\r\n";
        }
        $headers .= 'MIME-Version: 1.0'."\r\n";
        $headers .= 'Content-Type: text/html; charset=ISO-8859-1'."\r\n";
    }
    // check if the request comes from github server
    
    $signature = filter_input(INPUT_SERVER, 'HTTP_X_HUB_SIGNATURE');
    if (empty($signature)) {
            throw new \Exception('HTTP header \'X-Hub-Signature\' is missing. Please don\'t use this script with unsigned hooks.');
            die();
    } elseif (!extension_loaded('hash')) {
            throw new \Exception('Missing \'hash\' extension to check the secret code validity.');
            die();
    }
    list($hashAlgorithm, $hashString) = explode('=', $signature, 2) + array('', '');
    if (!in_array($hashAlgorithm, hash_algos(), true)) {
            throw new \Exception('Hash algorithm "'.$hashAlgorithm.'" is not supported.');
            die();
    }
    $rawPost = file_get_contents('php://input');

    //hash check was successful after here - this does look like a valid hook call by github.
    foreach ($config['endpoints'] as $endpoint) {
        // check if the push came from the right repository and branch
        if ($payload->repository->url == 'https://github.com/' . $endpoint['repo']
            && $payload->ref == 'refs/heads/' . $endpoint['branch']) {
            
            if (!isset($endpoint['secret']) || empty($endpoint['secret'])) {
                throw new \Exception('Config error: could not find hash secret.');
                die();
            }

            //validate hash for this call
            if (!hash_equals($hashString, hash_hmac($hashAlgorithm, $rawPost, $endpoint['secret']))) {
                throw new \Exception('Hook secret does not match.');
                die();
            }
            
            // execute update script, and record its output
            ob_start();
            require($endpoint['run']);
            $output = ob_get_clean();

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
                mail($payload->pusher->email, $endpoint['action'], $body, $headers);
            }
            exit('Thanks.');
        }
    }
    throw new \Exception('Found no configuration for this hook call! Repo: "'.$payload->repository->url.'" branch: "'.$payload->ref.'"');
}

if (isset($_POST['payload'])) {
    run();
} else {
    die('Missing payload. Do not call this directly.'.PHP_EOL);
}
