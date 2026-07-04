<?php

namespace App\Mcp\Tools;

use App\Modules\SiteManager\Models\Site;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Register a new WordPress site or update an existing site configuration.')]
class AddSiteTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $domainUrl = rtrim($request->get('domain_url'), '/');
        $apiKey = $request->get('api_key');
        $slot = $request->get('slot', '12:00');
        $promtId = $request->get('promt_id');
        $topics = $request->get('topics', []);

        $selectedTopics = [];
        foreach ($topics as $topic) {
            $selectedTopics[] = [
                'topic' => $topic,
                'promt_id' => $promtId,
            ];
        }

        $site = Site::updateOrCreate(
            ['domain_url' => $domainUrl],
            [
                'api_key' => $apiKey,
                'slot' => $slot,
                'promt_id' => $promtId,
                'selected_topics' => $selectedTopics,
            ]
        );

        return Response::text(sprintf(
            "Successfully saved site!\nID: %d\nURL: %s\nSlot: %s\nPrompt ID: %s\nTopics Count: %d",
            $site->id,
            $site->domain_url,
            $site->slot,
            $site->promt_id ?? 'None',
            count($selectedTopics)
        ));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain_url' => $schema->string()
                ->description('The target WordPress site root URL, e.g. https://myblog.com')
                ->required(),
            'api_key' => $schema->string()
                ->description('API Key or Key ID to authorize sync requests')
                ->required(),
            'slot' => $schema->string()
                ->description('The scheduled execution slot, e.g. "12:00"')
                ->default('12:00'),
            'promt_id' => $schema->integer()
                ->description('Associated prompt configuration ID'),
            'topics' => $schema->array()
                ->description('List of topic names to assign to the site'),
        ];
    }
}
