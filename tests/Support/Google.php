<?php

declare(strict_types=1);

namespace Tests\Support;

use Google\Client;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;

use function Pest\Laravel\instance;

function fakeGoogleClient(ResponseInterface ...$responses): MockHandler
{
    $handler = new MockHandler(array_values($responses));
    $client = new Client();
    $client->setHttpClient(new HttpClient(['handler' => HandlerStack::create($handler), 'http_errors' => false]));
    instance(Client::class, $client);

    return $handler;
}
