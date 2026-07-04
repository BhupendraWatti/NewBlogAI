<?php

namespace App\Mcp\Tools;

use App\Models\Topic;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get list of all news topics configured in the database.')]
class ListTopicsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $topics = Topic::all();

        if ($topics->isEmpty()) {
            return Response::text('No topics are currently configured.');
        }

        $formatted = "Configured Topics:\n";
        foreach ($topics as $topic) {
            $formatted .= sprintf("- ID: %d | Name: %s\n", $topic->id, $topic->name);
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
