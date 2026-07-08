<?php

namespace App\Mcp\Tools;

use App\Models\Key;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add a new API key or update an existing API key.')]
class AddKeyTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $name = $request->get('name');
        $keyValue = $request->get('key');

        if (empty($name)) {
            return Response::error('Key name is required.');
        }
        if (empty($keyValue)) {
            return Response::error('Key value is required.');
        }

        $key = Key::updateOrCreate(
            ['name' => $name],
            ['key' => $keyValue]
        );

        return Response::text(sprintf(
            "Successfully saved API key!\nID: %d\nName: %s\nKey: %s",
            $key->id,
            $key->name,
            $key->key ? substr($key->key, 0, 4).'***' : 'None'
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
            'name' => $schema->string()
                ->description('Name of the API key, e.g. "Main OpenAI Key"')
                ->required(),
            'key' => $schema->string()
                ->description('The actual API key text')
                ->required(),
        ];
    }
}
