<?php

use AleksandrSpicyn\ScribeMcp\Servers\ApiDocumentationServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('scribe', ApiDocumentationServer::class);
