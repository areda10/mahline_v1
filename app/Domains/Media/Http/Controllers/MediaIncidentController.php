<?php

declare(strict_types=1);

namespace App\Domains\Media\Http\Controllers;

use App\Domains\Media\Enums\MediaIncidentStatus;
use App\Domains\Media\Models\MediaIncident;
use App\Domains\Media\Services\MediaIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaIncidentController
{
    public function __construct(
        private readonly MediaIncidentService $incidents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->incidents->recordListView($request->user());

        return response()->json([
            'data' => MediaIncident::query()
                ->with(['reporter:id,first_name,last_name,email', 'assignee:id,first_name,last_name'])
                ->withCount('attachments')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
        ]);

        $incident = $this->incidents->create($request->user(), $data['title'], $data['description']);

        return response()->json(['data' => $incident], 201);
    }

    public function show(Request $request, MediaIncident $incident): JsonResponse
    {
        $this->incidents->recordView($request->user(), $incident);

        return response()->json([
            'data' => $incident->load(['reporter:id,first_name,last_name,email', 'assignee:id,first_name,last_name'])
                ->loadCount('attachments'),
        ]);
    }

    public function transition(Request $request, MediaIncident $incident): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:IN_PROGRESS,RESOLVED,CLOSED'],
            'resolution' => ['nullable', 'string', 'max:10000'],
        ]);

        $updated = $this->incidents->transition(
            $request->user(),
            $incident,
            MediaIncidentStatus::from($data['status']),
            $data['resolution'] ?? null,
        );

        return response()->json(['data' => $updated]);
    }

    public function reopen(Request $request, MediaIncident $incident): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        return response()->json([
            'data' => $this->incidents->reopen($request->user(), $incident, $data['reason']),
        ]);
    }

    public function attach(Request $request, MediaIncident $incident): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file'],
        ]);

        $media = $this->incidents->attach($request->user(), $incident, $request->file('file'));

        return response()->json([
            'data' => [
                'id' => $media->getKey(),
                'category' => $media->category,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'visibility' => 'private',
                'show_url' => route('media.private', $media),
            ],
        ], 201);
    }

    private static function safeCsvCell(string $value): string
    {
        return preg_match('/^\\s*[=+@-]/', $value) === 1 ? "'".$value : $value;
    }

    public function export(Request $request): StreamedResponse
    {
        $this->incidents->recordExport($request->user());
        return response()->streamDownload(static function (): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['reference', 'title', 'status', 'reported_by', 'created_at', 'resolved_at', 'closed_at']);

            MediaIncident::query()->orderBy('created_at')->chunk(200, static function ($incidents) use ($output): void {
                foreach ($incidents as $incident) {
                    fputcsv($output, [
                        $incident->reference,
                        self::safeCsvCell($incident->title),
                        $incident->status->value,
                        $incident->reported_by,
                        $incident->created_at?->toIso8601String(),
                        $incident->resolved_at?->toIso8601String(),
                        $incident->closed_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($output);
        }, 'mahline-media-incidents.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
