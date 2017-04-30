<?php

namespace Anyuzhe\LaravelFunctionFlow\Console;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * A command to generate autocomplete information for your IDE
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */
class GeneratorCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'funcFlow:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new config file.';

    public function handle()
    {
        $pack_file = dirname(__FILE__).'/../Config/function-flow.php';
        copy($pack_file,app()->configPath().'/function-flow.php');
        $this->info('config/function-flow.php is created');
    }
}
