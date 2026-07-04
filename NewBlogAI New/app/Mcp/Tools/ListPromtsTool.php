<?php

namespace App\Mcp\Tools;

use App\Models\Promt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get list of all prompt templates available in the database.')]
class ListPromtsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $prompts = Promt::all();

        if ($prompts->isEmpty()) {
            return Response::text('No prompt templates are currently configured.');
        }

        $formatted = "Configured Prompt Templates:\n";
        foreach ($prompts as $prompt) {
            $formatted .= sprintf(
                "- ID: %d | Name: %s | Prompt: %s\n",
                $prompt->id,
                $prompt->name,
                $prompt->promt
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
