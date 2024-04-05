<?php

namespace Mindtwo\LaravelClickUpApi\Commands;

use Illuminate\Console\Command;
use Mindtwo\LaravelClickUpApi\Endpoints\CustomField;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ListCustomFieldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clickup:list-custom-fields {--list= : The list id or mapping key to list the custom fields for.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists the available custom fields for a given list.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $list = $this->option('list');

        if (empty($list)) {
            $list = $this->ask('What is your lists id or mapping key?');
        }

        $listId = config('clickup.mappings.'.$list) ?: $list;

        $response = app(CustomField::class)->show($listId);

        $this->table(
            ['ID', 'Name', 'Type', 'Type Config', 'Date Created', 'Hide From Guests', 'Required'],
            collect($response->json('fields'))->map(function ($customField) {
                return [
                    $customField['id'],
                    $customField['name'],
                    $customField['type'],
                    json_encode($customField['type_config'], JSON_PRETTY_PRINT),
                    $customField['date_created'],
                    var_export($customField['hide_from_guests'], true),
                    var_export($customField['required'], true),
                ];
            })
        );

        return CommandAlias::SUCCESS;
    }
}
