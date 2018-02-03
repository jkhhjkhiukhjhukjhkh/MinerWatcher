<?php
/**
 * Created by PhpStorm.
 * User: william
 * Date: 2018/2/2
 * Time: 11:13
 */

use App\Exception\LoginErrException;
use App\Exception\LowLevelException;
use App\Main\Main;

require_once __DIR__ . '/../vendor/autoload.php';
ini_set('error_reporting', E_ALL);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1G');
ini_set('date.timezone', 'PRC');

$quotes = new \App\Quotes\Quotes($index = new \App\Main\Main());

try {
    $quotes->main();
} catch (LoginErrException $exception) {
    $index->mail($index->messageBuilder(Main::TYPE_LOGIN_ERR, date('Y-m-d H:i:s'), $exception->getMessage()));
} catch (LowLevelException $exception) {
    $index->mail($index->messageBuilder(Main::TYPE_LLVL_ERR, date('Y-m-d H:i:s'), $exception->getMessage()));
} catch (\Exception $exception) {
    $index->mail($index->messageBuilder(Main::TYPE_LLVL_ERR, date('Y-m-d H:i:s'), $exception->getMessage()));
}