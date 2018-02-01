<?php
/**
 * Created by PhpStorm.
 * User: william
 * Mail：tzh.wu.qq.com
 */

namespace App;


use App\Exception\LoginErrException;
use App\Exception\LowLevelException;
use App\Main\Main;

require_once __DIR__ . '/../vendor/autoload.php';


ini_set('error_reporting', E_ALL);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1G');

$index = new Main();

try {
    $index->main();
} catch (LoginErrException $exception) {
    $this->mail($this->messageBuilder(Main::TYPE_LOGIN_ERR, date('Y-m-d H:i:s'), $exception->getMessage()));
} catch (LowLevelException $exception) {
    $this->mail($this->messageBuilder(Main::TYPE_LLVL_ERR, date('Y-m-d H:i:s'), $exception->getMessage()));
} catch (\Exception $exception) {
    $this->mail($this->messageBuilder(Main::TYPE_LLVL_ERR, date('Y-m-d H:i:s'), $exception->getMessage()));
}