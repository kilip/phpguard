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

use Monolog\Logger;
use PhpGuard\Application\Console\LogHandler as LogerHandler;
use PhpGuard\Application\Console\Shell;
use PhpGuard\Application\Event\EvaluateEvent;
use PhpGuard\Application\Exception\ConfigurationException;
use PhpGuard\Application\Interfaces\ContainerInterface;
use PhpGuard\Application\Interfaces\PluginInterface;
use PhpGuard\Application\Listener\ConfigurationListener;
use PhpGuard\Listen\Adapter\Inotify\InotifyAdapter;
use PhpGuard\Listen\Event\ChangeSetEvent;
use PhpGuard\Application\Listener\ChangesetListener;
use PhpGuard\Listen\Events;
use PhpGuard\Plugins\PhpSpec\PhpSpecPlugin;
use \PhpGuard\Listen\Listen;
use PhpGuard\Plugins\PHPUnit\PHPUnitPlugin;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class PhpGuard
 *
 */
class PhpGuard
{
    const VERSION = '1.0.0-dev';

    private $watchers = array();

    /**
     * @var Container
     */
    private $container;

    private $options;

    public function addWatcher($definition)
    {
        $watcher = new Watcher();
        foreach($definition as $name => $value){
            if(!$watcher->hasOption($name)){
                throw new ConfigurationException(sprintf(
                    'Watcher do not have "%s" configuration.',
                    $name
                ));
            }
            call_user_func(array($watcher,'setOption'),$name,$value);

        }
        $this->watchers[] = $watcher;
        return $watcher;
    }

    public function getWatchers()
    {
        return $this->watchers;
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    public function setupServices(ContainerInterface $container)
    {
        $container->set('phpguard',$this);

        $container->setShared('phpguard.config',function(){
            return new Configuration();
        });

        $container->set('phpguard.ui.output',new ConsoleOutput());

        $container->setShared('phpguard.dispatcher', function ($c) {
            $dispatcher = new EventDispatcher;

            array_map(
                array($dispatcher, 'addSubscriber'),
                $c->getByPrefix('phpguard.dispatcher.listeners')
            );

            return $dispatcher;
        });

        $container->setShared('phpguard.logger.handler',function($c){
            $handler = new Console\LogHandler($c->getParameter('phpguard.log_level'));
            $handler->setLevel(LogLevel::DEBUG);
            return $handler;
        });

        $container->setShared('phpguard.logger',function($c){
            $logger = new Logger('PhpGuard');
            $logger->pushHandler($c->get('phpguard.logger.handler'));
            return $logger;
        });

        $container->setShared('phpguard.dispatcher.listeners.config',function($c){
            return new ConfigurationListener();
        });

        $container->setShared('phpguard.dispatcher.listeners.changeset',function($c){
            return new ChangesetListener();
        });

        $container->setShared('phpguard.ui.shell',function($c){
            $shell = new Shell($c);
            return $shell;
        });
        $this->container = $container;
    }

    public function setupListen()
    {
        $container = $this->container;
        $container->setShared('phpguard.listen.adapter',function($c){
            return Listen::getDefaultAdapter();
        });

        $container->setShared('phpguard.listen.listener',function($c){
            $listener = Listen::to(getcwd());
            $listener
                //->setLogger($c->get('phpguard.logger'))
                ->callback(array($c->get('phpguard'),'listen'))
            ;
            return $listener;
        });
    }

    public function loadPlugins()
    {
        $this->container->setShared('phpguard.plugins.phpspec',function(){
            return new PhpSpecPlugin();
        });
        $this->container->setShared('phpguard.plugins.phpunit',function(){
            return new PHPUnitPlugin();
        });
    }

    public function loadConfiguration()
    {
        $event = new GenericEvent($this);
        $dispatcher = $this->container->get('phpguard.dispatcher');
        $dispatcher->dispatch(PhpGuardEvents::CONFIG_PRE_LOAD,$event);

        $this->container->get('phpguard.config')
            ->compileFile(getcwd().'/phpguard.yml')
        ;
        $dispatcher->dispatch(PhpGuardEvents::CONFIG_POST_LOAD,$event);
    }

    public function start()
    {
        /* @var \PhpGuard\Listen\Listener */
        $listener = $this->container->get('phpguard.listen.listener');
        if(isset($this->options['ignores'])){
            foreach($this->options['ignores'] as $ignored){
                $listener->ignores($ignored);
            }
        }

        $this->log('<info>Starting to watch at <comment>{path}</comment></info>',array('path'=>getcwd()));
        $listener->start();
    }

    public function listen(ChangeSetEvent $event)
    {
        $this->getContainer()->get('phpguard.dispatcher')
            ->dispatch(PhpGuardEvents::POST_EVALUATE,new EvaluateEvent($event));
    }

    public function setOptions(array $options=array())
    {
        $resolver = new OptionsResolver();
        $this->setDefaultOptions($resolver);

        $this->options = $resolver->resolve($options);
    }

    private function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'ignores' => array(),
        ));

        $resolver->setNormalizers(array(
            'ignores' => function($value){
                    if(!is_array($value)){
                        return array($value);
                    }
            }
        ));
    }

    public function log($message, $context=array(),$level = LogLevel::INFO)
    {
        /* @var \Psr\Log\LoggerInterface $logger */
        $logger = $this->container->get('phpguard.logger');
        $logger->log($level,$message,$context);
    }
}