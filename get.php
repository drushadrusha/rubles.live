<?php

//
//      RUBLES.LIVE CURRENCY EXCHANGE RATES PARSER
//                       2022-2023
//                     @drushadrusha
//

// VSCode shortcuts for folding all blocks of code:
// Fold all: Ctrl+K Ctrl+0, on Mac Cmd+K Cmd+0
// Unfold all: Ctrl+K Ctrl+J, on Mac Cmd+K Cmd+J

// TODO: Fix variables names
// TODO: A little bit more of error handling
// TODO: Add more currencies and countries

//    __                  _   _                 
//   / _|                | | (_)                
//  | |_ _   _ _ __   ___| |_ _  ___  _ __  ___ 
//  |  _| | | | '_ \ / __| __| |/ _ \| '_ \/ __|
//  | | | |_| | | | | (__| |_| | (_) | | | \__ \
//  |_|  \__,_|_| |_|\___|\__|_|\___/|_| |_|___/
//
//  v-----------------------------------------v

    //
    // Paths
    //

      $storage_path = "/var/www/bots.larisa.fun/tenge";
      
    //
    // MIR
    // v-----------------------------------------v

        include('simple_html_dom.php');
        $html = file_get_html('https://mironline.ru/support/list/kursy_mir/');
        $table = $html->find('table', 0);
        $rows = $table->find('tr');
        $data = array();
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $cells = $row->find('td');
            $currency = trim($cells[0]->plaintext);
            $rate = trim($cells[1]->plaintext);
            // remove &nbsp; from the rate
            $rate = str_replace('&nbsp;', '', $rate);
            $data[] = array('currency' => $currency, 'rate' => $rate);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $mirDataJSON = $json;

        $mirData = array();
        $mirDataJSON = file_get_contents("https://bots.drusha.ru/tenge/mir.php");
        $mirData = json_decode($mirDataJSON, true);

        function getMirRate($currency){
            global $mirData;
            foreach($mirData as $item){
            if($item['currency'] == $currency){
                $item['rate'] = str_replace(',', '.', $item['rate']);
                return $item['rate'];
            }
            }
            return 0;
        }

    //
    // Generic USD/RUB/EUR rates
    // TODO: Fall back to other sources if one of them is down
    // v-----------------------------------------v

        $vndCurrencyData = json_decode(file_get_contents("https://raw.githubusercontent.com/fawazahmed0/currency-api/1/latest/currencies/vnd.json"), true);
        $kgsCurrencyData = json_decode(file_get_contents("https://raw.githubusercontent.com/fawazahmed0/currency-api/1/latest/currencies/kgs.json"), true);
        $tjCurrencyData =  json_decode(file_get_contents("https://raw.githubusercontent.com/fawazahmed0/currency-api/1/latest/currencies/tjs.json"), true);
        $uzsCurrencyData = json_decode(file_get_contents("https://raw.githubusercontent.com/fawazahmed0/currency-api/1/latest/currencies/uzs.json"), true);
        $geCurrencyData =  json_decode(file_get_contents("https://raw.githubusercontent.com/fawazahmed0/currency-api/1/latest/currencies/gel.json"), true);

        $USDtoVNDRate = $vndCurrencyData['vnd']['usd'];
        $USDtoTJSRate = $tjCurrencyData['tjs']['usd'];
        $EURtoTJSRate = $tjCurrencyData['tjs']['eur'];
        $RUBtoTJSRate = $tjCurrencyData['tjs']['rub'];

        $USDtoUZSRate = $uzsCurrencyData['uzs']['usd'];

        $RUBtoKGSRate = $kgsCurrencyData['kgs']['rub'];
        $USDtoKGSRate = $kgsCurrencyData['kgs']['usd'];

        $USDtoGELRate = $geCurrencyData['gel']['usd'];
        $EURtoGELRate = $geCurrencyData['gel']['eur'];

    //
    // Bestchange
    // v-----------------------------------------v
        $timeout = 60;
        $temp_filename = "info.zip";
        set_time_limit($timeout);//may take time for downloading
        ini_set("memory_limit", "512M");//may require more memory
        $content = file_get_contents("http://api.bestchange.ru/info.zip", false, stream_context_create(array("http"=>array("timeout"=>$timeout))));
        if ($content === false) exit("error");
        $fp = fopen($temp_filename, "w");
        fputs($fp, $content);
        fclose($fp);
        $zip = new ZipArchive;
        if (!$zip->open($temp_filename)) exit("error");
        $currencies = array();
        foreach (explode("\n", $zip->getFromName("bm_cy.dat")) as $value) {
            $entry = explode(";", $value);
            $currencies[$entry[0]] = iconv("windows-1251", "utf-8", $entry[2]);
        }
        $exchangers = array();
        foreach (explode("\n", $zip->getFromName("bm_exch.dat")) as $value) {
            $entry = explode(";", $value);
            $exchangers[$entry[0]] = iconv("windows-1251", "utf-8", $entry[1]);
        }
        $rates = array();
        foreach (explode("\n", $zip->getFromName("bm_rates.dat")) as $value) {
            $entry = explode(";", $value);
            $rates[$entry[0]][$entry[1]][$entry[2]] = array("rate"=>$entry[3] / $entry[4], "reserve"=>$entry[5], "reviews"=>str_replace(".", "/", $entry[6]));
        }
        $zip->close();
        unlink($temp_filename);

        function bestChange($from,$to){

            global $exchangers;
            global $rates;

            $from_cy = $from;//Bitcoin //165
            $to_cy = $to;//Ethereum // 169

            //echo("Exchange rates in the direction <a target=\"_blank\" href=\"https://www.bestchange.ru/index.php?from=" . $from_cy . "&to=" . $to_cy . "\">" . $currencies[$from_cy] . " &rarr; " . $currencies[$to_cy] . "</a>:<br><br>");
            uasort($rates[$from_cy][$to_cy], function ($a, $b) {
            if ($a["rate"] > $b["rate"]) return 1;
            if ($a["rate"] < $b["rate"]) return -1;
            return(0);
            });

            $data = array();
            foreach ($rates[$from_cy][$to_cy] as $exch_id=>$entry) {
            array_push($data, 1/$entry['rate']);
            }

            return $data[0];
        }

    //
    // HTML PARSER
    // Have no use for now
    // v-----------------------------------------v
        /*function Parse($p1, $p2, $p3) {
            $num1 = strpos($p1, $p2);
            if ($num1 === false) return 0;
            $num2 = substr($p1, $num1);
            return strip_tags(substr($num2, 0, strpos($num2, $p3)));
        }*/

    //
    // KUCOIN
    // v-----------------------------------------v
        $rub2usdtKuk = file_get_contents("https://www.kucoin.com/_api/otc/ad/list?currency=USDT&side=SELL&legal=RUB&page=1&pageSize=10&status=PUTUP&lang=en_US");
        $rub2usdtKuk = json_decode($rub2usdtKuk, true);
        if(isset($rub2usdtKuk['items'][0]['floatPrice'])){
            $rub2usdtKuk = $rub2usdtKuk['items'][0]['floatPrice'];
        } else {
            $rub2usdtKuk = 0;
        }

        function getKucoinRate($asset = "KZT"){

            $usdt2kzt = file_get_contents("https://www.kucoin.com/_api/otc/ad/list?currency=USDT&side=BUY&legal=".$asset."&page=1&pageSize=10&status=PUTUP&lang=en_US");
            $usdt2kzt = json_decode($usdt2kzt, true);

            if(isset($usdt2kzt['items'][0]['floatPrice'])){
                $usdt2kzt = $usdt2kzt['items'][0]['floatPrice'];
            } else {
                $usdt2kzt = 0;
            }

            return $usdt2kzt;

        }
    //
    // Binance 
    // v-----------------------------------------v

        // Rate to RUB
        $binance_rub2usdt = getBinanceRUB2("USDT");
        $binance_rub2btc = getBinanceRUB2("BTC");
        $binance_rub2eth = getBinanceRUB2("ETH");
        $binance_rub2bnb = getBinanceRUB2("BNB");
        $binance_rub2busd = getBinanceRUB2("BUSD");


        function getBinanceRUB2($asset){
            $result = getBinancePrice($asset, "5000", "RUB", '["TinkoffNew", "RaiffeisenBank", "AkBarsBank", "MTSBank"]', "BUY");
            return $result;
        }

        function getBinancePrice($asset, $amount, $fiat = "KZT", $payTypes = '["KaspiBank"]', $tradeType = "SELL"){
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "proMerchantAds": false,
                "page": 1,
                "rows": 10,
                "payTypes": '.$payTypes.',
                "countries": [],
                "publisherType": null,
                "asset": "'.$asset.'",
                "fiat": "'.$fiat.'",
                "tradeType": "SELL",
                "transAmount": "'.$amount.'"
            }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $data = json_decode($response, true);
            $tenge = $data['data'][0]['adv']['price'];
            return $tenge;

        }

    //
    // CBR
    // v-----------------------------------------v
        $date = date("d/m/Y",time());
        $cbxml = file_get_contents("http://www.cbr.ru/scripts/XML_daily.asp?date_req=".$date);
        function parseCbr($val){
            global $cbxml;
            $xml = $cbxml;
            $xml = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $array = json_decode($json,TRUE);
            //$result = array();
            foreach ($array['Valute'] as $key => $value) {
                if($value['CharCode'] == $val){
                    $rate = $value['Value'];
                    $rate = str_replace(',', '.', $rate);
                    return $rate;
                }
            }
            return false;
        }
    //
    // Finsend
    // v-----------------------------------------v
      function getFinsendKz(){
          $url = "https://www.finsend.io/api/v1/transfers/calculate";
          $data = array(
              "amount" => 100000,
              "toCountryId" => 398
          );
          $options = array(
              "http" => array(
                  "header" => "Content-type: application/json",
                  "method" => "POST",
                  "content" => json_encode($data)
              )
          );
          $context = stream_context_create($options);
          $response = file_get_contents($url, false, $context);
          $response = json_decode($response, true);
          if(isset($response['body']['currencyRate'])){
              return $response['body']['currencyRate'];
          } else {
              return 0;
          }
      }
    //
    // QIWI
    // v-----------------------------------------v
        function getQiwiRate($from, $to){
            $data = file_get_contents("https://edge.qiwi.com/sinap/crossRates");
            $data = json_decode($data, true)['result'];

            foreach ($data as $key => $k){
                if($k['to'] == $to && $k['from'] == $from){
                    if($k['rate'] == 0){
                        return 0;
                    }
                    return 1/($k['rate']);
                }
            }

            return 0;
        }
