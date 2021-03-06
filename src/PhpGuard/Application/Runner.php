<?php

namespace PhpGuard\Application;

/*
 * This file is part of the PhpGuard project.
 *
 * (c) Anthonius Munthi <me@itstoni.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Class Runner
 *
 */
class Runner
{
    /**
     * @var string
     */
    private $command;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @param array $arguments
     * @return Runner
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param string $command
     *
     * @return Runner
     */
    public function setCommand($command)
    {
        $this->command = $command;
        return $this;
    }

    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return Runner
     */
    public function setOutput($output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @return bool
     * @codeCoverageIgnore
     */
    public function run()
    {
        $command = $this->command;
        if(is_file($executable='./vendor/bin/'.$command)){
            $command = $executable;
        }
        elseif(is_file($executable='/bin/'.$command)){
            $command = $executable;
        }

        $arguments = $command.' '.implode(' ',$this->arguments);

        passthru($arguments,$return);

        if($return===0){
            return true;
        }else{
            return false;
        }
    }
}