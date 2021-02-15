<?php

// this function performs no checking on the input variables

// $username  -  the eBay user name

// $password  -  the eBay user password

// $item      -  the eBay item number to buy

// $link      -  itemlink with referral, can leave empty i.e. ''

function place_bin($username, $password, $item, $link) {

 

   $cookies = dirname(__DIR__).'/cookies.txt';
//$cookies = include('./cookies.txt');
     

    //set success as default false

    $success = false;

    $bid_success = false;

 

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:15.0) Gecko/20100101 Firefox/15.0.1');

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

    curl_setopt($curl, CURLOPT_REFERER, $link);

    curl_setopt($curl, CURLOPT_COOKIEFILE, $cookies);

    curl_setopt($curl, CURLOPT_COOKIEJAR, $cookies);

     

    //query the sign-out page

    //curl_setopt($curl, CURLOPT_URL, "http://signin.ebay.com/ws/eBayISAPI.dll?SignIn&lgout=1");

    //$ret = curl_exec ($curl);

     

    //IMPORTANT     

    //query the sign-in page to set the cookies

    curl_setopt($curl, CURLOPT_URL, 'http://signin.ebay.com/aw-cgi/eBayISAPI.dll?SignIn&campid=5337161990&customid=7');

    curl_exec ($curl);

     

    //query the referal link page

    if ($link) {

        curl_setopt($curl, CURLOPT_URL, $link);

        $ret = curl_exec ($curl);

    }

     

    //sign-in

    curl_setopt($curl, CURLOPT_URL, "http://signin.ebay.com/aw-cgi/eBayISAPI.dll?MfcISAPICommand=SignInWelcome&siteid=0&co_partnerId=2&UsingSSL=0&ru=&pp=&pa1=&pa2=&pa3=&i1=-1&pageType=-1&userid={$username}&pass={$password}");

    $ret = curl_exec ($curl);

    if(curl_errno($curl)){

        ebaylog('Curl error: ' . curl_error($curl));

    }

    if (!$ret) {

        $ret = curl_exec ($curl);

        if(curl_errno($curl)){

            ebaylog('Curl error: ' . curl_error($curl));

        }

        if (!$ret) {

            $ret = curl_exec ($curl);

            if(curl_errno($curl)){

                ebaylog('Curl error: ' . curl_error($curl));

            }

        }

    }

     

    if (strpos($ret, '"loggedIn":true') === FALSE) {

        if (preg_match('%<b class="altTitle">(.*)</b>%', $ret, $regs)) {

            $err = $regs[1];

            ebaylog("\"{$err}\"");

            if (strpos($err, 'The alerts below') === 0) {

                ebaylog("{$item}: 'The alerts below' found, successful");

                //set it to succes

            }

        } else {

            ebaylog("{$item}: Failed signing in");

            if (preg_match('%<font face=".*?" size="3"><b>(.*?)</b></font>%', $ret, $regs)) {

                $err = $regs[1];

                ebaylog("\"{$err}\"");

            }

             

            //test_write($ret);

            goto end;

        }

         

         

    } else {

        ebaylog("{$item}: Success signing in");

    }

     

     

    //place the initial bin

    curl_setopt($curl, CURLOPT_URL, "http://offer.ebay.com/ws/eBayISAPI.dll?BinConfirm&item={$item}&quantity=1&campid=5337161990&customid=7");

    $ret = curl_exec ($curl);

    if(curl_errno($curl)){

        ebaylog('Curl error: ' . curl_error($curl));

    }

    if (!$ret) {

        $ret = curl_exec ($curl);

    }

 

     

    if (preg_match_all('/(?:value="([-0-9a-zA-Z]*)" *)?name="stok"(?: *value="([-0-9a-zA-Z]*)")?/', $ret, $regs)) {

        $stok = $regs[1][0];

    } else {

        //Failed to get 'stok' value

        //try and determine why

         

        //check if immediate paypal checkout required

        if (preg_match('%<p\W*>\W*(.*)</p>%i', $ret, $regs)) {

            $err = $regs[1];

            if (stripos($ret, "You're almost done!") === 0) {

                ebaylog("Requires immediate PayPal payment.");

                //set it to success

                $success = true;

                goto end;

            } else {

                test_write($ret);

            }

         

        } else if (preg_match('%<div class="statusDiv">(.*?)</div>%', $ret, $regs)) {

            $err = $regs[1];

            ebaylog("'{$err}'");

            //if string starts with "Enter US $0.41 or more"

            if (stripos($err, 'Transaction Blocked') === 0) {

                ebaylog("{$item}: 'Transaction Blocked' found, aborting");

                //set it to success

                $success = true;

            } else {

                test_write($ret);

            }

             

        } else if (preg_match('%"\d*" - Invalid Item</div>%', $ret)) {

            ebaylog("{$item}: 'Invalid Item' found, aborting");

            test_write($ret);

            //set it to success

            $success = true;

        } else if (preg_match('%<div class="subTlt"><ul class="errList"><li>(.*?)</li></ul></div>%', $ret)) {

            ebaylog("{$item}: 'no longer available' found, aborting");

            test_write($ret);

            //set it to success

            $success = true;

        } else if (preg_match('%id="w\d-\d-_msg".*?>(.*?)</span>%', $ret, $regs)) {

            ebaylog("'{$regs[1]}'");

        } else if (preg_match('%<div\s+class\s*=\s*"(?:errRed|errBlk|errTitle|statusDiv)"\s*>(.*?)</div>%i', $ret, $regs)) {

            ebaylog("'{$regs[1]}'");

        } else {

            //don't know why so log the page

            ebaylog("{$item}: Failed to get 'stok' value");

            test_write($ret);

        }

        goto end;

    }

     

    if (preg_match_all('/(?:value="([-0-9a-zA-Z]*)" *)?name="uiid"(?: *value="([-0-9a-zA-Z]*)")?/', $ret, $regs)) {

        $uiid = $regs[1][0];

    } else {

        ebaylog("{$item}: Failed to get 'uiid' value");

        goto end;

    }

     

 

    if ($stok && $uiid) {

        ebaylog("{$item}: Success placing initial bid");

    } else {

        ebaylog("{$item}: Failed placing initial bid");

        goto end;

         

    }

     

    //confirm the bid

    $temp = "http://offer.ebay.com/ws/eBayISAPI.dll?MfcISAPICommand=BinConfirm&quantity=1&mode=1&stok={$stok}&xoredirect=true&uiid={$uiid}&co_partnerid=2&user={$username}&fb=0&item={$item}&campid=5337161990&customid=7";

    curl_setopt($curl, CURLOPT_URL, $temp);

    $ret = curl_exec ($curl);

    if(curl_errno($curl)){

        ebaylog('Curl error: ' . curl_error($curl));

    }

    if (!$ret) {

        $ret = curl_exec ($curl);

    }

     

    if (stripos($ret, 'Commit to buy') !== FALSE) {

        ebaylog('Trying again');

        $ret = curl_exec ($curl);

        if(curl_errno($curl)){

            ebaylog('Curl error: ' . curl_error($curl));

        }

        if (!$ret) {

            $ret = curl_exec ($curl);

        }

    }

     

    //perform a number of tests to determine if the bid was a success

    $bid_success = true;

    if (stripos($ret, 'Please pay now to complete your purchase.') === FALSE) {

        $bid_success  = false;

        ebaylog("{$item}: Failed placing final bid");

        //try and determine why

        if (preg_match('%<div\s+class\s*=\s*"(?:errRed|errBlk|errTitle|statusDiv|title)"\s*>(.*?)</div>%i', $ret, $regs)) {

            $err = $regs[1];

            ebaylog("'{$err}'");

        } else {

            //we don't know why it failed so write the data

            test_write($ret);

            ebaylog($temp);

        }

    }

     

    if ($bid_success) {

        ebaylog("{$item}: Success placing final bid");

        $success = true;

    }

     

    end:

     

    //close the curl session

    curl_close ($curl);

     

    if ($success) {

        ebaylog("{$item}: Success: {$username}");

    } else {

        ebaylog("{$item}: Failure: {$username}");

    }

     

    return $success;

}
?>
