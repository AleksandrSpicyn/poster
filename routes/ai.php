<?php

declare(strict_types=1);

use AleksandrSpicyn\ScribeMcp\Servers\ApiDocumentationServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('scribe', ApiDocumentationServer::class);
