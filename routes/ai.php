<?php

use App\Http\Middleware\AuthenticateCmsMcp;
use App\Http\Middleware\RejectUnexpectedMcpOrigin;
use App\Http\Middleware\ResolveTenant;
use App\Mcp\Servers\CmsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/cms', CmsServer::class)->middleware([
    RejectUnexpectedMcpOrigin::class,
    ResolveTenant::class,
    AuthenticateCmsMcp::class,
    'throttle:60,1',
]);
