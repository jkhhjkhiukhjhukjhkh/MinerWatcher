<?php

/**
 * Created by PhpStorm.
 * User: william
 * Date: 2018/2/2
 * Time: 10:55
 */


namespace App\Quotes;

use App\Main\Main;

class Quotes
{
    /**
     * @var Main
     */
    public $index;

    public function __construct($index)
    {
        $this->index = $index;
    }


    public function main()
    {
        $main = new Main();
        $price = $main->getCoinPrice();
        array_unshift($price, date('Y-m-d H:i:s'));

        $rate = abs($price[3]);

        if ($rate > $this->index->config['mail']['watcherRate']) {
            $main->mail($main->messageBuilder(Main::TYPE_QUOTE_EVENT, ...$price));
        }
    }
}