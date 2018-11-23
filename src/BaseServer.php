<?php

namespace SMProxy;

use SMProxy\Helper\ProcessHelper;
use SMProxy\MysqlPacket\ErrorPacket;
use Swoole\Coroutine;

/**
 * Author: Louis Livi <574747417@qq.com>
 * Date: 2018/10/26
 * Time: 下午5:40.
 */
abstract class BaseServer extends Base
{
    protected $connectReadState = [];
    protected $connectHasTransaction = [];
    protected $connectHasAutoCommit = [];
    protected $server;

    /**
     * BaseServer constructor.
     *
     * @throws \ErrorException
     */
    public function __construct()
    {
        try {
            if (!(CONFIG['server']['swoole'] ?? false)) {
                throw new SMProxyException('config [swoole] is not found !');
            }
            if ((CONFIG['server']['port'] ?? false)) {
                $ports = explode(',', CONFIG['server']['port']);
            } else {
                $ports = [3366];
            }
            $this->server = new \swoole_server(CONFIG['server']['host'] ?? '0.0.0.0',
                $ports[0], CONFIG['server']['mode'], CONFIG['server']['sock_type']);
            if (count($ports) > 1) {
                for ($i = 1; $i < count($ports); ++$i) {
                    $this->server->addListener(CONFIG['server']['host'] ?? '0.0.0.0',
                        $ports[$i], CONFIG['server']['sock_type']);
                }
            }
            $this->server->set(CONFIG['server']['swoole']);
            $this->server->on('connect', [$this, 'onConnect']);
            $this->server->on('receive', [$this, 'onReceive']);
            $this->server->on('close', [$this, 'onClose']);
            $this->server->on('start', [$this, 'onStart']);
            $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
            $this->server->on('ManagerStart', [$this, 'onManagerStart']);
            $result = $this->server->start();
            if ($result) {
                print_r('server start success!'."\n");
            } else {
                print_r('server start error!'."\n");
            }
        } catch (\Swoole\Exception $exception) {
            print_r('ERROR:'.$exception->getMessage()."\n");
        } catch (\ErrorException $exception) {
            print_r('ERROR:'.$exception->getMessage()."\n");
        } catch (SMProxyException $exception) {
            print_r('ERROR:'.$exception->errorMessage()."\n");
        }
    }

    protected function onConnect(\swoole_server $server, int $fd)
    {
    }

    protected function onReceive(\swoole_server $server, int $fd, int $reactor_id, string $data)
    {
    }

    protected function onWorkerStart(\swoole_server $server, int $worker_id)
    {
    }

    public function onStart(\swoole_server $server)
    {
        \file_put_contents(CONFIG['server']['swoole']['pid_file'], $server->master_pid . ',' . $server->manager_pid);
        ProcessHelper::setProcessTitle('SMProxy master process');
    }

    public function onManagerStart(\swoole_server $server)
    {
        ProcessHelper::setProcessTitle('SMProxy manager process');
    }

    /**
     * 关闭连接 销毁携程变量.
     *
     * @param $server
     * @param $fd
     */
    protected function onClose(\swoole_server $server, int $fd)
    {
        $cid = Coroutine::getuid();
        if ($cid > 0 && isset(self::$pool[$cid])) {
            unset(self::$pool[$cid]);
        }
    }

    protected function writeErrMessage(int $id, String $msg, int $errno = 0)
    {
        $err = new ErrorPacket();
        $err->packetId = $id;
        if ($errno) {
            $err->errno = $errno;
        }
        $err->message = array_iconv($msg);

        return $err->write();
    }
}
