<?php

/**
 * Created by PhpStorm.
 * User: william
 * Mail：tzh.wu.qq.com
 */

namespace App\Main;


use App\Exception\LoginErrException;
use App\Exception\LowLevelException;
use GuzzleHttp\Client;
use Sunra\PhpSimple\HtmlDomParser;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;
use Yosymfony\Toml\Toml;

class Main
{
    const TYPE_NOR = 0;
    const TYPE_LOGIN_ERR = 1;
    const TYPE_LLVL_ERR = 2;

    public $configFile;
    public $config;

    public $status = [];
    public $revenge = [];
    public $coinPrice = [];

    public $client1;
    public $client2;

    public function __construct()
    {
        $this->configFile = __DIR__ . '/../Config/config.toml';
        $this->config = Toml::parse($this->configFile);
        $this->client1 = new Client(
            [
                'base_uri' => $this->config['url']['baseurl'],
                'timeout' => $this->config['requestimeout'],
                'verify' => false,
            ]
        );
        $this->client2 = new Client(
            [
                'timeout' => $this->config['requestimeout'],
                'verify' => false,
            ]
        );
    }

    public function __destruct()
    {
        unset($this->client1);
        unset($this->client2);
    }


    public function main()
    {
        foreach ($this->config['phone'] as &$item) {
            $tp = array();

            if (empty($item['token'])) {
                $item['token'] = $token = $this->login($item['phone'], $item['password']);
            }

            usleep($this->config['requestgap']);

            $codes = $this->getMinerStatus($item['phone'], $item['token']);

            usleep($this->config['requestgap']);

            $tp['codes'] = $codes;

            list($tp['income'], $tp['incomeyes']) = $this->getIncome($item['phone'], $item['token']);

            $this->status[$item['phone']] = $tp;
        }


        $this->revenge = $this->handler();
        $this->coinPrice = $this->getCoinPrice();

        $this->mail($this->messageBuilder(self::TYPE_NOR));


    }

    function messageBuilder($type = self::TYPE_NOR, ...$args)
    {
        $message = '';
        switch ($type) {
            case self::TYPE_NOR:
                $result = $this->revenge;
                $result[] = $this->coinPrice[0];
                $result[] = $this->coinPrice[1];
                $result[] = $this->coinPrice[2];
                $result[] = $this->coinPrice[1] * $this->revenge[5];
                $message = sprintf($this->config['mail']['tp1'], ...$result);
                break;
            case self::TYPE_LOGIN_ERR:
                $message = sprintf($this->config['mail']['tp2'], ...$args);
                break;
            case self::TYPE_LLVL_ERR:
                $message = sprintf($this->config['mail']['tp3'], ...$args);
                break;
        }

        unset($result);
        unset($args);
        unset($type);

        return $message;

    }

    function mail($mailContent)
    {
        $transport = (new Swift_SmtpTransport('smtp.163.com', 465,'ssl'))
            ->setUsername($this->config['mail']['loginusername'])
            ->setPassword($this->config['mail']['loginpwd']);

        $mailer = new Swift_Mailer($transport);

        $message = (new Swift_Message($this->config['mail']['theme']))
            ->setFrom([$this->config['mail']['loginusername'] => $this->config['mail']['fromnickname']])
            ->setTo([$this->config['mail']['toaddress']], $this->config['mail']['tousername'])
            ->setBody($mailContent, $this->config['mail']['mailtype']);

        $result = $mailer->send($message);
        unset($mailer);
        unset($transport);
        unset($message);

        return $result;
    }


    function handler()
    {
        $totalMiner = 0;
        $totalMinerOffline = 0;
        $totalMinerOnline = 0;
        $totalIncome = 0;
        $totalIncomeYes = 0;
        $totalAccount = 0;
        foreach ($this->status as $state) {
            $totalAccount++;
            foreach ($state['codes'] as $code) {
                $totalMiner++;
                if ($code['onlineStatus'] == 0) {
                    $totalMinerOnline++;
                } else {
                    $totalMinerOffline++;
                }
            }

            $totalIncome += $state['income'];

            $totalIncomeYes += $state['incomeyes'];
        }

        return [
            date('Y-m-d H:i:s'),
            $totalAccount,
            $totalMiner,
            $totalMinerOnline,
            $totalMinerOffline,
            $totalIncome,
            $totalIncomeYes,
        ];
    }

    function getIncome($phone, $token)
    {

        $response = $this->client1->post($this->config['url']['income'], [
            'headers' => $this->getHeader(),
            'form_params' => [
                'phoneNumber' => $phone,
                'token' => $token,
            ]
        ]);

        $content = $response->getBody()->getContents();
        unset($response);

        $content = json_decode($content, true);

        if ($content['code'] == 0) {
            $last = array_pop($content['data']['history']);
            return array(
                $content['data']['totalincom'],
                $last['income']
            );
        }

        throw new LowLevelException('获取收入信息失败' . json_encode($content));
    }

    function login($phone, $pwd)
    {

        $result = $this->client1->post($this->config['url']['login'], [
            'headers' => $this->getHeader(),
            'form_params' => [
                'phoneNumber' => $phone,
                'password' => md5($pwd),
                'areacode' => $this->config['areacode'],
            ]
        ]);
        $content = $result->getBody()->getContents();


        $content = json_decode($content, true);

        unset($result);
        if ($content['code'] == 0) {
            return $content['data']['token'];
        } else {
            throw new LoginErrException(json_encode($content));
        }
    }

    function getMinerStatus($phone, $token)
    {

        $response = $this->client1->post($this->config['url']['getminercode'], [
            'headers' => $this->getHeader(),
            'form_params' => [
                'phoneNumber' => $phone,
                'token' => $token,
                'areacode' => 86,
            ]
        ]);

        $content = $response->getBody()->getContents();
        unset($response);

        $content = json_decode($content, true);


        if (empty($content['Err'])) {
            return $content['data']['CodeList'];
        }
        throw new LowLevelException('获取矿机状态失败' . json_encode($content));

    }

    function defaultHeaderBuilder()
    {
        $tp = <<<TP
Content-Type: application/x-www-form-urlencoded
Accept-Encoding: gzip
Accept-Language:zh-CN,zh;q=0.8
User-Agent: okhttp/3.8.0
TP;
        $tps = explode("\n", $tp);
        $headers = [];
        foreach ($tps as $tp) {
            list($key, $value) = explode(':', $tp, 2);
            $key = trim($key);
            $value = trim($value);
            $headers[$key] = $value;
        }

        return $headers;
    }

    function getHeader()
    {
        $headers = $this->defaultHeaderBuilder();
        return $headers;
    }

    function getCoinPrice()
    {
        $response = $this->client2->get($this->config['price']['url'], [
            'headers' => $this->getHeader(),
        ]);
        $content = $response->getBody()->getContents();

        $dom = HtmlDomParser::str_get_html($content);
        $dom1 = $dom->find('#top_last_rate_change', 0);
        unset($dom);
        if (empty($dom1)) {
            throw new LowLevelException();
        }
        $pricedom = $dom1->find('.price-dl', 0);
        $ratedom = $dom1->children(1);

        $rate = $ratedom->find('.red', 0)->innertext();

        $dollars = $pricedom->find('#currPrice', 0)->innertext();
        $ync = $pricedom->find('#currFiat', 0)->innertext();

        $pricedom->clear();
        $ratedom->clear();
        $dom1->clear();
        unset($pricedom);
        unset($ratedom);
        unset($dom1);
        unset($response);

        return [
            $dollars,
            $ync,
            $rate,
        ];
    }

}