//
//
//   _                  _    _         _              
//  | |                | |  | |       | |             
//  | | ____ _ ______ _| | _| |__  ___| |_ __ _ _ __  
//  | |/ / _` |_  / _` | |/ / '_ \/ __| __/ _` | '_ \ 
//  |   < (_| |/ / (_| |   <| | | \__ \ || (_| | | | |
//  |_|\_\__,_/___\__,_|_|\_\_| |_|___/\__\__,_|_| |_|
//
// v--------------------------------------------------v
                                                    
    echo "🇰🇿 Kazakstan\n";

    $date = time();
    $kzRates = array("date"=>$date);

    // KoronaPay KZT
    $data = file_get_contents("https://koronapay.com/transfers/online/api/transfers/tariffs?sendingCountryId=RUS&sendingCurrencyId=810&receivingCountryId=KAZ&receivingCurrencyId=398&paymentMethod=debitCard&receivingAmount=1000000&receivingMethod=cash&paidNotificationEnabled=false");
    $data = json_decode($data,true);
    if($data[0]['sendingAmount'] != 0 && $data[0]['receivingAmount'] != 0){
    $koronapay = $data[0]['receivingAmount'] / $data[0]['sendingAmount'];
      $kzRates['<a href="https://koronapay.com/">KoronaPay</a>'] = $koronapay;
    }

    // Mir
    $mir = getMirRate("Казахстанский тенге");
    if($mir > 0){
    $mir = 1/floatval($mir);
      $kzRates['<a href="https://www.mirtransfer.ru/">МИР</a>'] = $mir;
    }
    // CBR
    $cbr = 1/(floatval(parseCbr("KZT"))/100);
    if($cbr > 0){
      $kzRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbr;
    }

    // QIWI
    $qiwi = getQiwiRate("643","398");
    if($qiwi > 0){
      $kzRates['<a href="https://qiwi.com/">Qiwi</a>'] = $qiwi;
    }

    // BestChange Visa RU - Visa KZ
    $bestchange = bestChange(59, 111);  // visa ru - visa kz
    if($bestchange > 0){
      $kzRates['<a href="https://www.bestchange.ru/">BestChange</a> Visa/MC RU->KZ'] = $bestchange;
    }
    // BestChange Sber RU - Kaspi KZ
    $bestchangeSber = bestChange(42, 66);  // sber ru - kaspi
    if($bestchangeSber > 0){
      $kzRates['<a href="https://www.bestchange.ru/">BestChange</a> Sber->Kaspi'] = $bestchangeSber;
    }

    // BestChange Sber RU - USDT - Kaspi KZ
    $first = bestChange(42,10); // sber - usdt
    $second = bestChange(10,66); // usdt - kaspi
    if($first > 0 && $second > 0){
    $bestchangeSberUSDTKaspi = $second/(1/$first); // sber - usdt - kaspi
      $kzRates['<a href="https://www.bestchange.ru/">BestChange</a> Sber->USDT->Kaspi'] = $bestchangeSberUSDTKaspi;
    }

    // BINANCE

    // via USDT
    $kz_binance_usdt = getBinancePrice("USDT", 5000); 
    if($kz_binance_usdt > 0 && $binance_rub2usdt > 0){
      $kzRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> USDT'] = $kz_binance_usdt/$binance_rub2usdt;
    }

    // via ETH
    $kz_binance_eth = getBinancePrice("ETH", 5000);
    if($kz_binance_eth > 0 && $binance_rub2eth > 0){
      $kzRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> ETH'] = $kz_binance_eth/$binance_rub2eth;
    }

    // via BTC
    $kz_binance_btc = getBinancePrice("BTC", 5000);
    if($kz_binance_btc > 0 && $binance_rub2btc > 0){
      $kzRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> BTC'] = $kz_binance_btc/$binance_rub2btc;
    }

    // via BUSD
    $kz_binance_busd = getBinancePrice("BUSD", 5000);
    if($kz_binance_busd > 0 && $binance_rub2busd > 0){
      $kzRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> BUSD'] = $kz_binance_busd/$binance_rub2busd;
    }

    // Kucoin

    $kz_kukoin_usdt = getKucoinRate("KZT");
    // via USDT
    if($kz_kukoin_usdt > 0 && $rub2usdtKuk > 0){
      $kzRates['<a href="https://www.kucoin.com">Kucoin</a> USDT'] = $kz_kukoin_usdt/$rub2usdtKuk;
    }

    // Finsend

    $kzFinsend = getFinsendKz();
    if($kzFinsend > 0){
    $kzRates['<a href="https://finsend.ru/">Finsend</a>'] = $kzFinsend;
    }

    // save to json file
    print_r($kzRates);
    file_put_contents($storage_path."/data/".$date.'.json', json_encode($kzRates));
    sleep(5);

