<?php

declare(strict_types=1);

namespace Mindtwo\LaravelClickUpApi\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use JsonException;
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
         * @var array<int, array<string, mixed>> $responsePayload
         */
        $responsePayload = $response->json('fields');

        $this->table(
            ['ID', 'Name', 'Type', 'Type Config', 'Date Created', 'Hide From Guests', 'Required'],
            collect($responsePayload)
                ->map(
                    /**
                     * @param array<string, mixed> $customField
                     *
                     * @throws JsonException
                     *
                     * @return array<int, string>
                     */
                    function (array $customField): array {

                        /** @var bool $hideFromGuests */
                        $hideFromGuests = $customField['hide_from_guests'] ?? false;

                        /** @var bool $required */
                        $required = $customField['required'] ?? false;

                        return [
                            $customField['id'],
                            $customField['name'],
                            $customField['type'],
                            json_encode($customField['type_config'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
                            $customField['date_created'],
                            Str::of((string) $hideFromGuests)->toString(),
                            Str::of((string) $required)->toString(),
                        ];
                    }
                )
        );

        return CommandAlias::SUCCESS;
    }
}
