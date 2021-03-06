<?php

namespace spec\PhpGuard\Application;

require_once __DIR__.'/MockFileSystem.php';

use PhpGuard\Application\PhpGuard;
use PhpGuard\Application\Interfaces\ContainerInterface;
use PhpGuard\Application\Interfaces\PluginInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

use spec\PhpGuard\Application\MockFileSystem as mfs;

class ConfigurationSpec extends ObjectBehavior
{
    function let(ContainerInterface $container,PhpGuard $guard, PluginInterface $plugin)
    {
        mfs::cleanDir(mfs::$tmpDir);
        mfs::mkdir(mfs::$tmpDir);
        $container->get('phpguard')
            ->willReturn($guard)
        ;
        $this->setContainer($container);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpGuard\Application\Configuration');
    }

    function it_throws_when_configuration_file_not_exist()
    {
        $this
            ->shouldThrow('RuntimeException')
            ->duringCompileFile('foo.yml');
    }

    function it_should_process_global_section(ContainerInterface $container,PhpGuard $guard)
    {
        $guard->setOptions(array(
                'ignores' => 'app/cache'
            ))
            ->shouldBeCalled()
        ;

        $text = <<<EOF
phpguard:
    ignores: app/cache
EOF;
        $this->compile($text);
    }

    function it_should_process_plugin_section(ContainerInterface $container, PluginInterface $pspec, PluginInterface $plugin)
    {
        $container->has('phpguard.plugins.phpspec')
            ->willReturn(true)
        ;
        $container->get('phpguard.plugins.phpspec')
            ->willReturn($plugin)
        ;

        $plugin->setOptions(array(
            'all_on_success'=>true,
            'formatter' => 'progress',
            'ansi'=>true
        ))
            ->shouldBeCalled()
        ;
        $plugin->addWatcher(Argument::any())->shouldBeCalled();

        $text = <<<EOF

phpspec:
    options:
        all_on_success: true
        formatter: progress
        ansi: true
    watch:
        - { pattern: "#^spec\/.*\.php$#" }
        - { pattern: "#^src\/.*\.php$#" }

EOF;
        touch($file = mfs::$tmpDir.'/test.yml');
        file_put_contents($file,$text,LOCK_EX);

        $this->compileFile($file);
    }

    function it_throws_when_plugin_not_exists(
        ContainerInterface $container
    )
    {
        $container->has('phpguard.plugins.some')
            ->willReturn(false);

        $text = <<<EOF
some:
    watch:
        - { pattern: "#^spec\/.*\.php" }
EOF;

        $this->shouldThrow('InvalidArgumentException')
            ->duringCompile($text)
        ;
    }
}