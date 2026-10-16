<?php

namespace App\Http\Controllers;

use App\Models\ApplicationDocument;
use App\Models\PersonnelApplication;
use App\Services\ApplicationWorkflowService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Supporting documents are stored on the private "local" disk (storage/app/private)
 * and every download is authorized (BR-016).
 */
class ApplicationDocumentController extends Controller
{
    public function store(Request $request, PersonnelApplication $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('uploadDocument', $application);
        $data = $request->validate([
            'file' => array_merge(['required'], ApplicationWorkflowService::uploadRules()),
            'document_requirement_id' => ['nullable', 'integer'],
        ]);
        $workflow->uploadDocument($request->user(), $application, $request->file('file'), $data['document_requirement_id'] ?? null);

        return back()->with('success', 'Document uploaded. It will be verified by authorized staff.');
    }

    public function show(Request $request, ApplicationDocument $document, AuditLogger $audit): StreamedResponse
    {
        $document->load('application.type', 'requirement');
        Gate::authorize('viewDocument', $document->application);
        if ($document->requirement?->is_sensitive && ! Gate::allows('viewSensitiveDetails', $document->application)) {
            abort(403);
        }
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404, 'The file is no longer available.');
        $audit->log('document.viewed', $document, null, null, 'Supporting document opened');

        $inline = in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true);

        return Storage::disk('local')->response($document->storage_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ], $inline ? 'inline' : 'attachment');
    }

    public function review(Request $request, ApplicationDocument $document, ApplicationWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('verifyDocuments', $document->application);
        $data = $request->validate([
            'review_status' => ['required', 'in:verified,rejected'],
            'review_remarks' => ['nullable', 'string', 'max:255'],
        ]);
        $workflow->reviewDocument($request->user(), $document, $data['review_status'], $data['review_remarks'] ?? null);

        return back()->with('success', 'Document marked as '.$data['review_status'].'.');
    }
}