//
//                                   _       
//                                  (_)      
//    __ _ _ __ _ __ ___   ___ _ __  _  __ _ 
//   / _` | '__| '_ ` _ \ / _ \ '_ \| |/ _` |
//  | (_| | |  | | | | | |  __/ | | | | (_| |
//   \__,_|_|  |_| |_| |_|\___|_| |_|_|\__,_|
//
// v--------------------------------------------------v

  echo "🇦🇲 Armenia\n";

  $date = time();
  $amRates = array('date'=>$date);

  // BestChange Visa RU - Visa AM
  $bestchangeam = bestChange(59, 5);  // visa ru - visa am
  if($bestchangeam > 0){
    $amRates['<a href="https://rubles.live/bc.html">BestChange</a> Visa/MC RU->AM'] = $bestchangeam;
  }

  // CBR
  $cbram = 1/(floatval(parseCbr("AMD"))/100);
  if($cbram > 0){
    $amRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbram;
  }

  // MIR
  $String = file_get_contents('https://mironline.ru/support/list/kursy_mir/');
  $mir = getMirRate("Армянский драм");
  if($mir > 0){
    $amRates['<a href="https://mironline.ru/support/list/kursy_mir/">МИР</a>'] = 1/floatval($mir);
  }

  // Binance

  function calcBinanceAm($asset){
    $price = getBinancePrice($asset, 5000, "AMD", '["UNIBANK", "IDBank"]');
    return $price;
  }
  // via USDT
  $am_binance_usdt = calcBinanceAm("USDT");
  if($am_binance_usdt > 0 && $binance_rub2usdt > 0){
    $amRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> USDT'] = $am_binance_usdt/$binance_rub2usdt;
  }
  // via BTC
  $am_binance_btc = calcBinanceAm("BTC");
  if($am_binance_btc > 0 && $binance_rub2btc > 0){
    $amRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> BTC'] = $am_binance_btc/$binance_rub2btc;
  }
  // via ETH
  $am_binance_eth = calcBinanceAm("ETH");
  if($am_binance_eth > 0 && $binance_rub2eth > 0){
    $amRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> ETH'] = $am_binance_eth/$binance_rub2eth;
  }
  // via BUSD
  $am_binance_busd = calcBinanceAm("BUSD");
  if($am_binance_busd > 0 && $binance_rub2busd > 0){
    $amRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> BUSD'] = $am_binance_busd/$binance_rub2busd;
  }

  print_r($amRates);
  file_put_contents($storage_path."/am/".$date.'.json', json_encode($amRates));
  sleep(5);

