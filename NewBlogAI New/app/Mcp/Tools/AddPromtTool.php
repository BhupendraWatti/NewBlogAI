<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use App\Models\Promt;

#[Description('Add a new prompt template or update an existing prompt template.')]
class AddPromtTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $name = $request->get('name');
        $promptText = $request->get('promt');

        if (empty($name)) {
            return Response::error('Prompt template name is required.');
        }
        if (empty($promptText)) {
            return Response::error('Prompt text is required.');
        }

        $promt = Promt::updateOrCreate(
            ['name' => $name],
            ['promt' => $promptText]
        );

        return Response::text(sprintf(
            "Successfully saved prompt template!\nID: %d\nName: %s\nPrompt text: %s",
            $promt->id,
            $promt->name,
            $promt->promt
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
                ->description('The name of the prompt template, e.g. "Blog Post Generator"')
                ->required(),
            'promt' => $schema->string()
                ->description('The actual prompt template text')
                ->required(),
        ];
    }
}
