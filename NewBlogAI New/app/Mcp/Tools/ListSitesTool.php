<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use App\Modules\SiteManager\Models\Site;

#[Description('Get list of all WordPress sites monitored by the NewBlogAI system.')]
class ListSitesTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $sites = Site::all();

        if ($sites->isEmpty()) {
            return Response::text('No WordPress sites are currently registered.');
        }

        $formatted = "Registered WordPress Sites:\n";
        foreach ($sites as $site) {
            $formatted .= sprintf(
                "- ID: %d | URL: %s | Slot: %s | API Key: %s | Selected Topics: %s\n",
                $site->id,
                $site->domain_url,
                $site->slot ?? 'Not Set',
                $site->api_key ? substr($site->api_key, 0, 4) . '***' : 'None',
                is_array($site->selected_topics) ? implode(', ', array_column($site->selected_topics, 'topic')) : 'None'
            );
        }

        return Response::text($formatted);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