// 
//   _              _              
//  | |            | |             
//  | |_ _   _ _ __| | _____ _   _ 
//  | __| | | | '__| |/ / _ \ | | |
//  | |_| |_| | |  |   <  __/ |_| |
//   \__|\__,_|_|  |_|\_\___|\__, |
//                            __/ |
//                           |___/ 
//
// TODO: Add Binance
//
//  v--------------------------------------------------v
  echo "🇹🇷 Turkey\n";

  $date = time();
  $trRates = array('date'=>$date);

  // CBR
  $cbrtr = (floatval(parseCbr("TRY"))/10);
  if($cbrtr > 0){
    $trRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbrtr;
  }

  // KoronaPay TRY
  $data = file_get_contents("https://koronapay.com/transfers/online/api/transfers/tariffs?sendingCountryId=RUS&sendingCurrencyId=810&receivingCountryId=TUR&receivingCurrencyId=949&paymentMethod=debitCard&receivingAmount=100000&receivingMethod=cash&paidNotificationEnabled=false");
  $data = json_decode($data,true);
  if($data[0]['receivingAmount'] > 0 && $data[0]['sendingAmount'] > 0){
    $koronapaytr = 1/($data[0]['receivingAmount'] / $data[0]['sendingAmount']);
    $trRates['<a href="https://koronapay.com/">KoronaPay</a> TRY'] = $koronapaytr;
  }

  // BestChange Sber->USDT->Visa/MC
  $first = bestChange(42,10); // sber - usdt
  $second = bestChange(10,83); // usdt - visa/mc
  if($first > 0 && $second > 0){
    $bestchangeSberUSDTTurkey = 1/($second/(1/$first));
    $trRates['<a href="https://rubles.live/bc.html">BestChange</a> Visa/MC RU->USDT->Visa/MC TR'] = $bestchangeSberUSDTTurkey;
  }
  print_r($trRates);
  file_put_contents($storage_path."/tr/".$date.'.json', json_encode($trRates));
  sleep(5);

