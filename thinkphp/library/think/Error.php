<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://zjzit.cn>
// +----------------------------------------------------------------------

namespace think;

use think\console\Output as ConsoleOutput;
use think\exception\ErrorException;
use think\exception\Handle;
use think\exception\ThrowableError;

class Error
{
    /**
     * 注册异常处理
     * @access public
     * @return void
     */
    public static function register()
    {
        error_reporting(E_ALL);
        set_error_handler([__CLASS__, 'appError']);  // 设置用户的函数 (error_handler) 来处理脚本中出现的错误。
        set_exception_handler([__CLASS__, 'appException']);  // 设置默认的异常处理程序
        register_shutdown_function([__CLASS__, 'appShutdown']);  // 注册一个 callback ，它会在脚本执行完成或者 exit() 后被调用。
    }

    /**
     * 异常处理
     * @access public
     * @param  \Exception|\Throwable $e 异常
     * @return void
     */
    public static function appException($e)
    {        //用于确定一个 PHP 变量是否属于某一类 class 的实例：
        if (!$e instanceof \Exception) {
            $e = new ThrowableError($e);
        }

        // 获取异常处理对象
        $handler = self::getExceptionHandler();

        // 记录日志
        $handler->report($e);

        if (IS_CLI) {
            $handler->renderForConsole(new ConsoleOutput, $e);
        } else {
            // 返回信息掉到客户端
            $handler->render($e)->send();
        }
    }

    /**
     * 错误处理
     * @access public
     * @param  integer $errno      错误编号
     * @param  integer $errstr     详细错误信息
     * @param  string  $errfile    出错的文件
     * @param  integer $errline    出错行号
     * @return void
     * @throws ErrorException
     */
    public static function appError($errno, $errstr, $errfile = '', $errline = 0)
    {
        $exception = new ErrorException($errno, $errstr, $errfile, $errline);

        // 符合异常处理的则将错误信息托管至 think\exception\ErrorException
        if (error_reporting() & $errno) {
            throw $exception;
        }

        self::getExceptionHandler()->report($exception);
    }

    /**
     * 异常中止处理
     * @access public
     * @return void
     */
    public static function appShutdown()
    {
        // 将错误信息托管至 think\ErrorException
                    // 获取最后发生的错误
        if (!is_null($error = error_get_last()) && self::isFatal($error['type'])) {
            self::appException(new ErrorException(
                $error['type'], $error['message'], $error['file'], $error['line']
            ));
        }

        // 写入日志
        Log::save();
    }

    /**
     * 确定错误类型是否致命
     * @access protected
     * @param  int $type 错误类型
     * @return bool
     */
    protected static function isFatal($type)
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }

    /**
     * 获取异常处理的实例
     * @access public
     * @return Handle
     */

    // 默认获取think\exception\Handle 异常处理对象
    // 可以在config.php 中修改  exception_handle 的值

    public static function getExceptionHandler()
    {
        static $handle;

        if (!$handle) {
            // 异常处理 handle
            $class = Config::get('exception_handle');

            if ($class && is_string($class) && class_exists($class) &&
                //如果此对象是该类的子类
                is_subclass_of($class, "\\think\\exception\\Handle")
            ) {
                $handle = new $class;
            } else {
                $handle = new Handle;

                if ($class instanceof \Closure) {
                    $handle->setRender($class);
                }

            }
        }

        return $handle;
    }
}