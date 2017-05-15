<?php

namespace JC\TaskForker;

/**
 * Class Forker
 * @package JC\TaskForker
 */
class Forker
{
    /**
     * Default runtime path
     */
    const RUNTIME_PATH = '/tmp';
    /**
     * Default pidfile extension
     */
    const PIDFILE_EXT = '.pid';
    /**
     * Default max concurrent process to be executed
     */
    const MAX_PROCESS = 1;
    /**
     * @var null
     */
    public $name;
    /**
     * @var
     */
    public $fileExtension;
    /**
     * @var string
     */
    public $runtimePath;
    /**
     * @var
     */
    public $maxProcess;
    /**
     * @var
     */
    protected $pidFile;

    /**
     * Forker constructor.
     * @param null $name
     * @param string $runtimePath
     */
    public function __construct($name = null, $runtimePath = self::RUNTIME_PATH)
    {
        $this->setName($name);
        $this->setRuntimePath($runtimePath);
        $this->setFileExtension();
    }

    /**
     * Sets the task forker unique identification name.
     * @param null $name
     * @return Forker
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Gets the task forker unique identification name.
     * @return null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the max allowed concurrent processes.
     * @param $number
     * @return $this
     */
    public function setMaxProcess($number)
    {
        $this->maxProcess = $number;
        return $this;
    }

    /**
     * Gets the max allowed concurrent processes.
     * @return mixed
     */
    public function getMaxProcess()
    {
        return $this->maxProcess;
    }

    /**
     * Sets the file extension of pidfile.
     * @param string $fileExtension
     * @return $this
     */
    public function setFileExtension($fileExtension = self::PIDFILE_EXT)
    {
        $this->fileExtension = $fileExtension;

        return $this;
    }

    /**
     * Gets file extension of pidfile.
     * @return mixed
     */
    public function getFileExtension()
    {
        return $this->fileExtension;
    }

    /**
     * Sets the runtime path.
     * @param string $runtimePath
     * @return Forker
     */
    public function setRuntimePath($runtimePath)
    {
        $this->runtimePath = $runtimePath;
        return $this;
    }

    /**
     * Gets the runtime path.
     * @return string
     */
    public function getRuntimePath()
    {
        return $this->runtimePath;
    }

    /**
     * Required properties check.
     * @return bool
     */
    private function checkConfig()
    {
        if (is_null($this->getName())) {
            throw new \InvalidArgumentException('Invalid forker name identification');
        }

        return true;
    }

    /**
     * Executes a process if there are any available slot.
     * @param array $params
     */
    public function process($params = [])
    {
        $this->checkConfig();

        $pids = $this->getPidsFromFile();

        if (count($pids) >= $this->maxProcess) {
            $pid = pcntl_waitpid(-1, $status);
            unset($pids[$pid]);
        }

        $pid = pcntl_fork();

        if ($pid) {
            if ($pid < 0) {
                exit(1);
            } else {
                $pids[$pid] = $pid;
            }
        } else {
            $execute = new $params['job']();
            $execute->args = $params;
            $execute->perform();
            usleep(300);
            exit(0);
        }

        $this->setPidsToFile($pids);

        //Todo Needs to check a good way to find out when a child exits
        /*while ($pid1 > 0) {
            $status = pcntl_wexitstatus($status);
            echo "Child $status completed\n";
            $pid1 = pcntl_waitpid(0, $status, WNOHANG);
        }*/
    }

    /**
     * Terminate a child or all children processes.
     * @param bool $pid
     * @return bool
     */
    public function terminate($pid = false)
    {
        if (!$pid) {
            return false;
        }

        $pids = $this->getPidsFromFile();

        if (!$pids) {
            return false;
        }

        if ($pid == 'all') {
            foreach ($pids as $pid) {
                $killedPids[] = $this->killPid($pid);
            }
        } else {
            $killedPids[] = $this->killPid($pid);
        }

        foreach ($killedPids as $killedPid) {
            unset($pids[$killedPid]);
        }

        $this->setPidsToFile($pids);

        return true;
    }

    /**
     * Sends SIGKILL to child pid.
     * @param $pid
     * @return bool
     */
    private function killPid($pid)
    {
        if (!$pid) {
            return false;
        }

        posix_kill($pid, SIGKILL);

        return $pid;
    }

    /**
     * Gets the full pidfile path and name.
     * @return string
     */
    private function getPidFile()
    {
        return $this->getRuntimePath() . '/' . $this->getName() . $this->getFileExtension();
    }

    /**
     * Returns the pid list.
     * @return array
     */
    public function getPidList()
    {
        $pids = $this->getPidsFromFile();

        if (!$pids) {
            return false;
        }

        return $pids;
    }

    /**
     * Prints the pid list.
     * @return bool
     */
    public function showPidList()
    {
        $pids = $this->getPidList();

        if (!$pids) {
            return false;
        }

        print('Pid list:' . PHP_EOL);

        $i = 1;
        foreach ($pids as $pid) {
            print('#' . $i . ' - ' . $pid . PHP_EOL);
            $i++;
        }

        return true;
    }

    /**
     * Gets the pids from pidfile.
     * @return mixed
     */
    private function getPidsFromFile()
    {
        if (!file_exists($this->getPidFile())) {
            return [];
        }

        $pids = unserialize(file_get_contents($this->getPidFile()));

        if (count($pids) == 0) {
            return false;
        }

        return $pids;
    }

    /**
     * Sets the pids in pidfile.
     */
    private function setPidsToFile(array $pids)
    {
        file_put_contents($this->getPidFile(), serialize($pids));
    }

}