//
//         _      _                         
//        (_)    | |                        
//  __   ___  ___| |_ _ __   __ _ _ __ ___  
//  \ \ / / |/ _ \ __| '_ \ / _` | '_ ` _ \ 
//   \ V /| |  __/ |_| | | | (_| | | | | | |
//    \_/ |_|\___|\__|_| |_|\__,_|_| |_| |_|
//
//  TODO: Add Binance
//
// v--------------------------------------------------v

  echo "🇻🇳 Vietnam\n";

  $date = time();
  $vnRates = array('date'=>$date);
  // CBR
  $cbrvn = 1/(floatval(parseCbr("VND"))/10000);
  if($cbrvn > 0){
    $vnRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbrvn;
  }
  // mir
  $mir = getMirRate("Вьетнамский донг");
  if($mir > 0){
    $vnRates['<a href="https://mironline.ru/support/list/kursy_mir/">МИР</a>'] = 1/floatval($mir);
  }

  // KoronaPay VND
  $datavn = file_get_contents("https://koronapay.com/transfers/online/api/transfers/tariffs?sendingCountryId=RUS&sendingCurrencyId=810&receivingCountryId=VNM&receivingCurrencyId=840&paymentMethod=debitCard&receivingAmount=500&receivingMethod=cash&paidNotificationEnabled=false");
  $datavn = json_decode($datavn,true);
  if($datavn[0]['receivingAmount'] > 0 && $datavn[0]['sendingAmount'] > 0){
    $koronapayvnRUBtoUSDRate = 1/($datavn[0]['receivingAmount'] / $datavn[0]['sendingAmount']);
    $koronapayRUBtoVND = $koronapayvnRUBtoUSDRate * $USDtoVNDRate;
    if($koronapayRUBtoVND > 0){
      $vnRates['<a href="https://koronapay.com/">KoronaPay</a> USD (прим. курс)'] = 1/$koronapayRUBtoVND;
    }
  }

  // Kucoin
  $vn_kukoin_usdt = getKucoinRate("VND");
  // via USDT
  if($vn_kukoin_usdt > 0 && $rub2usdtKuk){
    $vnRates['<a href="https://www.kucoin.com">Kucoin</a> USDT'] = $vn_kukoin_usdt/$rub2usdtKuk;
  }

  // Save
  print_r($vnRates);
  file_put_contents($storage_path."/vn/".$date.'.json', json_encode($vnRates));
  sleep(5);


//
//   _          _                      
//  | |        | |                     
//  | |__   ___| | __ _ _ __ _   _ ___ 
//  | '_ \ / _ \ |/ _` | '__| | | / __|
//  | |_) |  __/ | (_| | |  | |_| \__ \
//  |_.__/ \___|_|\__,_|_|   \__,_|___/
//                                 
// v--------------------------------------------------v

  echo "🇧🇾 Belarus\n";

  $date = time();
  $byRates = array('date'=>$date);

  // CBR
  $cbrby = (floatval(parseCbr("BYN")));
  if($cbrby > 0){
    $byRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbrby;
  }
  // MIR
  $mir = getMirRate("Белорусский рубль");
  $mirby = floatval($mir);
  if($mirby > 0){
    $byRates['<a href="https://mironline.ru/support/list/kursy_mir/">МИР</a>'] = $mirby;
  }

  // Save
  print_r($byRates);
  file_put_contents($storage_path."/by/".$date.'.json', json_encode($byRates));

  sleep(5);

