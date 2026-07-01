<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Blog Server')]
#[Version('1.0.0')]
#[Instructions('This server manages WordPress sites, topics, prompts, and API keys for the NewBlogAI system.')]
class BlogServer extends Server
{
    protected array $tools = [
        \App\Mcp\Tools\ListSitesTool::class,
        \App\Mcp\Tools\AddSiteTool::class,
        \App\Mcp\Tools\SyncSiteTopicsTool::class,
        \App\Mcp\Tools\ListTopicsTool::class,
        \App\Mcp\Tools\AddTopicTool::class,
        \App\Mcp\Tools\ListPromtsTool::class,
        \App\Mcp\Tools\AddPromtTool::class,
        \App\Mcp\Tools\ListKeysTool::class,
        \App\Mcp\Tools\AddKeyTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
