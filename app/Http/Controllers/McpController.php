<?php

namespace App\Http\Controllers;

use App\Curation\CurationTools;
use App\Tenancy\TenantContext;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Http\Request;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Symfony\Component\HttpFoundation\Response;

class McpController extends Controller
{
    public function __invoke(Request $request, CurationTools $tools, TenantContext $tenant): Response
    {
        $server = Server::builder()
            ->setServerInfo('Timeline Curator', '0.3.0')
            ->setSession(new FileSessionStore(
                rtrim((string) config('mcp.session_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$tenant->id(),
                (int) config('mcp.session_ttl'),
            ))
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
                    'stories' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 10,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'client_item_id' => ['type' => 'string', 'maxLength' => 128],
                                'title' => ['type' => 'string', 'maxLength' => 255],
                                'summary_points' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string', 'maxLength' => 600],
                                    'minItems' => 1,
                                    'maxItems' => 6,
                                ],
                                'technical_bullets' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string', 'maxLength' => 600],
                                    'minItems' => 1,
                                    'maxItems' => 6,
                                    'description' => 'Deprecated compatibility alias for summary_points.',
                                ],
                                'why_it_matters' => ['type' => ['string', 'null'], 'maxLength' => 1200],
                                'sources' => [
                                    'type' => 'array',
                                    'minItems' => 1,
                                    'maxItems' => 5,
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'title' => ['type' => 'string', 'maxLength' => 255],
                                            'url' => ['type' => 'string', 'format' => 'uri'],
                                            'role' => ['type' => 'string', 'enum' => ['primary', 'supporting']],
                                            'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                                        ],
                                        'required' => ['title', 'url', 'role'],
                                    ],
                                ],
                                'media' => [
                                    'type' => 'array',
                                    'maxItems' => 3,
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'type' => ['type' => 'string', 'enum' => ['image', 'video']],
                                            'url' => ['type' => 'string', 'format' => 'uri'],
                                            'thumbnail_url' => ['type' => ['string', 'null'], 'format' => 'uri'],
                                            'caption' => ['type' => 'string', 'maxLength' => 500],
                                            'alt_text' => ['type' => 'string', 'maxLength' => 500],
                                            'credit' => ['type' => 'string', 'maxLength' => 255],
                                            'source_url' => ['type' => 'string', 'format' => 'uri'],
                                        ],
                                        'required' => ['type', 'url', 'caption', 'alt_text', 'credit', 'source_url'],
                                    ],
                                ],
                                'feedback_tags' => [
                                    'type' => 'array',
                                    'minItems' => 4,
                                    'maxItems' => 6,
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'string', 'maxLength' => 64],
                                            'label' => ['type' => 'string', 'maxLength' => 48],
                                            'signal' => [
                                                'type' => 'string',
                                                'enum' => [
                                                    'more_like_this', 'less_like_this',
                                                    'good_source', 'bad_source',
                                                    'useful_depth', 'wrong_depth',
                                                    'timely', 'stale',
                                                    'novel', 'already_known',
                                                    'accessible', 'inaccessible',
                                                ],
                                            ],
                                        ],
                                        'required' => ['id', 'label', 'signal'],
                                    ],
                                ],
                            ],
                            'required' => ['client_item_id', 'title', 'sources'],
                        ],
                    ],
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