//
//   _                    _         _              
//  | |                  (_)       | |             
//  | | ___   _ _ __ __ _ _ _______| |_ __ _ _ __  
//  | |/ / | | | '__/ _` | |_  / __| __/ _` | '_ \ 
//  |   <| |_| | | | (_| | |/ /\__ \ || (_| | | | |
//  |_|\_\\__, |_|  \__, |_/___|___/\__\__,_|_| |_|
//         __/ |     __/ |                         
//        |___/     |___/                          
//
// TODO: Add Binance
//
// v--------------------------------------------------v

  echo "🇰🇬 Kyrgyzstan\n";

  $date = time();
  $kgRates = array('date'=>$date);
  // CBR
  $cbrkg = 1/(floatval(parseCbr("KGS"))/100);
  if($cbrkg > 0){
    $kgRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbrkg;
  }
  // MIR
  $mir = getMirRate("Кыргызский сом");
  if($mir > 0){
    $kgRates['<a href="https://mironline.ru/support/list/kursy_mir/">МИР</a>'] = 1/floatval($mir);
  }

  // koronapay rub
  $koronapayRUBtoKGSRUB = file_get_contents("https://koronapay.com/transfers/online/api/transfers/tariffs?sendingCountryId=RUS&sendingCurrencyId=810&receivingCountryId=KGZ&receivingCurrencyId=810&paymentMethod=debitCard&receivingAmount=10000&receivingMethod=cash&paidNotificationEnabled=false");
  $koronapayRUBtoKGSRUB = json_decode($koronapayRUBtoKGSRUB,true);
  if($koronapayRUBtoKGSRUB[0]['sendingAmount'] > 0 && $koronapayRUBtoKGSRUB[0]['receivingAmount'] > 0){
    $koronapayRUBtoKGSRUB = $koronapayRUBtoKGSRUB[0]['sendingAmount'] / $koronapayRUBtoKGSRUB[0]['receivingAmount'];
    $koronapayRUBtoKGS = $koronapayRUBtoKGSRUB * $RUBtoKGSRate;
    if($koronapayRUBtoKGS > 0){
      $kgRates['<a href="https://koronapay.com/">KoronaPay</a> RUB (прим. курс)'] = 1/$koronapayRUBtoKGS;
    }
  }

  // koronapay usd
  $koronapayUSDtoKGSUSD = file_get_contents("https://koronapay.com/transfers/online/api/transfers/tariffs?sendingCountryId=RUS&sendingCurrencyId=810&receivingCountryId=KGZ&receivingCurrencyId=840&paymentMethod=debitCard&receivingAmount=10000&receivingMethod=cash&paidNotificationEnabled=false");
  $koronapayUSDtoKGSUSD = json_decode($koronapayUSDtoKGSUSD,true);
  if($koronapayUSDtoKGSUSD[0]['sendingAmount'] > 0 && $koronapayUSDtoKGSUSD[0]['receivingAmount'] > 0){
    $koronapayUSDtoKGSUSD = $koronapayUSDtoKGSUSD[0]['sendingAmount'] / $koronapayUSDtoKGSUSD[0]['receivingAmount'];
    $koronapayUSDtoKGS = 1/($koronapayUSDtoKGSUSD * $USDtoKGSRate);
    if($koronapayUSDtoKGS > 0){
      $kgRates['<a href="https://koronapay.com/">KoronaPay</a> USD (прим. курс)'] = $koronapayUSDtoKGS;
    }
  }

  // Save
  print_r($kgRates);
  file_put_contents($storage_path."/kg/".$date.'.json', json_encode($kgRates));

  sleep(5);

//
//   _        _ _ _    _     _              
//  | |      (_|_) |  (_)   | |             
//  | |_ __ _ _ _| | ___ ___| |_ __ _ _ __  
//  | __/ _` | | | |/ / / __| __/ _` | '_ \ 
//  | || (_| | | |   <| \__ \ || (_| | | | |
//   \__\__,_| |_|_|\_\_|___/\__\__,_|_| |_|
//          _/ |                            
//         |__/                             
//
//  TODO: Add Binance
//
// v--------------------------------------------------v

  echo "🇹🇯 Tajikistan\n";

  $date = time();
  $tjRates = array('date'=>$date);

  // CBR
  $cbrtj = (floatval(parseCbr("TJS"))/10);
  if($cbrtj > 0){
    $tjRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbrtj;
  }
  // MIR
  $mir = getMirRate("Таджикский сомони");
  if($mir > 0){
    $tjRates['<a href="https://mironline.ru/support/list/kursy_mir/">МИР</a>'] = floatval($mir);
  }

  // korona usd
  $koronapayRUBtoTJS = file_get_contents("https://koronapay.com/transfers/online/api/transfers/tariffs?sendingCountryId=RUS&sendingCurrencyId=810&receivingCountryId=TJK&receivingCurrencyId=840&paymentMethod=debitCard&receivingAmount=1000&receivingMethod=cash&paidNotificationEnabled=false");
  $koronapayRUBtoTJS = json_decode($koronapayRUBtoTJS,true);
  if($koronapayRUBtoTJS[0]['sendingAmount'] > 0 && $koronapayRUBtoTJS[0]['receivingAmount'] > 0){
    $koronapayRUBtoUZS = 1/($koronapayRUBtoTJS[0]['receivingAmount']/$koronapayRUBtoTJS[0]['sendingAmount']);
    $koronapayRUBtoTJSusd = $koronapayRUBtoUZS * $USDtoTJSRate;
    if($koronapayRUBtoTJSusd > 0){
      $tjRates['<a href="https://koronapay.com/">KoronaPay</a> USD (прим. курс)'] = $koronapayRUBtoTJSusd;
    }
  }

  print_r($tjRates);
  file_put_contents($storage_path."/tj/".$date.'.json', json_encode($tjRates));
  sleep(5);

