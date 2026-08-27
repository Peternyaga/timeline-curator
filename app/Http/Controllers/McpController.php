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
            ->setServerInfo('Timeline Curator', '0.5.0')
            ->setSession(new FileSessionStore(
                rtrim((string) config('mcp.session_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$tenant->id(),
                (int) config('mcp.session_ttl'),
            ))
            ->addTool([$tools, 'getCurationContext'], 'get_curation_context', description: 'Retrieve the authenticated user’s current topics, directives, feedback policy, plugin update status, and context version.', inputSchema: [
                'type' => 'object',
                'properties' => [
                    'plugin_version' => ['type' => ['string', 'null'], 'maxLength' => 100],
                ],
            ])
            ->addTool([$tools, 'beginCurationRun'], 'begin_curation_run', description: 'Start a tenant-scoped curation run and record its exact search queries.', inputSchema: [
                'type' => 'object',
                'properties' => [
                    'context_version' => ['type' => 'string'],
                    'exact_queries' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 20],
                    'job_queries' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 20],
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
            ->addTool([$tools, 'submitJobBatch'], 'submit_job_batch', description: 'Validate and publish up to ten current, evidence-backed job matches for an active combined run.', inputSchema: $this->jobBatchSchema())
            ->addTool([$tools, 'claimNextApplication'], 'claim_next_application', description: 'Atomically claim one user-approved job application for 30 minutes.', inputSchema: [
                'type' => 'object',
                'properties' => ['client_attempt_id' => ['type' => 'string', 'maxLength' => 128]],
                'required' => ['client_attempt_id'],
            ])
            ->addTool([$tools, 'getProfileDocument'], 'get_profile_document', description: 'Retrieve one private profile document included in the approved application snapshot.', inputSchema: $this->claimResourceSchema(['document_id']))
            ->addTool([$tools, 'saveApplicationMaterials'], 'save_application_materials', description: 'Retain the exact truthful materials generated for a claimed application.', inputSchema: [
                'type' => 'object',
                'properties' => [
                    'application_id' => ['type' => 'string'],
                    'claim_token' => ['type' => 'string'],
                    'materials' => [
                        'type' => 'array', 'minItems' => 1, 'maxItems' => 5,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'kind' => ['type' => 'string', 'enum' => ['resume', 'cover_letter', 'email_body', 'supporting', 'answer_attachment']],
                                'filename' => ['type' => ['string', 'null']],
                                'mime_type' => ['type' => 'string'],
                                'encoding' => ['type' => 'string', 'enum' => ['utf8', 'base64']],
                                'content' => ['type' => 'string'],
                                'fact_paths' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
                            ],
                            'required' => ['kind', 'content', 'fact_paths'],
                        ],
                    ],
                ],
                'required' => ['application_id', 'claim_token', 'materials'],
            ])
            ->addTool([$tools, 'requestApplicationInformation'], 'request_application_information', description: 'Pause a claimed application and create a safe server-rendered form for missing facts.', inputSchema: $this->questionnaireSchema())
            ->addTool([$tools, 'recordApplicationOutcome'], 'record_application_outcome', description: 'Record a confirmed, unconfirmed, blocked, or failed application outcome.', inputSchema: [
                'type' => 'object',
                'properties' => [
                    'application_id' => ['type' => 'string'], 'claim_token' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['submitted', 'attempted_unconfirmed', 'needs_manual_action', 'failed']],
                    'evidence' => ['type' => 'object'], 'reason' => ['type' => ['string', 'null'], 'maxLength' => 2000],
                ],
                'required' => ['application_id', 'claim_token', 'status'],
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

    private function jobBatchSchema(): array
    {
        $stringList = fn (int $min, int $max): array => ['type' => 'array', 'minItems' => $min, 'maxItems' => $max, 'items' => ['type' => 'string', 'maxLength' => 600]];

        return [
            'type' => 'object',
            'properties' => [
                'run_id' => ['type' => 'string'], 'context_version' => ['type' => 'string'],
                'jobs' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'client_item_id' => ['type' => 'string', 'maxLength' => 128], 'title' => ['type' => 'string', 'maxLength' => 255],
                            'employer' => ['type' => 'string', 'maxLength' => 255], 'canonical_url' => ['type' => 'string', 'format' => 'uri'],
                            'application_url' => ['type' => ['string', 'null'], 'format' => 'uri'], 'application_channel' => ['type' => 'string', 'enum' => ['web', 'email']],
                            'application_email' => ['type' => ['string', 'null'], 'format' => 'email'], 'location' => ['type' => ['string', 'null']],
                            'workplace_type' => ['type' => ['string', 'null']], 'employment_type' => ['type' => ['string', 'null']],
                            'salary' => ['type' => ['object', 'null']], 'posted_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                            'deadline_at' => ['type' => ['string', 'null'], 'format' => 'date-time'], 'summary_points' => $stringList(1, 6),
                            'requirements' => $stringList(0, 12), 'match_points' => $stringList(1, 6), 'gaps' => $stringList(0, 6),
                            'application_instructions' => ['type' => ['string', 'null'], 'maxLength' => 2000],
                            'sources' => [
                                'type' => 'array', 'minItems' => 1, 'maxItems' => 5,
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'], 'url' => ['type' => 'string', 'format' => 'uri'],
                                        'role' => ['type' => 'string', 'enum' => ['primary', 'supporting']],
                                        'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                                    ],
                                    'required' => ['title', 'url', 'role'],
                                ],
                            ],
                            'feedback_tags' => [
                                'type' => 'array', 'minItems' => 4, 'maxItems' => 6,
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'string'], 'label' => ['type' => 'string'],
                                        'signal' => ['type' => 'string', 'enum' => ['more_like_this', 'less_like_this', 'accurate_match', 'inaccurate_match', 'good_source', 'bad_source', 'timely', 'stale', 'eligible', 'ineligible', 'salary_fit', 'salary_mismatch']],
                                    ],
                                    'required' => ['id', 'label', 'signal'],
                                ],
                            ],
                        ],
                        'required' => ['client_item_id', 'title', 'employer', 'canonical_url', 'application_channel', 'summary_points', 'match_points', 'sources', 'feedback_tags'],
                    ],
                ],
            ],
            'required' => ['run_id', 'context_version', 'jobs'],
        ];
    }

    private function claimResourceSchema(array $extraRequired): array
    {
        $properties = ['application_id' => ['type' => 'string'], 'claim_token' => ['type' => 'string']];
        foreach ($extraRequired as $field) {
            $properties[$field] = ['type' => 'string'];
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => ['application_id', 'claim_token', ...$extraRequired]];
    }

    private function questionnaireSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'application_id' => ['type' => 'string'], 'claim_token' => ['type' => 'string'],
                'message' => ['type' => ['string', 'null']],
                'fields' => [
                    'type' => 'array', 'minItems' => 1, 'maxItems' => 20,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'], 'label' => ['type' => 'string'], 'help_text' => ['type' => ['string', 'null']],
                            'type' => ['type' => 'string', 'enum' => ['short_text', 'long_text', 'email', 'phone', 'number', 'date', 'single_select', 'multi_select', 'boolean', 'file']],
                            'classification' => ['type' => 'string', 'enum' => ['normal', 'sensitive', 'legal']],
                            'required' => ['type' => 'boolean'], 'choices' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['id', 'label', 'type', 'classification', 'required'],
                    ],
                ],
            ],
            'required' => ['application_id', 'claim_token', 'fields'],
        ];
    }
}
