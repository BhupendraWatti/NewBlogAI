<?php

namespace App\Mcp\Tools;

use App\Modules\TopicManager\Models\Topic;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add a new news topic to the database.')]
class AddTopicTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $name = $request->get('name');

        if (empty($name)) {
            return Response::error('Topic name is required.');
        }

        $topic = Topic::updateOrCreate(
            ['name' => $name]
        );

        return Response::text(sprintf("Successfully added topic!\nID: %d\nName: %s", $topic->id, $topic->name));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The name of the topic, e.g. "Artificial Intelligence"')
                ->required(),
        ];
    }
}