//
//             _          _    _     _              
//            | |        | |  (_)   | |             
//   _   _ ___| |__   ___| | ___ ___| |_ __ _ _ __  
//  | | | |_  / '_ \ / _ \ |/ / / __| __/ _` | '_ \ 
//  | |_| |/ /| |_) |  __/   <| \__ \ || (_| | | | |
//   \__,_/___|_.__/ \___|_|\_\_|___/\__\__,_|_| |_|
//                     
//  TODO: Add Binance
//
// v--------------------------------------------------v

  echo "🇺🇿 Uzbekistan\n";

  $date = time();
  $uzRates = array('date'=>$date);

  // cbr
  $cbruz = 1/(floatval(parseCbr("UZS"))/10000);
  if($cbruz > 0){
    $uzRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbruz;
  }
  // mir
  $mir = getMirRate("Узбекский сум");
  if($mir > 0){
    $uzRates['<a href="https://mironline.ru/support/list/kursy_mir/">МИР</a>'] = 1/floatval($mir);
  }

  // koronapay usd
  $koronapayRUBtoUZS = file_get_contents("https://koronapay.com/transfers/online/api/transfers/tariffs?sendingCountryId=RUS&sendingCurrencyId=810&receivingCountryId=UZB&receivingCurrencyId=840&paymentMethod=debitCard&receivingAmount=10000&receivingMethod=cash&paidNotificationEnabled=false");
  $koronapayRUBtoUZS = json_decode($koronapayRUBtoUZS,true);
  if($koronapayRUBtoUZS[0]['sendingAmount'] > 0 && $koronapayRUBtoUZS[0]['receivingAmount'] > 0){
    $koronapayRUBtoUZS = 1/($koronapayRUBtoUZS[0]['receivingAmount'] / $koronapayRUBtoUZS[0]['sendingAmount']);
    $koronapayRUBtoUZS = 1/($koronapayRUBtoUZS * $USDtoUZSRate);
    if($koronapayRUBtoUZS > 0){
      $uzRates['<a href="https://koronapay.com/">KoronaPay</a> USD (прим. курс)'] = $koronapayRUBtoUZS;
    }
  }

  // save
  print_r($uzRates);
  file_put_contents($storage_path."/uz/".$date.'.json', json_encode($uzRates));

  sleep(5);

//
//                             _       
//                            (_)      
//   __ _  ___  ___  _ __ __ _ _  __ _ 
//  / _` |/ _ \/ _ \| '__/ _` | |/ _` |
// | (_| |  __/ (_) | | | (_| | | (_| |
//  \__, |\___|\___/|_|  \__, |_|\__,_|
//   __/ |                __/ |        
//  |___/                |___/         
//
//  TODO: Add Binance
//
// v--------------------------------------------------v

  echo "🇬🇪 Georgia\n";

  $date = time();
  $geRates = array('date'=>$date);

  // cbr
  $cbrge = (floatval(parseCbr("GEL")));
  if($cbrge > 0){
    $geRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbrge;
  }

  // korona pay gel
  $koronapayRUBtoGEL = file_get_contents("https://koronapay.com/transfers/online/api/transfers/tariffs?sendingCountryId=RUS&sendingCurrencyId=810&receivingCountryId=GEO&receivingCurrencyId=981&paymentMethod=debitCard&receivingAmount=10000&receivingMethod=cash&paidNotificationEnabled=false");
  $koronapayRUBtoGEL = json_decode($koronapayRUBtoGEL,true);
  $koronapayRUBtoGEL = 1/($koronapayRUBtoGEL[0]['receivingAmount'] / $koronapayRUBtoGEL[0]['sendingAmount']);
  if($koronapayRUBtoGEL > 0){
    $geRates['<a href="https://koronapay.com/">KoronaPay</a> GEL'] = $koronapayRUBtoGEL;
  }

  // koronapay usd
  $koronapayRUBtoGELUSD = file_get_contents("https://koronapay.com/transfers/online/api/transfers/tariffs?sendingCountryId=RUS&sendingCurrencyId=810&receivingCountryId=GEO&receivingCurrencyId=840&paymentMethod=debitCard&receivingAmount=10000&receivingMethod=cash&paidNotificationEnabled=false");
  $koronapayRUBtoGELUSD = json_decode($koronapayRUBtoGELUSD,true);
  $koronapayRUBtoGELUSD = 1/($koronapayRUBtoGELUSD[0]['receivingAmount'] / $koronapayRUBtoGELUSD[0]['sendingAmount']);
  $koronapayRUBtoGELUSD = ($koronapayRUBtoGELUSD * $USDtoGELRate);
  if($koronapayRUBtoGELUSD > 0){
    $geRates['<a href="https://koronapay.com/">KoronaPay</a> USD (прим. курс)'] = $koronapayRUBtoGELUSD;
  }

  // save
  print_r($geRates);
  file_put_contents($storage_path."/ge/".$date.'.json', json_encode($geRates));

  sleep(5);

