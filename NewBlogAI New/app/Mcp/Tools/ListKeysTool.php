<?php

namespace App\Mcp\Tools;

use App\Models\keys;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get list of all API keys configured in the database.')]
class ListKeysTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $keys = keys::all();

        if ($keys->isEmpty()) {
            return Response::text('No API keys are currently configured.');
        }

        $formatted = "Configured API Keys:\n";
        foreach ($keys as $key) {
            $formatted .= sprintf(
                "- ID: %d | Name: %s | Key: %s\n",
                $key->id,
                $key->name,
                $key->key ? substr($key->key, 0, 4).'***' : 'None'
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
