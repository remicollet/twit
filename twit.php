#!/usr/bin/php
<?php
/**
 * Send Twitter message from command line
 *
 * PHP version 5
 *
 * Copyright © 2011 Remi Collet
 *
 * This file is part of twit.
 *
 * rpmphp is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * rpmphp is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with twit.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Main
 * @package   twit
 *
 * @author    Remi Collet <unknown@unknwown.com>
 * @copyright 2011 Remi Collet
 * @license   http://www.gnu.org/licenses/agpl-3.0-standalone.html AGPL License 3.0 or (at your option) any later version
 * @link      http://github.com/remicollet/twit/
 * @since     The begining of times.
*/
define('VERSION', '0.1');

function getConf($need=0) {
    $file = getenv('HOME').'/.config/phptwit/account';
    $conf = @simplexml_load_file($file);
    if (!$conf || !$need) {
        $conf = new SimpleXMLElement("<?xml version='1.0' standalone='yes'?><config></config>");
        $conf->addChild('Version', VERSION);
    }
    if ($need>=1 && !isset($conf->ConsumerKey)) {
        die("Application is not set. Run with 'register' option\n");
    }
    if ($need>=2 && !isset($conf->UserId)) {
        die("Access is not set. Run with 'access' option\n");
    }

    return $conf;
}

function saveConf(SimpleXMLElement $conf) {
    $dir = getenv('HOME').'/.config/phptwit';
    @mkdir($dir, 0700, true);
    $conf->saveXML($dir.'/account');
}

function getStatus() {
    $conf = getConf(2);

    try {
        $oauth = new OAuth($conf->ConsumerKey, $conf->ConsumerSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $oauth->setToken($conf->UserToken, $conf->UserSecret);


        $oauth->fetch('https://twitter.com/account/verify_credentials.json');
        $json = json_decode($oauth->getLastResponse());

        if (isset($json->name)) {
            echo "Name: ".$json->name."\n";
        } else {
            echo "User: ".$conf->UserName."\n";
        }
        if (isset($json->status->text)) {
            echo "Last: ".$json->status->text."\n";
            echo "Date: ".$json->status->created_at."\n";
        }
    } catch(OAuthException $E) {
        echo "Error: ".$E->getMessage()."\n";
    }
}

function setStatus($msg) {
    $conf = getConf(2);

    try {
        $oauth = new OAuth($conf->ConsumerKey, $conf->ConsumerSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_FORM);
        $oauth->setToken($conf->UserToken, $conf->UserSecret);

        $args = array(
            'status'      => $msg,
            'empty_param' => NULL
        );
        $oauth->fetch('https://twitter.com/statuses/update.json',$args, OAUTH_HTTP_METHOD_POST);
        $json = json_decode($oauth->getLastResponse());

        if (isset($json->id)) {
            echo "Tweet sent for ".$conf->UserName." !\n";
        }
    } catch(OAuthException $E) {
        echo "Error: ".$E->getMessage()."\n";
    }
}

function register($ckey, $csecret) {
    try {
        $oauth = new OAuth($ckey, $csecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $info = $oauth->getRequestToken('https://twitter.com/oauth/request_token');

        if (isset($info['oauth_token']) && isset($info['oauth_token_secret'])) {
            echo "Please visit https://twitter.com/oauth/authorize?oauth_token=".$info['oauth_token']."\n";
            echo "Then run again with 'access' option\n\n";

            $conf = getConf();
            $conf->addChild('ConsumerKey',    $ckey);
            $conf->addChild('ConsumerSecret', $csecret);
            $conf->addChild('OauthToken',     $info['oauth_token']);
            $conf->addChild('OauthSecret',    $info['oauth_token_secret']);
            saveConf($conf);
        }
    } catch(OAuthException $E) {
        echo "Error: ".$E->getMessage()."\n";
        die("Have you properly register the application ? Visit http://twitter.com/oauth_clients/new\n");
    }
}

function getAccess() {
    $conf = getConf(1);

    try {
        $oauth = new OAuth($conf->ConsumerKey, $conf->ConsumerSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $oauth->setToken($conf->OauthToken, $conf->OauthSecret);
        $info = $oauth->getAccessToken('https://twitter.com/oauth/access_token');

        if (isset($info['oauth_token'])) {
            echo "Access granted !\n\n";

            $conf->addChild('UserToken',  $info['oauth_token']);
            $conf->addChild('UserSecret', $info['oauth_token_secret']);
            $conf->addChild('UserId',     $info['user_id']);
            $conf->addChild('UserName',   $info['screen_name']);
            saveConf($conf);
        }
    } catch(OAuthException $E) {
        echo "Error: ".$E->getMessage()."\n";
        die("Have you allow the application to acces to your account ? Run 'register' again.\n");
    }
}

function Help() {
        $cmd = $_SERVER['argv'][0];
        echo "Usage:\n";
        echo "\t$cmd\n\t\twithout option, display current status\n\n";
        echo "\t$cmd help\n\t\tdisplay this text\n\n";
        echo "\t$cmd register CONSUMER_KEY CONSUMER_SECRET\n\t\tregister twitter application,\n\t\tsee http://twitter.com/oauth_clients/new\n\n";
        echo "\t$cmd access\n\t\tverify the registration, must be call only once after 'register'\n\n";
        echo "\t$cmd 'any message'\n\t\tchange you online status\n\n";
}

echo "\ntwit version ".VERSION."\n\n";

if (!class_exists('Oauth')) {
    die("oauth extension is mising\n");
}
if ($_SERVER['argc']==1) {
    getStatus();

} else switch($_SERVER['argv'][1]) {
    case 'register':
        if ($_SERVER['argc']==4) {
            register($_SERVER['argv'][2], $_SERVER['argv'][3]);
        } else {
            Help();
        }
        break;

    case 'access':
        getAccess();
        break;

    case 'help':
        Help();
        break;

    default:
        if ($_SERVER['argc']==2) {
            setStatus($_SERVER['argv'][1]);
        } else {
            Help();
        }
}
