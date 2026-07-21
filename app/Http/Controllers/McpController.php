<?php

namespace App\Http\Controllers;

use App\Curation\CurationTools;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Http\Request;
use Mcp\Server;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Symfony\Component\HttpFoundation\Response;

class McpController extends Controller
{
    public function __invoke(Request $request, CurationTools $tools): Response
    {
        $server = Server::builder()
            ->setServerInfo('Timeline Curator', '0.1.0')
            ->addTool([$tools, 'getCurationContext'], 'get_curation_context', description: 'Retrieve the authenticated user’s current topics, directives, feedback policy, and context version.', inputSchema: ['type' => 'object'])
            ->addTool([$tools, 'beginCurationRun'], 'begin_curation_run', description: 'Start a tenant-scoped curation run and record its exact search queries.', inputSchema: [
                'type' => 'object',
                'properties' => [
                    'context_version' => ['type' => 'string'],
                    'exact_queries' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 20],
                    'skill_version' => ['type' => ['string', 'null']],
                ],
                'required' => ['context_version', 'exact_queries'],
            ])
            ->addTool([$tools, 'submitStoryBatch'], 'submit_story_batch', description: 'Validate and publish up to ten evidence-backed story clusters for an active run.', inputSchema: [
                'type' => 'object',
                'properties' => [
                    'run_id' => ['type' => 'string'],
                    'context_version' => ['type' => 'string'],
                    'stories' => ['type' => 'array', 'items' => ['type' => 'object'], 'minItems' => 1, 'maxItems' => 10],
                ],
                'required' => ['run_id', 'context_version', 'stories'],
            ])
            ->addTool([$tools, 'completeCurationRun'], 'complete_curation_run', description: 'Finalize an active curation run as completed, empty, or failed.', inputSchema: [
                'type' => 'object',
                'properties' => [
                    'run_id' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['completed', 'completed_empty', 'failed']],
                ],
                'required' => ['run_id'],
            ])
            ->build();

        $psrRequest = new ServerRequest(
            $request->method(),
            $request->fullUrl(),
            $request->headers->all(),
            $request->getContent(),
            $request->getProtocolVersion(),
        );
        $psrResponse = $server->run(new StreamableHttpTransport($psrRequest, middleware: [
            new CorsMiddleware,
            new DnsRebindingProtectionMiddleware(config('mcp.allowed_hosts')),
            new ProtocolVersionMiddleware,
        ]));

        return response((string) $psrResponse->getBody(), $psrResponse->getStatusCode(), $psrResponse->getHeaders());
    }
}
