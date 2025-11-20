<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use Mindtwo\LaravelClickUpApi\Http\Endpoints\CustomField;
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
     * Execute the console command.
     *
     * @throws ConnectionException
     */
    public function handle(): int
    {
        /** @var null|string $list */
        $list = $this->option('list');

        if (empty($list)) {
            /** @var string $list */
            $list = $this->ask('What is your lists id or mapping key?');
        }

        $key = sprintf('clickup-api.mappings.%s', $list);
        /** @var int|string $listId */
        $listId = config($key) ?: $list;

        $response = app(CustomField::class)->show($listId);

        /**
         * @var array<string, array<string, int|string>|int|string> $responsePayload Contains 'task', 'dependencies',
         *                                                          'fields', and 'linked_tasks' keys
         */
        $responsePayload = $response->json('fields');

        $this->table(
            ['ID', 'Name', 'Type', 'Type Config', 'Date Created', 'Hide From Guests', 'Required'],
            collect($responsePayload)
                ->map(
                    function (array $customField): array {
                        /** @var array<string, int|string>|int|string */
                        return [
                            $customField['id'],
                            $customField['name'],
                            $customField['type'],
                            json_encode($customField['type_config'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
                            $customField['date_created'],
                            Str::of((string) $customField['hide_from_guests'])->toString(),
                            Str::of((string) $customField['required'])->toString(),
                        ];
                    }
                )
        );

        return CommandAlias::SUCCESS;
    }
}
