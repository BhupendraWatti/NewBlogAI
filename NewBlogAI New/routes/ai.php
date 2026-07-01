<?php

use Laravel\Mcp\Facades\Mcp;
use App\Mcp\Servers\BlogServer;

Mcp::web('/mcp/blog', BlogServer::class);
Mcp::local('blog', BlogServer::class);