//
//   _   _  __ _  ___ 
//  | | | |/ _` |/ _ \
//  | |_| | (_| |  __/
//   \__,_|\__,_|\___|
//
//                  
// v--------------------------------------------------v

  echo "🇦🇪 UAE\n";

  $date = time();
  $aeRates = array('date'=>$date);

  // cbr
  $cbrae = (floatval(parseCbr("AED")));
  if($cbrae > 0){
    $aeRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbrae;
  }

  // Binance P2P

  // via USDT to Pyypl
  $ae_binance_usdt = getBinancePrice("USDT", 100, "AED", '["Pyypl"]');
  if($ae_binance_usdt > 0 && $binance_rub2usdt > 0){
    $aeRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> USDT to Pyypl'] = 1/($ae_binance_usdt/$binance_rub2usdt);
  }

  // all other types
  $ae_binance = getBinancePrice("USDT", 100, "AED", '[]');
  if($ae_binance > 0 && $binance_rub2usdt > 0){
    $aeRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> USDT'] = 1/($ae_binance/$binance_rub2usdt);
  }


  // Kucoin
  $ae_kukoin_usdt = getKucoinRate("AED");

  if($ae_kukoin_usdt > 0 && $rub2usdtKuk > 0){
    $aeRates['<a href="https://www.kucoin.com">Kucoin</a> USDT'] = 1/($ae_kukoin_usdt/$rub2usdtKuk);
  }

  // Save
  print_r($aeRates);
  file_put_contents($storage_path."/ae/".$date.'.json', json_encode($aeRates));

  sleep(5);

//
//         _     _ _ _ _       _     _                 
//        | |   (_) | (_)     | |   (_)                
//   _ __ | |__  _| | |_ _ __ | |__  _ _ __   ___  ___ 
//  | '_ \| '_ \| | | | | '_ \| '_ \| | '_ \ / _ \/ __|
//  | |_) | | | | | | | | |_) | | | | | | | |  __/\__ \
//  | .__/|_| |_|_|_|_|_| .__/|_| |_|_|_| |_|\___||___/
//  | |                 | |                            
//  |_|                 |_|       
//
// v--------------------------------------------------v

  echo "🇵🇭 Philippines\n";
  $date = time();
  $phRates = array('date'=>$date);

  // cbr
  $cbrph = (floatval(parseCbr("PHP")));
  if($cbrph > 0){
    $phRates['<a href="https://www.cbr.ru/currency_base/daily/">ЦБ РФ</a>'] = $cbrph;
  }

  function calcBinancePh($asset){
    $rate = getBinancePrice($asset, 1000, "PHP", '["Gcash"]');
    return $rate;
  }
  // Binance P2P to GCash
  // via USDT
  $binance = calcBinancePh('USDT');
  if($binance > 0 && $binance_rub2usdt > 0){
    $phRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> USDT to GCash USDT'] = 1/($binance/$binance_rub2usdt);
  }
  // via ETH
  $binance = calcBinancePh('ETH');
  if($binance > 0 && $binance_rub2eth > 0){
    $phRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> USDT to GCash ETH'] = 1/($binance/$binance_rub2eth);
  }
  // via BTC
  $binance = calcBinancePh('BTC');
  if($binance > 0 && $binance_rub2btc > 0){
    $phRates['<a href="https://accounts.binance.com/ru/register?ref=27180256">Binance</a> USDT to GCash via BTC'] = 1/($binance/$binance_rub2btc);
  }

  // Kucoin

  $ph_kukoin_usdt = getKucoinRate("PHP");
  // via USDT
  if($ph_kukoin_usdt > 0 && $rub2usdtKuk > 0){
    $phRates['<a href="https://www.kucoin.com">Kucoin</a> USDT'] = 1/($ph_kukoin_usdt/$rub2usdtKuk);
  }

  // Save
  print_r($phRates);
  file_put_contents($storage_path."/ph/".$date.'.json', json_encode($phRates));

  // -----------
  // END
  echo "Done";
?>