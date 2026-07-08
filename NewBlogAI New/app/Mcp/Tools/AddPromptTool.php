<?php

namespace App\Mcp\Tools;

use App\Modules\PromptManager\Models\Prompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add a new prompt template or update an existing prompt template.')]
class AddPromptTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $name = $request->get('name');
        $promptText = $request->get('prompt') ?? $request->get('promt');

        if (empty($name)) {
            return Response::error('Prompt template name is required.');
        }
        if (empty($promptText)) {
            return Response::error('Prompt text is required.');
        }

        $prompt = Prompt::updateOrCreate(
            ['name' => $name],
            ['prompt' => $promptText]
        );

        return Response::text(sprintf(
            "Successfully saved prompt template!\nID: %d\nName: %s\nPrompt text: %s",
            $prompt->id,
            $prompt->name,
            $prompt->prompt
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
            'prompt' => $schema->string()
                ->description('The actual prompt template text')
                ->required(),
        ];
    }
}
