<?php

declare(strict_types=1);

use AleksandrSpicyn\ScribeMcp\Servers\ApiDocumentationServer;
use AleksandrSpicyn\ScribeMcp\Tools\GetApiEndpointTool;
use AleksandrSpicyn\ScribeMcp\Tools\SearchApiEndpointsTool;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\PendingCommand;
use Symfony\Component\Yaml\Yaml;
use function Pest\Laravel\artisan;

test('Scribe генерирует контракты без HTTP и MCP возвращает тот же endpoint', function (): void {
    Http::fake();

    $command = artisan('scribe:generate');
    expect($command)->toBeInstanceOf(PendingCommand::class);
    assert($command instanceof PendingCommand);
    $command->assertSuccessful();

    Http::assertNothingSent();
    $spec = Yaml::parseFile(config()->string('scribe-mcp.openapi_path'));
    expect(data_get($spec, 'components.securitySchemes.default.in'))->toBe('cookie')
        ->and(data_get($spec, 'paths./posts.get.responses.200.content.application/json.schema.properties.component.type'))->toBe('string')
        ->and(data_get($spec, 'paths./posts.get.responses.401.content.application/json.schema.properties.message.type'))->toBe('string')
        ->and(data_get($spec, 'paths./api/webhook/suno.post.security'))->toBe([])
        ->and(data_get($spec, 'paths./api/webhook/suno.post.responses'))->toHaveKeys([200, 422]);

    ApiDocumentationServer::tool(SearchApiEndpointsTool::class, ['query' => 'suno'])
        ->assertOk()->assertStructuredContent(fn(AssertableJson $json): AssertableJson => $json
        ->where('endpoints.0.id', 'POST /api/webhook/suno')->where('endpoints.0.authenticated', false)->etc());
    ApiDocumentationServer::tool(GetApiEndpointTool::class, ['endpoint_id' => 'GET /posts'])
        ->assertOk()->assertStructuredContent(fn(AssertableJson $json): AssertableJson => $json
        ->where('endpoint.operation', data_get($spec, 'paths./posts.get'))->has('document.components')->etc());
});

test('MCP сообщает об отсутствующем артефакте без запросов к API', function (): void {
    Http::fake();
    config(['scribe-mcp.openapi_path' => storage_path('missing-openapi.yaml')]);

    ApiDocumentationServer::tool(SearchApiEndpointsTool::class)->assertHasErrors(['не найден']);

    Http::assertNothingSent();
});
