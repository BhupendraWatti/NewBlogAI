<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddKeyTool;
use App\Mcp\Tools\AddPromtTool;
use App\Mcp\Tools\AddSiteTool;
use App\Mcp\Tools\AddTopicTool;
use App\Mcp\Tools\ListKeysTool;
use App\Mcp\Tools\ListPromtsTool;
use App\Mcp\Tools\ListSitesTool;
use App\Mcp\Tools\ListTopicsTool;
use App\Mcp\Tools\SyncSiteTopicsTool;
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
        ListSitesTool::class,
        AddSiteTool::class,
        SyncSiteTopicsTool::class,
        ListTopicsTool::class,
        AddTopicTool::class,
        ListPromtsTool::class,
        AddPromtTool::class,
        ListKeysTool::class,
        AddKeyTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
