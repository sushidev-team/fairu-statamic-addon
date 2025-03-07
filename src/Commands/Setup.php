<?php

namespace SushidevTeam\Fairu\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Statamic\Console\RunsInPlease;
use SushidevTeam\Fairu\Services\Fairu as ServicesFairu;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class Setup extends Command
{
    use RunsInPlease;

    protected $signature = 'fairu:setup';

    protected $description = 'Step by step wizard to bring your files to fairu';

    public function handle(): void
    {
        $this->checkConnection();
        $this->importFiles();
    }

    protected function checkConnection():void {

        $connection = select(
            label: 'What connection do you want to use?',
            options: array_keys(config('fairu.connections')),
            default: 'default'
        );

        $credentials = config('fairu.connections.'.$connection);

        $result = (new ServicesFairu($connection))->scope();

        if ($result == null){
            $this->error('Cannot check the given credentials. Please make sure you have set the environment keys "FAIRU_TENANT" and "FAIRU_TENANT_SECRET" or that you defined your custom connection in the configuration.');
        }

        $this->info("Your api keys are valid and we could recieve the following user data associated with the api.");

        $this->table(['id', 'email'], [Arr::only($result, ['id', 'email'])]);

        confirm("Want to continue with the import of your files?");

    }

    protected function importFiles(){

        // TODO: write a function that checks if the asset container is 
        // an s3 bucket of plain statamic flat file system.

    }

}